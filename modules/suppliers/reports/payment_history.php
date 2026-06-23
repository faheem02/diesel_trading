<?php
session_start();
$active_page = 'supplier_payment_history';
require_once '../../../includes/db.php';

$supplier_id = intval($_GET['supplier_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = "sl.reference_type = 'payment'";
$params = [];
$types = "";

if ($supplier_id > 0) {
    $where .= " AND sl.supplier_id = ?";
    $params[] = $supplier_id;
    $types .= "i";
}
if (!empty($from_date)) {
    $where .= " AND sl.transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $where .= " AND sl.transaction_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql = "SELECT sl.*, s.company_name FROM supplier_ledger sl JOIN suppliers s ON sl.supplier_id = s.id WHERE $where ORDER BY sl.transaction_date DESC, sl.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result();

$total_paid = $conn->query("SELECT COALESCE(SUM(debit + credit),0) AS total FROM supplier_ledger WHERE reference_type = 'payment'")->fetch_assoc()['total'];

$suppliers = $conn->query("SELECT id, company_name FROM suppliers ORDER BY company_name ASC");

include '../../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-history mr-1"></i> Payment History</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
        <i class="fas fa-print"></i> Print
    </button>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white shadow h-100">
            <div class="card-body">
                <div class="font-weight-bold text-white-50 small">Total Payments Made</div>
                <div class="display-6 font-weight-bold">$ <?= number_format($total_paid, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Payments</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">-- All Suppliers --</option>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>" <?= $supplier_id == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['company_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-primary mr-1"><i class="fas fa-filter"></i> Filter</button>
            <a href="payment_history.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Payment Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="paymentTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Description</th>
                        <th class="text-right">Amount ($)</th>
                        <th class="text-right">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments->num_rows === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No payments found.</td></tr>
                    <?php else:
                        while ($p = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['transaction_date']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($p['company_name']) ?></td>
                            <td><?= htmlspecialchars($p['description']) ?></td>
                            <td class="text-right font-weight-bold <?= $p['credit'] > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $p['credit'] > 0 ? '-' : '+' ?> <?= number_format($p['credit'] ?: $p['debit'], 2) ?>
                            </td>
                            <td class="text-right font-weight-bold"><?= number_format($p['balance'], 2) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#paymentTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
