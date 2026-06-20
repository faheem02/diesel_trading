<?php
session_start();
$active_page = 'tank_list';
require_once '../../config/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $tank_name = trim($_POST['tank_name']);
        $capacity  = floatval($_POST['capacity'] ?? 0);
        $location  = trim($_POST['location'] ?? '');
        $opening_stock = floatval($_POST['opening_stock'] ?? 0);

        if (empty($tank_name)) {
            $error = "Tank name is required.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO tanks (tank_name, capacity, opening_stock, location, current_stock) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdssd", $tank_name, $capacity, $opening_stock, $location, $opening_stock);
                $stmt->execute();
                $tank_id = $conn->insert_id;
                $stmt->close();

                // Record opening stock in stock_ledger so all reports are accurate
                if ($opening_stock > 0) {
                    $today = date('Y-m-d');
                    $desc  = "Opening stock for $tank_name";
                    $sl = $conn->prepare("INSERT INTO stock_ledger (tank_id, transaction_date, movement_type, reference_type, quantity, balance_before, balance_after, description) VALUES (?, ?, 'IN', 'opening_balance', ?, 0, ?, ?)");
                    $sl->bind_param("isdds", $tank_id, $today, $opening_stock, $opening_stock, $desc);
                    $sl->execute();
                    $sl->close();
                }

                $conn->commit();
                $success = "Tank added successfully with opening stock: " . number_format($opening_stock, 3) . " tons!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'edit') {
        $id        = intval($_POST['id']);
        $tank_name = trim($_POST['tank_name']);
        $capacity  = floatval($_POST['capacity'] ?? 0);
        $location  = trim($_POST['location'] ?? '');

        if (empty($tank_name) || $id <= 0) {
            $error = "Invalid input.";
        } else {
            $stmt = $conn->prepare("UPDATE tanks SET tank_name = ?, capacity = ?, location = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $tank_name, $capacity, $location, $id);
            if ($stmt->execute()) {
                $success = "Tank updated successfully!";
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM tanks WHERE id = ?");
    $stmt->bind_param("i", $did);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "Tank deleted successfully!";
    } else {
        $error = "Cannot delete tank. It may have linked records.";
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM tanks ORDER BY tank_name ASC");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-oil-can mr-1"></i> Tank Wise Stock</h1>
    <div>
        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addTankModal">
            <i class="fas fa-plus-circle"></i> Add New Tank
        </button>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
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
        <h6 class="m-0 font-weight-bold text-primary">All Tanks</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tanksTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Tank Name</th>
                        <th>Capacity (Tons)</th>
                        <th>Opening Stock (Tons)</th>
                        <th>Location</th>
                        <th>Current Stock (Tons)</th>
                        <th>Stock %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No tanks found. Add one to get started.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $pct = $row['capacity'] > 0 ? round(($row['current_stock'] / $row['capacity']) * 100, 1) : 0;
                            $bar_class = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['tank_name']) ?></td>
                            <td><?= number_format($row['capacity'], 3) ?></td>
                            <td><?= number_format($row['opening_stock'] ?? 0, 3) ?></td>
                            <td><?= htmlspecialchars($row['location'] ?: '-') ?></td>
                            <td class="font-weight-bold <?= $row['current_stock'] > 0 ? 'text-success' : 'text-muted' ?>">
                                <?= number_format($row['current_stock'], 3) ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 mr-2" style="height:10px;min-width:80px">
                                        <div class="progress-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <small><?= $pct ?>%</small>
                                </div>
                            </td>
                            <td class="text-center" style="white-space:nowrap">
                                <button class="btn btn-sm btn-outline-primary edit-tank" title="Edit"
                                    data-id="<?= $row['id'] ?>"
                                    data-name="<?= htmlspecialchars($row['tank_name']) ?>"
                                    data-capacity="<?= $row['capacity'] ?>"
                                    data-opening-stock="<?= $row['opening_stock'] ?? 0 ?>"
                                    data-location="<?= htmlspecialchars($row['location'] ?? '') ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="tanks.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this tank?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Tank Modal -->
<div class="modal fade" id="addTankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> Add New Tank</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank Name <span class="text-danger">*</span></label>
                        <input type="text" name="tank_name" class="form-control" required placeholder="e.g. Tank A">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Capacity (Tons)</label>
                        <input type="number" step="0.001" min="0" name="capacity" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Opening Stock (Tons)</label>
                        <input type="number" step="0.001" min="0" name="opening_stock" class="form-control" value="0" placeholder="Enter initial stock">
                        <small class="text-muted">Initial stock balance for this tank</small>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Main Yard">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Tank Modal -->
<div class="modal fade" id="editTankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-pen mr-1"></i> Edit Tank</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Tank Name <span class="text-danger">*</span></label>
                        <input type="text" name="tank_name" id="edit_tank_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Capacity (Tons)</label>
                        <input type="number" step="0.001" min="0" name="capacity" id="edit_capacity" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tanksTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });

    $('.edit-tank').click(function() {
        $('#edit_id').val($(this).data('id'));
        $('#edit_tank_name').val($(this).data('name'));
        $('#edit_capacity').val($(this).data('capacity'));
        $('#edit_location').val($(this).data('location'));
        $('#editTankModal').modal('show');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>