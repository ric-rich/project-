-- KSG SMI Performance System
-- Database Schema
-- Version: 1.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `ksg_smi_performance`
--
CREATE DATABASE IF NOT EXISTS `ksg_smi_performance` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ksg_smi_performance`;

-- --------------------------------------------------------

--
-- Table structure for table `access_logs`
--

CREATE TABLE `access_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `admin_id` INT(11) DEFAULT NULL,
  `user_type` ENUM('user','admin','system') NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `index_code` VARCHAR(255) NOT NULL,
  `profile_picture` MEDIUMBLOB DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `index_code`) VALUES
(1, 'Admin', 'admin@ksg.ac.ke', '$2y$10$g0aL.R6j.X3/a5.b.C4d.e.U/y.f.G6h.I8j.K0l.M2n.O4p.Q6q.S', '$2y$10$NotTheRealHashForRichmond524'); -- pass: admin123

-- --------------------------------------------------------

--
-- Table structure for table `system_backups`
--

CREATE TABLE `system_backups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT(11) NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_categories`
--

CREATE TABLE `task_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_categories`
--

INSERT INTO `task_categories` (`id`, `name`, `description`) VALUES
(1, 'Financial Stewardship and Discipline', NULL),
(2, 'Service Delivery', NULL),
(3, 'Core Mandate', NULL),
(4, 'Administration and Infrastructure', NULL),
(5, 'Cross-Cutting Issues', NULL);


-- Messages Table (Contact Form)
CREATE TABLE `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100),
  `email` VARCHAR(150),
  `message` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- --------------------------------------------------------

--
-- Table structure for table `task_templates`
--

CREATE TABLE `task_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_templates`
--

INSERT INTO `task_templates` (`id`, `category_id`, `title`) VALUES
(1, 1, 'Revenue'),
(2, 1, 'Debt Management'),
(3, 1, 'Pending Bills'),
(4, 1, 'Zero Fault Audits'),
(5, 2, 'Implementation of Citizens\' Service Delivery Charter'),
(6, 2, 'Resolution of Public Complaints'),
(7, 3, 'Review existing training programs.'),
(8, 3, 'Develop and roll out new training programs.'),
(9, 3, 'Undertake consultancy and research activities.'),
(10, 3, 'Organize and host national symposia or conferences.'),
(11, 3, 'Improve productivity.'),
(12, 3, 'Manage the customer experience and satisfaction score.'),
(13, 3, 'Conduct a training needs assessment.'),
(14, 3, 'Mobilize participants for training.'),
(15, 3, 'Convert and offer existing programs online.'),
(16, 3, 'Carry out program and facilitator evaluations.'),
(17, 3, 'Identify and implement innovation and creativity initiatives.'),
(18, 3, 'Institutionalize Performance Management Culture'),
(19, 4, 'Operationalize digitalized processes.'),
(20, 4, 'Implement a risk register.'),
(21, 4, 'Implement Quality Management Systems.'),
(22, 4, 'Implementation of Presidential Directives'),
(23, 5, 'Youth Internships, Industrial Attachment and Apprenticeship'),
(24, 5, 'Competence Development'),
(25, 5, 'National Cohesion and Values');

-- --------------------------------------------------------

--
-- Table structure for table `task_uploads`
--

CREATE TABLE `task_uploads` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(100) NOT NULL,
  `file_size` INT(11) NOT NULL,
  `file_data` MEDIUMBLOB NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `profile_picture` MEDIUMBLOB DEFAULT NULL,
  `notification_preferences` JSON DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `department`, `job_title`) VALUES
(1, 'John Doe', 'john.doe@ksg.ac.ke', '$2y$10$a.b.CdeFgHiJkLmNoPqRs.TuvWxyZaBcDeFgHiJkLmNoPqRs.TuvW', 'Training & Development', 'Training Officer'); -- pass: user123

-- --------------------------------------------------------

--
-- Table structure for table `user_tasks`
--

CREATE TABLE `user_tasks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `priority` VARCHAR(50) NOT NULL DEFAULT 'medium',
  `due_date` DATE NOT NULL,
  `created_date` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `completed_date` TIMESTAMP NULL DEFAULT NULL,
  `assigned_by` VARCHAR(255) DEFAULT NULL,
  `assigned_by_id` INT(11) DEFAULT NULL,
  `instructions` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_by_id` (`assigned_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `user_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `user_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stored Procedures
--

DELIMITER $$

CREATE PROCEDURE `LogUserActivity` (IN `p_user_id` INT, IN `p_admin_id` INT, IN `p_user_type` VARCHAR(50), IN `p_action` VARCHAR(255), IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
    INSERT INTO access_logs (user_id, admin_id, user_type, action, ip_address, user_agent)
    VALUES (p_user_id, p_admin_id, p_user_type, p_action, p_ip_address, p_user_agent);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Foreign Key Constraints
--

ALTER TABLE `access_logs`
  ADD CONSTRAINT `access_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `access_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `system_backups`
  ADD CONSTRAINT `system_backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `task_templates`
  ADD CONSTRAINT `task_templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `task_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `task_uploads`
  ADD CONSTRAINT `task_uploads_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `user_tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `task_uploads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `user_tasks`
  ADD CONSTRAINT `user_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_tasks_ibfk_2` FOREIGN KEY (`assigned_by_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;
