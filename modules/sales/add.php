<?php
session_start();
$active_page = 'sale_entry';
require_once '../../config/db.php';

$success = "";
$error = "";

$customers = $conn->query("SELECT id, customer_name, mobile FROM customers ORDER BY customer_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no       = trim($_POST['invoice_no']);
    $customer_id      = intval($_POST['customer_id'] ?? 0);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $mobile           = trim($_POST['mobile'] ?? '');
    $sale_date        = $_POST['sale_date'];
    $vehicle_number   = trim($_POST['vehicle_number'] ?? '');
    $quantity         = floatval($_POST['quantity'] ?? 0);
    $rate_per_ton     = floatval($_POST['rate_per_ton'] ?? 0);
    $payment_type      = $_POST['payment_type'] ?? 'Cash';
    $cash_paid         = floatval($_POST['cash_paid'] ?? 0);
    $driver_info       = trim($_POST['driver_info'] ?? '');
    $delivery_location = trim($_POST['delivery_location'] ?? '');

    if (empty($invoice_no) || empty($sale_date) || $quantity <= 0 || $rate_per_ton <= 0) {
        $error = "Please fill all required fields with valid values.";
    } elseif ($customer_id === 0 && empty($customer_name)) {
        $error = "Please enter a name for the walk-in customer.";
    } elseif ($customer_id < 0) {
        $error = "Please select a customer.";
    } else {
        $total_amount = $quantity * $rate_per_ton;
        
        $payment_method   = trim($_POST['payment_method'] ?? 'Cash');
        $bank_account_id  = intval($_POST['bank_account_id'] ?? 0);

        // If Cash is selected, ensure we use the cash account ID
        if ($payment_method === 'Cash' && $bank_account_id <= 0) {
            $cash_acc = $conn->query("SELECT id FROM bank_accounts WHERE account_type = 'Cash' LIMIT 1")->fetch_assoc();
            if ($cash_acc) $bank_account_id = $cash_acc['id'];
        }

        if ($customer_id > 0) {
            $c = $conn->query("SELECT customer_name, mobile FROM customers WHERE id = $customer_id")->fetch_assoc();
            if ($c) {
                $customer_name = $c['customer_name'];
                if (empty($mobile)) $mobile = $c['mobile'];
            } else {
                $customer_id = 0;
            }
        }

        $conn->begin_transaction();
        try {
            if ($customer_id > 0) {
                $stmt = $conn->prepare("INSERT INTO customer_sales (invoice_no, customer_id, customer_name, mobile, sale_date, vehicle_number, quantity, rate_per_ton, total_amount, payment_type, driver_info, delivery_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sissssdddsss", $invoice_no, $customer_id, $customer_name, $mobile, $sale_date, $vehicle_number, $quantity, $rate_per_ton, $total_amount, $payment_type, $driver_info, $delivery_location);
            } else {
                $stmt = $conn->prepare("INSERT INTO customer_sales (invoice_no, customer_name, mobile, sale_date, vehicle_number, quantity, rate_per_ton, total_amount, payment_type, driver_info, delivery_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssdddsss", $invoice_no, $customer_name, $mobile, $sale_date, $vehicle_number, $quantity, $rate_per_ton, $total_amount, $payment_type, $driver_info, $delivery_location);
            }
            $stmt->execute();
            $sale_id = $conn->insert_id;
            $stmt->close();

            $cl_bal_before = 0;
            if ($customer_id > 0) {
                $cl_bal_before = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS bal FROM customer_ledger WHERE customer_id = $customer_id")->fetch_assoc()['bal'];
                $new_bal = $cl_bal_before + $total_amount;
                $desc = "Sale Invoice #$invoice_no";
                $stmt2 = $conn->prepare("INSERT INTO customer_ledger (customer_id, transaction_date, reference_type, reference_id, description, debit, credit, balance) VALUES (?, ?, 'sale', ?, ?, ?, 0, ?)");
                $stmt2->bind_param("isisds", $customer_id, $sale_date, $sale_id, $desc, $total_amount, $new_bal);
                $stmt2->execute();
                $stmt2->close();
                $conn->query("UPDATE customers SET balance = $new_bal WHERE id = $customer_id");
            }

            if ($payment_type === 'Cash' && $cash_paid > 0 && $customer_id > 0) {
                $cl_bal_after_sale = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS bal FROM customer_ledger WHERE customer_id = $customer_id")->fetch_assoc()['bal'];
                $new_cl_bal = $cl_bal_after_sale - $cash_paid;
                $desc2 = "Cash received against Sale Invoice #$invoice_no";
                $stmt3 = $conn->prepare("INSERT INTO customer_ledger (customer_id, transaction_date, reference_type, reference_id, description, debit, credit, balance) VALUES (?, ?, 'payment', ?, ?, 0, ?, ?)");
                $stmt3->bind_param("isisds", $customer_id, $sale_date, $sale_id, $desc2, $cash_paid, $new_cl_bal);
                $stmt3->execute();
                $stmt3->close();
                $conn->query("UPDATE customers SET balance = $new_cl_bal WHERE id = $customer_id");
            }

            $conn->commit();
            $success = "Sale recorded successfully! Invoice #$invoice_no";
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

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register mr-1"></i> Customer Sale Entry</h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> Sales List
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
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> Sale Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Invoice Number <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_no" class="form-control" required
                               value="<?= htmlspecialchars($_POST['invoice_no'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="sale_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_select" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <option value="0" <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == '0') ? 'selected' : '' ?>>Walk-in Customer</option>
                            <?php if ($customers && $customers->num_rows > 0):
                                $customers->data_seek(0);
                                while ($c = $customers->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"
                                    data-mobile="<?= htmlspecialchars($c['mobile'] ?? '') ?>"
                                    <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['customer_name']) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4" id="walkin_name_group" style="display:none">
                    <div class="form-group">
                        <label class="small font-weight-bold">Walk-in Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control"
                               placeholder="Enter customer name"
                               value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Mobile Number</label>
                        <input type="text" name="mobile" id="customer_mobile" class="form-control"
                               value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Vehicle Number</label>
                        <input type="text" name="vehicle_number" class="form-control"
                               value="<?= htmlspecialchars($_POST['vehicle_number'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Quantity (Tons) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" id="sale_qty" class="form-control" required
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Rate Per Ton (Rs.) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="rate_per_ton" id="rate_per_ton" class="form-control" required
                               value="<?= htmlspecialchars($_POST['rate_per_ton'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Total Amount (Rs.)</label>
                        <input type="text" id="total_amount" class="form-control bg-light" readonly value="0.00">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="Cash" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Cash') ? 'selected' : '' ?>>Cash</option>
                            <option value="Bank" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Bank') ? 'selected' : '' ?>>Bank</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="bank_account_group">
                    <div class="form-group">
                        <label class="small font-weight-bold">Select Bank Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="form-control">
                            <option value="">-- Select Bank --</option>
                            <?php 
                            $bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, current_balance FROM bank_accounts WHERE account_type = 'Bank' ORDER BY account_name ASC");
                            if ($bank_accounts && $bank_accounts->num_rows > 0):
                                while ($b = $bank_accounts->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"
                                    <?= (isset($_POST['bank_account_id']) && $_POST['bank_account_id'] == $b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['bank_name'] . " - " . $b['account_name']) ?> (Bal: <?= number_format($b['current_balance'], 2) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="cash_paid_group">
                    <div class="form-group">
                        <label class="small font-weight-bold"><i class="fas fa-money-bill-wave text-success mr-1"></i>Amount Received (Rs.)</label>
                        <input type="number" step="0.01" min="0" name="cash_paid" id="cash_paid" class="form-control"
                               placeholder="Amount customer paid now"
                               value="<?= htmlspecialchars($_POST['cash_paid'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary shadow-sm">
            <i class="fas fa-save"></i> Save Sale
        </button>
        <a href="list.php" class="btn btn-secondary shadow-sm">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<script>
// Customer dropdown — walk-in toggle + mobile auto-fill
const customerSelect  = document.getElementById('customer_select');
const walkinGroup     = document.getElementById('walkin_name_group');
const customerNameInp = document.getElementById('customer_name');
const mobileInp       = document.getElementById('customer_mobile');

customerSelect.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (this.value === '0') {
        walkinGroup.style.display = '';
        customerNameInp.required  = true;
        mobileInp.value = '';
    } else if (this.value === '') {
        walkinGroup.style.display = 'none';
        customerNameInp.required  = false;
    } else {
        walkinGroup.style.display = 'none';
        customerNameInp.required  = false;
        mobileInp.value = opt.dataset.mobile || '';
    }
});

// Restore state on page load (after POST error)
(function () {
    const v = customerSelect.value;
    if (v === '0') {
        walkinGroup.style.display = '';
        customerNameInp.required  = true;
    } else {
        walkinGroup.style.display = 'none';
        customerNameInp.required  = false;
    }
})();

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

document.getElementById('sale_qty').addEventListener('input', calcTotal);
document.getElementById('rate_per_ton').addEventListener('input', calcTotal);

function calcTotal() {
    const qty = parseFloat(document.getElementById('sale_qty').value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_ton').value) || 0;
    document.getElementById('total_amount').value = (qty * rate).toFixed(2);
}
</script>

<?php include '../../includes/footer.php'; ?>
