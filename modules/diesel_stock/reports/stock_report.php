<?php
session_start();
$active_page = 'stock_report';
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$tank_id   = intval($_GET['tank_id'] ?? 0);
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

$tanks_res = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name ASC");
$tanks_list = [];
while($t = $tanks_res->fetch_assoc()) $tanks_list[] = $t;

$selected_tank_name = '';
if ($tank_id > 0) {
    foreach ($tanks_list as $tl) {
        if ($tl['id'] == $tank_id) { $selected_tank_name = $tl['tank_name']; break; }
    }
}

$rows = [];
$stock_qty_total = 0;
$stock_value = 0;
$avg_rate = 0;

if ($tank_id > 0) {

$sql = "SELECT sl.*, t.tank_name FROM stock_ledger sl JOIN tanks t ON sl.tank_id = t.id WHERE sl.tank_id = ?";
$params = [$tank_id];
$types = "i";
if (!empty($from_date)) {
    $sql .= " AND sl.transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND sl.transaction_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY sl.transaction_date ASC, sl.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

} // end tank_id > 0

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Stock Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
        .print-wrapper { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
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
        table tbody tr.total-row { background: #f8f9fc; font-weight: 700; border-top: 2px solid #2C3E50; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 10mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 15px 20px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } table tbody tr.total-row { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
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
        <h2>Stock Report<?= $selected_tank_name ? ' — ' . htmlspecialchars($selected_tank_name) : '' ?></h2>
        <div class="subtitle">
            Period: <?= htmlspecialchars($from_date ?: 'All') ?> to <?= htmlspecialchars($to_date ?: 'All') ?>
            &nbsp;|&nbsp; Total Entries: <?= count($rows) ?>
        </div>

        <table>
            <thead><tr>
                <th>#</th><th>Date</th><th>Source</th><th class="text-right">Qty (Tons)</th>
                <th class="text-right">Rate ($/ton)</th><th class="text-right">Amount ($)</th>
                <th class="text-right">Stock Qty Total</th><th class="text-right">Stock Value ($)</th>
                <th class="text-right">Avg Rate ($/ton)</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-center" style="color:#999;padding:20px;">No stock entries found.</td></tr>
                <?php else:
                    $i = 1;
                    foreach ($rows as $row):
                        if ($row['movement_type'] === 'IN') {
                            $stock_qty_total += $row['quantity'];
                            $stock_value += $row['amount'];
                        } elseif ($row['movement_type'] === 'OUT') {
                            $stock_qty_total -= $row['quantity'];
                            $stock_value -= $row['amount'];
                        } else {
                            $stock_qty_total = $row['balance_after'];
                        }
                        $avg_rate = $stock_qty_total > 0 ? $stock_value / $stock_qty_total : 0;
                ?>
                <?php
                    $source = $row['reference_type'] ?? $row['movement_type'];
                    $source_labels = ['purchase' => 'Purchase', 'sale' => 'Sale', 'opening_balance' => 'Opening Balance', 'adjustment' => 'Adjustment'];
                    $source_label = $source_labels[$source] ?? ucfirst($source);
                    $qty_display = $row['movement_type'] === 'OUT' ? -$row['quantity'] : $row['quantity'];
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                    <td><?= $source_label ?></td>
                    <td class="text-right" style="color:<?= $qty_display < 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($qty_display, 3) ?></td>
                    <td class="text-right"><?= number_format($row['rate'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['amount'], 2) ?></td>
                    <td class="text-right"><?= number_format($stock_qty_total, 3) ?></td>
                    <td class="text-right"><?= number_format($stock_value, 2) ?></td>
                    <td class="text-right"><?= number_format($avg_rate, 2) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <?php if (!empty($rows)): ?>
                <tr class="total-row">
                    <td colspan="3">TOTAL</td>
                    <td class="text-right"><?= number_format($stock_qty_total, 3) ?></td>
                    <td class="text-right"></td>
                    <td class="text-right"><?= number_format($stock_value, 2) ?></td>
                    <td class="text-right"><?= number_format($stock_qty_total, 3) ?></td>
                    <td class="text-right"><?= number_format($stock_value, 2) ?></td>
                    <td class="text-right"><?= number_format($avg_rate, 2) ?></td>
                </tr>
                <?php endif; ?>
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

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line mr-1"></i> Stock Report<?= $selected_tank_name ? ' — ' . htmlspecialchars($selected_tank_name) : '' ?></h1>
    <div>
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?tank_id=<?= $tank_id ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=1200,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row w-100">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank <span class="text-danger">*</span></label>
                        <select name="tank_id" class="form-control" required id="tankSelect">
                            <option value="">-- Select Tank --</option>
                            <?php foreach ($tanks_list as $tl): ?>
                                <option value="<?= $tl['id'] ?>" <?= $tank_id == $tl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tl['tank_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-1"><i class="fas fa-search fa-sm mr-1"></i> Filter</button>
                            <a href="stock_report.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($tank_id > 0): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stock Entries<?= $selected_tank_name ? ' — ' . htmlspecialchars($selected_tank_name) : '' ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="stockReportTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Source</th>
                        <th class="text-right">Qty (Tons)</th>
                        <th class="text-right">Rate ($/ton)</th>
                        <th class="text-right">Amount ($)</th>
                        <th class="text-right">Stock Qty Total</th>
                        <th class="text-right">Stock Value ($)</th>
                        <th class="text-right">Avg Rate ($/ton)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No stock entries found.</td></tr>
                    <?php else:
                        $stock_qty_total = 0;
                        $stock_value = 0;
                        $i = 1;
                        foreach ($rows as $row):
                            if ($row['movement_type'] === 'IN') {
                                $stock_qty_total += $row['quantity'];
                                $stock_value += $row['amount'];
                            } elseif ($row['movement_type'] === 'OUT') {
                                $stock_qty_total -= $row['quantity'];
                                $stock_value -= $row['amount'];
                            } else {
                                $stock_qty_total = $row['balance_after'];
                            }
                            $avg_rate = $stock_qty_total > 0 ? $stock_value / $stock_qty_total : 0;
                    ?>
                    <?php
                        $source = $row['reference_type'] ?? $row['movement_type'];
                        $source_labels = ['purchase' => 'Purchase', 'sale' => 'Sale', 'opening_balance' => 'Opening Balance', 'adjustment' => 'Adjustment'];
                        $source_label = $source_labels[$source] ?? ucfirst($source);
                        $qty_display = $row['movement_type'] === 'OUT' ? -$row['quantity'] : $row['quantity'];
                        $source_badge = $row['movement_type'] === 'IN' ? 'success' : ($row['movement_type'] === 'OUT' ? 'danger' : 'warning');
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                        <td><span class="badge badge-<?= $source_badge ?>"><?= $source_label ?></span></td>
                        <td class="text-right font-weight-bold" style="color:<?= $qty_display < 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($qty_display, 3) ?></td>
                        <td class="text-right"><?= number_format($row['rate'], 2) ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($row['amount'], 2) ?></td>
                        <td class="text-right font-weight-bold text-primary"><?= number_format($stock_qty_total, 3) ?></td>
                        <td class="text-right font-weight-bold text-primary"><?= number_format($stock_value, 2) ?></td>
                        <td class="text-right font-weight-bold text-success"><?= number_format($avg_rate, 2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot>
                    <tr class="font-weight-bold" style="background:#f8f9fc;">
                        <td colspan="3">TOTAL</td>
                        <td class="text-right"><?= number_format($stock_qty_total, 3) ?></td>
                        <td class="text-right"></td>
                        <td class="text-right"><?= number_format($stock_value, 2) ?></td>
                        <td class="text-right text-primary"><?= number_format($stock_qty_total, 3) ?></td>
                        <td class="text-right text-primary"><?= number_format($stock_value, 2) ?></td>
                        <td class="text-right text-success"><?= number_format($avg_rate, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card shadow mb-4">
    <div class="card-body text-center py-5">
        <i class="fas fa-oil-can fa-3x text-gray-300 mb-3"></i>
        <h5 class="text-gray-500">Please select a tank to view stock report</h5>
        <p class="text-muted">Use the filter above to select a tank</p>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#stockReportTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        searching: false,
        language: { lengthMenu: "Show _MENU_ entries" }
    });

    $('#tankSelect').on('change', function() {
        this.form.submit();
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>