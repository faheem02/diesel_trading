<?php
session_start();
$active_page = 'rpt_balance_sheet';
require_once '../../config/db.php';

$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// ===== ASSETS =====
// Cash accounts
$cash_bal = $conn->query("SELECT COALESCE(SUM(current_balance),0) AS t FROM bank_accounts WHERE account_type = 'Cash'")->fetch_assoc()['t'];

// Bank accounts
$bank_accounts = [];
$ba = $conn->query("SELECT id, account_name, bank_name, account_number, current_balance FROM bank_accounts WHERE account_type = 'Bank' ORDER BY account_name");
while ($b = $ba->fetch_assoc()) $bank_accounts[] = $b;
$total_bank = array_sum(array_column($bank_accounts, 'current_balance'));

// Customer receivables (balance where customer owes us)
$customer_receivables = $conn->query("SELECT COALESCE(SUM(balance),0) AS t FROM customers WHERE balance > 0")->fetch_assoc()['t'];

// Supplier receivables (balance where supplier owes us — credit balance in supplier_ledger means we owe them,
// but debit balance means they owe us)
$supplier_receivables = $conn->query("SELECT COALESCE(SUM(balance),0) AS t FROM suppliers WHERE balance < 0")->fetch_assoc()['t'];
$supplier_receivables = abs($supplier_receivables);

// Stock value (at average cost)
$total_stock = $conn->query("SELECT COALESCE(SUM(current_stock),0) AS t FROM tanks")->fetch_assoc()['t'];
// Get average purchase rate
$avg_purchase_rate = 0;
$purch = $conn->query("SELECT SUM(diesel_quantity) AS qty, SUM(net_purchase_cost) AS cost FROM purchases")->fetch_assoc();
if ($purch['qty'] > 0) $avg_purchase_rate = $purch['cost'] / $purch['qty'];
$stock_value = $total_stock * $avg_purchase_rate;

$total_assets = $cash_bal + $total_bank + $customer_receivables + $supplier_receivables + $stock_value;

// ===== LIABILITIES =====
// Supplier payables (balance where we owe supplier)
$supplier_payables = $conn->query("SELECT COALESCE(SUM(balance),0) AS t FROM suppliers WHERE balance > 0")->fetch_assoc()['t'];

// Customer payables (balance where we owe customer — negative balance means we owe them)
$customer_payables = $conn->query("SELECT COALESCE(SUM(balance),0) AS t FROM customers WHERE balance < 0")->fetch_assoc()['t'];
$customer_payables = abs($customer_payables);

$total_liabilities = $supplier_payables + $customer_payables;

// ===== EQUITY =====
$equity = $total_assets - $total_liabilities;

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice mr-1"></i> Balance Sheet</h1>
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
        <!-- Assets -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success"><i class="fas fa-arrow-left mr-1"></i> Assets</h6></div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <tr class="bg-light"><th colspan="2" class="text-primary">Current Assets</th></tr>
                    <tr><td class="pl-4">Cash on Hand</td><td class="text-right font-weight-bold"><?= number_format($cash_bal, 2) ?></td></tr>
                    <tr><td class="pl-4">Bank Accounts</td><td class="text-right font-weight-bold"><?= number_format($total_bank, 2) ?></td></tr>
                    <?php foreach ($bank_accounts as $b): ?>
                    <tr><td class="pl-5 text-muted small"><?= htmlspecialchars($b['account_name']) ?></td><td class="text-right text-muted small"><?= number_format($b['current_balance'], 2) ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td class="pl-4">Accounts Receivable (Customers)</td><td class="text-right font-weight-bold"><?= number_format($customer_receivables, 2) ?></td></tr>
                    <tr><td class="pl-4">Due from Suppliers</td><td class="text-right font-weight-bold"><?= number_format($supplier_receivables, 2) ?></td></tr>
                    <tr class="bg-light"><th colspan="2" class="text-primary">Inventory</th></tr>
                    <tr><td class="pl-4">Diesel Stock (<?= number_format($total_stock, 3) ?> Tons @ Avg $ <?= number_format($avg_purchase_rate, 2) ?>/ton)</td><td class="text-right font-weight-bold"><?= number_format($stock_value, 2) ?></td></tr>
                    <tr class="table-success"><th>Total Assets</th><th class="text-right"><?= number_format($total_assets, 2) ?></th></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <!-- Liabilities & Equity -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-arrow-right mr-1"></i> Liabilities &amp; Equity</h6></div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <tr class="bg-light"><th colspan="2" class="text-primary">Current Liabilities</th></tr>
                    <tr><td class="pl-4">Accounts Payable (Suppliers)</td><td class="text-right font-weight-bold"><?= number_format($supplier_payables, 2) ?></td></tr>
                    <tr><td class="pl-4">Customer Credit Balances</td><td class="text-right font-weight-bold"><?= number_format($customer_payables, 2) ?></td></tr>
                    <tr class="table-warning"><th>Total Liabilities</th><th class="text-right"><?= number_format($total_liabilities, 2) ?></th></tr>

                    <tr class="bg-light"><th colspan="2" class="text-primary">Equity</th></tr>
                    <tr><td class="pl-4">Opening Capital / Retained Earnings</td><td class="text-right font-weight-bold"><?= number_format($equity, 2) ?></td></tr>
                    <tr class="table-info"><th>Total Liabilities &amp; Equity</th><th class="text-right"><?= number_format($total_liabilities + $equity, 2) ?></th></tr>
                </table>
            </div>
        </div>

        <?php
        $bal_check = abs(($total_liabilities + $equity) - $total_assets);
        ?>
        <?php if ($bal_check < 1): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i> <strong>Balanced</strong> — Assets = Liabilities + Equity ($ <?= number_format($total_assets, 2) ?>)</div>
        <?php else: ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i> Difference of $ <?= number_format($bal_check, 2) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
