<?php
session_start();
$active_page = 'stock_report_daily';
require_once '../../../config/db.php';

$report_date = $_GET['report_date'] ?? date('Y-m-d');
$tank_id     = intval($_GET['tank_id'] ?? 0);

$tanks = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name ASC");

$sql = "SELECT sl.*, t.tank_name FROM stock_ledger sl JOIN tanks t ON sl.tank_id = t.id WHERE sl.transaction_date = ?";
$params = [$report_date];
$types = "s";

if ($tank_id > 0) {
    $sql .= " AND sl.tank_id = ?";
    $params[] = $tank_id;
    $types .= "i";
}

$sql .= " ORDER BY sl.tank_id ASC, sl.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$day_summary = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN movement_type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out,
        COALESCE(SUM(CASE WHEN movement_type = 'ADJUSTMENT' THEN quantity ELSE 0 END), 0) AS total_adj
    FROM stock_ledger WHERE transaction_date = '$report_date'
")->fetch_assoc();

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-day mr-1"></i> Daily Stock Movement</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Date</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row w-100">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date</label>
                        <input type="date" name="report_date" class="form-control" value="<?= htmlspecialchars($report_date) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank</label>
                        <select name="tank_id" class="form-control">
                            <option value="">All Tanks</option>
                            <?php while ($t = $tanks->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>" <?= $tank_id === $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['tank_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-search fa-sm mr-1"></i> View</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-xl-4 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Stock In</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($day_summary['total_in'], 3) ?> Tons</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-down fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Stock Out</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($day_summary['total_out'], 3) ?> Tons</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-up fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Adjustments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($day_summary['total_adj'], 3) ?> Tons</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-sliders-h fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Movements on <?= htmlspecialchars($report_date) ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="dailyTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Tank</th>
                        <th>Type</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance After</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No movements on this date.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $qty_in = $row['movement_type'] === 'IN' ? $row['quantity'] : 0;
                            $qty_out = $row['movement_type'] !== 'IN' ? $row['quantity'] : 0;
                            $type_badge = $row['movement_type'] === 'IN' ? 'success' : ($row['movement_type'] === 'OUT' ? 'danger' : 'warning');
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
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
    $('#dailyTable').DataTable({
        pageLength: 50,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
