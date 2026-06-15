<?php
// Run this file ONCE to create the database and tables.
// Visit: http://localhost/diesel_cashbook/install.php

$host = 'localhost';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = "
CREATE DATABASE IF NOT EXISTS diesel_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE diesel_management;

CREATE TABLE IF NOT EXISTS transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    book        ENUM('cash','bank') NOT NULL,
    type        ENUM('in','out') NOT NULL,
    txn_date    DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    category    VARCHAR(100) NOT NULL,
    amount      DECIMAL(15,2) NOT NULL,
    reference   VARCHAR(100) DEFAULT '',
    notes       TEXT DEFAULT '',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO transactions (book, type, txn_date, description, category, amount, reference, notes) VALUES
('cash','in', '2025-06-01','Opening balance — cash on hand','Customer receipt',150000.00,'OB-001',''),
('cash','out','2025-06-03','Diesel purchase — Al-Hamra Depot','Diesel purchase',85000.00,'VCH-002','500 litres @ PKR 170'),
('cash','in', '2025-06-05','Diesel sale — Khan Transport','Diesel sale',122500.00,'INV-001','700 litres @ PKR 175'),
('cash','out','2025-06-07','Driver salary — June','Salary',35000.00,'SAL-001',''),
('bank','in', '2025-06-01','Opening bank balance','Customer receipt',500000.00,'OB-B01','MCB current account'),
('bank','out','2025-06-04','Supplier payment — Attock Petroleum','Supplier payment',250000.00,'TRF-001','Invoice #AP-2245'),
('bank','in', '2025-06-06','Customer receipt — City Logistics','Customer receipt',180000.00,'RCP-002',''),
('bank','out','2025-06-08','Tax payment — FBR','Tax / duty',45000.00,'TAX-001','');
";

if ($conn->multi_query($sql)) {
    echo '<div style="font-family:sans-serif;padding:2rem;background:#e8f5e9;border-radius:8px;max-width:500px;margin:2rem auto">';
    echo '<h2 style="color:#2e7d32">✅ Installation successful!</h2>';
    echo '<p>Database <strong>diesel_management</strong> and table <strong>transactions</strong> created with sample data.</p>';
    echo '<p><a href="index.php" style="background:#1976d2;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none">→ Open Cash Book</a></p>';
    echo '</div>';
} else {
    echo '<p style="color:red">Error: ' . $conn->error . '</p>';
}
$conn->close();
