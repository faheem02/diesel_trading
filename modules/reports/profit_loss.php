<?php
session_start();
$active_page = 'rpt_profit_loss';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');

// Revenue from customer sales
$sales_data = $conn->query("SELECT COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(total_amount),0) AS amount FROM customer_sales WHERE sale_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Revenue from diesel stock sales
$stock_sales_data = $conn->query("SELECT COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(total_amount),0) AS amount FROM sales WHERE sale_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Purchases (cost of goods)
$purchase_data = $conn->query("SELECT COALESCE(SUM(diesel_quantity),0) AS qty, COALESCE(SUM(total_amount),0) AS diesel_cost, COALESCE(SUM(freight_charges),0) AS freight, COALESCE(SUM(other_charges),0) AS other, COALESCE(SUM(net_purchase_cost),0) AS total FROM purchases WHERE purchase_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Expenses by category
$expense_data = [];
$exp = $conn->query("SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date' GROUP BY category ORDER BY category");
while ($e = $exp->fetch_assoc()) $expense_data[] = $e;
$total_expenses = array_sum(array_column($expense_data, 'total'));

// Stock adjustment losses (treat as expense)
$adj_loss = $conn->query("SELECT COALESCE(SUM(quantity),0) AS t FROM stock_adjustments WHERE adjustment_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['t'];
// Get avg purchase rate for stock adjustment valuation
$avg_rate = $purchase_data['diesel_cost'] > 0 && $purchase_data['qty'] > 0 ? $purchase_data['diesel_cost'] / $purchase_data['qty'] : 0;
$adj_loss_value = $adj_loss * $avg_rate;

$total_revenue = $sales_data['amount'] + $stock_sales_data['amount'];
$total_cogs = $purchase_data['total'];
$gross_profit = $total_revenue - $total_cogs;
$net_profit = $gross_profit - $total_expenses - $adj_loss_value;

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line mr-1"></i> Profit & Loss Statement</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm"><i class="fas fa-print fa-sm"></i> Print</button>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filter</h6></div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">From</label><input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>"></div>
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">To</label><input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>"></div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Generate</button>
            <a href="profit_loss.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow mb-4">
            <div class="card-header py-3 text-center">
                <h5 class="m-0 font-weight-bold text-primary">Profit & Loss Statement</h5>
                <small class="text-muted">For the period <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?></small>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <tr class="bg-light"><th colspan="3" class="text-primary">Revenue</th></tr>
                    <tr><td class="pl-4" style="width:50%">Customer Sales (Qty: <?= number_format($sales_data['qty'], 3) ?> Tons)</td><td class="text-right font-weight-bold"><?= number_format($sales_data['amount'], 2) ?></td><td style="width:100px"></td></tr>
                    <tr><td class="pl-4">Diesel Stock Sales (Qty: <?= number_format($stock_sales_data['qty'], 3) ?> Tons)</td><td class="text-right font-weight-bold"><?= number_format($stock_sales_data['amount'], 2) ?></td><td></td></tr>
                    <tr class="table-success"><th>Total Revenue</th><th class="text-right"><?= number_format($total_revenue, 2) ?></th><th></th></tr>

                    <tr class="bg-light"><th colspan="3" class="text-danger">Cost of Goods Sold</th></tr>
                    <tr><td class="pl-4">Purchases (Main Module)</td><td class="text-right"><?= number_format($purchase_data['total'], 2) ?></td><td></td></tr>
                    <?php if ($direct_stock_in['amount'] > 0): ?>
                    <tr><td class="pl-4">Direct Stock In Costs (Manual)</td><td class="text-right"><?= number_format($direct_stock_in['amount'], 2) ?></td><td></td></tr>
                    <?php endif; ?>
                    <tr class="table-warning"><th>Total Cost of Goods Sold</th><th class="text-right"><?= number_format($total_cogs, 2) ?></th><th></th></tr>

                    <tr class="bg-light"><th colspan="3">Gross Profit</th></tr>
                    <tr class="table-primary"><td colspan="2" class="text-right font-weight-bold">Gross Profit (Revenue - COGS)</td><td class="text-right font-weight-bold <?= $gross_profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($gross_profit, 2) ?></td></tr>

                    <tr class="bg-light"><th colspan="3" class="text-danger">Operating Expenses</th></tr>
                    <?php if (empty($expense_data) && $adj_loss_value <= 0): ?>
                    <tr><td colspan="3" class="text-center text-muted">No expenses recorded for this period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($expense_data as $e): ?>
                    <tr><td class="pl-4"><?= htmlspecialchars($e['category']) ?> Expenses</td><td class="text-right"><?= number_format($e['total'], 2) ?></td><td></td></tr>
                    <?php endforeach; ?>
                    <?php if ($adj_loss_value > 0): ?>
                    <tr><td class="pl-4">Stock Adjustments (Losses) — <?= number_format($adj_loss, 3) ?> Tons</td><td class="text-right"><?= number_format($adj_loss_value, 2) ?></td><td></td></tr>
                    <?php endif; ?>
                    <tr class="table-warning"><th>Total Expenses</th><th class="text-right"><?= number_format($total_expenses + $adj_loss_value, 2) ?></th><th></th></tr>
                    <?php endif; ?>

                    <tr class="bg-light"><th colspan="3">Net Profit / Loss</th></tr>
                    <tr class="<?= $net_profit >= 0 ? 'table-success' : 'table-danger' ?>">
                        <td colspan="2" class="text-right font-weight-bold"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></td>
                        <td class="text-right font-weight-bold"><?= number_format(abs($net_profit), 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
f; ?>

                    <tr class="bg-light"><th colspan="3">Net Profit / Loss</th></tr>
                    <tr class="<?= $net_profit >= 0 ? 'table-success' : 'table-danger' ?>">
                        <td colspan="2" class="text-right font-weight-bold"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></td>
                        <td class="text-right font-weight-bold"><?= number_format(abs($net_profit), 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
