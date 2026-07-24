<?php
session_start();
$active_page = 'manual_entry';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;
$delete_id  = isset($_GET['delete']) ? intval($_GET['delete']) : 0;

if ($delete_id > 0) {
    $stmt = $conn->prepare("DELETE FROM manual_entries WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: list.php");
    exit;
}

$sql = "SELECT * FROM manual_entries WHERE 1=1";
$params = [];
$types = "";

if (!empty($from_date)) {
    $sql .= " AND entry_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND entry_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}
$sql .= " ORDER BY entry_date DESC, id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$total_amount_sum = 0;
$paid_amount_sum  = 0;
$balance_sum      = 0;
foreach ($rows as $r) {
    $total_amount_sum += $r['total_amount'];
    $paid_amount_sum  += $r['paid_amount'];
    $balance_sum      += ($r['total_amount'] - $r['paid_amount']);
}

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Manual Entries</title>
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
        h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin-bottom: 5px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table thead th.text-right { text-align: right; }
        table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        table tbody td.text-right { text-align: right; }
        table tbody tr.total-row { background: #f8f9fc; font-weight: 700; border-top: 2px solid #2C3E50; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 10mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 15px 20px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } table tbody tr.total-row { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
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
        <h2>Manual Entries</h2>
        <div class="subtitle">
            Period: <?= htmlspecialchars($from_date ?: 'All') ?> to <?= htmlspecialchars($to_date ?: 'All') ?>
            &nbsp;|&nbsp; Total Entries: <?= count($rows) ?>
        </div>

        <table>
            <thead><tr>
                <th>SR No</th><th>Date</th><th>Name</th>
                <th class="text-right">Rate</th><th class="text-right">Qty</th>
                <th class="text-right">Total($)</th><th class="text-right">Paid($)</th>
                <th class="text-right">Balance($)</th><th>Description</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-center" style="color:#999;padding:20px;">No entries found.</td></tr>
                <?php else:
                    foreach ($rows as $row):
                        $balance = $row['total_amount'] - $row['paid_amount'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['sr_no']) ?></td>
                    <td><?= htmlspecialchars($row['entry_date']) ?></td>
                    <td><?= htmlspecialchars($row['person_name']) ?></td>
                    <td class="text-right"><?= number_format($row['rate_per_ton'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['quantity'], 3) ?></td>
                    <td class="text-right"><?= number_format($row['total_amount'], 2) ?></td>
                    <td class="text-right" style="color:#28a745"><?= number_format($row['paid_amount'], 2) ?></td>
                    <td class="text-right font-weight-bold" style="color:<?= $balance > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance, 2) ?></td>
                    <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <?php if (!empty($rows)): ?>
                <tr class="total-row">
                    <td colspan="3">TOTAL</td>
                    <td></td>
                    <td class="text-right"><?= number_format(array_sum(array_column($rows, 'quantity')), 3) ?></td>
                    <td class="text-right"><?= number_format($total_amount_sum, 2) ?></td>
                    <td class="text-right"><?= number_format($paid_amount_sum, 2) ?></td>
                    <td class="text-right" style="color:<?= $balance_sum > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance_sum, 2) ?></td>
                    <td></td>
                </tr>
                <?php endif; ?>
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

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-clipboard-list mr-1"></i> Manual Entries</h1>
    <div>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm mr-1">
            <i class="fas fa-plus-circle"></i> New Entry
        </a>
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row w-100">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-1"><i class="fas fa-search fa-sm mr-1"></i> Filter</button>
                            <a href="list.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Entries</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="entriesTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>SR No</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Total($)</th>
                        <th class="text-right">Paid($)</th>
                        <th class="text-right">Balance($)</th>
                        <th>Description</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No entries found.</td></tr>
                    <?php else:
                        foreach ($rows as $row):
                            $balance = $row['total_amount'] - $row['paid_amount'];
                    ?>
                        <tr>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['sr_no']) ?></td>
                            <td><?= htmlspecialchars($row['entry_date']) ?></td>
                            <td><?= htmlspecialchars($row['person_name']) ?></td>
                            <td class="text-right"><?= number_format($row['rate_per_ton'], 2) ?></td>
                            <td class="text-right font-weight-bold"><?= number_format($row['quantity'], 3) ?></td>
                            <td class="text-right"><?= number_format($row['total_amount'], 2) ?></td>
                            <td class="text-right font-weight-bold" style="color:#28a745"><?= number_format($row['paid_amount'], 2) ?></td>
                            <td class="text-right font-weight-bold" style="color:<?= $balance > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance, 2) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                            <td class="text-center" style="white-space:nowrap">
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="print.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success ml-1" target="_blank" title="Print"><i class="fas fa-print"></i></a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger ml-1" onclick="return confirm('Delete this entry?')" title="Delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot>
                    <tr class="font-weight-bold" style="background:#f8f9fc;">
                        <td colspan="3">TOTAL</td>
                        <td></td>
                        <td class="text-right"><?= number_format(array_sum(array_column($rows, 'quantity')), 3) ?></td>
                        <td class="text-right"><?= number_format($total_amount_sum, 2) ?></td>
                        <td class="text-right" style="color:#28a745"><?= number_format($paid_amount_sum, 2) ?></td>
                        <td class="text-right" style="color:<?= $balance_sum > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance_sum, 2) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#entriesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
