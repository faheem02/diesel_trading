ALTER TABLE `manual_entries`
  DROP COLUMN `rate_per_ton`,
  DROP COLUMN `quantity`,
  ADD COLUMN `person_name` varchar(150) NOT NULL AFTER `entry_date`,
  ADD COLUMN `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`;
