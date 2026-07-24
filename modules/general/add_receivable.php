<?php
session_start();
$active_page = 'general_receivable';
require_once '../../includes/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id  = intval($_POST['account_id'] ?? 0);
    $txn_date    = $_POST['txn_date'];
    $amount      = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($account_id <= 0 || empty($txn_date) || $amount <= 0) {
        $error = "Please fill all required fields.";
    } else {
        $conn->begin_transaction();
        try {
            $current_bal = $conn->query("SELECT balance FROM personal_accounts WHERE id = $account_id")->fetch_assoc()['balance'] ?? 0;
            $new_balance = $current_bal + $amount;

            $desc = "Receivable: " . ($description ?: "Received by Younas");
            $stmt = $conn->prepare("INSERT INTO personal_ledger (account_id, transaction_date, description, debit, credit, balance, reference_type, payment_method, bank_account_id) VALUES (?, ?, ?, 0, ?, ?, 'receivable', 'Cash', NULL)");
            $stmt->bind_param("issdd", $account_id, $txn_date, $desc, $amount, $new_balance);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();

            $conn->query("UPDATE personal_accounts SET balance = $new_balance WHERE id = $account_id");

            $conn->commit();
            $success = "Receivable of $ " . number_format($amount, 2) . " recorded successfully!";
            $_POST = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$parties = $conn->query("SELECT id, person_name, balance FROM personal_accounts ORDER BY person_name ASC");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-dollar-sign mr-1"></i> Add Receivable</h1>
    <div>
        <a href="parties.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Receivable Details</h6>
            <small class="text-muted">Record money a party owes you (balance increases)</small>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Party <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-control" required>
                            <option value="">-- Select Party --</option>
                            <?php while ($p = $parties->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>" <?= (($_POST['account_id'] ?? ($_GET['party_id'] ?? '')) == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['person_name']) ?> (Bal: $ <?= number_format($p['balance'], 2) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="txn_date" class="form-control" required value="<?= htmlspecialchars($_POST['txn_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Optional notes" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-success shadow-sm"><i class="fas fa-save"></i> Save Receivable</button>
        <a href="parties.php" class="btn btn-secondary shadow-sm"><i class="fas fa-times"></i> Cancel</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>