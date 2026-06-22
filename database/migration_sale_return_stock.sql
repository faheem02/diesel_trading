-- Add tank_id to sale_returns for stock tracking
ALTER TABLE sale_returns ADD COLUMN tank_id INT DEFAULT NULL AFTER sale_id;

-- Extend stock_ledger reference_type to support sale_return
ALTER TABLE stock_ledger MODIFY COLUMN reference_type ENUM('purchase','sale','adjustment','sale_return') DEFAULT NULL;
