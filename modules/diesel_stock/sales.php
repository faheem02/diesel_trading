<?php
session_start();
$active_page = 'sale_add';
require_once '../../config/db.php';

$success = "";
$error = "";

$tanks = $conn->query("SELECT id, tank_name, current_stock FROM tanks ORDER BY tank_name ASC");
$customers_list = $conn->query("SELECT id, customer_name, mobile FROM customers ORDER BY customer_name ASC");
$bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, account_type, current_balance FROM bank_accounts ORDER BY account_type ASC, account_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no       = trim($_POST['invoice_no']);
    $sale_date        = $_POST['sale_date'];
    $tank_id          = intval($_POST['tank_id']);
    $customer_id      = intval($_POST['customer_id'] ?? 0);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_mobile  = trim($_POST['customer_mobile'] ?? '');

    // For known customers, pull the name from the DB (authoritative)
    if ($customer_id > 0) {
        $c = $conn->query("SELECT customer_name, mobile FROM customers WHERE id = $customer_id")->fetch_assoc();
        if ($c) {
            $customer_name   = $c['customer_name'];
            $customer_mobile = $customer_mobile ?: ($c['mobile'] ?? '');
        } else {
            $customer_id = 0;
        }
    }
    $vehicle_number   = trim($_POST['vehicle_number'] ?? '');
    $quantity         = floatval($_POST['quantity'] ?? 0);
    $rate_per_ton     = floatval($_POST['rate_per_ton'] ?? 0);
    $payment_type     = $_POST['payment_type'] ?? 'Cash';
    $bank_account_id  = intval($_POST['bank_account_id'] ?? 0);
    $payment_method   = trim($_POST['payment_method'] ?? 'Cash');

    if (empty($invoice_no) || empty($sale_date) || $tank_id <= 0 || $quantity <= 0 || $rate_per_ton <= 0) {
        $error = "Please fill all required fields with valid values.";
    } elseif ($customer_id === 0 && empty($customer_name)) {
        $error = "Please enter a name for the walk-in customer.";
    } elseif ($customer_id < 0) {
        $error = "Please select a customer.";
    } else {
        $total_amount = $quantity * $rate_per_ton;
        
        // If Cash Sale is selected, ensure we use the cash account ID
        if ($payment_type === 'Cash' && $bank_account_id <= 0) {
            $cash_acc = $conn->query("SELECT id FROM bank_accounts WHERE account_type = 'Cash' LIMIT 1")->fetch_assoc();
            if ($cash_acc) $bank_account_id = $cash_acc['id'];
        }
        

        $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = $tank_id")->fetch_assoc();
        if (!$tank) {
            $error = "Tank not found.";
        } elseif ($tank['current_stock'] < $quantity) {
            $error = "Insufficient stock! Available: " . number_format($tank['current_stock'], 3) . " tons.";
        } else {
            $bal_before = $tank['current_stock'];
            $bal_after  = $bal_before - $quantity;
            
            // Valuation for stock ledger
            $val_before = $bal_before * $rate_per_ton;
            $val_after  = $bal_after * $rate_per_ton;

            $conn->begin_transaction();
            try {
                if ($customer_id > 0) {
                    $stmt = $conn->prepare("INSERT INTO sales (invoice_no, sale_date, tank_id, customer_id, customer_name, customer_mobile, vehicle_number, quantity, rate_per_ton, total_amount, payment_type, bank_account_id, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssiisssdddsis", $invoice_no, $sale_date, $tank_id, $customer_id, $customer_name, $customer_mobile, $vehicle_number, $quantity, $rate_per_ton, $total_amount, $payment_type, $bank_account_id, $payment_method);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sales (invoice_no, sale_date, tank_id, customer_name, customer_mobile, vehicle_number, quantity, rate_per_ton, total_amount, payment_type, bank_account_id, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssisssdddsis", $invoice_no, $sale_date, $tank_id, $customer_name, $customer_mobile, $vehicle_number, $quantity, $rate_per_ton, $total_amount, $payment_type, $bank_account_id, $payment_method);
                }
                $stmt->execute();
                $sale_id = $conn->insert_id;
                $stmt->close();

                $description = "Sale Invoice #$invoice_no - $customer_name" . ($payment_type === 'Cash' ? " (Cash Received)" : " (On Credit)");
                $stmt2 = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, reference_id, bank_account_id, payment_method, quantity, rate, amount, balance_before, balance_before_value, balance_after, balance_after_value, description) VALUES (?, ?, 'OUT', 'sale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("isiiissddddddds", $tank_id, $sale_date, $sale_id, $bank_account_id, $payment_method, $quantity, $rate_per_ton, $total_amount, $bal_before, $val_before, $bal_after, $val_after, $description);
                $stmt2->execute();
                $stmt2->close();

                $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = $tank_id");

                // If Cash, update Bank Account balance
                if ($payment_type === 'Cash' && $bank_account_id > 0) {
                    $conn->query("UPDATE bank_accounts SET current_balance = current_balance + $total_amount WHERE id = $bank_account_id");
                }

                if ($customer_id > 0) {
                    $cl_bal = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS bal FROM customer_ledger WHERE customer_id = $customer_id")->fetch_assoc()['bal'];
                    $new_cl_balance = $cl_bal + $total_amount;
                    $stmt3 = $conn->prepare("INSERT INTO customer_ledger (customer_id, transaction_date, reference_type, reference_id, description, debit, credit, balance, bank_account_id, payment_method) VALUES (?, ?, 'sale', ?, ?, ?, 0, ?, ?, ?)");
                    $stmt3->bind_param("isissdiss", $customer_id, $sale_date, $sale_id, $description, $total_amount, $new_cl_balance, $bank_account_id, $payment_method);
                    $stmt3->execute();
                    $stmt3->close();
                    
                    if ($payment_type === 'Cash') {
                        // Record the immediate payment in customer ledger too
                        $cl_bal_after_sale = $conn->query("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS bal FROM customer_ledger WHERE customer_id = $customer_id")->fetch_assoc()['bal'];
                        $new_cl_bal_final = $cl_bal_after_sale - $total_amount;
                        $pay_desc = "Cash received against Sale #$invoice_no";
                        $stmt4 = $conn->prepare("INSERT INTO customer_ledger (customer_id, transaction_date, reference_type, reference_id, description, debit, credit, balance, bank_account_id, payment_method) VALUES (?, ?, 'payment', ?, ?, 0, ?, ?, ?, ?)");
                        $stmt4->bind_param("isissdiss", $customer_id, $sale_date, $sale_id, $pay_desc, $total_amount, $new_cl_bal_final, $bank_account_id, $payment_method);
                        $stmt4->execute();
                        $stmt4->close();
                        $conn->query("UPDATE customers SET balance = $new_cl_bal_final WHERE id = $customer_id");
                    } else {
                        $conn->query("UPDATE customers SET balance = $new_cl_balance WHERE id = $customer_id");
                    }
                }

                $conn->commit();
                $success = "Sale recorded successfully! Invoice #$invoice_no";
                if ($payment_type === 'Cash') $success .= " (Stock out & Cash book updated)";
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

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-arrow-up mr-1"></i> Diesel Sale (Stock Out)</h1>
    <div>
        <a href="sales_list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> Sales History
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
                        <label class="small font-weight-bold">Invoice No <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_no" class="form-control" required
                               value="<?= htmlspecialchars($_POST['invoice_no'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Sale Date <span class="text-danger">*</span></label>
                        <input type="date" name="sale_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')) ?>">
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
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_select" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <option value="0" <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == '0') ? 'selected' : '' ?>>Walk-in Customer</option>
                            <?php
                            if ($customers_list && $customers_list->num_rows > 0):
                                $customers_list->data_seek(0);
                                while ($c = $customers_list->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"
                                data-mobile="<?= htmlspecialchars($c['mobile'] ?? '') ?>"
                                <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['customer_name']) ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
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
                        <label class="small font-weight-bold">Customer Mobile</label>
                        <input type="text" name="customer_mobile" id="customer_mobile" class="form-control"
                               value="<?= htmlspecialchars($_POST['customer_mobile'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
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
                        <label class="small font-weight-bold">Rate per Ton (Rs.) <span class="text-danger">*</span></label>
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
            <div class="row border-top pt-3 mt-3">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Sale Type <span class="text-danger">*</span></label>
                        <select name="payment_type" id="payment_type" class="form-control" required>
                            <option value="Cash" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] === 'Cash') ? 'selected' : '' ?>>Cash Sale</option>
                            <option value="Credit" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] === 'Credit') ? 'selected' : '' ?>>Credit Sale</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="method_field">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash" <?= (isset($_POST['payment_method']) && $_POST['payment_method']=='Cash') ? 'selected':'' ?>>Cash</option>
                            <option value="Bank" <?= (isset($_POST['payment_method']) && $_POST['payment_method']=='Bank') ? 'selected':'' ?>>Bank</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="bank_account_group">
                    <div class="form-group">
                        <label class="small font-weight-bold">Deposit to Bank Account <span class="text-danger">*</span></label>
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
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary btn-lg px-5 shadow">
            <i class="fas fa-save mr-1"></i> Save Sale
        </button>
        <a href="sales_list.php" class="btn btn-secondary btn-lg px-4 shadow">
            <i class="fas fa-times mr-1"></i> Cancel
        </a>
    </div>
</form>

<script>
// Auto-fill mobile when a known customer is selected; show name input for walk-in
const customerSelect  = document.getElementById('customer_select');
const walkinGroup     = document.getElementById('walkin_name_group');
const customerNameInp = document.getElementById('customer_name');
const mobileInp       = document.getElementById('customer_mobile');

customerSelect.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (this.value === '0') {
        // Walk-in: show name input, clear mobile
        walkinGroup.style.display = '';
        customerNameInp.required  = true;
        mobileInp.value = '';
    } else if (this.value === '') {
        walkinGroup.style.display = 'none';
        customerNameInp.required  = false;
    } else {
        // Known customer: hide name input, fill mobile
        walkinGroup.style.display = 'none';
        customerNameInp.required  = false;
        mobileInp.value = opt.dataset.mobile || '';
    }
});

// Run on load to restore state after POST error
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

document.getElementById('sale_qty').addEventListener('input', calcTotal);
document.getElementById('rate_per_ton').addEventListener('input', calcTotal);

function calcTotal() {
    const qty = parseFloat(document.getElementById('sale_qty').value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_ton').value) || 0;
    document.getElementById('total_amount').value = (qty * rate).toFixed(2);
}

const payType = document.getElementById('payment_type');
const metField = document.getElementById('method_field');
const bankGroup = document.getElementById('bank_account_group');
const pmSelect = document.getElementById('payment_method');
const bankSelect = document.getElementById('bank_account_id');

function toggleFields() {
    if (payType.value === 'Credit') {
        metField.style.display = 'none';
        bankGroup.style.display = 'none';
        bankSelect.required = false;
    } else {
        metField.style.display = '';
        toggleBankField();
    }
}

function toggleBankField() {
    if (pmSelect.value === 'Bank') {
        bankGroup.style.display = '';
        bankSelect.required = true;
    } else {
        bankGroup.style.display = 'none';
        bankSelect.required = false;
        bankSelect.value = '';
    }
}

payType.addEventListener('change', toggleFields);
pmSelect.addEventListener('change', toggleBankField);
toggleFields();
calcTotal();
</script>

<?php include '../../includes/footer.php'; ?>
