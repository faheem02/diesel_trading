<?php
session_start();
$active_page = 'general_report';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: parties.php"); exit; }

$party = $conn->query("SELECT * FROM personal_accounts WHERE id = $id")->fetch_assoc();
if (!$party) { header("Location: parties.php"); exit; }

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

$stmt = $conn->prepare("SELECT COUNT(*) AS total_entries, COALESCE(SUM(CASE WHEN reference_type = 'payable' THEN debit ELSE 0 END), 0) AS total_paid, COALESCE(SUM(CASE WHEN reference_type = 'receivable' THEN credit ELSE 0 END), 0) AS total_received FROM personal_ledger WHERE account_id = ? $date_filter");
$bind_types = "i" . $date_types;
$bind_params = array_merge([$id], $date_params);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_entries = $summary['total_entries'];
$total_paid    = $summary['total_paid'];
$total_received = $summary['total_received'];

$total_opening = $conn->query("SELECT COALESCE(SUM(credit), 0) AS total FROM personal_ledger WHERE account_id = $id AND reference_type = 'opening_balance'")->fetch_assoc()['total'];

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Summary - <?= htmlspecialchars($party['person_name']) ?></title>
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
        <h2>Party Summary Report</h2>
        <div class="subtitle">Generated: <?= date('d M Y h:i A') ?>
            <?php if ($from_date || $to_date): ?>
                &nbsp;|&nbsp; Period: <?= htmlspecialchars($from_date ?: 'Start') ?> to <?= htmlspecialchars($to_date ?: 'Now') ?>
            <?php endif; ?>
        </div>

        <div class="party-line">
            <span class="label">Party:</span> <?= htmlspecialchars($party['person_name']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Mobile:</span> <?= htmlspecialchars($party['mobile'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <span class="label">Address:</span> <?= htmlspecialchars($party['address'] ?: '-') ?>
        </div>

        <table class="summary-table">
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
                    <td>Cash In</td>
                    <td class="text-right text-success"><?= number_format($total_received, 2) ?></td>
                </tr>
                <tr>
                    <td>Cash Out</td>
                    <td class="text-right text-danger"><?= number_format($total_paid, 2) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align:right;font-weight:700;">Current Balance</td>
                    <td class="text-right" style="font-size:16px;"><?= number_format($party['balance'], 2) ?></td>
                </tr>
            </tfoot>
        </table>

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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar mr-1"></i> Summary — <?= htmlspecialchars($party['person_name']) ?></h1>
    <div>
        <button onclick="window.open('?id=<?= $id ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=1000,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="party_ledger.php?id=<?= $id ?>" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm mr-1">
            <i class="fas fa-book"></i> Ledger
        </a>
        <a href="parties.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
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
            <a href="party_summary.php?id=<?= $id ?>" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Party line + Summary -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div style="margin-bottom:15px;padding:8px 12px;background:#f8f9fc;border-radius:6px;border-left:4px solid #2C3E50;font-size:14px;">
            <strong>Party:</strong> <?= htmlspecialchars($party['person_name']) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Mobile:</strong> <?= htmlspecialchars($party['mobile'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Address:</strong> <?= htmlspecialchars($party['address'] ?: '-') ?>
        </div>
        <table class="table table-bordered mb-0">
            <thead class="thead-dark">
                <tr><th>Description</th><th class="text-right">Amount ($)</th></tr>
            </thead>
            <tbody>
                <tr><td>Opening Balance</td><td class="text-right font-weight-bold"><?= number_format($total_opening, 2) ?></td></tr>
                <tr><td>Cash In</td><td class="text-right text-success font-weight-bold"><?= number_format($total_received, 2) ?></td></tr>
                <tr><td>Cash Out</td><td class="text-right text-danger font-weight-bold"><?= number_format($total_paid, 2) ?></td></tr>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fc;">
                    <td class="text-right font-weight-bold">Current Balance</td>
                    <td class="text-right font-weight-bold <?= $party['balance'] > 0 ? 'text-success' : ($party['balance'] < 0 ? 'text-danger' : '') ?>" style="font-size:16px;">$ <?= number_format($party['balance'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
