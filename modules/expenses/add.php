<?php
session_start();
$active_page = 'expense_add';
require_once '../../config/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date    = $_POST['expense_date'];
    $category        = $_POST['category'];
    $subcategory     = $_POST['subcategory'];
    $amount          = floatval($_POST['amount'] ?? 0);
    $description     = trim($_POST['description'] ?? '');
    $payment_method  = trim($_POST['payment_method'] ?? 'Cash');
    $bank_account_id = intval($_POST['bank_account_id'] ?? 0);

    if (empty($expense_date) || empty($category) || empty($subcategory) || $amount <= 0) {
        $error = "Please fill all required fields.";
    } else {
        // If Cash is selected, ensure we use the cash account ID
        if ($payment_method === 'Cash' && $bank_account_id <= 0) {
            $cash_acc = $conn->query("SELECT id FROM bank_accounts WHERE account_type = 'Cash' LIMIT 1")->fetch_assoc();
            if ($cash_acc) $bank_account_id = $cash_acc['id'];
        }
        
        $bank_id = $bank_account_id > 0 ? $bank_account_id : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, subcategory, amount, description, payment_method, bank_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdssi", $expense_date, $category, $subcategory, $amount, $description, $payment_method, $bank_id);
            $stmt->execute();
            $stmt->close();

            // Deduct from the selected cash/bank account
            if ($bank_id) {
                $conn->query("UPDATE bank_accounts SET current_balance = current_balance - $amount WHERE id = $bank_account_id");
            }

            $conn->commit();
            $success = "Expense recorded successfully!";
            $_POST = [];
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-receipt mr-1"></i> Expense Entry</h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> Expense List
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>
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
                               value="<?= htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Category <span class="text-danger">*</span></label>
                        <select name="category" id="category" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <option value="Purchase Related" <?= (isset($_POST['category']) && $_POST['category'] === 'Purchase Related') ? 'selected' : '' ?>>Purchase Related</option>
                            <option value="Office" <?= (isset($_POST['category']) && $_POST['category'] === 'Office') ? 'selected' : '' ?>>Office Expenses</option>
                            <option value="Vehicle" <?= (isset($_POST['category']) && $_POST['category'] === 'Vehicle') ? 'selected' : '' ?>>Vehicle Expenses</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Subcategory <span class="text-danger">*</span></label>
                        <select name="subcategory" id="subcategory" class="form-control" required>
                            <option value="">-- Select Subcategory --</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount (Rs.) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash" <?= (!isset($_POST['payment_method']) || $_POST['payment_method']==='Cash') ? 'selected':'' ?>>Cash</option>
                            <option value="Bank" <?= (isset($_POST['payment_method']) && $_POST['payment_method']==='Bank') ? 'selected':'' ?>>Bank</option>
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
                                    <?= (isset($_POST['bank_account_id']) && $_POST['bank_account_id'] == $b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['bank_name'] . " - " . $b['account_name']) ?> (Bal: <?= number_format($b['current_balance'], 2) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Optional notes"
                               value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-save"></i> Save Expense</button>
        <a href="list.php" class="btn btn-secondary shadow-sm"><i class="fas fa-times"></i> Cancel</a>
    </div>
</form>

<script>
const subcategories = {
    'Purchase Related': ['Freight Charges', 'Loading Charges', 'Unloading Charges', 'Driver Expense'],
    'Office': ['Salaries', 'Utilities', 'Rent', 'Internet', 'Miscellaneous'],
    'Vehicle': ['Diesel Consumption', 'Repairs', 'Tyres', 'Maintenance']
};

document.getElementById('category').addEventListener('change', function() {
    const sel = document.getElementById('subcategory');
    const cat = this.value;
    sel.innerHTML = '<option value="">-- Select Subcategory --</option>';
    if (subcategories[cat]) {
        subcategories[cat].forEach(function(s) {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            sel.appendChild(opt);
        });
    }
});

<?php if (isset($_POST['category'])): ?>
document.getElementById('category').dispatchEvent(new Event('change'));
document.getElementById('subcategory').value = '<?= htmlspecialchars($_POST['subcategory'] ?? '', ENT_QUOTES) ?>';
<?php endif; ?>

// Filter account dropdown by payment method
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
