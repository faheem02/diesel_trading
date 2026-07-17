<?php
session_start();
$active_page = 'customer_ledger';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$customer_id = intval($_GET['id'] ?? 0);
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';
$print_mode  = isset($_GET['print']) && $_GET['print'] == 1;

$customers = $conn->query("SELECT id, customer_name, balance FROM customers ORDER BY customer_name ASC");

$sup = null;
if ($customer_id > 0) {
    $sup = $conn->query("SELECT id, customer_name, balance, opening_balance, credit_limit FROM customers WHERE id = $customer_id")->fetch_assoc();
    if (!$sup) {
        $customer_id = 0;
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Customer Ledger</h1>
    <div>
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?id=<?= $customer_id ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<?php if (!$sup): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Customer</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group mr-3">
                <label class="small font-weight-bold mr-2">Customer</label>
                <select name="id" class="form-control" required>
                    <option value="">-- Select Customer --</option>
                    <?php while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?> (Bal: <?= number_format($c['balance'], 2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> View Ledger</button>
        </form>
    </div>
</div>
<?php else:
    $sql = "SELECT * FROM customer_ledger WHERE customer_id = ?";
    $params = [$customer_id];
    $types = "i";

    if (!empty($from_date)) {
        $sql .= " AND transaction_date >= ?";
        $params[] = $from_date;
        $types .= "s";
    } else {
        $from_date = date('Y-m-01');
    }
    if (!empty($to_date)) {
        $sql .= " AND transaction_date <= ?";
        $params[] = $to_date;
        $types .= "s";
    } else {
        $to_date = date('Y-m-d');
    }

    $sql .= " ORDER BY transaction_date ASC, id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $entries = $stmt->get_result();

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Customer Ledger - <?= htmlspecialchars($sup['customer_name'] ?? '') ?></title>
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
        <h2>Customer Ledger</h2>
        <div class="subtitle">
            <strong><?= htmlspecialchars($sup['customer_name'] ?? '') ?></strong> &nbsp;|&nbsp;
            Period: <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?> &nbsp;|&nbsp;
            Balance: <?= number_format($sup['balance'] ?? 0, 2) ?>
        </div>
        <table>
            <thead><tr><th>Date</th><th>Description</th><th>Ref</th><th class="text-right">Debit ($)</th><th class="text-right">Credit ($)</th><th class="text-right">Balance ($)</th></tr></thead>
            <tbody>
                <?php if ($entries->num_rows === 0): ?>
                    <tr><td colspan="6" class="text-center" style="color:#999;padding:20px;">No entries found.</td></tr>
                <?php else: while ($row = $entries->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td><?= str_replace('_', ' ', $row['reference_type']) ?></td>
                    <td class="text-right"><?= $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' ?></td>
                    <td class="text-right"><?= $row['credit'] > 0 ? number_format($row['credit'], 2) : '-' ?></td>
                    <td class="text-right"><?= number_format($row['balance'], 2) ?></td>
                </tr>
                <?php endwhile; endif; ?>
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
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($sup['customer_name']) ?>
            <span class="badge badge-<?= $sup['balance'] >= 0 ? 'success' : 'danger' ?> ml-2">Bal: $ <?= number_format($sup['balance'], 2) ?></span>
            <?php if ($sup['credit_limit'] > 0): ?>
                <small class="text-muted ml-2">Credit Limit: $ <?= number_format($sup['credit_limit'], 2) ?></small>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline mb-3">
            <input type="hidden" name="id" value="<?= $customer_id ?>">
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="ledger.php?id=<?= $customer_id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Ref</th>
                        <th>Debit ($)</th>
                        <th>Credit ($)</th>
                        <th>Balance ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php else:
                        while ($row = $entries->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><span class="badge badge-info"><?= str_replace('_', ' ', $row['reference_type']) ?></span></td>
                            <td class="text-danger font-weight-bold"><?= $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' ?></td>
                            <td class="text-success font-weight-bold"><?= $row['credit'] > 0 ? number_format($row['credit'], 2) : '-' ?></td>
                            <td class="font-weight-bold <?= $row['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($row['balance'], 2) ?>
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
    $('#ledgerTable').DataTable({
        pageLength: 50,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
