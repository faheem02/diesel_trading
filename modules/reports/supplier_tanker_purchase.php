<?php
session_start();
$active_page = 'rpt_supplier_tanker_purchase';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$view      = $_GET['view'] ?? 'company';

$rows = [];

if ($view === 'company') {
    $sql = "SELECT s.id, s.company_name,
                   COUNT(*) AS invoices, SUM(p.diesel_quantity) AS total_qty,
                   SUM(p.net_purchase_cost) AS total_cost, SUM(p.paid_amount) AS total_paid
            FROM purchases p JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.purchase_date BETWEEN ? AND ?
            GROUP BY s.id, s.company_name ORDER BY total_cost DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($r = $result->fetch_assoc()) $rows[] = $r;
} else {
    $sql = "SELECT pt.tanker_number, pt.driver_name, pt.driver_mobile,
                   COUNT(*) AS trips, SUM(pt.diesel_quantity) AS total_qty,
                   SUM(pt.net_amount) AS total_cost, SUM(pt.freight_charges) AS total_freight,
                   SUM(pt.other_charges) AS total_other
            FROM purchase_tankers pt JOIN purchases p ON pt.purchase_id = p.id
            WHERE p.purchase_date BETWEEN ? AND ? AND pt.tanker_number != ''
            GROUP BY pt.tanker_number, pt.driver_name, pt.driver_mobile ORDER BY total_qty DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($r = $result->fetch_assoc()) $rows[] = $r;
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Supplier / Tanker Purchase Report</h1>
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
                    <option value="company" <?= $view === 'company' ? 'selected' : '' ?>>Company Wise</option>
                    <option value="tanker" <?= $view === 'tanker' ? 'selected' : '' ?>>Tanker Wise</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="supplier_tanker_purchase.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?= $view === 'company' ? 'Supplier' : 'Tanker' ?> Summary</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="rptTable">
                <thead class="thead-dark">
                    <?php if ($view === 'company'): ?>
                        <tr><th>#</th><th>Supplier</th><th class="text-right">Invoices</th><th class="text-right">Qty (Tons)</th><th class="text-right">Total Cost</th><th class="text-right">Amount Paid</th><th class="text-right">Balance</th></tr>
                    <?php else: ?>
                        <tr><th>#</th><th>Tanker No</th><th>Driver</th><th>Mobile</th><th class="text-right">Trips</th><th class="text-right">Qty (Tons)</th><th class="text-right">Total Cost</th><th class="text-right">Freight</th><th class="text-right">Other</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No purchases found.</td></tr>
                    <?php else: $i = 1; foreach ($rows as $r): ?>
                    <tr>
                        <?php if ($view === 'company'): ?>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($r['company_name']) ?></td>
                            <td class="text-right"><?= $r['invoices'] ?></td>
                            <td class="text-right"><?= number_format($r['total_qty'], 3) ?></td>
                            <td class="text-right"><?= number_format($r['total_cost'], 0) ?></td>
                            <td class="text-right"><?= number_format($r['total_paid'], 0) ?></td>
                            <td class="text-right font-weight-bold"><?= number_format($r['total_cost'] - $r['total_paid'], 0) ?></td>
                        <?php else: ?>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($r['tanker_number']) ?></td>
                            <td><?= htmlspecialchars($r['driver_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($r['driver_mobile'] ?: '-') ?></td>
                            <td class="text-right"><?= $r['trips'] ?></td>
                            <td class="text-right"><?= number_format($r['total_qty'], 3) ?></td>
                            <td class="text-right"><?= number_format($r['total_cost'], 0) ?></td>
                            <td class="text-right"><?= number_format($r['total_freight'], 0) ?></td>
                            <td class="text-right"><?= number_format($r['total_other'], 0) ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-active">
                    <?php if ($view === 'company'): ?>
                        <tr><th colspan="2" class="text-right">Total:</th><th class="text-right"><?= array_sum(array_column($rows, 'invoices')) ?></th><th class="text-right"><?= number_format(array_sum(array_column($rows, 'total_qty')), 3) ?></th><th class="text-right"><?= number_format(array_sum(array_column($rows, 'total_cost')), 0) ?></th><th class="text-right"><?= number_format(array_sum(array_column($rows, 'total_paid')), 0) ?></th><th class="text-right"><?= number_format(array_sum(array_column($rows, 'total_cost')) - array_sum(array_column($rows, 'total_paid')), 0) ?></th></tr>
                    <?php endif; ?>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<script>$(document).ready(function(){$('#rptTable').DataTable({pageLength:25,ordering:false,language:{search:"Search:"}});});</script>
<?php include '../../includes/footer.php'; ?>
