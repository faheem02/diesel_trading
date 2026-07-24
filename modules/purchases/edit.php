<?php
session_start();
$active_page = 'purchase_list';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$purchase = $conn->query("SELECT * FROM purchases WHERE id = $id")->fetch_assoc();
if (!$purchase) { header("Location: list.php"); exit; }

$existing_tankers = $conn->query("SELECT * FROM purchase_tankers WHERE purchase_id = $id ORDER BY id ASC");

$success = "";
$error = "";

require_once '../../includes/tank_helper.php';
$tanks_list = resolve_default_tank($conn);
$single_tank = $tanks_list[0]; // default tank always exists (auto-created if table was empty)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no      = trim($_POST['invoice_no']);
    $purchase_date   = $_POST['purchase_date'];
    $supplier_id     = intval($_POST['supplier_id']);
    $payment_status  = $_POST['payment_status'];
    $paid_amount     = floatval($_POST['paid_amount'] ?? 0);

    if (empty($invoice_no) || empty($purchase_date) || $supplier_id <= 0) {
        $error = "Please fill all required fields.";
    } elseif (!isset($_POST['tankers']) || count($_POST['tankers']) < 1) {
        $error = "Please add at least one tanker entry.";
    } else {
        $tankers = $_POST['tankers'];
        if ($single_tank) {
            foreach ($tankers as &$t) { $t['tank_id'] = $single_tank['id']; }
            unset($t);
        }
        $total_qty = 0;
        $total_amount = 0;

        foreach ($tankers as $t) {
            $qty  = floatval($t['diesel_quantity'] ?? 0);
            $rate = floatval($t['rate_per_ton'] ?? 0);
            $total_qty += $qty;
            $total_amount += ($qty * $rate);
        }

        $weighted_rate = $total_qty > 0 ? $total_amount / $total_qty : 0;

        if (empty($error)) {
            $conn->begin_transaction();
            try {
                $old_tankers = $conn->query("SELECT tank_id, diesel_quantity FROM purchase_tankers WHERE purchase_id = $id");
                while ($ot = $old_tankers->fetch_assoc()) {
                    if ($ot['tank_id'] > 0) {
                        $conn->query("UPDATE tanks SET current_stock = current_stock - {$ot['diesel_quantity']} WHERE id = {$ot['tank_id']}");
                        $conn->query("DELETE FROM stock_ledger WHERE reference_type = 'purchase' AND reference_id = $id AND tank_id = {$ot['tank_id']}");
                    }
                }
                $conn->query("DELETE FROM purchase_tankers WHERE purchase_id = $id");

                $conn->query("DELETE FROM supplier_ledger WHERE reference_type = 'purchase' AND description LIKE '%Invoice #" . $purchase['invoice_no'] . "%'");
                if ($purchase['payment_status'] !== 'Credit') {
                    $conn->query("DELETE FROM supplier_ledger WHERE reference_type = 'payment' AND description LIKE '%Invoice #" . $purchase['invoice_no'] . "%'");
                }

                $stmt = $conn->prepare("UPDATE purchases SET invoice_no=?, purchase_date=?, supplier_id=?, diesel_quantity=?, rate_per_ton=?, total_amount=?, freight_charges=?, net_purchase_cost=?, payment_status=?, paid_amount=? WHERE id=?");
                $stmt->bind_param("ssiddddddi", $invoice_no, $purchase_date, $supplier_id, $total_qty, $weighted_rate, $total_amount, 0, $total_amount, $payment_status, $paid_amount, $id);
                $stmt->execute();
                $stmt->close();

                $tanker_stmt = $conn->prepare("INSERT INTO purchase_tankers (purchase_id, tank_id, tanker_number, driver_name, driver_mobile, diesel_quantity, rate_per_ton, total_amount, freight_charges, net_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($tankers as $t) {
                    $tank_id     = intval($t['tank_id'] ?? 0);
                    $tanker_no   = trim($t['tanker_number'] ?? '');
                    $driver_name = trim($t['driver_name'] ?? '');
                    $driver_mob  = trim($t['driver_mobile'] ?? '');
                    $qty         = floatval($t['diesel_quantity'] ?? 0);
                    $rate        = floatval($t['rate_per_ton'] ?? 0);
                    $t_total     = $qty * $rate;
                    $tank_id_val = ($tank_id > 0) ? $tank_id : null;
                    $zero = 0;
                    $tanker_stmt->bind_param("iisssddddd", $id, $tank_id_val, $tanker_no, $driver_name, $driver_mob, $qty, $rate, $t_total, $zero, $t_total);
                    $tanker_stmt->execute();

                    if ($tank_id > 0) {
                        $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = $tank_id")->fetch_assoc();
                        if ($tank) {
                            $bal_before = $tank['current_stock'];
                            $bal_after  = $bal_before + $qty;
                            $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = $tank_id");
                            $desc = "Purchase Invoice #$invoice_no (Tanker: $tanker_no)";
                            $sl = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, reference_id, quantity, rate, amount, balance_before, balance_after, description) VALUES (?, ?, 'IN', 'purchase', ?, ?, ?, ?, ?, ?, ?)");
                            $sl->bind_param("isiddddds", $tank_id, $purchase_date, $id, $qty, $rate, $t_total, $bal_before, $bal_after, $desc);
                            $sl->execute();
                            $sl->close();
                        }
                    }
                }
                $tanker_stmt->close();

                $ledger_desc = "Purchase Invoice #$invoice_no" . ($payment_status === 'Paid' ? " (Paid $ " . number_format($paid_amount, 0) . ")" : "");
                $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type, reference_id) VALUES ($supplier_id, '$purchase_date', '$ledger_desc', 0, $total_amount, 0, 'purchase', $id)");
                $entry_id = $conn->insert_id;
                $bal = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                $conn->query("UPDATE supplier_ledger SET balance = $bal WHERE id = $entry_id");

                if (($payment_status === 'Paid' || $payment_status === 'Partial Paid') && $paid_amount > 0) {
                    $pay_desc = "Payment against Invoice #$invoice_no";
                    $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type, bank_account_id, payment_method) VALUES ($supplier_id, '$purchase_date', '$pay_desc', $paid_amount, 0, 0, 'payment', NULL, 'Cash')");
                    $e2 = $conn->insert_id;
                    $b2 = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                    $conn->query("UPDATE supplier_ledger SET balance = $b2 WHERE id = $e2");
                }

                $final_bal = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                $conn->query("UPDATE suppliers SET balance = $final_bal WHERE id = $supplier_id");

                $conn->commit();
                header("Location: list.php?updated=1");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = ($conn->errno === 1062) ? "Invoice number already exists." : "Database error: " . $e->getMessage();
            }
        }
    }
}

$suppliers = $conn->query("SELECT id, company_name FROM suppliers ORDER BY company_name");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Edit Purchase #<?= htmlspecialchars($purchase['invoice_no']) ?></h1>
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

<form method="POST" id="purchaseForm">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> Invoice Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Invoice No <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_no" class="form-control" required value="<?= htmlspecialchars($_POST['invoice_no'] ?? $purchase['invoice_no']) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" name="purchase_date" class="form-control" required value="<?= htmlspecialchars($_POST['purchase_date'] ?? $purchase['purchase_date']) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php while ($row = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>" <?= ((($_POST['supplier_id'] ?? $purchase['supplier_id']) == $row['id']) ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Status <span class="text-danger">*</span></label>
                        <select name="payment_status" id="payment_status" class="form-control" required>
                            <option value="Paid"     <?= (($_POST['payment_status'] ?? $purchase['payment_status']) === 'Paid') ? 'selected' : '' ?>>Paid</option>
                            <option value="Partial Paid" <?= (($_POST['payment_status'] ?? $purchase['payment_status']) === 'Partial Paid') ? 'selected' : '' ?>>Partial Paid</option>
                            <option value="Credit"   <?= (($_POST['payment_status'] ?? $purchase['payment_status']) === 'Credit') ? 'selected' : '' ?>>Credit</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Paid Amount</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" class="form-control" value="<?= htmlspecialchars($_POST['paid_amount'] ?? $purchase['paid_amount']) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck mr-1"></i> Tanker Entries</h6>
            <button type="button" class="btn btn-sm btn-primary" id="addTankerBtn"><i class="fas fa-plus"></i> Add Tanker</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="tankersTable">
                    <thead class="thead-light">
                        <tr>
                            <th style="min-width:120px">Tanker No</th>
                            <th style="min-width:120px">Driver Name</th>
                            <th style="min-width:110px">Driver Mobile</th>
                            <th style="min-width:100px">Qty (Ton)</th>
                            <th style="min-width:90px">Rate/Ton</th>
                            <th style="min-width:100px">Total ($)</th>
                            <th style="width:50px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tankersBody">
                        <?php
                        $existing_tankers->data_seek(0);
                        $ei = 0;
                        while ($et = $existing_tankers->fetch_assoc()):
                        ?>
                        <tr class="tanker-row">
                            <input type="hidden" name="tankers[<?= $ei ?>][tank_id]" value="<?= $single_tank['id'] ?>">
                            <td><input type="text" name="tankers[<?= $ei ?>][tanker_number]" class="form-control form-control-sm" value="<?= htmlspecialchars($et['tanker_number']) ?>"></td>
                            <td><input type="text" name="tankers[<?= $ei ?>][driver_name]" class="form-control form-control-sm" value="<?= htmlspecialchars($et['driver_name']) ?>"></td>
                            <td><input type="text" name="tankers[<?= $ei ?>][driver_mobile]" class="form-control form-control-sm" value="<?= htmlspecialchars($et['driver_mobile']) ?>"></td>
                            <td><input type="number" step="0.001" min="0" name="tankers[<?= $ei ?>][diesel_quantity]" class="form-control form-control-sm tanker-qty" value="<?= $et['diesel_quantity'] ?>" required></td>
                            <td><input type="number" step="0.01" min="0" name="tankers[<?= $ei ?>][rate_per_ton]" class="form-control form-control-sm tanker-rate" value="<?= $et['rate_per_ton'] ?>" required></td>
                            <td><input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="<?= number_format($et['total_amount'], 2) ?>"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-tanker"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php $ei++; endwhile; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr>
                            <th colspan="3" class="text-right">Totals:</th>
                            <th><span id="totalQty">0.000</span></th>
                            <th></th>
                            <th><span id="grandTotal">0.00</span></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Purchase</button>
        <a href="list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
    </div>
</form>

<script>
let tankerIndex = <?= $ei ?>;

function calculateRow(row) {
    const qty = parseFloat(row.querySelector('.tanker-qty').value) || 0;
    const rate = parseFloat(row.querySelector('.tanker-rate').value) || 0;
    row.querySelector('.tanker-total').value = (qty * rate).toFixed(2);
    calculateGrandTotals();
}

function calculateGrandTotals() {
    let totalQty = 0, grandTotal = 0;
    document.querySelectorAll('#tankersBody .tanker-row').forEach(row => {
        totalQty   += parseFloat(row.querySelector('.tanker-qty').value) || 0;
        grandTotal += parseFloat(row.querySelector('.tanker-total').value) || 0;
    });
    document.getElementById('totalQty').textContent = totalQty.toFixed(3);
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
}

document.getElementById('addTankerBtn').addEventListener('click', function() {
    const tbody = document.getElementById('tankersBody');
    const row = document.createElement('tr');
    row.className = 'tanker-row';
    const i = tankerIndex++;
    row.innerHTML = `
        <input type="hidden" name="tankers[${i}][tank_id]" value="<?= $single_tank['id'] ?>">
        <td><input type="text" name="tankers[${i}][tanker_number]" class="form-control form-control-sm"></td>
        <td><input type="text" name="tankers[${i}][driver_name]" class="form-control form-control-sm"></td>
        <td><input type="text" name="tankers[${i}][driver_mobile]" class="form-control form-control-sm"></td>
        <td><input type="number" step="0.001" min="0" name="tankers[${i}][diesel_quantity]" class="form-control form-control-sm tanker-qty" required></td>
        <td><input type="number" step="0.01" min="0" name="tankers[${i}][rate_per_ton]" class="form-control form-control-sm tanker-rate" required></td>
        <td><input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-tanker"><i class="fas fa-trash"></i></button></td>`;
    row.querySelectorAll('.tanker-qty, .tanker-rate').forEach(inp => inp.addEventListener('input', () => calculateRow(row)));
    row.querySelector('.remove-tanker').addEventListener('click', () => { row.remove(); calculateGrandTotals(); });
    tbody.appendChild(row);
});

document.querySelectorAll('#tankersBody .tanker-row').forEach(row => {
    row.querySelectorAll('.tanker-qty, .tanker-rate').forEach(inp => inp.addEventListener('input', () => calculateRow(row)));
    row.querySelector('.remove-tanker').addEventListener('click', () => { row.remove(); calculateGrandTotals(); });
});

const paymentStatus  = document.getElementById('payment_status');
const paidAmountEl   = document.getElementById('paid_amount');

function togglePaidAmount() {
    if (paymentStatus.value === 'Credit') {
        paidAmountEl.value = 0; paidAmountEl.readOnly = true;
    } else {
        paidAmountEl.readOnly = false;
    }
}

paymentStatus.addEventListener('change', togglePaidAmount);
togglePaidAmount();
calculateGrandTotals();
</script>

<?php include '../../includes/footer.php'; ?>
