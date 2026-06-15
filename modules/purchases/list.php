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
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm w-100" value="<?= $_GET['from_date'] ?? '' ?>">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm w-100" value="<?= $_GET['to_date'] ?? '' ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm w-100">
                        <option value="">All Suppliers</option>
                        <?php 
                        $suppliers->data_seek(0);
                        while ($row_s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $row_s['id'] ?>" <?= (($_GET['supplier_id'] ?? '') == $row_s['id']) ? 'selected':'' ?>>
                                <?= htmlspecialchars($row_s['company_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold">Status</label>
                    <select name="payment_status" class="form-control form-control-sm w-100">
                        <option value="">All</option>
                        <option value="Paid" <?= (($_GET['payment_status'] ?? '') == 'Paid') ? 'selected':'' ?>>Paid</option>
                        <option value="Partial Paid" <?= (($_GET['payment_status'] ?? '') == 'Partial Paid') ? 'selected':'' ?>>Partial Paid</option>
                        <option value="Credit" <?= (($_GET['payment_status'] ?? '') == 'Credit') ? 'selected':'' ?>>Credit</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="list.php" class="btn btn-sm btn-secondary flex-grow-1">
                        <i class="fas fa-redo"></i> Reset
                    </a>
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
                        <th class="text-center">Invoice No</th>
                        <th class="text-center">Date</th>
                        <th>Supplier</th>
                        <th class="text-right">Qty (Ton)</th>
                        <th class="text-right">Rate/Ton</th>
                        <th class="text-right">Net Cost</th>
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
                                <td class="text-center font-weight-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['purchase_date']) ?></td>
                                <td><?= htmlspecialchars($row['company_name']) ?></td>
                                <td class="text-right">
                                    <?= number_format($row['diesel_quantity'], 3) ?>
                                    <small class="d-block text-muted">Waste: <?= number_format($row['waste_kg'] ?? 0, 2) ?> kg</small>
                                </td>
                                <td class="text-right"><?= number_format($row['rate_per_ton'], 2) ?></td>
                                <td class="text-right font-weight-bold"><?= number_format($row['net_purchase_cost'], 2) ?></td>
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
                                        <small class="d-block text-danger font-weight-bold">Due: Rs. <?= number_format($remaining, 0) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="white-space:nowrap">
                                    <a href="#" class="btn btn-sm btn-outline-primary" title="View Details" data-toggle="modal" data-target="#viewModal<?= $row['id'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="returns.php?purchase_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning" title="Return">
                                        <i class="fas fa-undo-alt"></i>
                                    </a>
                                    <a href="adjustments.php?purchase_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Adjust">
                                        <i class="fas fa-sliders-h"></i>
                                    </a>
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
?>

<div class="modal fade" id="viewModal<?= $tid ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice text-primary mr-1"></i> Purchase Details - Invoice #<?= htmlspecialchars($row['invoice_no']) ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Invoice Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4"><small class="text-muted">Supplier</small><br><strong><?= htmlspecialchars($row['company_name']) ?></strong></div>
                            <div class="col-md-4"><small class="text-muted">Invoice No</small><br><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></div>
                            <div class="col-md-4"><small class="text-muted">Date</small><br><strong><?= htmlspecialchars($row['purchase_date']) ?></strong></div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-4"><small class="text-muted">Diesel Qty</small><br><strong><?= number_format($row['diesel_quantity'], 3) ?> Ton</strong></div>
                            <div class="col-md-4"><small class="text-muted">Waste</small><br><strong><?= number_format($row['waste_kg'] ?? 0, 2) ?> kg</strong></div>
                            <div class="col-md-4"><small class="text-muted">Net Qty</small><br><strong><?= number_format($row['net_quantity'] ?? 0, 3) ?> Ton</strong></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4"><small class="text-muted">Rate / Ton</small><br><strong>Rs. <?= number_format($row['rate_per_ton'], 2) ?></strong></div>
                            <div class="col-md-4"><small class="text-muted">Total Amount</small><br><strong>Rs. <?= number_format($row['total_amount'], 2) ?></strong></div>
                            <div class="col-md-4"><small class="text-muted">Freight</small><br><strong>Rs. <?= number_format($row['freight_charges'], 2) ?></strong></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4"><small class="text-muted">Other Charges</small><br><strong>Rs. <?= number_format($row['other_charges'], 2) ?></strong></div>
                            <div class="col-md-4"><small class="text-muted">Net Cost</small><br><strong class="text-primary">Rs. <?= number_format($row['net_purchase_cost'], 2) ?></strong></div>
                            <div class="col-md-4"><small class="text-muted">Payment Status</small><br>
                                <strong><span class="badge badge-<?= $row['payment_status'] == 'Paid' ? 'success' : ($row['payment_status'] == 'Partial Paid' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($row['payment_status']) ?></span></strong>
                                <small class="d-block text-muted">Paid: Rs. <?= number_format($row['paid_amount'], 0) ?></small>
                            </div>
                        </div>
                        <?php if ($row['invoice_attachment']): ?>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">Attachment:</small>
                                <a href="../../<?= htmlspecialchars($row['invoice_attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary ml-2">
                                    <i class="fas fa-file"></i> View
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-truck mr-1"></i> Tanker Details</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Tanker No</th>
                                    <th>Driver Name</th>
                                    <th>Driver Mobile</th>
                                    <th>Qty (Ton)</th>
                                    <th>Waste (Kg)</th>
                                    <th>Net (Ton)</th>
                                    <th>Rate/Ton</th>
                                    <th>Total</th>
                                    <th>Freight</th>
                                    <th>Other</th>
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
                                        <td><?= number_format($t['waste_kg'], 3) ?></td>
                                        <td><?= number_format($t['net_quantity'], 3) ?></td>
                                        <td><?= number_format($t['rate_per_ton'], 2) ?></td>
                                        <td><?= number_format($t['total_amount'], 2) ?></td>
                                        <td><?= number_format($t['freight_charges'], 2) ?></td>
                                        <td><?= number_format($t['other_charges'], 2) ?></td>
                                        <td class="font-weight-bold"><?= number_format($t['net_amount'], 2) ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
