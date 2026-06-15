CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20),
    address TEXT,
    ntn_cnic VARCHAR(50) DEFAULT NULL,
    balance DECIMAL(12,2) DEFAULT 0.00,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    purchase_date DATE NOT NULL,
    supplier_id INT NOT NULL,
    tanker_number VARCHAR(50),
    driver_name VARCHAR(100),
    driver_mobile VARCHAR(20),
    diesel_quantity DECIMAL(12,3) NOT NULL,
    rate_per_ton DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    freight_charges DECIMAL(12,2) DEFAULT 0,
    other_charges DECIMAL(12,2) DEFAULT 0,
    net_purchase_cost DECIMAL(12,2) NOT NULL,
    payment_status ENUM('Paid','Partial Paid','Credit') DEFAULT 'Credit',
    paid_amount DECIMAL(12,2) DEFAULT 0,
    invoice_attachment VARCHAR(255),
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    return_date DATE NOT NULL,
    quantity_returned DECIMAL(12,3) NOT NULL,
    rate_per_ton DECIMAL(12,2) NOT NULL,
    return_amount DECIMAL(12,2) NOT NULL,
    reason TEXT,
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    adjustment_date DATE NOT NULL,
    adjustment_type ENUM('Quantity','Rate','Amount') NOT NULL,
    old_value DECIMAL(12,3),
    new_value DECIMAL(12,3),
    reason TEXT,
    adjusted_by VARCHAR(100),
    created_at DATE DEFAULT (CURRENT_DATE),
    updated_at DATE DEFAULT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO suppliers (company_name, phone, address) VALUES
('ABC Fuel Suppliers', '03001234567', 'Lahore, Pakistan'),
('XYZ Diesel Co.', '03007654321', 'Karachi, Pakistan');
