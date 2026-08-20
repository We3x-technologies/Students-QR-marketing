-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 20, 2026 at 08:25 AM
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
-- Database: `we3x_qr_marketing`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `username`, `password`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'we3x', 'we3x_admin', '$2y$10$fKAVvtMaeGVEjSPxi.J4.u.vOnY.fldBHYZon4B9LBCb.XWOtHSxa', 'active', '2026-08-19 15:35:15', '2026-08-19 09:47:55', '2026-08-19 10:05:15');

-- --------------------------------------------------------

--
-- Table structure for table `lead_status_history`
--

CREATE TABLE `lead_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` enum('need_to_contact','converted','rejected') DEFAULT NULL,
  `new_status` enum('need_to_contact','converted','rejected') NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_by_admin` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lead_status_history`
--

INSERT INTO `lead_status_history` (`id`, `lead_id`, `old_status`, `new_status`, `remarks`, `changed_by_admin`, `created_at`) VALUES
(1, 1, 'need_to_contact', 'converted', 'Student contacted and course details explained.', 1, '2026-08-20 05:05:56');

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `shop_name`, `owner_name`, `phone`, `email`, `address`, `username`, `password`, `qr_code`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Sri Textiles', 'Ramesh Kumar', '9876543210', 'sritextiles@gmail.com', '12 Main Road, Karaikudi, Tamil Nadu', 'sri_textiles', '$2y$10$W4RjIjRaPBgEk/F6MfbGLuunABreK2F6CBzl9Ub2RMJBvZFE86/VW', 'WE3X-QR-89D74E9BEBABBA71', 'active', '2026-08-20 11:49:24', '2026-08-19 10:16:50', '2026-08-20 06:19:24');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `gender` varchar(30) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(150) NOT NULL,
  `current_location` varchar(255) NOT NULL,
  `highest_qualification` varchar(150) NOT NULL,
  `institution_name` varchar(255) DEFAULT NULL,
  `current_status` enum('student','working','job_seeker','other') NOT NULL,
  `current_status_other` varchar(150) DEFAULT NULL,
  `course_applied` enum('digital_marketing','mern_stack_development','ui_ux_graphics_design','cyber_security','tally_prime','basics_computers','ai_ml','cloud_computing') NOT NULL,
  `training_mode` enum('classroom','online','hybrid') NOT NULL,
  `declaration` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `gender`, `phone`, `email`, `current_location`, `highest_qualification`, `institution_name`, `current_status`, `current_status_other`, `course_applied`, `training_mode`, `declaration`, `created_at`, `updated_at`) VALUES
(1, 'Arun Kumar', 'male', '9876543210', 'arun@gmail.com', 'Karaikudi', 'B.E. Computer Science', 'ABC Engineering College', 'student', NULL, 'ai_ml', 'classroom', 1, '2026-08-19 12:40:34', '2026-08-19 12:40:34');

-- --------------------------------------------------------

--
-- Table structure for table `student_leads`
--

CREATE TABLE `student_leads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('need_to_contact','converted','rejected') NOT NULL DEFAULT 'need_to_contact',
  `admin_remarks` text DEFAULT NULL,
  `contacted_at` datetime DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_leads`
--

INSERT INTO `student_leads` (`id`, `shop_id`, `student_id`, `status`, `admin_remarks`, `contacted_at`, `converted_at`, `rejected_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'converted', 'Student contacted and course details explained.', '2026-08-20 10:35:56', '2026-08-20 10:35:56', NULL, '2026-08-19 12:40:34', '2026-08-20 05:05:56');

-- --------------------------------------------------------

--
-- Table structure for table `student_registration_tokens`
--

CREATE TABLE `student_registration_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_registration_tokens`
--

INSERT INTO `student_registration_tokens` (`id`, `shop_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 1, '6272f731df398fa5bee9493b1070ccf443bcbdd79fdee6e1ae52dc1189b9bfd3', '2026-08-19 19:04:51', '2026-08-19 18:10:34', '2026-08-19 12:34:51'),
(2, 1, '7bf625634c3d53c1bf79a3f82efe6f86393c888cd6e035b64acd34d7b31d45d1', '2026-08-20 12:24:06', NULL, '2026-08-20 05:54:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_admin_status` (`status`),
  ADD KEY `idx_admin_username` (`username`);

--
-- Indexes for table `lead_status_history`
--
ALTER TABLE `lead_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_lead` (`lead_id`),
  ADD KEY `idx_history_admin` (`changed_by_admin`),
  ADD KEY `idx_history_created_at` (`created_at`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `unique_shop_phone` (`phone`),
  ADD KEY `idx_shop_name` (`shop_name`),
  ADD KEY `idx_shop_status` (`status`),
  ADD KEY `idx_shop_username` (`username`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_name` (`name`),
  ADD KEY `idx_student_phone` (`phone`),
  ADD KEY `idx_student_email` (`email`),
  ADD KEY `idx_student_course` (`course_applied`),
  ADD KEY `idx_student_status` (`current_status`),
  ADD KEY `idx_student_created_at` (`created_at`);

--
-- Indexes for table `student_leads`
--
ALTER TABLE `student_leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lead_shop` (`shop_id`),
  ADD KEY `idx_lead_student` (`student_id`),
  ADD KEY `idx_lead_status` (`status`),
  ADD KEY `idx_lead_created_at` (`created_at`),
  ADD KEY `idx_shop_status` (`shop_id`,`status`);

--
-- Indexes for table `student_registration_tokens`
--
ALTER TABLE `student_registration_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_registration_token_shop` (`shop_id`),
  ADD KEY `idx_registration_token_expires` (`expires_at`),
  ADD KEY `idx_registration_token_used` (`used_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lead_status_history`
--
ALTER TABLE `lead_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_leads`
--
ALTER TABLE `student_leads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_registration_tokens`
--
ALTER TABLE `student_registration_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lead_status_history`
--
ALTER TABLE `lead_status_history`
  ADD CONSTRAINT `fk_history_admin` FOREIGN KEY (`changed_by_admin`) REFERENCES `admins` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_history_lead` FOREIGN KEY (`lead_id`) REFERENCES `student_leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_leads`
--
ALTER TABLE `student_leads`
  ADD CONSTRAINT `fk_student_lead_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_lead_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_registration_tokens`
--
ALTER TABLE `student_registration_tokens`
  ADD CONSTRAINT `fk_registration_token_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
