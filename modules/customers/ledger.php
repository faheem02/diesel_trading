<?php
session_start();
$active_page = 'customer_ledger';
require_once '../../config/db.php';

$customer_id = intval($_GET['id'] ?? 0);
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

$customers = $conn->query("SELECT id, customer_name, balance FROM customers ORDER BY customer_name ASC");

$sup = null;
if ($customer_id > 0) {
    $sup = $conn->query("SELECT id, customer_name, balance, opening_balance, credit_limit FROM customers WHERE id = $customer_id")->fetch_assoc();
    if (!$sup) {
        $customer_id = 0;
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Customer Ledger</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<?php if (!$sup): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Customer</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group mr-3">
                <label class="small font-weight-bold mr-2">Customer</label>
                <select name="id" class="form-control" required>
                    <option value="">-- Select Customer --</option>
                    <?php while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?> (Bal: <?= number_format($c['balance'], 2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> View Ledger</button>
        </form>
    </div>
</div>
<?php else:
    $sql = "SELECT * FROM customer_ledger WHERE customer_id = ?";
    $params = [$customer_id];
    $types = "i";

    if (!empty($from_date)) {
        $sql .= " AND transaction_date >= ?";
        $params[] = $from_date;
        $types .= "s";
    } else {
        $from_date = date('Y-m-01');
    }
    if (!empty($to_date)) {
        $sql .= " AND transaction_date <= ?";
        $params[] = $to_date;
        $types .= "s";
    } else {
        $to_date = date('Y-m-d');
    }

    $sql .= " ORDER BY transaction_date ASC, id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $entries = $stmt->get_result();
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($sup['customer_name']) ?>
            <span class="badge badge-<?= $sup['balance'] >= 0 ? 'success' : 'danger' ?> ml-2">Bal: Rs. <?= number_format($sup['balance'], 2) ?></span>
            <?php if ($sup['credit_limit'] > 0): ?>
                <small class="text-muted ml-2">Credit Limit: Rs. <?= number_format($sup['credit_limit'], 2) ?></small>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline mb-3">
            <input type="hidden" name="id" value="<?= $customer_id ?>">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="ledger.php?id=<?= $customer_id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Ref</th>
                        <th>Debit (Rs.)</th>
                        <th>Credit (Rs.)</th>
                        <th>Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php else:
                        while ($row = $entries->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><span class="badge badge-info"><?= str_replace('_', ' ', $row['reference_type']) ?></span></td>
                            <td class="text-danger font-weight-bold"><?= $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' ?></td>
                            <td class="text-success font-weight-bold"><?= $row['credit'] > 0 ? number_format($row['credit'], 2) : '-' ?></td>
                            <td class="font-weight-bold <?= $row['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($row['balance'], 2) ?>
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
    $('#ledgerTable').DataTable({
        pageLength: 50,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
