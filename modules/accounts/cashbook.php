<?php
session_start();
$active_page = 'cashbook';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

$transactions = [];

// Customer payments (cash received from / paid to customers)
$sql = "SELECT
                cl.transaction_date AS txn_date,
                cl.description,
                CASE WHEN cl.credit > 0 THEN 'IN' ELSE 'OUT' END AS direction,
                CASE WHEN cl.credit > 0 THEN cl.credit ELSE cl.debit END AS amount,
                c.customer_name AS party,
                'Customer' AS party_type,
                cl.payment_method,
                'Cash' AS account_name
            FROM customer_ledger cl
            JOIN customers c ON cl.customer_id = c.id
            WHERE cl.reference_type = 'payment'
              AND cl.payment_method = 'Cash'
              AND cl.transaction_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $transactions[] = $row;
$stmt->close();

// Supplier payments (cash paid to / received from suppliers)
$sql = "SELECT
            sl.transaction_date AS txn_date,
            sl.description,
            CASE WHEN sl.debit > 0 THEN 'OUT' ELSE 'IN' END AS direction,
            CASE WHEN sl.debit > 0 THEN sl.debit ELSE sl.credit END AS amount,
            s.company_name AS party,
            'Supplier' AS party_type,
            sl.payment_method,
            'Cash' AS account_name
        FROM supplier_ledger sl
        JOIN suppliers s ON sl.supplier_id = s.id
        WHERE sl.reference_type = 'payment'
          AND sl.payment_method = 'Cash'
          AND sl.transaction_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $transactions[] = $row;
$stmt->close();

// Expenses paid by cash
$sql = "SELECT
            e.expense_date AS txn_date,
            CONCAT(e.category, ' - ', e.subcategory, IFNULL(CONCAT(': ', e.description), '')) AS description,
            'OUT' AS direction,
            e.amount,
            'Expense' AS party,
            'Expense' AS party_type,
            e.payment_method,
            'Cash' AS account_name
        FROM expenses e
        WHERE e.payment_method = 'Cash'
          AND e.expense_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $transactions[] = $row;
$stmt->close();

// Stock transactions (direct cash purchases/sales from stock modules)
$sql = "SELECT
            sl.transaction_date AS txn_date,
            sl.description,
            CASE WHEN sl.movement_type = 'OUT' THEN 'IN' ELSE 'OUT' END AS direction,
            sl.amount,
            t.tank_name AS party,
            'Stock' AS party_type,
            sl.payment_method,
            'Cash' AS account_name
        FROM stock_ledger sl
        JOIN tanks t ON sl.tank_id = t.id
        WHERE sl.amount > 0
          AND sl.payment_method = 'Cash'
          AND sl.transaction_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $transactions[] = $row;
$stmt->close();

// General party payables / receivables
// payable (credit) → we pay party → Cash OUT
// receivable (debit) → party pays us → Cash IN
$sql = "SELECT
            pl.transaction_date AS txn_date,
            pl.description,
            CASE WHEN pl.debit > 0 THEN 'IN' ELSE 'OUT' END AS direction,
            CASE WHEN pl.debit > 0 THEN pl.debit ELSE pl.credit END AS amount,
            pa.person_name AS party,
            'General Party' AS party_type,
            pl.payment_method,
            'Cash' AS account_name
        FROM personal_ledger pl
        JOIN personal_accounts pa ON pl.account_id = pa.id
        WHERE pl.reference_type IN ('payable','receivable')
          AND pl.payment_method = 'Cash'
          AND pl.transaction_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $transactions[] = $row;
$stmt->close();

// Sort all transactions by date ASC
usort($transactions, fn($a, $b) => strcmp($a['txn_date'], $b['txn_date']));

$total_in  = array_sum(array_column(array_filter($transactions, fn($t) => $t['direction'] === 'IN'),  'amount'));
$total_out = array_sum(array_column(array_filter($transactions, fn($t) => $t['direction'] === 'OUT'), 'amount'));

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><title>Cash Book</title>
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
        .summary-row { display: flex; gap: 15px; margin-bottom: 20px; }
        .summary-row .box { flex: 1; padding: 12px 16px; border-radius: 8px; text-align: center; }
        .summary-row .box .num { font-size: 22px; font-weight: 800; }
        .summary-row .box .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .box.in { background: #d4edda; color: #155724; }
        .box.out { background: #f8d7da; color: #721c24; }
        .box.net { background: #2C3E50; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table thead th.text-right { text-align: right; }
        table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        table tbody td.text-right { text-align: right; }
        table tfoot td { padding: 10px 12px; font-size: 13px; font-weight: 700; border-top: 2px solid #2C3E50; background: #f8f9fc; }
        table tfoot td.text-right { text-align: right; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 15mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; } table tfoot td { background: #f8f9fc !important; } .box.in { background: #d4edda !important; } .box.out { background: #f8d7da !important; } .box.net { background: #2C3E50 !important; } }
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
        <h2>Cash Book</h2>
        <div class="subtitle">
            Period: <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?>
        </div>
        <div class="summary-row">
            <div class="box in"><div class="lbl">Total Cash In</div><div class="num">$<?= number_format($total_in, 2) ?></div></div>
            <div class="box out"><div class="lbl">Total Cash Out</div><div class="num">$<?= number_format($total_out, 2) ?></div></div>
            <div class="box net"><div class="lbl">Net Flow</div><div class="num">$<?= number_format($total_in - $total_out, 2) ?></div></div>
        </div>
        <table>
            <thead><tr><th>Date</th><th>Description</th><th>Party</th><th>Type</th><th class="text-right">Cash In ($)</th><th class="text-right">Cash Out ($)</th></tr></thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" class="text-center" style="color:#999;padding:20px;">No transactions found.</td></tr>
                <?php else: foreach ($transactions as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['txn_date']) ?></td>
                    <td><?= htmlspecialchars($t['description']) ?></td>
                    <td><?= htmlspecialchars($t['party']) ?> (<?= htmlspecialchars($t['party_type']) ?>)</td>
                    <td><?= $t['direction'] === 'IN' ? 'Cash In' : 'Cash Out' ?></td>
                    <td class="text-right"><?= $t['direction'] === 'IN' ? number_format($t['amount'], 2) : '-' ?></td>
                    <td class="text-right"><?= $t['direction'] === 'OUT' ? number_format($t['amount'], 2) : '-' ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot><tr>
                <td colspan="4" class="text-right">Totals:</td>
                <td class="text-right">$<?= number_format($total_in, 2) ?></td>
                <td class="text-right">$<?= number_format($total_out, 2) ?></td>
            </tr></tfoot>
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Cash Book</h1>
    <div>
        <button onclick="window.open('<?= $_SERVER['PHP_SELF'] ?>?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print fa-sm"></i> Print
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Cash In</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_in, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-circle-down fa-2x text-success"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Cash Out</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_out, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-arrow-circle-up fa-2x text-danger"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Net Cash Flow</div>
                        <?php $net = $total_in - $total_out; ?>
                        <div class="h5 mb-0 font-weight-bold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                            $ <?= number_format($net, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="cashbook.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Cash Transactions</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="cashbookTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Party</th>
                        <th>Type</th>
                        <th class="text-right">Cash In ($)</th>
                        <th class="text-right">Cash Out ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No cash transactions found for this period.</td></tr>
                    <?php else:
                        foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['txn_date']) ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td>
                                <?= htmlspecialchars($t['party']) ?>
                                <span class="badge badge-secondary ml-1"><?= htmlspecialchars($t['party_type']) ?></span>
                            </td>
                            <td>
                                <?php if ($t['direction'] === 'IN'): ?>
                                    <span class="badge badge-success"><i class="fas fa-arrow-down fa-xs"></i> Cash In</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-arrow-up fa-xs"></i> Cash Out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right text-success font-weight-bold">
                                <?= $t['direction'] === 'IN' ? number_format($t['amount'], 2) : '-' ?>
                            </td>
                            <td class="text-right text-danger font-weight-bold">
                                <?= $t['direction'] === 'OUT' ? number_format($t['amount'], 2) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($transactions)): ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="4" class="text-right">Totals:</th>
                        <th class="text-right text-success">$ <?= number_format($total_in, 2) ?></th>
                        <th class="text-right text-danger">$ <?= number_format($total_out, 2) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#cashbookTable').DataTable({
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ordering: true,
        order: [[0, 'asc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
