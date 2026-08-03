<?php
session_start();
$active_page = 'manual_entry';
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$search    = trim($_GET['search'] ?? '');
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;
$delete_person = trim($_GET['delete_person'] ?? '');
$delete_id     = isset($_GET['delete']) ? intval($_GET['delete']) : 0;

if ($delete_id > 0) {
    $stmt = $conn->prepare("DELETE FROM manual_entries WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: list.php");
    exit;
}
if (!empty($delete_person)) {
    $stmt = $conn->prepare("DELETE FROM manual_entries WHERE person_name = ?");
    $stmt->bind_param("s", $delete_person);
    $stmt->execute();
    $stmt->close();
    $redirect = "list.php";
    $params = [];
    if (!empty($from_date)) $params[] = "from_date=" . urlencode($from_date);
    if (!empty($to_date))   $params[] = "to_date=" . urlencode($to_date);
    if (!empty($search))    $params[] = "search=" . urlencode($search);
    if (!empty($params)) $redirect .= "?" . implode("&", $params);
    header("Location: $redirect");
    exit;
}

$sql = "SELECT person_name,
               COUNT(*) AS entry_count,
               MIN(entry_date) AS first_date,
               MAX(entry_date) AS last_date,
               SUM(quantity) AS quantity,
               SUM(total_amount) AS total_amount,
               SUM(paid_amount) AS paid_amount,
               GROUP_CONCAT(DISTINCT payment_source SEPARATOR '، ') AS payment_sources
        FROM manual_entries WHERE 1=1";
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
if (!empty($search)) {
    $sql .= " AND (person_name LIKE ? OR description LIKE ? OR payment_source LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}
$sql .= " GROUP BY person_name ORDER BY person_name ASC";

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
$stmt->close();

$total_amount_sum = 0;
$paid_amount_sum  = 0;
$balance_sum      = 0;
$total_entries    = 0;
foreach ($rows as $r) {
    $total_amount_sum += $r['total_amount'];
    $paid_amount_sum  += $r['paid_amount'];
    $balance_sum      += ($r['total_amount'] - $r['paid_amount']);
    $total_entries    += $r['entry_count'];
}

if ($print_mode) {
    $logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
    ?><!DOCTYPE html><html lang="ur" dir="rtl"><head>
    <meta charset="UTF-8"><title>اندراج کی فہرست</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; direction: rtl; }
        .print-wrapper { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 45px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .print-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #2C3E50; padding-bottom: 15px; margin-bottom: 20px; }
        .print-header .logo { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; border: 3px solid #F39C12; flex-shrink: 0; }
        .print-header .logo img { width: 100%; height: 100%; object-fit: cover; }
        .print-header .brand .company { font-size: 24px; font-weight: 900; color: #2C3E50; line-height: 1.2; }
        .print-header .brand .sub { font-size: 12px; color: #F39C12; font-weight: 700; letter-spacing: 2px; margin-top: 2px; }
        .print-header .brand .contact { font-size: 13px; color: #555; margin-top: 5px; }
        h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin-bottom: 5px; text-align: center; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 15px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; direction: rtl; }
        table thead th { background: #2C3E50; color: #fff; padding: 10px 12px; font-size: 12px; text-align: center; }
        table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; text-align: center; }
        table tbody tr.total-row { background: #f8f9fc; font-weight: 700; border-top: 2px solid #2C3E50; }
        .btn-print { display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; border: none; margin-top: 20px; }
        .btn-print:hover { background: #1A252F; }
        .btn-back { display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin-left: 10px; }
        @page { margin: 10mm; }
        @media print { body { background: #fff; padding: 0; } .print-wrapper { box-shadow: none; border-radius: 0; padding: 15px 20px; } .no-print { display: none; } table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } table tbody tr.total-row { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style></head><body>
    <div class="print-wrapper">
        <h2>اندراج کی فہرست</h2>
        <div class="subtitle">
            مدت: <?= htmlspecialchars($from_date ?: 'سب') ?> سے <?= htmlspecialchars($to_date ?: 'سب') ?>
            <?php if ($search): ?>
                &nbsp;|&nbsp; تلاش: "<?= htmlspecialchars($search) ?>"
            <?php endif; ?>
            &nbsp;|&nbsp; کل اشخاص: <?= count($rows) ?> | کل اندراجات: <?= $total_entries ?>
        </div>

        <table>
            <thead><tr>
                <th>#</th><th>نام</th><th>تعداد اندراجات</th><th>تاریخ</th>
                <th>تعداد</th><th>کل رقم</th><th>وصولی</th><th>زریعہ وصولی</th><th>باقی</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" style="color:#999;padding:20px;">کوئی اندراج نہیں ملا۔</td></tr>
                <?php else:
                    $i = 1;
                    foreach ($rows as $row):
                        $balance = $row['total_amount'] - $row['paid_amount'];
                        $payment_sources = array_filter(array_map('trim', array_filter(explode('،', $row['payment_sources'] ?? ''))));
                        $date_range = $row['first_date'] == $row['last_date']
                            ? htmlspecialchars($row['first_date'])
                            : htmlspecialchars($row['first_date']) . ' - ' . htmlspecialchars($row['last_date']);
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td style="font-weight:bold"><?= htmlspecialchars($row['person_name']) ?></td>
                    <td><?= $row['entry_count'] ?></td>
                    <td><small><?= $date_range ?></small></td>
                    <td><?= number_format($row['quantity'], 3) ?></td>
                    <td><?= number_format($row['total_amount'], 2) ?></td>
                    <td style="color:#28a745"><?= number_format($row['paid_amount'], 2) ?></td>
                    <td><small><?= !empty($payment_sources) ? htmlspecialchars(implode('، ', $payment_sources)) : '<span style="color:#999">—</span>' ?></small></td>
                    <td style="font-weight:bold;color:<?= $balance > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance, 2) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <?php if (!empty($rows)): ?>
                <tr class="total-row">
                    <td colspan="2">کل</td>
                    <td><?= $total_entries ?></td>
                    <td></td>
                    <td><?= number_format(array_sum(array_column($rows, 'quantity')), 3) ?></td>
                    <td><?= number_format($total_amount_sum, 2) ?></td>
                    <td><?= number_format($paid_amount_sum, 2) ?></td>
                    <td></td>
                    <td style="color:<?= $balance_sum > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance_sum, 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="no-print" style="text-align:center;margin-top:20px;">
            <button class="btn-print" onclick="window.print()">پرنٹ / PDF محفوظ کریں</button>
            <button class="btn-back" onclick="window.close()">بند کریں</button>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
    </body></html>
    <?php exit;
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-clipboard-list mr-1"></i> اندراج کی فہرست</h1>
    <div>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm mr-1">
            <i class="fas fa-plus-circle"></i> نئی اندراج
        </a>
        <button onclick="printFiltered()" class="d-none d-sm-inline-block btn btn-sm btn-dark shadow-sm">
            <i class="fas fa-print"></i> پرنٹ
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">فلٹر</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row w-100">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">شروع تاریخ</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">اختتامی تاریخ</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary shadow-sm mr-1"><i class="fas fa-search fa-sm mr-1"></i> فلٹر</button>
                            <a href="list.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-redo fa-sm mr-1"></i> ری سیٹ</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">اندراجات</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="entriesTable" width="100%" cellspacing="0" style="direction:rtl; text-align:right;">
                <thead class="thead-dark">
                    <tr>
                        <th style="text-align:center;">#</th>
                        <th>نام</th>
                        <th style="text-align:center;">اندراجات</th>
                        <th>تاریخ</th>
                        <th class="text-right">تعداد</th>
                        <th class="text-right">کل رقم</th>
                        <th class="text-right">وصولی</th>
                        <th>زریعہ وصولی</th>
                        <th class="text-right">باقی</th>
                        <th class="text-center">عمل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">کوئی اندراج نہیں ملا۔</td></tr>
                    <?php else:
                        $i = 1;
                        foreach ($rows as $row):
                            $balance = $row['total_amount'] - $row['paid_amount'];
                            $payment_sources = array_filter(array_map('trim', array_filter(explode('،', $row['payment_sources'] ?? ''))));
                            $date_range = $row['first_date'] == $row['last_date']
                                ? htmlspecialchars($row['first_date'])
                                : htmlspecialchars($row['first_date']) . ' - ' . htmlspecialchars($row['last_date']);
                    ?>
                        <tr>
                            <td style="text-align:center"><?= $i++ ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['person_name']) ?></td>
                            <td style="text-align:center"><span class="badge badge-primary"><?= $row['entry_count'] ?></span></td>
                            <td><small><?= $date_range ?></small></td>
                            <td class="text-right font-weight-bold"><?= number_format($row['quantity'], 3) ?></td>
                            <td class="text-right"><?= number_format($row['total_amount'], 2) ?></td>
                            <td class="text-right font-weight-bold" style="color:#28a745"><?= number_format($row['paid_amount'], 2) ?></td>
                            <td><small><?= !empty($payment_sources) ? htmlspecialchars(implode('، ', $payment_sources)) : '<span class="text-muted">—</span>' ?></small></td>
                            <td class="text-right font-weight-bold" style="color:<?= $balance > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance, 2) ?></td>
                            <td class="text-center" style="white-space:nowrap">
                                <a href="view.php?person=<?= urlencode($row['person_name']) ?>" class="btn btn-sm btn-outline-success" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="ledger.php?person=<?= urlencode($row['person_name']) ?>" class="btn btn-sm btn-info" title="ledger"><i class="fas fa-book"></i></a>
                                <a href="list.php?delete_person=<?= urlencode($row['person_name']) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-danger ml-1" onclick="return confirm('کیا آپ واقعی اس شخص کے تمام اندراجات حذف کرنا چاہتے ہیں?');" title="حذف"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot>
                    <tr class="font-weight-bold" style="background:#f8f9fc;">
                        <td colspan="2">کل</td>
                        <td style="text-align:center"><?= $total_entries ?></td>
                        <td></td>
                        <td class="text-right"><?= number_format(array_sum(array_column($rows, 'quantity')), 3) ?></td>
                        <td class="text-right"><?= number_format($total_amount_sum, 2) ?></td>
                        <td class="text-right" style="color:#28a745"><?= number_format($paid_amount_sum, 2) ?></td>
                        <td></td>
                        <td class="text-right" style="color:<?= $balance_sum > 0 ? '#dc3545' : '#28a745' ?>"><?= number_format($balance_sum, 2) ?></td>
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
        language: { search: "تلاش کریں:", lengthMenu: "_MENU_ اندراجات دکھائیں" }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

<script>
function printFiltered() {
    var params = new URLSearchParams();
    params.set('print', '1');

    var dtSearch = document.querySelector('#entriesTable_filter input');
    var fromVal = document.querySelector('input[name="from_date"]');
    var toVal = document.querySelector('input[name="to_date"]');

    if (dtSearch && dtSearch.value) params.set('search', dtSearch.value);
    if (fromVal && fromVal.value) params.set('from_date', fromVal.value);
    if (toVal && toVal.value) params.set('to_date', toVal.value);

    window.open('list.php?' + params.toString(), '_blank', 'width=1100,height=700');
}
</script>
