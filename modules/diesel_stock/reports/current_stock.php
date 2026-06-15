<?php
session_start();
$active_page = 'stock_report_current';
require_once '../../../config/db.php';

$result = $conn->query("SELECT * FROM tanks ORDER BY tank_name ASC");

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-pie mr-1"></i> Current Stock Report</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="row">
    <?php
    $total_capacity = 0;
    $total_stock = 0;
    while ($row = $result->fetch_assoc()):
        $total_capacity += $row['capacity'];
        $total_stock += $row['current_stock'];
        $pct = $row['capacity'] > 0 ? round(($row['current_stock'] / $row['capacity']) * 100, 1) : 0;
        $bar_class = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
    ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?= htmlspecialchars($row['tank_name']) ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($row['current_stock'], 3) ?> / <?= number_format($row['capacity'], 3) ?> Tons
                        </div>
                        <div class="progress mt-2" style="height:12px">
                            <div class="progress-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $pct ?>% full</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-oil-can fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stock Summary</h6>
    </div>
    <div class="card-body">
        <?php $result->data_seek(0); ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="currentStockTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Tank Name</th>
                        <th>Location</th>
                        <th>Capacity (Tons)</th>
                        <th>Current Stock (Tons)</th>
                        <th>Available Space (Tons)</th>
                        <th>Utilization %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No tanks found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $pct = $row['capacity'] > 0 ? round(($row['current_stock'] / $row['capacity']) * 100, 1) : 0;
                            $available = $row['capacity'] - $row['current_stock'];
                            $bar_class = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
                            <td><?= htmlspecialchars($row['location'] ?: '-') ?></td>
                            <td><?= number_format($row['capacity'], 3) ?></td>
                            <td class="font-weight-bold text-success"><?= number_format($row['current_stock'], 3) ?></td>
                            <td><?= number_format($available, 3) ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 mr-2" style="height:10px;min-width:80px">
                                        <div class="progress-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <small><?= $pct ?>%</small>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="3" class="text-right">Totals:</th>
                        <th><?= number_format($total_capacity, 3) ?></th>
                        <th><?= number_format($total_stock, 3) ?></th>
                        <th><?= number_format($total_capacity - $total_stock, 3) ?></th>
                        <th><?= $total_capacity > 0 ? round(($total_stock / $total_capacity) * 100, 1) : 0 ?>%</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#currentStockTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
