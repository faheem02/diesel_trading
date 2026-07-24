<?php
session_start();
$active_page = 'purchase_add';
require_once '../../includes/db.php';

$success = "";
$error = "";

require_once '../../includes/tank_helper.php';
$tanks_list = resolve_default_tank($conn);
$single_tank = $tanks_list[0]; // default tank always exists (auto-created if table was empty)

$max_inv = $conn->query("SELECT COALESCE(MAX(CAST(invoice_no AS UNSIGNED)),0)+1 AS next_inv FROM purchases")->fetch_assoc();
$next_invoice = str_pad($max_inv['next_inv'], 4, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $invoice_no        = trim($_POST['invoice_no']);
    $purchase_date     = $_POST['purchase_date'];
    $supplier_id       = intval($_POST['supplier_id']);
    $advance_payment   = floatval($_POST['advance_payment'] ?? 0);

    if (empty($invoice_no) || empty($purchase_date) || $supplier_id <= 0) {
        $error = "Please fill all required fields with valid values.";
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
        $total_freight = 0;
        $total_net = 0;

        foreach ($tankers as $t) {
            $qty = floatval($t['diesel_quantity'] ?? 0);
            $rate = floatval($t['rate_per_ton'] ?? 0);
            $t_total = $qty * $rate;
            $t_net = $t_total;

            $total_qty += $qty;
            $total_amount += $t_total;
            $total_net += $t_net;
        }

        $weighted_rate = $total_qty > 0 ? $total_amount / $total_qty : 0;

        if (empty($error)) {
            $conn->begin_transaction();
            try {
                // Fixed: Removed waste_kg and net_quantity from the query
                // Derive payment_status / paid_amount for display (list.php) only.
                if ($advance_payment <= 0) {
                    $payment_status = 'Credit';
                    $paid_amount    = 0;
                } elseif ($advance_payment >= $total_net) {
                    $payment_status = 'Paid';
                    $paid_amount    = $total_net;
                } else {
                    $payment_status = 'Partial Paid';
                    $paid_amount    = $advance_payment;
                }

                $stmt = $conn->prepare("INSERT INTO purchases 
                    (invoice_no, purchase_date, supplier_id, diesel_quantity, rate_per_ton,
                     total_amount, freight_charges, net_purchase_cost,
                     payment_status, paid_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssidddddsd",
                    $invoice_no,
                    $purchase_date,
                    $supplier_id,
                    $total_qty,
                    $weighted_rate,
                    $total_amount,
                    $total_freight,
                    $total_net,
                    $payment_status,
                    $paid_amount
                );

                $stmt->execute();
                $purchase_id = $conn->insert_id;
                $stmt->close();

                $tanker_stmt = $conn->prepare("INSERT INTO purchase_tankers 
                    (purchase_id, tank_id, tanker_number, driver_name, driver_mobile,
                     diesel_quantity, rate_per_ton, total_amount,
                     freight_charges, net_amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($tankers as $t) {
                    $tank_id     = intval($t['tank_id'] ?? 0);
                    $tanker_no   = trim($t['tanker_number'] ?? '');
                    $driver_name = trim($t['driver_name'] ?? '');
                    $driver_mob  = trim($t['driver_mobile'] ?? '');
                    $qty         = floatval($t['diesel_quantity'] ?? 0);
                    $rate        = floatval($t['rate_per_ton'] ?? 0);
                    $t_total     = $qty * $rate;
                    $t_net       = $t_total;

                    $tank_id_val = ($tank_id > 0) ? $tank_id : null;
                    $zero_freight = 0;
                    $tanker_stmt->bind_param("iisssddddd", 
                        $purchase_id, $tank_id_val, $tanker_no, $driver_name, $driver_mob,
                        $qty, $rate, $t_total, $zero_freight, $t_net
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

                $ledger_desc = "Purchase Invoice #$invoice_no";
                $ledger_credit = $total_net;

                // 1. Always post the full invoice as credit (we owe supplier the full amount)
                $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type, reference_id) VALUES ($supplier_id, '$purchase_date', '$ledger_desc', 0, $ledger_credit, 0, 'purchase', $purchase_id)");
                $entry_id = $conn->insert_id;
                $bal = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                $conn->query("UPDATE supplier_ledger SET balance = $bal WHERE id = $entry_id");

                // 2. Advance Payment — posted as Cash payment (shows in supplier ledger & cash book)
                if ($advance_payment > 0) {
                    $pay_desc = "Advance Payment against Invoice #$invoice_no";
                    $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type, payment_method) VALUES ($supplier_id, '$purchase_date', '$pay_desc', $advance_payment, 0, 0, 'payment', 'Cash')");
                    $entry_id2 = $conn->insert_id;
                    $bal2 = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                    $conn->query("UPDATE supplier_ledger SET balance = $bal2 WHERE id = $entry_id2");
                }

                $final_bal = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc()['bal'];
                $conn->query("UPDATE suppliers SET balance = $final_bal WHERE id = $supplier_id");

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
                               value="<?= htmlspecialchars($_POST['invoice_no'] ?? $next_invoice) ?>">
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
                        <div class="d-flex align-items-end">
                            <select name="supplier_id" id="supplier_id" class="form-control" required>
                                <option value="">-- Select Supplier --</option>
                                <?php while ($row = $suppliers->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"
                                        <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $row['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['company_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm ml-2 mb-0" data-toggle="modal" data-target="#addSupplierModal" title="Add New Supplier" style="height:38px;">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Advance Payment ($)</label>
                        <input type="number" step="0.01" min="0" name="advance_payment" id="advance_payment" class="form-control"
                               value="<?= htmlspecialchars($_POST['advance_payment'] ?? '0') ?>">
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
                            <th style="min-width:90px">Rate/Ton</th>
                            <th style="min-width:100px">Total ($)</th>
                            <th style="width:50px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tankersBody">
                        <tr class="tanker-row">
                            <input type="hidden" name="tankers[0][tank_id]" value="<?= $single_tank['id'] ?>">
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
                                <input type="number" step="0.01" min="0" name="tankers[0][rate_per_ton]" class="form-control form-control-sm tanker-rate" required>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00">
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

function calculateRow(row) {
    const qty = parseFloat(row.querySelector('.tanker-qty').value) || 0;
    const rate = parseFloat(row.querySelector('.tanker-rate').value) || 0;
    const total = qty * rate;
    
    row.querySelector('.tanker-total').value = total.toFixed(2);
    calculateGrandTotals();
}

function calculateGrandTotals() {
    const rows = document.querySelectorAll('#tankersBody .tanker-row');
    let totalQty = 0;
    let grandTotal = 0;
    
    rows.forEach(row => {
        totalQty    += parseFloat(row.querySelector('.tanker-qty').value) || 0;
        grandTotal  += parseFloat(row.querySelector('.tanker-total').value) || 0;
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
            <input type="number" step="0.01" min="0" name="tankers[${i}][rate_per_ton]" class="form-control form-control-sm tanker-rate" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger remove-tanker">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    const inputs = row.querySelectorAll('.tanker-qty, .tanker-rate');
    inputs.forEach(inp => inp.addEventListener('input', function() { calculateRow(row); }));

    row.querySelector('.remove-tanker').addEventListener('click', function() {
        row.remove();
        calculateGrandTotals();
    });

    tbody.appendChild(row);
});

document.querySelectorAll('#tankersBody .tanker-row').forEach(row => {
    const inputs = row.querySelectorAll('.tanker-qty, .tanker-rate');
    inputs.forEach(inp => inp.addEventListener('input', function() { calculateRow(row); }));
});
</script>

<?php include '../../includes/footer.php'; ?>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-truck mr-1"></i> Add New Supplier</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="supplierAlert" class="alert d-none"></div>
                <div class="form-group">
                    <label class="small font-weight-bold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" id="sup_company_name" class="form-control" placeholder="Company name" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Contact Person</label>
                    <input type="text" id="sup_contact_person" class="form-control" placeholder="Contact person name">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Phone</label>
                    <input type="text" id="sup_phone" class="form-control" placeholder="Phone number">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">NTN / CNIC</label>
                    <input type="text" id="sup_ntn_cnic" class="form-control" placeholder="NTN or CNIC number">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Address</label>
                    <input type="text" id="sup_address" class="form-control" placeholder="Address">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Opening Balance</label>
                    <input type="number" step="0.01" id="sup_opening_balance" class="form-control" placeholder="0.00" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSupplierBtn"><i class="fas fa-save mr-1"></i> Save Supplier</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('saveSupplierBtn').addEventListener('click', function() {
    const btn = this;
    const name = document.getElementById('sup_company_name').value.trim();
    if (!name) {
        const alertEl = document.getElementById('supplierAlert');
        alertEl.className = 'alert alert-danger';
        alertEl.textContent = 'Company name is required.';
        alertEl.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span>Saving...';

    const fd = new FormData();
    fd.append('company_name', name);
    fd.append('contact_person', document.getElementById('sup_contact_person').value.trim());
    fd.append('phone', document.getElementById('sup_phone').value.trim());
    fd.append('ntn_cnic', document.getElementById('sup_ntn_cnic').value.trim());
    fd.append('address', document.getElementById('sup_address').value.trim());
    fd.append('opening_balance', document.getElementById('sup_opening_balance').value || 0);

    fetch('ajax_add_supplier.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Supplier';
            if (res.success) {
                const sel = document.getElementById('supplier_id');
                const opt = document.createElement('option');
                opt.value = res.id;
                opt.textContent = res.company_name;
                opt.selected = true;
                sel.appendChild(opt);
                // Clear form
                document.getElementById('sup_company_name').value = '';
                document.getElementById('sup_contact_person').value = '';
                document.getElementById('sup_phone').value = '';
                document.getElementById('sup_address').value = '';
                document.getElementById('supplierAlert').classList.add('d-none');
                $('#addSupplierModal').modal('hide');
            } else {
                const alertEl = document.getElementById('supplierAlert');
                alertEl.className = 'alert alert-danger';
                alertEl.textContent = res.message;
                alertEl.classList.remove('d-none');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Supplier';
        });
});

$('#addSupplierModal').on('hidden.bs.modal', function() {
    document.getElementById('sup_company_name').value = '';
    document.getElementById('sup_contact_person').value = '';
    document.getElementById('sup_phone').value = '';
    document.getElementById('sup_ntn_cnic').value = '';
    document.getElementById('sup_address').value = '';
    document.getElementById('sup_opening_balance').value = '0';
    document.getElementById('supplierAlert').classList.add('d-none');
});
</script>