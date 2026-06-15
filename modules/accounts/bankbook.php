<?php
session_start();
$active_page = 'bankbook';
require_once '../../config/db.php';

// Handle Add Account via modal
$acc_success = "";
$acc_error   = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    $account_name    = trim($_POST['name'] ?? '');
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');
    $opening_balance = floatval($_POST['opening_balance'] ?? 0);
    if (empty($account_name) || empty($bank_name)) {
        $acc_error = "Name and Bank Name are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO bank_accounts (account_name, bank_name, account_number, account_type, opening_balance, current_balance) VALUES (?, ?, ?, 'Bank', ?, ?)");
        $stmt->bind_param("sssdd", $account_name, $bank_name, $account_number, $opening_balance, $opening_balance);
        $stmt->execute();
        $stmt->close();
        $acc_success = "Account \"$account_name ($bank_name)\" created successfully!";
    }
}

$from_date  = $_GET['from_date']  ?? date('Y-m-01');
$to_date    = $_GET['to_date']    ?? date('Y-m-d');
$account_id = intval($_GET['account_id'] ?? 0);

// Get all Bank-type accounts
$bank_accounts_res = $conn->query("SELECT id, account_name, bank_name, account_number, current_balance FROM bank_accounts WHERE account_type = 'Bank' ORDER BY account_name ASC");
$bank_accounts_arr = [];
while ($ba = $bank_accounts_res->fetch_assoc()) {
    $bank_accounts_arr[] = $ba;
}

$selected_account = null;
if ($account_id > 0) {
    foreach ($bank_accounts_arr as $ba) {
        if ($ba['id'] == $account_id) { $selected_account = $ba; break; }
    }
}
// Default to first account if none selected
if (!$selected_account && !empty($bank_accounts_arr)) {
    $selected_account = $bank_accounts_arr[0];
    $account_id = $selected_account['id'];
}

$transactions = [];
$total_in     = 0;
$total_out    = 0;

if ($selected_account) {
    // Customer payments via this bank account
    $sql = "SELECT
                cl.transaction_date AS txn_date,
                cl.description,
                CASE WHEN cl.credit > 0 THEN 'IN' ELSE 'OUT' END AS direction,
                CASE WHEN cl.credit > 0 THEN cl.credit ELSE cl.debit END AS amount,
                c.customer_name AS party,
                'Customer' AS party_type,
                cl.payment_method
            FROM customer_ledger cl
            JOIN customers c ON cl.customer_id = c.id
            WHERE cl.reference_type = 'payment'
              AND cl.bank_account_id = ?
              AND cl.transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $account_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    // Supplier payments via this bank account
    $sql = "SELECT
                sl.transaction_date AS txn_date,
                sl.description,
                CASE WHEN sl.credit > 0 THEN 'OUT' ELSE 'IN' END AS direction,
                CASE WHEN sl.credit > 0 THEN sl.credit ELSE sl.debit END AS amount,
                s.company_name AS party,
                'Supplier' AS party_type,
                sl.payment_method
            FROM supplier_ledger sl
            JOIN suppliers s ON sl.supplier_id = s.id
            WHERE sl.reference_type = 'payment'
              AND sl.bank_account_id = ?
              AND sl.transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $account_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    // Expenses paid via this bank account
    $sql = "SELECT
                e.expense_date AS txn_date,
                CONCAT(e.category, ' - ', e.subcategory, IFNULL(CONCAT(': ', e.description), '')) AS description,
                'OUT' AS direction,
                e.amount,
                'Expense' AS party,
                'Expense' AS party_type,
                e.payment_method
            FROM expenses e
            WHERE e.bank_account_id = ?
              AND e.expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $account_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    // Stock transactions via this bank account
    $sql = "SELECT
                sl.transaction_date AS txn_date,
                sl.description,
                CASE WHEN sl.movement_type = 'OUT' THEN 'IN' ELSE 'OUT' END AS direction,
                sl.amount,
                t.tank_name AS party,
                'Stock' AS party_type,
                sl.payment_method
            FROM stock_ledger sl
            JOIN tanks t ON sl.tank_id = t.id
            WHERE sl.amount > 0
              AND sl.bank_account_id = ?
              AND sl.transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $account_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();

    usort($transactions, fn($a, $b) => strcmp($a['txn_date'], $b['txn_date']));

    $total_in  = array_sum(array_column(array_filter($transactions, fn($t) => $t['direction'] === 'IN'),  'amount'));
    $total_out = array_sum(array_column(array_filter($transactions, fn($t) => $t['direction'] === 'OUT'), 'amount'));
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-university mr-1"></i> Bank Book</h1>
    <div>
        <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm mr-1" data-toggle="modal" data-target="#addAccountModal">
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Received</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?= number_format($total_in, 2) ?></div>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Paid Out</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?= number_format($total_out, 2) ?></div>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Net Flow</div>
                        <?php $net = $total_in - $total_out; ?>
                        <div class="h5 mb-0 font-weight-bold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                            Rs. <?= number_format($net, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($selected_account): ?>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Account Balance</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?= number_format($selected_account['current_balance'], 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-piggy-bank fa-2x text-gray-300"></i></div>
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
            <?php if (!empty($bank_accounts_arr)): ?>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">Bank Account</label>
                <select name="account_id" class="form-control form-control-sm">
                    <?php foreach ($bank_accounts_arr as $ba): ?>
                        <option value="<?= $ba['id'] ?>" <?= $account_id == $ba['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ba['account_name']) ?> (<?= htmlspecialchars($ba['bank_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="bankbook.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list mr-1"></i>
            Bank Transactions
            <?php if ($selected_account): ?>
                — <?= htmlspecialchars($selected_account['account_name']) ?>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($bank_accounts_arr)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                No Bank accounts found. <a href="#" data-toggle="modal" data-target="#addAccountModal">Add a Bank account</a> first and select it when recording payments.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="bankbookTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Party</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th class="text-right">Credit / In (Rs.)</th>
                        <th class="text-right">Debit / Out (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No bank transactions found for this period.</td></tr>
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
                                    <span class="badge badge-success"><i class="fas fa-arrow-down fa-xs"></i> In</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-arrow-up fa-xs"></i> Out</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($t['payment_method'] ?? 'Bank Transfer') ?></span></td>
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
                        <th class="text-right text-success">Rs. <?= number_format($total_in, 2) ?></th>
                        <th class="text-right text-danger">Rs. <?= number_format($total_out, 2) ?></th>
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
                <h5 class="modal-title"><i class="fas fa-plus-circle text-success mr-1"></i> New Bank Account</h5>
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
                    <label class="small font-weight-bold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Main Account, Salary Account">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Bank Name <span class="text-danger">*</span></label>
                    <input type="text" name="bank_name" class="form-control" required placeholder="e.g. HBL, UBL, Allied Bank">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Account Number</label>
                    <input type="text" name="account_number" class="form-control" placeholder="e.g. 1234567890 / IBAN">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Opening Balance (Rs.)</label>
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
    $('#bankbookTable').DataTable({
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ordering: true,
        order: [[0, 'asc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
