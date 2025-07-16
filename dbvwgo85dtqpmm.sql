-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 16, 2025 at 07:08 AM
-- Server version: 8.0.41-32
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbvwgo85dtqpmm`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int NOT NULL,
  `dept_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `manager_id`) VALUES
(1, 'HR', 3),
(2, 'Finance', 4),
(3, 'IT', 7);

-- --------------------------------------------------------

--
-- Table structure for table `department_indicators`
--

CREATE TABLE `department_indicators` (
  `indicator_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `responsible_departments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_goal` float NOT NULL,
  `unit_of_goal` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `way_of_measurement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_weight` int DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_indicators`
--

INSERT INTO `department_indicators` (`indicator_id`, `name`, `description`, `responsible_departments`, `default_goal`, `unit_of_goal`, `unit`, `way_of_measurement`, `default_weight`, `sort_order`, `active`) VALUES
(1, 'Employee Training Hours', 'Average training hours per employee per month', NULL, 20, 'hours', 'hours', 'Training attendance sheets', 20, 1, 1),
(2, 'Budget Variance', 'Percentage difference between actual and planned budget', '2', 5, '%', '%', 'Monthly financial report', 30, 2, 1),
(3, 'System Uptime', 'Percentage of core-system availability during business hours', '3', 99.9, '%', '%', 'Monitoring dashboard', 50, 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `department_indicator_monthly`
--

CREATE TABLE `department_indicator_monthly` (
  `snapshot_id` int NOT NULL,
  `indicator_id` int DEFAULT NULL,
  `dept_id` int NOT NULL,
  `month` date NOT NULL,
  `custom_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT '0',
  `target_value` float NOT NULL,
  `weight` int NOT NULL,
  `unit_of_goal` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `way_of_measurement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `actual_value` float DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_indicator_monthly`
--

INSERT INTO `department_indicator_monthly` (`snapshot_id`, `indicator_id`, `dept_id`, `month`, `custom_name`, `is_custom`, `target_value`, `weight`, `unit_of_goal`, `unit`, `way_of_measurement`, `created_by`, `actual_value`, `notes`, `created_at`) VALUES
(1, 1, 1, '2025-05-01', NULL, 0, 20, 20, 'hours', 'hours', 'Training attendance sheets', 3, 18, 'Achieved target for May', '2025-05-31 20:59:59'),
(2, 2, 2, '2025-05-01', NULL, 0, 5, 30, '%', '%', 'Monthly financial report', 4, 4.2, 'Under budget', '2025-05-31 20:59:59'),
(3, 3, 3, '2025-05-01', NULL, 0, 99.9, 50, '%', '%', 'Monitoring dashboard', 7, 99.7, 'Minor outage on 12 May', '2025-05-31 20:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

CREATE TABLE `history` (
  `history_id` int NOT NULL,
  `user_id` int NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `history`
--

INSERT INTO `history` (`history_id`, `user_id`, `event_type`, `details`, `timestamp`) VALUES
(1, 1, 'login', 'User logged in', '2025-06-15 05:30:00'),
(2, 2, 'create_indicator', 'Created new individual indicator', '2025-06-15 06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `indicators_old`
--

CREATE TABLE `indicators_old` (
  `indicator_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `indicators_old`
--

INSERT INTO `indicators_old` (`indicator_id`, `name`, `archived_at`) VALUES
(1, 'Customer Complaints', '2023-12-31 21:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `individual_evaluations`
--

CREATE TABLE `individual_evaluations` (
  `evaluation_id` int NOT NULL,
  `evaluator_id` int NOT NULL,
  `evaluatee_id` int NOT NULL,
  `indicator_id` int NOT NULL,
  `month` date NOT NULL,
  `rating` float NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `individual_evaluations`
--

INSERT INTO `individual_evaluations` (`evaluation_id`, `evaluator_id`, `evaluatee_id`, `indicator_id`, `month`, `rating`, `comments`, `created_at`) VALUES
(1, 1, 5, 1, '2025-05-01', 4, NULL, '2025-05-25 07:00:00'),
(2, 5, 1, 1, '2025-05-01', 5, NULL, '2025-05-25 07:05:00'),
(3, 3, 5, 3, '2025-05-01', 4, NULL, '2025-05-28 09:00:00'),
(4, 4, 1, 3, '2025-05-01', 5, NULL, '2025-05-28 09:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `individual_indicators`
--

CREATE TABLE `individual_indicators` (
  `indicator_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` enum('individual','manager') COLLATE utf8mb4_unicode_ci NOT NULL,
  `responsible_departments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_goal` float NOT NULL,
  `default_weight` int DEFAULT NULL,
  `sort_order` int NOT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `individual_indicators`
--

INSERT INTO `individual_indicators` (`indicator_id`, `name`, `description`, `category`, `responsible_departments`, `default_goal`, `default_weight`, `sort_order`, `active`) VALUES
(1, 'Teamwork', 'Collaborates effectively with team members', 'individual', '1,2,3', 4, 20, 1, 1),
(2, 'Communication', 'Communicates clearly and effectively', 'individual', '1,2,3', 4, 20, 2, 1),
(3, 'Leadership', 'Guides and inspires others toward goals', 'manager', '1,2,3', 4, 30, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `reminders_log`
--

CREATE TABLE `reminders_log` (
  `reminder_id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reminders_log`
--

INSERT INTO `reminders_log` (`reminder_id`, `user_id`, `type`, `sent_at`) VALUES
(1, 1, 'monthly_evaluation_reminder', '2025-05-28 06:00:00'),
(2, 5, 'monthly_evaluation_reminder', '2025-05-28 06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int NOT NULL,
  `role_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'admin'),
(2, 'manager'),
(3, 'employee');

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `score_id` int NOT NULL,
  `user_id` int NOT NULL,
  `month` date NOT NULL,
  `indicator_id` int DEFAULT NULL,
  `score` float DEFAULT NULL,
  `category` enum('individual','manager') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dept_id` int DEFAULT NULL,
  `final_score` float DEFAULT NULL,
  `dept_score` float DEFAULT NULL,
  `individual_score` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scores`
--

INSERT INTO `scores` (`score_id`, `user_id`, `month`, `indicator_id`, `score`, `category`, `dept_id`, `final_score`, `dept_score`, `individual_score`) VALUES
(1, 1, '2025-05-01', NULL, NULL, NULL, 2, 85, 80, 88),
(2, 5, '2025-05-01', NULL, NULL, NULL, 1, 83.5, 85, 82),
(3, 3, '2025-05-01', NULL, NULL, 'manager', 1, 90, 92, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('department_score_weight', '70'),
('evaluation_deadline_days', '2'),
('individual_score_weight', '30'),
('telegram_signup_required', '0');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int NOT NULL,
  `dept_id` int DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_chat_id` bigint DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `rating_window_days` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `email_verified` tinyint(1) DEFAULT '0',
  `email_verification_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role_id`, `dept_id`, `phone`, `telegram_chat_id`, `position`, `birth_date`, `hire_date`, `rating_window_days`, `active`, `email_verified`, `email_verification_token`, `email_verification_expires`) VALUES
(1, 'Ahmed Shorsh Hamad', 'ahmad.shorsh@sk.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 2, '+9647719921065', NULL, 'Sales Officer', '2000-08-26', '2023-09-01', 0, 1, 0, 'fd321bee828388f18d85687e865305361e51e12439dedea7d58c9155e967d806', '2025-06-17 09:37:51'),
(2, 'Test', 'shorsh907@gmail.com', '$2y$10$PhMas541E53NTMCjlXh3SOB7zLDRqdmJWEvPS0Fa5on3uugkv2ZMS', 1, 3, '+1234567890', NULL, 'Sales Officer', '2013-02-18', '2025-06-24', 0, 1, 0, 'ddacd6229eabe11a5849ade29e1ce8f2b5bce3c0e30ed9072172c920f8ceadee', '2025-06-17 10:35:25'),
(3, 'Sara Abbas', 'sara.abbas@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 2, 1, '+9647700000001', NULL, 'HR Manager', '1985-04-10', '2020-01-15', 2, 1, 1, NULL, NULL),
(4, 'Ali Mohammed', 'ali.mohammed@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 2, 2, '+9647700000002', NULL, 'Finance Manager', '1980-11-13', '2019-05-10', 2, 1, 1, NULL, NULL),
(5, 'Farah Karim', 'farah.karim@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 1, '+9647700000003', NULL, 'Recruiter', '1995-02-20', '2024-03-01', 0, 1, 1, NULL, NULL),
(6, 'Omar Hameed', 'omar.hameed@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 3, '+9647700000004', NULL, 'IT Support', '1992-07-12', '2022-08-15', 0, 1, 1, NULL, NULL),
(7, 'Layla Qadir', 'layla.qadir@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 2, 3, '+9647700000005', NULL, 'IT Manager', '1987-09-05', '2018-11-01', 2, 1, 1, NULL, NULL),
(19, 'Ahmed Shorsh', 'ahmad.shorsh@southkurdistan.com', '$2y$10$6p5R/hgZRaaGgM/22n6kheOraxWQXMuiybkICv8QYWGJ.nFtqhcgq', 3, 1, '+9647719921065', NULL, 'IT Support', '2025-07-01', '2025-07-02', 0, 1, 1, NULL, NULL),
(20, 'test', 'ahmadhamad.one@gmail.com', '$2y$10$SHUzewbp46PAdi1UlaEfeuqeXoAPaAZyGj2SRfkxjjMjlpaGwJx06', 3, 2, '+9647719921065', NULL, 'Finance Manager', '2025-07-09', '2025-07-01', 0, 1, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_flags`
--

CREATE TABLE `user_flags` (
  `flag_id` int NOT NULL,
  `user_id` int NOT NULL,
  `flag` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `flag_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seen_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_flags`
--

INSERT INTO `user_flags` (`flag_id`, `user_id`, `flag`, `value`, `flag_name`, `seen_at`) VALUES
(1, 2, '', NULL, 'intro_seen', '2025-06-16 11:41:20'),
(2, 1, '', NULL, 'intro_seen', '2025-06-24 09:47:02'),
(4, 19, '', NULL, 'intro_seen', '2025-07-15 14:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `user_telegram`
--

CREATE TABLE `user_telegram` (
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `telegram_chat_id` bigint DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_telegram`
--

INSERT INTO `user_telegram` (`user_id`, `token`, `telegram_chat_id`, `verified`) VALUES
(19, '018888634c24bbd747855c407378d484', NULL, 0),
(20, 'bdeccde83c501722cf9f3de4a03ed12e', NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `department_indicators`
--
ALTER TABLE `department_indicators`
  ADD PRIMARY KEY (`indicator_id`);

--
-- Indexes for table `department_indicator_monthly`
--
ALTER TABLE `department_indicator_monthly`
  ADD PRIMARY KEY (`snapshot_id`),
  ADD KEY `indicator_id` (`indicator_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `indicators_old`
--
ALTER TABLE `indicators_old`
  ADD PRIMARY KEY (`indicator_id`);

--
-- Indexes for table `individual_evaluations`
--
ALTER TABLE `individual_evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD KEY `evaluator_id` (`evaluator_id`),
  ADD KEY `evaluatee_id` (`evaluatee_id`),
  ADD KEY `indicator_id` (`indicator_id`);

--
-- Indexes for table `individual_indicators`
--
ALTER TABLE `individual_indicators`
  ADD PRIMARY KEY (`indicator_id`);

--
-- Indexes for table `reminders_log`
--
ALTER TABLE `reminders_log`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`score_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `indicator_id` (`indicator_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- Indexes for table `user_flags`
--
ALTER TABLE `user_flags`
  ADD PRIMARY KEY (`flag_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_telegram`
--
ALTER TABLE `user_telegram`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `token_idx` (`token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `department_indicators`
--
ALTER TABLE `department_indicators`
  MODIFY `indicator_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `department_indicator_monthly`
--
ALTER TABLE `department_indicator_monthly`
  MODIFY `snapshot_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `history`
--
ALTER TABLE `history`
  MODIFY `history_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `indicators_old`
--
ALTER TABLE `indicators_old`
  MODIFY `indicator_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `individual_evaluations`
--
ALTER TABLE `individual_evaluations`
  MODIFY `evaluation_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `individual_indicators`
--
ALTER TABLE `individual_indicators`
  MODIFY `indicator_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reminders_log`
--
ALTER TABLE `reminders_log`
  MODIFY `reminder_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `score_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_flags`
--
ALTER TABLE `user_flags`
  MODIFY `flag_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `department_indicator_monthly`
--
ALTER TABLE `department_indicator_monthly`
  ADD CONSTRAINT `department_indicator_monthly_ibfk_1` FOREIGN KEY (`indicator_id`) REFERENCES `department_indicators` (`indicator_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `department_indicator_monthly_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `department_indicator_monthly_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `individual_evaluations`
--
ALTER TABLE `individual_evaluations`
  ADD CONSTRAINT `individual_evaluations_ibfk_1` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `individual_evaluations_ibfk_2` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `individual_evaluations_ibfk_3` FOREIGN KEY (`indicator_id`) REFERENCES `individual_indicators` (`indicator_id`) ON DELETE CASCADE;

--
-- Constraints for table `reminders_log`
--
ALTER TABLE `reminders_log`
  ADD CONSTRAINT `reminders_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`indicator_id`) REFERENCES `individual_indicators` (`indicator_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_flags`
--
ALTER TABLE `user_flags`
  ADD CONSTRAINT `user_flags_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_telegram`
--
ALTER TABLE `user_telegram`
  ADD CONSTRAINT `fk_user_telegram_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
