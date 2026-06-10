-- FK Student Club & Event Management System
-- Fresh database setup + seed data
-- Run this in database: fk_scems_db

CREATE DATABASE IF NOT EXISTS `fk_scems_db`;
USE `fk_scems_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `eventwaitinglist`;
DROP TABLE IF EXISTS `eventregistration`;
DROP TABLE IF EXISTS `event`;
DROP TABLE IF EXISTS `committee`;
DROP TABLE IF EXISTS `membership`;
DROP TABLE IF EXISTS `club`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `student`;
DROP TABLE IF EXISTS `user`;

SET FOREIGN_KEY_CHECKS = 1;

-- USER
CREATE TABLE `user` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student','committee','admin') NOT NULL,
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
  PRIMARY KEY (`matric_number`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- ADMIN
CREATE TABLE `admin` (
  `staff_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`staff_id`),
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
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
  PRIMARY KEY (`membership_id`),
  CONSTRAINT `fk_membership_student` FOREIGN KEY (`matric_number`) REFERENCES `student` (`matric_number`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_membership_club` FOREIGN KEY (`club_id`) REFERENCES `club` (`club_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- COMMITTEE
CREATE TABLE `committee` (
  `committee_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membership_id` INT UNSIGNED NOT NULL,
  `club_id` INT UNSIGNED NOT NULL,
  `position` VARCHAR(50) NOT NULL,
  `assigned_date` DATE NOT NULL,
  PRIMARY KEY (`committee_id`),
  CONSTRAINT `fk_committee_membership` FOREIGN KEY (`membership_id`) REFERENCES `membership` (`membership_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_committee_club` FOREIGN KEY (`club_id`) REFERENCES `club` (`club_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
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
  `end_time` TIME NOT NULL,
  `venue` VARCHAR(255) NOT NULL,
  `max_participant` INT UNSIGNED NOT NULL,
  `event_status` ENUM('upcoming','ongoing','completed','cancelled','full','open') NOT NULL,
  `registration_open` TINYINT(1) NOT NULL DEFAULT 1,
  `qr_code` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  CONSTRAINT `fk_event_club` FOREIGN KEY (`club_id`) REFERENCES `club` (`club_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_event_committee` FOREIGN KEY (`committee_id`) REFERENCES `committee` (`committee_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- EVENT REGISTRATION
CREATE TABLE `eventregistration` (
  `registration_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matric_number` VARCHAR(10) NOT NULL,
  `event_id` INT UNSIGNED NOT NULL,
  `registration_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `registration_status` ENUM('registered','cancelled') NOT NULL,
  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `unique_student_event_registration` (`matric_number`, `event_id`),
  CONSTRAINT `fk_registration_student` FOREIGN KEY (`matric_number`) REFERENCES `student` (`matric_number`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_registration_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- EVENT WAITING LIST
CREATE TABLE `eventwaitinglist` (
  `waiting_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matric_number` VARCHAR(10) NOT NULL,
  `event_id` INT UNSIGNED NOT NULL,
  `waiting_status` ENUM('waiting','notified','registered','cancelled') NOT NULL DEFAULT 'waiting',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`waiting_id`),
  UNIQUE KEY `unique_student_event_waiting` (`matric_number`, `event_id`),
  CONSTRAINT `fk_waiting_student` FOREIGN KEY (`matric_number`) REFERENCES `student` (`matric_number`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_waiting_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- ATTENDANCE
CREATE TABLE `attendance` (
  `attendance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` INT UNSIGNED NOT NULL,
  `attendance_status` ENUM('present','absent','late') NOT NULL,
  `check_in_time` DATETIME DEFAULT NULL,
  `point_awarded` TINYINT UNSIGNED DEFAULT 0,
  PRIMARY KEY (`attendance_id`),
  CONSTRAINT `fk_attendance_registration` FOREIGN KEY (`registration_id`) REFERENCES `eventregistration` (`registration_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA
-- Password for all users: password123
-- ============================================================

INSERT INTO `user` (`user_id`, `username`, `email`, `password`, `role`, `contact_no`) VALUES
(1, 'admin01', 'admin01@umpsa.edu.my', 'password123', 'admin', '0123456789'),
(2, 'CB24001', 'student01@student.umpsa.edu.my', 'password123', 'student', '0123456701'),
(3, 'CB24002', 'committee01@student.umpsa.edu.my', 'password123', 'committee', '0123456702'),
(4, 'CB24003', 'student02@student.umpsa.edu.my', 'password123', 'student', '0123456703');

INSERT INTO `admin` (`staff_id`, `user_id`, `name`) VALUES
(1, 1, 'System Administrator');

INSERT INTO `student` (`matric_number`, `user_id`, `name`, `course`, `profile_photo`, `status`) VALUES
('CB24001', 2, 'Aina Rahman', 'Bachelor of Computer Science', NULL, 'active'),
('CB24002', 3, 'Daniel Lim', 'Bachelor of Software Engineering', NULL, 'active'),
('CB24003', 4, 'Siti Nurhaliza', 'Bachelor of Cyber Security', NULL, 'active');

INSERT INTO `club` (`club_id`, `club_name`, `description`, `advisor_name`, `club_status`) VALUES
(1, 'Computing Club', 'Club for programming, software engineering and computing activities.', 'Dr. Ahmad Zaki', 'active'),
(2, 'Cyber Security Club', 'Club for cyber security workshops and competitions.', 'Dr. Nur Aisyah', 'active'),
(3, 'Multimedia Club', 'Club for design, media and creative technology.', 'Dr. Tan Wei', 'active');

INSERT INTO `membership` (`membership_id`, `matric_number`, `club_id`, `membership_status`, `join_date`) VALUES
(1, 'CB24002', 1, 'approved', '2026-01-10'),
(2, 'CB24001', 1, 'approved', '2026-01-15'),
(3, 'CB24003', 2, 'approved', '2026-02-01');

INSERT INTO `committee` (`committee_id`, `membership_id`, `club_id`, `position`, `assigned_date`) VALUES
(1, 1, 1, 'President', '2026-02-10');

INSERT INTO `event`
(`event_id`, `club_id`, `committee_id`, `event_title`, `event_description`, `event_date`, `event_time`, `end_time`, `venue`, `max_participant`, `event_status`, `registration_open`, `qr_code`)
VALUES
(1, 1, 1, 'Coding Workshop', 'Hands-on coding workshop for FK students.', '2026-07-12', '10:30:00', '17:30:00', 'FK Room A', 40, 'upcoming', 1, NULL),
(2, 1, 1, 'AI Talk', 'Talk about artificial intelligence and student projects.', '2026-06-30', '14:00:00', '16:00:00', 'FK Room B', 50, 'upcoming', 1, NULL),
(3, 1, 1, 'Career Briefing', 'Briefing about internship and career preparation.', '2026-05-18', '14:00:00', '16:00:00', 'FK Room A', 60, 'completed', 0, NULL),
(4, 1, 1, 'Leadership Camp', 'Leadership and teamwork camp for FK students.', '2026-08-05', '08:00:00', '17:00:00', 'FK Room C', 35, 'upcoming', 1, NULL);

INSERT INTO `eventregistration`
(`registration_id`, `matric_number`, `event_id`, `registration_date`, `registration_status`)
VALUES
(1, 'CB24001', 3, '2026-05-01 09:15:00', 'registered'),
(2, 'CB24003', 3, '2026-05-02 10:30:00', 'registered'),
(3, 'CB24001', 2, '2026-05-10 11:00:00', 'registered');

INSERT INTO `attendance`
(`attendance_id`, `registration_id`, `attendance_status`, `check_in_time`, `point_awarded`)
VALUES
(1, 1, 'present', '2026-05-18 14:05:00', 10),
(2, 2, 'late', '2026-05-18 14:25:00', 5);
