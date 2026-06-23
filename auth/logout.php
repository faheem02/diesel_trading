<?php
session_start();
session_destroy();
require_once '../includes/config.php';
header("Location: " . $base_url . "auth/login.php");
exit;
