<?php
session_start();
$active_page = 'supplier_outstanding';
require_once '../../../config/db.php';

$suppliers = $conn->query("
    SELECT s.*,
        (SELECT COALESCE(SUM(sl.debit),0) FROM supplier_ledger sl WHERE sl.supplier_id = s.id) AS total_debit,
        (SELECT COALESCE(SUM(sl.credit),0) FROM supplier_ledger sl WHERE sl.supplier_id = s.id) AS total_credit,
        (SELECT MAX(sl.transaction_date) FROM supplier_ledger sl WHERE sl.supplier_id = s.id) AS last_transaction
    FROM suppliers s
    ORDER BY s.balance DESC
");

include '../../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar mr-1"></i> Supplier Outstanding</h1>
    <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
        <i class="fas fa-print"></i> Print
    </button>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Suppliers Balance Summary</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="outstandingTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th class="text-right">Total Debit (Rs.)</th>
                        <th class="text-right">Total Credit (Rs.)</th>
                        <th class="text-right">Outstanding (Rs.)</th>
                        <th>Last Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($suppliers->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr>
                    <?php else:
                        $i = 1;
                        $grand_debit = 0; $grand_credit = 0; $grand_balance = 0;
                        while ($s = $suppliers->fetch_assoc()): 
                            $grand_debit += $s['total_debit'];
                            $grand_credit += $s['total_credit'];
                            $grand_balance += $s['balance'];
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($s['company_name']) ?></td>
                            <td><?= htmlspecialchars($s['contact_person'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($s['phone'] ?: '-') ?></td>
                            <td class="text-right text-danger"><?= number_format($s['total_debit'], 2) ?></td>
                            <td class="text-right text-success"><?= number_format($s['total_credit'], 2) ?></td>
                            <td class="text-right font-weight-bold <?= $s['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($s['balance'], 2) ?>
                            </td>
                            <td><?= $s['last_transaction'] ? htmlspecialchars($s['last_transaction']) : '-' ?></td>
                        </tr>
                    <?php endwhile; ?>
                        <tr class="table-active font-weight-bold">
                            <td colspan="4" class="text-right">Totals:</td>
                            <td class="text-right text-danger"><?= number_format($grand_debit, 2) ?></td>
                            <td class="text-right text-success"><?= number_format($grand_credit, 2) ?></td>
                            <td class="text-right <?= $grand_balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($grand_balance, 2) ?></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#outstandingTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
