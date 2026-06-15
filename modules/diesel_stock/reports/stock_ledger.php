<?php
session_start();
$active_page = 'stock_report_ledger';
require_once '../../../config/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$tank_id   = intval($_GET['tank_id'] ?? 0);
$movement_type = $_GET['movement_type'] ?? '';

$tanks = $conn->query("SELECT id, tank_name FROM tanks ORDER BY tank_name ASC");

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

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Stock Ledger</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
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
        <h6 class="m-0 font-weight-bold text-primary">Stock Ledger Entries</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th width="3%">#</th>
                        <th width="10%">Date</th>
                        <th width="12%">Tank</th>
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
                        <tr><td colspan="9" class="text-center text-muted py-4">No ledger entries found.</td></tr>
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
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
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
