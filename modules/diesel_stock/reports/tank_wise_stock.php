<?php
session_start();
$active_page = 'stock_report_tank_wise';
require_once '../../../includes/db.php';

$tank_id = intval($_GET['tank_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$tanks = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name ASC");

$sql = "SELECT sl.*, t.tank_name FROM stock_ledger sl JOIN tanks t ON sl.tank_id = t.id WHERE 1=1";
$params = [];
$types = "";

if ($tank_id > 0) {
    $sql .= " AND sl.tank_id = ?";
    $params[] = $tank_id;
    $types .= "i";
}
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

$sql .= " ORDER BY sl.transaction_date DESC, sl.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$summary = $conn->query("
    SELECT t.id, t.tank_name, t.capacity, t.opening_stock, t.current_stock,
        COALESCE(SUM(CASE WHEN sl.movement_type = 'IN'  AND sl.reference_type != 'opening_balance' THEN sl.quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sl.movement_type = 'OUT' THEN sl.quantity ELSE 0 END), 0) AS total_out,
        COALESCE(SUM(CASE WHEN sl.movement_type = 'ADJUSTMENT' THEN sl.quantity ELSE 0 END), 0) AS total_adj_removed,
        COALESCE(SUM(CASE WHEN sl.movement_type = 'ADJUSTMENT' AND sl.balance_after > sl.balance_before THEN sl.quantity ELSE 0 END), 0) AS total_adj_added
    FROM tanks t
    LEFT JOIN stock_ledger sl ON t.id = sl.tank_id
    GROUP BY t.id
    ORDER BY t.tank_name ASC
");

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Tank Wise Stock Report</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Tank Summary</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="summaryTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Tank Name</th>
                        <th>Location / Capacity</th>
                        <th>Opening Stock</th>
                        <th>Stock In (Purchases)</th>
                        <th>Stock Out (Sales)</th>
                        <th>Adjustments</th>
                        <th>Current Stock (Tons)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($summary->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No tanks found.</td></tr>
                    <?php else:
                        while ($row = $summary->fetch_assoc()):
                            $net_adj = $row['total_adj_added'] - $row['total_adj_removed'];
                        ?>
                        <tr>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
                            <td><?= number_format($row['capacity'], 3) ?> Tons</td>
                            <td class="text-info font-weight-bold"><?= number_format($row['opening_stock'], 3) ?></td>
                            <td class="text-success font-weight-bold">+ <?= number_format($row['total_in'], 3) ?></td>
                            <td class="text-danger font-weight-bold">− <?= number_format($row['total_out'], 3) ?></td>
                            <td class="<?= $net_adj >= 0 ? 'text-success' : 'text-warning' ?> font-weight-bold">
                                <?= $net_adj >= 0 ? '+' : '' ?><?= number_format($net_adj, 3) ?>
                            </td>
                            <td class="font-weight-bold text-primary"><?= number_format($row['current_stock'], 3) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Movements</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row w-100">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank</label>
                        <select name="tank_id" class="form-control">
                            <option value="">All Tanks</option>
                            <?php
                            $tanks->data_seek(0);
                            while ($t = $tanks->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>" <?= $tank_id === $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['tank_name']) ?>
                                </option>
                            <?php endwhile; ?>
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
                            <a href="tank_wise_stock.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Movement Details</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="movementTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Tank</th>
                        <th>Type</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No movements found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $qty_in = $row['movement_type'] === 'IN' ? $row['quantity'] : 0;
                            $qty_out = $row['movement_type'] !== 'IN' ? $row['quantity'] : 0;
                            $type_badge = $row['movement_type'] === 'IN' ? 'success' : ($row['movement_type'] === 'OUT' ? 'danger' : 'warning');
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                            <td><?= htmlspecialchars($row['tank_name']) ?></td>
                            <td><span class="badge badge-<?= $type_badge ?>"><?= $row['movement_type'] ?></span></td>
                            <td class="text-success font-weight-bold"><?= $qty_in > 0 ? number_format($qty_in, 3) : '-' ?></td>
                            <td class="text-danger font-weight-bold"><?= $qty_out > 0 ? number_format($qty_out, 3) : '-' ?></td>
                            <td class="font-weight-bold"><?= number_format($row['balance_after'], 3) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#summaryTable, #movementTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
