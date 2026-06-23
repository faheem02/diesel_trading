<?php
require_once 'includes/db.php';

$plain_password = 'admin123';

$stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $plain_password, $role);
$name  = "Administrator";
$email = "admin";
$role  = "admin";

if ($stmt->execute()) {
    echo "✅ Admin user created successfully!<br>Login: <strong>admin</strong> / <strong>password</strong>";
} else {
    echo "❌ Error: " . $stmt->error;
}
?>
