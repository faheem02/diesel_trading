<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { exit('Invalid entry.'); }

$row = $conn->query("SELECT * FROM manual_entries WHERE id = $id")->fetch_assoc();
if (!$row) { exit('Entry not found.'); }

$balance = $row['total_amount'] - $row['paid_amount'];
$logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manual Entry - <?= htmlspecialchars($row['sr_no']) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
    .print-wrapper { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 25px; }
    .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
    .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
    .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
    .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
    .print-header .brand .contact { font-size: 13px; color: #555; margin-top: 5px; }
    h2 { font-size: 20px; color: #2C3E50; font-weight: 700; margin-bottom: 20px; }
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 30px; margin-bottom: 25px; }
    .detail-item .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #888; font-weight: 600; margin-bottom: 3px; }
    .detail-item .value { font-size: 15px; font-weight: 600; color: #2C3E50; }
    .detail-item .value.green { color: #28a745; }
    .detail-item .value.red { color: #dc3545; }
    .detail-item.full { grid-column: 1 / -1; }
    .divider { border-top: 2px solid #eee; margin: 20px 0; }
    .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 10px; }
    .btn-print:hover { background: #1A252F; }
    .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
    @page { margin: 10mm; }
    @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 15px 20px; } .no-print { display: none; } .print-header { border-bottom-color: #2C3E50 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
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

    <h2>Manual Entry Receipt</h2>

    <div class="detail-grid">
        <div class="detail-item">
            <div class="label">SR No</div>
            <div class="value"><?= htmlspecialchars($row['sr_no']) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Date</div>
            <div class="value"><?= htmlspecialchars($row['entry_date']) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Name</div>
            <div class="value"><?= htmlspecialchars($row['person_name']) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Rate ($/Ton)</div>
            <div class="value"><?= number_format($row['rate_per_ton'], 2) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Quantity (Ton)</div>
            <div class="value"><?= number_format($row['quantity'], 3) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Total Amount ($)</div>
            <div class="value"><?= number_format($row['total_amount'], 2) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Paid Amount ($)</div>
            <div class="value green"><?= number_format($row['paid_amount'], 2) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Balance ($)</div>
            <div class="value <?= $balance > 0 ? 'red' : 'green' ?>"><?= number_format($balance, 2) ?></div>
        </div>
        <?php if (!empty($row['description'])): ?>
        <div class="detail-item full">
            <div class="label">Description</div>
            <div class="value"><?= htmlspecialchars($row['description']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="no-print" style="text-align:center;margin-top:10px;">
        <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <button class="btn-back" onclick="window.close()">Close</button>
    </div>
</div>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
</body>
</html>
