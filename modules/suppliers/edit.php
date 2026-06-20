<?php
session_start();
$active_page = 'supplier_list';
require_once '../../config/db.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    header("Location: list.php");
    exit;
}

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name   = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone          = trim($_POST['mobile'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $ntn_cnic       = trim($_POST['ntn_cnic'] ?? '');

    if (empty($company_name)) {
        $error = "Company name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE suppliers SET company_name = ?, contact_person = ?, phone = ?, address = ?, ntn_cnic = ?, updated_at = CURRENT_DATE WHERE id = ?");
        if (!$stmt) {
            $error = "Database error: " . $conn->error . ". Make sure you have run the migration script to add new columns.";
        } else {
        $stmt->bind_param("sssssi", $company_name, $contact_person, $phone, $address, $ntn_cnic, $id);

        if ($stmt->execute()) {
            $success = "Supplier updated successfully!";
            $supplier['company_name'] = $company_name;
            $supplier['contact_person'] = $contact_person;
            $supplier['phone'] = $phone;
            $supplier['address'] = $address;
            $supplier['ntn_cnic'] = $ntn_cnic;
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Edit Supplier</h1>
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
                               value="<?= htmlspecialchars($_POST['company_name'] ?? $supplier['company_name']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control"
                               value="<?= htmlspecialchars($_POST['contact_person'] ?? $supplier['contact_person']) ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" name="mobile" class="form-control" required
                               value="<?= htmlspecialchars($_POST['mobile'] ?? $supplier['phone']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">NTN / CNIC</label>
                        <input type="text" name="ntn_cnic" class="form-control"
                               value="<?= htmlspecialchars($_POST['ntn_cnic'] ?? $supplier['ntn_cnic']) ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? $supplier['address']) ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Opening Balance ($)</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="<?= number_format($supplier['opening_balance'], 2) ?>">
                        <small class="text-muted">Opening balance cannot be changed after creation.</small>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Current Balance ($)</label>
                        <input type="text" class="form-control bg-light font-weight-bold" readonly
                               value="<?= number_format($supplier['balance'], 2) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Supplier
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
