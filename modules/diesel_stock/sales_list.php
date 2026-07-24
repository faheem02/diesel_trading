<?php
session_start();
require_once '../../includes/config.php';
header("Location: {$base_url}modules/sales/list.php");
exit;
$params = [];
$types = "";

if (!empty($from_date)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}
if ($tank_id > 0) {
    $sql .= " AND s.tank_id = ?";
    $params[] = $tank_id;
    $types .= "i";
}

$sql .= " ORDER BY s.sale_date DESC, s.id DESC";

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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Sales History</h1>
    <div>
        <a href="sales.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus-circle"></i> New Sale
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
                <input type="hidden" name="tank_id" value="<?= $tank_id ?>">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-1"><i class="fas fa-search fa-sm mr-1"></i> Filter</button>
                            <a href="sales_list.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Sales Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="salesTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Vehicle</th>
                        <th>Qty (Tons)</th>
                        <th>Rate/Ton</th>
                        <th>Total ($)</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No sales records found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                            <td><?= htmlspecialchars($row['sale_date']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= htmlspecialchars($row['customer_mobile'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['vehicle_number'] ?: '-') ?></td>
                            <td class="font-weight-bold"><?= number_format($row['quantity'], 3) ?></td>
                            <td><?= number_format($row['rate_per_ton'], 2) ?></td>
                            <td class="font-weight-bold"><?= number_format($row['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge badge-<?= $row['payment_type'] === 'Cash' ? 'success' : 'warning' ?>">
                                    <?= $row['payment_type'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#salesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
