


CREATE TABLE `academic_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_name` varchar(50) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO academic_terms VALUES("1","ภาคเรียนที่ 1","2568");
INSERT INTO academic_terms VALUES("2","ภาคเรียนที่ 2","2568");
INSERT INTO academic_terms VALUES("3","ตลอดปีการศึกษา","2568");





CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','manager','staff','staff_lead') DEFAULT 'user',
  `role_position` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `workload_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `target_group` enum('teacher','staff','both') DEFAULT 'teacher',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `workload_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `attachment_link` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_workload_user` (`user_id`),
  KEY `fk_workload_category` (`category_id`),
  CONSTRAINT `fk_workload_category` FOREIGN KEY (`category_id`) REFERENCES `workload_categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_workload_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `workload_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `work_log_id` (`work_log_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_work_log_id` (`work_log_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `workload_logs_ibfk_1` FOREIGN KEY (`work_log_id`) REFERENCES `workload_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workload_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
