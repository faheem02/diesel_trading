<?php
session_start();
$active_page = 'supplier_add';
require_once '../../config/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name   = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone          = trim($_POST['mobile'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $ntn_cnic       = trim($_POST['ntn_cnic'] ?? '');
    $opening_balance = floatval($_POST['opening_balance'] ?? 0);

    if (empty($company_name)) {
        $error = "Company name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO suppliers (company_name, contact_person, phone, address, ntn_cnic, balance, opening_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $error = "Database error: " . $conn->error . ". Make sure you have run the migration script to add new columns.";
        } else {
        $stmt->bind_param("sssssdd", $company_name, $contact_person, $phone, $address, $ntn_cnic, $opening_balance, $opening_balance);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            if ($opening_balance != 0) {
                $ob_desc = "Opening Balance";
                $today = date('Y-m-d');
                if ($opening_balance > 0) {
                    $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($new_id, '$today', '$ob_desc', 0, $opening_balance, $opening_balance, 'opening_balance')");
                } else {
                    $pos = abs($opening_balance);
                    $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($new_id, '$today', '$ob_desc', $pos, 0, $opening_balance, 'opening_balance')");
                }
            }
            $success = "Supplier added successfully!";
            $_POST = [];
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
        }
    }
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Add New Supplier</h1>
    <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
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

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Supplier Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control"
                               value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" name="mobile" class="form-control" required
                               value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">NTN / CNIC</label>
                        <input type="text" name="ntn_cnic" class="form-control"
                               value="<?= htmlspecialchars($_POST['ntn_cnic'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Opening Balance (Rs.)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control"
                               value="<?= htmlspecialchars($_POST['opening_balance'] ?? '0') ?>">
                        <small class="text-muted">Use positive for receivable, negative for payable</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Supplier
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
