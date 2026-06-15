<?php
session_start();
$active_page = 'general_ledger';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$type_filter = $_GET['type'] ?? '';

$entries = [];

function fetchGL($conn, $sql, $params, $types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function matchesFilter($type_filter, $accepted) {
    return !$type_filter || in_array($type_filter, $accepted);
}

// Supplier ledger entries
if (matchesFilter($type_filter, ['', 'purchase', 'payment', 'return', 'opening_balance', 'adjustment'])) {
    $sql = "SELECT sl.transaction_date AS txn_date, sl.description, sl.debit, sl.credit,
                   s.company_name AS party, 'Supplier' AS party_type, sl.reference_type, sl.reference_id,
                   ba.account_name AS account_ref, ba.account_type
            FROM supplier_ledger sl
            JOIN suppliers s ON sl.supplier_id = s.id
            LEFT JOIN bank_accounts ba ON sl.bank_account_id = ba.id
            WHERE sl.transaction_date BETWEEN ? AND ?";
    $params = [$from_date, $to_date];
    $types = "ss";
    if ($type_filter) {
        $sql .= " AND sl.reference_type = ?";
        $params[] = $type_filter;
        $types .= "s";
    }
    $sql .= " ORDER BY sl.transaction_date ASC, sl.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, $params, $types));
}

// Customer ledger entries
if (matchesFilter($type_filter, ['', 'sale', 'payment', 'return', 'opening_balance'])) {
    $sql = "SELECT cl.transaction_date AS txn_date, cl.description, cl.debit, cl.credit,
                   c.customer_name AS party, 'Customer' AS party_type, cl.reference_type, cl.reference_id,
                   ba.account_name AS account_ref, ba.account_type
            FROM customer_ledger cl
            JOIN customers c ON cl.customer_id = c.id
            LEFT JOIN bank_accounts ba ON cl.bank_account_id = ba.id
            WHERE cl.transaction_date BETWEEN ? AND ?";
    $params = [$from_date, $to_date];
    $types = "ss";
    if ($type_filter) {
        $sql .= " AND cl.reference_type = ?";
        $params[] = $type_filter;
        $types .= "s";
    }
    $sql .= " ORDER BY cl.transaction_date ASC, cl.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, $params, $types));
}

// Expenses
if (matchesFilter($type_filter, ['', 'expense'])) {
    $sql = "SELECT e.expense_date AS txn_date,
                   CONCAT(e.category, ' - ', e.subcategory, IFNULL(CONCAT(': ', e.description), '')) AS description,
                   e.amount AS debit, 0 AS credit,
                   'Expense' AS party, 'Expense' AS party_type,
                   'expense' AS reference_type, e.id AS reference_id,
                   ba.account_name AS account_ref, ba.account_type
            FROM expenses e
            LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
            WHERE e.expense_date BETWEEN ? AND ?
            ORDER BY e.expense_date ASC, e.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}

// Diesel stock sales
if (matchesFilter($type_filter, ['', 'stock_sale'])) {
    $sql = "SELECT s.sale_date AS txn_date,
                   CONCAT('Stock Sale #', s.invoice_no, ' - ', s.customer_name) AS description,
                   0 AS debit, s.total_amount AS credit,
                   s.customer_name AS party, 'Stock Sale' AS party_type,
                   'stock_sale' AS reference_type, s.id AS reference_id,
                   NULL AS account_ref, NULL AS account_type
            FROM sales s
            WHERE s.sale_date BETWEEN ? AND ?
            ORDER BY s.sale_date ASC, s.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}

// Purchase returns (also covered by supplier_ledger 'return' type, but include standalone)
if (matchesFilter($type_filter, ['', 'return'])) {
    $sql = "SELECT pr.return_date AS txn_date,
                   CONCAT('Purchase Return - Invoice #', p.invoice_no) AS description,
                   0 AS debit, pr.return_amount AS credit,
                   s.company_name AS party, 'Supplier' AS party_type,
                   'return' AS reference_type, pr.id AS reference_id,
                   NULL AS account_ref, NULL AS account_type
            FROM purchase_returns pr
            JOIN purchases p ON pr.purchase_id = p.id
            JOIN suppliers s ON p.supplier_id = s.id
            WHERE pr.return_date BETWEEN ? AND ?
            ORDER BY pr.return_date ASC, pr.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}

// Stock adjustments
if (matchesFilter($type_filter, ['', 'adjustment'])) {
    $sql = "SELECT sa.adjustment_date AS txn_date,
                   CONCAT('Stock Adjustment - ', sa.adjustment_type, IFNULL(CONCAT(': ', sa.description), '')) AS description,
                   sa.quantity AS debit, 0 AS credit,
                   t.tank_name AS party, 'Adjustment' AS party_type,
                   'adjustment' AS reference_type, sa.id AS reference_id,
                   NULL AS account_ref, NULL AS account_type
            FROM stock_adjustments sa
            JOIN tanks t ON sa.tank_id = t.id
            WHERE sa.adjustment_date BETWEEN ? AND ?
            ORDER BY sa.adjustment_date ASC, sa.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}

// Sort all entries by date, then by debit/credit priority
usort($entries, fn($a, $b) =>
    $a['txn_date'] <=> $b['txn_date'] ?:
    ($b['debit'] <=> $b['credit']) <=> ($a['debit'] <=> $a['credit'])
);

// Calculate running balance
$running_bal = 0;
foreach ($entries as &$e) {
    $running_bal += ($e['debit'] - $e['credit']);
    $e['running_bal'] = $running_bal;
}
unset($e);

$total_debit  = array_sum(array_column($entries, 'debit'));
$total_credit = array_sum(array_column($entries, 'credit'));

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> General Ledger</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
        <i class="fas fa-print fa-sm"></i> Print
    </button>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Debit (Rs.)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_debit, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-circle-down fa-2x text-success"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Credit (Rs.)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_credit, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-circle-up fa-2x text-danger"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Net Balance</div>
                        <div class="h5 mb-0 font-weight-bold <?= $running_bal >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($running_bal, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Entries</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($entries) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-list fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">Type</label>
                <select name="type" class="form-control form-control-sm">
                    <option value="">All Types</option>
                    <option value="purchase" <?= $type_filter === 'purchase' ? 'selected' : '' ?>>Purchase</option>
                    <option value="payment" <?= $type_filter === 'payment' ? 'selected' : '' ?>>Payment</option>
                    <option value="sale" <?= $type_filter === 'sale' ? 'selected' : '' ?>>Sale</option>
                    <option value="return" <?= $type_filter === 'return' ? 'selected' : '' ?>>Return</option>
                    <option value="expense" <?= $type_filter === 'expense' ? 'selected' : '' ?>>Expense</option>
                    <option value="adjustment" <?= $type_filter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="general_ledger.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Entries Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Ledger Entries</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="glTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Party</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th class="text-right">Debit (Rs.)</th>
                        <th class="text-right">Credit (Rs.)</th>
                        <th class="text-right">Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No entries found for this period.</td></tr>
                    <?php else:
                        foreach ($entries as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['txn_date']) ?></td>
                            <td><?= htmlspecialchars($e['description']) ?></td>
                            <td>
                                <?= htmlspecialchars($e['party']) ?>
                                <span class="badge badge-secondary ml-1"><?= htmlspecialchars($e['party_type']) ?></span>
                            </td>
                            <td>
                                <?php
                                $badge_map = [
                                    'purchase' => ['Purchase', 'primary'],
                                    'payment' => ['Payment', 'success'],
                                    'sale' => ['Sale', 'info'],
                                    'return' => ['Return', 'warning'],
                                    'expense' => ['Expense', 'danger'],
                                    'opening_balance' => ['Opening', 'secondary'],
                                    'stock_sale' => ['Stock Sale', 'info'],
                                    'adjustment' => ['Adjustment', 'dark'],
                                ];
                                $ref = $e['reference_type'] ?? '';
                                [$label, $color] = $badge_map[$ref] ?? [$ref, 'secondary'];
                                ?>
                                <span class="badge badge-<?= $color ?>"><?= $label ?></span>
                            </td>
                            <td>
                                <?php if ($e['account_ref']): ?>
                                    <?= htmlspecialchars($e['account_ref']) ?>
                                    <?php if ($e['account_type']): ?>
                                        <span class="badge badge-<?= $e['account_type'] === 'Cash' ? 'success' : 'primary' ?> badge-sm">
                                            <?= htmlspecialchars($e['account_type']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right text-danger font-weight-bold">
                                <?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?>
                            </td>
                            <td class="text-right text-success font-weight-bold">
                                <?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?>
                            </td>
                            <td class="text-right font-weight-bold <?= $e['running_bal'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($e['running_bal'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($entries)): ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="5" class="text-right">Totals:</th>
                        <th class="text-right text-danger"><?= number_format($total_debit, 2) ?></th>
                        <th class="text-right text-success"><?= number_format($total_credit, 2) ?></th>
                        <th class="text-right font-weight-bold"><?= number_format($running_bal, 2) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#glTable').DataTable({
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ordering: true,
        order: [[0, 'asc'], [5, 'desc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
