<?php
session_start();
$active_page = 'manual_entry';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$person = trim($_GET['person'] ?? '');
if (empty($person)) { header("Location: list.php"); exit; }

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

$sql = "SELECT * FROM manual_entries WHERE person_name = ? ORDER BY entry_date ASC, id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $person);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

$total_amount_sum = 0;
$paid_amount_sum  = 0;
foreach ($rows as $r) {
    $total_amount_sum += $r['total_amount'];
    $paid_amount_sum  += $r['paid_amount'];
}
$balance_sum = $total_amount_sum - $paid_amount_sum;

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="ur" dir="rtl"><head>
    <meta charset="UTF-8"><title><?= htmlspecialchars($person) ?> — Ledger</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; direction: rtl; }
        .print-wrapper { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
        .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
        .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; letter-spacing: 2px; margin-top: 2px; }
        .print-header .brand .contact { font-size: 13px; color: #555; margin-top: 5px; }
        h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin-bottom: 5px; text-align: center; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 15px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; direction: rtl; }
        table thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-align: center; }
        table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; text-align: center; }
        table tbody tr.total-row { background: #f8f9fc; font-weight: 700; border-top: 2px solid #2C3E50; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 10mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 15px 20px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } table tbody tr.total-row { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style></head><body>
    <div class="print-wrapper">
        <h2><?= htmlspecialchars($person) ?> — Ledger</h2>
        <div class="subtitle">کل اندراجات: <?= count($rows) ?></div>

        <table>
            <thead><tr>
                <th>SR نمبر</th><th>تاریخ</th><th>تفصیل</th>
                <th>تعداد</th><th>فی دانہ</th>
                <th>کل رقم</th><th>وصولی</th>
                <th>باقی</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" style="color:#999;padding:20px;">کوئی اندراج نہیں ملا۔</td></tr>
                <?php else:
                    $running_balance = 0;
                    foreach ($rows as $row):
                        $balance = $row['total_amount'] - $row['paid_amount'];
                        $running_balance += $balance;
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['sr_no']) ?></td>
                    <td><?= htmlspecialchars($row['entry_date']) ?></td>
                    <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                    <td><?= number_format($row['quantity'], 3) ?></td>
                    <td><?= number_format($row['rate_per_ton'], 2) ?></td>
                    <td><?= number_format($row['total_amount'], 2) ?></td>
                    <td style="color:#28a745"><?= number_format($row['paid_amount'], 2) ?></td>
                    <td style="font-weight:bold;color:<?= $balance > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance, 2) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <?php if (!empty($rows)): ?>
                <tr class="total-row">
                    <td colspan="3">کل</td>
                    <td><?= number_format(array_sum(array_column($rows, 'quantity')), 3) ?></td>
                    <td></td>
                    <td><?= number_format($total_amount_sum, 2) ?></td>
                    <td><?= number_format($paid_amount_sum, 2) ?></td>
                    <td style="color:<?= $balance_sum > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance_sum, 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="no-print" style="text-align:center;margin-top:20px;">
            <button class="btn-print" onclick="window.print()">پرنٹ / PDF محفوظ کریں</button>
            <a href="list.php" class="btn-back">واپس</a>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
    </body></html>
    <?php exit;
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> <?= htmlspecialchars($person) ?> — Ledger</h1>
    <div>
        <button onclick="printLedger()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> پرنٹ
        </button>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> واپس
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">کل رقم</div>
                    <div class="h5 mb-0 font-weight-bold">$ <?= number_format($total_amount_sum, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">وصولی</div>
                    <div class="h5 mb-0 font-weight-bold text-success">$ <?= number_format($paid_amount_sum, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">باقی</div>
                    <div class="h5 mb-0 font-weight-bold text-danger">$ <?= number_format($balance_sum, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"> Ledger — <?= htmlspecialchars($person) ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0" style="direction:rtl; text-align:right;">
                <thead class="thead-dark">
                    <tr>
                        <th style="text-align:center;">SR نمبر</th>
                        <th>تاریخ</th>
                        <th>تفصیل</th>
                        <th class="text-right">تعداد</th>
                        <th class="text-right">فی دانہ</th>
                        <th class="text-right">کل رقم</th>
                        <th class="text-right">وصولی</th>
                        <th class="text-right">باقی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">کوئی اندراج نہیں ملا۔</td></tr>
                    <?php else:
                        $running_balance = 0;
                        foreach ($rows as $row):
                            $balance = $row['total_amount'] - $row['paid_amount'];
                            $running_balance += $balance;
                    ?>
                        <tr>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['sr_no']) ?></td>
                            <td><?= htmlspecialchars($row['entry_date']) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                            <td class="text-right font-weight-bold"><?= number_format($row['quantity'], 3) ?></td>
                            <td class="text-right"><?= number_format($row['rate_per_ton'], 2) ?></td>
                            <td class="text-right"><?= number_format($row['total_amount'], 2) ?></td>
                            <td class="text-right font-weight-bold" style="color:#28a745"><?= number_format($row['paid_amount'], 2) ?></td>
                            <td class="text-right font-weight-bold" style="color:<?= $balance > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance, 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot>
                    <tr class="font-weight-bold" style="background:#f8f9fc;">
                        <td colspan="3">کل</td>
                        <td class="text-right"><?= number_format(array_sum(array_column($rows, 'quantity')), 3) ?></td>
                        <td class="text-right"></td>
                        <td class="text-right"><?= number_format($total_amount_sum, 2) ?></td>
                        <td class="text-right" style="color:#28a745"><?= number_format($paid_amount_sum, 2) ?></td>
                        <td class="text-right" style="color:<?= $balance_sum > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance_sum, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function printLedger() {
    window.open('ledger.php?person=<?= urlencode($person) ?>&print=1', '_blank', 'width=1100,height=700');
}
</script>
