-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 12:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `datavending`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `username`, `password`, `created_at`) VALUES
(1, 'System Administrator', 'admin', 'admin123', '2026-08-04 07:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `airtime_settings`
--

CREATE TABLE `airtime_settings` (
  `id` int(11) NOT NULL,
  `network_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cable_plans`
--

CREATE TABLE `cable_plans` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `plan_name` varchar(150) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cable_plans`
--

INSERT INTO `cable_plans` (`id`, `provider_id`, `plan_name`, `amount`) VALUES
(1, 1, 'DSTV Compact', 12500.00),
(2, 1, 'DSTV Compact Plus', 19800.00),
(3, 1, 'DSTV Premium', 37000.00),
(4, 2, 'GOTV Smallie', 1900.00),
(5, 2, 'GOTV Jinja', 3900.00),
(6, 2, 'GOTV Max', 7200.00),
(7, 3, 'Startimes Basic', 3300.00),
(8, 3, 'Startimes Smart', 4500.00),
(9, 3, 'Startimes Classic', 6200.00);

-- --------------------------------------------------------

--
-- Table structure for table `cable_providers`
--

CREATE TABLE `cable_providers` (
  `id` int(11) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `logo` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cable_providers`
--

INSERT INTO `cable_providers` (`id`, `provider_name`, `logo`) VALUES
(1, 'DSTV', 'assets/images/dstv.png'),
(2, 'GOTV', 'assets/images/gotv.png'),
(3, 'Startimes', 'assets/images/startimes.png');

-- --------------------------------------------------------

--
-- Table structure for table `data_plans`
--

CREATE TABLE `data_plans` (
  `id` int(11) NOT NULL,
  `network_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `data_plans`
--

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
(10, 4, '1GB Daily', 300.00);

-- --------------------------------------------------------

--
-- Table structure for table `networks`
--

CREATE TABLE `networks` (
  `id` int(11) NOT NULL,
  `network_name` varchar(50) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `networks`
--

INSERT INTO `networks` (`id`, `network_name`, `logo`, `status`) VALUES
(1, 'MTN', 'assets/images/networks/mtn.png', 'Active'),
(2, 'Airtel', 'assets/images/networks/airtel.png', 'Active'),
(3, 'Glo', 'assets/images/networks/glo.png', 'Active'),
(4, '9mobile', 'assets/images/networks/9mobile.png', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_type` enum('Data','Airtime','Cable') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `wallet_balance` decimal(12,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `face_descriptor` longtext DEFAULT NULL,
  `face_photo` mediumtext DEFAULT NULL,
  `face_enrolled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_funding`
--

CREATE TABLE `wallet_funding` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `funded_by` int(11) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Card Payment',
  `reference` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Completed',
  `funded_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `airtime_settings`
--
ALTER TABLE `airtime_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `network_id` (`network_id`);

--
-- Indexes for table `cable_plans`
--
ALTER TABLE `cable_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `cable_providers`
--
ALTER TABLE `cable_providers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `data_plans`
--
ALTER TABLE `data_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `network_id` (`network_id`);

--
-- Indexes for table `networks`
--
ALTER TABLE `networks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `email_2` (`email`);

--
-- Indexes for table `wallet_funding`
--
ALTER TABLE `wallet_funding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `funded_by` (`funded_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `airtime_settings`
--
ALTER TABLE `airtime_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cable_plans`
--
ALTER TABLE `cable_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cable_providers`
--
ALTER TABLE `cable_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `data_plans`
--
ALTER TABLE `data_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `networks`
--
ALTER TABLE `networks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_funding`
--
ALTER TABLE `wallet_funding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `airtime_settings`
--
ALTER TABLE `airtime_settings`
  ADD CONSTRAINT `airtime_settings_ibfk_1` FOREIGN KEY (`network_id`) REFERENCES `networks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cable_plans`
--
ALTER TABLE `cable_plans`
  ADD CONSTRAINT `cable_plans_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `cable_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `data_plans`
--
ALTER TABLE `data_plans`
  ADD CONSTRAINT `data_plans_ibfk_1` FOREIGN KEY (`network_id`) REFERENCES `networks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_funding`
--
ALTER TABLE `wallet_funding`
  ADD CONSTRAINT `wallet_funding_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wallet_funding_ibfk_2` FOREIGN KEY (`funded_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
