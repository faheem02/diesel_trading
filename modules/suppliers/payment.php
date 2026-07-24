<?php
session_start();
$active_page = 'supplier_payment';
require_once '../../includes/db.php';
require_once '../../includes/ledger.php';

$success = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id     = intval($_POST['supplier_id']);
    $payment_date    = $_POST['payment_date'];
    $amount          = floatval($_POST['amount'] ?? 0);
    $direction       = $_POST['direction'] ?? 'to_supplier';
    $reference_no    = trim($_POST['reference_no'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if ($supplier_id <= 0 || empty($payment_date) || $amount <= 0) {
        $error = "Please fill all required fields with valid values.";
    } else {
        $conn->begin_transaction();
        try {
            if ($direction === 'to_supplier') {
                // We pay supplier → debit (reduces our debt), our cash/bank goes OUT
                $debit  = $amount;
                $credit = 0;
                $desc   = "Payment to supplier" . (!empty($reference_no) ? " (Ref: $reference_no)" : "") . (!empty($notes) ? " — $notes" : "");
                $bal_change = -$amount; // money leaves our account
            } else {
                // Supplier pays us → credit (reduces their claim), our cash/bank comes IN
                $debit  = 0;
                $credit = $amount;
                $desc   = "Payment from supplier" . (!empty($reference_no) ? " (Ref: $reference_no)" : "") . (!empty($notes) ? " — $notes" : "");
                $bal_change = $amount; // money enters our account
            }

            // 1. Insert into supplier_ledger
            $stmt = $conn->prepare("
                INSERT INTO supplier_ledger
                    (supplier_id, transaction_date, description, debit, credit, reference_type, bank_account_id, payment_method)
                VALUES (?, ?, ?, ?, ?, 'payment', 0, 'Cash')
            ");
            // types: i s s d d  = 5 params
            $stmt->bind_param("issdd",
                $supplier_id, $payment_date, $desc,
                $debit, $credit
            );
            $stmt->execute();
            $entry_id = $conn->insert_id;
            $stmt->close();

            // 2. Recalculate and update running balance
            $bal = $conn->query("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal
                                 FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc();
            $running = $bal['bal'];
            $conn->query("UPDATE supplier_ledger SET balance = $running WHERE id = $entry_id");
            $conn->query("UPDATE suppliers SET balance = $running WHERE id = $supplier_id");

            $conn->commit();
            $label   = $direction === 'to_supplier' ? 'paid to' : 'received from';
            $success = "Payment of $ " . number_format($amount, 2) . " $label supplier recorded successfully!";
            $_POST   = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$suppliers     = $conn->query("SELECT id, company_name, balance FROM suppliers ORDER BY company_name ASC");
include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Record Supplier Payment</h1>
    <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Payment Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php while ($s = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>"
                                    <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['company_name']) ?> (Bal: <?= number_format($s['balance'], 2) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Direction <span class="text-danger">*</span></label>
                        <div class="d-flex pt-2">
                            <div class="form-check mr-4">
                                <input class="form-check-input" type="radio" name="direction" id="dirTo" value="to_supplier"
                                    <?= (!isset($_POST['direction']) || $_POST['direction'] === 'to_supplier') ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="dirTo">We Pay Supplier</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="direction" id="dirFrom" value="from_supplier"
                                    <?= (isset($_POST['direction']) && $_POST['direction'] === 'from_supplier') ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="dirFrom">Supplier Pays Us</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Reference No.</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="Ref #"
                               value="<?= htmlspecialchars($_POST['reference_no'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional"
                               value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Record Payment
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
