<?php
session_start();
require_once 'includes/config.php';
if (isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "dashboard.php");
} else {
    header("Location: " . $base_url . "auth/login.php");
}
exit;
