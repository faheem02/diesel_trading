<?php
session_start();
$active_page = 'sale_return';
require_once '../../config/db.php';

$success  = "";
$error    = "";
$sale     = null;

// Load sale if selected
if (isset($_GET['sale_id'])) {
    $sid = intval($_GET['sale_id']);
    $q = $conn->prepare("SELECT cs.id, cs.invoice_no, cs.sale_date, cs.customer_name,
                          cs.quantity, cs.net_amount, cs.rate_per_ton
                          FROM customer_sales cs WHERE cs.id = ?");
    $q->bind_param("i", $sid);
    $q->execute();
    $sale = $q->get_result()->fetch_assoc();
    $q->close();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id        = intval($_POST['sale_id']);
    $return_date    = $_POST['return_date'];
    $qty_returned   = floatval($_POST['quantity_returned'] ?? 0);
    $rate_per_ton   = floatval($_POST['rate_per_ton'] ?? 0);
    $return_amount  = $qty_returned * $rate_per_ton;
    $reason         = trim($_POST['reason'] ?? '');

    if (empty($return_date) || $qty_returned <= 0 || $rate_per_ton <= 0) {
        $error = "Please fill all required fields with valid values.";
    } else {
        $conn->begin_transaction();
        try {
            // Insert return record
            $stmt = $conn->prepare("INSERT INTO sale_returns
                (sale_id, return_date, quantity_returned, rate_per_ton, return_amount, reason)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isddds", $sale_id, $return_date, $qty_returned, $rate_per_ton, $return_amount, $reason);
            $stmt->execute();
            $stmt->close();

            // Reduce quantity and amounts on the sale
            $conn->query("UPDATE customer_sales SET
                quantity    = GREATEST(quantity - $qty_returned, 0),
                total_amount= GREATEST(total_amount - $return_amount, 0),
                net_amount  = GREATEST(net_amount - $return_amount, 0)
                WHERE id = $sale_id");

            // Update customer ledger if sale linked to a customer
            $sale_row = $conn->query("SELECT customer_id, invoice_no FROM customer_sales WHERE id = $sale_id")->fetch_assoc();
            if ($sale_row && $sale_row['customer_id']) {
                $cid = intval($sale_row['customer_id']);
                $inv = $conn->real_escape_string($sale_row['invoice_no']);
                $d   = $conn->real_escape_string($return_date);
                $desc = "Sale Return - Invoice #$inv";
                $conn->query("INSERT INTO customer_ledger
                    (customer_id, transaction_date, description, debit, credit, balance, reference_type)
                    VALUES ($cid, '$d', '$desc', 0, $return_amount, 0, 'return')");
                $eid = $conn->insert_id;
                $bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM customer_ledger WHERE customer_id = $cid")->fetch_assoc()['bal'];
                $conn->query("UPDATE customer_ledger SET balance = $bal WHERE id = $eid");
                $conn->query("UPDATE customers SET balance = $bal WHERE id = $cid");
            }

            $conn->commit();
            $success = "Sale return recorded successfully!";
            $sale = null;
            $_POST = [];
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$sales = $conn->query("SELECT cs.id, cs.invoice_no, cs.sale_date, cs.customer_name
                        FROM customer_sales cs ORDER BY cs.sale_date DESC, cs.id DESC");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-undo-alt mr-1"></i> Sale Return</h1>
    <div>
        <a href="returns_list.php" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm mr-1">
            <i class="fas fa-list"></i> Return List
        </a>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Sales
        </a>
    </div>
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

<div class="row">
    <div class="col-lg-8">
        <!-- Select Invoice -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Select Sale Invoice</h6>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2 flex-grow-1">
                        <select name="sale_id" class="form-control w-100" required>
                            <option value="">-- Select Invoice --</option>
                            <?php while ($row = $sales->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>"
                                    <?= (isset($_GET['sale_id']) && $_GET['sale_id'] == $row['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['invoice_no']) ?> —
                                    <?= htmlspecialchars($row['customer_name']) ?> —
                                    <?= htmlspecialchars($row['sale_date']) ?>
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

        <?php if ($sale): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-file-invoice mr-1"></i> Invoice #<?= htmlspecialchars($sale['invoice_no']) ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Customer</small>
                        <strong><?= htmlspecialchars($sale['customer_name']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Sale Date</small>
                        <strong><?= htmlspecialchars($sale['sale_date']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Total Quantity</small>
                        <strong><?= number_format($sale['quantity'], 3) ?> Tons</strong>
                    </div>
                </div>
                <hr>
                <form method="POST">
                    <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Return Date <span class="text-danger">*</span></label>
                                <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Quantity Returned (Ton) <span class="text-danger">*</span></label>
                                <input type="number" step="0.001" min="0.001" name="quantity_returned" class="form-control"
                                       max="<?= $sale['quantity'] ?>" required>
                                <small class="text-muted">Max: <?= number_format($sale['quantity'], 3) ?> Tons</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Rate Per Ton ($) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="rate_per_ton" class="form-control"
                                       value="<?= number_format($sale['rate_per_ton'], 2, '.', '') ?>" required>
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

    <!-- Recent Returns sidebar -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history mr-1"></i> Recent Returns</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $recent = $conn->query("SELECT sr.*, cs.invoice_no, cs.customer_name
                                            FROM sale_returns sr
                                            JOIN customer_sales cs ON sr.sale_id = cs.id
                                            ORDER BY sr.created_at DESC LIMIT 5");
                    if ($recent && $recent->num_rows > 0):
                        while ($r = $recent->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($r['invoice_no']) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($r['return_date']) ?></small>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($r['customer_name']) ?></small><br>
                            <small>Qty: <?= number_format($r['quantity_returned'], 3) ?> Ton |
                                   $ <?= number_format($r['return_amount'], 2) ?></small>
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
