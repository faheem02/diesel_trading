<?php
session_start();
$active_page = 'tanker_expense_list';
require_once '../../config/db.php';

$tanker_id    = intval($_GET['tanker_id'] ?? 0);
$expense_type = $_GET['expense_type'] ?? '';
$from_date    = $_GET['from_date'] ?? '';
$to_date      = $_GET['to_date'] ?? '';

$sql = "SELECT te.*, t.tanker_number FROM tanker_expenses te JOIN tankers t ON te.tanker_id = t.id WHERE 1=1";
$params = [];
$types = "";

if ($tanker_id > 0) {
    $sql .= " AND te.tanker_id = ?";
    $params[] = $tanker_id;
    $types .= "i";
}
if (!empty($expense_type)) {
    $sql .= " AND te.expense_type = ?";
    $params[] = $expense_type;
    $types .= "s";
}
if (!empty($from_date)) {
    $sql .= " AND te.expense_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND te.expense_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY te.expense_date DESC, te.id DESC";

$result = $conn->prepare($sql);
if (!empty($params)) {
    $result->bind_param($types, ...$params);
}
$result->execute();
$result = $result->get_result();

$tankers = $conn->query("SELECT id, tanker_number FROM tankers ORDER BY tanker_number ASC");
$grand_total = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM tanker_expenses")->fetch_assoc()['total'];

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Tanker Expenses List</h1>
    <div>
        <a href="expenses_add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus-circle"></i> New Expense
        </a>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?= number_format($grand_total, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-receipt fa-2x text-gray-300"></i></div>
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
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Tanker</label>
                        <select name="tanker_id" class="form-control">
                            <option value="">All Tankers</option>
                            <?php while ($t = $tankers->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>" <?= $tanker_id === $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['tanker_number']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Expense Type</label>
                        <select name="expense_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Fuel" <?= $expense_type === 'Fuel' ? 'selected' : '' ?>>Fuel Expense</option>
                            <option value="Driver" <?= $expense_type === 'Driver' ? 'selected' : '' ?>>Driver Expense</option>
                            <option value="Maintenance" <?= $expense_type === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Toll Tax" <?= $expense_type === 'Toll Tax' ? 'selected' : '' ?>>Toll Tax</option>
                            <option value="Other" <?= $expense_type === 'Other' ? 'selected' : '' ?>>Other Charges</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="expenses_list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Expense Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="expenseTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Tanker</th>
                        <th>Expense Type</th>
                        <th>Amount (Rs.)</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No expenses found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['expense_date']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tanker_number']) ?></td>
                            <td>
                                <?php
                                $badge = match($row['expense_type']) {
                                    'Fuel' => 'warning',
                                    'Driver' => 'info',
                                    'Maintenance' => 'secondary',
                                    'Toll Tax' => 'primary',
                                    default => 'dark'
                                };
                                ?>
                                <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($row['expense_type']) ?></span>
                            </td>
                            <td class="font-weight-bold text-danger"><?= number_format($row['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                            <td>
                                <a href="expenses_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?')"><i class="fas fa-trash"></i></a>
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
    $('#expenseTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
