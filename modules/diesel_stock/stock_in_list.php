<?php
session_start();
$active_page = 'stock_in_list';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$tank_id   = intval($_GET['tank_id'] ?? 0);

$sql = "SELECT sl.*, t.tank_name FROM stock_ledger sl JOIN tanks t ON sl.tank_id = t.id WHERE sl.movement_type = 'IN'";
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

$sql .= " ORDER BY sl.transaction_date DESC, sl.id DESC";

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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Stock In History</h1>
    <div>
        <a href="stock_in.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus-circle"></i> New Stock In
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
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-1"><i class="fas fa-search fa-sm mr-1"></i> Filter</button>
                            <a href="stock_in_list.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stock In Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="stockInTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Tank</th>
                        <th>Quantity (Tons)</th>
                        <th>Balance Before</th>
                        <th>Balance After</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No stock in records found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
                            <td class="font-weight-bold text-success"><?= number_format($row['quantity'], 3) ?></td>
                            <td><?= number_format($row['balance_before'], 3) ?></td>
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
    $('#stockInTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
