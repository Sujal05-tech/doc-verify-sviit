-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 18, 2026 at 12:23 PM
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
-- Database: `docuguard`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 7, 'upload', 10, 'Uploaded document: Sheet Two (1775127845_Discrete_Structure_MST_PYQ.pdf)', '::1', '2026-04-02 11:04:05'),
(2, 7, 'upload', 11, 'Uploaded document: Sheet Three (1775128867_DC__OOP__PPL_PYQP.pdf)', '::1', '2026-04-02 11:21:07'),
(3, 4, 'status_change', 9, 'Changed status to: verified', '::1', '2026-04-02 11:21:52'),
(4, 4, 'status_change', 11, 'Changed status to: verified', '::1', '2026-04-02 11:21:53'),
(5, 4, 'status_change', 10, 'Changed status to: verified', '::1', '2026-04-02 11:21:55'),
(6, 1, 'contact_admin', NULL, 'Sent support message: Hiiiiii', '::1', '2026-04-03 10:51:42'),
(7, 1, 'admin_reply', 1, 'Replied to support ticket #1', '::1', '2026-04-03 11:02:25'),
(8, 7, 'contact_admin', NULL, 'Sent support message: Message One.', '::1', '2026-04-03 11:03:10'),
(9, 4, 'admin_reply', 2, 'Replied to support ticket #2', '::1', '2026-04-03 11:03:39'),
(10, 4, 'status_change', 8, 'Changed status to: verified', '::1', '2026-04-16 08:29:37'),
(11, 4, 'role_change', 5, 'Changed user #5 role to: admin', '::1', '2026-04-16 08:30:13'),
(12, 4, 'role_change', 5, 'Changed user #5 role to: verifier', '::1', '2026-04-16 08:30:20'),
(13, 4, 'admin_reply', 2, 'Replied to support ticket #2', '::1', '2026-04-16 08:30:38'),
(14, 6, 'ai_verify_official', 13, 'Official doc verify: Aadhaar - FAKE/SUSPECT', '::1', '2026-04-18 09:16:16'),
(15, 6, 'ai_verify_official', 14, 'Official doc verify: Aadhaar - GENUINE', '::1', '2026-04-18 09:27:15');

-- --------------------------------------------------------

--
-- Table structure for table `bus_passes`
--

CREATE TABLE `bus_passes` (
  `pass_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `pass_number` varchar(20) NOT NULL,
  `route_no` varchar(10) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bus_passes`
--

INSERT INTO `bus_passes` (`pass_id`, `student_id`, `pass_number`, `route_no`, `issue_date`, `expiry_date`, `is_active`) VALUES
(1, 'CSE2301', 'BP001', 'R-5', '2026-01-01', '2026-12-31', 1),
(2, 'CSE2302', 'BP002', 'R-3', '2026-01-01', '2026-12-31', 1),
(3, 'CSE2201', 'BP003', 'R-7', '2026-01-01', '2026-12-31', 1),
(4, 'IT2301', 'BP004', 'R-2', '2026-01-01', '2026-12-31', 1),
(5, 'IT2302', 'BP005', 'R-5', '2026-01-01', '2026-12-31', 1);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_reply` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `user_id`, `subject`, `message`, `status`, `created_at`, `admin_reply`) VALUES
(1, 1, 'Hiiiiii', 'One One.', 'read', '2026-04-03 10:51:42', 'Hi brooo'),
(2, 7, 'Message One.', 'Pehela Message.', 'read', '2026-04-03 11:03:10', 'giif');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `title`, `file_name`, `status`, `uploaded_at`) VALUES
(2, 4, 'Waav', 'week 1 report.pdf', 'pending', '2026-04-01 18:40:37'),
(3, 6, 'MEME One.', 'WhatsApp Image 2026-03-24 at 6.42.54 PM.jpeg', 'rejected', '2026-04-01 18:49:35'),
(4, 6, 'CCVV', 'cv of sujeet..pdf', 'verified', '2026-04-01 18:49:52'),
(5, 6, 'srgdsfgfesdgesdrtgrettg', '1775070798_desktop.ini', 'pending', '2026-04-01 19:13:18'),
(6, 6, 'GG Bro!!', '1775072045_week2_minor.pdf', 'verified', '2026-04-01 19:34:05'),
(7, 6, 'aabbccdd', '1775121858_Eak_file_thi.pdf', 'pending', '2026-04-02 09:24:18'),
(8, 6, 'File 2.', '1775122833_Do_file_thi.pdf', 'verified', '2026-04-02 09:40:33'),
(9, 7, 'Sheet One', '1775124975_dsa_and_os_.pdf', 'verified', '2026-04-02 10:16:15'),
(10, 7, 'Sheet Two', '1775127845_Discrete_Structure_MST_PYQ.pdf', 'verified', '2026-04-02 11:04:05'),
(11, 7, 'Sheet Three', '1775128867_DC__OOP__PPL_PYQP.pdf', 'verified', '2026-04-02 11:21:07'),
(13, 6, 'Official Verify: Aadhaar', 'ID Card 1.jpeg', 'rejected', '2026-04-18 09:16:16'),
(14, 6, 'Official Verify: Aadhaar', '20260418_145440.jpg', 'verified', '2026-04-18 09:27:15');

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `email` varchar(100) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `year` varchar(20) DEFAULT '1st Year',
  `branch` varchar(50) DEFAULT 'Unknown',
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `bus_fee_paid` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `name`, `year`, `branch`, `email`, `phone`, `photo`, `bus_fee_paid`, `created_at`) VALUES
('CSE2101', 'Riya Malhotra', '1st Year', 'CSE', 'riya@sviit.ac.in', '9876543217', NULL, 0, '2026-04-17 01:52:19'),
('CSE2201', 'Neha Joshi', '2nd Year', 'CSE', 'neha@sviit.ac.in', '9876543213', NULL, 1, '2026-04-17 01:52:19'),
('CSE2202', 'Vikram Singh', '2nd Year', 'CSE', 'vikram@sviit.ac.in', '9876543214', NULL, 0, '2026-04-17 01:52:19'),
('CSE2301', 'Amit Patel', '3rd Year', 'CSE', 'amit@sviit.ac.in', '9876543210', NULL, 1, '2026-04-17 01:52:19'),
('CSE2302', 'Sneha Verma', '3rd Year', 'CSE', 'sneha@sviit.ac.in', '9876543211', NULL, 1, '2026-04-17 01:52:19'),
('CSE2303', 'Rohan Gupta', '3rd Year', 'CSE', 'rohan@sviit.ac.in', '9876543212', NULL, 0, '2026-04-17 01:52:19'),
('IT2301', 'Kavya Sharma', '3rd Year', 'IT', 'kavya@sviit.ac.in', '9876543215', NULL, 1, '2026-04-17 01:52:19'),
('IT2302', 'Harsh Patel', '3rd Year', 'IT', 'harsh@sviit.ac.in', '9876543216', NULL, 1, '2026-04-17 01:52:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','verifier','admin') DEFAULT 'user',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `is_deleted`, `created_at`) VALUES
(4, 'Shreyas Purohit', 'shreyas@gmail.com', '$2y$10$60LVoGnrSF.kmZiozUU7MumHlUDxgzuTbph2up1A2695lTr1JNhrW', 'admin', 0, '2026-04-01 18:40:08'),
(5, 'Samarth Parihar', 'samarth@gmail.com', '$2y$10$uhc/qAyOSu5dccvzqtuI1.icNOO.uhbbQZPtHj8.Hn4kQ0HB2mwG6', 'verifier', 0, '2026-04-01 18:43:27'),
(6, 'Aaruj Singh', 'aaruj@gmail.com', '$2y$10$/hh.k2Tgghdrwxz08Kxtf.pz3t.U6fZgIsbDwxogzAGgb5U37grre', 'user', 0, '2026-04-01 18:44:38'),
(7, 'Kushagra Kaalbhawar', 'kushagra@gmail.com', '$2y$10$jDXObhy7ob.pUZc.jYJdbeneP099a2jNgX.TXL4YsSb110qyV56sG', 'user', 0, '2026-04-02 10:14:39');

-- --------------------------------------------------------

--
-- Table structure for table `verification_results`
--

CREATE TABLE `verification_results` (
  `result_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `is_valid` tinyint(1) NOT NULL,
  `failure_reason` text DEFAULT NULL,
  `ai_extracted` text DEFAULT NULL,
  `match_score` float DEFAULT NULL,
  `checked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_results`
--

INSERT INTO `verification_results` (`result_id`, `doc_id`, `is_valid`, `failure_reason`, `ai_extracted`, `match_score`, `checked_at`) VALUES
(1, 13, 0, 'The provided image is a generic \'IDENTITY CARD\' with US national symbols (flag, Statue of Liberty) and a generic \'ID NUMBER\', not an Indian Aadhaar Card as requested. The \'aadhaar_number\' field has been populated with the \'ID NUMBER\' found on this card, which is not a valid Aadhaar number format. The card appears to be a stock image or a template, not a legitimate government-issued document.', '{\"aadhaar_number\":\"451234567\",\"name\":\"John Doe\",\"date_of_birth\":\"1999-10-10\",\"gender\":\"Male\",\"is_likely_fake\":true,\"forgery_reason\":\"The provided image is a generic \'IDENTITY CARD\' with US national symbols (flag, Statue of Liberty) and a generic \'ID NUMBER\', not an Indian Aadhaar Card as requested. The \'aadhaar_number\' field has been populated with the \'ID NUMBER\' found on this card, which is not a valid Aadhaar number format. The card appears to be a stock image or a template, not a legitimate government-issued document.\"}', NULL, '2026-04-18 14:46:16'),
(2, 14, 1, 'No overt signs of visual forgery such as misaligned text, inconsistent fonts, obvious image manipulation, or print defects typically associated with fraudulent documents are visible. The card appears to be a standard printed e-Aadhaar, photographed under normal conditions.', '{\"aadhaar_number\":\"423168337709\",\"name\":\"Shreyas Purohit\",\"date_of_birth\":\"07\\/07\\/2005\",\"gender\":\"Male\",\"is_likely_fake\":false,\"forgery_reason\":\"No overt signs of visual forgery such as misaligned text, inconsistent fonts, obvious image manipulation, or print defects typically associated with fraudulent documents are visible. The card appears to be a standard printed e-Aadhaar, photographed under normal conditions.\"}', NULL, '2026-04-18 14:57:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bus_passes`
--
ALTER TABLE `bus_passes`
  ADD PRIMARY KEY (`pass_id`),
  ADD UNIQUE KEY `pass_number` (`pass_number`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verification_results`
--
ALTER TABLE `verification_results`
  ADD PRIMARY KEY (`result_id`),
  ADD KEY `doc_id` (`doc_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `bus_passes`
--
ALTER TABLE `bus_passes`
  MODIFY `pass_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `verification_results`
--
ALTER TABLE `verification_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
