<?php
session_start();
$active_page = 'stock_adjustment';
require_once '../../includes/db.php';

$success = "";
$error = "";

$tanks = $conn->query("SELECT id, tank_name, current_stock FROM tanks ORDER BY tank_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tank_id         = intval($_POST['tank_id']);
    $adjustment_date = $_POST['adjustment_date'] ?? date('Y-m-d');
    $adjustment_type = $_POST['adjustment_type'] ?? '';
    $direction       = $_POST['direction'] ?? 'remove'; // 'add' or 'remove'
    $quantity        = floatval($_POST['quantity'] ?? 0);
    $description     = trim($_POST['description'] ?? '');

    if ($tank_id <= 0 || empty($adjustment_type) || $quantity <= 0) {
        $error = "Please fill all required fields with valid values.";
    } else {
        $tank = $conn->query("SELECT current_stock FROM tanks WHERE id = $tank_id")->fetch_assoc();
        if (!$tank) {
            $error = "Tank not found.";
        } elseif ($direction === 'remove' && $tank['current_stock'] < $quantity) {
            $error = "Insufficient stock! Available: " . number_format($tank['current_stock'], 3) . " tons.";
        } else {
            $bal_before = $tank['current_stock'];
            $bal_after  = $direction === 'add' ? $bal_before + $quantity : $bal_before - $quantity;

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO stock_adjustments (adjustment_date, tank_id, adjustment_type, quantity, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sisds", $adjustment_date, $tank_id, $adjustment_type, $quantity, $description);
                $stmt->execute();
                $adj_id = $conn->insert_id;
                $stmt->close();

                $desc = "$adjustment_type (" . ($direction === 'add' ? '+' : '-') . number_format($quantity, 3) . " tons)" . ($description ? " - $description" : "");
                $stmt2 = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, reference_id, quantity, balance_before, balance_after, description) VALUES (?, ?, 'ADJUSTMENT', 'adjustment', ?, ?, ?, ?, ?)");
                $stmt2->bind_param("isiddds", $tank_id, $adjustment_date, $adj_id, $quantity, $bal_before, $bal_after, $desc);
                $stmt2->execute();
                $stmt2->close();

                $conn->query("UPDATE tanks SET current_stock = $bal_after WHERE id = $tank_id");

                $conn->commit();
                $dir_label = $direction === 'add' ? 'added to' : 'removed from';
                $success = "Adjustment recorded: " . number_format($quantity, 3) . " tons $dir_label tank. New balance: " . number_format($bal_after, 3) . " tons.";
                $_POST = [];
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    $tanks = $conn->query("SELECT id, tank_name, current_stock FROM tanks ORDER BY tank_name ASC");
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-sliders-h mr-1"></i> Stock Adjustment</h1>
    <div>
        <a href="adjustments_list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> Adjustment History
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
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tools mr-1"></i> Adjustment Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="adjustment_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['adjustment_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank <span class="text-danger">*</span></label>
                        <select name="tank_id" class="form-control" required>
                            <option value="">-- Select Tank --</option>
                            <?php while ($t = $tanks->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>"
                                    <?= (isset($_POST['tank_id']) && $_POST['tank_id'] == $t['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['tank_name']) ?> (Stock: <?= number_format($t['current_stock'], 3) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Adjustment Type <span class="text-danger">*</span></label>
                        <select name="adjustment_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <option value="Shortage"               <?= (($_POST['adjustment_type']??'')==='Shortage')               ? 'selected':'' ?>>Shortage</option>
                            <option value="Leakage"                <?= (($_POST['adjustment_type']??'')==='Leakage')                ? 'selected':'' ?>>Leakage</option>
                            <option value="Measurement_Difference" <?= (($_POST['adjustment_type']??'')==='Measurement_Difference') ? 'selected':'' ?>>Measurement Difference</option>
                            <option value="Correction"             <?= (($_POST['adjustment_type']??'')==='Correction')             ? 'selected':'' ?>>Correction (Add)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Direction <span class="text-danger">*</span></label>
                        <div class="d-flex pt-2">
                            <div class="form-check mr-4">
                                <input class="form-check-input" type="radio" name="direction" id="dirRemove" value="remove"
                                    <?= (!isset($_POST['direction']) || $_POST['direction']==='remove') ? 'checked':'' ?>>
                                <label class="form-check-label small" for="dirRemove">Remove from Stock</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="direction" id="dirAdd" value="add"
                                    <?= (isset($_POST['direction']) && $_POST['direction']==='add') ? 'checked':'' ?>>
                                <label class="form-check-label small" for="dirAdd">Add to Stock</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">Quantity (Tons) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" required
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="small font-weight-bold">Description / Reason</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Describe the reason for adjustment"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-warning">
            <i class="fas fa-save"></i> Record Adjustment
        </button>
        <a href="adjustments_list.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
