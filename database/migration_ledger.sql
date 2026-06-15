CREATE TABLE IF NOT EXISTS supplier_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    debit DECIMAL(12,2) DEFAULT 0.00,
    credit DECIMAL(12,2) DEFAULT 0.00,
    balance DECIMAL(12,2) DEFAULT 0.00,
    reference_type ENUM('opening_balance','purchase','payment','return','adjustment') NOT NULL,
    reference_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
