<?php
session_start();
$active_page = 'purchase_return_list';
require_once '../../config/db.php';

$where = [];
$params = [];
$types = "";

if (!empty($_GET['from_date'])) {
    $where[] = "pr.return_date >= ?";
    $params[] = $_GET['from_date'];
    $types .= "s";
}
if (!empty($_GET['to_date'])) {
    $where[] = "pr.return_date <= ?";
    $params[] = $_GET['to_date'];
    $types .= "s";
}
if (!empty($_GET['supplier_id'])) {
    $where[] = "p.supplier_id = ?";
    $params[] = $_GET['supplier_id'];
    $types .= "i";
}
if (!empty($_GET['purchase_id'])) {
    $where[] = "pr.purchase_id = ?";
    $params[] = $_GET['purchase_id'];
    $types .= "i";
}

$sql = "SELECT pr.*, p.invoice_no, p.purchase_date, s.company_name
        FROM purchase_returns pr
        JOIN purchases p ON pr.purchase_id = p.id
        JOIN suppliers s ON p.supplier_id = s.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY pr.return_date DESC, pr.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$suppliers = $conn->query("SELECT id, company_name FROM suppliers ORDER BY company_name");
$purchases = $conn->query("SELECT id, invoice_no FROM purchases ORDER BY invoice_no DESC");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-undo-alt mr-1"></i> Purchase Return List</h1>
    <div>
        <a href="returns.php" class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm mr-1">
            <i class="fas fa-plus-circle"></i> New Return
        </a>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Purchases
        </a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Records</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                    <label class="small font-weight-bold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $_GET['from_date'] ?? '' ?>">
                </div>
                <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                    <label class="small font-weight-bold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $_GET['to_date'] ?? '' ?>">
                </div>
                <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                    <label class="small font-weight-bold">Supplier</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">All Suppliers</option>
                        <?php while ($row = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= (($_GET['supplier_id'] ?? '') == $row['id']) ? 'selected':'' ?>>
                                <?= htmlspecialchars($row['company_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                    <label class="small font-weight-bold">Invoice</label>
                    <select name="purchase_id" class="form-control">
                        <option value="">All Invoices</option>
                        <?php while ($row = $purchases->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= (($_GET['purchase_id'] ?? '') == $row['id']) ? 'selected':'' ?>>
                                <?= htmlspecialchars($row['invoice_no']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="returns_list.php" class="btn btn-secondary btn-block mt-1">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Return Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="returnsTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Supplier</th>
                        <th>Qty Returned (Ton)</th>
                        <th>Rate/Ton</th>
                        <th>Return Amount</th>
                        <th>Reason</th>
                        <th>Original Purchase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No return records found.</td></tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['return_date']) ?></td>
                                <td class="font-weight-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                                <td><?= htmlspecialchars($row['company_name']) ?></td>
                                <td><?= number_format($row['quantity_returned'], 3) ?></td>
                                <td><?= number_format($row['rate_per_ton'], 2) ?></td>
                                <td class="font-weight-bold text-danger"><?= number_format($row['return_amount'], 2) ?></td>
                                <td><?= htmlspecialchars($row['reason'] ?: '-') ?></td>
                                <td class="text-center">
                                    <a href="list.php?purchase_id=<?= $row['purchase_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#returnsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
