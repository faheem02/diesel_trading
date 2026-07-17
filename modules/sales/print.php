<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { die("Invalid sale ID"); }

$sale = $conn->query("SELECT cs.*, ba.account_name, ba.bank_name FROM customer_sales cs LEFT JOIN bank_accounts ba ON cs.bank_account_id = ba.id WHERE cs.id = $id")->fetch_assoc();
if (!$sale) { die("Sale not found"); }

$logo = $base_url . "modules/logo/WhatsApp%20Image%202026-07-04%20at%201.20.58%20PM.jpeg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #<?= htmlspecialchars($sale['invoice_no']) ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; color: #333; }
.invoice-wrapper { max-width: 800px; margin: 0 auto; }
.invoice {
    background: #fff; border-radius: 12px; padding: 40px 45px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative;
}
.invoice::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 5px; background: linear-gradient(90deg, #2C3E50, #F39C12);
    border-radius: 12px 12px 0 0;
}
.header { display: flex; align-items: center; gap: 25px; margin-bottom: 30px; padding-bottom: 25px; border-bottom: 3px solid #2C3E50; }
.header .logo { width: 95px; height: 95px; border-radius: 50%; overflow: hidden; border: 4px solid #F39C12; flex-shrink: 0; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
.header .logo img { width: 100%; height: 100%; object-fit: cover; }
.header .brand { flex: 1; }
.header .brand h1 { font-size: 28px; color: #2C3E50; font-weight: 900; margin: 0; letter-spacing: -0.5px; }
.header .brand .sub { font-size: 13px; color: #F39C12; font-weight: 700; text-transform: uppercase; letter-spacing: 3px; margin-top: 2px; }
.header .brand .contact { font-size: 14px; color: #555; margin-top: 8px; display: flex; gap: 25px; flex-wrap: wrap; }
.header .brand .contact span { display: inline-flex; align-items: center; gap: 6px; }
.header .brand .contact i { color: #F39C12; font-style: normal; }
.title-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
.title-row .invoice-title h2 { font-size: 22px; color: #2C3E50; font-weight: 700; margin: 0; }
.title-row .invoice-title p { font-size: 13px; color: #888; margin-top: 3px; }
.title-row .status-badge {
    padding: 8px 20px; border-radius: 20px; font-weight: 700; font-size: 13px;
    text-transform: uppercase; letter-spacing: 1px;
}
.status-badge.cash { background: #d4edda; color: #155724; }
.status-badge.credit { background: #fff3cd; color: #856404; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; background: #f8f9fc; padding: 20px; border-radius: 8px; }
.info-grid .info-item label { font-size: 11px; text-transform: uppercase; color: #888; font-weight: 600; letter-spacing: 0.5px; display: block; margin-bottom: 3px; }
.info-grid .info-item .value { font-size: 15px; color: #333; font-weight: 600; }
table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
table thead th {
    background: #2C3E50; color: #fff; padding: 12px 14px; font-size: 12px;
    text-transform: uppercase; letter-spacing: 0.5px; text-align: left;
}
table thead th.text-right { text-align: right; }
table tbody td { padding: 12px 14px; border-bottom: 1px solid #eee; font-size: 14px; }
table tbody td.text-right { text-align: right; }
table tbody tr:last-child td { border-bottom: none; }
table tfoot td {
    padding: 10px 14px; font-size: 14px; font-weight: 600; border-top: 2px solid #2C3E50;
}
table tfoot td.text-right { text-align: right; }
table tfoot .grand-total td { font-size: 16px; background: #f8f9fc; border-top: 2px solid #F39C12; }
.payment-info { background: #f8f9fc; padding: 18px 20px; border-radius: 8px; margin-bottom: 25px; }
.payment-info h4 { font-size: 13px; text-transform: uppercase; color: #888; margin-bottom: 8px; letter-spacing: 0.5px; }
.payment-info .row { display: flex; gap: 30px; flex-wrap: wrap; }
.payment-info .row span { font-size: 14px; color: #333; }
.payment-info .row span strong { color: #2C3E50; margin-right: 5px; }
.signature { display: flex; justify-content: space-between; margin-top: 35px; padding-top: 20px; border-top: 1px solid #ddd; }
.signature .sig-item { text-align: center; min-width: 180px; }
.signature .sig-item .line { width: 180px; height: 1px; border-top: 1px solid #333; margin: 10px auto 6px; }
.signature .sig-item p { font-size: 12px; color: #888; margin: 0; }
.footer-note { text-align: center; margin-top: 25px; font-size: 12px; color: #aaa; border-top: 1px solid #eee; padding-top: 15px; }
.no-print { text-align: center; margin-top: 20px; }
.no-print .btn-print {
    display: inline-block; padding: 12px 40px; background: #2C3E50; color: #fff;
    text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;
    cursor: pointer; border: none;
}
.no-print .btn-print:hover { background: #1A252F; }
.no-print .btn-back {
    display: inline-block; padding: 12px 30px; background: #6c757d; color: #fff;
    text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;
    margin-left: 10px;
}
@page { margin: 15mm; }
@media print {
    body { background: #fff; padding: 0; }
    .invoice-wrapper { max-width: 100%; }
    .invoice { box-shadow: none; border-radius: 0; padding: 20px 30px; }
    .invoice::before { display: none; }
    .no-print { display: none; }
    table thead th { background: #2C3E50 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>
<div class="invoice-wrapper">
    <div class="invoice">
        <div class="header">
            <div class="logo">
                <img src="<?= $logo ?>" alt="Logo">
            </div>
            <div class="brand">
                <h1>Muhammad Younas</h1>
                <div class="sub">Diesel Management System</div>
                <div class="contact">
                    <span><i>&#9742;</i> +93 70 260 7159</span>
                    <span><i>&#9993;</i> info@myounas.com</span>
                </div>
            </div>
        </div>

        <div class="title-row">
            <div class="invoice-title">
                <h2>SALE INVOICE</h2>
                <p>Tax Invoice / Payment Receipt</p>
            </div>
            <div class="status-badge <?= $sale['payment_type'] === 'Credit' ? 'credit' : 'cash' ?>">
                <?= htmlspecialchars($sale['payment_type'] === 'Credit' ? 'ON CREDIT' : 'PAID') ?>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <label>Invoice No</label>
                <div class="value">#<?= htmlspecialchars($sale['invoice_no']) ?></div>
            </div>
            <div class="info-item">
                <label>Date</label>
                <div class="value"><?= date('d-M-Y', strtotime($sale['sale_date'])) ?></div>
            </div>
            <div class="info-item">
                <label>Customer</label>
                <div class="value"><?= htmlspecialchars($sale['customer_name']) ?></div>
            </div>
            <div class="info-item">
                <label>Mobile</label>
                <div class="value"><?= htmlspecialchars($sale['mobile'] ?: '-') ?></div>
            </div>
            <?php if ($sale['vehicle_number']): ?>
            <div class="info-item">
                <label>Vehicle No</label>
                <div class="value"><?= htmlspecialchars($sale['vehicle_number']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($sale['driver_info']): ?>
            <div class="info-item">
                <label>Driver</label>
                <div class="value"><?= htmlspecialchars($sale['driver_info']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($sale['delivery_location']): ?>
            <div class="info-item">
                <label>Delivery Location</label>
                <div class="value"><?= htmlspecialchars($sale['delivery_location']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Qty (Ton)</th>
                    <th class="text-right">Rate/Ton</th>
                    <th class="text-right">Waste (Kg)</th>
                    <th class="text-right">Net Qty</th>
                    <th class="text-right">Amount ($)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Diesel Sale</td>
                    <td class="text-right"><?= number_format($sale['quantity'], 3) ?></td>
                    <td class="text-right"><?= number_format($sale['rate_per_ton'], 2) ?></td>
                    <td class="text-right"><?= number_format($sale['waste_kg'], 3) ?></td>
                    <td class="text-right"><?= number_format($sale['net_quantity'], 3) ?></td>
                    <td class="text-right"><?= number_format($sale['total_amount'], 2) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">Total Amount:</td>
                    <td class="text-right"><?= number_format($sale['total_amount'], 2) ?></td>
                </tr>
                <?php if ($sale['freight_charges'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-right">Freight Charges:</td>
                    <td class="text-right"><?= number_format($sale['freight_charges'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($sale['other_charges'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-right">Other Charges:</td>
                    <td class="text-right"><?= number_format($sale['other_charges'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td colspan="5" class="text-right">Net Amount:</td>
                    <td class="text-right">$ <?= number_format($sale['net_amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="payment-info">
            <h4>Payment Details</h4>
            <div class="row">
                <span><strong>Status:</strong> <?= htmlspecialchars($sale['payment_type']) ?></span>
                <span><strong>Method:</strong> <?= htmlspecialchars($sale['payment_method'] ?: '-') ?></span>
                <?php if ($sale['account_name']): ?>
                <span><strong>Account:</strong> <?= htmlspecialchars($sale['bank_name'] ? $sale['bank_name'] . ' — ' : '') . htmlspecialchars($sale['account_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="signature">
            <div class="sig-item">
                <div class="line"></div>
                <p>Customer Signature</p>
            </div>
            <div class="sig-item">
                <div class="line"></div>
                <p>Authorized Signature</p>
            </div>
        </div>

        <div class="footer-note">
            Muhammad Younas &mdash; Diesel Management System &bull; +93 70 260 7159
        </div>
    </div>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
        <a href="list.php" class="btn-back">&larr; Back to Sales</a>
    </div>
</div>
</body>
</html>
