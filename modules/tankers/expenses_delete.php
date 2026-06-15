<?php
session_start();
require_once '../../config/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $conn->query("DELETE FROM tanker_expenses WHERE id = $id");
}
header("Location: expenses_list.php");
exit;
