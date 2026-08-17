-- MT Data Database Schema & Initial Seed Data
-- Strict Cloud MySQL (Aiven, Render, AWS RDS) Compatible

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admins` (`id`, `fullname`, `username`, `password`, `created_at`) 
VALUES (1, 'System Administrator', 'admin', '$2y$12$e6kK.1z3oZz43u0G2fXoDeT8Cj3c4sR69h6eF0.49YwG9E7h8H8S6', '2026-08-04 07:49:31')
ON DUPLICATE KEY UPDATE `id`=`id`;

CREATE TABLE IF NOT EXISTS `networks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `network_name` varchar(50) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `networks` (`id`, `network_name`, `logo`, `status`) VALUES
(1, 'MTN', 'assets/images/networks/mtn.png', 'Active'),
(2, 'Airtel', 'assets/images/networks/airtel.png', 'Active'),
(3, 'Glo', 'assets/images/networks/glo.png', 'Active'),
(4, '9mobile', 'assets/images/networks/9mobile.png', 'Active')
ON DUPLICATE KEY UPDATE `id`=`id`;

CREATE TABLE IF NOT EXISTS `airtime_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `network_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `network_id` (`network_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cable_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(100) NOT NULL,
  `logo` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cable_providers` (`id`, `provider_name`, `logo`) VALUES
(1, 'DSTV', 'assets/images/dstv.png'),
(2, 'GOTV', 'assets/images/gotv.png'),
(3, 'Startimes', 'assets/images/startimes.png')
ON DUPLICATE KEY UPDATE `id`=`id`;

CREATE TABLE IF NOT EXISTS `cable_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) NOT NULL,
  `plan_name` varchar(150) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `provider_id` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cable_plans` (`id`, `provider_id`, `plan_name`, `amount`) VALUES
(1, 1, 'DSTV Compact', 12500.00),
(2, 1, 'DSTV Compact Plus', 19800.00),
(3, 1, 'DSTV Premium', 37000.00),
(4, 2, 'GOTV Smallie', 1900.00),
(5, 2, 'GOTV Jinja', 3900.00),
(6, 2, 'GOTV Max', 7200.00),
(7, 3, 'Startimes Basic', 3300.00),
(8, 3, 'Startimes Smart', 4500.00),
(9, 3, 'Startimes Classic', 6200.00)
ON DUPLICATE KEY UPDATE `id`=`id`;

CREATE TABLE IF NOT EXISTS `data_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `network_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `network_id` (`network_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `data_plans` (`id`, `network_id`, `plan_name`, `amount`) VALUES
(1, 1, '500MB Daily', 150.00),
(2, 1, '1GB Daily', 300.00),
(3, 1, '2GB Weekly', 800.00),
(4, 2, '500MB Daily', 150.00),
(5, 2, '1GB Daily', 300.00),
(6, 2, '2GB Weekly', 800.00),
(7, 3, '500MB Daily', 150.00),
(8, 3, '1GB Daily', 300.00),
(9, 4, '500MB Daily', 150.00),
(10, 4, '1GB Daily', 300.00)
ON DUPLICATE KEY UPDATE `id`=`id`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `phone` varchar(20) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `wallet_balance` decimal(12,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `face_descriptor` longtext DEFAULT NULL,
  `face_photo` mediumtext DEFAULT NULL,
  `face_enrolled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_type` enum('Data','Airtime','Cable') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wallet_funding` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `funded_by` int(11) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Card Payment',
  `reference` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Completed',
  `funded_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `funded_by` (`funded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `service_type` varchar(50) DEFAULT 'System',
  `channels` varchar(100) DEFAULT 'in_app,email',
  `metadata` longtext DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
