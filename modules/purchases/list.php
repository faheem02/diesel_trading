<?php
session_start();
$active_page = 'purchase_list';
require_once '../../config/db.php';

$where = [];
$params = [];
$types = "";

if (!empty($_GET['from_date'])) {
    $where[] = "p.purchase_date >= ?";
    $params[] = $_GET['from_date'];
    $types .= "s";
}
if (!empty($_GET['to_date'])) {
    $where[] = "p.purchase_date <= ?";
    $params[] = $_GET['to_date'];
    $types .= "s";
}
if (!empty($_GET['supplier_id'])) {
    $where[] = "p.supplier_id = ?";
    $params[] = $_GET['supplier_id'];
    $types .= "i";
}
if (!empty($_GET['payment_status'])) {
    $where[] = "p.payment_status = ?";
    $params[] = $_GET['payment_status'];
    $types .= "s";
}

$sql = "SELECT p.*, s.company_name,
        (SELECT COUNT(*) FROM purchase_tankers WHERE purchase_id = p.id) AS tanker_count,
        (SELECT COUNT(*) FROM purchase_returns WHERE purchase_id = p.id) AS return_count,
        (SELECT COALESCE(SUM(return_amount),0) FROM purchase_returns WHERE purchase_id = p.id) AS return_total,
        (SELECT COUNT(*) FROM purchase_adjustments WHERE purchase_id = p.id) AS adjustment_count
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.purchase_date DESC, p.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$suppliers = $conn->query("SELECT id, company_name FROM suppliers ORDER BY company_name");

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Purchase List</h1>
    <div>
        <a href="returns.php" class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm mr-1">
            <i class="fas fa-undo-alt"></i> Purchase Return
        </a>
        <a href="adjustments.php" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm mr-1">
            <i class="fas fa-sliders-h"></i> Adjustment
        </a>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm mr-1">
            <i class="fas fa-plus-circle"></i> New Purchase
        </a>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Records</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= $_GET['from_date'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= $_GET['to_date'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">All Suppliers</option>
                            <?php while ($row = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>" <?= (($_GET['supplier_id'] ?? '') == $row['id']) ? 'selected':'' ?>>
                                    <?= htmlspecialchars($row['company_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="">All</option>
                            <option value="Paid" <?= (($_GET['payment_status'] ?? '') == 'Paid') ? 'selected':'' ?>>Paid</option>
                            <option value="Partial Paid" <?= (($_GET['payment_status'] ?? '') == 'Partial Paid') ? 'selected':'' ?>>Partial Paid</option>
                            <option value="Credit" <?= (($_GET['payment_status'] ?? '') == 'Credit') ? 'selected':'' ?>>Credit</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-group w-100">
                        <label class="small font-weight-bold d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="list.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Purchase Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="purchasesTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Qty (Ton)</th>
                        <th>Rate/Ton</th>
                        <th>Net Cost</th>
                        <th>Status / Payment</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No purchase records found.</td></tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="font-weight-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                                <td><?= htmlspecialchars($row['purchase_date']) ?></td>
                                <td><?= htmlspecialchars($row['company_name']) ?></td>
                                <td><?= number_format($row['diesel_quantity'], 3) ?>
                                    <small class="d-block text-muted">Waste: <?= number_format($row['waste_kg'] ?? 0, 2) ?> kg</small>
                                </td>
                                <td><?= number_format($row['rate_per_ton'], 2) ?></td>
                                <td class="font-weight-bold"><?= number_format($row['net_purchase_cost'], 2) ?></td>
                                <td>
                                    <?php
                                    $badge = $row['payment_status'] == 'Paid' ? 'success' : ($row['payment_status'] == 'Partial Paid' ? 'warning' : 'danger');
                                    $paid = $row['paid_amount'];
                                    $total = $row['net_purchase_cost'];
                                    $remaining = $total - $paid;
                                    ?>
                                    <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($row['payment_status']) ?></span>
                                    <small class="d-block text-muted">Paid: <?= number_format($paid, 0) ?>/<?= number_format($total, 0) ?></small>
                                    <?php if ($remaining > 0): ?>
                                        <small class="d-block text-danger font-weight-bold">Due: $ <?= number_format($remaining, 0) ?></small>
                                    <?php endif; ?>
                                    <?php if ($row['return_count'] > 0): ?>
                                        <span class="badge badge-warning mt-1 d-inline-block" title="Returns">
                                            <i class="fas fa-undo-alt"></i> <?= $row['return_count'] ?> Return<?= $row['return_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($row['adjustment_count'] > 0): ?>
                                        <span class="badge badge-info mt-1 d-inline-block" title="Adjustments">
                                            <i class="fas fa-sliders-h"></i> <?= $row['adjustment_count'] ?> Adjustment<?= $row['adjustment_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="#" class="btn btn-info" data-toggle="modal" data-target="#tankerModal<?= $row['id'] ?>" title="View Tanker Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="returns.php?purchase_id=<?= $row['id'] ?>" class="btn btn-outline-warning" title="Return">
                                            <i class="fas fa-undo-alt"></i>
                                        </a>
                                        <a href="adjustments.php?purchase_id=<?= $row['id'] ?>" class="btn btn-outline-secondary" title="Adjust">
                                            <i class="fas fa-sliders-h"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$result->data_seek(0);
while ($row = $result->fetch_assoc()):
    $tid = $row['id'];
    $tankers_q = $conn->prepare("SELECT * FROM purchase_tankers WHERE purchase_id = ?");
    $tankers_q->bind_param("i", $tid);
    $tankers_q->execute();
    $tankers = $tankers_q->get_result();

    $returns_q = $conn->prepare("SELECT * FROM purchase_returns WHERE purchase_id = ?");
    $returns_q->bind_param("i", $tid);
    $returns_q->execute();
    $returns = $returns_q->get_result();
?>

<div class="modal fade" id="tankerModal<?= $tid ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-truck text-primary mr-1"></i> Tankers - Invoice #<?= htmlspecialchars($row['invoice_no']) ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Tanker No</th>
                            <th>Driver Name</th>
                            <th>Driver Mobile</th>
                            <th>Qty (Ton)</th>
                            <!-- <th>Waste (Kg)</th> -->
                            <!-- <th>Net (Ton)</th> -->
                            <th>Rate</th>
                            <th>Total</th>
                            <th>Freight</th>
                            <!-- <th>Other</th> -->
                            <th>Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tankers->num_rows === 0): ?>
                            <tr><td colspan="11" class="text-center text-muted py-3">No tanker details available</td></tr>
                        <?php else:
                            while ($t = $tankers->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['tanker_number']) ?></td>
                                <td><?= htmlspecialchars($t['driver_name']) ?></td>
                                <td><?= htmlspecialchars($t['driver_mobile']) ?></td>
                                <td><?= number_format($t['diesel_quantity'], 3) ?></td>
                               
                                <td><?= number_format($t['rate_per_ton'], 2) ?></td>
                                <td><?= number_format($t['total_amount'], 2) ?></td>
                                <td><?= number_format($t['freight_charges'], 2) ?></td>
                               
                                <td class="font-weight-bold"><?= number_format($t['net_amount'], 2) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($returns->num_rows > 0): ?>
<div class="modal fade" id="returnModal<?= $tid ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-undo-alt text-warning mr-1"></i> Returns - Invoice #<?= htmlspecialchars($row['invoice_no']) ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Qty Returned</th>
                            <th>Rate</th>
                            <th>Amount</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = $returns->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['return_date']) ?></td>
                                <td><?= number_format($r['quantity_returned'], 3) ?></td>
                                <td><?= number_format($r['rate_per_ton'], 2) ?></td>
                                <td><?= number_format($r['return_amount'], 2) ?></td>
                                <td><?= htmlspecialchars($r['reason'] ?: '-') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endwhile; ?>

<script>
$(document).ready(function() {
    $('#purchasesTable').DataTable({
        order: [[1, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
