CREATE TABLE IF NOT EXISTS tanker_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanker_id INT NOT NULL,
    expense_date DATE NOT NULL,
    expense_type ENUM('Fuel','Driver','Maintenance','Toll Tax','Other') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tanker_id) REFERENCES tankers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
