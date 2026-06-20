<?php
session_start();
$active_page = 'customer_recovery';
require_once '../../../config/db.php';

$customer_id = intval($_GET['customer_id'] ?? 0);
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

$customers = $conn->query("SELECT id, customer_name FROM customers ORDER BY customer_name ASC");

$sql = "SELECT cl.*, c.customer_name FROM customer_ledger cl JOIN customers c ON cl.customer_id = c.id WHERE cl.reference_type = 'payment'";
$params = [];
$types = "";

if (!empty($from_date)) {
    $sql .= " AND cl.transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND cl.transaction_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}
if ($customer_id > 0) {
    $sql .= " AND cl.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

$sql .= " ORDER BY cl.transaction_date DESC, cl.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total_recovered = $conn->query("SELECT COALESCE(SUM(credit),0) AS total FROM customer_ledger WHERE reference_type = 'payment'")->fetch_assoc()['total'];

include '../../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-hand-holding-usd mr-1"></i> Customer Recovery Report</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Recovered</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_recovered, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
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
                        <label class="small font-weight-bold">Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">All Customers</option>
                            <?php while ($c = $customers->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>" <?= $customer_id === $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['customer_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
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
            </div>
            <div class="row mt-3">
                <div class="col">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="recovery.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Recovery / Payment Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="recoveryTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Description</th>
                        <th>Amount ($)</th>
                        <th>Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No recovery records found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td class="font-weight-bold <?= $row['credit'] > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $row['credit'] > 0 ? '+' . number_format($row['credit'], 2) : '-' . number_format($row['debit'], 2) ?>
                            </td>
                            <td><?= number_format($row['balance'], 2) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#recoveryTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
