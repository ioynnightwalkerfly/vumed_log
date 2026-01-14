-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 13, 2026 at 04:56 PM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 8.3.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sql_vumedhr`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--

CREATE TABLE `academic_terms` (
  `id` int(11) NOT NULL,
  `term_name` varchar(50) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_terms`
--

INSERT INTO `academic_terms` (`id`, `term_name`, `academic_year`) VALUES
(1, 'ภาคเรียนที่ 1', '2568'),
(2, 'ภาคเรียนที่ 2', '2568'),
(3, 'ตลอดปีการศึกษา', '2568');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','manager','staff','staff_lead') DEFAULT 'user',
  `role_position` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-

-- --------------------------------------------------------

--
-- Table structure for table `workload_categories`
--

CREATE TABLE `workload_categories` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `main_area` tinyint(1) NOT NULL,
  `name_th` varchar(255) NOT NULL,
  `calc_type` varchar(50) DEFAULT 'STATIC',
  `weight` decimal(8,2) DEFAULT 0.00,
  `max_terms` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `target_group` enum('teacher','staff','both') DEFAULT 'teacher'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Table structure for table `workload_items`
--

CREATE TABLE `workload_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `term_id` varchar(10) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `fund_source` varchar(255) DEFAULT NULL,
  `fund_amount` decimal(10,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) DEFAULT 0.00,
  `hours_lec` decimal(10,2) DEFAULT 0.00,
  `hours_lab` decimal(10,2) DEFAULT 0.00,
  `computed_hours` decimal(8,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `evidence` text DEFAULT NULL,
  `status` enum('pending','verified','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` text DEFAULT NULL,
  `last_reject_comment` text DEFAULT NULL,
  `last_reject_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `project_count` int(11) DEFAULT 0,
  `week_count` int(11) DEFAULT 0,
  `attachment_link` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

--
-- Table structure for table `workload_logs`
--

CREATE TABLE `workload_logs` (
  `id` int(11) NOT NULL,
  `work_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--


--
-- Indexes for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `workload_categories`
--
ALTER TABLE `workload_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `workload_items`
--
ALTER TABLE `workload_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_workload_user` (`user_id`),
  ADD KEY `fk_workload_category` (`category_id`);

--
-- Indexes for table `workload_logs`
--
ALTER TABLE `workload_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_log_id` (`work_log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_work_log_id` (`work_log_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_terms`
--
ALTER TABLE `academic_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `workload_categories`
--
ALTER TABLE `workload_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `workload_items`
--
ALTER TABLE `workload_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `workload_logs`
--
ALTER TABLE `workload_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `workload_items`
--
ALTER TABLE `workload_items`
  ADD CONSTRAINT `fk_workload_category` FOREIGN KEY (`category_id`) REFERENCES `workload_categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_workload_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workload_logs`
--
ALTER TABLE `workload_logs`
  ADD CONSTRAINT `workload_logs_ibfk_1` FOREIGN KEY (`work_log_id`) REFERENCES `workload_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workload_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
