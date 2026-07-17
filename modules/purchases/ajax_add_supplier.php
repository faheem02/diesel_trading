<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/db.php';

$company_name    = trim($_POST['company_name'] ?? '');
$contact_person  = trim($_POST['contact_person'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$ntn_cnic        = trim($_POST['ntn_cnic'] ?? '');
$address         = trim($_POST['address'] ?? '');
$opening_balance = floatval($_POST['opening_balance'] ?? 0);

if (empty($company_name)) {
    echo json_encode(['success' => false, 'message' => 'Company name is required.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO suppliers (company_name, contact_person, phone, ntn_cnic, address, balance, opening_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssdd", $company_name, $contact_person, $phone, $ntn_cnic, $address, $opening_balance, $opening_balance);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $stmt->close();

    if ($opening_balance != 0) {
        $ob_desc = "Opening Balance";
        $today = date('Y-m-d');
        if ($opening_balance > 0) {
            $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($new_id, '$today', '$ob_desc', 0, $opening_balance, $opening_balance, 'opening_balance')");
        } else {
            $pos = abs($opening_balance);
            $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($new_id, '$today', '$ob_desc', $pos, 0, $opening_balance, 'opening_balance')");
        }
    }

    echo json_encode(['success' => true, 'id' => $new_id, 'company_name' => $company_name]);
} else {
    $error = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
}
?>
