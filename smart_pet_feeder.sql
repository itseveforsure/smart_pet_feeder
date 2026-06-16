-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 16, 2026 at 07:23 PM
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
-- Database: `smart_pet_feeder`
--

-- --------------------------------------------------------

--
-- Table structure for table `feeder_device_status`
--

CREATE TABLE `feeder_device_status` (
  `id` int(11) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `is_online` tinyint(4) DEFAULT 0,
  `food_level` int(11) DEFAULT 100,
  `water_level` int(11) DEFAULT 100,
  `last_heartbeat` timestamp NOT NULL DEFAULT current_timestamp(),
  `battery_level` int(11) DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_device_status`
--

INSERT INTO `feeder_device_status` (`id`, `device_id`, `is_online`, `food_level`, `water_level`, `last_heartbeat`, `battery_level`) VALUES
(1, 'SMARTFEEDER_001', 1, 80, 75, '2026-05-25 00:50:19', 100);

-- --------------------------------------------------------

--
-- Table structure for table `feeder_history`
--

CREATE TABLE `feeder_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `portion_size` int(11) NOT NULL,
  `feed_time` datetime DEFAULT current_timestamp(),
  `status` enum('success','failed','skipped','manual') DEFAULT 'success',
  `source` enum('schedule','manual','app') DEFAULT 'manual',
  `notification_sent` tinyint(4) DEFAULT 0,
  `schedule_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_history`
--

INSERT INTO `feeder_history` (`id`, `user_id`, `pet_id`, `portion_size`, `feed_time`, `status`, `source`, `notification_sent`, `schedule_id`) VALUES
(1, 3, 3, 50, '2026-05-25 14:00:01', 'success', 'manual', 0, NULL),
(2, 3, 3, 50, '2026-05-27 16:18:39', 'success', 'manual', 0, NULL),
(3, 3, 3, 50, '2026-05-28 10:50:05', 'success', 'manual', 0, NULL),
(4, 3, 3, 50, '2026-06-06 12:44:14', 'success', 'manual', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feeder_login_attempts`
--

CREATE TABLE `feeder_login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username_attempted` varchar(100) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feeder_notifications`
--

CREATE TABLE `feeder_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('water','feeding','system','success','warning') DEFAULT 'system',
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_notifications`
--

INSERT INTO `feeder_notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 3, '???? Test Notification', 'This is a test notification from the system.', 'system', 0, '2026-05-25 05:51:50'),
(2, 3, '???? New Pet Added', 'nyomet has been added to your profile.', 'system', 0, '2026-05-25 05:55:32'),
(3, 3, '⏰ Schedule Created', 'New feeding schedule added for 08:00 (50g fixed portion).', 'success', 0, '2026-05-25 05:59:49'),
(4, 3, '✅ Manual Feeding', 'Dispensed 50g of food for your pet.', 'success', 0, '2026-05-25 06:00:01'),
(5, 3, '✅ Manual Feeding', 'Dispensed 50g of food for your pet.', 'success', 0, '2026-05-27 08:18:39'),
(6, 3, '✅ Manual Feeding', 'Dispensed 50g of food for your pet.', 'success', 0, '2026-05-28 02:50:05'),
(7, 3, 'Welcome Back!', 'Welcome back to Smart Pet Feeder, Nur Esya!', 'success', 0, '2026-06-06 04:44:07'),
(8, 3, '✅ Manual Feeding', 'Dispensed 50g of food for your pet.', 'success', 0, '2026-06-06 04:44:14'),
(9, 3, 'Welcome Back!', 'Welcome back to Smart Pet Feeder, Nur Esya!', 'success', 0, '2026-06-16 08:23:47');

-- --------------------------------------------------------

--
-- Table structure for table `feeder_pets`
--

CREATE TABLE `feeder_pets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pet_name` varchar(50) NOT NULL,
  `pet_type` varchar(50) NOT NULL,
  `breed` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default_pet.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_pets`
--

INSERT INTO `feeder_pets` (`id`, `user_id`, `pet_name`, `pet_type`, `breed`, `age`, `weight`, `avatar`, `created_at`) VALUES
(1, 2, 'Max', 'Dog', 'Golden Retriever', 3, 25.50, 'default_pet.png', '2026-05-25 00:50:19'),
(2, 2, 'Max', 'Dog', 'Golden Retriever', 3, 25.50, 'default_pet.png', '2026-05-25 00:57:01'),
(3, 3, 'nyomet', 'Cat', 'Persian', 1, 4.00, 'default_pet.png', '2026-05-25 05:55:32');

-- --------------------------------------------------------

--
-- Table structure for table `feeder_schedule`
--

CREATE TABLE `feeder_schedule` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `portion_size` int(11) NOT NULL,
  `feed_time` time NOT NULL,
  `feed_days` varchar(50) NOT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_schedule`
--

INSERT INTO `feeder_schedule` (`id`, `user_id`, `pet_id`, `portion_size`, `feed_time`, `feed_days`, `is_active`, `created_at`) VALUES
(1, 3, 3, 50, '08:00:00', 'Daily', 1, '2026-05-25 05:59:49');

-- --------------------------------------------------------

--
-- Table structure for table `feeder_users`
--

CREATE TABLE `feeder_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `dark_mode` tinyint(4) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL,
  `last_password_reset` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_users`
--

INSERT INTO `feeder_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role`, `created_at`, `last_login`, `dark_mode`, `status`, `reset_token`, `reset_expires`, `remember_token`, `remember_expires`, `last_password_reset`) VALUES
(1, 'admin', 'admin@smartfeeder.com', '$2y$10$uqc2dVkudhx5pgkuOx2WXuqgIEZMKwfaqaPkGHFyT.5SchoI4zdwG', 'System Admin', NULL, 'admin', '2026-05-25 00:50:19', NULL, 0, 'active', NULL, NULL, NULL, NULL, NULL),
(2, 'petlover', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pet Lover', '+1234567890', 'user', '2026-05-25 00:50:19', NULL, 0, 'active', NULL, NULL, NULL, NULL, NULL),
(3, 'Esya', 'monicabae67@gmail.com', '$2y$10$lMU29YRmZfiBDIzqTyBhr.Z35PwmL7Y6N0DmgFqJQ3gWwYFm7vbzW', 'Nur Esya', '', 'user', '2026-05-25 00:55:20', '2026-06-16 08:23:47', 0, 'active', '7f89fab5e385ce8ccfb9e1c8bd0135db1700801c0e3200f6de054d88ce1720a3', '2026-06-06 13:17:59', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feeder_water_level`
--

CREATE TABLE `feeder_water_level` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `water_level_percent` int(11) DEFAULT 100,
  `is_low` tinyint(4) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notification_sent` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeder_water_level`
--

INSERT INTO `feeder_water_level` (`id`, `user_id`, `water_level_percent`, `is_low`, `last_updated`, `notification_sent`) VALUES
(1, 1, 100, 0, '2026-05-25 00:50:19', 0),
(2, 2, 75, 0, '2026-05-25 00:50:19', 0),
(3, 3, 100, 0, '2026-05-25 00:55:20', 0),
(4, 1, 100, 0, '2026-05-25 00:57:01', 0),
(5, 2, 75, 0, '2026-05-25 00:57:01', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feeder_device_status`
--
ALTER TABLE `feeder_device_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`);

--
-- Indexes for table `feeder_history`
--
ALTER TABLE `feeder_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `feeder_login_attempts`
--
ALTER TABLE `feeder_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);

--
-- Indexes for table `feeder_notifications`
--
ALTER TABLE `feeder_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feeder_pets`
--
ALTER TABLE `feeder_pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feeder_schedule`
--
ALTER TABLE `feeder_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `feeder_users`
--
ALTER TABLE `feeder_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_reset_token` (`reset_token`),
  ADD KEY `idx_remember_token` (`remember_token`);

--
-- Indexes for table `feeder_water_level`
--
ALTER TABLE `feeder_water_level`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `feeder_device_status`
--
ALTER TABLE `feeder_device_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feeder_history`
--
ALTER TABLE `feeder_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feeder_login_attempts`
--
ALTER TABLE `feeder_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feeder_notifications`
--
ALTER TABLE `feeder_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `feeder_pets`
--
ALTER TABLE `feeder_pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feeder_schedule`
--
ALTER TABLE `feeder_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feeder_users`
--
ALTER TABLE `feeder_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `feeder_water_level`
--
ALTER TABLE `feeder_water_level`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feeder_history`
--
ALTER TABLE `feeder_history`
  ADD CONSTRAINT `feeder_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `feeder_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feeder_history_ibfk_2` FOREIGN KEY (`pet_id`) REFERENCES `feeder_pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feeder_notifications`
--
ALTER TABLE `feeder_notifications`
  ADD CONSTRAINT `feeder_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `feeder_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feeder_pets`
--
ALTER TABLE `feeder_pets`
  ADD CONSTRAINT `feeder_pets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `feeder_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feeder_schedule`
--
ALTER TABLE `feeder_schedule`
  ADD CONSTRAINT `feeder_schedule_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `feeder_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feeder_schedule_ibfk_2` FOREIGN KEY (`pet_id`) REFERENCES `feeder_pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feeder_water_level`
--
ALTER TABLE `feeder_water_level`
  ADD CONSTRAINT `feeder_water_level_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `feeder_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
