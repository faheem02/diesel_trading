<?php
session_start();
$active_page = 'customer_payment';
require_once '../../includes/db.php';

$success = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id     = intval($_POST['customer_id']);
    $payment_date    = $_POST['payment_date'];
    $amount          = floatval($_POST['amount'] ?? 0);
    $direction       = $_POST['direction'] ?? 'from_customer';
    $payment_method  = trim($_POST['payment_method'] ?? 'Cash');
    $bank_account_id = intval($_POST['bank_account_id'] ?? 0);
    $notes           = trim($_POST['notes'] ?? '');

    if ($customer_id <= 0 || empty($payment_date) || $amount <= 0) {
        $error = "Please fill all required fields with valid values.";
    } elseif ($bank_account_id <= 0) {
        $error = "Please select a Cash or Bank account.";
    } else {
        $conn->begin_transaction();
        try {
            if ($direction === 'from_customer') {
                // Customer pays us → our cash/bank comes IN
                $debit  = 0;
                $credit = $amount;
                $desc   = "Payment from customer" . (!empty($notes) ? " — $notes" : "");
                $bal_change = $amount; // money enters our account
            } else {
                // We pay customer → our cash/bank goes OUT
                $debit  = $amount;
                $credit = 0;
                $desc   = "Payment to customer" . (!empty($notes) ? " — $notes" : "");
                $bal_change = -$amount; // money leaves our account
            }

            // 1. Insert into customer_ledger
            $stmt = $conn->prepare("
                INSERT INTO customer_ledger
                    (customer_id, transaction_date, description, debit, credit, reference_type, bank_account_id, payment_method)
                VALUES (?, ?, ?, ?, ?, 'payment', ?, ?)
            ");
            // types: i s s d d i s = 7 params
            $stmt->bind_param("issddis",
                $customer_id, $payment_date, $desc,
                $debit, $credit,
                $bank_account_id, $payment_method
            );
            $stmt->execute();
            $entry_id = $conn->insert_id;
            $stmt->close();

            // 2. Recalculate running balance
            $bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal
                                 FROM customer_ledger WHERE customer_id = $customer_id")->fetch_assoc();
            $running = $bal['bal'];
            $conn->query("UPDATE customer_ledger SET balance = $running WHERE id = $entry_id");
            $conn->query("UPDATE customers SET balance = $running WHERE id = $customer_id");

            // 3. Update cash / bank account balance
            $conn->query("UPDATE bank_accounts
                          SET current_balance = current_balance + ($bal_change)
                          WHERE id = $bank_account_id");

            $conn->commit();
            $label   = $direction === 'from_customer' ? 'received from' : 'paid to';
            $success = "Payment of $ " . number_format($amount, 2) . " $label customer recorded successfully!";
            $_POST   = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$customers     = $conn->query("SELECT id, customer_name, balance FROM customers ORDER BY customer_name ASC");
$bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, account_type, current_balance
                                FROM bank_accounts ORDER BY account_type ASC, account_name ASC");
include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Record Customer Payment</h1>
    <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Payment Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php while ($c = $customers->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['customer_name']) ?> (Bal: <?= number_format($c['balance'], 2) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Direction <span class="text-danger">*</span></label>
                        <div class="d-flex pt-2">
                            <div class="form-check mr-4">
                                <input class="form-check-input" type="radio" name="direction" id="dirFrom" value="from_customer"
                                    <?= (!isset($_POST['direction']) || $_POST['direction'] === 'from_customer') ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="dirFrom">Customer Pays Us</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="direction" id="dirTo" value="to_customer"
                                    <?= (isset($_POST['direction']) && $_POST['direction'] === 'to_customer') ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="dirTo">We Pay Customer</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash"          <?= (!isset($_POST['payment_method']) || $_POST['payment_method'] === 'Cash')          ? 'selected' : '' ?>>Cash</option>
                            <option value="Bank Transfer" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Bank Transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label class="small font-weight-bold" id="acct_label">Cash Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="form-control" required>
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
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes"
                               value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Record Payment
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<script>
const pmSelect   = document.getElementById('payment_method');
const acctSelect = document.getElementById('bank_account_id');
const acctLabel  = document.getElementById('acct_label');

function filterAccounts() {
    const method   = pmSelect.value;
    const isCash   = (method === 'Cash');
    const wantType = isCash ? 'Cash' : 'Bank';

    acctLabel.innerHTML = (isCash ? 'Cash Account' : 'Bank Account') + ' <span class="text-danger">*</span>';

    let firstMatch = null;
    acctSelect.querySelectorAll('option[data-type]').forEach(opt => {
        const show = (opt.dataset.type === wantType);
        opt.style.display = show ? '' : 'none';
        if (show && !firstMatch) firstMatch = opt.value;
    });

    // If current selection is now hidden, auto-select first visible
    const cur = acctSelect.querySelector('option:checked');
    if (!cur || cur.style.display === 'none') {
        acctSelect.value = firstMatch || '';
    }
}

pmSelect.addEventListener('change', filterAccounts);
filterAccounts(); // run on page load
</script>

<?php include '../../includes/footer.php'; ?>
