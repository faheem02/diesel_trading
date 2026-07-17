<?php
session_start();
$active_page = 'expense_list';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$expense = $conn->query("SELECT * FROM expenses WHERE id = $id")->fetch_assoc();
if (!$expense) { header("Location: list.php"); exit; }

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date    = $_POST['expense_date'];
    $expense_type    = trim($_POST['expense_type'] ?? '');
    $amount          = floatval($_POST['amount'] ?? 0);
    $description     = trim($_POST['description'] ?? '');
    $payment_method  = trim($_POST['payment_method'] ?? 'Cash');
    $bank_account_id = intval($_POST['bank_account_id'] ?? 0);

    if (empty($expense_date) || empty($expense_type) || $amount <= 0) {
        $error = "Please fill all required fields.";
    } else {
        $old_amount = $expense['amount'];
        $old_bank_id = $expense['bank_account_id'];

        if ($payment_method === 'Cash' && $bank_account_id <= 0) {
            $cash_acc = $conn->query("SELECT id FROM bank_accounts WHERE account_type = 'Cash' LIMIT 1")->fetch_assoc();
            if ($cash_acc) $bank_account_id = $cash_acc['id'];
        }

        $bank_id = $bank_account_id > 0 ? $bank_account_id : null;

        $conn->begin_transaction();
        try {
            if ($old_bank_id) {
                $conn->query("UPDATE bank_accounts SET current_balance = current_balance + $old_amount WHERE id = $old_bank_id");
            }

            $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, category=?, subcategory='', amount=?, description=?, payment_method=?, bank_account_id=? WHERE id=?");
            $stmt->bind_param("sssdsii", $expense_date, $expense_type, $amount, $description, $payment_method, $bank_id, $id);
            $stmt->execute();
            $stmt->close();

            if ($bank_id) {
                $conn->query("UPDATE bank_accounts SET current_balance = current_balance - $amount WHERE id = $bank_account_id");
            }

            $conn->commit();
            header("Location: list.php?updated=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$bank_accounts = $conn->query("SELECT id, account_name, bank_name, account_number, account_type, current_balance FROM bank_accounts ORDER BY account_type ASC, account_name ASC");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Edit Expense</h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Expense Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['expense_date'] ?? $expense['expense_date']) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Expense Type <span class="text-danger">*</span></label>
                        <select name="expense_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <?php
                            $types = ['Fuel', 'Driver', 'Maintenance', 'Toll Tax', 'Office', 'Other'];
                            $current = $_POST['expense_type'] ?? $expense['category'];
                            foreach ($types as $t):
                            ?>
                                <option value="<?= $t ?>" <?= ($current === $t) ? 'selected' : '' ?>><?= $t ?> Expense</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                               value="<?= htmlspecialchars($_POST['amount'] ?? $expense['amount']) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Optional notes"
                               value="<?= htmlspecialchars($_POST['description'] ?? $expense['description']) ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash" <?= (($_POST['payment_method'] ?? $expense['payment_method']) === 'Cash') ? 'selected' : '' ?>>Cash</option>
                            <option value="Bank" <?= (($_POST['payment_method'] ?? $expense['payment_method']) === 'Bank') ? 'selected' : '' ?>>Bank</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" id="bank_account_group">
                    <div class="form-group">
                        <label class="small font-weight-bold">Select Bank Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="form-control">
                            <option value="">-- Select Bank --</option>
                            <?php if ($bank_accounts && $bank_accounts->num_rows > 0):
                                $bank_accounts->data_seek(0);
                                while ($b = $bank_accounts->fetch_assoc()):
                                    if($b['account_type'] !== 'Bank') continue; ?>
                                <option value="<?= $b['id'] ?>"
                                    <?= (($_POST['bank_account_id'] ?? $expense['bank_account_id']) == $b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['bank_name'] . " - " . $b['account_name']) ?> (Bal: <?= number_format($b['current_balance'], 2) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-save"></i> Update Expense</button>
        <a href="list.php" class="btn btn-secondary shadow-sm"><i class="fas fa-times"></i> Cancel</a>
    </div>
</form>

<script>
const pmSelect = document.getElementById('payment_method');
const bankGroup = document.getElementById('bank_account_group');
const bankSelect = document.getElementById('bank_account_id');

function togglePaymentFields() {
    if (pmSelect.value === 'Bank') {
        bankGroup.style.display = '';
        bankSelect.required = true;
    } else {
        bankGroup.style.display = 'none';
        bankSelect.required = false;
        bankSelect.value = '';
    }
}

pmSelect.addEventListener('change', togglePaymentFields);
togglePaymentFields();
</script>

<?php include '../../includes/footer.php'; ?>
