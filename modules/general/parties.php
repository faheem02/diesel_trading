<?php
session_start();
$active_page = 'general_report';
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

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-1"></i> Parties</h1>
    <div>
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
