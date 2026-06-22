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

$purchases_active    = in_array($active_page ?? '', ['purchase_add', 'purchase_list', 'purchase_return', 'purchase_return_list', 'purchase_adjustment']);
$suppliers_active    = in_array($active_page ?? '', ['supplier_add', 'supplier_list', 'supplier_ledger', 'supplier_payment', 'supplier_outstanding', 'supplier_payment_history']);
$diesel_stock_active = in_array($active_page ?? '', ['tank_list', 'stock_in', 'stock_in_list', 'sale_add', 'sale_list', 'stock_adjustment', 'adjustment_list', 'stock_report_current', 'stock_report_tank_wise', 'stock_report_daily', 'stock_report_ledger']);
$customers_active    = in_array($active_page ?? '', ['customer_add', 'customer_list', 'customer_ledger', 'customer_payment', 'customer_recovery']);
$sales_mgmt_active   = in_array($active_page ?? '', ['sale_entry', 'sale_list', 'sale_return', 'sale_return_list', 'sales_outstanding']);
$tanker_active       = in_array($active_page ?? '', ['tanker_list', 'tanker_expense_add', 'tanker_expense_list', 'expense_add', 'expense_list']);
$accounts_active     = in_array($active_page ?? '', ['cashbook', 'bankbook', 'accounts_manage', 'general_ledger']);

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
    
    <style>
        @media print {
            body * { visibility: hidden; }
            .container-fluid, .container-fluid * { visibility: visible; }
            .container-fluid { position: absolute; left: 0; top: 0; width: 100%; }
            #wrapper, #content-wrapper, #content { margin: 0 !important; padding: 0 !important; }
            .sidebar, .topbar, .btn, .card-header .btn, .no-print,
            .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate,
            #sidebarToggle, hr.sidebar-divider, .sidebar-brand, .sidebar-heading,
            .nav-item, .card-header a, .card-header button, form, .card-body a:not(.btn) { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            .card-header { background: #f8f9fc !important; border: 1px solid #ddd !important; }
            .table { border-collapse: collapse !important; }
            .table th, .table td { border: 1px solid #000 !important; }
            @page { margin: 0.5in; }
        }
        
        :root {
            --navy: #2C3E50;
            --navy-dark: #1A252F;
            --amber: #F39C12;
            --amber-dark: #D68910;
            --amber-light: #FEF5E7;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(180deg, var(--navy) 10%, var(--navy-dark) 100%) !important;
        }
        .sidebar-brand {
            background-color: var(--navy-dark) !important;
        }
        .sidebar-dark .nav-item .nav-link:focus, .sidebar-dark .nav-item .nav-link:hover,
        .sidebar-dark .nav-item.active > .nav-link {
            color: var(--amber) !important;
        }
        .sidebar-dark .nav-item.active > .nav-link::after {
            background-color: var(--amber) !important;
        }
        .sidebar-dark #sidebarToggle:hover {
            background-color: rgba(243, 156, 18, 0.2) !important;
        }
        .btn-primary {
            background-color: var(--amber) !important;
            border-color: var(--amber) !important;
            color: #fff !important;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--amber-dark) !important;
            border-color: var(--amber-dark) !important;
        }
        .btn-outline-primary {
            color: var(--amber) !important;
            border-color: var(--amber) !important;
        }
        .btn-outline-primary:hover {
            background-color: var(--amber) !important;
            border-color: var(--amber) !important;
            color: #fff !important;
        }
        .text-primary {
            color: var(--amber) !important;
        }
        .border-primary {
            border-color: var(--amber) !important;
        }
        .border-left-primary {
            border-left-color: var(--amber) !important;
        }
        a {
            color: #D68910;
        }
        a:hover {
            color: #B87A0E;
        }
        .page-item.active .page-link {
            background-color: var(--navy) !important;
            border-color: var(--navy) !important;
            color: #fff !important;
        }
        .page-link {
            color: var(--navy) !important;
            background-color: #fff !important;
        }
        .page-link:hover {
            color: var(--navy-dark) !important;
            background-color: #f8f9fc !important;
        }
        .page-item.disabled .page-link {
            color: #b7b9cc !important;
        }
        .card-header {
            background-color: var(--amber-light) !important;
            border-bottom: 1px solid #FDEBD0 !important;
        }
        .card-header .font-weight-bold.text-primary {
            color: var(--navy) !important;
        }
        .table .thead-dark th {
            background-color: var(--navy) !important;
            border-color: var(--navy-dark) !important;
        }
        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--amber) !important;
            box-shadow: 0 0 0 0.2rem rgba(243, 156, 18, 0.25) !important;
        }
        .topbar .dropdown .dropdown-menu .dropdown-item:focus, .topbar .dropdown .dropdown-menu .dropdown-item:hover {
            background-color: var(--amber-light) !important;
            color: var(--navy) !important;
        }
        .bg-gradient-primary .sidebar-brand-text,
        .bg-gradient-primary .sidebar-brand-icon i {
            color: var(--amber) !important;
        }
        .sidebar .sidebar-heading {
            color: rgba(243, 156, 18, 0.7) !important;
        }
        #tankersTable td {
            overflow: visible;
        }
        #tankersTable .form-control-sm {
            transition: width 0.1s ease;
            min-width: 60px;
        }
        .sidebar-dark .nav-item.active > .nav-link {
            color: var(--amber) !important;
        }
        .sidebar-dark .nav-item .nav-link[data-toggle="collapse"]::after {
            color: rgba(255,255,255,0.35) !important;
        }
        .sidebar-dark .nav-item.active .nav-link[data-toggle="collapse"]::after {
            color: var(--amber) !important;
        }
        .collapse-inner {
            background: transparent !important;
            margin: 0 6px !important;
        }
        .collapse-inner .collapse-header {
            color: rgba(243, 156, 18, 0.6) !important;
            font-size: 0.65rem !important;
            letter-spacing: 0.5px;
            padding: 0.3rem 0.5rem !important;
        }
        .collapse-inner .collapse-item {
            color: rgba(255,255,255,0.75) !important;
            padding: 0.35rem 0.5rem !important;
            border-radius: 4px !important;
            font-size: 0.85rem !important;
            transition: all 0.15s ease;
        }
        .collapse-inner .collapse-item:hover,
        .collapse-inner .collapse-item:focus {
            background: rgba(243, 156, 18, 0.15) !important;
            color: #fff !important;
        }
        .collapse-inner .collapse-item.active {
            background: rgba(243, 156, 18, 0.12) !important;
            color: var(--amber) !important;
            font-weight: 600;
        }
        .collapse-inner .collapse-item i {
            width: 16px;
            text-align: center;
        }

        /* ===== FIXED FORM INPUT STYLES ===== */
        /* Make all form controls a consistent, readable size */
        .form-control {
            width: 100% !important;
            height: 42px !important;
            font-size: 14px !important;
            padding: 8px 14px !important;
            border-radius: 5px !important;
            border: 1px solid #d1d3e2 !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
            box-sizing: border-box !important;
        }

        .form-control:focus {
            border-color: #F39C12 !important;
            box-shadow: 0 0 0 0.2rem rgba(243, 156, 18, 0.25) !important;
        }

        .form-control-sm {
            height: 36px !important;
            font-size: 13px !important;
            padding: 6px 12px !important;
        }

        .form-control-lg {
            height: 48px !important;
            font-size: 16px !important;
            padding: 10px 18px !important;
        }

        /* Override any inline width set by JavaScript */
        input.form-control[style*="width"] {
            width: 100% !important;
        }

        /* Select dropdowns */
        select.form-control {
            height: 42px !important;
            padding: 6px 12px !important;
        }

        select.form-control-sm {
            height: 36px !important;
        }

        /* Textareas */
        textarea.form-control {
            min-height: 80px !important;
            resize: vertical !important;
        }

        /* Labels */
        label, .form-label {
            font-size: 14px !important;
            font-weight: 600 !important;
            margin-bottom: 5px !important;
            color: #333 !important;
        }

        /* Form groups */
        .form-group {
            margin-bottom: 16px !important;
        }

        /* Modal inputs */
        .modal .form-control {
            height: 42px !important;
            font-size: 14px !important;
        }

        .modal .form-control-sm {
            height: 36px !important;
        }

        .modal-body {
            padding: 25px !important;
        }

        .modal .form-group {
            margin-bottom: 18px !important;
        }

        /* Table inputs */
        .table .form-control,
        .table .form-control-sm {
            height: 34px !important;
            font-size: 13px !important;
            padding: 4px 10px !important;
            border-radius: 4px !important;
        }

        /* DataTable inputs */
        .dataTables_wrapper .dataTables_filter input {
            height: 38px !important;
            padding: 6px 14px !important;
            font-size: 14px !important;
            margin-left: 8px !important;
            border-radius: 4px !important;
            border: 1px solid #d1d3e2 !important;
        }

        .dataTables_wrapper .dataTables_length select {
            height: 38px !important;
            padding: 4px 10px !important;
            font-size: 14px !important;
            border-radius: 4px !important;
            border: 1px solid #d1d3e2 !important;
        }

        /* Buttons */
        .btn {
            padding: 8px 20px !important;
            font-size: 14px !important;
            border-radius: 5px !important;
            font-weight: 500 !important;
        }

        .btn-sm {
            padding: 6px 14px !important;
            font-size: 13px !important;
            border-radius: 4px !important;
        }

        .btn-lg {
            padding: 12px 30px !important;
            font-size: 16px !important;
            border-radius: 6px !important;
        }

        /* Placeholder text */
        ::placeholder {
            color: #999 !important;
            font-size: 14px !important;
            opacity: 1 !important;
        }

        /* Input groups */
        .input-group .form-control {
            height: 42px !important;
        }

        .input-group .input-group-text {
            height: 42px !important;
            padding: 0 15px !important;
            font-size: 14px !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-control {
                height: 38px !important;
                font-size: 13px !important;
                padding: 6px 12px !important;
            }
            
            .modal .form-control {
                height: 38px !important;
                font-size: 13px !important;
            }
            
            .btn {
                padding: 6px 16px !important;
                font-size: 13px !important;
            }
        }
    </style>
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
            <li class="nav-item <?= $purchases_active ? 'active' : '' ?>">
                <a class="nav-link <?= $purchases_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapsePurchases" aria-expanded="<?= $purchases_active ? 'true' : 'false' ?>" aria-controls="collapsePurchases">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Purchases</span>
                </a>
                <div id="collapsePurchases" class="collapse <?= $purchases_active ? 'show' : '' ?>" aria-labelledby="headingPurchases" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Manage Purchases:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'purchase_add' ? 'active' : '' ?>" href="<?= $base_path ?>modules/purchases/add.php">
                            <i class="fas fa-fw fa-plus-circle fa-sm mr-1"></i> New Purchase
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'purchase_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/purchases/list.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Purchase List
                        </a>
                    </div>
                </div>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Suppliers</div>
            <li class="nav-item <?= $suppliers_active ? 'active' : '' ?>">
                <a class="nav-link <?= $suppliers_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseSuppliers" aria-expanded="<?= $suppliers_active ? 'true' : 'false' ?>" aria-controls="collapseSuppliers">
                    <i class="fas fa-fw fa-truck"></i>
                    <span>Suppliers</span>
                </a>
                <div id="collapseSuppliers" class="collapse <?= $suppliers_active ? 'show' : '' ?>" aria-labelledby="headingSuppliers" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Manage Suppliers:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'supplier_add' ? 'active' : '' ?>" href="<?= $base_path ?>modules/suppliers/add.php">
                            <i class="fas fa-fw fa-plus-circle fa-sm mr-1"></i> Add New Supplier
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'supplier_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/suppliers/list.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Supplier List
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'supplier_ledger' ? 'active' : '' ?>" href="<?= $base_path ?>modules/suppliers/ledger.php">
                            <i class="fas fa-fw fa-book fa-sm mr-1"></i> Ledger
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'supplier_payment' ? 'active' : '' ?>" href="<?= $base_path ?>modules/suppliers/payment.php">
                            <i class="fas fa-fw fa-money-bill-wave fa-sm mr-1"></i> Payment
                        </a>
                        <h6 class="collapse-header">Reports:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'supplier_outstanding' ? 'active' : '' ?>" href="<?= $base_path ?>modules/suppliers/reports/outstanding.php">
                            <i class="fas fa-fw fa-chart-bar fa-sm mr-1"></i> Outstanding
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'supplier_payment_history' ? 'active' : '' ?>" href="<?= $base_path ?>modules/suppliers/reports/payment_history.php">
                            <i class="fas fa-fw fa-history fa-sm mr-1"></i> Payment History
                        </a>
                    </div>
                </div>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Diesel Stock</div>
            <li class="nav-item <?= $diesel_stock_active ? 'active' : '' ?>">
                <a class="nav-link <?= $diesel_stock_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseStock" aria-expanded="<?= $diesel_stock_active ? 'true' : 'false' ?>" aria-controls="collapseStock">
                    <i class="fas fa-fw fa-oil-can"></i>
                    <span>Diesel Stock</span>
                </a>
                <div id="collapseStock" class="collapse <?= $diesel_stock_active ? 'show' : '' ?>" aria-labelledby="headingStock" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Manage Stock:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'tank_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/diesel_stock/tanks.php">
                            <i class="fas fa-fw fa-oil-can fa-sm mr-1"></i> Tank Wise Stock
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'stock_adjustment' ? 'active' : '' ?>" href="<?= $base_path ?>modules/diesel_stock/adjustments.php">
                            <i class="fas fa-fw fa-sliders-h fa-sm mr-1"></i> Stock Adjustment
                        </a>
                        <h6 class="collapse-header">Reports:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'stock_report_tank_wise' ? 'active' : '' ?>" href="<?= $base_path ?>modules/diesel_stock/reports/tank_wise_stock.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Stock Summary
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'stock_report_ledger' ? 'active' : '' ?>" href="<?= $base_path ?>modules/diesel_stock/reports/stock_ledger.php">
                            <i class="fas fa-fw fa-book fa-sm mr-1"></i> Stock Ledger
                        </a>
                    </div>
                </div>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Customers</div>
            <li class="nav-item <?= $customers_active ? 'active' : '' ?>">
                <a class="nav-link <?= $customers_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseCustomers" aria-expanded="<?= $customers_active ? 'true' : 'false' ?>" aria-controls="collapseCustomers">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Customers</span>
                </a>
                <div id="collapseCustomers" class="collapse <?= $customers_active ? 'show' : '' ?>" aria-labelledby="headingCustomers" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Manage Customers:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'customer_add' ? 'active' : '' ?>" href="<?= $base_path ?>modules/customers/add.php">
                            <i class="fas fa-fw fa-plus-circle fa-sm mr-1"></i> Add Customer
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'customer_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/customers/list.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Customer List
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'customer_ledger' ? 'active' : '' ?>" href="<?= $base_path ?>modules/customers/ledger.php">
                            <i class="fas fa-fw fa-book fa-sm mr-1"></i> Ledger
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'customer_payment' ? 'active' : '' ?>" href="<?= $base_path ?>modules/customers/payment.php">
                            <i class="fas fa-fw fa-money-bill-wave fa-sm mr-1"></i> Record Payment
                        </a>
                        <h6 class="collapse-header">Reports:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'customer_recovery' ? 'active' : '' ?>" href="<?= $base_path ?>modules/customers/reports/recovery.php">
                            <i class="fas fa-fw fa-hand-holding-usd fa-sm mr-1"></i> Recovery
                        </a>
                    </div>
                </div>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Sales Management</div>
            <li class="nav-item <?= $sales_mgmt_active ? 'active' : '' ?>">
                <a class="nav-link <?= $sales_mgmt_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseSalesMgmt" aria-expanded="<?= $sales_mgmt_active ? 'true' : 'false' ?>" aria-controls="collapseSalesMgmt">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Sales</span>
                </a>
                <div id="collapseSalesMgmt" class="collapse <?= $sales_mgmt_active ? 'show' : '' ?>" aria-labelledby="headingSalesMgmt" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Manage Sales:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'sale_entry' ? 'active' : '' ?>" href="<?= $base_path ?>modules/sales/add.php">
                            <i class="fas fa-fw fa-plus-circle fa-sm mr-1"></i> Customer Sale Entry
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'sale_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/sales/list.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Sales List
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'sale_return' ? 'active' : '' ?>" href="<?= $base_path ?>modules/sales/returns.php">
                            <i class="fas fa-fw fa-undo-alt fa-sm mr-1"></i> New Return
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'sale_return_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/sales/returns_list.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Return List
                        </a>
                        <h6 class="collapse-header">Reports:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'sales_outstanding' ? 'active' : '' ?>" href="<?= $base_path ?>modules/sales/reports/outstanding.php">
                            <i class="fas fa-fw fa-chart-bar fa-sm mr-1"></i> Outstanding
                        </a>
                    </div>
                </div>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Expenses</div>
            <li class="nav-item <?= $tanker_active ? 'active' : '' ?>">
                <a class="nav-link <?= $tanker_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseTankers" aria-expanded="<?= $tanker_active ? 'true' : 'false' ?>" aria-controls="collapseTankers">
                    <i class="fas fa-fw fa-truck"></i>
                    <span>Expenses</span>
                </a>
                <div id="collapseTankers" class="collapse <?= $tanker_active ? 'show' : '' ?>" aria-labelledby="headingTankers" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= ($active_page ?? '') === 'tanker_expense_add' ? 'active' : '' ?>" href="<?= $base_path ?>modules/tankers/expenses_add.php">
                            <i class="fas fa-fw fa-plus-circle fa-sm mr-1"></i> Add Expense
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'tanker_expense_list' ? 'active' : '' ?>" href="<?= $base_path ?>modules/tankers/expenses_list.php">
                            <i class="fas fa-fw fa-list fa-sm mr-1"></i> Expense List
                        </a>
                    </div>
                </div>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="sidebar-heading">Accounts</div>
            <li class="nav-item <?= $accounts_active ? 'active' : '' ?>">
                <a class="nav-link <?= $accounts_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseAccounts" aria-expanded="<?= $accounts_active ? 'true' : 'false' ?>" aria-controls="collapseAccounts">
                    <i class="fas fa-fw fa-wallet"></i>
                    <span>Cash & Bank</span>
                </a>
                <div id="collapseAccounts" class="collapse <?= $accounts_active ? 'show' : '' ?>" aria-labelledby="headingAccounts" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Books:</h6>
                        <a class="collapse-item <?= ($active_page ?? '') === 'cashbook' ? 'active' : '' ?>" href="<?= $base_path ?>modules/accounts/cashbook.php">
                            <i class="fas fa-fw fa-money-bill-wave fa-sm mr-1"></i> Cash Book
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'bankbook' ? 'active' : '' ?>" href="<?= $base_path ?>modules/accounts/bankbook.php">
                            <i class="fas fa-fw fa-university fa-sm mr-1"></i> Bank Book
                        </a>
                        <a class="collapse-item <?= ($active_page ?? '') === 'general_ledger' ? 'active' : '' ?>" href="<?= $base_path ?>modules/accounts/general_ledger.php">
                            <i class="fas fa-fw fa-book fa-sm mr-1"></i> General Ledger
                        </a>
                    </div>
                </div>
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
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User') ?></span>
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