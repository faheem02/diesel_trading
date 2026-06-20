<?php
session_start();
$active_page = 'rpt_trial_balance';
require_once '../../config/db.php';

$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// Supplier ledger balances (credit = purchase, debit = payment)
$supplier_bal = $conn->query("SELECT COALESCE(SUM(debit),0) AS total_debit, COALESCE(SUM(credit),0) AS total_credit FROM supplier_ledger WHERE transaction_date <= '$as_of_date'")->fetch_assoc();

// Customer ledger balances (debit = payment from customer, credit = sale)
$customer_bal = $conn->query("SELECT COALESCE(SUM(debit),0) AS total_debit, COALESCE(SUM(credit),0) AS total_credit FROM customer_ledger WHERE transaction_date <= '$as_of_date'")->fetch_assoc();

// Expenses
$total_expenses = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date <= '$as_of_date'")->fetch_assoc()['t'];

// Bank/Cash account balances
$cash_bal = $conn->query("SELECT COALESCE(SUM(current_balance),0) AS t FROM bank_accounts WHERE account_type = 'Cash'")->fetch_assoc()['t'];
$bank_bal = $conn->query("SELECT COALESCE(SUM(current_balance),0) AS t FROM bank_accounts WHERE account_type = 'Bank'")->fetch_assoc()['t'];

// Debits: Supplies (purchases + freight + other) + Expenses + Cash + Bank
$purchases_total = $conn->query("SELECT COALESCE(SUM(net_purchase_cost),0) AS t FROM purchases WHERE purchase_date <= '$as_of_date'")->fetch_assoc()['t'];
$sales_total = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM customer_sales WHERE sale_date <= '$as_of_date'")->fetch_assoc()['t'];

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-balance-scale mr-1"></i> Trial Balance</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm"><i class="fas fa-print fa-sm"></i> Print</button>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filter</h6></div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">As of Date</label><input type="date" name="as_of_date" class="form-control form-control-sm" value="<?= htmlspecialchars($as_of_date) ?>"></div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Generate</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Debits</h6></div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount ($)</th></tr></thead>
                    <tbody>
                        <tr><td>Purchases (Diesel Cost)</td><td class="text-right"><?= number_format($purchases_total, 2) ?></td></tr>
                        <tr><td>Expenses</td><td class="text-right"><?= number_format($total_expenses, 2) ?></td></tr>
                        <tr><td>Cash Accounts</td><td class="text-right"><?= number_format($cash_bal, 2) ?></td></tr>
                        <tr><td>Bank Accounts</td><td class="text-right"><?= number_format($bank_bal, 2) ?></td></tr>
                        <tr><td>Customer Debits (Payments Received)</td><td class="text-right"><?= number_format($customer_bal['total_debit'], 2) ?></td></tr>
                        <tr><td>Supplier Debits (Payments Made)</td><td class="text-right"><?= number_format($supplier_bal['total_debit'], 2) ?></td></tr>
                    </tbody>
                    <?php
                    $total_debits = $purchases_total + $total_expenses + $cash_bal + $bank_bal + $customer_bal['total_debit'] + $supplier_bal['total_debit'];
                    ?>
                    <tfoot class="table-active"><tr><th>Total Debits</th><th class="text-right"><?= number_format($total_debits, 2) ?></th></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">Credits</h6></div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount ($)</th></tr></thead>
                    <tbody>
                        <tr><td>Sales Revenue</td><td class="text-right"><?= number_format($sales_total, 2) ?></td></tr>
                        <tr><td>Supplier Credits (Purchases on Credit)</td><td class="text-right"><?= number_format($supplier_bal['total_credit'], 2) ?></td></tr>
                        <tr><td>Customer Credits (Sales on Credit)</td><td class="text-right"><?= number_format($customer_bal['total_credit'], 2) ?></td></tr>
                    </tbody>
                    <?php
                    $total_credits = $sales_total + $supplier_bal['total_credit'] + $customer_bal['total_credit'];
                    $diff = $total_debits - $total_credits;
                    ?>
                    <tfoot class="table-active"><tr><th>Total Credits</th><th class="text-right"><?= number_format($total_credits, 2) ?></th></tr></tfoot>
                </table>
            </div>
        </div>
        <?php if (abs($diff) > 0.01): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Difference (Debit - Credit): <strong>$ <?= number_format($diff, 2) ?></strong>
            <?php if ($diff > 0): ?><br><small>Net income adds to credits to balance.</small><?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i> <strong>Balanced</strong> — Debits equal Credits.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
