<?php
session_start();
$active_page = 'rpt_stock_summary';
require_once '../../config/db.php';

$tanks = $conn->query("SELECT * FROM tanks ORDER BY tank_name ASC");
$total_capacity = 0;
$total_stock = 0;
while ($t = $tanks->fetch_assoc()) {
    $total_capacity += $t['capacity'];
    $total_stock += $t['current_stock'];
    $rows[] = $t;
}
$tanks->data_seek(0);

// Total purchase qty and sales qty
$purchase_qty = $conn->query("SELECT COALESCE(SUM(diesel_quantity),0) AS q FROM purchases")->fetch_assoc()['q'];
$sales_qty = $conn->query("SELECT COALESCE(SUM(quantity),0) AS q FROM customer_sales")->fetch_assoc()['q'];

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-pie mr-1"></i> Stock Summary</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm"><i class="fas fa-print fa-sm"></i> Print</button>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Purchased</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($purchase_qty, 3) ?> Tons</div>
                </div>
                <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Sold</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($sales_qty, 3) ?> Tons</div>
                </div>
                <div class="col-auto"><i class="fas fa-arrow-up fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Current Stock</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_stock, 3) ?> Tons</div>
                </div>
                <div class="col-auto"><i class="fas fa-oil-can fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Capacity</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_capacity, 3) ?> Tons</div>
                </div>
                <div class="col-auto"><i class="fas fa-tachometer-alt fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Tank Wise Stock</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="rptTable">
                <thead class="thead-dark"><tr><th>#</th><th>Tank Name</th><th>Location</th><th class="text-right">Capacity</th><th class="text-right">Current Stock</th><th class="text-right">% Full</th><th class="text-right">Available</th></tr></thead>
                <tbody>
                    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">No tanks found.</td></tr>
                    <?php else: $i=1; foreach ($rows as $r): $pct = $r['capacity'] > 0 ? ($r['current_stock'] / $r['capacity']) * 100 : 0; ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="font-weight-bold"><?= htmlspecialchars($r['tank_name']) ?></td>
                        <td><?= htmlspecialchars($r['location'] ?: '-') ?></td>
                        <td class="text-right"><?= number_format($r['capacity'], 3) ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($r['current_stock'], 3) ?></td>
                        <td class="text-right">
                            <div class="progress" style="height:20px;">
                                <div class="progress-bar <?= $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= min($pct, 100) ?>%;"><?= number_format($pct, 1) ?>%</div>
                            </div>
                        </td>
                        <td class="text-right text-muted"><?= number_format(max($r['capacity'] - $r['current_stock'], 0), 3) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="table-active"><tr><th colspan="3" class="text-right">Total:</th><th class="text-right"><?= number_format($total_capacity, 3) ?></th><th class="text-right"><?= number_format($total_stock, 3) ?></th><th colspan="2"></th></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<script>$(document).ready(function(){$('#rptTable').DataTable({pageLength:25,ordering:false,language:{search:"Search:"}});});</script>
<?php include '../../includes/footer.php'; ?>
