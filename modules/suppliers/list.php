<?php
session_start();
$active_page = 'supplier_list';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$search = trim($_GET['search'] ?? '');
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

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

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Supplier List</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
        .print-wrapper { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
        .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
        .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
        .print-header .brand .contact { font-size: 13px; color: #555; margin-top: 5px; }
        .print-header .brand .contact i { color: #F39C12; font-style: normal; }
        h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin-bottom: 5px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table thead th.text-right { text-align: right; }
        table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        table tbody td.text-right { text-align: right; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 15mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style></head><body>
    <div class="print-wrapper">
        <div class="print-header">
            <div class="logo"><img src="<?= $logo ?>" alt="Logo"></div>
            <div class="brand">
                <div class="company">Muhammad Younas</div>
                <div class="sub">Diesel Management System</div>
                <div class="contact"><i>&#9742;</i> +93 70 260 7159</div>
            </div>
        </div>
        <h2>Supplier List</h2>
        <?php if (!empty($search)): ?><div class="subtitle">Search: "<?= htmlspecialchars($search) ?>"</div><?php endif; ?>
        <table>
            <thead><tr><th>#</th><th>Company Name</th><th>Contact Person</th><th>Phone</th><th>NTN/CNIC</th><th class="text-right">Opening Balance</th><th class="text-right">Balance ($)</th></tr></thead>
            <tbody>
                <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['company_name']) ?></td>
                    <td><?= htmlspecialchars($row['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['ntn_cnic'] ?? '-') ?></td>
                    <td class="text-right"><?= number_format($row['opening_balance'] ?? 0, 2) ?></td>
                    <td class="text-right"><?= number_format($row['balance'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="no-print" style="text-align:center;margin-top:20px;">
            <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
            <button class="btn-back" onclick="window.close()">Close</button>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
    </body></html>
    <?php exit;
}

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
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
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
                        <th>Balance($)</th>
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
