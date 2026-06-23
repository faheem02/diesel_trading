<?php
session_start();
$active_page = 'general_ledger';
require_once '../../includes/db.php';

$success = "";
$error = "";

// Handle Personal Account add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_personal_account') {
            $person_name = trim($_POST['person_name']);
            $mobile = trim($_POST['mobile'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $opening_balance = floatval($_POST['opening_balance'] ?? 0);
            
            if (empty($person_name)) {
                $error = "Person name is required.";
            } else {
                // Check if person already exists
                $check = $conn->prepare("SELECT id FROM personal_accounts WHERE person_name = ?");
                $check->bind_param("s", $person_name);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Person already exists!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO personal_accounts (person_name, mobile, address, balance) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssd", $person_name, $mobile, $address, $opening_balance);
                    if ($stmt->execute()) {
                        $account_id = $conn->insert_id;
                        
                        // Add opening balance entry in ledger if balance > 0
                        if ($opening_balance > 0) {
                            $desc = "Opening Balance";
                            $stmt2 = $conn->prepare("INSERT INTO personal_ledger (account_id, transaction_date, description, debit, credit, balance, reference_type) VALUES (?, CURDATE(), ?, ?, 0, ?, 'opening_balance')");
                            $stmt2->bind_param("isdd", $account_id, $desc, $opening_balance, $opening_balance);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                        
                        $success = "Personal account added successfully!";
                    } else {
                        $error = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                }
                $check->close();
            }
        } elseif ($_POST['action'] === 'add_payment') {
            $account_id = intval($_POST['account_id']);
            $payment_date = $_POST['payment_date'];
            $payment_type = $_POST['payment_type']; // 'received' or 'given'
            $amount = floatval($_POST['amount']);
            $description = trim($_POST['description']);
            $payment_method = trim($_POST['payment_method'] ?? 'Cash');
            $bank_account_id = intval($_POST['bank_account_id'] ?? 0);
            
            if ($account_id <= 0 || empty($payment_date) || $amount <= 0) {
                $error = "Please fill all required fields.";
            } else {
                // Get current balance
                $bal_query = $conn->query("SELECT balance FROM personal_accounts WHERE id = $account_id");
                $current_bal = $bal_query->fetch_assoc()['balance'] ?? 0;
                
                // Debit = Received (money comes in), Credit = Given (money goes out)
                $debit = ($payment_type === 'received') ? $amount : 0;
                $credit = ($payment_type === 'given') ? $amount : 0;
                $new_balance = $current_bal + $debit - $credit;
                
                $conn->begin_transaction();
                try {
                    // Insert into personal_ledger
                    $stmt = $conn->prepare("INSERT INTO personal_ledger (account_id, transaction_date, description, debit, credit, balance, reference_type, payment_method, bank_account_id) VALUES (?, ?, ?, ?, ?, ?, 'payment', ?, ?)");
                    $stmt->bind_param("issdddsi", $account_id, $payment_date, $description, $debit, $credit, $new_balance, $payment_method, $bank_account_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update personal_accounts balance
                    $conn->query("UPDATE personal_accounts SET balance = $new_balance WHERE id = $account_id");
                    
                    // If bank account selected, update bank balance
                    if ($bank_account_id > 0 && $payment_type === 'received') {
                        $conn->query("UPDATE bank_accounts SET current_balance = current_balance + $amount WHERE id = $bank_account_id");
                    } elseif ($bank_account_id > 0 && $payment_type === 'given') {
                        $conn->query("UPDATE bank_accounts SET current_balance = current_balance - $amount WHERE id = $bank_account_id");
                    }
                    
                    $conn->commit();
                    $success = "Payment recorded successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle DELETE
if (isset($_GET['delete_account']) && is_numeric($_GET['delete_account'])) {
    $id = intval($_GET['delete_account']);
    $stmt = $conn->prepare("DELETE FROM personal_accounts WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Account deleted successfully!";
    } else {
        $error = "Cannot delete account. It may have linked records.";
    }
    $stmt->close();
}

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

// Personal Accounts (External) ledger entries
if (matchesFilter($type_filter, ['', 'personal_payment', 'opening_balance'])) {
    $sql = "SELECT pl.transaction_date AS txn_date, 
                   CONCAT('[Personal] ', pl.description) AS description, 
                   pl.debit, pl.credit,
                   pa.person_name AS party, 'Personal Account' AS party_type, 
                   pl.reference_type, pl.reference_id,
                   ba.account_name AS account_ref, ba.account_type
            FROM personal_ledger pl
            JOIN personal_accounts pa ON pl.account_id = pa.id
            LEFT JOIN bank_accounts ba ON pl.bank_account_id = ba.id
            WHERE pl.transaction_date BETWEEN ? AND ?";
    $params = [$from_date, $to_date];
    $types = "ss";
    if ($type_filter) {
        $sql .= " AND pl.reference_type = ?";
        $params[] = $type_filter;
        $types .= "s";
    }
    $sql .= " ORDER BY pl.transaction_date ASC, pl.id ASC";
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

// Get all Personal Accounts for dropdown
$personal_accounts = $conn->query("SELECT id, person_name, balance FROM personal_accounts ORDER BY person_name ASC");
$bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, account_type, current_balance FROM bank_accounts ORDER BY account_type ASC, account_name ASC");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> General Ledger</h1>
    <div>
        <button class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#addPersonalAccountModal">
            <i class="fas fa-user-plus"></i> Add Personal Account
        </button>
        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addPaymentModal">
            <i class="fas fa-money-bill-wave"></i> Add Payment
        </button>
        <button onclick="openPrint()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print fa-sm"></i> Print
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Debit ($)</div>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Credit ($)</div>
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
                    <option value="personal_payment" <?= $type_filter === 'personal_payment' ? 'selected' : '' ?>>Personal Payment</option>
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
                        <th class="text-right">Debit ($)</th>
                        <th class="text-right">Credit ($)</th>
                        <th class="text-right">Balance ($)</th>
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
                                    'personal_payment' => ['Personal Payment', 'purple'],
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

<!-- Add Personal Account Modal -->
<div class="modal fade" id="addPersonalAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_personal_account">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus mr-1"></i> Add Personal Account</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Person Name <span class="text-danger">*</span></label>
                        <input type="text" name="person_name" class="form-control" required placeholder="Enter person name">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Mobile</label>
                        <input type="text" name="mobile" class="form-control" placeholder="Mobile number">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Address"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Opening Balance ($)</label>
                        <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0">
                        <small class="text-muted">Initial balance if this person already owes or is owed money</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_payment">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave mr-1"></i> Add Personal Payment</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Person <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-control" required>
                            <option value="">-- Select Person --</option>
                            <?php while ($p = $personal_accounts->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['person_name']) ?> 
                                    (Balance: <?= number_format($p['balance'], 2) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Type <span class="text-danger">*</span></label>
                        <select name="payment_type" class="form-control" required>
                            <option value="received">Received (Person paid you)</option>
                            <option value="given">Given (You paid person)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required placeholder="Enter amount">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Payment description">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Bank/Cash Account</label>
                        <select name="bank_account_id" class="form-control">
                            <option value="">-- Select Account --</option>
                            <?php 
                            $bank_accounts->data_seek(0);
                            while ($b = $bank_accounts->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>">
                                    [<?= $b['account_type'] ?>] <?= htmlspecialchars($b['account_name']) ?>
                                    <?php if ($b['bank_name']): ?> - <?= htmlspecialchars($b['bank_name']) ?><?php endif; ?>
                                    (Bal: <?= number_format($b['current_balance'], 2) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Payment</button>
                </div>
            </form>
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

function openPrint() {
    var params = new URLSearchParams(window.location.search);
    window.open('general_ledger_print.php?' + params.toString(), '_blank');
}
</script>

<div id="print-header" style="display:none;">
    <h4>General Ledger</h4>
    <p>Period: <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?>
       <?= $type_filter ? ' | Type: ' . htmlspecialchars(ucfirst($type_filter)) : '' ?>
    </p>
    <p>Printed on: <?= date('d M Y, h:i A') ?></p>
</div>

<?php include '../../includes/footer.php'; ?>