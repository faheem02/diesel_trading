<?php
session_start();
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$purchase = $conn->query("SELECT p.*, s.company_name, s.contact_person, s.phone AS supplier_phone
    FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = $id")->fetch_assoc();
if (!$purchase) { header("Location: list.php"); exit; }

$tankers = $conn->query("SELECT pt.*, t.tank_name FROM purchase_tankers pt LEFT JOIN tanks t ON pt.tank_id = t.id WHERE pt.purchase_id = $id ORDER BY pt.id ASC");

$logo = $base_url ?? '';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/diesel_trading/modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg')) {
    $logo = '/diesel_trading/modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase #<?= htmlspecialchars($purchase['invoice_no']) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
    .print-wrapper { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
    .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
    .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
    .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
    .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 30px; margin-bottom: 20px; font-size: 13px; }
    .info-grid .label { font-weight: 700; color: #555; }
    .info-grid .value { color: #222; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
    thead th.text-right { text-align: right; }
    tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
    tbody td.text-right { text-align: right; }
    tfoot td { padding: 10px 12px; font-size: 14px; font-weight: 700; border-top: 2px solid #2C3E50; background: #f8f9fc; }
    tfoot td.text-right { text-align: right; }
    .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
    .btn-print:hover { background: #1A252F; }
    .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
    @page { margin: 15mm; }
    @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } thead th { background: #2C3E50 !important; color: #fff !important; } }
</style>
</head>
<body>
<div class="print-wrapper">
    <div class="print-header">
        <?php if ($logo): ?>
        <div class="logo"><img src="<?= $logo ?>" alt="Logo"></div>
        <?php endif; ?>
        <div class="brand">
            <div class="company">Muhammad Younas</div>
            <div class="sub">Diesel Management System</div>
        </div>
    </div>

    <h2 style="font-size:22px;color:#2C3E50;margin-bottom:10px;">Purchase Invoice</h2>

    <div class="info-grid">
        <div><span class="label">Invoice No:</span> <span class="value"><?= htmlspecialchars($purchase['invoice_no']) ?></span></div>
        <div><span class="label">Date:</span> <span class="value"><?= htmlspecialchars($purchase['purchase_date']) ?></span></div>
        <div><span class="label">Supplier:</span> <span class="value"><?= htmlspecialchars($purchase['company_name']) ?></span></div>
        <div><span class="label">Contact:</span> <span class="value"><?= htmlspecialchars($purchase['supplier_phone'] ?? '-') ?></span></div>
        <div><span class="label">Payment Status:</span> <span class="value"><?= htmlspecialchars($purchase['payment_status']) ?></span></div>
        <div><span class="label">Paid Amount:</span> <span class="value">$ <?= number_format($purchase['paid_amount'], 2) ?></span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tanker No</th>
                <th>Driver</th>
                <th>Mobile</th>
                <th class="text-right">Qty (Ton)</th>
                <th class="text-right">Rate/Ton</th>
                <th class="text-right">Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; $totalQty = 0; $grandTotal = 0; while ($t = $tankers->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($t['tanker_number']) ?></td>
                <td><?= htmlspecialchars($t['driver_name']) ?></td>
                <td><?= htmlspecialchars($t['driver_mobile']) ?></td>
                <td class="text-right"><?= number_format($t['diesel_quantity'], 3) ?></td>
                <td class="text-right"><?= number_format($t['rate_per_ton'], 2) ?></td>
                <td class="text-right"><?= number_format($t['total_amount'], 2) ?></td>
            </tr>
            <?php $totalQty += $t['diesel_quantity']; $grandTotal += $t['total_amount']; endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">Totals:</td>
                <td class="text-right"><?= number_format($totalQty, 3) ?></td>
                <td></td>
                <td class="text-right">$ <?= number_format($grandTotal, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="no-print" style="text-align:center;margin-top:20px;">
        <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <a href="list.php" class="btn-back">Back to List</a>
    </div>
</div>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
</body>
</html>
