<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}
$active_page = 'dashboard';
require_once 'config/db.php';
include 'includes/header.php';

$today = date('Y-m-d');
$month_start = date('Y-m-01');

// 1. Current Diesel Stock (Ton)
$current_stock = $conn->query("SELECT COALESCE(SUM(current_stock),0) AS t FROM tanks")->fetch_assoc()['t'];

// 2. Today's Purchase (Qty)
$today_purchase = $conn->query("SELECT COALESCE(SUM(diesel_quantity),0) AS t FROM purchases WHERE purchase_date = '$today'")->fetch_assoc()['t'];

// 3. Today's Sales (Qty)
$today_sales_qty = $conn->query("SELECT COALESCE(SUM(quantity),0) AS t FROM customer_sales WHERE sale_date = '$today'")->fetch_assoc()['t'];

// 4. Today's Sales Amount
$today_sales_amount = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM customer_sales WHERE sale_date = '$today'")->fetch_assoc()['t'];

// 5. Weighted Average Cost Rate (for profit calc)
$purchase_data = $conn->query("SELECT COALESCE(SUM(diesel_quantity),0) AS total_qty, COALESCE(SUM(total_amount),0) AS total_amount, COALESCE(SUM(freight_charges),0) AS total_freight, COALESCE(SUM(other_charges),0) AS total_other FROM purchases")->fetch_assoc();
if ($purchase_data['total_qty'] > 0) {
    $avg_cost_rate = ($purchase_data['total_amount'] + $purchase_data['total_freight'] + $purchase_data['total_other']) / $purchase_data['total_qty'];
} else {
    $avg_cost_rate = 0;
}

// 6. Today's Profit
$today_cogs = $today_sales_qty * $avg_cost_rate;
$today_profit = $today_sales_amount - $today_cogs;

// 7. Total Receivables (customers who owe us - negative balance)
$total_receivables = abs($conn->query("SELECT COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END),0) AS t FROM customers")->fetch_assoc()['t']);

// 8. Total Payables (what we owe suppliers - negative balance)
$total_payables = abs($conn->query("SELECT COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END),0) AS t FROM suppliers")->fetch_assoc()['t']);

// 9. Cash Balance
$cash_balance = $conn->query("SELECT COALESCE(SUM(current_balance),0) AS t FROM bank_accounts WHERE account_type = 'Cash'")->fetch_assoc()['t'];

// 10. Bank Balance
$bank_balance = $conn->query("SELECT COALESCE(SUM(current_balance),0) AS t FROM bank_accounts WHERE account_type = 'Bank'")->fetch_assoc()['t'];

// 11. Monthly Revenue
$monthly_revenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM customer_sales WHERE sale_date >= '$month_start' AND sale_date <= '$today'")->fetch_assoc()['t'];

// 12. Monthly Sales Qty
$monthly_sales_qty = $conn->query("SELECT COALESCE(SUM(quantity),0) AS t FROM customer_sales WHERE sale_date >= '$month_start' AND sale_date <= '$today'")->fetch_assoc()['t'];

// 13. Monthly Profit
$monthly_cogs = $monthly_sales_qty * $avg_cost_rate;
$monthly_profit = $monthly_revenue - $monthly_cogs;
?>
<style>
.card-dashboard .card-body { padding: 1.25rem; }
.card-dashboard .h5 { font-size: 1.5rem; }
</style>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</h1>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Current Diesel Stock (Ton)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($current_stock, 3) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-oil-can fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Purchase</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($today_purchase, 3) ?> Ton</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today's Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($today_sales_qty, 3) ?> Ton</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-cash-register fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Today's Profit</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($today_profit, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Receivables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_receivables, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Payables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_payables, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Cash Balance</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($cash_balance, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Bank Balance</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($bank_balance, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-university fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Monthly Revenue (<?= date('F Y') ?>)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($monthly_revenue, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 mb-4">
        <div class="card border-left-dark shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Monthly Profit (<?= date('F Y') ?>)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($monthly_profit, 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bolt mr-1"></i> Quick Actions</h6>
            </div>
            <div class="card-body">
                <a href="modules/purchases/add.php" class="btn btn-primary btn-block mb-2">
                    <i class="fas fa-plus-circle"></i> New Purchase Entry
                </a>
                <a href="modules/purchases/list.php" class="btn btn-success btn-block">
                    <i class="fas fa-list"></i> View Purchase List
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck mr-1"></i> Suppliers Overview</h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Registered Suppliers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-3x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
