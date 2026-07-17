<?php
session_start();
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: parties.php"); exit; }

$party = $conn->query("SELECT * FROM personal_accounts WHERE id = $id")->fetch_assoc();
if (!$party) { header("Location: parties.php"); exit; }

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM personal_ledger WHERE account_id = $id");
    $conn->query("DELETE FROM personal_accounts WHERE id = $id");
    $conn->commit();
    header("Location: parties.php?deleted=1");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: parties.php?error=" . urlencode($e->getMessage()));
}
exit;
