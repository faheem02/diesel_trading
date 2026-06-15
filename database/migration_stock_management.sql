CREATE TABLE IF NOT EXISTS tanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tank_name VARCHAR(100) NOT NULL,
    capacity DECIMAL(12,3) DEFAULT 0,
    location VARCHAR(255),
    current_stock DECIMAL(12,3) DEFAULT 0,
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    sale_date DATE NOT NULL,
    tank_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_mobile VARCHAR(20),
    vehicle_number VARCHAR(50),
    quantity DECIMAL(12,3) NOT NULL,
    rate_per_ton DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_type ENUM('Cash','Credit') DEFAULT 'Cash',
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL,
    FOREIGN KEY (tank_id) REFERENCES tanks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_date DATE NOT NULL,
    tank_id INT NOT NULL,
    adjustment_type ENUM('Shortage','Leakage','Measurement_Difference') NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    description TEXT,
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL,
    FOREIGN KEY (tank_id) REFERENCES tanks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tank_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    movement_type ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
    reference_type ENUM('purchase','sale','adjustment') DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    balance_before DECIMAL(12,3) DEFAULT 0,
    balance_after DECIMAL(12,3) DEFAULT 0,
    description TEXT,
    created_at DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (tank_id) REFERENCES tanks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
