<?php
if (!isset($_SESSION['user_id'])) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (strpos($script_name, '/auth/') === false) {
        $depth = substr_count(trim(dirname($script_name), '/'), '/');
        $prefix = str_repeat('../', $depth);
        header("Location: " . $prefix . "auth/login.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

// Calculate base path for assets relative to current file
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$depth = substr_count(trim($script_dir, '/'), '/');
$base_path = $depth > 0 ? str_repeat('../', $depth) : './';
$asset_path = $base_path . 'assets/sb-admin2/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Diesel Trading - Purchase Management</title>
    <link href="<?= $asset_path ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="<?= $asset_path ?>css/sb-admin-2.min.css" rel="stylesheet">
    <link href="<?= $asset_path ?>vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <script src="<?= $asset_path ?>vendor/jquery/jquery.min.js"></script>
</head>

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= $base_path ?>dashboard.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-fuel-pump"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Diesel Trading</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                <a class="nav-link" href="<?= $base_path ?>dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Purchases</div>
            <li class="nav-item <?= ($active_page ?? '') === 'purchase_add' ? 'active' : '' ?>">
                <a class="nav-link" href="<?= $base_path ?>modules/purchases/add.php">
                    <i class="fas fa-fw fa-plus-circle"></i>
                    <span>New Purchase</span>
                </a>
            </li>
            <li class="nav-item <?= ($active_page ?? '') === 'purchase_list' ? 'active' : '' ?>">
                <a class="nav-link" href="<?= $base_path ?>modules/purchases/list.php">
                    <i class="fas fa-fw fa-list"></i>
                    <span>Purchase List</span>
                </a>
            </li>
            <li class="nav-item <?= ($active_page ?? '') === 'purchase_return' ? 'active' : '' ?>">
                <a class="nav-link" href="<?= $base_path ?>modules/purchases/returns.php">
                    <i class="fas fa-fw fa-undo-alt"></i>
                    <span>Purchase Return</span>
                </a>
            </li>
            <li class="nav-item <?= ($active_page ?? '') === 'purchase_adjustment' ? 'active' : '' ?>">
                <a class="nav-link" href="<?= $base_path ?>modules/purchases/adjustments.php">
                    <i class="fas fa-fw fa-sliders-h"></i>
                    <span>Adjustment</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                                <i class="fas fa-user-circle fa-2x text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="<?= $base_path ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
