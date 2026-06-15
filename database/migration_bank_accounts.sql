CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(200) NOT NULL,
    account_type ENUM('Cash','Bank') NOT NULL DEFAULT 'Bank',
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    current_balance DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE customer_ledger ADD COLUMN bank_account_id INT DEFAULT NULL AFTER reference_id;
ALTER TABLE customer_ledger ADD FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL;
