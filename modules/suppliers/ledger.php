<?php
session_start();
$active_page = 'supplier_ledger';
require_once '../../includes/db.php';
require_once '../../includes/ledger.php';

$supplier_id = intval($_GET['id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$sup = null;
if ($supplier_id > 0) {
    $sup = $conn->query("SELECT id, company_name, balance, opening_balance FROM suppliers WHERE id = $supplier_id")->fetch_assoc();
}

$all_suppliers = $conn->query("SELECT id, company_name, balance FROM suppliers ORDER BY company_name ASC");


$where = "sl.supplier_id = $supplier_id";
$params = [];
$types = "";

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

$sql = "SELECT sl.*, s.company_name FROM supplier_ledger sl JOIN suppliers s ON sl.supplier_id = s.id WHERE $where ORDER BY sl.transaction_date ASC, sl.id ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$entries = $stmt->get_result();

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Supplier Ledger</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if (!$sup): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Supplier</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">Supplier</label>
                <select name="id" class="form-control" required>
                    <option value="">-- Select Supplier --</option>
                    <?php while ($s = $all_suppliers->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company_name']) ?> (Bal: <?= number_format($s['balance'], 2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> View Ledger</button>
        </form>
    </div>
</div>
<?php else: ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
    <i class="fas fa-truck mr-1"></i> <?= htmlspecialchars($sup['company_name']) ?>
    <span class="ml-3 badge badge-<?= $sup['balance'] >= 0 ? 'success' : 'danger' ?> font-weight-bold" style="font-size:0.9rem">
        Balance: <?= number_format($sup['balance'], 2) ?>
    </span>
</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline mb-3">
            <input type="hidden" name="id" value="<?= $supplier_id ?>">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($from_date ?: date('Y-m-01')) ?>">
            </div>
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($to_date ?: date('Y-m-d')) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter"></i> Filter</button>
            <a href="ledger.php?id=<?= $supplier_id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Ref</th>
                        <th class="text-right">Debit ($)</th>
                        <th class="text-right">Credit ($)</th>
                        <th class="text-right">Balance ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php else:
                        while ($e = $entries->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['transaction_date']) ?></td>
                            <td><?= htmlspecialchars($e['description']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($e['reference_type']) ?></span></td>
                            <td class="text-right text-success font-weight-bold"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?></td>
                            <td class="text-right text-danger font-weight-bold"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?></td>
                            <td class="text-right font-weight-bold <?= $e['balance'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($e['balance'], 2) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#ledgerTable').DataTable({
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
