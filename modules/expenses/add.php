<?php
session_start();
$active_page = 'expense_add';
require_once '../../includes/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date    = $_POST['expense_date'];
    $expense_type    = trim($_POST['expense_type'] ?? '');
    $amount          = floatval($_POST['amount'] ?? 0);
    $description     = trim($_POST['description'] ?? '');

    if (empty($expense_date) || empty($expense_type) || $amount <= 0) {
        $error = "Please fill all required fields.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, subcategory, amount, description, payment_method, bank_account_id) VALUES (?, ?, '', ?, ?, 'Cash', 0)");
            $stmt->bind_param("ssds", $expense_date, $expense_type, $amount, $description);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = "Expense recorded successfully!";
            $_POST = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

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
                        <label class="small font-weight-bold">Expense Type <span class="text-danger">*</span></label>
                        <select name="expense_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <option value="Fuel" <?= (($_POST['expense_type'] ?? '') === 'Fuel') ? 'selected' : '' ?>>Fuel Expense</option>
                            <option value="Driver" <?= (($_POST['expense_type'] ?? '') === 'Driver') ? 'selected' : '' ?>>Driver Expense</option>
                            <option value="Maintenance" <?= (($_POST['expense_type'] ?? '') === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Toll Tax" <?= (($_POST['expense_type'] ?? '') === 'Toll Tax') ? 'selected' : '' ?>>Toll Tax</option>
                            <option value="Office" <?= (($_POST['expense_type'] ?? '') === 'Office') ? 'selected' : '' ?>>Office Expense</option>
                            <option value="Other" <?= (($_POST['expense_type'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
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

<?php include '../../includes/footer.php'; ?>
