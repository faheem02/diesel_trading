<?php
session_start();
$active_page = 'sale_list';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$sql = "SELECT cs.* FROM customer_sales cs WHERE 1=1";
$params = [];
$types = "";

if (!empty($from_date)) {
    $sql .= " AND cs.sale_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND cs.sale_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY cs.sale_date DESC, cs.id DESC";

$result = $conn->prepare($sql);
if (!empty($params)) {
    $result->bind_param($types, ...$params);
}
$result->execute();
$result = $result->get_result();

// Calculate average purchase cost rates from ALL purchases (for the date range if filtered)
$cost_sql = "SELECT
    COALESCE(SUM(diesel_quantity), 0) AS total_qty,
    COALESCE(SUM(total_amount), 0) AS total_amount,
    COALESCE(SUM(freight_charges), 0) AS total_freight,
    COALESCE(SUM(other_charges), 0) AS total_other
FROM purchases WHERE 1=1";
$cost_params = [];
$cost_types = "";
if (!empty($from_date)) {
    $cost_sql .= " AND purchase_date <= ?";
    $cost_params[] = $to_date ?: date('Y-m-d');
    $cost_types .= "s";
}
$cost_stmt = $conn->prepare($cost_sql);
if (!empty($cost_params)) {
    $cost_stmt->bind_param($cost_types, ...$cost_params);
}
$cost_stmt->execute();
$purchase_totals = $cost_stmt->get_result()->fetch_assoc();
$cost_stmt->close();

$avg_diesel_rate = 0;
$avg_freight_rate = 0;
$avg_other_rate = 0;
if ($purchase_totals['total_qty'] > 0) {
    $avg_diesel_rate  = $purchase_totals['total_amount'] / $purchase_totals['total_qty'];
    $avg_freight_rate = $purchase_totals['total_freight'] / $purchase_totals['total_qty'];
    $avg_other_rate   = $purchase_totals['total_other'] / $purchase_totals['total_qty'];
}

// Compute totals for the summary row
$total_sale_value = 0;
$total_diesel_cost = 0;
$total_freight_cost = 0;
$total_other_cost = 0;
$total_profit = 0;
$sales_data = [];
while ($row = $result->fetch_assoc()) {
    $qty = $row['quantity'];
    $diesel_cost  = $qty * $avg_diesel_rate;
    $freight_cost = $qty * $avg_freight_rate;
    $other_cost   = $qty * $avg_other_rate;
    $profit = $row['total_amount'] - $diesel_cost - $freight_cost - $other_cost;

    $total_sale_value  += $row['total_amount'];
    $total_diesel_cost += $diesel_cost;
    $total_freight_cost += $freight_cost;
    $total_other_cost  += $other_cost;
    $total_profit      += $profit;

    $sales_data[] = [
        'row' => $row,
        'diesel_cost' => $diesel_cost,
        'freight_cost' => $freight_cost,
        'other_cost' => $other_cost,
        'profit' => $profit,
    ];
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Sales List</h1>
    <div>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus-circle"></i> New Sale
        </a>
    </div>
</div>

<!-- Profit Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Sale Value</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_sale_value, 0) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Diesel Cost</div>
                        <div class="h5 mb-0 font-weight-bold text-danger"><?= number_format($total_diesel_cost, 0) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-oil-can fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Freight Cost</div>
                        <div class="h5 mb-0 font-weight-bold text-warning"><?= number_format($total_freight_cost, 0) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Other Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-info"><?= number_format($total_other_cost, 0) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-receipt fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Net Profit</div>
                        <div class="h5 mb-0 font-weight-bold <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($total_profit, 0) ?>
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($purchase_totals['total_qty'] > 0): ?>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Avg Cost/Ton</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($avg_diesel_rate + $avg_freight_rate + $avg_other_rate, 0) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-calculator fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Records</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Sales</h6>
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
                        <th>Qty</th>
                        <th class="text-right">Sale Value</th>
                        <th class="text-right">Diesel Cost</th>
                        <th class="text-right">Freight</th>
                        <th class="text-right">Other</th>
                        <th class="text-right">Net Profit</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_data)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">No sales found.</td></tr>
                    <?php else:
                        $i = 1;
                        foreach ($sales_data as $sd):
                            $row = $sd['row']; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                            <td><?= htmlspecialchars($row['sale_date']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= number_format($row['quantity'], 3) ?></td>
                            <td class="text-right font-weight-bold"><?= number_format($row['total_amount'], 0) ?></td>
                            <td class="text-right text-danger"><?= number_format($sd['diesel_cost'], 0) ?></td>
                            <td class="text-right text-warning"><?= number_format($sd['freight_cost'], 0) ?></td>
                            <td class="text-right text-info"><?= number_format($sd['other_cost'], 0) ?></td>
                            <td class="text-right font-weight-bold <?= $sd['profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($sd['profit'], 0) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $row['payment_type'] === 'Credit' ? 'warning' : 'success' ?>">
                                    <?= $row['payment_type'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($sales_data)): ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="5" class="text-right">Totals:</th>
                        <th class="text-right"><?= number_format($total_sale_value, 0) ?></th>
                        <th class="text-right text-danger"><?= number_format($total_diesel_cost, 0) ?></th>
                        <th class="text-right text-warning"><?= number_format($total_freight_cost, 0) ?></th>
                        <th class="text-right text-info"><?= number_format($total_other_cost, 0) ?></th>
                        <th class="text-right <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($total_profit, 0) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php if ($purchase_totals['total_qty'] > 0): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Cost Basis</h6>
    </div>
    <div class="card-body">
        <p class="mb-0 text-muted small">
            Diesel cost rate: <strong>Rs. <?= number_format($avg_diesel_rate, 2) ?>/ton</strong> &middot;
            Freight rate: <strong>Rs. <?= number_format($avg_freight_rate, 2) ?>/ton</strong> &middot;
            Other charges rate: <strong>Rs. <?= number_format($avg_other_rate, 2) ?>/ton</strong> &middot;
            Total cost rate: <strong>Rs. <?= number_format($avg_diesel_rate + $avg_freight_rate + $avg_other_rate, 2) ?>/ton</strong>
            &mdash; Calculated from all purchase records<?= !empty($from_date) || !empty($to_date) ? ' up to the selected date range' : '' ?>.
        </p>
    </div>
</div>
<?php endif; ?>

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
