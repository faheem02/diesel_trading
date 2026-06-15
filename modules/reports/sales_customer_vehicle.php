<?php
session_start();
$active_page = 'rpt_sales_customer_vehicle';
require_once '../../config/db.php';

$from_date   = $_GET['from_date']   ?? date('Y-m-01');
$to_date     = $_GET['to_date']     ?? date('Y-m-d');
$view        = $_GET['view']        ?? 'customer';
$customer_id = intval($_GET['customer_id'] ?? 0);
$vehicle_no  = trim($_GET['vehicle_no'] ?? '');

// Build WHERE clause
$where  = "cs.sale_date BETWEEN ? AND ?";
$params = [$from_date, $to_date];
$types  = "ss";

if ($view === 'customer' && $customer_id > 0) {
    $where .= " AND cs.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}
if ($view === 'vehicle') {
    if ($vehicle_no !== '') {
        $where .= " AND cs.vehicle_number LIKE ?";
        $params[] = "%$vehicle_no%";
        $types .= "s";
    }
}

$rows = [];
$grand_qty = 0; $grand_amount = 0; $grand_invoices = 0;

if ($view === 'customer') {
    $sql = "SELECT c.id AS customer_id, c.customer_name AS customer_name, c.mobile, COUNT(*) AS invoices,
                   SUM(cs.quantity) AS total_qty, SUM(cs.total_amount) AS total_amount,
                   SUM(CASE WHEN cs.payment_type = 'Credit' THEN cs.total_amount ELSE 0 END) AS credit_amount,
                   SUM(CASE WHEN cs.payment_type = 'Cash' THEN cs.total_amount ELSE 0 END) AS cash_amount
            FROM customer_sales cs
            JOIN customers c ON c.id = cs.customer_id
            WHERE $where
            GROUP BY c.id, c.customer_name, c.mobile ORDER BY total_amount DESC";
} else {
    $sql = "SELECT cs.vehicle_number, c.customer_name AS customer_name, c.mobile, COUNT(*) AS invoices,
                   SUM(cs.quantity) AS total_qty, SUM(cs.total_amount) AS total_amount
            FROM customer_sales cs
            JOIN customers c ON c.id = cs.customer_id
            WHERE $where AND cs.vehicle_number != ''
            GROUP BY cs.vehicle_number, c.customer_name, c.mobile ORDER BY total_qty DESC";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL error: " . $conn->error . "<br><pre>" . htmlspecialchars($sql) . "</pre>");
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
while ($r = $result->fetch_assoc()) {
    $grand_qty += $r['total_qty']; $grand_amount += $r['total_amount'];
    $grand_invoices += $r['invoices'];
    $rows[] = $r;
}

$customers = $conn->query("SELECT id, customer_name, mobile FROM customers ORDER BY customer_name ASC");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-1"></i> Sales Report — Customer / Vehicle Wise</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm"><i class="fas fa-print fa-sm"></i> Print</button>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filter</h6></div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">From</label><input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>"></div>
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">To</label><input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>"></div>
            <div class="form-group mr-3 mb-2"><label class="small font-weight-bold mr-1">View</label>
                <select name="view" class="form-control form-control-sm" id="viewSelect">
                    <option value="customer" <?= $view === 'customer' ? 'selected' : '' ?>>Customer Wise</option>
                    <option value="vehicle" <?= $view === 'vehicle' ? 'selected' : '' ?>>Vehicle Wise</option>
                </select>
            </div>
            <div class="form-group mr-3 mb-2" id="customerGroup" style="<?= $view === 'customer' ? '' : 'display:none' ?>">
                <label class="small font-weight-bold mr-1">Customer</label>
                <select name="customer_id" class="form-control form-control-sm">
                    <option value="0">All Customers</option>
                    <?php while ($c = $customers->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['mobile'] ?: '-') ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group mr-3 mb-2" id="vehicleGroup" style="<?= $view === 'vehicle' ? '' : 'display:none' ?>">
                <label class="small font-weight-bold mr-1">Vehicle No</label>
                <input type="text" name="vehicle_no" class="form-control form-control-sm" value="<?= htmlspecialchars($vehicle_no) ?>" placeholder="Search vehicle...">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="sales_customer_vehicle.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?= $view === 'customer' ? 'Customer Wise' : 'Vehicle Wise' ?> Summary</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="rptTable">
                <thead class="thead-dark">
                    <tr>
                        <?php if ($view === 'customer'): ?>
                            <th>#</th><th>Customer</th><th>Mobile</th><th class="text-right">Invoices</th><th class="text-right">Qty (Tons)</th><th class="text-right">Total Amount</th><th class="text-right">Cash</th><th class="text-right">Credit</th>
                        <?php else: ?>
                            <th>#</th><th>Vehicle No</th><th>Customer</th><th>Mobile</th><th class="text-right">Invoices</th><th class="text-right">Qty (Tons)</th><th class="text-right">Total Amount</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No sales found.</td></tr>
                    <?php else: $i = 1; foreach ($rows as $r): ?>
                    <tr>
                        <?php if ($view === 'customer'): ?>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><?= htmlspecialchars($r['mobile'] ?: '-') ?></td>
                            <td class="text-right"><?= $r['invoices'] ?></td>
                            <td class="text-right"><?= number_format($r['total_qty'], 3) ?></td>
                            <td class="text-right"><?= number_format($r['total_amount'], 0) ?></td>
                            <td class="text-right text-success"><?= number_format($r['cash_amount'], 0) ?></td>
                            <td class="text-right text-warning"><?= number_format($r['credit_amount'], 0) ?></td>
                        <?php else: ?>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($r['vehicle_number']) ?></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><?= htmlspecialchars($r['mobile'] ?: '-') ?></td>
                            <td class="text-right"><?= $r['invoices'] ?></td>
                            <td class="text-right"><?= number_format($r['total_qty'], 3) ?></td>
                            <td class="text-right"><?= number_format($r['total_amount'], 0) ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-active">
                    <tr>
                        <?php if ($view === 'customer'): ?>
                            <th colspan="3" class="text-right">Grand Totals:</th>
                        <?php else: ?>
                            <th colspan="4" class="text-right">Grand Totals:</th>
                        <?php endif; ?>
                        <th class="text-right"><?= $grand_invoices ?></th>
                        <th class="text-right"><?= number_format($grand_qty, 3) ?></th>
                        <th class="text-right"><?= number_format($grand_amount, 0) ?></th>
                        <?php if ($view === 'customer'): ?>
                            <th></th><th></th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    $('#rptTable').DataTable({pageLength:50,lengthMenu:[25,50,100],ordering:false,language:{search:"Search:",lengthMenu:"Show _MENU_ entries"}});
    $('#viewSelect').change(function(){
        var v = $(this).val();
        $('#customerGroup').toggle(v === 'customer');
        $('#vehicleGroup').toggle(v === 'vehicle');
        $(this).closest('form').submit();
    });
});
</script>
<?php include '../../includes/footer.php'; ?>
