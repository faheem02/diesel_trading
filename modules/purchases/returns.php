<?php
session_start();
$active_page = 'purchase_return';
require_once '../../config/db.php';

$success = "";
$error = "";
$purchase = null;

if (isset($_GET['purchase_id'])) {
    $pid = intval($_GET['purchase_id']);
    $q = $conn->prepare("SELECT p.id, p.invoice_no, p.purchase_date, s.company_name,
                         p.diesel_quantity, p.net_purchase_cost
                         FROM purchases p JOIN suppliers s ON p.supplier_id = s.id
                         WHERE p.id = ?");
    $q->bind_param("i", $pid);
    $q->execute();
    $purchase = $q->get_result()->fetch_assoc();
    $q->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_id    = intval($_POST['purchase_id']);
    $return_date    = $_POST['return_date'];
    $qty_returned   = floatval($_POST['quantity_returned']);
    $rate_per_ton   = floatval($_POST['rate_per_ton']);
    $return_amount  = $qty_returned * $rate_per_ton;
    $reason         = trim($_POST['reason'] ?? '');

    if (empty($return_date) || $qty_returned <= 0 || $rate_per_ton <= 0) {
        $error = "Please fill all required fields with valid values.";
    } else {
        $stmt = $conn->prepare("INSERT INTO purchase_returns
            (purchase_id, return_date, quantity_returned, rate_per_ton, return_amount, reason)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isddds", $purchase_id, $return_date, $qty_returned, $rate_per_ton, $return_amount, $reason);

        if ($stmt->execute()) {
            $success = "Purchase return recorded successfully!";
            $purchase = null;
            $_POST = [];
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$purchases = $conn->query("SELECT p.id, p.invoice_no, p.purchase_date, s.company_name
                           FROM purchases p JOIN suppliers s ON p.supplier_id = s.id
                           ORDER BY p.purchase_date DESC");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-undo-alt mr-1"></i> Purchase Return</h1>
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

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Select Purchase Invoice</h6>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2 flex-grow-1">
                        <select name="purchase_id" class="form-control w-100" required>
                            <option value="">-- Select Invoice --</option>
                            <?php while ($row = $purchases->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>"
                                    <?= (isset($_GET['purchase_id']) && $_GET['purchase_id'] == $row['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['invoice_no']) ?> -
                                    <?= htmlspecialchars($row['company_name']) ?> -
                                    <?= htmlspecialchars($row['purchase_date']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Load Invoice
                    </button>
                </form>
            </div>
        </div>

        <?php if ($purchase): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Invoice #<?= htmlspecialchars($purchase['invoice_no']) ?></h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Supplier</small>
                        <strong><?= htmlspecialchars($purchase['company_name']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Purchase Date</small>
                        <strong><?= htmlspecialchars($purchase['purchase_date']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Total Quantity</small>
                        <strong><?= number_format($purchase['diesel_quantity'], 3) ?> Tons</strong>
                    </div>
                </div>

                <hr>

                <form method="POST">
                    <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Return Date <span class="text-danger">*</span></label>
                                <input type="date" name="return_date" class="form-control"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Quantity Returned (Ton) <span class="text-danger">*</span></label>
                                <input type="number" step="0.001" min="0.001" name="quantity_returned"
                                       class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Rate Per Ton <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="rate_per_ton"
                                       class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="small font-weight-bold">Reason for Return</label>
                                <textarea name="reason" class="form-control" rows="2" placeholder="Optional reason for return"></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-undo-alt"></i> Record Return
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history mr-1"></i> Recent Returns</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $recent = $conn->query("SELECT pr.*, p.invoice_no FROM purchase_returns pr
                                            JOIN purchases p ON pr.purchase_id = p.id
                                            ORDER BY pr.created_at DESC LIMIT 5");
                    if ($recent && $recent->num_rows > 0):
                        while ($r = $recent->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($r['invoice_no']) ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($r['return_date']) ?></small>
                                </div>
                                <small>Qty: <?= number_format($r['quantity_returned'], 3) ?> Tons |
                                       Amount: <?= number_format($r['return_amount'], 2) ?></small>
                            </div>
                        <?php endwhile;
                    else: ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                            No returns recorded yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
