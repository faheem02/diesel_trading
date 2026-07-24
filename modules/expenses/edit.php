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

    if (empty($expense_date) || empty($expense_type) || $amount <= 0) {
        $error = "Please fill all required fields.";
    } else {
        $old_amount = $expense['amount'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, category=?, subcategory='', amount=?, description=?, payment_method='Cash', bank_account_id=NULL WHERE id=?");
            $stmt->bind_param("ssdsi", $expense_date, $expense_type, $amount, $description, $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: list.php?updated=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

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
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-save"></i> Update Expense</button>
        <a href="list.php" class="btn btn-secondary shadow-sm"><i class="fas fa-times"></i> Cancel</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
