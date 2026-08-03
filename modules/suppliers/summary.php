<?php
session_start();
$active_page = 'supplier_list';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$supplier = $conn->query("SELECT * FROM suppliers WHERE id = $id")->fetch_assoc();
if (!$supplier) { header("Location: list.php"); exit; }

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$date_filter = "";
$date_types = "";
$date_params = [];
if (!empty($from_date)) {
    $date_filter .= " AND transaction_date >= ?";
    $date_types .= "s";
    $date_params[] = $from_date;
}
if (!empty($to_date)) {
    $date_filter .= " AND transaction_date <= ?";
    $date_types .= "s";
    $date_params[] = $to_date;
}

$total_opening = $conn->query("SELECT COALESCE(SUM(debit), 0) AS total FROM supplier_ledger WHERE supplier_id = $id AND reference_type = 'opening_balance'")->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total_entries, COALESCE(SUM(credit), 0) AS total_credit, COALESCE(SUM(debit), 0) AS total_debit FROM supplier_ledger WHERE supplier_id = ? AND reference_type = 'purchase' $date_filter");
$bind_types = "i" . $date_types;
$bind_params = array_merge([$id], $date_params);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$purchase_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt2 = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(debit), 0) AS total FROM supplier_ledger WHERE supplier_id = ? AND reference_type = 'payment' $date_filter");
$stmt2->bind_param($bind_types, ...$bind_params);
$stmt2->execute();
$payment_summary = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$total_purchases_credit = $purchase_summary['total_credit'];
$total_payments_debit = $payment_summary['total'];
$purchase_count = $purchase_summary['total_entries'];
$payment_count = $payment_summary['cnt'];
$current_bal = $supplier['balance'];

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?>
    <!DOCTYPE html>
    <html lang="en" dir="ltr">
    <head>
    <meta charset="UTF-8">
    <title>Summary - <?= htmlspecialchars($supplier['company_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
        .print-wrapper { max-width: 400px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 30px 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { text-align: center; border-bottom: 3px solid #2C3E50; padding-bottom: 12px; margin-bottom: 15px; }
        .print-header .logo { width: 60px; height: 60px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; margin: 0 auto 8px; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .company { font-size: 20px; font-weight: 900; color: #2C3E50; }
        .print-header .sub { font-size: 11px; color: #F39C12; font-weight: 700; letter-spacing: 2px; margin-top: 2px; }
        .party-name { font-size: 16px; font-weight: 700; color: #2C3E50; text-align: center; margin: 10px 0 15px; padding: 8px; background: #f8f9fc; border-radius: 6px; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 15px; }
        .summary-row .label { font-weight: 600; color: #555; }
        .summary-row .value { font-weight: 700; font-size: 16px; }
        .summary-row .value.green { color: #28a745; }
        .summary-row .value.red { color: #dc3545; }
        .summary-row.total { border-bottom: none; border-top: 2px solid #2C3E50; margin-top: 5px; padding-top: 12px; }
        .summary-row.total .label { font-size: 16px; font-weight: 700; color: #2C3E50; }
        .summary-row.total .value { font-size: 18px; }
        .date-line { text-align: center; font-size: 11px; color: #999; margin-top: 15px; }
        .btn-print { display: inline-block; padding: 10px 30px; background: #2C3E50; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; margin-top: 15px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 10px 25px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; margin-left: 8px; }
        .no-print { text-align: center; }
        @media print {
            @page { size: 72mm 150mm; margin: 0; }
            html, body { direction: ltr; width: 72mm; height: 150mm; margin: 0; padding: 0; background: #fff; overflow: hidden; }
            body { padding: 3mm 2mm; }
            .print-wrapper { max-width: 68mm; width: 68mm; margin: 0; padding: 0; background: #fff; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
            .print-header { border-bottom: 2px solid #2C3E50 !important; padding-bottom: 3mm; margin-bottom: 3mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-header .logo { width: 12mm; height: 12mm; border-width: 1.5px; }
            .print-header .company { font-size: 11pt; }
            .print-header .sub { font-size: 7pt; letter-spacing: 1px; }
            .party-name { font-size: 10pt; padding: 2mm; margin: 2mm 0 3mm; }
            .summary-row { padding: 2.5mm 1mm; font-size: 9pt; border-bottom-width: 0.5px; }
            .summary-row .label { font-size: 9pt; }
            .summary-row .value { font-size: 10pt; }
            .summary-row.total .label { font-size: 10pt; }
            .summary-row.total .value { font-size: 11pt; }
            .date-line { font-size: 7pt; margin-top: 3mm; }
        }
    </style>
    </head>
    <body>
    <div class="print-wrapper">
        <div class="print-header">
            <div class="logo"><img src="<?= $logo ?>" alt="Logo"></div>
            <div class="company">Muhammad Younas</div>
            <div class="sub">DIESEL MANAGEMENT</div>
        </div>

        <div class="party-name"><?= htmlspecialchars($supplier['company_name']) ?></div>

        <div class="summary-row">
            <span class="label">Opening Balance</span>
            <span class="value"><?= number_format($total_opening, 2) ?></span>
        </div>
        <div class="summary-row">
            <span class="label">Total Purchases</span>
            <span class="value red"><?= number_format($total_purchases_credit, 2) ?></span>
        </div>
        <div class="summary-row">
            <span class="label">Total Payments</span>
            <span class="value green"><?= number_format($total_payments_debit, 2) ?></span>
        </div>
        <div class="summary-row total">
            <span class="label">Balance</span>
            <span class="value <?= $current_bal > 0 ? 'red' : 'green' ?>"><?= number_format($current_bal, 2) ?></span>
        </div>

        <div class="date-line"><?= date('d M Y h:i A') ?></div>

        <div class="no-print">
            <button class="btn-print" onclick="window.print()">Print</button>
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar mr-1"></i> Summary — <?= htmlspecialchars($supplier['company_name']) ?></h1>
    <div>
        <button onclick="window.open('?id=<?= $id ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=500,height=500')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> ٹھرمل پرنٹ
        </button>
        <a href="ledger.php?id=<?= $id ?>" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm mr-1">
            <i class="fas fa-book"></i> Ledger
        </a>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="summary.php?id=<?= $id ?>" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Party info + Summary -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div style="margin-bottom:15px;padding:8px 12px;background:#f8f9fc;border-radius:6px;border-left:4px solid #2C3E50;font-size:14px;">
            <strong>Supplier:</strong> <?= htmlspecialchars($supplier['company_name']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Contact:</strong> <?= htmlspecialchars($supplier['contact_person'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Phone:</strong> <?= htmlspecialchars($supplier['phone'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>NTN/CNIC:</strong> <?= htmlspecialchars($supplier['ntn_cnic'] ?: '-') ?>
        </div>
        <table class="table table-bordered mb-0">
            <thead class="thead-dark">
                <tr><th>Description</th><th class="text-right">Amount ($)</th></tr>
            </thead>
            <tbody>
                <tr><td>Opening Balance</td><td class="text-right font-weight-bold"><?= number_format($total_opening, 2) ?></td></tr>
                <tr><td>Total Purchases (Credit)</td><td class="text-right text-danger font-weight-bold"><?= number_format($total_purchases_credit, 2) ?></td></tr>
                <tr><td>Total Payments Made (Debit)</td><td class="text-right text-success font-weight-bold"><?= number_format($total_payments_debit, 2) ?></td></tr>
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

<?php include '../../includes/footer.php'; ?>

<style>
@media print {
    @page { size: 72mm 150mm; margin: 0; }
    html, body { width: 72mm; height: 150mm; margin: 0; padding: 0; background: #fff; overflow: hidden; }
    body { padding: 3mm 2mm; }
    #wrapper, #wrapper > * { display: none !important; }
    .card { box-shadow: none !important; border: none !important; max-width: 68mm !important; width: 68mm !important; margin: 0 !important; padding: 2mm !important; }
    .card-header { background: #2C3E50 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 3mm !important; }
    .card-header h6 { color: #fff !important; font-size: 11px !important; }
    .card-body { padding: 2mm !important; }
    .card-body table { font-size: 11px !important; }
    .card-body table td { padding: 3mm 2mm !important; }
    .d-sm-flex { display: none !important; }
}
</style>
