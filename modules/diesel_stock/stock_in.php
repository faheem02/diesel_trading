<?php
session_start();
$active_page = 'stock_in';
require_once '../../config/db.php';

$success = "";
$error = "";

$tanks = $conn->query("SELECT id, tank_name, current_stock FROM tanks ORDER BY tank_name ASC");
$bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, account_type, current_balance FROM bank_accounts ORDER BY account_type ASC, account_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tank_id         = intval($_POST['tank_id']);
    $stock_date      = $_POST['stock_date'] ?? date('Y-m-d');
    $quantity        = floatval($_POST['quantity'] ?? 0);
    $rate            = floatval($_POST['rate'] ?? 0);
    $payment_method  = trim($_POST['payment_method'] ?? 'Cash');
    $bank_account_id = intval($_POST['bank_account_id'] ?? 0);
    
    // If Cash is selected, ensure we use the cash account ID
    if ($payment_method === 'Cash' && $bank_account_id <= 0) {
        $cash_acc = $conn->query("SELECT id FROM bank_accounts WHERE account_type = 'Cash' LIMIT 1")->fetch_assoc();
        if ($cash_acc) $bank_account_id = $cash_acc['id'];
    }

    $supplier_name   = trim($_POST['supplier_name'] ?? '');
    $invoice_ref     = trim($_POST['invoice_ref'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if ($tank_id <= 0 || $quantity <= 0) {
        $error = "Please select a tank and enter a valid quantity.";
    } else {
        $total_amount = $quantity * $rate;
        $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = $tank_id")->fetch_assoc();
        
        if (!$tank) {
            $error = "Tank not found.";
        } else {
            $bal_before = $tank['current_stock'];
            $bal_after  = $bal_before + $quantity;
            
            // Valuation
            $val_before = $bal_before * $rate;
            $val_after  = $bal_after * $rate;

            $desc_parts = [];
            if ($supplier_name) $desc_parts[] = "Supplier: $supplier_name";
            if ($invoice_ref) $desc_parts[] = "Invoice: $invoice_ref";
            if ($notes) $desc_parts[] = $notes;
            $description = "Stock In" . ($desc_parts ? " - " . implode(", ", $desc_parts) : "") . ($total_amount > 0 ? " (Paid Rs. ".number_format($total_amount,0).")" : "");

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, bank_account_id, payment_method, quantity, rate, amount, balance_before, balance_before_value, balance_after, balance_after_value, description) VALUES (?, ?, 'IN', 'purchase', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissddddddds", $tank_id, $stock_date, $bank_account_id, $payment_method, $quantity, $rate, $total_amount, $bal_before, $val_before, $bal_after, $val_after, $description);
                $stmt->execute();
                $stmt->close();

                $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = $tank_id");

                // Update Cash/Bank balance if account is selected
                if ($total_amount > 0 && $bank_account_id > 0) {
                    $conn->query("UPDATE bank_accounts SET current_balance = current_balance - $total_amount WHERE id = $bank_account_id");
                }

                $conn->commit();
                $success = "Stock In recorded successfully! Quantity: " . number_format($quantity, 3) . " tons.";
                if ($total_amount > 0) $success .= " Cash book updated.";
                $_POST = [];
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    $tanks = $conn->query("SELECT id, tank_name, current_stock FROM tanks ORDER BY tank_name ASC");
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-arrow-down mr-1"></i> Stock In</h1>
    <div>
        <a href="stock_in_list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> Stock In History
        </a>
        <a href="tanks.php" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm">
            <i class="fas fa-oil-can"></i> Tank Wise Stock
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks mr-1"></i> Stock In Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="stock_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['stock_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank <span class="text-danger">*</span></label>
                        <select name="tank_id" class="form-control" required>
                            <option value="">-- Select Tank --</option>
                            <?php while ($t = $tanks->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>"
                                    <?= (isset($_POST['tank_id']) && $_POST['tank_id'] == $t['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['tank_name']) ?> (Stock: <?= number_format($t['current_stock'], 3) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Quantity (Tons) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" id="qty" class="form-control" required
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Rate per Ton (Rs.)</label>
                        <input type="number" step="0.01" min="0" name="rate" id="rate" class="form-control"
                               value="<?= htmlspecialchars($_POST['rate'] ?? '') ?>">
                        <small class="text-muted">Enter to record payment in cash book.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Total Amount (Rs.)</label>
                        <input type="text" id="total_amount" class="form-control bg-light" readonly value="0.00">
                    </div>
                </div>
            </div>
            <div class="row border-top pt-3 mt-3">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash" <?= (!isset($_POST['payment_method']) || $_POST['payment_method']=='Cash') ? 'selected':'' ?>>Cash</option>
                            <option value="Bank" <?= (isset($_POST['payment_method']) && $_POST['payment_method']=='Bank') ? 'selected':'' ?>>Bank</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="bank_account_group">
                    <div class="form-group">
                        <label class="small font-weight-bold">Select Bank Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="form-control">
                            <option value="">-- Select Bank --</option>
                            <?php if ($bank_accounts && $bank_accounts->num_rows > 0):
                                $bank_accounts->data_seek(0);
                                while ($b = $bank_accounts->fetch_assoc()): 
                                    if($b['account_type'] !== 'Bank') continue; ?>
                                <option value="<?= $b['id'] ?>"
                                    <?= (isset($_POST['bank_account_id']) && $_POST['bank_account_id'] == $b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['bank_name'] . " - " . $b['account_name']) ?> (Bal: <?= number_format($b['current_balance'], 2) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="cash_account_group">
                    <input type="hidden" name="cash_account_id" id="cash_account_id" value="<?php 
                        $bank_accounts->data_seek(0);
                        while($b = $bank_accounts->fetch_assoc()) { if($b['account_type'] == 'Cash') { echo $b['id']; break; } } 
                    ?>">
                </div>
            </div>
            <div class="row border-top pt-3 mt-3">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Supplier Name</label>
                        <input type="text" name="supplier_name" class="form-control" placeholder="Supplier name (optional)"
                               value="<?= htmlspecialchars($_POST['supplier_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Invoice Reference</label>
                        <input type="text" name="invoice_ref" class="form-control" placeholder="Purchase invoice # (optional)"
                               value="<?= htmlspecialchars($_POST['invoice_ref'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Any notes"
                               value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary btn-lg shadow px-5">
            <i class="fas fa-save mr-1"></i> Record Stock In
        </button>
        <a href="stock_in_list.php" class="btn btn-secondary btn-lg shadow px-4">
            <i class="fas fa-times mr-1"></i> Cancel
        </a>
    </div>
</form>

<script>
document.getElementById('qty').addEventListener('input', calcTotal);
document.getElementById('rate').addEventListener('input', calcTotal);

function calcTotal() {
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const rate = parseFloat(document.getElementById('rate').value) || 0;
    document.getElementById('total_amount').value = (qty * rate).toFixed(2);
}

const pmSelect = document.getElementById('payment_method');
const bankGroup = document.getElementById('bank_account_group');
const bankSelect = document.getElementById('bank_account_id');

function togglePaymentFields() {
    if (pmSelect.value === 'Bank') {
        bankGroup.style.display = '';
        bankSelect.required = true;
    } else {
        bankGroup.style.display = 'none';
        bankSelect.required = false;
        bankSelect.value = '';
    }
}

pmSelect.addEventListener('change', togglePaymentFields);
togglePaymentFields();
calcTotal();
</script>

<?php include '../../includes/footer.php'; ?>
