<?php
session_start();
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

$expense = $conn->query("SELECT * FROM expenses WHERE id = $id")->fetch_assoc();
if (!$expense) { header("Location: list.php"); exit; }

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM expenses WHERE id = $id");
    $conn->commit();
    header("Location: list.php?deleted=1");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: list.php?error=" . urlencode($e->getMessage()));
}
exit;
