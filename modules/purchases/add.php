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

        $attachment_path = NULL;
        if (isset($_FILES['invoice_attachment']) && $_FILES['invoice_attachment']['error'] === 0) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($_FILES['invoice_attachment']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $upload_dir = '../../uploads/purchase_invoices/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = uniqid('inv_') . '.' . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['invoice_attachment']['tmp_name'], $target_path)) {
                    $attachment_path = 'uploads/purchase_invoices/' . $new_filename;
                } else {
                    $error = "Failed to upload attachment.";
                }
            } else {
                $error = "Invalid file type. Only PDF, JPG, PNG allowed.";
            }
        }

        if (empty($error)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO purchases 
                    (invoice_no, purchase_date, supplier_id, diesel_quantity, waste_kg, net_quantity, rate_per_ton,
                     total_amount, freight_charges, other_charges, net_purchase_cost,
                     payment_status, paid_amount, invoice_attachment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssidddddddddss",
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
                    $paid_amount,
                    $attachment_path
                );

                $stmt->execute();
                $purchase_id = $conn->insert_id;
                $stmt->close();

                $tanker_stmt = $conn->prepare("INSERT INTO purchase_tankers 
                    (purchase_id, tanker_number, driver_name, driver_mobile,
                     diesel_quantity, waste_kg, net_quantity, rate_per_ton, total_amount,
                     freight_charges, other_charges, net_amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($tankers as $t) {
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

                    $tanker_stmt->bind_param("isssdddddddd",
                        $purchase_id, $tanker_no, $driver_name, $driver_mob,
                        $qty, $waste, $net_qty, $rate, $t_total, $freight, $other, $t_net
                    );
                    $tanker_stmt->execute();
                }
                $tanker_stmt->close();

                $conn->commit();
                $success = "Purchase entry saved successfully with " . count($tankers) . " tanker(s)!";
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

<form method="POST" enctype="multipart/form-data" id="purchaseForm">
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
                            <th colspan="3" class="text-right">Totals:</th>
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

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-paperclip mr-1"></i> Invoice Attachment</h6>
        </div>
        <div class="card-body">
            <div class="form-group">
                <input type="file" name="invoice_attachment" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">
                <small class="text-muted">Allowed: PDF, JPG, PNG</small>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Purchase Entry
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<script>
let tankerIndex = 1;

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
const paidAmount = document.getElementById('paid_amount');
function togglePaidAmount() {
    if (paymentStatus.value === 'Credit') {
        paidAmount.value = 0;
        paidAmount.readOnly = true;
    } else {
        paidAmount.readOnly = false;
    }
}
paymentStatus.addEventListener('change', togglePaidAmount);
togglePaidAmount();
</script>

<?php include '../../includes/footer.php'; ?>
