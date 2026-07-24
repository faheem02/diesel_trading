<?php
session_start();
$active_page = 'supplier_ledger';
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/ledger.php';

$supplier_id = intval($_GET['id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

$sup = null;
if ($supplier_id > 0) {
    $sup = $conn->query("SELECT id, company_name, balance, opening_balance FROM suppliers WHERE id = $supplier_id")->fetch_assoc();
}

$all_suppliers = $conn->query("SELECT id, company_name, balance FROM suppliers ORDER BY company_name ASC");


$where = "sl.supplier_id = $supplier_id";
$params = [];
$types = "";

if (!empty($from_date)) {
    $where .= " AND sl.transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $where .= " AND sl.transaction_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql = "SELECT sl.*, s.company_name, p.diesel_quantity AS quantity, p.rate_per_ton FROM supplier_ledger sl JOIN suppliers s ON sl.supplier_id = s.id LEFT JOIN purchases p ON sl.reference_id = p.id AND sl.reference_type = 'purchase' WHERE $where ORDER BY sl.transaction_date ASC, sl.id ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$entries = $stmt->get_result();

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Supplier Ledger - <?= htmlspecialchars($sup['company_name'] ?? '') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
        .print-wrapper { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
        .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
        .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
        .print-header .brand .contact { font-size: 13px; color: #555; margin-top: 5px; }
        .print-header .brand .contact i { color: #F39C12; font-style: normal; }
        h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin-bottom: 5px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table thead th.text-right { text-align: right; }
        table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        table tbody td.text-right { text-align: right; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 15mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style></head><body>
    <div class="print-wrapper">
        <div class="print-header">
            <div class="logo"><img src="<?= $logo ?>" alt="Logo"></div>
            <div class="brand">
                <div class="company">Muhammad Younas</div>
                <div class="sub">Diesel Management System</div>
                <div class="contact"><i>&#9742;</i> +93 70 260 7159</div>
            </div>
        </div>
        <h2>Supplier Ledger</h2>
        <div class="subtitle">
            <strong><?= htmlspecialchars($sup['company_name'] ?? '') ?></strong> &nbsp;|&nbsp;
            Period: <?= htmlspecialchars($from_date ?: date('Y-m-01')) ?> to <?= htmlspecialchars($to_date ?: date('Y-m-d')) ?> &nbsp;|&nbsp;
            Balance: <?= number_format($sup['balance'] ?? 0, 2) ?>
        </div>
        <table>
            <thead><tr><th>Date</th><th>Description</th><th>Ref</th><th class="text-right">Qty (Ton)</th><th class="text-right">Rate/Ton</th><th class="text-right">Debit ($)</th><th class="text-right">Credit ($)</th><th class="text-right">Balance ($)</th></tr></thead>
            <tbody>
                <?php if ($entries->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center" style="color:#999;padding:20px;">No entries found.</td></tr>
                <?php else: while ($e = $entries->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($e['transaction_date']) ?></td>
                    <td><?= htmlspecialchars($e['description']) ?></td>
                    <td><?= htmlspecialchars($e['reference_type']) ?></td>
                    <td class="text-right"><?= ($e['reference_type'] === 'purchase' && $e['quantity'] > 0) ? number_format($e['quantity'], 3) : '-' ?></td>
                    <td class="text-right"><?= ($e['reference_type'] === 'purchase' && $e['rate_per_ton'] > 0) ? number_format($e['rate_per_ton'], 2) : '-' ?></td>
                    <td class="text-right"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?></td>
                    <td class="text-right"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?></td>
                    <td class="text-right"><?= number_format($e['balance'], 2) ?></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Supplier Ledger</h1>
    <div>
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?id=<?= $supplier_id ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if (!$sup): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Supplier</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">Supplier</label>
                <select name="id" class="form-control" required>
                    <option value="">-- Select Supplier --</option>
                    <?php while ($s = $all_suppliers->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company_name']) ?> (Bal: <?= number_format($s['balance'], 2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> View Ledger</button>
        </form>
    </div>
</div>
<?php else: ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
    <i class="fas fa-truck mr-1"></i> <?= htmlspecialchars($sup['company_name']) ?>
    <span class="ml-3 badge badge-<?= $sup['balance'] >= 0 ? 'success' : 'danger' ?> font-weight-bold" style="font-size:0.9rem">
        Balance: <?= number_format($sup['balance'], 2) ?>
    </span>
</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline mb-3">
            <input type="hidden" name="id" value="<?= $supplier_id ?>">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($from_date ?: date('Y-m-01')) ?>">
            </div>
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($to_date ?: date('Y-m-d')) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter"></i> Filter</button>
            <a href="ledger.php?id=<?= $supplier_id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Ref</th>
                        <th class="text-right">Qty (Ton)</th>
                        <th class="text-right">Rate/Ton</th>
                        <th class="text-right">Debit ($)</th>
                        <th class="text-right">Credit ($)</th>
                        <th class="text-right">Balance ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php else:
                        while ($e = $entries->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['transaction_date']) ?></td>
                            <td><?= htmlspecialchars($e['description']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($e['reference_type']) ?></span></td>
                            <td class="text-right"><?= ($e['reference_type'] === 'purchase' && $e['quantity'] > 0) ? number_format($e['quantity'], 3) : '-' ?></td>
                            <td class="text-right"><?= ($e['reference_type'] === 'purchase' && $e['rate_per_ton'] > 0) ? number_format($e['rate_per_ton'], 2) : '-' ?></td>
                            <td class="text-right text-danger font-weight-bold"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?></td>
                            <td class="text-right text-success font-weight-bold"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?></td>
                            <td class="text-right font-weight-bold <?= $e['balance'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($e['balance'], 2) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#ledgerTable').DataTable({
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
