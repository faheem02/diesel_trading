<?php
session_start();
$active_page = 'adjustment_list';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$tank_id   = intval($_GET['tank_id'] ?? 0);
$adj_type  = $_GET['adj_type'] ?? '';

$sql = "SELECT sa.*, t.tank_name FROM stock_adjustments sa JOIN tanks t ON sa.tank_id = t.id WHERE 1=1";
$params = [];
$types = "";

if (!empty($from_date)) {
    $sql .= " AND sa.adjustment_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND sa.adjustment_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}
if ($tank_id > 0) {
    $sql .= " AND sa.tank_id = ?";
    $params[] = $tank_id;
    $types .= "i";
}
if (!empty($adj_type)) {
    $sql .= " AND sa.adjustment_type = ?";
    $params[] = $adj_type;
    $types .= "s";
}

$sql .= " ORDER BY sa.adjustment_date DESC, sa.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$tanks = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name ASC");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Adjustment History</h1>
    <div>
        <a href="adjustments.php" class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm">
            <i class="fas fa-plus-circle"></i> New Adjustment
        </a>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Records</h6>
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
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Type</label>
                        <select name="adj_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Shortage" <?= $adj_type === 'Shortage' ? 'selected' : '' ?>>Shortage</option>
                            <option value="Leakage" <?= $adj_type === 'Leakage' ? 'selected' : '' ?>>Leakage</option>
                            <option value="Measurement_Difference" <?= $adj_type === 'Measurement_Difference' ? 'selected' : '' ?>>Measurement Difference</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-1"><i class="fas fa-search fa-sm mr-1"></i> Filter</button>
                            <a href="adjustments_list.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Adjustment Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="adjustmentsTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Tank</th>
                        <th>Type</th>
                        <th>Quantity (Tons)</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No adjustment records found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['adjustment_date']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
                            <td>
                                <?php
                                    $badge = 'secondary';
                                    if ($row['adjustment_type'] === 'Shortage') $badge = 'danger';
                                    elseif ($row['adjustment_type'] === 'Leakage') $badge = 'warning';
                                    elseif ($row['adjustment_type'] === 'Measurement_Difference') $badge = 'info';
                                ?>
                                <span class="badge badge-<?= $badge ?>">
                                    <?= str_replace('_', ' ', $row['adjustment_type']) ?>
                                </span>
                            </td>
                            <td class="font-weight-bold text-danger"><?= number_format($row['quantity'], 3) ?></td>
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
    $('#adjustmentsTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
