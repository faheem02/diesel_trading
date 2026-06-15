-- Migration: Add bank_account_id and payment_method tracking to supplier_ledger
ALTER TABLE supplier_ledger
    ADD COLUMN IF NOT EXISTS bank_account_id INT DEFAULT NULL AFTER reference_id,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL AFTER bank_account_id;

ALTER TABLE supplier_ledger
    ADD CONSTRAINT fk_sl_bank_account
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL;

-- Add payment_method tracking to customer_ledger (bank_account_id already exists)
ALTER TABLE customer_ledger
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL AFTER bank_account_id;

-- Add payment_method and bank_account_id to expenses so cash/bank expenses show in books
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash' AFTER description,
    ADD COLUMN IF NOT EXISTS bank_account_id INT DEFAULT NULL AFTER payment_method;

ALTER TABLE expenses
    ADD CONSTRAINT fk_exp_bank_account
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL;
