<?php
session_start();
$active_page = 'sale_entry';
require_once '../../includes/db.php';

$success = "";
$error   = "";

$customers  = $conn->query("SELECT id, customer_name, mobile FROM customers ORDER BY customer_name ASC");
$tanks_res  = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name");
$tanks_list = [];
while($t = $tanks_res->fetch_assoc()) $tanks_list[] = $t;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no       = trim($_POST['invoice_no']);
    $sale_date        = $_POST['sale_date'];
    $customer_id      = intval($_POST['customer_id'] ?? 0);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $mobile           = trim($_POST['mobile'] ?? '');
    $advance_payment  = floatval($_POST['advance_payment'] ?? 0);
    $bank_account_id  = 0;
    $payment_method   = 'Cash';

    // Resolve customer name for known customers
    if ($customer_id > 0) {
        $c = $conn->query("SELECT customer_name, mobile FROM customers WHERE id = $customer_id")->fetch_assoc();
        if ($c) { $customer_name = $c['customer_name']; if (empty($mobile)) $mobile = $c['mobile'] ?? ''; }
        else     { $customer_id = 0; }
    }

    if (empty($invoice_no) || empty($sale_date)) {
        $error = "Please fill all required fields.";
    } elseif ($customer_id === 0 && empty($customer_name)) {
        $error = "Please select a customer or enter a walk-in name.";
    } elseif (!isset($_POST['tankers']) || count($_POST['tankers']) < 1) {
        $error = "Please add at least one tanker entry.";
    } else {
        $tankers = $_POST['tankers'];
        $total_qty = 0;
        $total_waste = 0;
        $total_net_qty = 0;
        $total_amount = 0;
        $total_net = 0;
        $all_driver_info = [];

        foreach ($tankers as $t) {
            $qty     = floatval($t['quantity'] ?? 0);
            $rate    = floatval($t['rate_per_ton'] ?? 0);
            $waste   = floatval($t['waste_kg'] ?? 0);
            $net_qty = $qty - ($waste / 1000);
            $t_amt   = $qty * $rate;
            $t_net   = $t_amt;

            $total_qty     += $qty;
            $total_waste   += $waste;
            $total_net_qty += $net_qty;
            $total_amount  += $t_amt;
            $total_net     += $t_net;
            
            if (!empty($t['driver_name']) || !empty($t['driver_mobile'])) {
                $driver_info = trim($t['driver_name'] ?? '') . ' - ' . trim($t['driver_mobile'] ?? '');
                if ($driver_info != ' - ') {
                    $all_driver_info[] = $driver_info;
                }
            }
        }
        
        $weighted_rate = $total_qty > 0 ? $total_amount / $total_qty : 0;
        $vehicle_number = '';
        $driver_info = implode(', ', array_unique($all_driver_info));

        $conn->begin_transaction();
        try {
            // Derive payment_type for display from advance_payment
            $payment_type = $advance_payment >= $total_net ? 'Cash' : 'Credit';

            // Insert into customer_sales table
            $stmt = $conn->prepare("INSERT INTO customer_sales
                (invoice_no, customer_id, customer_name, mobile, sale_date,
                 vehicle_number, quantity, waste_kg, net_quantity, rate_per_ton,
                 total_amount, freight_charges, other_charges, net_amount,
                 payment_type, payment_method, bank_account_id, driver_info, delivery_location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Convert null to empty string or 0 for bind_param
            $cid_val = $customer_id > 0 ? $customer_id : 0;
            $delivery_location = '';
            
            $stmt->bind_param(
                "sissssddddddddssiss",
                $invoice_no,
                $cid_val,
                $customer_name,
                $mobile,
                $sale_date,
                $vehicle_number,
                $total_qty,
                $total_waste,
                $total_net_qty,
                $weighted_rate,
                $total_amount,
                0,
                0,
                $total_net,
                $payment_type,
                $payment_method,
                $bank_account_id,
                $driver_info,
                $delivery_location
            );
            $stmt->execute();
            $sale_id = $conn->insert_id;
            $stmt->close();

            // Update stock for each tank
            foreach ($tankers as $t) {
                $tank_id = intval($t['tank_id'] ?? 0);
                $qty     = floatval($t['quantity'] ?? 0);
                $rate    = floatval($t['rate_per_ton'] ?? 0);
                $t_amt   = $qty * $rate;
                $tanker_no = trim($t['tanker_number'] ?? '');
                
                if ($tank_id > 0 && $qty > 0) {
                    // Get current stock
                    $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = $tank_id")->fetch_assoc();
                    if ($tank) {
                        $bal_before = $tank['current_stock'];
                        $bal_after  = $bal_before - $qty;
                        
                        // Update tank stock
                        $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = $tank_id");
                        
                        // Insert into stock_ledger using direct query to avoid bind_param issues
                        $stock_desc = "Sale Invoice #$invoice_no (Tanker: $tanker_no)";
                        $movement_type = 'OUT';
                        $reference_type = 'sale';
                        $reference_id_val = $sale_id ? $sale_id : 0;
                        
                        // Use a simple insert with escaped values
                        $acct_id_for_ledger = 0;
                        $pm_for_ledger      = 'Cash';
                        $insert_sql = "INSERT INTO stock_ledger 
                            (tank_id, transaction_date, movement_type, reference_type, reference_id, 
                             quantity, rate, amount, balance_before, balance_after, description,
                             bank_account_id, payment_method) 
                            VALUES (
                                " . intval($tank_id) . ",
                                '$sale_date',
                                '$movement_type',
                                '$reference_type',
                                " . intval($reference_id_val) . ",
                                " . floatval($qty) . ",
                                " . floatval($rate) . ",
                                " . floatval($t_amt) . ",
                                " . floatval($bal_before) . ",
                                " . floatval($bal_after) . ",
                                '" . addslashes($stock_desc) . "',
                                " . $acct_id_for_ledger . ",
                                '$pm_for_ledger'
                            )";
                        $conn->query($insert_sql);
                    }
                }
            }

            // Customer ledger: debit (sale) - only if customer is selected
            if ($customer_id > 0) {
                // Get current balance
                $bal_result = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS b FROM customer_ledger WHERE customer_id=$customer_id");
                $current_bal = $bal_result->fetch_assoc()['b'] ?? 0;
                $new_bal = $current_bal + $total_net;
                
                $desc = "Sale Invoice #$invoice_no";
                $ref_type = 'sale';
                
                $s2 = $conn->prepare("INSERT INTO customer_ledger 
                    (customer_id, transaction_date, description, debit, credit, balance, reference_type, reference_id, bank_account_id, payment_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $zero = 0;
                $s2->bind_param(
                    "issdddisis",
                    $customer_id,
                    $sale_date,
                    $desc,
                    $total_net,
                    $zero,
                    $new_bal,
                    $ref_type,
                    $sale_id,
                    $bank_account_id,
                    $payment_method
                );
                $s2->execute();
                $s2->close();
                $conn->query("UPDATE customers SET balance=$new_bal WHERE id=$customer_id");

                // Advance Payment — customer pays us cash now (shows in Cash Book & customer ledger)
                if ($advance_payment > 0) {
                    $bal_result2 = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS b FROM customer_ledger WHERE customer_id=$customer_id");
                    $current_bal2 = $bal_result2->fetch_assoc()['b'] ?? 0;
                    $new_bal2 = $current_bal2 - $advance_payment;

                    $desc2 = "Advance Payment against Invoice #$invoice_no";
                    $ref_type2 = 'payment';

                    $s3 = $conn->prepare("INSERT INTO customer_ledger
                        (customer_id, transaction_date, description, debit, credit, balance, reference_type, reference_id, bank_account_id, payment_method)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $s3->bind_param(
                        "issdddisis",
                        $customer_id,
                        $sale_date,
                        $desc2,
                        $zero,
                        $advance_payment,
                        $new_bal2,
                        $ref_type2,
                        $sale_id,
                        $bank_account_id,
                        $payment_method
                    );
                    $s3->execute();
                    $s3->close();
                    $conn->query("UPDATE customers SET balance=$new_bal2 WHERE id=$customer_id");
                }
            }

            $conn->commit();
            $success = "Sale recorded successfully! Invoice #$invoice_no";
            $_POST = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = ($conn->errno === 1062) ? "Invoice number already exists." : "Database error: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register mr-1"></i> Customer Sale Entry</h1>
    <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>

<form method="POST" id="saleForm">
<!-- Invoice / Customer Info -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> Sale Information</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label class="small font-weight-bold">Sale Invoice No <span class="text-danger">*</span></label>
                    <input type="text" name="invoice_no" class="form-control" required value="<?= htmlspecialchars($_POST['invoice_no'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="small font-weight-bold">Sale Date <span class="text-danger">*</span></label>
                    <input type="date" name="sale_date" class="form-control" required value="<?= htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="small font-weight-bold">Customer <span class="text-danger">*</span></label>
                    <div class="d-flex align-items-end">
                        <select name="customer_id" id="customer_select" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <option value="0" <?= (isset($_POST['customer_id']) && $_POST['customer_id']=='0') ? 'selected':'' ?>>Walk-in Customer</option>
                            <?php if ($customers && $customers->num_rows > 0): $customers->data_seek(0);
                                while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" data-mobile="<?= htmlspecialchars($c['mobile']??'') ?>"
                                <?= (isset($_POST['customer_id']) && $_POST['customer_id']==$c['id']) ? 'selected':'' ?>>
                                <?= htmlspecialchars($c['customer_name']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                        <button type="button" class="btn btn-outline-primary btn-sm ml-2 mb-0" data-toggle="modal" data-target="#addCustomerModal" title="Add New Customer" style="height:38px;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4" id="walkin_name_group" style="display:none">
                <div class="form-group">
                    <label class="small font-weight-bold">Walk-in Name <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Enter name" value="<?= htmlspecialchars($_POST['customer_name']??'') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="small font-weight-bold">Mobile</label>
                    <input type="text" name="mobile" id="customer_mobile" class="form-control" value="<?= htmlspecialchars($_POST['mobile']??'') ?>">
                </div>
            </div>
        </div>
        <!-- Payment row -->
        <div class="row border-top pt-3 mt-2">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="small font-weight-bold">Advance Payment ($)</label>
                    <input type="number" step="0.01" min="0" name="advance_payment" id="advance_payment" class="form-control" value="<?= htmlspecialchars($_POST['advance_payment']??'0') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tanker Entries -->
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
                        <th style="min-width:140px">Tank <span class="text-danger">*</span></th>
                        <th style="min-width:120px">Tanker No</th>
                        <th style="min-width:120px">Driver Name</th>
                        <th style="min-width:110px">Driver Mobile</th>
                        <th style="min-width:100px">Qty (Ton)</th>
                        <th style="min-width:90px">Waste (Kg)</th>
                        <th style="min-width:100px">Net Qty (Ton)</th>
                        <th style="min-width:90px">Rate/Ton</th>
                        <th style="min-width:100px">Total ($)</th>
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
                        <td><input type="text" name="tankers[0][tanker_number]" class="form-control form-control-sm" placeholder="Tanker No"></td>
                        <td><input type="text" name="tankers[0][driver_name]" class="form-control form-control-sm" placeholder="Driver Name"></td>
                        <td><input type="text" name="tankers[0][driver_mobile]" class="form-control form-control-sm" placeholder="Mobile"></td>
                        <td><input type="number" step="0.001" min="0" name="tankers[0][quantity]" class="form-control form-control-sm tanker-qty" required></td>
                        <td><input type="number" step="0.001" min="0" name="tankers[0][waste_kg]" class="form-control form-control-sm tanker-waste" value="0.000"></td>
                        <td><input type="text" class="form-control form-control-sm tanker-net-qty bg-light" readonly value="0.000"></td>
                        <td><input type="number" step="0.01" min="0" name="tankers[0][rate_per_ton]" class="form-control form-control-sm tanker-rate" required></td>
                        <td><input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00"></td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-tanker" disabled><i class="fas fa-trash"></i></button></td>
                    </tr>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="5" class="text-right">Totals:</th>
                        <th><span id="totalQty">0.000</span></th>
                        <th><span id="totalWaste">0.000</span></th>
                        <th><span id="totalNetQty">0.000</span></th>
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
    <button type="submit" class="btn btn-primary btn-lg px-5 shadow"><i class="fas fa-save mr-1"></i> Save Sale</button>
    <a href="list.php" class="btn btn-secondary btn-lg px-4 shadow"><i class="fas fa-times mr-1"></i> Cancel</a>
</div>
</form>

<script>
let tankerIndex = 1;
const tanksOptions = `<?php foreach($tanks_list as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['tank_name']) ?></option><?php endforeach; ?>`;

function calculateRow(row) {
    const qty     = parseFloat(row.querySelector('.tanker-qty').value)     || 0;
    const rate    = parseFloat(row.querySelector('.tanker-rate').value)    || 0;
    const wasteEl = row.querySelector('.tanker-waste');
    let waste     = parseFloat(wasteEl.value) || 0;
    const netQty  = qty - (waste / 1000);
    const total   = qty * rate;
    
    row.querySelector('.tanker-net-qty').value = netQty.toFixed(3);
    row.querySelector('.tanker-total').value   = total.toFixed(2);
    calculateGrandTotals();
}

function autoFillWaste(row) {
    const qty = parseFloat(row.querySelector('.tanker-qty').value) || 0;
    const waste = (qty / 35) * 50;
    row.querySelector('.tanker-waste').value = waste.toFixed(3);
    calculateRow(row);
}

function calculateGrandTotals() {
    let tQty=0, tWaste=0, tNetQty=0, gTotal=0;
    document.querySelectorAll('#tankersBody .tanker-row').forEach(r => {
        tQty    += parseFloat(r.querySelector('.tanker-qty').value)     || 0;
        tWaste  += parseFloat(r.querySelector('.tanker-waste').value)   || 0;
        tNetQty += parseFloat(r.querySelector('.tanker-net-qty').value) || 0;
        gTotal  += parseFloat(r.querySelector('.tanker-total').value)   || 0;
    });
    document.getElementById('totalQty').textContent    = tQty.toFixed(3);
    document.getElementById('totalWaste').textContent  = tWaste.toFixed(3);
    document.getElementById('totalNetQty').textContent = tNetQty.toFixed(3);
    document.getElementById('grandTotal').textContent  = gTotal.toFixed(2);
}

function bindRow(row) {
    row.querySelector('.tanker-qty').addEventListener('input', () => autoFillWaste(row));
    row.querySelector('.tanker-rate').addEventListener('input', () => calculateRow(row));
    row.querySelector('.tanker-waste').addEventListener('input', () => calculateRow(row));
    row.querySelector('.remove-tanker').addEventListener('click', () => { row.remove(); calculateGrandTotals(); });
}

document.getElementById('addTankerBtn').addEventListener('click', function() {
    const i = tankerIndex++;
    const tr = document.createElement('tr');
    tr.className = 'tanker-row';
    tr.innerHTML = `
        <td>
            <select name="tankers[${i}][tank_id]" class="form-control form-control-sm" required>
                <option value="">-- Tank --</option>
                ${tanksOptions}
            </select>
        </td>
        <td><input type="text" name="tankers[${i}][tanker_number]" class="form-control form-control-sm" placeholder="Tanker No"></td>
        <td><input type="text" name="tankers[${i}][driver_name]" class="form-control form-control-sm" placeholder="Driver Name"></td>
        <td><input type="text" name="tankers[${i}][driver_mobile]" class="form-control form-control-sm" placeholder="Mobile"></td>
        <td><input type="number" step="0.001" min="0" name="tankers[${i}][quantity]" class="form-control form-control-sm tanker-qty" required></td>
        <td><input type="number" step="0.001" min="0" name="tankers[${i}][waste_kg]" class="form-control form-control-sm tanker-waste" value="0.000"></td>
        <td><input type="text" class="form-control form-control-sm tanker-net-qty bg-light" readonly value="0.000"></td>
        <td><input type="number" step="0.01" min="0" name="tankers[${i}][rate_per_ton]" class="form-control form-control-sm tanker-rate" required></td>
        <td><input type="text" class="form-control form-control-sm tanker-total bg-light" readonly value="0.00"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-tanker"><i class="fas fa-trash"></i></button></td>`;
    document.getElementById('tankersBody').appendChild(tr);
    bindRow(tr);
});

document.querySelectorAll('#tankersBody .tanker-row').forEach(bindRow);

// Customer walk-in toggle
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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-1"></i> Add New Customer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="customerAlert" class="alert d-none"></div>
                <div class="form-group">
                    <label class="small font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" id="cust_name" class="form-control" placeholder="Customer name" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Mobile</label>
                    <input type="text" id="cust_mobile" class="form-control" placeholder="Mobile number">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Address</label>
                    <input type="text" id="cust_address" class="form-control" placeholder="Address">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Opening Balance</label>
                    <input type="number" step="0.01" id="cust_opening_balance" class="form-control" placeholder="0.00" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCustomerBtn"><i class="fas fa-save mr-1"></i> Save Customer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('saveCustomerBtn').addEventListener('click', function() {
    const btn = this;
    const name = document.getElementById('cust_name').value.trim();
    if (!name) {
        const alertEl = document.getElementById('customerAlert');
        alertEl.className = 'alert alert-danger';
        alertEl.textContent = 'Customer name is required.';
        alertEl.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span>Saving...';

    const fd = new FormData();
    fd.append('customer_name', name);
    fd.append('mobile', document.getElementById('cust_mobile').value.trim());
    fd.append('address', document.getElementById('cust_address').value.trim());
    fd.append('opening_balance', document.getElementById('cust_opening_balance').value || 0);

    fetch('ajax_add_customer.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Customer';
            if (res.success) {
                const sel = document.getElementById('customer_select');
                const opt = document.createElement('option');
                opt.value = res.id;
                opt.textContent = res.customer_name;
                opt.dataset.mobile = res.mobile || '';
                opt.selected = true;
                // Insert before walk-in option
                const walkinOpt = sel.querySelector('option[value="0"]');
                sel.insertBefore(opt, walkinOpt);
                // Clear form
                document.getElementById('cust_name').value = '';
                document.getElementById('cust_mobile').value = '';
                document.getElementById('cust_address').value = '';
                document.getElementById('customerAlert').classList.add('d-none');
                // Trigger change to hide walk-in name group
                sel.dispatchEvent(new Event('change'));
                $('#addCustomerModal').modal('hide');
            } else {
                const alertEl = document.getElementById('customerAlert');
                alertEl.className = 'alert alert-danger';
                alertEl.textContent = res.message;
                alertEl.classList.remove('d-none');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Customer';
        });
});

$('#addCustomerModal').on('hidden.bs.modal', function() {
    document.getElementById('cust_name').value = '';
    document.getElementById('cust_mobile').value = '';
    document.getElementById('cust_address').value = '';
    document.getElementById('cust_opening_balance').value = '0';
    document.getElementById('customerAlert').classList.add('d-none');
});
</script>