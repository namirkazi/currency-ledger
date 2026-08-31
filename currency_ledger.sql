-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 31, 2026 at 08:59 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `currency_ledger`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_balances`
--

DROP TABLE IF EXISTS `account_balances`;
CREATE TABLE `account_balances` (
  `currency_id` bigint(20) UNSIGNED NOT NULL,
  `balance` decimal(24,6) NOT NULL DEFAULT 0.000000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_balances`
--

INSERT INTO `account_balances` (`currency_id`, `balance`, `updated_at`) VALUES
(1, 36725.000000, '2026-08-31 06:02:14'),
(2, 27000.000000, '2026-08-30 13:00:43'),
(3, 0.000000, '2026-08-27 08:46:00'),
(4, 0.000000, '2026-08-27 08:46:00'),
(5, 0.000000, '2026-08-27 08:46:00'),
(6, 0.000000, '2026-08-27 08:46:00'),
(7, 0.000000, '2026-08-27 08:46:00'),
(8, 0.000000, '2026-08-27 08:46:00'),
(9, 0.000000, '2026-08-27 08:46:00'),
(10, 0.000000, '2026-08-27 08:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `balance_movements`
--

DROP TABLE IF EXISTS `balance_movements`;
CREATE TABLE `balance_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` enum('INFLOW','OUTFLOW') NOT NULL,
  `amount` decimal(24,6) NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `balance_movements`
--

INSERT INTO `balance_movements` (`id`, `currency_id`, `movement_type`, `amount`, `created_by`, `created_at`) VALUES
(1, 1, 'INFLOW', 100000.000000, 2, '2026-08-31 06:01:55'),
(2, 1, 'OUTFLOW', 100000.000000, 2, '2026-08-31 06:02:14');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` char(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(16) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `active`, `created_at`) VALUES
(1, 'AED', 'United Arab Emirates Dirham', 'د.إ', 1, '2026-08-27 08:46:00'),
(2, 'USD', 'United States Dollar', '$', 1, '2026-08-27 08:46:00'),
(3, 'INR', 'Indian Rupee', '₹', 1, '2026-08-27 08:46:00'),
(4, 'PKR', 'Pakistani Rupee', '₨', 1, '2026-08-27 08:46:00'),
(5, 'GBP', 'British Pound Sterling', '£', 1, '2026-08-27 08:46:00'),
(6, 'SAR', 'Saudi Riyal', '﷼', 1, '2026-08-27 08:46:00'),
(7, 'OMR', 'Omani Rial', '﷼', 1, '2026-08-27 08:46:00'),
(8, 'BHD', 'Bahraini Dinar', '.د.ب', 1, '2026-08-27 08:46:00'),
(9, 'QAR', 'Qatari Riyal', '﷼', 1, '2026-08-27 08:46:00'),
(10, 'KWD', 'Kuwaiti Dinar', 'د.ك', 1, '2026-08-27 08:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_lots`
--

DROP TABLE IF EXISTS `inventory_lots`;
CREATE TABLE `inventory_lots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `opening_balance_id` bigint(20) UNSIGNED DEFAULT NULL,
  `original_amount` decimal(24,6) NOT NULL,
  `remaining_amount` decimal(24,6) NOT NULL,
  `acquisition_rate` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `currency_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `ledger_entries`
--

DROP TABLE IF EXISTS `ledger_entries`;
CREATE TABLE `ledger_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(24,6) NOT NULL,
  `entry_type` enum('OPENING','TRADE','ADJUSTMENT') NOT NULL,
  `transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `opening_balance_id` bigint(20) UNSIGNED DEFAULT NULL,
  `balance_movement_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `ledger_entries`
--

INSERT INTO `ledger_entries` (`id`, `currency_id`, `amount`, `entry_type`, `transaction_id`, `opening_balance_id`, `balance_movement_id`, `created_at`) VALUES
(1, 1, 100000.000000, 'OPENING', NULL, 1, NULL, '2026-08-30 12:11:49'),
(2, 2, 10000.000000, 'OPENING', NULL, 2, NULL, '2026-08-30 12:12:09'),
(3, 2, -10000.000000, 'TRADE', 1, NULL, NULL, '2026-08-30 12:59:30'),
(4, 1, 36725.000000, 'TRADE', 1, NULL, NULL, '2026-08-30 12:59:30'),
(5, 1, -100000.000000, 'TRADE', 2, NULL, NULL, '2026-08-30 13:00:43'),
(6, 2, 27000.000000, 'TRADE', 2, NULL, NULL, '2026-08-30 13:00:43'),
(7, 1, 100000.000000, 'ADJUSTMENT', NULL, NULL, 1, '2026-08-31 06:01:55'),
(8, 1, -100000.000000, 'ADJUSTMENT', NULL, NULL, 2, '2026-08-31 06:02:14');

-- --------------------------------------------------------

--
-- Table structure for table `opening_balances`
--

DROP TABLE IF EXISTS `opening_balances`;
CREATE TABLE `opening_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(24,6) NOT NULL,
  `cost_rate` decimal(20,6) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `opening_balances`
--

INSERT INTO `opening_balances` (`id`, `currency_id`, `amount`, `cost_rate`, `created_by`, `created_at`) VALUES
(1, 1, 100000.000000, NULL, 2, '2026-08-30 12:11:49'),
(2, 2, 10000.000000, NULL, 2, '2026-08-30 12:12:09');

-- --------------------------------------------------------

--
-- Table structure for table `sale_allocations`
--

DROP TABLE IF EXISTS `sale_allocations`;
CREATE TABLE `sale_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_transaction_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_lot_id` bigint(20) UNSIGNED NOT NULL,
  `currency_amount` decimal(24,6) NOT NULL,
  `acquisition_rate` decimal(20,6) NOT NULL,
  `cost_amount` decimal(24,6) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` char(36) NOT NULL,
  `type` enum('BUY','SELL') NOT NULL,
  `from_currency_id` bigint(20) UNSIGNED NOT NULL,
  `from_amount` decimal(24,6) NOT NULL,
  `to_currency_id` bigint(20) UNSIGNED NOT NULL,
  `to_amount` decimal(24,6) NOT NULL,
  `exchange_rate` decimal(20,6) NOT NULL,
  `realized_profit` decimal(24,6) NOT NULL DEFAULT 0.000000,
  `status` enum('COMPLETED','VOIDED') NOT NULL DEFAULT 'COMPLETED',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `request_id`, `type`, `from_currency_id`, `from_amount`, `to_currency_id`, `to_amount`, `exchange_rate`, `realized_profit`, `status`, `created_by`, `created_at`) VALUES
(1, 'fb48aeee-eb07-4bdd-8f31-b9885b4d247c', 'BUY', 2, 10000.000000, 1, 36725.000000, 3.672500, 0.000000, 'COMPLETED', 2, '2026-08-30 12:59:30'),
(2, 'e3e8149c-8590-4190-a527-91c4bf5aac0c', 'BUY', 1, 100000.000000, 2, 27000.000000, 0.270000, 0.000000, 'COMPLETED', 2, '2026-08-30 13:00:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('ADMIN','USER') NOT NULL DEFAULT 'USER',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'Administrator', 'admin_', '$2y$10$71z5JapZKZOv2aGbuI43NOgyzFICZWLt8aiwxon755JqoxPhwl5bW', 'ADMIN', 0, '2026-08-27 08:46:00'),
(2, 'administrator', 'admin', '$2y$10$RNNaygzMyhIraqpNx.Xk5.jK/rURgeKWpUQlzc5X3mHdDk9DZ4E8e', 'ADMIN', 1, '2026-08-30 11:05:44'),
(3, 'Mohammed Namir Kazi', 'namir', '$2y$10$/xrMQagtwvLJCjxhMAS62.SgvLAFEyO8cqjRPd396Vw55Z4hmnJdO', 'ADMIN', 1, '2026-08-31 06:03:55');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_account_balances`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_account_balances`;
CREATE TABLE `v_account_balances` (
`currency_id` bigint(20) unsigned
,`code` char(3)
,`name` varchar(100)
,`symbol` varchar(16)
,`active` tinyint(1)
,`balance` decimal(24,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_inventory`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_inventory`;
CREATE TABLE `v_inventory` (
`id` bigint(20) unsigned
,`currency_id` bigint(20) unsigned
,`currency_code` char(3)
,`currency_name` varchar(100)
,`original_amount` decimal(24,6)
,`remaining_amount` decimal(24,6)
,`acquisition_rate` decimal(20,6)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `v_account_balances`
--
DROP TABLE IF EXISTS `v_account_balances`;

DROP VIEW IF EXISTS `v_account_balances`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_account_balances`  AS SELECT `c`.`id` AS `currency_id`, `c`.`code` AS `code`, `c`.`name` AS `name`, `c`.`symbol` AS `symbol`, `c`.`active` AS `active`, coalesce(`ab`.`balance`,0.000000) AS `balance` FROM (`currencies` `c` left join `account_balances` `ab` on(`ab`.`currency_id` = `c`.`id`)) WHERE `c`.`active` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `v_inventory`
--
DROP TABLE IF EXISTS `v_inventory`;

DROP VIEW IF EXISTS `v_inventory`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inventory`  AS SELECT `il`.`id` AS `id`, `il`.`currency_id` AS `currency_id`, `c`.`code` AS `currency_code`, `c`.`name` AS `currency_name`, `il`.`original_amount` AS `original_amount`, `il`.`remaining_amount` AS `remaining_amount`, `il`.`acquisition_rate` AS `acquisition_rate`, `il`.`created_at` AS `created_at` FROM (`inventory_lots` `il` join `currencies` `c` on(`c`.`id` = `il`.`currency_id`)) WHERE `il`.`remaining_amount` > 0 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_balances`
--
ALTER TABLE `account_balances`
  ADD PRIMARY KEY (`currency_id`);

--
-- Indexes for table `balance_movements`
--
ALTER TABLE `balance_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_balance_movements_currency_date` (`currency_id`,`created_at`),
  ADD KEY `idx_balance_movements_created_by` (`created_by`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_currencies_code` (`code`),
  ADD KEY `idx_currencies_active_code` (`active`,`code`);

--
-- Indexes for table `inventory_lots`
--
ALTER TABLE `inventory_lots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_source_transaction` (`source_transaction_id`),
  ADD UNIQUE KEY `uq_inventory_opening_balance` (`opening_balance_id`),
  ADD KEY `idx_inventory_currency_fifo` (`currency_id`,`created_at`,`id`);

--
-- Indexes for table `ledger_entries`
--
ALTER TABLE `ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ledger_currency_date` (`currency_id`,`created_at`,`id`),
  ADD KEY `idx_ledger_transaction` (`transaction_id`),
  ADD KEY `idx_ledger_opening` (`opening_balance_id`),
  ADD KEY `idx_ledger_movement` (`balance_movement_id`);

--
-- Indexes for table `opening_balances`
--
ALTER TABLE `opening_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_opening_balance_currency` (`currency_id`),
  ADD KEY `idx_opening_balances_created_by` (`created_by`);

--
-- Indexes for table `sale_allocations`
--
ALTER TABLE `sale_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_lot` (`sale_transaction_id`,`inventory_lot_id`),
  ADD KEY `idx_sale_allocations_lot` (`inventory_lot_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_transactions_request_id` (`request_id`),
  ADD KEY `idx_transactions_type_date` (`type`,`created_at`),
  ADD KEY `idx_transactions_created_by` (`created_by`),
  ADD KEY `fk_tx_from_currency_id` (`from_currency_id`),
  ADD KEY `fk_tx_to_currency_id` (`to_currency_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role_active` (`role`,`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `balance_movements`
--
ALTER TABLE `balance_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_lots`
--
ALTER TABLE `inventory_lots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ledger_entries`
--
ALTER TABLE `ledger_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opening_balances`
--
ALTER TABLE `opening_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_allocations`
--
ALTER TABLE `sale_allocations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_balances`
--
ALTER TABLE `account_balances`
  ADD CONSTRAINT `fk_account_balances_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `balance_movements`
--
ALTER TABLE `balance_movements`
  ADD CONSTRAINT `fk_balance_movements_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_balance_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_lots`
--
ALTER TABLE `inventory_lots`
  ADD CONSTRAINT `fk_inventory_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_opening_balance` FOREIGN KEY (`opening_balance_id`) REFERENCES `opening_balances` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_source_transaction` FOREIGN KEY (`source_transaction_id`) REFERENCES `transactions` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `ledger_entries`
--
ALTER TABLE `ledger_entries`
  ADD CONSTRAINT `fk_ledger_balance_movement` FOREIGN KEY (`balance_movement_id`) REFERENCES `balance_movements` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ledger_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ledger_opening` FOREIGN KEY (`opening_balance_id`) REFERENCES `opening_balances` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ledger_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `opening_balances`
--
ALTER TABLE `opening_balances`
  ADD CONSTRAINT `fk_opening_balances_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_opening_balances_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sale_allocations`
--
ALTER TABLE `sale_allocations`
  ADD CONSTRAINT `fk_sale_allocations_lot` FOREIGN KEY (`inventory_lot_id`) REFERENCES `inventory_lots` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sale_allocations_sale` FOREIGN KEY (`sale_transaction_id`) REFERENCES `transactions` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_from_currency` FOREIGN KEY (`from_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `fk_transactions_to_currency` FOREIGN KEY (`to_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tx_from_currency_id` FOREIGN KEY (`from_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `fk_tx_to_currency_id` FOREIGN KEY (`to_currency_id`) REFERENCES `currencies` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
