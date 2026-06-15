CREATE TABLE IF NOT EXISTS customer_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(100) NOT NULL UNIQUE,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(255) NOT NULL,
    mobile VARCHAR(20),
    sale_date DATE NOT NULL,
    vehicle_number VARCHAR(100),
    quantity DECIMAL(12,3) NOT NULL,
    rate_per_ton DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_type ENUM('Cash','Credit') NOT NULL DEFAULT 'Cash',
    driver_info VARCHAR(255),
    delivery_location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
