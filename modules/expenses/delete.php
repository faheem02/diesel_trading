<?php
session_start();
require_once '../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $conn->query("DELETE FROM expenses WHERE id = $id");
}
header("Location: list.php");
exit;
