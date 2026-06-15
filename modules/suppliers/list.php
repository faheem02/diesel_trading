<?php
session_start();
$active_page = 'supplier_list';
require_once '../../config/db.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM suppliers";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " WHERE company_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR ntn_cnic LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = "ssss";
}

$sql .= " ORDER BY company_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$delete_msg = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $did);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $delete_msg = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Supplier deleted successfully.<button type="button" class="close" data-dismiss="alert">&times;</button></div>';
    } else {
        $delete_msg = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> Cannot delete supplier. It may have linked records.<button type="button" class="close" data-dismiss="alert">&times;</button></div>';
    }
    $stmt->close();
}

include '../../includes/header.php';
?>

<?= $delete_msg ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Supplier List</h1>
    <div>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm mr-1">
            <i class="fas fa-plus-circle"></i> Add New Supplier
        </a>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Suppliers</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="row w-100">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="small font-weight-bold">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, Contact, Phone, NTN..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Suppliers</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="suppliersTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Mobile</th>
                        <th>NTN / CNIC</th>
                        <th>Opening Balance</th>
                        <th>Balance (Rs.)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['contact_person'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['ntn_cnic'] ?: '-') ?></td>
                            <td><?= number_format($row['opening_balance'], 2) ?></td>
                            <td class="font-weight-bold <?= $row['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($row['balance'], 2) ?>
                            </td>
                            <td class="text-center" style="white-space:nowrap">
                                <a href="ledger.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Ledger">
                                    <i class="fas fa-book"></i>
                                </a>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <a href="list.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this supplier?')">
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

<script>
$(document).ready(function() {
    $('#suppliersTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
