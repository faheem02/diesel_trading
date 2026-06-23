<?php
session_start();
$active_page = 'sales_outstanding';
require_once '../../../includes/db.php'; // Fixed: 3 levels up

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$customer_filter = $_GET['customer_id'] ?? '';

// AJAX handler for modal ledger data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ledger' && $customer_filter) {
    header('Content-Type: application/json');
    $cid = intval($customer_filter);
    $fdate = $_GET['from_date'] ?? date('Y-m-01');
    $tdate = $_GET['to_date'] ?? date('Y-m-d');
    $ledger_sql = "SELECT 
                        cl.transaction_date,
                        cl.description,
                        cl.debit,
                        cl.credit,
                        cl.balance,
                        cl.reference_type,
                        cl.reference_id,
                        ba.account_name AS bank_account,
                        cl.payment_method
                    FROM customer_ledger cl
                    LEFT JOIN bank_accounts ba ON cl.bank_account_id = ba.id
                    WHERE cl.customer_id = $cid
                        AND cl.transaction_date BETWEEN '$fdate' AND '$tdate'
                    ORDER BY cl.transaction_date ASC, cl.id ASC";
    $ledger_result = $conn->query($ledger_sql);
    $entries = [];
    while ($row = $ledger_result->fetch_assoc()) {
        $entries[] = $row;
    }
    $cust = $conn->query("SELECT id, customer_name, balance FROM customers WHERE id = $cid")->fetch_assoc();
    echo json_encode(['customer' => $cust, 'entries' => $entries]);
    exit;
}

// Get all customers for filter
$customers = $conn->query("SELECT id, customer_name, mobile, balance FROM customers ORDER BY customer_name ASC");

// Fetch customer outstanding data
$sql = "SELECT 
            c.id,
            c.customer_name,
            c.mobile,
            c.address,
            c.balance AS current_balance,
            COALESCE(SUM(CASE WHEN cl.debit > 0 THEN cl.debit ELSE 0 END), 0) AS total_sales,
            COALESCE(SUM(CASE WHEN cl.credit > 0 AND cl.reference_type != 'return' THEN cl.credit ELSE 0 END), 0) AS total_payments,
            COALESCE(SUM(CASE WHEN cl.reference_type = 'return' THEN cl.credit ELSE 0 END), 0) AS total_returns
        FROM customers c
        LEFT JOIN customer_ledger cl ON c.id = cl.customer_id 
            AND cl.transaction_date BETWEEN '$from_date' AND '$to_date'
        WHERE 1=1";

if ($customer_filter) {
    $sql .= " AND c.id = $customer_filter";
}

$sql .= " GROUP BY c.id ORDER BY c.customer_name ASC";

$result = $conn->query($sql);

// Calculate totals
$total_outstanding = 0;
$total_sales = 0;
$total_payments = 0;
$total_returns = 0;

$customer_data = [];
while ($row = $result->fetch_assoc()) {
    $outstanding = $row['total_sales'] - $row['total_payments'] - $row['total_returns'];
    $row['outstanding'] = $outstanding;
    $customer_data[] = $row;
    
    $total_outstanding += $outstanding;
    $total_sales += $row['total_sales'];
    $total_payments += $row['total_payments'];
    $total_returns += $row['total_returns'];
}

// If customer filter is applied, get detailed ledger
$ledger_entries = [];
if ($customer_filter) {
    $ledger_sql = "SELECT 
                        cl.transaction_date,
                        cl.description,
                        cl.debit,
                        cl.credit,
                        cl.balance,
                        cl.reference_type,
                        cl.reference_id,
                        ba.account_name AS bank_account,
                        cl.payment_method
                    FROM customer_ledger cl
                    LEFT JOIN bank_accounts ba ON cl.bank_account_id = ba.id
                    WHERE cl.customer_id = $customer_filter
                        AND cl.transaction_date BETWEEN '$from_date' AND '$to_date'
                    ORDER BY cl.transaction_date ASC, cl.id ASC";
    $ledger_result = $conn->query($ledger_sql);
    while ($row = $ledger_result->fetch_assoc()) {
        $ledger_entries[] = $row;
    }
}

include '../../../includes/header.php'; // Fixed: 3 levels up
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-1"></i> Customer Outstanding Report</h1>
    <div>
        <button onclick="window.print()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print fa-sm"></i> Print
        </button>
        <a href="../../../dashboard.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_sales, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Payments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_payments, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Returns</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_returns, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-undo fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Outstanding</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_outstanding, 2) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i></div>
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
            <div class="form-group mr-3 mb-2">
                <label class="small font-weight-bold mr-1">Customer</label>
                <select name="customer_id" class="form-control form-control-sm">
                    <option value="">All Customers</option>
                    <?php 
                    $customers->data_seek(0);
                    while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $customer_filter == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['customer_name']) ?> 
                            (Bal: <?= number_format($c['balance'], 2) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-2"><i class="fas fa-search fa-sm"></i> Filter</button>
            <a href="outstanding.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-redo fa-sm"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Outstanding Summary Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Customer Outstanding Summary</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="outstandingTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>Address</th>
                        <th class="text-right">Total Sales</th>
                        <th class="text-right">Total Payments</th>
                        <th class="text-right">Total Returns</th>
                        <th class="text-right">Current Balance</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customer_data)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php else:
                        $i = 1;
                        foreach ($customer_data as $row): 
                            $outstanding_class = $row['outstanding'] > 0 ? 'text-danger' : ($row['outstanding'] < 0 ? 'text-success' : 'text-muted');
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= htmlspecialchars($row['mobile'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['address'] ?: '-') ?></td>
                            <td class="text-right text-primary font-weight-bold"><?= number_format($row['total_sales'], 2) ?></td>
                            <td class="text-right text-success font-weight-bold"><?= number_format($row['total_payments'], 2) ?></td>
                            <td class="text-right text-warning font-weight-bold"><?= number_format($row['total_returns'], 2) ?></td>
                            <td class="text-right"><?= number_format($row['current_balance'], 2) ?></td>
                            <td class="text-right font-weight-bold <?= $outstanding_class ?>">
                                <?= number_format($row['outstanding'], 2) ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" title="View Details"
                                    data-toggle="modal" data-target="#customerLedgerModal"
                                    data-customer-id="<?= $row['id'] ?>"
                                    data-customer-name="<?= htmlspecialchars($row['customer_name']) ?>"
                                    data-from-date="<?= $from_date ?>"
                                    data-to-date="<?= $to_date ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($customer_data)): ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="4" class="text-right font-weight-bold">Totals:</th>
                        <th class="text-right text-primary font-weight-bold"><?= number_format($total_sales, 2) ?></th>
                        <th class="text-right text-success font-weight-bold"><?= number_format($total_payments, 2) ?></th>
                        <th class="text-right text-warning font-weight-bold"><?= number_format($total_returns, 2) ?></th>
                        <th></th>
                        <th class="text-right font-weight-bold text-danger"><?= number_format($total_outstanding, 2) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Customer Detailed Ledger (if customer selected) -->
<?php if ($customer_filter && !empty($ledger_entries)): 
    $customer_name = '';
    $customers->data_seek(0);
    while ($c = $customers->fetch_assoc()) {
        if ($c['id'] == $customer_filter) {
            $customer_name = $c['customer_name'];
            break;
        }
    }
?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-file-invoice mr-1"></i> 
            Detailed Ledger - <?= htmlspecialchars($customer_name) ?>
            <small class="text-muted ml-2"><?= $from_date ?> to <?= $to_date ?></small>
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable" width="100%" cellspacing="0">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Reference Type</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                        <th>Payment Method</th>
                        <th>Bank Account</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($ledger_entries as $entry): 
                        $badge_map = [
                            'sale' => ['Sale', 'info'],
                            'payment' => ['Payment', 'success'],
                            'return' => ['Return', 'warning'],
                            'opening_balance' => ['Opening', 'secondary'],
                        ];
                        $ref = $entry['reference_type'] ?? '';
                        [$label, $color] = $badge_map[$ref] ?? [$ref, 'secondary'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['transaction_date']) ?></td>
                        <td><?= htmlspecialchars($entry['description']) ?></td>
                        <td><span class="badge badge-<?= $color ?>"><?= $label ?></span></td>
                        <td class="text-right text-danger font-weight-bold">
                            <?= $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' ?>
                        </td>
                        <td class="text-right text-success font-weight-bold">
                            <?= $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' ?>
                        </td>
                        <td class="text-right font-weight-bold <?= $entry['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($entry['balance'], 2) ?>
                        </td>
                        <td><?= htmlspecialchars($entry['payment_method'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($entry['bank_account'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($ledger_entries)): 
                    $total_debit = array_sum(array_column($ledger_entries, 'debit'));
                    $total_credit = array_sum(array_column($ledger_entries, 'credit'));
                    $final_balance = end($ledger_entries)['balance'] ?? 0;
                ?>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="3" class="text-right font-weight-bold">Totals:</th>
                        <th class="text-right text-danger font-weight-bold"><?= number_format($total_debit, 2) ?></th>
                        <th class="text-right text-success font-weight-bold"><?= number_format($total_credit, 2) ?></th>
                        <th class="text-right font-weight-bold <?= $final_balance >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($final_balance, 2) ?>
                        </th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Customer Ledger Modal -->
<div class="modal fade" id="customerLedgerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice mr-1"></i>
                    Customer Ledger - <span id="ledgerCustomerName"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0" id="modalLedgerTable" width="100%">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Ref</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th class="text-right">Balance</th>
                                <th>Method</th>
                                <th>Bank Account</th>
                            </tr>
                        </thead>
                        <tbody id="modalLedgerBody">
                            <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function escapeHtml(text) {
    var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

$(document).ready(function() {
    $('#outstandingTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        order: [[8, 'desc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });

    <?php if ($customer_filter && !empty($ledger_entries)): ?>
    $('#ledgerTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        order: [[0, 'asc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
    <?php endif; ?>

    $('#customerLedgerModal').on('show.bs.modal', function (e) {
        var button = $(e.relatedTarget);
        var customerId = button.data('customer-id');
        var customerName = button.data('customer-name');
        var fromDate = button.data('from-date');
        var toDate = button.data('to-date');

        $('#ledgerCustomerName').text(customerName);
        $('#modalLedgerBody').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</td></tr>');

        $.ajax({
            url: window.location.pathname,
            data: {
                ajax: 'ledger',
                customer_id: customerId,
                from_date: fromDate,
                to_date: toDate
            },
            dataType: 'json',
            success: function (data) {
                var html = '';
                if (!data.entries || data.entries.length === 0) {
                    html = '<tr><td colspan="8" class="text-center text-muted py-4">No ledger entries found.</td></tr>';
                } else {
                    var badgeMap = {
                        'sale': ['Sale', 'info'],
                        'payment': ['Payment', 'success'],
                        'return': ['Return', 'warning'],
                        'opening_balance': ['Opening', 'secondary'],
                    };
                    $.each(data.entries, function (i, entry) {
                        var ref = entry.reference_type || '';
                        var badge = badgeMap[ref] || [ref, 'secondary'];
                        html += '<tr>' +
                            '<td>' + escapeHtml(entry.transaction_date) + '</td>' +
                            '<td>' + escapeHtml(entry.description) + '</td>' +
                            '<td><span class="badge badge-' + badge[1] + '">' + escapeHtml(badge[0]) + '</span></td>' +
                            '<td class="text-right text-danger font-weight-bold">' + (parseFloat(entry.debit) > 0 ? parseFloat(entry.debit).toFixed(2) : '-') + '</td>' +
                            '<td class="text-right text-success font-weight-bold">' + (parseFloat(entry.credit) > 0 ? parseFloat(entry.credit).toFixed(2) : '-') + '</td>' +
                            '<td class="text-right font-weight-bold ' + (parseFloat(entry.balance) >= 0 ? 'text-success' : 'text-danger') + '">' + parseFloat(entry.balance).toFixed(2) + '</td>' +
                            '<td>' + escapeHtml(entry.payment_method || '-') + '</td>' +
                            '<td>' + escapeHtml(entry.bank_account || '-') + '</td>' +
                            '</tr>';
                    });
                }
                $('#modalLedgerBody').html(html);
            },
            error: function () {
                $('#modalLedgerBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Error loading ledger data.</td></tr>');
            }
        });
    });
});
</script>

<?php include '../../../includes/footer.php'; // Fixed: 3 levels up ?>