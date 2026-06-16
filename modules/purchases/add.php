<?php
session_start();
$active_page = 'purchase_add';
require_once '../../config/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $invoice_no       = trim($_POST['invoice_no']);
    $purchase_date    = $_POST['purchase_date'];
    $supplier_id      = intval($_POST['supplier_id']);
    $payment_status   = $_POST['payment_status'];
    $paid_amount      = floatval($_POST['paid_amount']);
    $bank_account_id  = intval($_POST['bank_account_id'] ?? 0);
    $payment_method   = trim($_POST['payment_method'] ?? 'Cash');

    if (empty($invoice_no) || empty($purchase_date) || $supplier_id <= 0) {
        $error = "Please fill all required fields with valid values.";
    } elseif (!isset($_POST['tankers']) || count($_POST['tankers']) < 1) {
        $error = "Please add at least one tanker entry.";
    } else {
        $tankers = $_POST['tankers'];
        $total_qty = 0;
        $total_waste = 0;
        $total_net_qty = 0;
        $total_amount = 0;
        $total_freight = 0;
        $total_other = 0;
        $total_net = 0;

        foreach ($tankers as $t) {
            $qty = floatval($t['diesel_quantity'] ?? 0);
            $rate = floatval($t['rate_per_ton'] ?? 0);
            $freight = floatval($t['freight_charges'] ?? 0);
            $other = floatval($t['other_charges'] ?? 0);
            $t_total = $qty * $rate;
            $t_net = $t_total + $freight + $other;
            $waste = ($qty / 35) * 50;
            $net_qty = $qty - ($waste / 1000);

            $total_qty += $qty;
            $total_waste += $waste;
            $total_net_qty += $net_qty;
            $total_amount += $t_total;
            $total_freight += $freight;
            $total_other += $other;
            $total_net += $t_net;
        }

        $weighted_rate = $total_qty > 0 ? $total_amount / $total_qty : 0;

        if (empty($error)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO purchases 
                    (invoice_no, purchase_date, supplier_id, diesel_quantity, waste_kg, net_quantity, rate_per_ton,
                     total_amount, freight_charges, other_charges, net_purchase_cost,
                     payment_status, paid_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssiddddddddds",
                    $invoice_no,
                    $purchase_date,
                    $supplier_id,
                    $total_qty,
                    $total_waste,
                    $total_net_qty,
                    $weighted_rate,
                    $total_amount,
                    $total_freight,
                    $total_other,
                    $total_net,
                    $payment_status,
                    $paid_amount
                );

                $stmt->execute();
                $purchase_id = $conn->insert_id;
                $stmt->close();

                $tanker_stmt = $conn->prepare("INSERT INTO purchase_tankers 
                    (purchase_id, tank_id, tanker_number, driver_name, driver_mobile,
                     diesel_quantity, waste_kg, net_quantity, rate_per_ton, total_amount,
                     freight_charges, other_charges, net_amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($tankers as $t) {
                    $tank_id     = intval($t['tank_id'] ?? 0);
                    $tanker_no   = trim($t['tanker_number'] ?? '');
                    $driver_name = trim($t['driver_name'] ?? '');
                    $driver_mob  = trim($t['driver_mobile'] ?? '');
                    $qty         = floatval($t['diesel_quantity'] ?? 0);
                    $rate        = floatval($t['rate_per_ton'] ?? 0);
                    $freight     = floatval($t['freight_charges'] ?? 0);
                    $other       = floatval($t['other_charges'] ?? 0);
                    $t_total     = $qty * $rate;
                    $t_net       = $t_total + $freight + $other;
                    $waste       = ($qty / 35) * 50;
                    $net_qty     = $qty - ($waste / 1000);

                    $tank_id_val = ($tank_id > 0) ? $tank_id : null;
                    $tanker_stmt->bind_param("iisssdddddddd",
                        $purchase_id, $tank_id_val, $tanker_no, $driver_name, $driver_mob,
                        $qty, $waste, $net_qty, $rate, $t_total, $freight, $other, $t_net
                    );
                    $tanker_stmt->execute();

                    // Update Stock if Tank is selected
                    if ($tank_id > 0) {
                        $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = $tank_id")->fetch_assoc();
                        if ($tank) {
                            $bal_before = $tank['current_stock'];
                            $bal_after  = $bal_before + $qty;
                            
                            $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = $tank_id");
                            
                            $stock_desc = "Purchase Invoice #$invoice_no (Tanker: $tanker_no)";
                            $stmt_sl = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, reference_id, quantity, rate, amount, balance_before, balance_after, description) VALUES (?, ?, 'IN', 'purchase', ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_sl->bind_param("isiddddds", $tank_id, $purchase_date, $purchase_id, $qty, $rate, $t_total, $bal_before, $bal_after, $stock_desc);
                            $stmt_sl->execute();
                            $stmt_sl->close();
                        }
                    }
                }
                $tanker_stmt->close();

                $ledger_desc = "Purchase Invoice #$invoice_no" . ($payment_status === 'Paid' ? " (Paid Rs. " . number_format($paid_amount, 0) . ")" : "");
                $ledger_debit = $total_net;
                if ($payment_status === 'Paid' || $payment_status === 'Partial Paid') {
                    $paid = min($paid_amount, $total_net);
                    $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($supplier_id, '$purchase_date', '$ledger_desc', $ledger_debit, 0, 0, 'purchase')");
                    $entry_id = $conn->insert_id;
                    $bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                    $conn->query("UPDATE supplier_ledger SET balance = $bal WHERE id = $entry_id");

                    if ($paid > 0) {
                        $pay_desc = "Payment against Invoice #$invoice_no";
                        $bank_id_for_ledger = ($bank_account_id > 0) ? $bank_account_id : null;
                        $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type, bank_account_id, payment_method) VALUES ($supplier_id, '$purchase_date', '$pay_desc', 0, $paid, 0, 'payment', " . ($bank_id_for_ledger ?: 'NULL') . ", '$payment_method')");
                        $entry_id2 = $conn->insert_id;
                        $bal2 = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                        $conn->query("UPDATE supplier_ledger SET balance = $bal2 WHERE id = $entry_id2");
                        if ($bank_id_for_ledger) {
                            $conn->query("UPDATE bank_accounts SET current_balance = current_balance - $paid WHERE id = $bank_account_id");
                        }
                    }
                    $final_bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                    $conn->query("UPDATE suppliers SET balance = $final_bal WHERE id = $supplier_id");
                } else {
                    $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($supplier_id, '$purchase_date', '$ledger_desc', $ledger_debit, 0, 0, 'purchase')");
                    $entry_id = $conn->insert_id;
                    $bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                    $conn->query("UPDATE supplier_ledger SET balance = $bal WHERE id = $entry_id");
                    $conn->query("UPDATE suppliers SET balance = $bal WHERE id = $supplier_id");
                }

                $conn->commit();
                $success = "Purchase entry saved and stock updated successfully with " . count($tankers) . " tanker(s)!";
                $_POST = [];
            } catch (Exception $e) {
                $conn->rollback();
                if ($conn->errno === 1062) {
                    $error = "Invoice number already exists.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

$suppliers = $conn->query("SELECT id, company_name FROM suppliers ORDER BY company_name");
$bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, account_type, current_balance FROM bank_accounts ORDER BY account_type ASC, account_name ASC");
$tanks_res = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name");
$tanks_list = [];
while($t = $tanks_res->fetch_assoc()) $tanks_list[] = $t;

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Diesel Purchase Entry</h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to List
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

    <form method="POST" id="purchaseForm">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> Invoice Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Purchase Invoice No <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_no" class="form-control" required
                               value="<?= htmlspecialchars($_POST['invoice_no'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" name="purchase_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Company / Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php while ($row = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>"
                                    <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $row['id']) ? 'selected' : '' ?>>
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
                            <option value="Paid" <?= (isset($_POST['payment_status']) && $_POST['payment_status']=='Paid') ? 'selected':'' ?>>Paid</option>
                            <option value="Partial Paid" <?= (isset($_POST['payment_status']) && $_POST['payment_status']=='Partial Paid') ? 'selected':'' ?>>Partial Paid</option>
                            <option value="Credit" <?= (!isset($_POST['payment_status']) || $_POST['payment_status']=='Credit') ? 'selected':'' ?>>Credit</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Paid Amount</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" class="form-control"
                               value="<?= htmlspecialchars($_POST['paid_amount'] ?? '0') ?>">
                    </div>
                </div>
                <div class="col-md-4" id="payment_account_group">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash"          <?= (!isset($_POST['payment_method']) || $_POST['payment_method']==='Cash')          ? 'selected':'' ?>>Cash</option>
                            <option value="Bank Transfer" <?= (isset($_POST['payment_method']) && $_POST['payment_method']==='Bank Transfer') ? 'selected':'' ?>>Bank Transfer</option>
                            <option value="Cheque"        <?= (isset($_POST['payment_method']) && $_POST['payment_method']==='Cheque')        ? 'selected':'' ?>>Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="bank_account_group">
                    <div class="form-group">
                        <label class="small font-weight-bold" id="acct_label">Cash Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="form-control">
                            <option value="">-- Select Account --</option>
                            <?php
                            $bank_accounts->data_seek(0);
                            while ($b = $bank_accounts->fetch_assoc()):
                                $display = htmlspecialchars($b['account_name']);
                                if ($b['bank_name'])      $display = htmlspecialchars($b['bank_name']) . ' — ' . $display;
                                if ($b['account_number']) $display .= ' (' . htmlspecialchars($b['account_number']) . ')';
                                $display .= ' | Bal: ' . number_format($b['current_balance'], 2);
                            ?>
                                <option value="<?= $b['id'] ?>"
                                    data-type="<?= $b['account_type'] ?>"
                                    <?= (isset($_POST['bank_account_id']) && $_POST['bank_account_id'] == $b['id']) ? 'selected' : '' ?>>
                                    [<?= $b['account_type'] ?>] <?= $display ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck mr-1"></i> Tanker Entries</h6>
            <button type="button" class="btn btn-sm btn-primary" id="addTankerBtn">
                <i class="fas fa-plus"></i> Add Tanker
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="tankersTable">
                    <thead class="thead-light">
                        <tr>
                            <th style="min-width:140px">Tank <span class="text-danger">*</span></th>
                            <th style="min-width:120px">Tanker No</th>
                            <th style="min-width:120px">Driver Name</th>
                            <th style="min-width:110px">Driver Mobile</th>
                            <th style="min-width:100px">Qty (Ton)</th>
                            <th style="min-width:90px">Waste (Kg)</th>
                            <th style="min-width:100px">Net Qty (Ton)</th>
                            <th style="min-width:90px">Rate/Ton</th>
                            <th style="min-width:100px">Total</th>
                            <th style="min-width:90px">Freight</th>
                            <th style="min-width:90px">Other</th>
                            <th style="min-width:100px">Net Amount</th>
                            <th style="width:50px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tankersBody">
                        <tr class="tanker-row">
                            <td>
                                <select name="tankers[0][tank_id]" class="form-control form-control-sm" required>
                                    <option value="">-- Tank --</option>
                                    <?php foreach($tanks_list as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['tank_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="tankers[0][tanker_number]" class="form-control form-control-sm" placeholder="Tanker No">
                            </td>
                            <td>
                                <input type="text" name="tankers[0][driver_name]" class="form-control form-control-sm" placeholder="Driver Name">
                            </td>
                            <td>
                                <input type="text" name="tankers[0][driver_mobile]" class="form-control form-control-sm" placeholder="Mobile">
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0" name="tankers[0][diesel_quantity]" class="form-control form-control-sm tanker-qty" required>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm tanker-waste bg-light" readonly value="0.000">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm tanker-net-qty bg-light" readonly value="0.000">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="tankers[0][rate_per_ton]" class="form-control form-control-sm tanker-rate" required>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="tankers[0][freight_charges]" class="form-control form-control-sm tanker-freight" value="0">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="tankers[0][other_charges]" class="form-control form-control-sm tanker-other" value="0">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm tanker-net font-weight-bold bg-light" readonly value="0.00">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-danger remove-tanker" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-active">
                        <tr>
                            <th colspan="4" class="text-right">Totals:</th>
                            <th><span id="totalQty">0.000</span></th>
                            <th><span id="totalWaste">0.000</span></th>
                            <th><span id="totalNetQty">0.000</span></th>
                            <th></th>
                            <th><span id="grandTotal">0.00</span></th>
                            <th><span id="grandFreight">0.00</span></th>
                            <th><span id="grandOther">0.00</span></th>
                            <th><span id="grandNet">0.00</span></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Purchase & Update Stock
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<script>
let tankerIndex = 1;
const tanksOptions = `<?php foreach($tanks_list as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['tank_name']) ?></option><?php endforeach; ?>`;

function calculateRow(row) {
    const qty = parseFloat(row.querySelector('.tanker-qty').value) || 0;
    const rate = parseFloat(row.querySelector('.tanker-rate').value) || 0;
    const freight = parseFloat(row.querySelector('.tanker-freight').value) || 0;
    const other = parseFloat(row.querySelector('.tanker-other').value) || 0;
    const total = qty * rate;
    const net = total + freight + other;
    const waste = (qty / 35) * 50;
    const netQty = qty - (waste / 1000);
    row.querySelector('.tanker-waste').value = waste.toFixed(3);
    row.querySelector('.tanker-net-qty').value = netQty.toFixed(3);
    row.querySelector('.tanker-total').value = total.toFixed(2);
    row.querySelector('.tanker-net').value = net.toFixed(2);
    calculateGrandTotals();
}

function calculateGrandTotals() {
    const rows = document.querySelectorAll('#tankersBody .tanker-row');
    let totalQty = 0, totalWaste = 0, totalNetQty = 0;
    let grandTotal = 0, grandFreight = 0, grandOther = 0, grandNet = 0;
    rows.forEach(row => {
        totalQty += parseFloat(row.querySelector('.tanker-qty').value) || 0;
        totalWaste += parseFloat(row.querySelector('.tanker-waste').value) || 0;
        totalNetQty += parseFloat(row.querySelector('.tanker-net-qty').value) || 0;
        grandTotal += parseFloat(row.querySelector('.tanker-total').value) || 0;
        grandFreight += parseFloat(row.querySelector('.tanker-freight').value) || 0;
        grandOther += parseFloat(row.querySelector('.tanker-other').value) || 0;
        grandNet += parseFloat(row.querySelector('.tanker-net').value) || 0;
    });
    document.getElementById('totalQty').textContent = totalQty.toFixed(3);
    document.getElementById('totalWaste').textContent = totalWaste.toFixed(3);
    document.getElementById('totalNetQty').textContent = totalNetQty.toFixed(3);
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    document.getElementById('grandFreight').textContent = grandFreight.toFixed(2);
    document.getElementById('grandOther').textContent = grandOther.toFixed(2);
    document.getElementById('grandNet').textContent = grandNet.toFixed(2);
}

document.getElementById('addTankerBtn').addEventListener('click', function() {
    const tbody = document.getElementById('tankersBody');
    const row = document.createElement('tr');
    row.className = 'tanker-row';
    const i = tankerIndex++;
    row.innerHTML = `
        <td>
            <select name="tankers[${i}][tank_id]" class="form-control form-control-sm" required>
                <option value="">-- Tank --</option>
                ${tanksOptions}
            </select>
        </td>
        <td>
            <input type="text" name="tankers[${i}][tanker_number]" class="form-control form-control-sm" placeholder="Tanker No">
        </td>
        <td>
            <input type="text" name="tankers[${i}][driver_name]" class="form-control form-control-sm" placeholder="Driver Name">
        </td>
        <td>
            <input type="text" name="tankers[${i}][driver_mobile]" class="form-control form-control-sm" placeholder="Mobile">
        </td>
        <td>
            <input type="number" step="0.001" min="0" name="tankers[${i}][diesel_quantity]" class="form-control form-control-sm tanker-qty" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm tanker-waste bg-light" readonly value="0.000">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm tanker-net-qty bg-light" readonly value="0.000">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="tankers[${i}][rate_per_ton]" class="form-control form-control-sm tanker-rate" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="tankers[${i}][freight_charges]" class="form-control form-control-sm tanker-freight" value="0">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="tankers[${i}][other_charges]" class="form-control form-control-sm tanker-other" value="0">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm tanker-net font-weight-bold bg-light" readonly value="0.00">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger remove-tanker">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    const inputs = row.querySelectorAll('.tanker-qty, .tanker-rate, .tanker-freight, .tanker-other');
    inputs.forEach(inp => inp.addEventListener('input', function() { calculateRow(row); }));

    row.querySelector('.remove-tanker').addEventListener('click', function() {
        row.remove();
        calculateGrandTotals();
    });

    tbody.appendChild(row);
});

document.querySelectorAll('#tankersBody .tanker-row').forEach(row => {
    const inputs = row.querySelectorAll('.tanker-qty, .tanker-rate, .tanker-freight, .tanker-other');
    inputs.forEach(inp => inp.addEventListener('input', function() { calculateRow(row); }));
});

const paymentStatus = document.getElementById('payment_status');
const paidAmount    = document.getElementById('paid_amount');
const pmSelect      = document.getElementById('payment_method');
const acctSelect    = document.getElementById('bank_account_id');
const acctLabel     = document.getElementById('acct_label');
const bankAcctGroup = document.getElementById('bank_account_group');
const paymentAcctGroup = document.getElementById('payment_account_group');

function filterAccounts() {
    const isCash   = (pmSelect.value === 'Cash');
    const wantType = isCash ? 'Cash' : 'Bank';

    acctLabel.innerHTML = (isCash ? 'Cash Account' : 'Bank Account') + ' <span class="text-danger">*</span>';

    let firstMatch = null;
    acctSelect.querySelectorAll('option[data-type]').forEach(opt => {
        const show = (opt.dataset.type === wantType);
        opt.style.display = show ? '' : 'none';
        if (show && !firstMatch) firstMatch = opt.value;
    });

    const cur = acctSelect.querySelector('option:checked');
    if (!cur || cur.style.display === 'none') {
        acctSelect.value = firstMatch || '';
    }
}

function togglePaidAmount() {
    if (paymentStatus.value === 'Credit') {
        paidAmount.value = 0;
        paidAmount.readOnly = true;
        paymentAcctGroup.style.display = 'none';
        bankAcctGroup.style.display    = 'none';
        acctSelect.required = false;
    } else {
        paidAmount.readOnly = false;
        paymentAcctGroup.style.display = '';
        bankAcctGroup.style.display    = '';
        acctSelect.required = true;
        filterAccounts();
    }
}

paymentStatus.addEventListener('change', togglePaidAmount);
pmSelect.addEventListener('change', filterAccounts);
togglePaidAmount();
</script>

<?php include '../../includes/footer.php'; ?>
