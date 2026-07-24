<?php
session_start();
$active_page = 'general_report';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_party') {
    $person_name = trim($_POST['person_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $opening_balance = floatval($_POST['opening_balance'] ?? 0);

    if (empty($person_name)) {
        $error = "Party name is required.";
    } else {
        $check = $conn->prepare("SELECT id FROM personal_accounts WHERE person_name = ?");
        $check->bind_param("s", $person_name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Party already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO personal_accounts (person_name, mobile, address, notes, balance) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdd", $person_name, $mobile, $address, $notes, $opening_balance);
            if ($stmt->execute()) {
                $account_id = $conn->insert_id;
                if ($opening_balance > 0) {
                    $desc = "Opening Balance";
                    $stmt2 = $conn->prepare("INSERT INTO personal_ledger (account_id, transaction_date, description, debit, credit, balance, reference_type) VALUES (?, CURDATE(), ?, 0, ?, ?, 'opening_balance')");
                    $stmt2->bind_param("isdd", $account_id, $desc, $opening_balance, $opening_balance);
                    $stmt2->execute();
                    $stmt2->close();
                }
                $success = "Party added successfully!";
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

$parties = $conn->query("
    SELECT pa.*,
           COALESCE((SELECT SUM(pl.credit) FROM personal_ledger pl WHERE pl.account_id = pa.id AND pl.reference_type = 'opening_balance'), 0) AS opening_balance
    FROM personal_accounts pa
    ORDER BY pa.person_name ASC
");

$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>All Parties</title>
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
        table tfoot td { padding: 10px 12px; font-size: 13px; font-weight: 700; border-top: 2px solid #2C3E50; background: #f8f9fc; }
        table tfoot td.text-right { text-align: right; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 15mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 20px 30px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; } table tfoot td { background: #f8f9fc !important; } }
    </style>
    </head>
    <body>
    <div class="print-wrapper">
        <div class="print-header">
            <div class="logo"><img src="<?= $logo ?>" alt="Logo"></div>
            <div class="brand">
                <div class="company">Muhammad Younas</div>
                <div class="sub">Diesel Management System</div>
                <div class="contact"><i>&#9742;</i> +93 70 260 7159</div>
            </div>
        </div>
        <h2>All Parties</h2>
        <div class="subtitle">Generated: <?= date('d M Y h:i A') ?></div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Party Name</th>
                    <th>Mobile</th>
                    <th>Address</th>
                    <th>Notes</th>
                    <th class="text-right">Opening Balance ($)</th>
                    <th class="text-right">Current Balance ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $parties->data_seek(0);
                $pi = 1;
                $total_opening_all = 0;
                $total_current_all = 0;
                while ($p = $parties->fetch_assoc()):
                    $total_opening_all += $p['opening_balance'];
                    $total_current_all += $p['balance'];
                ?>
                <tr>
                    <td><?= $pi++ ?></td>
                    <td><strong><?= htmlspecialchars($p['person_name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['mobile'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($p['address'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($p['notes'] ?: '-') ?></td>
                    <td class="text-right"><?= number_format($p['opening_balance'], 2) ?></td>
                    <td class="text-right">$ <?= number_format($p['balance'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">Totals (<?= $pi - 1 ?> Parties):</td>
                    <td class="text-right">$ <?= number_format($total_opening_all, 2) ?></td>
                    <td class="text-right">$ <?= number_format($total_current_all, 2) ?></td>
                </tr>
            </tfoot>
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-1"></i> Parties</h1>
    <div>
        <button onclick="window.open('?print=1', '_blank', 'width=1100,height=700')" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm mr-1">
            <i class="fas fa-print fa-sm"></i> Print
        </button>
        <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addPartyModal">
            <i class="fas fa-plus-circle"></i> Add Party
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Parties</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="partiesTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Party Name</th>
                        <th>Mobile</th>
                        <th>Address</th>
                        <th>Notes</th>
                        <th class="text-right">Opening Balance ($)</th>
                        <th class="text-right">Current Balance ($)</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($parties->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No parties found. Click "Add Party" to create one.</td></tr>
                    <?php else:
                        $i = 1;
                        while ($p = $parties->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($p['person_name']) ?></td>
                            <td><?= htmlspecialchars($p['mobile'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($p['address'] ?: '-') ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($p['notes'] ?: '-') ?></small></td>
                            <td class="text-right"><?= number_format($p['opening_balance'], 2) ?></td>
                            <td class="text-right font-weight-bold <?= $p['balance'] > 0 ? 'text-success' : ($p['balance'] < 0 ? 'text-danger' : '') ?>">
                                $ <?= number_format($p['balance'], 2) ?>
                            </td>
                            <td class="text-center" style="white-space:nowrap">
                                <a href="party_summary.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="View Summary"><i class="fas fa-eye"></i></a>
                                <a href="party_ledger.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Ledger"><i class="fas fa-book"></i></a>
                                <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this party and all its ledger entries?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Party Modal -->
<div class="modal fade" id="addPartyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-1"></i> Add New Party</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_party">
                <div class="form-group">
                    <label class="small font-weight-bold">Party Name <span class="text-danger">*</span></label>
                    <input type="text" name="person_name" class="form-control" required placeholder="Enter party name">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Mobile</label>
                    <input type="text" name="mobile" class="form-control" placeholder="Phone number">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Address">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Opening Balance ($)</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0">
                    <small class="text-muted">Amount this party owes you</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Party</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#partiesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
