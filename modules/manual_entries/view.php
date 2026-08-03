<?php
session_start();
$active_page = 'manual_entry';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$person = trim($_GET['person'] ?? '');
if (empty($person)) { header("Location: list.php"); exit; }

$stmt = $conn->prepare("SELECT SUM(total_amount) AS total_amount, SUM(paid_amount) AS paid_amount FROM manual_entries WHERE person_name = ?");
$stmt->bind_param("s", $person);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { header("Location: list.php"); exit; }

$total_amount = $row['total_amount'] ?? 0;
$paid_amount  = $row['paid_amount'] ?? 0;
$balance      = $total_amount - $paid_amount;

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?>
    <!DOCTYPE html>
    <html lang="ur" dir="rtl">
    <head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($person) ?> - تفصیل</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; direction: rtl; }
        .print-wrapper { max-width: 400px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 30px 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { text-align: center; border-bottom: 3px solid #2C3E50; padding-bottom: 12px; margin-bottom: 15px; }
        .print-header .logo { width: 60px; height: 60px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; margin: 0 auto 8px; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .company { font-size: 20px; font-weight: 900; color: #2C3E50; }
        .print-header .sub { font-size: 11px; color: #F39C12; font-weight: 700; letter-spacing: 2px; margin-top: 2px; }
        .person-name { font-size: 16px; font-weight: 700; color: #2C3E50; text-align: center; margin: 10px 0 15px; padding: 8px; background: #f8f9fc; border-radius: 6px; }
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
            html, body { direction: rtl; width: 72mm; height: 150mm; margin: 0; padding: 0; background: #fff; overflow: hidden; }
            body { padding: 3mm 2mm; }
            .print-wrapper { max-width: 68mm; width: 68mm; margin: 0; padding: 0; background: #fff; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
            .print-header { border-bottom: 2px solid #2C3E50 !important; padding-bottom: 3mm; margin-bottom: 3mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-header .logo { width: 12mm; height: 12mm; border-width: 1.5px; }
            .print-header .company { font-size: 11pt; }
            .print-header .sub { font-size: 7pt; letter-spacing: 1px; }
            .person-name { font-size: 10pt; padding: 2mm; margin: 2mm 0 3mm; }
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

        <div class="person-name"><?= htmlspecialchars($person) ?></div>

        <div class="summary-row">
            <span class="label">کل رقم</span>
            <span class="value"><?= number_format($total_amount, 2) ?></span>
        </div>
        <div class="summary-row">
            <span class="label">وصولی</span>
            <span class="value green"><?= number_format($paid_amount, 2) ?></span>
        </div>
        <div class="summary-row total">
            <span class="label">باقی</span>
            <span class="value <?= $balance > 0 ? 'red' : 'green' ?>"><?= number_format($balance, 2) ?></span>
        </div>

        <div class="date-line"><?= date('d M Y h:i A') ?></div>

        <div class="no-print">
            <button class="btn-print" onclick="window.print()">پرنٹ</button>
            <button class="btn-back" onclick="window.close()">بند کریں</button>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
    </body></html>
    <?php exit;
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-eye mr-1"></i> <?= htmlspecialchars($person) ?></h1>
    <div>
        <button onclick="window.open('?person=<?= urlencode($person) ?>&print=1', '_blank', 'width=500,height=500')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> ٹھرمل پرنٹ
        </button>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> واپس
        </a>
    </div>
</div>

<div class="card shadow mb-4" style="max-width:450px; margin:0 auto;">
    <div class="card-header py-3 text-center">
        <h6 class="m-0 font-weight-bold text-primary">اصول کا خلاصہ</h6>
    </div>
    <div class="card-body">
        <div style="text-align:center;padding:10px;background:#f8f9fc;border-radius:6px;margin-bottom:20px;font-size:16px;font-weight:700;color:#2C3E50;">
            <?= htmlspecialchars($person) ?>
        </div>

        <table class="table table-bordered mb-0" style="direction:rtl;text-align:right;">
            <tbody>
                <tr>
                    <td style="font-weight:600;">کل رقم</td>
                    <td class="text-left font-weight-bold" style="font-size:16px;"><?= number_format($total_amount, 2) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#28a745;">وصولی</td>
                    <td class="text-left font-weight-bold" style="font-size:16px;color:#28a745;"><?= number_format($paid_amount, 2) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fc;">
                    <td style="font-weight:700;font-size:15px;">باقی</td>
                    <td class="text-left font-weight-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>" style="font-size:18px;"><?= number_format($balance, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<style>
@media print {
    @page { size: 80mm auto; margin: 2mm; }
    body { background: #fff !important; padding: 0 !important; margin: 0 !important; width: 80mm; }
    html { width: 80mm; }
    #wrapper, #wrapper > * { display: none !important; }
    .card { box-shadow: none !important; border: none !important; max-width: 76mm !important; width: 76mm !important; margin: 0 !important; padding: 2mm !important; }
    .card-header { background: #2C3E50 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 3mm !important; }
    .card-header h6 { color: #fff !important; font-size: 11px !important; }
    .card-body { padding: 2mm !important; }
    .card-body table { font-size: 11px !important; }
    .card-body table td { padding: 3mm 2mm !important; }
    .d-sm-flex { display: none !important; }
}
</style>
