<?php
session_start();
$active_page = 'expense_list';
require_once '../../includes/db.php';

$category    = $_GET['category'] ?? '';
$subcategory = $_GET['subcategory'] ?? '';
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

$sql = "SELECT * FROM expenses WHERE 1=1";
$params = [];
$types = "";

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}
if (!empty($subcategory)) {
    $sql .= " AND subcategory = ?";
    $params[] = $subcategory;
    $types .= "s";
}
if (!empty($from_date)) {
    $sql .= " AND expense_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND expense_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY expense_date DESC, id DESC";

$result = $conn->prepare($sql);
if (!empty($params)) {
    $result->bind_param($types, ...$params);
}
$result->execute();
$result = $result->get_result();

$category_totals = $conn->query("SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses GROUP BY category");
$cat_totals = [];
while ($ct = $category_totals->fetch_assoc()) {
    $cat_totals[$ct['category']] = $ct['total'];
}

$grand_total = array_sum($cat_totals);

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Expenses List</h1>
    <div>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus-circle"></i> New Expense
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total All</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($grand_total, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-receipt fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Purchase Related</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($cat_totals['Purchase Related'] ?? 0, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Office Expenses</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($cat_totals['Office'] ?? 0, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Vehicle Expenses</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($cat_totals['Vehicle'] ?? 0, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-car fa-2x text-gray-300"></i></div>
            </div></div>
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
                        <label class="small font-weight-bold">Category</label>
                        <select name="category" id="filter_category" class="form-control">
                            <option value="">All Categories</option>
                            <option value="Purchase Related" <?= $category === 'Purchase Related' ? 'selected' : '' ?>>Purchase Related</option>
                            <option value="Office" <?= $category === 'Office' ? 'selected' : '' ?>>Office Expenses</option>
                            <option value="Vehicle" <?= $category === 'Vehicle' ? 'selected' : '' ?>>Vehicle Expenses</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Subcategory</label>
                        <select name="subcategory" id="filter_subcategory" class="form-control">
                            <option value="">All Subcategories</option>
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
                    <a href="list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
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
                        <th>Category</th>
                        <th>Subcategory</th>
                        <th>Amount ($)</th>
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
                            <td>
                                <?php
                                $cbadge = match($row['category']) {
                                    'Purchase Related' => 'warning',
                                    'Office' => 'info',
                                    'Vehicle' => 'secondary',
                                    default => 'dark'
                                };
                                ?>
                                <span class="badge badge-<?= $cbadge ?>"><?= htmlspecialchars($row['category']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['subcategory']) ?></td>
                            <td class="font-weight-bold text-danger"><?= number_format($row['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                            <td><a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?')"><i class="fas fa-trash"></i></a></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const filterSubcategories = {
    'Purchase Related': ['Freight Charges', 'Loading Charges', 'Unloading Charges', 'Driver Expense'],
    'Office': ['Salaries', 'Utilities', 'Rent', 'Internet', 'Miscellaneous'],
    'Vehicle': ['Diesel Consumption', 'Repairs', 'Tyres', 'Maintenance']
};

document.getElementById('filter_category').addEventListener('change', function() {
    const sel = document.getElementById('filter_subcategory');
    const cat = this.value;
    sel.innerHTML = '<option value="">All Subcategories</option>';
    if (filterSubcategories[cat]) {
        filterSubcategories[cat].forEach(function(s) {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            sel.appendChild(opt);
        });
    }
});

<?php if (!empty($subcategory)): ?>
document.getElementById('filter_category').dispatchEvent(new Event('change'));
document.getElementById('filter_subcategory').value = '<?= htmlspecialchars($subcategory, ENT_QUOTES) ?>';
<?php endif; ?>

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
