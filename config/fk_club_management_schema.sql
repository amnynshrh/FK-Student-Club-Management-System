-- USER
CREATE TABLE `user` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student','admin') NOT NULL,
  `contact_no` VARCHAR(15) NOT NULL,
  PRIMARY KEY (`user_id`)
);

-- STUDENT
CREATE TABLE `student` (
  `matric_number` VARCHAR(10) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `course` VARCHAR(100) NOT NULL,
  `profile_photo` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`matric_number`)
);

-- ADMIN
CREATE TABLE `admin` (
  `staff_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`staff_id`)
);

-- CLUB
CREATE TABLE `club` (
  `club_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `club_name` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `advisor_name` VARCHAR(100) NOT NULL,
  `club_status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`club_id`)
);

-- MEMBERSHIP
CREATE TABLE `membership` (
  `membership_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matric_number` VARCHAR(10) NOT NULL,
  `club_id` INT UNSIGNED NOT NULL,
  `membership_status` ENUM('pending','approved','rejected') NOT NULL,
  `join_date` DATE NOT NULL,
  PRIMARY KEY (`membership_id`)
);

-- COMMITTEE
CREATE TABLE `committee` (
  `committee_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membership_id` INT UNSIGNED NOT NULL,
  `club_id` INT UNSIGNED NOT NULL,
  `position` VARCHAR(50) NOT NULL,
  `assigned_date` DATE NOT NULL,
  PRIMARY KEY (`committee_id`)
);

-- EVENT
CREATE TABLE `event` (
  `event_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `club_id` INT UNSIGNED NOT NULL,
  `committee_id` INT UNSIGNED NOT NULL,
  `event_title` VARCHAR(150) NOT NULL,
  `event_description` TEXT NOT NULL,
  `event_date` DATE NOT NULL,
  `event_time` TIME NOT NULL,
  `venue` VARCHAR(255) NOT NULL,
  `max_participant` INT UNSIGNED NOT NULL,
  `event_status` ENUM('upcoming','ongoing','completed','cancelled') NOT NULL,
  `qr_code` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`event_id`)
);

-- EVENT REGISTRATION
CREATE TABLE `eventregistration` (
  `registration_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matric_number` VARCHAR(10) NOT NULL,
  `event_id` INT UNSIGNED NOT NULL,
  `registration_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `registration_status` ENUM('registered','cancelled') NOT NULL,
  `confirmation_status` ENUM('pending','confirmed') NOT NULL,
  PRIMARY KEY (`registration_id`)
);

-- ATTENDANCE
CREATE TABLE `attendance` (
  `attendance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` INT UNSIGNED NOT NULL,
  `attendance_status` ENUM('present','absent','late') NOT NULL,
  `check_in_time` DATETIME DEFAULT NULL,
  `point_awarded` TINYINT UNSIGNED DEFAULT 0,
  PRIMARY KEY (`attendance_id`)
);
