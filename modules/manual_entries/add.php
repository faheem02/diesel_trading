<?php
session_start();
$active_page = 'manual_entry';
require_once '../../includes/db.php';

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sr_no          = trim($_POST['sr_no']);
    $entry_date     = $_POST['entry_date'];
    $person_name    = trim($_POST['person_name']);
    $description    = trim($_POST['description'] ?? '');
    $quantity       = floatval($_POST['quantity'] ?? 0);
    $rate_per_ton   = floatval($_POST['rate_per_ton'] ?? 0);
    $total_amount   = $rate_per_ton * $quantity;
    $paid_amount    = floatval($_POST['paid_amount'] ?? 0);
    $payment_source = trim($_POST['payment_source'] ?? '');

    if (empty($sr_no) || empty($entry_date) || empty($person_name)) {
        $error = "براہ کرم SR No، تاریخ اور نام درج کریں۔";
    } else {
        $stmt = $conn->prepare("INSERT INTO manual_entries (sr_no, entry_date, person_name, description, rate_per_ton, quantity, total_amount, paid_amount, payment_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdddds", $sr_no, $entry_date, $person_name, $description, $rate_per_ton, $quantity, $total_amount, $paid_amount, $payment_source);
        $stmt->execute();
        $stmt->close();
        $success = "اندراج کامیابی سے محفوظ ہو گیا!";
        $_POST = [];
    }
}

$max_sr = $conn->query("SELECT COALESCE(MAX(CAST(sr_no AS UNSIGNED)),0)+1 AS next_sr FROM manual_entries")->fetch_assoc();
$next_sr = str_pad($max_sr['next_sr'], 4, '0', STR_PAD_LEFT);

$existing_names = $conn->query("SELECT DISTINCT person_name FROM manual_entries ORDER BY person_name ASC");
$name_list = [];
while ($n = $existing_names->fetch_assoc()) {
    $name_list[] = $n['person_name'];
}

include '../../includes/header.php';
?>

<style>
.form-urdu label { font-family: 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Arial, sans-serif'; }
.ac-wrapper { position: relative; }
.ac-list { position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #d1d3e2; border-top: none; border-radius: 0 0 .35rem .35rem; display: none; box-shadow: 0 4px 8px rgba(0,0,0,.15); }
.ac-list .ac-item { padding: 8px 12px; cursor: pointer; font-size: 14px; }
.ac-list .ac-item:hover, .ac-list .ac-item.active { background: #4e73df; color: #fff; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> نئی اندراج</h1>
    <div>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-list"></i> فہرست دیکھیں
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice mr-1"></i> اندراج کی تفصیلات</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">SR نمبر <span class="text-danger">*</span></label>
                        <input type="text" name="sr_no" class="form-control" required
                               value="<?= htmlspecialchars($_POST['sr_no'] ?? $next_sr) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">نام <span class="text-danger">*</span></label>
                        <div class="ac-wrapper">
                            <input type="text" name="person_name" id="person_name" class="form-control" required placeholder="نام درج کریں"
                                   value="<?= htmlspecialchars($_POST['person_name'] ?? '') ?>" autocomplete="off">
                            <div class="ac-list" id="namesDropdown"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">تاریخ <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" class="form-control" required
                               value="<?= htmlspecialchars($_POST['entry_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="small font-weight-bold">تفصیل</label>
                        <input type="text" name="description" class="form-control" placeholder="تفصیل درج کریں..."
                               value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">تعداد</label>
                        <input type="number" step="0.001" min="0" name="quantity" id="quantity" class="form-control"
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">فی دانہ</label>
                        <input type="number" step="0.01" min="0" name="rate_per_ton" id="rate_per_ton" class="form-control"
                               value="<?= htmlspecialchars($_POST['rate_per_ton'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">کل رقم</label>
                        <input type="text" id="total_amount" class="form-control bg-light" readonly value="0.00">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">وصولی</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" class="form-control"
                               value="<?= htmlspecialchars($_POST['paid_amount'] ?? '0') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">زریعہ وصولی</label>
                        <input type="text" name="payment_source" class="form-control" placeholder="مثلاً نقد، چیک، ٹرانسفر..."
                               value="<?= htmlspecialchars($_POST['payment_source'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="small font-weight-bold">باقی</label>
                        <input type="text" id="balance_display" class="form-control bg-light" readonly value="0.00">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button type="submit" class="btn btn-primary btn-lg px-5 shadow">
            <i class="fas fa-save mr-1"></i> محفوظ کریں
        </button>
        <a href="list.php" class="btn btn-secondary btn-lg px-4 shadow">
            <i class="fas fa-times mr-1"></i> منسوخ
        </a>
    </div>
</form>

<script>
var dbNames = <?= json_encode($name_list) ?>;
var acInput = document.getElementById('person_name');
var acDropdown = document.getElementById('namesDropdown');
var acIndex = -1;

acInput.addEventListener('input', function() {
    var val = this.value.trim().toLowerCase();
    acDropdown.innerHTML = '';
    acIndex = -1;
    if (val.length === 0) { acDropdown.style.display = 'none'; return; }
    var matches = dbNames.filter(function(n) { return n.toLowerCase().indexOf(val) !== -1; });
    if (matches.length === 0) { acDropdown.style.display = 'none'; return; }
    matches.forEach(function(name, i) {
        var div = document.createElement('div');
        div.className = 'ac-item';
        div.textContent = name;
        div.addEventListener('mousedown', function(e) {
            e.preventDefault();
            acInput.value = name;
            acDropdown.style.display = 'none';
        });
        acDropdown.appendChild(div);
    });
    acDropdown.style.display = 'block';
});

acInput.addEventListener('keydown', function(e) {
    var items = acDropdown.querySelectorAll('.ac-item');
    if (items.length === 0) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        acIndex = Math.min(acIndex + 1, items.length - 1);
        items.forEach(function(el, i) { el.classList.toggle('active', i === acIndex); });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        acIndex = Math.max(acIndex - 1, 0);
        items.forEach(function(el, i) { el.classList.toggle('active', i === acIndex); });
    } else if (e.key === 'Enter' && acIndex >= 0) {
        e.preventDefault();
        acInput.value = items[acIndex].textContent;
        acDropdown.style.display = 'none';
    } else if (e.key === 'Escape') {
        acDropdown.style.display = 'none';
    }
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.ac-wrapper')) acDropdown.style.display = 'none';
});

document.getElementById('rate_per_ton').addEventListener('input', calcTotals);
document.getElementById('quantity').addEventListener('input', calcTotals);

function calcTotals() {
    const rate = parseFloat(document.getElementById('rate_per_ton').value) || 0;
    const qty  = parseFloat(document.getElementById('quantity').value) || 0;
    const total = rate * qty;
    document.getElementById('total_amount').value = total.toFixed(2);
    const paid = parseFloat(document.querySelector('[name="paid_amount"]').value) || 0;
    document.getElementById('balance_display').value = (total - paid).toFixed(2);
}

document.querySelector('[name="paid_amount"]').addEventListener('input', calcTotals);

calcTotals();
</script>

<?php include '../../includes/footer.php'; ?>
