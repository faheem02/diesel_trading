<?php
session_start();
$active_page = 'sale_list';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$sale = $conn->query("SELECT * FROM customer_sales WHERE id = $id")->fetch_assoc();
if (!$sale) { header("Location: list.php"); exit; }

$customer_id = intval($sale['customer_id']);
$customer = null;
if ($customer_id > 0) {
    $customer = $conn->query("SELECT * FROM customers WHERE id = $customer_id")->fetch_assoc();
}

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($customer_id > 0) {
    $total_opening = $conn->query("SELECT COALESCE(SUM(credit), 0) AS total FROM customer_ledger WHERE customer_id = $customer_id AND reference_type = 'opening_balance'")->fetch_assoc()['total'];
    $total_debit   = $conn->query("SELECT COALESCE(SUM(debit), 0) AS total FROM customer_ledger WHERE customer_id = $customer_id AND reference_type = 'sale'")->fetch_assoc()['total'];
    $total_credit  = $conn->query("SELECT COALESCE(SUM(credit), 0) AS total FROM customer_ledger WHERE customer_id = $customer_id AND reference_type = 'payment'")->fetch_assoc()['total'];
    $current_bal   = $customer['balance'];
    $total_sales   = $conn->query("SELECT COUNT(*) AS cnt FROM customer_sales WHERE customer_id = $customer_id")->fetch_assoc()['cnt'];
    $total_payments = $conn->query("SELECT COUNT(*) AS cnt FROM customer_ledger WHERE customer_id = $customer_id AND reference_type = 'payment'")->fetch_assoc()['cnt'];
} else {
    $total_opening = 0;
    $total_debit = 0;
    $total_credit = 0;
    $current_bal = 0;
    $total_sales = $conn->query("SELECT COUNT(*) AS cnt FROM customer_sales WHERE id = $id")->fetch_assoc()['cnt'];
    $total_payments = 0;
}

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Sale Summary - Invoice #<?= htmlspecialchars($sale['invoice_no']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
        .print-wrapper { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
        .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
        .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
        .print-header .brand .contact { font-size: 13px; color: #555; margin-top: 5px; }
        h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin-bottom: 5px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 10px; }
        .party-line { font-size: 14px; color: #333; margin-bottom: 20px; padding: 8px 12px; background: #f8f9fc; border-radius: 6px; border-left: 4px solid #2C3E50; }
        .party-line .label { font-weight: 700; color: #555; }
        table.summary-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.summary-table th { background: #2C3E50; color: #fff; padding: 10px 14px; font-size: 12px; text-transform: uppercase; text-align: left; }
        table.summary-table th.text-right { text-align: right; }
        table.summary-table td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #eee; }
        table.summary-table td.text-right { text-align: right; font-weight: 600; }
        table.summary-table tfoot td { padding: 10px 14px; font-size: 14px; font-weight: 700; border-top: 2px solid #2C3E50; background: #f8f9fc; }
        table.summary-table tfoot td.text-right { text-align: right; }
        .text-success { color: #155724; }
        .text-danger { color: #721c24; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; margin-top: 20px; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 15mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } table.summary-table th { background: #2C3E50 !important; color: #fff !important; } table.summary-table tfoot td { background: #f8f9fc !important; } .party-line { background: #f8f9fc !important; } }
    </style>
    </head>
    <body>
    <div class="print-wrapper">
        <div class="print-header">
            <div class="logo"><img src="<?= $logo ?>" alt="Logo"></div>
            <div class="brand">
                <div class="company">Muhammad Younas</div>
                <div class="sub">Diesel Management System</div>
                <div class="contact"><i>&#9742;</i> +93 70 260 7159</div>
            </div>
        </div>
        <h2>Sale Summary — Invoice #<?= htmlspecialchars($sale['invoice_no']) ?></h2>
        <div class="subtitle">Generated: <?= date('d M Y h:i A') ?></div>

        <div class="party-line">
            <span class="label">Customer:</span> <?= htmlspecialchars($sale['customer_name']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Mobile:</span> <?= htmlspecialchars($sale['mobile'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Date:</span> <?= htmlspecialchars($sale['sale_date']) ?>
        </div>

        <div class="party-line" style="margin-top:10px;">
            <span class="label">Invoice:</span> <?= htmlspecialchars($sale['invoice_no']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Qty:</span> <?= number_format($sale['quantity'], 3) ?> Ton
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Rate:</span> $ <?= number_format($sale['rate_per_ton'], 0) ?>/Ton
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Total:</span> $ <?= number_format($sale['total_amount'], 2) ?>
        </div>

        <?php if ($customer_id > 0): ?>
        <table class="summary-table" style="margin-top:20px;">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount ($)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Opening Balance</td>
                    <td class="text-right"><?= number_format($total_opening, 2) ?></td>
                </tr>
                <tr>
                    <td>Total Sales (Debit — They Owe Us)</td>
                    <td class="text-right text-danger"><?= number_format($total_debit, 2) ?></td>
                </tr>
                <tr>
                    <td>Total Payments Received (Credit)</td>
                    <td class="text-right text-success"><?= number_format($total_credit, 2) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align:right;font-weight:700;">Current Balance</td>
                    <td class="text-right" style="font-size:16px;"><?= number_format($current_bal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <p class="text-muted" style="margin-top:20px;">Walk-in customer — no ledger summary available.</p>
        <?php endif; ?>

        <div class="no-print" style="text-align:center;margin-top:20px;">
            <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
            <button class="btn-back" onclick="window.close()">Close</button>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
    </body></html>
    <?php exit;
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar mr-1"></i> Sale Summary — Invoice #<?= htmlspecialchars($sale['invoice_no']) ?></h1>
    <div>
        <button onclick="window.open('?id=<?= $id ?>&print=1', '_blank', 'width=1000,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Sale Info -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div style="margin-bottom:15px;padding:8px 12px;background:#f8f9fc;border-radius:6px;border-left:4px solid #2C3E50;font-size:14px;">
            <strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Mobile:</strong> <?= htmlspecialchars($sale['mobile'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Date:</strong> <?= htmlspecialchars($sale['sale_date']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Payment:</strong>
            <span class="badge badge-<?= ($sale['payment_type'] ?? '') === 'Credit' ? 'warning' : 'success' ?>">
                <?= htmlspecialchars($sale['payment_type'] ?? 'Not Set') ?>
            </span>
        </div>
        <table class="table table-bordered mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Invoice No</th>
                    <th class="text-right">Qty (Ton)</th>
                    <th class="text-right">Rate/Ton</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($sale['invoice_no']) ?></td>
                    <td class="text-right"><?= number_format($sale['quantity'], 3) ?></td>
                    <td class="text-right"><?= number_format($sale['rate_per_ton'], 0) ?></td>
                    <td class="text-right font-weight-bold">$ <?= number_format($sale['total_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if ($customer_id > 0): ?>
<!-- Ledger Summary -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user mr-1"></i> Customer Ledger Summary — <?= htmlspecialchars($customer['customer_name']) ?></h6>
    </div>
    <div class="card-body">
        <table class="table table-bordered mb-0">
            <thead class="thead-dark">
                <tr><th>Description</th><th class="text-right">Amount ($)</th></tr>
            </thead>
            <tbody>
                <tr><td>Opening Balance</td><td class="text-right font-weight-bold"><?= number_format($total_opening, 2) ?></td></tr>
                <tr><td>Total Sales (Debit — They Owe Us)</td><td class="text-right text-danger font-weight-bold"><?= number_format($total_debit, 2) ?></td></tr>
                <tr><td>Total Payments Received (Credit)</td><td class="text-right text-success font-weight-bold"><?= number_format($total_credit, 2) ?></td></tr>
                <tr><td>Total Sales Invoices</td><td class="text-right font-weight-bold"><?= $total_sales ?></td></tr>
                <tr><td>Total Payment Entries</td><td class="text-right font-weight-bold"><?= $total_payments ?></td></tr>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fc;">
                    <td class="text-right font-weight-bold">Current Balance</td>
                    <td class="text-right font-weight-bold <?= $current_bal > 0 ? 'text-success' : ($current_bal < 0 ? 'text-danger' : '') ?>" style="font-size:16px;">$ <?= number_format($current_bal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card shadow mb-4">
    <div class="card-body text-center text-muted py-4">
        Walk-in customer — no ledger summary available.
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
