<?php
session_start();
$active_page = 'sale_list';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$sale = $conn->query("SELECT * FROM customer_sales WHERE id = $id")->fetch_assoc();
if (!$sale) { header("Location: list.php"); exit; }

$customers = $conn->query("SELECT id, customer_name, mobile FROM customers ORDER BY customer_name ASC");

$success = "";
$error   = "";

require_once '../../includes/tank_helper.php';
$tanks_list = resolve_default_tank($conn);
$single_tank = $tanks_list[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no       = trim($_POST['invoice_no']);
    $sale_date        = $_POST['sale_date'];
    $customer_id      = intval($_POST['customer_id'] ?? 0);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $mobile           = trim($_POST['mobile'] ?? '');
    $quantity         = floatval($_POST['quantity'] ?? 0);
    $rate_per_ton     = floatval($_POST['rate_per_ton'] ?? 0);
    $payment_type     = $_POST['payment_type'] ?? 'Cash';
    $total_amount     = $quantity * $rate_per_ton;

    if ($customer_id > 0) {
        $c = $conn->query("SELECT customer_name, mobile FROM customers WHERE id = $customer_id")->fetch_assoc();
        if ($c) { $customer_name = $c['customer_name']; if (empty($mobile)) $mobile = $c['mobile'] ?? ''; }
        else     { $customer_id = 0; }
    }

    if (empty($invoice_no) || empty($sale_date) || $quantity <= 0 || $rate_per_ton <= 0) {
        $error = "Please fill all required fields with valid values.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Reverse old stock adjustments
            $old_stock = $conn->query("SELECT tank_id, quantity FROM stock_ledger WHERE reference_type = 'sale' AND reference_id = $id");
            if ($old_stock) {
                while ($os = $old_stock->fetch_assoc()) {
                    $tid = intval($os['tank_id']);
                    $qty = floatval($os['quantity']);
                    if ($tid > 0 && $qty > 0) {
                        $conn->query("UPDATE tanks SET current_stock = current_stock + $qty WHERE id = $tid");
                    }
                }
            }
            $conn->query("DELETE FROM stock_ledger WHERE reference_type = 'sale' AND reference_id = $id");

            // 2. Reverse old customer ledger entries
            $old_customer_id = intval($sale['customer_id']);
            if ($old_customer_id > 0) {
                $conn->query("DELETE FROM customer_ledger WHERE reference_type = 'sale' AND reference_id = $id");
                $conn->query("DELETE FROM customer_ledger WHERE reference_type = 'payment' AND reference_id = $id");
                $old_bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS b FROM customer_ledger WHERE customer_id = $old_customer_id")->fetch_assoc()['b'];
                $conn->query("UPDATE customers SET balance = $old_bal WHERE id = $old_customer_id");
            }

            // 3. Update customer_sales row
            $waste_kg = floatval($sale['waste_kg'] ?? 0);
            $net_quantity = $quantity - ($waste_kg / 1000);
            $stmt = $conn->prepare("UPDATE customer_sales SET invoice_no=?, customer_id=?, customer_name=?, mobile=?, sale_date=?, quantity=?, net_quantity=?, rate_per_ton=?, total_amount=?, payment_type=? WHERE id=?");
            $cid_val = $customer_id > 0 ? $customer_id : null;
            $stmt->bind_param("sissssdddsi", $invoice_no, $cid_val, $customer_name, $mobile, $sale_date, $quantity, $net_quantity, $rate_per_ton, $total_amount, $payment_type, $id);
            $stmt->execute();
            $stmt->close();

            // 4. Re-apply stock (same tanker data)
            $tanker_rows = $conn->query("SELECT tanker_number, quantity, rate_per_ton FROM stock_ledger WHERE 1=0");
            $old_tanker_stock = $conn->query("SELECT sl.tank_id, sl.quantity, sl.rate, sl.amount, pt.tanker_number FROM purchase_tankers pt JOIN stock_ledger sl ON 1=0 WHERE 1=0");
            $stock_entries = $conn->query("SELECT * FROM stock_ledger WHERE reference_type = 'sale' AND reference_id = " . $id);
            // Use original tanker info from the sale's stock entries (already deleted above)
            // Instead, re-create stock from the sale quantity
            if ($single_tank && $quantity > 0) {
                $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = {$single_tank['id']}")->fetch_assoc();
                if ($tank) {
                    $bal_before = $tank['current_stock'];
                    $bal_after = $bal_before - $quantity;
                    $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = {$single_tank['id']}");
                    $desc = "Sale Invoice #$invoice_no (Edit)";
                    $sl = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, reference_id, quantity, rate, amount, balance_before, balance_after, description, bank_account_id, payment_method) VALUES (?, ?, 'OUT', 'sale', ?, ?, ?, ?, ?, ?, ?, 0, 'Cash')");
                    $sl->bind_param("isiddddds", $single_tank['id'], $sale_date, $id, $quantity, $rate_per_ton, $total_amount, $bal_before, $bal_after, $desc);
                    $sl->execute();
                    $sl->close();
                }
            }

            // 5. Re-post customer ledger
            if ($customer_id > 0) {
                $bal_result = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS b FROM customer_ledger WHERE customer_id=$customer_id");
                $current_bal = $bal_result->fetch_assoc()['b'] ?? 0;
                $new_bal = $current_bal + $total_amount;

                $desc = "Sale Invoice #$invoice_no";
                $ref_type = 'sale';
                $s2 = $conn->prepare("INSERT INTO customer_ledger (customer_id, transaction_date, description, debit, credit, balance, reference_type, reference_id, bank_account_id, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 'Cash')");
                $zero = 0;
                $s2->bind_param("issdddsi", $customer_id, $sale_date, $desc, $total_amount, $zero, $new_bal, $ref_type, $id);
                $s2->execute();
                $s2->close();
                $conn->query("UPDATE customers SET balance=$new_bal WHERE id=$customer_id");
            }

            $conn->commit();
            header("Location: list.php?updated=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = ($conn->errno === 1062) ? "Invoice number '$invoice_no' already exists." : "Database error: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Edit Sale #<?= htmlspecialchars($sale['invoice_no']) ?></h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>

<form method="POST" id="saleForm">
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> Sale Information</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Sale Invoice No <span class="text-danger">*</span></label>
                    <input type="text" name="invoice_no" class="form-control" required value="<?= htmlspecialchars($_POST['invoice_no'] ?? $sale['invoice_no']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Sale Date <span class="text-danger">*</span></label>
                    <input type="date" name="sale_date" class="form-control" required value="<?= htmlspecialchars($_POST['sale_date'] ?? $sale['sale_date']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customer_select" class="form-control" required>
                        <option value="">-- Select Customer --</option>
                        <option value="0" <?= (isset($_POST['customer_id']) && $_POST['customer_id']=='0') ? 'selected':'' ?>>Walk-in Customer</option>
                        <?php if ($customers && $customers->num_rows > 0): $customers->data_seek(0);
                            while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" data-mobile="<?= htmlspecialchars($c['mobile']??'') ?>"
                            <?= ((isset($_POST['customer_id']) ? $_POST['customer_id'] : $sale['customer_id']) == $c['id']) ? 'selected':'' ?>>
                            <?= htmlspecialchars($c['customer_name']) ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3" id="walkin_name_group" style="display:none">
                <div class="form-group">
                    <label class="small font-weight-bold">Walk-in Name <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Enter name" value="<?= htmlspecialchars($_POST['customer_name'] ?? $sale['customer_name']) ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Mobile</label>
                    <input type="text" name="mobile" id="customer_mobile" class="form-control" value="<?= htmlspecialchars($_POST['mobile'] ?? $sale['mobile']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Quantity (Ton) <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0" name="quantity" id="quantity" class="form-control" required value="<?= htmlspecialchars($_POST['quantity'] ?? $sale['quantity']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Rate/Ton <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="rate_per_ton" id="rate_per_ton" class="form-control" required value="<?= htmlspecialchars($_POST['rate_per_ton'] ?? $sale['rate_per_ton']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Total Amount</label>
                    <input type="text" id="total_display" class="form-control bg-light" readonly value="<?= number_format($sale['total_amount'], 2) ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Payment Type</label>
                    <select name="payment_type" class="form-control">
                        <option value="Cash" <?= (($_POST['payment_type'] ?? $sale['payment_type']) === 'Cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="Credit" <?= (($_POST['payment_type'] ?? $sale['payment_type']) === 'Credit') ? 'selected' : '' ?>>Credit</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between mb-4">
    <button type="submit" class="btn btn-primary btn-lg px-5 shadow"><i class="fas fa-save mr-1"></i> Update Sale</button>
    <a href="list.php" class="btn btn-secondary btn-lg px-4 shadow"><i class="fas fa-times mr-1"></i> Cancel</a>
</div>
</form>

<script>
const qtyEl = document.getElementById('quantity');
const rateEl = document.getElementById('rate_per_ton');
const totalEl = document.getElementById('total_display');
function calcTotal() {
    const q = parseFloat(qtyEl.value) || 0;
    const r = parseFloat(rateEl.value) || 0;
    totalEl.value = (q * r).toFixed(2);
}
qtyEl.addEventListener('input', calcTotal);
rateEl.addEventListener('input', calcTotal);

const custSel     = document.getElementById('customer_select');
const walkinGrp   = document.getElementById('walkin_name_group');
const custNameInp = document.getElementById('customer_name');
const mobileInp   = document.getElementById('customer_mobile');
custSel.addEventListener('change', function() {
    if (this.value==='0')  { walkinGrp.style.display=''; custNameInp.required=true; mobileInp.value=''; }
    else if(this.value==='') { walkinGrp.style.display='none'; custNameInp.required=false; }
    else { walkinGrp.style.display='none'; custNameInp.required=false; mobileInp.value=this.options[this.selectedIndex].dataset.mobile||''; }
});
(function(){ const v=custSel.value; if(v==='0'){walkinGrp.style.display='';custNameInp.required=true;}else{walkinGrp.style.display='none';custNameInp.required=false;} })();
</script>

<?php include '../../includes/footer.php'; ?>
