CREATE TABLE IF NOT EXISTS `manual_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sr_no` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `rate_per_ton` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
