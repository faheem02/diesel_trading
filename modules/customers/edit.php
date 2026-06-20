<?php
session_start();
$active_page = 'customer_list';
require_once '../../config/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$customer = $conn->query("SELECT * FROM customers WHERE id = $id")->fetch_assoc();
if (!$customer) {
    header("Location: list.php");
    exit;
}

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $mobile        = trim($_POST['mobile'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $credit_limit  = floatval($_POST['credit_limit'] ?? 0);

    if (empty($customer_name)) {
        $error = "Customer name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE customers SET customer_name = ?, mobile = ?, address = ?, credit_limit = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssdi", $customer_name, $mobile, $address, $credit_limit, $id);
        if ($stmt->execute()) {
            $success = "Customer updated successfully!";
            $customer['customer_name'] = $customer_name;
            $customer['mobile'] = $mobile;
            $customer['address'] = $address;
            $customer['credit_limit'] = $credit_limit;
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-edit mr-1"></i> Edit Customer</h1>
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
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Customer Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['customer_name'] ?? $customer['customer_name']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Mobile Number</label>
                        <input type="text" name="mobile" class="form-control"
                               value="<?= htmlspecialchars($_POST['mobile'] ?? $customer['mobile'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="small font-weight-bold">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? $customer['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Opening Balance ($)</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="<?= number_format($customer['opening_balance'], 2) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Current Balance ($)</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="<?= number_format($customer['balance'], 2) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Credit Limit ($)</label>
                        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control"
                               value="<?= htmlspecialchars($_POST['credit_limit'] ?? $customer['credit_limit'] ?? '0') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Customer
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
