<?php
session_start();
$active_page = 'manual_entry';
require_once '../../includes/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sr_no        = trim($_POST['sr_no']);
    $entry_date   = $_POST['entry_date'];
    $person_name  = trim($_POST['person_name']);
    $rate_per_ton = floatval($_POST['rate_per_ton'] ?? 0);
    $quantity     = floatval($_POST['quantity'] ?? 0);
    $total_amount = $rate_per_ton * $quantity;
    $paid_amount  = floatval($_POST['paid_amount'] ?? 0);
    $description  = trim($_POST['description'] ?? '');

    if (empty($sr_no) || empty($entry_date) || empty($person_name)) {
        $error = "Please fill SR No, Date and Name.";
    } else {
        $stmt = $conn->prepare("INSERT INTO manual_entries (sr_no, entry_date, person_name, rate_per_ton, quantity, total_amount, paid_amount, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdddss", $sr_no, $entry_date, $person_name, $rate_per_ton, $quantity, $total_amount, $paid_amount, $description);
        $stmt->execute();
        $stmt->close();
        $success = "Manual entry saved successfully!";
        $_POST = [];
    }
}

$max_sr = $conn->query("SELECT COALESCE(MAX(CAST(sr_no AS UNSIGNED)),0)+1 AS next_sr FROM manual_entries")->fetch_assoc();
$next_sr = str_pad($max_sr['next_sr'], 4, '0', STR_PAD_LEFT);

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Manual Entry</h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> View Entries
        </a>
    </div>
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
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> Entry Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">SR No <span class="text-danger">*</span></label>
                        <input type="text" name="sr_no" class="form-control" required
                               value="<?= htmlspecialchars($_POST['sr_no'] ?? $next_sr) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['entry_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="person_name" class="form-control" required placeholder="Person name"
                               value="<?= htmlspecialchars($_POST['person_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Rate</label>
                        <input type="number" step="0.01" min="0" name="rate_per_ton" id="rate_per_ton" class="form-control"
                               value="<?= htmlspecialchars($_POST['rate_per_ton'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Quantity</label>
                        <input type="number" step="0.001" min="0" name="quantity" id="quantity" class="form-control"
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Total ($)</label>
                        <input type="text" id="total_amount" class="form-control bg-light" readonly value="0.00">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Paid Amount ($)</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" class="form-control"
                               value="<?= htmlspecialchars($_POST['paid_amount'] ?? '0') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional notes..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary btn-lg px-5 shadow">
            <i class="fas fa-save mr-1"></i> Save Entry
        </button>
        <a href="list.php" class="btn btn-secondary btn-lg px-4 shadow">
            <i class="fas fa-times mr-1"></i> Cancel
        </a>
    </div>
</form>

<script>
document.getElementById('rate_per_ton').addEventListener('input', calcTotal);
document.getElementById('quantity').addEventListener('input', calcTotal);

function calcTotal() {
    const rate = parseFloat(document.getElementById('rate_per_ton').value) || 0;
    const qty  = parseFloat(document.getElementById('quantity').value) || 0;
    document.getElementById('total_amount').value = (rate * qty).toFixed(2);
}

calcTotal();
</script>

<?php include '../../includes/footer.php'; ?>
