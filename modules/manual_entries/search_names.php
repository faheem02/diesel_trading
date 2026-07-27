<?php
require_once '../../includes/db.php';
$term = trim($_GET['term'] ?? '');
if (empty($term)) { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT DISTINCT person_name FROM manual_entries WHERE person_name LIKE ? ORDER BY person_name ASC LIMIT 15");
$search = "%$term%";
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();
$names = [];
while ($row = $result->fetch_assoc()) {
    $names[] = $row['person_name'];
}
$stmt->close();
header('Content-Type: application/json');
echo json_encode($names);
