<?php
session_start();
$active_page = 'tanker_expense_add';
require_once '../../config/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanker_id    = intval($_POST['tanker_id']);
    $expense_date = $_POST['expense_date'];
    $expense_type = $_POST['expense_type'];
    $amount       = floatval($_POST['amount'] ?? 0);
    $description  = trim($_POST['description'] ?? '');

    if ($tanker_id <= 0 || empty($expense_date) || empty($expense_type) || $amount <= 0) {
        $error = "Please fill all required fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO tanker_expenses (tanker_id, expense_date, expense_type, amount, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issds", $tanker_id, $expense_date, $expense_type, $amount, $description);
        $stmt->execute();
        $stmt->close();
        $success = "Expense recorded successfully!";
        $_POST = [];
    }
}

$tankers = $conn->query("SELECT id, tanker_number FROM tankers ORDER BY tanker_number ASC");
include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-receipt mr-1"></i> Tanker Expense Entry</h1>
    <div>
        <a href="expenses_list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
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
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tanker <span class="text-danger">*</span></label>
                        <select name="tanker_id" class="form-control" required>
                            <option value="">-- Select Tanker --</option>
                            <?php while ($t = $tankers->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>" <?= (isset($_POST['tanker_id']) && $_POST['tanker_id'] == $t['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['tanker_number']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Expense Type <span class="text-danger">*</span></label>
                        <select name="expense_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <option value="Fuel" <?= (isset($_POST['expense_type']) && $_POST['expense_type'] === 'Fuel') ? 'selected' : '' ?>>Fuel Expense</option>
                            <option value="Driver" <?= (isset($_POST['expense_type']) && $_POST['expense_type'] === 'Driver') ? 'selected' : '' ?>>Driver Expense</option>
                            <option value="Maintenance" <?= (isset($_POST['expense_type']) && $_POST['expense_type'] === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Toll Tax" <?= (isset($_POST['expense_type']) && $_POST['expense_type'] === 'Toll Tax') ? 'selected' : '' ?>>Toll Tax</option>
                            <option value="Other" <?= (isset($_POST['expense_type']) && $_POST['expense_type'] === 'Other') ? 'selected' : '' ?>>Other Charges</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-8">
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
        <a href="expenses_list.php" class="btn btn-secondary shadow-sm"><i class="fas fa-times"></i> Cancel</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
