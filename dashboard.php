<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}
$active_page = 'dashboard';
require_once 'config/db.php';
include 'includes/header.php';

$total_purchases = $conn->query("SELECT COUNT(*) AS c FROM purchases")->fetch_assoc()['c'];
$total_cost = $conn->query("SELECT COALESCE(SUM(net_purchase_cost),0) AS t FROM purchases")->fetch_assoc()['t'];
$total_paid = $conn->query("SELECT COALESCE(SUM(paid_amount),0) AS t FROM purchases")->fetch_assoc()['t'];
$pending = $total_cost - $total_paid;
$suppliers = $conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'];
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Purchases</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_purchases ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Cost</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_cost) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Amount Paid</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_paid) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Amount</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($pending) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
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
                <h6 class="m-0 font-weight-bold text-primary">Suppliers Overview</h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Registered Suppliers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $suppliers ?></div>
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
