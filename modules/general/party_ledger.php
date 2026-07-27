<?php
session_start();
$active_page = 'general_report';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: parties.php"); exit; }

$party = $conn->query("SELECT * FROM personal_accounts WHERE id = $id")->fetch_assoc();
if (!$party) { header("Location: parties.php"); exit; }

$total_payable = $conn->query("SELECT COALESCE(SUM(debit), 0) AS total FROM personal_ledger WHERE account_id = $id AND reference_type = 'payable'")->fetch_assoc()['total'];
$total_receivable = $conn->query("SELECT COALESCE(SUM(credit), 0) AS total FROM personal_ledger WHERE account_id = $id AND reference_type = 'receivable'")->fetch_assoc()['total'];
$total_opening = $conn->query("SELECT COALESCE(SUM(credit), 0) AS total FROM personal_ledger WHERE account_id = $id AND reference_type = 'opening_balance'")->fetch_assoc()['total'];

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$search    = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM personal_ledger WHERE account_id = ?";
$params = [$id];
$types = "i";

if (!empty($from_date)) {
    $sql .= " AND transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $sql .= " AND transaction_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND description LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$sql .= " ORDER BY transaction_date ASC, id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$entries = $stmt->get_result();
$stmt->close();

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($print_mode) {
    $logo = '';
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/diesel_trading/modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg')) {
        $logo = '/diesel_trading/modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Ledger - <?= htmlspecialchars($party['person_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
        .print-wrapper { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4 20px rgba(0,0,0,0.08); }
        .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
        .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; }
        .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; }
        .info { font-size: 13px; margin-bottom: 15px; }
        .info .label { font-weight: 700; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; text-align: left; }
        thead th.text-right { text-align: right; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        tbody td.text-right { text-align: right; }
        tfoot td { padding: 10px 12px; font-size: 14px; font-weight: 700; border-top: 2px solid #2C3E50; background: #f8f9fc; }
        tfoot td.text-right { text-align: right; }
        .opening-row td { background: #e3f2fd; font-style: italic; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; margin-top: 20px; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 15mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } thead th { background: #2C3E50 !important; color: #fff !important; } }
    </style>
    </head>
    <body>
    <div class="print-wrapper">
        <div class="print-header">
            <?php if ($logo): ?><div class="logo"><img src="<?= $logo ?>" alt="Logo"></div><?php endif; ?>
            <div class="brand">
                <div class="company">Muhammad Younas</div>
                <div class="sub">Diesel Management System</div>
            </div>
        </div>
        <h2 style="font-size:20px;color:#2C3E50;margin-bottom:10px;">Party Ledger</h2>
        <div class="info">
            <span class="label">Party:</span> <?= htmlspecialchars($party['person_name']) ?> &nbsp;|&nbsp;
            <span class="label">Mobile:</span> <?= htmlspecialchars($party['mobile'] ?: '-') ?> &nbsp;|&nbsp;
            <span class="label">Address:</span> <?= htmlspecialchars($party['address'] ?: '-') ?>
            <?php if ($from_date || $to_date): ?>
                &nbsp;|&nbsp; <span class="label">Period:</span> <?= htmlspecialchars($from_date ?: 'Start') ?> to <?= htmlspecialchars($to_date ?: 'Now') ?>
            <?php endif; ?>
            <?php if ($search): ?>
                &nbsp;|&nbsp; <span class="label">Search:</span> "<?= htmlspecialchars($search) ?>"
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="text-right">Debit ($)</th>
                    <th class="text-right">Credit ($)</th>
                    <th class="text-right">Balance ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $running = 0;
                $total_debit = 0;
                $total_credit = 0;
                $entries->data_seek(0);
                while ($e = $entries->fetch_assoc()):
                    $running = $e['balance'];
                    $total_debit += $e['debit'];
                    $total_credit += $e['credit'];
                ?>
                <tr class="<?= $e['reference_type'] === 'opening_balance' ? 'opening-row' : '' ?>">
                    <td><?= htmlspecialchars($e['transaction_date']) ?></td>
                    <?php
                        $pdesc = $e['description'];
                        if (strpos($pdesc, 'Payable:') === 0) $pdesc = trim(substr($pdesc, 8));
                        elseif (strpos($pdesc, 'Receivable:') === 0) $pdesc = trim(substr($pdesc, 11));
                    ?>
                    <td><?= htmlspecialchars($pdesc ?: '-') ?></td>
                    <td><small><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $e['reference_type']))) ?></small></td>
                    <td class="text-right"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?></td>
                    <td class="text-right"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?></td>
                    <td class="text-right font-weight-bold">$ <?= number_format($running, 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right">Totals:</td>
                    <td class="text-right">$ <?= number_format($total_debit, 2) ?></td>
                    <td class="text-right">$ <?= number_format($total_credit, 2) ?></td>
                    <td class="text-right">$ <?= number_format($party['balance'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
        <div class="no-print" style="text-align:center;margin-top:20px;">
            <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
            <a href="party_ledger.php?id=<?= $id ?><?= $from_date ? '&from_date='.urlencode($from_date) : '' ?><?= $to_date ? '&to_date='.urlencode($to_date) : '' ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="btn-back">Back</a>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
    </body></html>
    <?php exit;
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Ledger — <?= htmlspecialchars($party['person_name']) ?></h1>
    <div>
        <a href="add_payable.php?party_id=<?= $id ?>" class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm mr-1">
            <i class="fas fa-hand-holding-usd"></i> Add Payable
        </a>
        <a href="add_receivable.php?party_id=<?= $id ?>" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm mr-1">
            <i class="fas fa-dollar-sign"></i> Add Receivable
        </a>
        <button onclick="printFiltered()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="parties.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Party Info Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Current Balance</div>
                    <div class="h5 mb-0 font-weight-bold <?= $party['balance'] > 0 ? 'text-success' : ($party['balance'] < 0 ? 'text-danger' : 'text-gray-800') ?>">
                        $ <?= number_format($party['balance'], 2) ?>
                    </div>
                </div>
                <div class="col-auto"><i class="fas fa-wallet fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Cash Out</div>
                    <div class="h5 mb-0 font-weight-bold text-warning">$ <?= number_format($total_payable, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Cash In</div>
                    <div class="h5 mb-0 font-weight-bold text-success">$ <?= number_format($total_receivable, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
</div>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Opening Balance</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($total_opening, 2) ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-book-open fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Mobile</div>
                    <div class="h6 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($party['mobile'] ?: '-') ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-phone fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-dark shadow h-100 py-2">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Address</div>
                    <div class="h6 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($party['address'] ?: '-') ?></div>
                </div>
                <div class="col-auto"><i class="fas fa-map-marker-alt fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline flex-wrap">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">Search</label>
                <input type="text" name="search" id="filterSearch" class="form-control form-control-sm" placeholder="Description..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="party_ledger.php?id=<?= $id ?>" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Ledger Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ledger Entries</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th class="text-right">Debit ($)</th>
                        <th class="text-right">Credit ($)</th>
                        <th class="text-right">Balance ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $entries->data_seek(0);
                    if ($entries->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries found.</td></tr>
                    <?php else:
                        while ($e = $entries->fetch_assoc()):
                            $is_opening = $e['reference_type'] === 'opening_balance';
                        ?>
                        <tr class="<?= $is_opening ? 'table-info' : '' ?>">
                            <td><?= htmlspecialchars($e['transaction_date']) ?></td>
                            <?php
                                $desc = $e['description'];
                                if (strpos($desc, 'Payable:') === 0) $desc = trim(substr($desc, 8));
                                elseif (strpos($desc, 'Receivable:') === 0) $desc = trim(substr($desc, 11));
                            ?>
                            <td class="<?= $is_opening ? 'font-weight-bold font-italic' : '' ?>"><?= htmlspecialchars($desc ?: '-') ?></td>
                            <?php
                                $type_label = ucfirst(str_replace('_', ' ', $e['reference_type']));
                                $type_label = str_replace('Payable', 'Paid', $type_label);
                                $type_label = str_replace('Receivable', 'Received', $type_label);
                            ?>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($type_label) ?></span></td>
                            <td class="text-right text-danger font-weight-bold"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?></td>
                            <td class="text-right text-success font-weight-bold"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?></td>
                            <td class="text-right font-weight-bold">$ <?= number_format($e['balance'], 2) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function printFiltered() {
    var params = new URLSearchParams();
    params.set('id', '<?= $id ?>');
    params.set('print', '1');

    var fromVal = document.querySelector('input[name="from_date"]').value;
    var toVal = document.querySelector('input[name="to_date"]').value;
    var searchVal = document.querySelector('#filterSearch').value;

    if (fromVal) params.set('from_date', fromVal);
    if (toVal) params.set('to_date', toVal);
    if (searchVal) params.set('search', searchVal);

    window.open('party_ledger.php?' + params.toString(), '_blank', 'width=1000,height=700');
}
</script>
