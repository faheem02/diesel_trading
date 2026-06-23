<?php
require_once 'includes/db.php';
require_once 'includes/config.php';

$plain_password = 'admin123';

$stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $plain_password, $role);
$name  = "Administrator";
$email = "admin";
$role  = "admin";

if ($stmt->execute()) {
    echo "✅ Admin user created successfully!<br>Login: <strong>admin</strong> / <strong>admin123</strong><br>";
    echo "<a href=\"" . $base_url . "auth/login.php\">Go to Login</a>";
} else {
    echo "❌ Error: " . $stmt->error;
}
?>
