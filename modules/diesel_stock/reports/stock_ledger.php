<?php
session_start();
$active_page = 'stock_report_ledger';
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');
$tank_id   = intval($_GET['tank_id'] ?? 0);
$movement_type = $_GET['movement_type'] ?? '';
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

$tanks = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name ASC");
$tanks_arr = [];
while ($row = $tanks->fetch_assoc()) $tanks_arr[] = $row;
$single_tank = (count($tanks_arr) === 1) ? $tanks_arr[0] : null;

// Auto-select single tank
if ($single_tank && $tank_id <= 0) {
    $tank_id = intval($single_tank['id']);
}

$sql = "SELECT sl.*, t.tank_name FROM stock_ledger sl JOIN tanks t ON sl.tank_id = t.id WHERE 1=1";
$params = [];
$types = "";

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
if ($tank_id > 0) {
    $sql .= " AND sl.tank_id = ?";
    $params[] = $tank_id;
    $types .= "i";
}
if (!empty($movement_type)) {
    $sql .= " AND sl.movement_type = ?";
    $params[] = $movement_type;
    $types .= "s";
}

$sql .= " ORDER BY sl.transaction_date DESC, sl.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    $selected_tank = '';
    if ($tank_id > 0) {
        $tq = $conn->query("SELECT tank_name FROM tanks WHERE id = $tank_id");
        if ($tr = $tq->fetch_assoc()) $selected_tank = $tr['tank_name'];
    }
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Stock Ledger</title>
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
        <h2>Stock Ledger</h2>
        <div class="subtitle">
            Period: <?= htmlspecialchars($from_date ?: 'All') ?> to <?= htmlspecialchars($to_date ?: 'All') ?>
            <?php if ($selected_tank): ?> &nbsp;|&nbsp; Tank: <?= htmlspecialchars($selected_tank) ?><?php endif; ?>
            <?php if ($movement_type): ?> &nbsp;|&nbsp; Type: <?= htmlspecialchars($movement_type) ?><?php endif; ?>
        </div>
        <table>
            <thead><tr><th>#</th><th>Date</th><th>Type</th><th class="text-right">In (Tons)</th><th class="text-right">Out (Tons)</th><th class="text-right">Balance Before</th><th class="text-right">Balance After</th><th>Description</th></tr></thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center" style="color:#999;padding:20px;">No entries found.</td></tr>
                <?php else: $i = 1; while ($row = $result->fetch_assoc()):
                    $qty_in  = $row['movement_type'] === 'IN' ? $row['quantity'] : 0;
                    $qty_out = $row['movement_type'] !== 'IN' ? $row['quantity'] : 0;
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                    <td><?= $row['movement_type'] ?></td>
                    <td class="text-right"><?= $qty_in > 0 ? number_format($qty_in, 3) : '-' ?></td>
                    <td class="text-right"><?= $qty_out > 0 ? number_format($qty_out, 3) : '-' ?></td>
                    <td class="text-right"><?= number_format($row['balance_before'], 3) ?></td>
                    <td class="text-right"><?= number_format($row['balance_after'], 3) ?></td>
                    <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
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

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Stock Ledger</h1>
    <div>
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&tank_id=<?= $tank_id ?>&movement_type=<?= urlencode($movement_type) ?>&print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Ledger</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row w-100">
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
                <input type="hidden" name="tank_id" value="<?= $single_tank ? $single_tank['id'] : ($tanks_arr[0]['id'] ?? '') ?>">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Movement Type</label>
                        <select name="movement_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="IN" <?= $movement_type === 'IN' ? 'selected' : '' ?>>Stock In</option>
                            <option value="OUT" <?= $movement_type === 'OUT' ? 'selected' : '' ?>>Stock Out</option>
                            <option value="ADJUSTMENT" <?= $movement_type === 'ADJUSTMENT' ? 'selected' : '' ?>>Adjustment</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="btn-group btn-group-sm shadow-sm" role="group">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-2"><i class="fas fa-search fa-sm mr-1"></i> Filter</button>
                            <a href="stock_ledger.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stock Ledger Entries</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th width="3%">#</th>
                        <th width="10%">Date</th>
                        <th width="8%">Type</th>
                        <th width="10%">In (Tons)</th>
                        <th width="10%">Out (Tons)</th>
                        <th width="12%">Balance Before</th>
                        <th width="12%">Balance After</th>
                        <th width="23%">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $qty_in  = $row['movement_type'] === 'IN' ? $row['quantity'] : 0;
                            $qty_out = $row['movement_type'] !== 'IN' ? $row['quantity'] : 0;
                            $type_badge = $row['movement_type'] === 'IN' ? 'success' : ($row['movement_type'] === 'OUT' ? 'danger' : 'warning');
                            $description = htmlspecialchars($row['description'] ?: '-');
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                            <td><span class="badge badge-<?= $type_badge ?>"><?= $row['movement_type'] ?></span></td>
                            <td class="text-success font-weight-bold"><?= $qty_in  > 0 ? number_format($qty_in,  3) : '-' ?></td>
                            <td class="text-danger font-weight-bold"><?= $qty_out > 0 ? number_format($qty_out, 3) : '-' ?></td>
                            <td><?= number_format($row['balance_before'], 3) ?></td>
                            <td class="font-weight-bold"><?= number_format($row['balance_after'], 3) ?></td>
                            <td style="max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                title="<?= $description ?>" data-toggle="tooltip">
                                <?= $description ?>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enable Bootstrap tooltips -->
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<script>
$(document).ready(function() {
    $('#ledgerTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
