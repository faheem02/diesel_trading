<?php
session_start();
$active_page = 'opening_stock';
require_once '../../includes/db.php';

$success = "";
$error = "";

require_once '../../includes/tank_helper.php';
$tanks_arr = resolve_default_tank($conn);
$single_tank = $tanks_arr[0]; // default tank always exists (auto-created if table was empty)

$opening_set = false;
$opening_data = null;
if ($single_tank) {
    $chk = $conn->query("SELECT COUNT(*) AS cnt FROM stock_ledger WHERE reference_type = 'opening_balance' AND tank_id = " . intval($single_tank['id']));
    $opening_set = ($chk->fetch_assoc()['cnt'] > 0);
    if ($opening_set) {
        $od = $conn->query("SELECT id, quantity, rate, amount, transaction_date FROM stock_ledger WHERE reference_type = 'opening_balance' AND tank_id = " . intval($single_tank['id']) . " LIMIT 1")->fetch_assoc();
        if ($od) $opening_data = $od;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tank_id    = intval($single_tank['id']);
    $open_date  = $_POST['opening_date'] ?? date('Y-m-d');
    $quantity   = floatval($_POST['quantity'] ?? 0);
    $rate       = floatval($_POST['rate'] ?? 0);
    $value      = floatval($_POST['value'] ?? 0);

    if ($quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } else {
        if ($value <= 0 && $rate > 0) {
            $value = $quantity * $rate;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE tanks SET opening_stock = ?, current_stock = ? WHERE id = ?");
            $stmt->bind_param("ddi", $quantity, $quantity, $tank_id);
            $stmt->execute();
            $stmt->close();

            $desc = "Opening Stock — " . number_format($quantity, 3) . " tons";
            if ($rate > 0) $desc .= " @ $" . number_format($rate, 2) . "/ton";

            if ($opening_set && $opening_data) {
                $sl = $conn->prepare("UPDATE stock_ledger SET transaction_date = ?, quantity = ?, rate = ?, amount = ?, balance_after = ?, balance_after_value = ?, description = ? WHERE id = ?");
                $sl->bind_param("sddddssi", $open_date, $quantity, $rate, $value, $quantity, $value, $desc, $opening_data['id']);
                $sl->execute();
                $sl->close();
                $success = "Opening stock updated successfully!";
            } else {
                $sl = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, quantity, rate, amount, balance_before, balance_before_value, balance_after, balance_after_value, description) VALUES (?, ?, 'IN', 'opening_balance', ?, ?, ?, 0, 0, ?, ?, ?)");
                $sl->bind_param("isddddds", $tank_id, $open_date, $quantity, $rate, $value, $quantity, $value, $desc);
                $sl->execute();
                $sl->close();
                $success = "Opening stock set successfully!";
            }

            $conn->commit();
            $_POST = [];
            $opening_set = true;

            $od = $conn->query("SELECT id, quantity, rate, amount, transaction_date FROM stock_ledger WHERE reference_type = 'opening_balance' AND tank_id = $tank_id LIMIT 1")->fetch_assoc();
            if ($od) $opening_data = $od;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }

        $tanks_arr = resolve_default_tank($conn);
        $single_tank = $tanks_arr[0];
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-database mr-1"></i> Opening Stock</h1>
    <div>
        <a href="reports/stock_report.php" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm">
            <i class="fas fa-chart-line"></i> Stock Report
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

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-edit mr-1"></i>
            <?= $opening_set ? 'Update Opening Stock' : 'Set Opening Stock' ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($opening_set && $opening_data): ?>
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card border-left-primary py-2 px-3">
                    <label class="small font-weight-bold text-muted mb-1">Date</label>
                    <p class="font-weight-bold mb-0"><?= htmlspecialchars($opening_data['transaction_date'] ?? '-') ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-success py-2 px-3">
                    <label class="small font-weight-bold text-muted mb-1">Quantity (Tons)</label>
                    <p class="font-weight-bold mb-0"><?= number_format($opening_data['quantity'] ?? 0, 3) ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-info py-2 px-3">
                    <label class="small font-weight-bold text-muted mb-1">Rate ($/ton)</label>
                    <p class="font-weight-bold mb-0">$ <?= number_format($opening_data['rate'] ?? 0, 2) ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-warning py-2 px-3">
                    <label class="small font-weight-bold text-muted mb-1">Total Value ($)</label>
                    <p class="font-weight-bold mb-0">$ <?= number_format($opening_data['amount'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        <hr>
        <?php endif; ?>

        <form method="POST" id="openingForm">
            <input type="hidden" name="tank_id" value="<?= $single_tank['id'] ?>">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="opening_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['opening_date'] ?? ($opening_data['transaction_date'] ?? date('Y-m-d'))) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Quantity (Tons) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" id="qty" class="form-control" required
                               value="<?= htmlspecialchars($_POST['quantity'] ?? ($opening_data['quantity'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Rate per Ton ($)</label>
                        <input type="number" step="0.01" min="0" name="rate" id="rate" class="form-control"
                               value="<?= htmlspecialchars($_POST['rate'] ?? ($opening_data['rate'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Stock Value ($)</label>
                        <input type="number" step="0.01" min="0" name="value" id="total_value" class="form-control"
                               value="<?= htmlspecialchars($_POST['value'] ?? ($opening_data['amount'] ?? '')) ?>">
                        <small class="text-muted">Auto: Qty × Rate</small>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mb-4">
    <button type="submit" form="openingForm" class="btn btn-primary btn-lg shadow px-5">
        <i class="fas fa-save mr-1"></i> <?= $opening_set ? 'Update Opening Stock' : 'Save Opening Stock' ?>
    </button>
</div>

<script>
document.getElementById('qty').addEventListener('input', calcValue);
document.getElementById('rate').addEventListener('input', calcValue);

function calcValue() {
    const qty   = parseFloat(document.getElementById('qty').value)   || 0;
    const rate  = parseFloat(document.getElementById('rate').value)  || 0;
    const value = qty * rate;
    if (rate > 0) {
        document.getElementById('total_value').value = value.toFixed(2);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
