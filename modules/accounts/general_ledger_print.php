<?php
session_start();
require_once '../../includes/db.php';

$from_date   = $_GET['from_date'] ?? date('Y-m-01');
$to_date     = $_GET['to_date']   ?? date('Y-m-d');
$type_filter = $_GET['type'] ?? '';

$entries = [];

function fetchGL($conn, $sql, $params, $types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}
function matchesFilter($type_filter, $accepted) {
    return !$type_filter || in_array($type_filter, $accepted);
}

if (matchesFilter($type_filter, ['', 'purchase', 'payment', 'return', 'opening_balance', 'adjustment'])) {
    $sql = "SELECT sl.transaction_date AS txn_date, sl.description, sl.debit, sl.credit,
                   s.company_name AS party, 'Supplier' AS party_type, sl.reference_type,
                   ba.account_name AS account_ref, ba.account_type
            FROM supplier_ledger sl
            JOIN suppliers s ON sl.supplier_id = s.id
            LEFT JOIN bank_accounts ba ON sl.bank_account_id = ba.id
            WHERE sl.transaction_date BETWEEN ? AND ?";
    $p = [$from_date, $to_date]; $t = "ss";
    if ($type_filter) { $sql .= " AND sl.reference_type = ?"; $p[] = $type_filter; $t .= "s"; }
    $sql .= " ORDER BY sl.transaction_date ASC, sl.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, $p, $t));
}
if (matchesFilter($type_filter, ['', 'sale', 'payment', 'return', 'opening_balance'])) {
    $sql = "SELECT cl.transaction_date AS txn_date, cl.description, cl.debit, cl.credit,
                   c.customer_name AS party, 'Customer' AS party_type, cl.reference_type,
                   ba.account_name AS account_ref, ba.account_type
            FROM customer_ledger cl
            JOIN customers c ON cl.customer_id = c.id
            LEFT JOIN bank_accounts ba ON cl.bank_account_id = ba.id
            WHERE cl.transaction_date BETWEEN ? AND ?";
    $p = [$from_date, $to_date]; $t = "ss";
    if ($type_filter) { $sql .= " AND cl.reference_type = ?"; $p[] = $type_filter; $t .= "s"; }
    $sql .= " ORDER BY cl.transaction_date ASC, cl.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, $p, $t));
}
if (matchesFilter($type_filter, ['', 'expense'])) {
    $sql = "SELECT e.expense_date AS txn_date,
                   CONCAT(e.category, ' - ', e.subcategory, IFNULL(CONCAT(': ', e.description), '')) AS description,
                   e.amount AS debit, 0 AS credit, 'Expense' AS party, 'Expense' AS party_type,
                   'expense' AS reference_type, ba.account_name AS account_ref, ba.account_type
            FROM expenses e LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
            WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date ASC, e.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}
if (matchesFilter($type_filter, ['', 'stock_sale'])) {
    $sql = "SELECT s.sale_date AS txn_date,
                   CONCAT('Stock Sale #', s.invoice_no, ' - ', s.customer_name) AS description,
                   0 AS debit, s.total_amount AS credit,
                   s.customer_name AS party, 'Stock Sale' AS party_type,
                   'stock_sale' AS reference_type, NULL AS account_ref, NULL AS account_type
            FROM sales s WHERE s.sale_date BETWEEN ? AND ? ORDER BY s.sale_date ASC, s.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}
if (matchesFilter($type_filter, ['', 'return'])) {
    $sql = "SELECT pr.return_date AS txn_date,
                   CONCAT('Purchase Return - Invoice #', p.invoice_no) AS description,
                   0 AS debit, pr.return_amount AS credit,
                   s.company_name AS party, 'Supplier' AS party_type,
                   'return' AS reference_type, NULL AS account_ref, NULL AS account_type
            FROM purchase_returns pr
            JOIN purchases p ON pr.purchase_id = p.id
            JOIN suppliers s ON p.supplier_id = s.id
            WHERE pr.return_date BETWEEN ? AND ? ORDER BY pr.return_date ASC, pr.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}
if (matchesFilter($type_filter, ['', 'adjustment'])) {
    $sql = "SELECT sa.adjustment_date AS txn_date,
                   CONCAT('Stock Adjustment - ', sa.adjustment_type, IFNULL(CONCAT(': ', sa.description), '')) AS description,
                   sa.quantity AS debit, 0 AS credit,
                   t.tank_name AS party, 'Adjustment' AS party_type,
                   'adjustment' AS reference_type, NULL AS account_ref, NULL AS account_type
            FROM stock_adjustments sa JOIN tanks t ON sa.tank_id = t.id
            WHERE sa.adjustment_date BETWEEN ? AND ? ORDER BY sa.adjustment_date ASC, sa.id ASC";
    $entries = array_merge($entries, fetchGL($conn, $sql, [$from_date, $to_date], "ss"));
}

usort($entries, fn($a, $b) => $a['txn_date'] <=> $b['txn_date']);

$running_bal = 0;
foreach ($entries as &$e) {
    $running_bal += ($e['debit'] - $e['credit']);
    $e['running_bal'] = $running_bal;
}
unset($e);

$total_debit  = array_sum(array_column($entries, 'debit'));
$total_credit = array_sum(array_column($entries, 'credit'));
$net_balance  = $total_debit - $total_credit;

$badge_map = [
    'purchase'        => ['Purchase',    '#1a56db'],
    'payment'         => ['Payment',     '#057a55'],
    'sale'            => ['Sale',        '#0694a2'],
    'return'          => ['Return',      '#c27803'],
    'expense'         => ['Expense',     '#c81e1e'],
    'opening_balance' => ['Opening',     '#6b7280'],
    'stock_sale'      => ['Stock Sale',  '#0694a2'],
    'adjustment'      => ['Adjustment',  '#374151'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>General Ledger | <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: Arial, sans-serif;
    font-size: 8.5pt;
    color: #000;
    background: #fff;
    padding: 10mm;
}

/* ── Header ── */
.print-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 6px;
    margin-bottom: 8px;
}
.print-header .company { font-size: 14pt; font-weight: bold; color: #1a1a1a; }
.print-header .title   { font-size: 11pt; font-weight: bold; margin-top: 2px; }
.print-header .meta    { font-size: 7.5pt; color: #555; margin-top: 3px; }

/* ── Summary Row ── */
.summary-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}
.summary-box {
    flex: 1;
    border: 0.5px solid #ccc;
    border-radius: 4px;
    padding: 5px 8px;
    background: #f9f9f9;
}
.summary-box .label {
    font-size: 7pt;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 2px;
}
.summary-box .value {
    font-size: 10pt;
    font-weight: bold;
}
.summary-box.debit   .value { color: #c0392b; }
.summary-box.credit  .value { color: #1a7a3f; }
.summary-box.balance .value { color: #1a56db; }
.summary-box.entries .value { color: #374151; }

/* ── Table ── */
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
col.c-date   { width: 18mm; }
col.c-desc   { width: 72mm; }
col.c-party  { width: 32mm; }
col.c-type   { width: 18mm; }
col.c-acct   { width: 26mm; }
col.c-debit  { width: 25mm; }
col.c-credit { width: 25mm; }
col.c-bal    { width: 27mm; }

thead tr th {
    background: #1a1a1a;
    color: #fff;
    padding: 4px 5px;
    font-size: 7.5pt;
    font-weight: bold;
    text-align: left;
    border: 0.5px solid #444;
}
thead tr th.text-right { text-align: right; }

tbody tr td {
    padding: 3px 5px;
    border: 0.5px solid #ddd;
    font-size: 7.5pt;
    vertical-align: middle;
    word-break: break-word;
}
tbody tr:nth-child(even) td { background: #f7f7f7; }
tbody tr:hover td { background: #eef4ff; }

.text-right { text-align: right; }
.text-danger { color: #c0392b; font-weight: bold; }
.text-success { color: #1a7a3f; font-weight: bold; }
.text-pos { color: #1a7a3f; font-weight: bold; }
.text-neg { color: #c0392b; font-weight: bold; }

.badge {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 6.5pt;
    font-weight: bold;
    color: #fff;
    white-space: nowrap;
}
.party-badge {
    display: inline-block;
    font-size: 6pt;
    color: #666;
    border: 0.5px solid #ccc;
    border-radius: 2px;
    padding: 0px 3px;
    margin-left: 2px;
}

tfoot tr td {
    padding: 4px 5px;
    border: 0.5px solid #aaa;
    font-size: 8pt;
    font-weight: bold;
    background: #f0f0f0;
}

/* ── Footer ── */
.print-footer {
    margin-top: 8px;
    border-top: 0.5px solid #ccc;
    padding-top: 4px;
    display: flex;
    justify-content: space-between;
    font-size: 7pt;
    color: #888;
}

/* ── Print ── */
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body { padding: 0; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
}

@media screen {
    body { max-width: 277mm; margin: 0 auto; background: #e5e5e5; padding: 10mm; }
    .page { background: #fff; padding: 10mm; box-shadow: 0 2px 12px rgba(0,0,0,0.15); }
}
</style>
</head>
<body>
<div class="page">

<!-- Header -->
<div class="print-header">
    <div>
        <div class="company">Your Company Name</div>
        <div class="title">General Ledger</div>
        <div class="meta">
            Period: <strong><?= htmlspecialchars($from_date) ?></strong> to <strong><?= htmlspecialchars($to_date) ?></strong>
            <?= $type_filter ? ' &nbsp;|&nbsp; Type: <strong>' . htmlspecialchars(ucfirst($type_filter)) . '</strong>' : '' ?>
        </div>
    </div>
    <div style="text-align:right;">
        <div class="meta">Printed: <?= date('d M Y, h:i A') ?></div>
        <div class="meta">Total Entries: <?= count($entries) ?></div>
        <button onclick="window.print()" style="margin-top:6px;padding:4px 12px;font-size:8pt;cursor:pointer;background:#1a1a1a;color:#fff;border:none;border-radius:3px;">
            🖨 Print / Save PDF
        </button>
    </div>
</div>

<!-- Summary -->
<div class="summary-row">
    <div class="summary-box debit">
        <div class="label">Total Debit ($)</div>
        <div class="value"><?= number_format($total_debit, 2) ?></div>
    </div>
    <div class="summary-box credit">
        <div class="label">Total Credit ($)</div>
        <div class="value"><?= number_format($total_credit, 2) ?></div>
    </div>
    <div class="summary-box balance">
        <div class="label">Net Balance ($)</div>
        <div class="value" style="color:<?= $net_balance >= 0 ? '#1a56db' : '#c0392b' ?>">
            <?= number_format(abs($net_balance), 2) ?> <?= $net_balance >= 0 ? 'Dr' : 'Cr' ?>
        </div>
    </div>
    <div class="summary-box entries">
        <div class="label">Total Entries</div>
        <div class="value"><?= count($entries) ?></div>
    </div>
</div>

<!-- Table -->
<table>
    <colgroup>
        <col class="c-date"><col class="c-desc"><col class="c-party">
        <col class="c-type"><col class="c-acct">
        <col class="c-debit"><col class="c-credit"><col class="c-bal">
    </colgroup>
    <thead>
        <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Party</th>
            <th>Type</th>
            <th>Account</th>
            <th class="text-right">Debit ($)</th>
            <th class="text-right">Credit ($)</th>
            <th class="text-right">Balance ($)</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($entries)): ?>
        <tr><td colspan="8" style="text-align:center;color:#888;padding:12px;">No entries found for this period.</td></tr>
    <?php else: foreach ($entries as $e):
        [$label, $color] = $badge_map[$e['reference_type'] ?? ''] ?? [ucfirst($e['reference_type'] ?? ''), '#6b7280'];
    ?>
        <tr>
            <td><?= htmlspecialchars($e['txn_date']) ?></td>
            <td><?= htmlspecialchars($e['description']) ?></td>
            <td>
                <?= htmlspecialchars($e['party']) ?>
                <span class="party-badge"><?= htmlspecialchars($e['party_type']) ?></span>
            </td>
            <td><span class="badge" style="background:<?= $color ?>"><?= $label ?></span></td>
            <td>
                <?php if ($e['account_ref']): ?>
                    <?= htmlspecialchars($e['account_ref']) ?>
                    <?php if ($e['account_type']): ?>
                        <span class="party-badge"><?= htmlspecialchars($e['account_type']) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:#bbb;">—</span>
                <?php endif; ?>
            </td>
            <td class="text-right text-danger"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '-' ?></td>
            <td class="text-right text-success"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '-' ?></td>
            <td class="text-right <?= $e['running_bal'] >= 0 ? 'text-pos' : 'text-neg' ?>">
                <?= number_format(abs($e['running_bal']), 2) ?> <?= $e['running_bal'] >= 0 ? 'Dr' : 'Cr' ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($entries)): ?>
    <tfoot>
        <tr>
            <td colspan="5" class="text-right">TOTALS:</td>
            <td class="text-right text-danger"><?= number_format($total_debit, 2) ?></td>
            <td class="text-right text-success"><?= number_format($total_credit, 2) ?></td>
            <td class="text-right <?= $net_balance >= 0 ? 'text-pos' : 'text-neg' ?>">
                <?= number_format(abs($net_balance), 2) ?> <?= $net_balance >= 0 ? 'Dr' : 'Cr' ?>
            </td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<!-- Footer -->
<div class="print-footer">
    <span>General Ledger — <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?></span>
    <span>Generated: <?= date('d M Y, h:i A') ?></span>
</div>

</div>
</body>
</html>