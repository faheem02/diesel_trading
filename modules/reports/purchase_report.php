<?php
session_start();
$active_page = 'rpt_purchase';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$view      = $_GET['view'] ?? 'daily';

$rows = [];
$grand_qty = 0; $grand_cost = 0; $grand_paid = 0; $grand_invoices = 0;

if ($view === 'daily') {
    $sql = "SELECT purchase_date AS label, COUNT(*) AS invoices, SUM(diesel_quantity) AS total_qty,
                   SUM(net_purchase_cost) AS total_cost, SUM(paid_amount) AS total_paid
            FROM purchases WHERE purchase_date BETWEEN ? AND ?
            GROUP BY purchase_date ORDER BY purchase_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($r = $result->fetch_assoc()) {
        $grand_qty += $r['total_qty']; $grand_cost += $r['total_cost'];
        $grand_paid += $r['total_paid']; $grand_invoices += $r['invoices'];
        $rows[] = $r;
    }
} else {
    $sql = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS label, COUNT(*) AS invoices,
                   SUM(diesel_quantity) AS total_qty, SUM(net_purchase_cost) AS total_cost, SUM(paid_amount) AS total_paid
            FROM purchases WHERE purchase_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(purchase_date, '%Y-%m') ORDER BY label DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($r = $result->fetch_assoc()) {
        $grand_qty += $r['total_qty']; $grand_cost += $r['total_cost'];
        $grand_paid += $r['total_paid']; $grand_invoices += $r['invoices'];
        $r['label_display'] = date('F Y', strtotime($r['label'] . '-01'));
        $rows[] = $r;
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-alt mr-1"></i> Purchase Report</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm"><i class="fas fa-print fa-sm"></i> Print</button>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filter</h6></div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">From</label><input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>"></div>
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">To</label><input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>"></div>
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">View</label>
                <select name="view" class="form-control form-control-sm">
                    <option value="daily" <?= $view === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="monthly" <?= $view === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="purchase_report.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?= ucfirst($view) ?> Summary</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="rptTable">
                <thead class="thead-dark">
                    <tr>
                        <th><?= $view === 'daily' ? 'Date' : 'Month' ?></th>
                        <th class="text-right">Invoices</th>
                        <th class="text-right">Qty (Tons)</th>
                        <th class="text-right">Total Cost</th>
                        <th class="text-right">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No purchases found.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="font-weight-bold"><?= htmlspecialchars($r['label_display'] ?? $r['label']) ?></td>
                        <td class="text-right"><?= $r['invoices'] ?></td>
                        <td class="text-right"><?= number_format($r['total_qty'], 3) ?></td>
                        <td class="text-right"><?= number_format($r['total_cost'], 0) ?></td>
                        <td class="text-right"><?= number_format($r['total_paid'], 0) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-active">
                    <tr>
                        <th class="text-right">Grand Totals:</th>
                        <th class="text-right"><?= $grand_invoices ?></th>
                        <th class="text-right"><?= number_format($grand_qty, 3) ?></th>
                        <th class="text-right"><?= number_format($grand_cost, 0) ?></th>
                        <th class="text-right"><?= number_format($grand_paid, 0) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<script>$(document).ready(function(){$('#rptTable').DataTable({pageLength:50,lengthMenu:[25,50,100],ordering:false,language:{search:"Search:",lengthMenu:"Show _MENU_ entries"}});});</script>
<?php include '../../includes/footer.php'; ?>
