ALTER TABLE bank_accounts
    ADD COLUMN IF NOT EXISTS account_number VARCHAR(100) DEFAULT NULL AFTER account_name;
