<?php
session_start();
$active_page = 'tanker_list';
require_once '../../includes/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $tanker_number  = trim($_POST['tanker_number']);
        $owner_name     = trim($_POST['owner_name']);
        $driver_name    = trim($_POST['driver_name']);
        $mobile         = trim($_POST['mobile'] ?? '');
        $capacity       = floatval($_POST['capacity'] ?? 0);
        $route_info     = trim($_POST['route_info'] ?? '');

        if (empty($tanker_number) || empty($owner_name) || empty($driver_name) || $capacity <= 0) {
            $error = "Please fill all required fields.";
        } else {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO tankers (tanker_number, owner_name, driver_name, mobile, capacity, route_info) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssds", $tanker_number, $owner_name, $driver_name, $mobile, $capacity, $route_info);
            } else {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE tankers SET tanker_number=?, owner_name=?, driver_name=?, mobile=?, capacity=?, route_info=? WHERE id=?");
                $stmt->bind_param("ssssdsi", $tanker_number, $owner_name, $driver_name, $mobile, $capacity, $route_info, $id);
            }

            try {
                $stmt->execute();
                $stmt->close();
                $success = $action === 'add' ? "Tanker added successfully!" : "Tanker updated successfully!";
            } catch (Exception $e) {
                if ($conn->errno === 1062) {
                    $error = "Tanker number already exists.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM tankers WHERE id = $id");
        $success = "Tanker deleted.";
    }
}

$tankers = $conn->query("SELECT * FROM tankers ORDER BY tanker_number ASC");
include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Tanker Management</h1>
    <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addModal">
        <i class="fas fa-plus-circle"></i> Add Tanker
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> All Tankers</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tankerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Tanker No.</th>
                        <th>Owner Name</th>
                        <th>Driver Name</th>
                        <th>Mobile</th>
                        <th>Capacity</th>
                        <th>Route</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tankers->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No tankers found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($t = $tankers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($t['tanker_number']) ?></td>
                            <td><?= htmlspecialchars($t['owner_name']) ?></td>
                            <td><?= htmlspecialchars($t['driver_name']) ?></td>
                            <td><?= htmlspecialchars($t['mobile'] ?: '-') ?></td>
                            <td><?= number_format($t['capacity'], 2) ?> L</td>
                            <td><?= htmlspecialchars($t['route_info'] ?: '-') ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editModal"
                                    data-id="<?= $t['id'] ?>"
                                    data-number="<?= htmlspecialchars($t['tanker_number'], ENT_QUOTES) ?>"
                                    data-owner="<?= htmlspecialchars($t['owner_name'], ENT_QUOTES) ?>"
                                    data-driver="<?= htmlspecialchars($t['driver_name'], ENT_QUOTES) ?>"
                                    data-mobile="<?= htmlspecialchars($t['mobile'], ENT_QUOTES) ?>"
                                    data-capacity="<?= $t['capacity'] ?>"
                                    data-route="<?= htmlspecialchars($t['route_info'], ENT_QUOTES) ?>"
                                    title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars($t['tanker_number'], ENT_QUOTES) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> Add Tanker</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Tanker Number <span class="text-danger">*</span></label>
                    <input type="text" name="tanker_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Owner Name <span class="text-danger">*</span></label>
                    <input type="text" name="owner_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Driver Name <span class="text-danger">*</span></label>
                    <input type="text" name="driver_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Mobile Number</label>
                    <input type="text" name="mobile" class="form-control">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Capacity (Liters) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="capacity" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Route Information</label>
                    <textarea name="route_info" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit mr-1"></i> Edit Tanker</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Tanker Number <span class="text-danger">*</span></label>
                    <input type="text" name="tanker_number" id="edit_tanker_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Owner Name <span class="text-danger">*</span></label>
                    <input type="text" name="owner_name" id="edit_owner_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Driver Name <span class="text-danger">*</span></label>
                    <input type="text" name="driver_name" id="edit_driver_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Mobile Number</label>
                    <input type="text" name="mobile" id="edit_mobile" class="form-control">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Capacity (Liters) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="capacity" id="edit_capacity" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Route Information</label>
                    <textarea name="route_info" id="edit_route_info" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
$('#editModal').on('show.bs.modal', function(e) {
    const btn = $(e.relatedTarget);
    $('#edit_id').val(btn.data('id'));
    $('#edit_tanker_number').val(btn.data('number'));
    $('#edit_owner_name').val(btn.data('owner'));
    $('#edit_driver_name').val(btn.data('driver'));
    $('#edit_mobile').val(btn.data('mobile'));
    $('#edit_capacity').val(btn.data('capacity'));
    $('#edit_route_info').val(btn.data('route'));
});

function confirmDelete(id, number) {
    if (confirm('Delete tanker "' + number + '"?')) {
        $('#delete_id').val(id);
        $('#deleteForm').submit();
    }
}

$(document).ready(function() {
    $('#tankerTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
