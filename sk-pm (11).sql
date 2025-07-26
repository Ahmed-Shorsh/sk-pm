-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 26, 2025 at 10:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sk-pm`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `share_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `manager_id`, `share_path`) VALUES
(1, 'HR', 3, '\\\\192.168.10.252\\Plan\\HR Plans'),
(2, 'Finance', 4, ''),
(3, 'IT', 7, NULL),
(4, 'Engineering', 28, ''),
(5, 'test', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `department_indicators`
--

CREATE TABLE `department_indicators` (
  `indicator_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `responsible_departments` varchar(255) DEFAULT NULL,
  `default_goal` float NOT NULL,
  `unit_of_goal` varchar(50) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `way_of_measurement` varchar(255) DEFAULT NULL,
  `default_weight` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_indicators`
--

INSERT INTO `department_indicators` (`indicator_id`, `name`, `description`, `responsible_departments`, `default_goal`, `unit_of_goal`, `unit`, `way_of_measurement`, `default_weight`, `sort_order`, `active`) VALUES
(2, 'Budget Variance', 'Percentage difference between actual and planned budget', '2', 5, '%', NULL, 'Monthly financial report', 30, 2, 1),
(3, 'System Uptime', 'Percentage of core-system availability during business hours', '3', 99.9, '%', '%', 'Monitoring dashboard', 50, 3, 1),
(5, 'departemnt test', 'Why Your Previous Modals Likely Failed\r\nCustom multi-select script threw an error → JS stopped → Bootstrap data API never ran.\r\n\r\nMultiple, conflicting Bootstrap versions (4 + 5).', '2,1,3', 10, 'hours', NULL, 'Training attendance sheets', 5, 4, 1),
(6, 'test', 'test des', '1', 5, 'hours', NULL, 'Training attendance sheets', 5, 5, 1),
(7, 'test for hr', 'hr only', '1', 10, '', NULL, '', NULL, 0, 1),
(8, 'test dept indicator for hr', 'f', '1', 2, '', NULL, '', NULL, 0, 1),
(9, 'engneer kpi1', 'test', '4', 10, '', NULL, '', 30, 1, 1),
(10, 'engneer kpi2', 'test', '4', 10, '', NULL, '', 30, 2, 1),
(11, 'engneer kpi3', 'test', '4', 10, '', NULL, '', 40, 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `department_indicator_monthly`
--

CREATE TABLE `department_indicator_monthly` (
  `snapshot_id` int(11) NOT NULL,
  `indicator_id` int(11) DEFAULT NULL,
  `dept_id` int(11) NOT NULL,
  `month` date NOT NULL,
  `custom_name` varchar(255) DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `target_value` float NOT NULL,
  `weight` int(11) NOT NULL,
  `unit_of_goal` varchar(50) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `way_of_measurement` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `actual_value` float DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `audit_score` float DEFAULT NULL,
  `task_file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_indicator_monthly`
--

INSERT INTO `department_indicator_monthly` (`snapshot_id`, `indicator_id`, `dept_id`, `month`, `custom_name`, `is_custom`, `target_value`, `weight`, `unit_of_goal`, `unit`, `way_of_measurement`, `created_by`, `actual_value`, `notes`, `created_at`, `audit_score`, `task_file_path`) VALUES
(2, 2, 2, '2025-05-01', NULL, 0, 5, 30, '%', '%', 'Monthly financial report', 4, 4.2, 'Under budget', '2025-05-31 20:59:59', 2.5, NULL),
(3, 3, 3, '2025-05-01', NULL, 0, 99.9, 50, '%', '%', 'Monitoring dashboard', 7, 99.7, 'Minor outage on 12 May', '2025-05-31 20:59:59', 5, NULL),
(4, NULL, 1, '2025-07-01', 'custom kpi', 1, 3, 5, 'hours', '', '', 19, 3, 'note for custom', '2025-07-19 12:12:43', 5, ''),
(5, 5, 1, '2025-07-01', '', 0, 3, 9, '', '', '', 19, 3, '', '2025-07-19 12:27:03', 2.5, '\\\\192.168.10.252\\Plan\\PR & Media\\tech\\link-to-excel\\uploads\\test_1.jpg'),
(6, 6, 1, '2025-07-01', '', 0, 4, 5, '', '', '', 19, 0, '', '2025-07-19 15:09:23', 2.5, ''),
(7, 2, 1, '2025-07-01', '', 0, 3, 1, '', '', '', 19, 2, 'majal nabw', '2025-07-20 10:45:56', 5, '\\\\192.168.10.252\\Plan\\PR & Media\\tech\\Group 25.png'),
(8, 3, 1, '2025-07-01', '', 0, 5, 50, '', '', '', 19, 5, '', '2025-07-20 12:04:13', 0, ''),
(10, 2, 2, '2025-07-01', '', 0, 4, 4, '', '', '', 26, 2, '', '2025-07-23 10:43:18', NULL, ''),
(12, 8, 1, '2025-07-01', '', 0, 4, 4, '', '', '', 22, NULL, NULL, '2025-07-23 13:22:57', NULL, NULL),
(13, 9, 4, '2025-07-01', '', 0, 5, 30, '', '', '', 28, 30, '', '2025-07-24 11:08:08', 2.5, ''),
(14, 10, 4, '2025-07-01', '', 0, 5, 40, '', '', '', 28, 40, '', '2025-07-24 11:08:31', 5, ''),
(15, 11, 4, '2025-07-01', '', 0, 5, 30, '', '', '', 28, 30, '', '2025-07-24 11:09:27', 2.5, '');

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

CREATE TABLE `history` (
  `history_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
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
  `indicator_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  `evaluation_id` int(11) NOT NULL,
  `evaluator_id` int(11) NOT NULL,
  `evaluatee_id` int(11) NOT NULL,
  `indicator_id` int(11) NOT NULL,
  `month` date NOT NULL,
  `rating` float NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `individual_evaluations`
--

INSERT INTO `individual_evaluations` (`evaluation_id`, `evaluator_id`, `evaluatee_id`, `indicator_id`, `month`, `rating`, `comments`, `created_at`) VALUES
(1, 1, 5, 1, '2025-05-01', 4, NULL, '2025-05-25 07:00:00'),
(2, 5, 1, 1, '2025-05-01', 5, NULL, '2025-05-25 07:05:00'),
(3, 3, 5, 3, '2025-05-01', 4, NULL, '2025-05-28 09:00:00'),
(4, 4, 1, 3, '2025-05-01', 5, NULL, '2025-05-28 09:15:00'),
(11, 19, 3, 1, '2025-07-01', 3, 'd', '2025-07-19 08:16:05'),
(12, 19, 3, 4, '2025-07-01', 2, 'd', '2025-07-19 08:16:05'),
(13, 19, 3, 2, '2025-07-01', 3, 'd', '2025-07-19 08:16:05'),
(14, 19, 3, 3, '2025-07-01', 3, 'd', '2025-07-19 08:16:05'),
(15, 19, 5, 1, '2025-07-01', 3, 'bahawas', '2025-07-20 10:47:43'),
(16, 19, 5, 4, '2025-07-01', 1, 'bahawas', '2025-07-20 10:47:43'),
(17, 19, 5, 2, '2025-07-01', 3, 'bahawas', '2025-07-20 10:47:43'),
(18, 19, 5, 5, '2025-07-01', 0, 'bahawas', '2025-07-20 10:47:43'),
(59, 22, 5, 1, '2025-07-01', 2, 'test new', '2025-07-22 14:38:21'),
(60, 22, 5, 4, '2025-07-01', 2, 'test new', '2025-07-22 14:38:21'),
(61, 22, 5, 2, '2025-07-01', 2, 'test new', '2025-07-22 14:38:21'),
(62, 22, 5, 5, '2025-07-01', 2, 'test new', '2025-07-22 14:38:21'),
(63, 26, 1, 1, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(64, 26, 1, 4, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(65, 26, 1, 2, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(66, 26, 1, 5, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(67, 26, 4, 1, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(68, 26, 4, 4, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(69, 26, 4, 2, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(70, 26, 4, 5, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(71, 26, 4, 3, '2025-07-01', 2, 'test', '2025-07-23 09:05:54'),
(72, 27, 1, 1, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(73, 27, 1, 4, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(74, 27, 1, 2, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(75, 27, 1, 5, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(76, 27, 26, 1, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(77, 27, 26, 4, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(78, 27, 26, 2, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(79, 27, 26, 5, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(80, 27, 4, 1, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(81, 27, 4, 4, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(82, 27, 4, 2, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(83, 27, 4, 5, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(84, 27, 4, 3, '2025-07-01', 2, '', '2025-07-23 10:17:49'),
(85, 24, 5, 1, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(86, 24, 5, 4, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(87, 24, 5, 2, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(88, 24, 5, 5, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(89, 24, 19, 1, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(90, 24, 19, 4, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(91, 24, 19, 2, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(92, 24, 19, 5, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(93, 24, 3, 1, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(94, 24, 3, 4, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(95, 24, 3, 2, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(96, 24, 3, 5, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(97, 24, 3, 3, '2025-07-01', 1, '', '2025-07-23 10:38:51'),
(98, 28, 29, 1, '2025-07-01', 4, 'full mark ', '2025-07-24 10:18:49'),
(99, 28, 29, 2, '2025-07-01', 4, 'full mark ', '2025-07-24 10:18:49'),
(100, 28, 30, 1, '2025-07-01', 2, 'half', '2025-07-24 10:18:49'),
(101, 28, 30, 2, '2025-07-01', 2, 'half', '2025-07-24 10:18:49'),
(102, 29, 30, 1, '2025-07-01', 2, 'half', '2025-07-24 10:55:18'),
(103, 29, 30, 2, '2025-07-01', 2, 'half', '2025-07-24 10:55:18'),
(104, 29, 28, 1, '2025-07-01', 4, 'leader half', '2025-07-24 10:55:18'),
(105, 29, 28, 2, '2025-07-01', 4, 'leader half', '2025-07-24 10:55:18'),
(106, 29, 28, 3, '2025-07-01', 2, 'leader half', '2025-07-24 10:55:18'),
(107, 30, 29, 1, '2025-07-01', 4, '', '2025-07-24 11:00:47'),
(108, 30, 29, 2, '2025-07-01', 4, '', '2025-07-24 11:00:47'),
(109, 30, 28, 1, '2025-07-01', 4, '', '2025-07-24 11:00:47'),
(110, 30, 28, 2, '2025-07-01', 4, '', '2025-07-24 11:00:47'),
(111, 30, 28, 3, '2025-07-01', 4, '', '2025-07-24 11:00:47');

-- --------------------------------------------------------

--
-- Table structure for table `individual_indicators`
--

CREATE TABLE `individual_indicators` (
  `indicator_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('individual','manager') NOT NULL,
  `responsible_departments` varchar(255) DEFAULT NULL,
  `default_goal` float NOT NULL,
  `default_weight` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `individual_indicators`
--

INSERT INTO `individual_indicators` (`indicator_id`, `name`, `description`, `category`, `responsible_departments`, `default_goal`, `default_weight`, `sort_order`, `active`) VALUES
(1, 'Teamwork', 'Collaborates effectively with team members', 'individual', NULL, 4, 30, 1, 1),
(2, 'Communication', 'Communicates clearly and effectively', 'individual', NULL, 4, 40, 2, 1),
(3, 'Leadership', 'Guides and inspires others toward goals', 'manager', NULL, 4, 30, 3, 1),
(4, 'name', 'description added. edited', 'individual', NULL, 2, NULL, 1, 0),
(5, 'test', '', 'individual', NULL, 3, NULL, 5, 0);

-- --------------------------------------------------------

--
-- Table structure for table `reminders_log`
--

CREATE TABLE `reminders_log` (
  `reminder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reminders_log`
--

INSERT INTO `reminders_log` (`reminder_id`, `user_id`, `type`, `sent_at`) VALUES
(1, 1, 'monthly_evaluation_reminder', '2025-05-28 06:00:00'),
(2, 5, 'monthly_evaluation_reminder', '2025-05-28 06:00:00'),
(3, 22, 'telegram', '2025-07-20 13:13:39'),
(4, 22, 'telegram', '2025-07-20 13:18:07'),
(5, 22, 'telegram', '2025-07-20 13:18:26'),
(6, 22, 'telegram', '2025-07-20 13:23:37'),
(7, 22, 'telegram', '2025-07-20 13:28:27'),
(8, 22, 'telegram', '2025-07-20 13:28:32'),
(9, 22, 'telegram', '2025-07-20 13:28:41'),
(10, 22, 'telegram', '2025-07-20 13:28:48'),
(11, 22, 'telegram', '2025-07-22 06:12:22'),
(12, 22, 'telegram', '2025-07-22 06:12:29'),
(13, 22, 'telegram', '2025-07-22 06:14:36'),
(14, 22, 'telegram', '2025-07-22 06:16:01'),
(15, 22, 'telegram', '2025-07-22 06:16:12');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
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
  `score_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month` date NOT NULL,
  `category` enum('individual','manager') DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `final_score` float DEFAULT NULL,
  `dept_score` float DEFAULT NULL,
  `individual_score` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scores`
--

INSERT INTO `scores` (`score_id`, `user_id`, `month`, `category`, `dept_id`, `final_score`, `dept_score`, `individual_score`) VALUES
(1, 1, '2025-05-01', NULL, 2, 85, 80, 88),
(2, 5, '2025-05-01', NULL, 1, 83.5, 85, 82),
(3, 3, '2025-05-01', 'manager', 1, 90, 92, NULL),
(4, 5, '2025-07-01', NULL, 1, 58.08, 50, 76.92),
(5, 5, '2025-07-01', NULL, 1, 43.08, 50, 26.92),
(6, 5, '2025-07-01', NULL, 1, 43.08, 50, 26.92),
(7, 3, '2025-07-01', NULL, 1, 58.57, 50, 78.57),
(8, 5, '2025-07-01', NULL, 1, 43.08, 50, 26.92),
(9, 19, '2025-07-01', NULL, 1, 35, 50, 0),
(10, 22, '2025-07-01', NULL, 1, 35, 50, 0),
(11, 5, '2025-07-01', NULL, 1, 52.31, 50, 57.69),
(12, 5, '2025-07-01', NULL, 1, 52.31, 50, 57.69),
(13, 5, '2025-07-01', NULL, 1, 52.31, 50, 57.69),
(14, 5, '2025-07-01', NULL, 1, 52.31, 50, 57.69),
(15, 5, '2025-07-01', NULL, 1, 52.31, 50, 57.69),
(16, 5, '2025-07-01', NULL, 1, 52.31, 50, 57.69),
(17, 1, '2025-07-01', NULL, 2, 18.46, 0, 61.54),
(18, 4, '2025-07-01', NULL, 2, 17.65, 0, 58.82),
(19, 1, '2025-07-01', NULL, 2, 18.46, 0, 61.54),
(20, 26, '2025-07-01', NULL, 2, 18.46, 0, 61.54),
(21, 4, '2025-07-01', NULL, 2, 17.65, 0, 58.82),
(22, 5, '2025-07-01', NULL, 1, 49.62, 50, 48.72),
(23, 19, '2025-07-01', NULL, 1, 44.23, 50, 30.77),
(24, 3, '2025-07-01', NULL, 1, 49.12, 50, 47.06),
(25, 1, '2025-07-01', NULL, 2, 18.46, 0, 61.54),
(26, 4, '2025-07-01', NULL, 2, 17.65, 0, 58.82),
(27, 23, '2025-07-01', NULL, 2, 0, 0, 0),
(28, 26, '2025-07-01', NULL, 2, 18.46, 0, 61.54),
(29, 27, '2025-07-01', NULL, 2, 0, 0, 0),
(30, 29, '2025-07-01', NULL, 4, 30, 0, 100),
(31, 30, '2025-07-01', NULL, 4, 15, 0, 50),
(32, 30, '2025-07-01', NULL, 4, 15, 0, 50),
(33, 28, '2025-07-01', NULL, 4, 25, 0, 83.33),
(34, 29, '2025-07-01', NULL, 4, 30, 0, 100),
(35, 28, '2025-07-01', NULL, 4, 27.5, 0, 91.67),
(36, 28, '2025-07-01', NULL, 4, 27.5, 0, 91.67),
(37, 29, '2025-07-01', NULL, 4, 30, 0, 100),
(38, 30, '2025-07-01', NULL, 4, 15, 0, 50),
(39, 28, '2025-07-01', NULL, 4, 97.5, 100, 91.67),
(40, 29, '2025-07-01', NULL, 4, 100, 100, 100),
(41, 30, '2025-07-01', NULL, 4, 85, 100, 50),
(42, 28, '2025-07-01', NULL, 4, 150, 175, 91.67),
(43, 29, '2025-07-01', NULL, 4, 152.5, 175, 100),
(44, 30, '2025-07-01', NULL, 4, 137.5, 175, 50),
(45, 28, '2025-07-01', NULL, 4, 202.5, 250, 91.67),
(46, 29, '2025-07-01', NULL, 4, 205, 250, 100),
(47, 30, '2025-07-01', NULL, 4, 190, 250, 50),
(48, 28, '2025-07-01', NULL, 4, 272.5, 350, 91.67),
(49, 29, '2025-07-01', NULL, 4, 275, 350, 100),
(50, 30, '2025-07-01', NULL, 4, 260, 350, 50);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('department_score_weight', '70'),
('evaluation_deadline_days', '0'),
('individual_score_weight', '30'),
('telegram_signup_required', '0');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `telegram_chat_id` bigint(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `rating_window_days` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role_id`, `dept_id`, `phone`, `telegram_chat_id`, `position`, `birth_date`, `hire_date`, `rating_window_days`, `active`, `email_verified`, `email_verification_token`, `email_verification_expires`) VALUES
(1, 'Ahmed Shorsh Hamad', 'ahmad.shorsh@sk.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 2, '+964771992106', NULL, 'Sales Officer', '2000-08-26', '2023-09-01', 0, 1, 0, 'fd321bee828388f18d85687e865305361e51e12439dedea7d58c9155e967d806', '2025-06-17 09:37:51'),
(2, 'Test', 'shorsh907@gmail.com', '$2y$10$PhMas541E53NTMCjlXh3SOB7zLDRqdmJWEvPS0Fa5on3uugkv2ZMS', 1, 3, '+1234567890', NULL, 'Sales Officer', '2013-02-18', '2025-06-24', 0, 1, 0, 'ddacd6229eabe11a5849ade29e1ce8f2b5bce3c0e30ed9072172c920f8ceadee', '2025-06-17 10:35:25'),
(3, 'Sara Abbas', 'sara.abbas@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 2, 1, '+9647700000001', NULL, 'HR Manager', '1985-04-10', '2020-01-15', 2, 1, 1, NULL, NULL),
(4, 'Ali Mohammed', 'ali.mohammed@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 2, '+9647700000002', NULL, 'Finance Manager', '1980-11-13', '2019-05-10', 2, 1, 1, NULL, NULL),
(5, 'Farah Karim', 'farah.karim@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 1, '+9647700000003', NULL, 'Recruiter', '1995-02-20', '2024-03-01', 0, 1, 1, NULL, NULL),
(6, 'Omar Hameed', 'omar.hameed@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 3, 3, '+9647700000004', NULL, 'IT Support', '1992-07-12', '2022-08-15', 0, 1, 1, NULL, NULL),
(7, 'Layla Qadir', 'layla.qadir@southkurdistan.com', '$2y$10$tyKZ4sV8GEd9M15wkrrkdu8HctVagMUChZIgdXkpohUt/uMFEatui', 2, 3, '+9647700000005', NULL, 'IT Manager', '1987-09-05', '2018-11-01', 2, 1, 1, NULL, NULL),
(19, 'teestt', 'ahmad.shorsh@southkurdistan', '$2y$10$6p5R/hgZRaaGgM/22n6kheOraxWQXMuiybkICv8QYWGJ.nFtqhcgq', 3, 1, '+96477199210', NULL, 'IT Support', '2025-07-01', '2025-07-02', 0, 1, 1, NULL, NULL),
(22, 'Ahmed Shorshh', 'ahmad.shorsh@southkurdistan.com', '$2y$10$EK1D.nhcHY5gTQjSUhAxoOr1drbQ7Ch0zaUkb.EwGoz3QViMLxI2K', 2, 1, '+9647719921065', 1421878995, 'fd', '0000-00-00', '0000-00-00', 0, 1, 1, NULL, NULL),
(23, 'Danya kawa faeq', 'dania.kawa@southkurdistan.com', '$2y$10$yLO.zYa7BQVW6xz9m.XsA.CUaeDHGnJUUcyMHFpFU6onXZ/z0Bdi.', 1, 2, '+9647708944040', 197340445, 'Managing Director', '1994-01-01', '2019-11-30', 0, 1, 0, '2d28a599e79ec0c1c8fdcc6efb812ee3cffeac9a498af14c3ed016927695b8fa', '2025-07-23 11:16:43'),
(24, 'mawa', 'mawa@m.com', '$2y$10$P1zVqJvBWKzmsTRZQEp55OHskWvgdIP6wamWEkG/AbwVNNMlJaCgO', 3, 1, '+9647719921065', NULL, 'Sales Officer', NULL, NULL, 0, 1, 0, '295a43d37a2bf92a7badfb29cbdc16cb8cdb40d556d17647ca7d74cf6d3ef4e7', '2025-07-24 07:42:43'),
(26, 'maw', 'skg.iraq@gmail.com', '$2y$10$9f/qkZXcoLFGRtQ0JT8n7enBYASATtca5mBpO8FVV0USoCJDGXKtC', 2, 2, '+9647719921065', NULL, 'IT Support', NULL, NULL, 0, 1, 1, NULL, NULL),
(27, 'testtest', 'ahmadhamad.one@gmail.com', '$2y$10$11USCGe1cbxRcvq.3JDZpusSXZwjZcg0m5N3Vqnw2fKN.WHm4FGpS', 3, 2, '+9647719921065', NULL, 'IT Support', NULL, NULL, 0, 1, 1, NULL, NULL),
(28, 'name eng1', 'eng1@email.com', '$2y$10$K8YG3CJzq.Y2KA0bhAmppud3pN7iBeCuMQMxGJHLn4QyOLUkG7R2K', 2, 4, '+9647719921065', NULL, 'eng officer1', '1992-07-12', '2022-08-15', 0, 1, 0, NULL, NULL),
(29, 'name eng2', 'eng2@email.com', '$2y$10$j33ze.4C4bKovkYg2ovXrOIGY53cZccls7KohHsTTh2EysGaLk1Vm', 3, 4, '+9647719921065', NULL, 'eng officer2', '0000-00-00', '0000-00-00', 0, 1, 0, NULL, NULL),
(30, 'name eng3', 'eng3@email.com', '$2y$10$XsQo7ZD4dtTYG12pKn2KveKh3E1aurW.liEYGQtiVQfRYEtK7FBa6', 3, 4, '+9647719921065', NULL, 'eng officer3', '0000-00-00', '0000-00-00', 0, 1, 0, NULL, NULL),
(31, 'test', 'test@gmail.comm', '$2y$10$iTR6RmIcqdpWfDyLm7db2ewaomv4cyN7qngbYb.j8.U2u8SD4bP/K', 3, 1, '+9647719921065', NULL, 'Sales Officer', '0000-00-00', '0000-00-00', 0, 1, 0, NULL, NULL),
(32, 'test', 'test@gmail.comtt', '$2y$10$RdAAVTulVz6OFOoUqa7DUOOMwS8aCVhG3K8Oc0N.J7cMMMjNwY4QC', 3, 1, '+9647719921065', NULL, 'IT Support', '0000-00-00', '0000-00-00', 0, 1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_flags`
--

CREATE TABLE `user_flags` (
  `flag_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `flag` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `flag_name` varchar(100) DEFAULT NULL,
  `seen_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_flags`
--

INSERT INTO `user_flags` (`flag_id`, `user_id`, `flag`, `value`, `flag_name`, `seen_at`) VALUES
(1, 2, '', NULL, 'intro_seen', '2025-06-16 11:41:20'),
(2, 1, '', NULL, 'intro_seen', '2025-06-24 09:47:02'),
(4, 19, '', NULL, 'intro_seen', '2025-07-15 14:07:02'),
(5, 22, '', NULL, 'intro_seen', '2025-07-20 12:23:38'),
(6, 26, '', NULL, 'intro_seen', '2025-07-23 08:21:47'),
(7, 24, '', NULL, 'intro_seen', '2025-07-23 10:01:43'),
(8, 27, '', NULL, 'intro_seen', '2025-07-23 10:05:34'),
(9, 28, '', NULL, 'intro_seen', '2025-07-24 10:16:53'),
(10, 29, '', NULL, 'intro_seen', '2025-07-24 10:19:00'),
(11, 30, '', NULL, 'intro_seen', '2025-07-24 13:55:30');

-- --------------------------------------------------------

--
-- Table structure for table `user_telegram`
--

CREATE TABLE `user_telegram` (
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `telegram_chat_id` bigint(20) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_telegram`
--

INSERT INTO `user_telegram` (`user_id`, `token`, `telegram_chat_id`, `verified`) VALUES
(19, '018888634c24bbd747855c407378d484', NULL, 0),
(22, '0f36e5ad5635d0e24793508cd9be3b6d', 1421878995, 1),
(23, '95ab508c0d96e2ad63558abcc4e03df4', 197340445, 1),
(24, 'd9d8e1348f4b2f334b31fc02bcf020fc', NULL, 0),
(26, '244750da0025b2d921db2615450218c2', NULL, 0),
(27, '8aa4da0ab6e08d6b7d33da8368d5c462', NULL, 0);

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
  ADD UNIQUE KEY `uniq_eval_per_month` (`evaluator_id`,`evaluatee_id`,`indicator_id`,`month`),
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
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `department_indicators`
--
ALTER TABLE `department_indicators`
  MODIFY `indicator_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `department_indicator_monthly`
--
ALTER TABLE `department_indicator_monthly`
  MODIFY `snapshot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `history`
--
ALTER TABLE `history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `indicators_old`
--
ALTER TABLE `indicators_old`
  MODIFY `indicator_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `individual_evaluations`
--
ALTER TABLE `individual_evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `individual_indicators`
--
ALTER TABLE `individual_indicators`
  MODIFY `indicator_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reminders_log`
--
ALTER TABLE `reminders_log`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `score_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `user_flags`
--
ALTER TABLE `user_flags`
  MODIFY `flag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
