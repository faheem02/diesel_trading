<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/db.php';

$customer_name   = trim($_POST['customer_name'] ?? '');
$mobile          = trim($_POST['mobile'] ?? '');
$address         = trim($_POST['address'] ?? '');
$opening_balance = floatval($_POST['opening_balance'] ?? 0);

if (empty($customer_name)) {
    echo json_encode(['success' => false, 'message' => 'Customer name is required.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO customers (customer_name, mobile, address, opening_balance, credit_limit, balance) VALUES (?, ?, ?, ?, 0, ?)");
$stmt->bind_param("sssdd", $customer_name, $mobile, $address, $opening_balance, $opening_balance);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $stmt->close();

    if ($opening_balance != 0) {
        $ob_desc = "Opening Balance";
        $today = date('Y-m-d');
        if ($opening_balance > 0) {
            $conn->query("INSERT INTO customer_ledger (customer_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($new_id, '$today', '$ob_desc', $opening_balance, 0, $opening_balance, 'opening_balance')");
        } else {
            $pos = abs($opening_balance);
            $conn->query("INSERT INTO customer_ledger (customer_id, transaction_date, description, debit, credit, balance, reference_type) VALUES ($new_id, '$today', '$ob_desc', 0, $pos, $opening_balance, 'opening_balance')");
        }
    }

    echo json_encode(['success' => true, 'id' => $new_id, 'customer_name' => $customer_name, 'mobile' => $mobile]);
} else {
    $error = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
}
?>
