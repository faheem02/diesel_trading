<?php
session_start();
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$conn->begin_transaction();
try {
    $purchase = $conn->query("SELECT * FROM purchases WHERE id = $id")->fetch_assoc();
    if (!$purchase) {
        header("Location: list.php");
        exit;
    }

    $tankers = $conn->query("SELECT id, tank_id, diesel_quantity FROM purchase_tankers WHERE purchase_id = $id");
    while ($t = $tankers->fetch_assoc()) {
        if ($t['tank_id'] > 0) {
            $conn->query("UPDATE tanks SET current_stock = current_stock - {$t['diesel_quantity']} WHERE id = {$t['tank_id']}");
            $conn->query("DELETE FROM stock_ledger WHERE reference_type = 'purchase' AND reference_id = $id AND tank_id = {$t['tank_id']}");
        }
    }

    $conn->query("DELETE FROM purchase_tankers WHERE purchase_id = $id");
    $conn->query("DELETE FROM supplier_ledger WHERE reference_type = 'purchase' AND description LIKE '%Invoice #" . $purchase['invoice_no'] . "%'");
    $conn->query("DELETE FROM purchases WHERE id = $id");

    $conn->commit();
    header("Location: list.php?deleted=1");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: list.php?error=" . urlencode($e->getMessage()));
}
exit;
