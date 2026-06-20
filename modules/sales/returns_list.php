<?php
session_start();
$active_page = 'sale_return_list';
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date']   ?? '';

$sql = "SELECT sr.*, cs.invoice_no, cs.customer_name
        FROM sale_returns sr
        JOIN customer_sales cs ON sr.sale_id = cs.id
        WHERE 1=1";
$params = []; $types = "";

if (!empty($from_date)) { $sql .= " AND sr.return_date >= ?"; $params[] = $from_date; $types .= "s"; }
if (!empty($to_date))   { $sql .= " AND sr.return_date <= ?"; $params[] = $to_date;   $types .= "s"; }
$sql .= " ORDER BY sr.return_date DESC, sr.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Sale Return List</h1>
    <a href="returns.php" class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm">
        <i class="fas fa-plus-circle"></i> New Return
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Records</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-group w-100">
                        <label class="small font-weight-bold d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Filter</button>
                        <a href="returns_list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Sale Returns</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="returnsTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Return Date</th>
                        <th>Invoice No</th>
                        <th>Customer</th>
                        <th class="text-right">Qty Returned (Ton)</th>
                        <th class="text-right">Rate/Ton</th>
                        <th class="text-right">Return Amount ($)</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No return records found.</td></tr>
                    <?php else:
                        $i = 1; $total_amt = 0;
                        while ($row = $result->fetch_assoc()):
                            $total_amt += $row['return_amount']; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['return_date']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td class="text-right"><?= number_format($row['quantity_returned'], 3) ?></td>
                            <td class="text-right"><?= number_format($row['rate_per_ton'], 2) ?></td>
                            <td class="text-right font-weight-bold text-danger"><?= number_format($row['return_amount'], 2) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($row['reason'] ?: '—') ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
                <?php if ($result->num_rows > 0): ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="6" class="text-right">Total Return Amount:</th>
                        <th class="text-right text-danger">$ <?= number_format($total_amt, 2) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#returnsTable').DataTable({
        pageLength: 25, lengthMenu: [10, 25, 50, 100], ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
