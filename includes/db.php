<?php
$host = "localhost";
$db   = "diesel_trading_fixed";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>