<?php
session_start();
$active_page = 'cashbook';
require_once '../../includes/db.php';

// Handle Add Account via modal
$acc_success = "";
$acc_error   = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    $account_name    = trim($_POST['account_name'] ?? '');
    $opening_balance = floatval($_POST['opening_balance'] ?? 0);
    if (empty($account_name)) {
        $acc_error = "Account name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO bank_accounts (account_name, account_type, opening_balance, current_balance) VALUES (?, 'Cash', ?, ?)");
        $stmt->bind_param("sdd", $account_name, $opening_balance, $opening_balance);
        $stmt->execute();
        $stmt->close();
        $acc_success = "Account \"$account_name\" created successfully!";
    }
}

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$account_id = intval($_GET['account_id'] ?? 0);

// Get all Cash-type accounts
$cash_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, current_balance FROM bank_accounts WHERE account_type = 'Cash' ORDER BY account_name ASC");
$cash_accounts_arr = [];
while ($ca = $cash_accounts->fetch_assoc()) {
    $cash_accounts_arr[] = $ca;
}

// Build account filter
$account_filter = '';
$account_ids    = [];
if ($account_id > 0) {
    $account_ids = [$account_id];
} else {
    foreach ($cash_accounts_arr as $ca) {
        $account_ids[] = $ca['id'];
    }
}

$transactions = [];

if (!empty($account_ids)) {
    $ids_in = implode(',', array_map('intval', $account_ids));

    // Customer payments (cash received from / paid to customers)
    $sql = "SELECT
                cl.transaction_date AS txn_date,
                cl.description,
                CASE WHEN cl.credit > 0 THEN 'IN' ELSE 'OUT' END AS direction,
                CASE WHEN cl.credit > 0 THEN cl.credit ELSE cl.debit END AS amount,
                c.customer_name AS party,
                'Customer' AS party_type,
                cl.payment_method,
                ba.account_name
            FROM customer_ledger cl
            JOIN customers c ON cl.customer_id = c.id
            JOIN bank_accounts ba ON cl.bank_account_id = ba.id
            WHERE cl.reference_type = 'payment'
              AND ba.account_type = 'Cash'
              AND cl.bank_account_id IN ($ids_in)
              AND cl.transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    // Supplier payments (cash paid to / received from suppliers)
    $sql = "SELECT
                sl.transaction_date AS txn_date,
                sl.description,
                CASE WHEN sl.credit > 0 THEN 'OUT' ELSE 'IN' END AS direction,
                CASE WHEN sl.credit > 0 THEN sl.credit ELSE sl.debit END AS amount,
                s.company_name AS party,
                'Supplier' AS party_type,
                sl.payment_method,
                ba.account_name
            FROM supplier_ledger sl
            JOIN suppliers s ON sl.supplier_id = s.id
            JOIN bank_accounts ba ON sl.bank_account_id = ba.id
            WHERE sl.reference_type = 'payment'
              AND ba.account_type = 'Cash'
              AND sl.bank_account_id IN ($ids_in)
              AND sl.transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    // Expenses paid by cash
    $sql = "SELECT
                e.expense_date AS txn_date,
                CONCAT(e.category, ' - ', e.subcategory, IFNULL(CONCAT(': ', e.description), '')) AS description,
                'OUT' AS direction,
                e.amount,
                'Expense' AS party,
                'Expense' AS party_type,
                e.payment_method,
                ba.account_name
            FROM expenses e
            JOIN bank_accounts ba ON e.bank_account_id = ba.id
            WHERE ba.account_type = 'Cash'
              AND e.bank_account_id IN ($ids_in)
              AND e.expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    // Stock transactions (direct cash purchases/sales from stock modules)
    $sql = "SELECT
                sl.transaction_date AS txn_date,
                sl.description,
                CASE WHEN sl.movement_type = 'OUT' THEN 'IN' ELSE 'OUT' END AS direction,
                sl.amount,
                t.tank_name AS party,
                'Stock' AS party_type,
                sl.payment_method,
                ba.account_name
            FROM stock_ledger sl
            JOIN tanks t ON sl.tank_id = t.id
            JOIN bank_accounts ba ON sl.bank_account_id = ba.id
            WHERE ba.account_type = 'Cash'
              AND sl.amount > 0
              AND sl.bank_account_id IN ($ids_in)
              AND sl.transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();
}

// Sort all transactions by date ASC
usort($transactions, fn($a, $b) => strcmp($a['txn_date'], $b['txn_date']));

$total_in  = array_sum(array_column(array_filter($transactions, fn($t) => $t['direction'] === 'IN'),  'amount'));
$total_out = array_sum(array_column(array_filter($transactions, fn($t) => $t['direction'] === 'OUT'), 'amount'));

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Cash Book</h1>
    <div>
        <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#addAccountModal">
            <i class="fas fa-plus-circle fa-sm"></i> Add Account
        </button>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print fa-sm"></i> Print
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Cash In</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_in, 2) ?></div>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Cash Out</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_out, 2) ?></div>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Net Cash Flow</div>
                        <?php $net = $total_in - $total_out; ?>
                        <div class="h5 mb-0 font-weight-bold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                            $ <?= number_format($net, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($cash_accounts_arr)): ?>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Cash Account(s) Balance</div>
                        <?php
                        $total_cash_bal = array_sum(array_column($cash_accounts_arr, 'current_balance'));
                        ?>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_cash_bal, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-wallet fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
            <?php if (!empty($cash_accounts_arr)): ?>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">Account</label>
                <select name="account_id" class="form-control form-control-sm">
                    <option value="0">All Cash Accounts</option>
                    <?php foreach ($cash_accounts_arr as $ca): ?>
                        <option value="<?= $ca['id'] ?>" <?= $account_id == $ca['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ca['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="cashbook.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Cash Transactions</h6>
    </div>
    <div class="card-body">
        <?php if (empty($cash_accounts_arr)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                No Cash accounts found. <a href="#" data-toggle="modal" data-target="#addAccountModal">Add a Cash account</a> first and select it when recording payments.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="cashbookTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Party</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th class="text-right">Cash In ($)</th>
                        <th class="text-right">Cash Out ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No cash transactions found for this period.</td></tr>
                    <?php else:
                        foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['txn_date']) ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td>
                                <?= htmlspecialchars($t['party']) ?>
                                <span class="badge badge-secondary ml-1"><?= htmlspecialchars($t['party_type']) ?></span>
                            </td>
                            <td>
                                <?php if ($t['direction'] === 'IN'): ?>
                                    <span class="badge badge-success"><i class="fas fa-arrow-down fa-xs"></i> Cash In</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-arrow-up fa-xs"></i> Cash Out</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['account_name']) ?></td>
                            <td class="text-right text-success font-weight-bold">
                                <?= $t['direction'] === 'IN' ? number_format($t['amount'], 2) : '-' ?>
                            </td>
                            <td class="text-right text-danger font-weight-bold">
                                <?= $t['direction'] === 'OUT' ? number_format($t['amount'], 2) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($transactions)): ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="5" class="text-right">Totals:</th>
                        <th class="text-right text-success">$ <?= number_format($total_in, 2) ?></th>
                        <th class="text-right text-danger">$ <?= number_format($total_out, 2) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-success mr-1"></i> New Cash Account</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($acc_success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($acc_success) ?></div>
                <?php endif; ?>
                <?php if ($acc_error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($acc_error) ?></div>
                <?php endif; ?>
                <input type="hidden" name="action" value="add_account">
                <div class="form-group">
                    <label class="small font-weight-bold">Account Name <span class="text-danger">*</span></label>
                    <input type="text" name="account_name" class="form-control" required placeholder="e.g. Petty Cash">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Opening Balance ($)</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#cashbookTable').DataTable({
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ordering: true,
        order: [[0, 'asc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
