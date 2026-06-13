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
  `point_awarded` TINYINT DEFAULT 0,
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
(4, 'CB24003', 'student02@student.umpsa.edu.my', 'password123', 'student', '0123456703'),
(5, 'CB24004', 'committee02@student.umpsa.edu.my', 'password123', 'committee', '0123456704'),
(6, 'CB24005', 'committee03@student.umpsa.edu.my', 'password123', 'committee', '0123456705'),
(7, 'CB24006', 'committee04@student.umpsa.edu.my', 'password123', 'committee', '0123456706'),
(8, 'CB24007', 'committee05@student.umpsa.edu.my', 'password123', 'committee', '0123456707'),
(9, 'CB24008', 'committee06@student.umpsa.edu.my', 'password123', 'committee', '0123456708'),
(10, 'CB24009', 'student09@student.umpsa.edu.my', 'password123', 'student', '0123456709'),
(11, 'CB24010', 'student10@student.umpsa.edu.my', 'password123', 'student', '0123456710');

INSERT INTO `admin` (`staff_id`, `user_id`, `name`) VALUES
(1, 1, 'System Administrator');

INSERT INTO `student` (`matric_number`, `user_id`, `name`, `course`, `profile_photo`, `status`) VALUES
('CB24001', 2, 'Aina Rahman', 'Bachelor of Computer Science', NULL, 'active'),
('CB24002', 3, 'Daniel Lim', 'Bachelor of Software Engineering', NULL, 'active'),
('CB24003', 4, 'Siti Nurhaliza', 'Bachelor of Cyber Security', NULL, 'active'),
('CB24004', 5, 'Amni Nashirah', 'Bachelor of Software Engineering', NULL, 'active'),
('CB24005', 6, 'Farah Aziz', 'Bachelor of Data Science', NULL, 'active'),
('CB24006', 7, 'Hakim Ismail', 'Bachelor of Multimedia Computing', NULL, 'active'),
('CB24007', 8, 'Mei Ling', 'Bachelor of Network Engineering', NULL, 'active'),
('CB24008', 9, 'Imran Hakimi', 'Bachelor of Information Systems', NULL, 'active'),
('CB24009', 10, 'Nur Iman', 'Bachelor of Cyber Security', NULL, 'active'),
('CB24010', 11, 'Arif Danish', 'Bachelor of Computer Science', NULL, 'active');

INSERT INTO `club` (`club_id`, `club_name`, `description`, `advisor_name`, `club_status`) VALUES
(1, 'Computing Club', 'Club for programming, software engineering and computing activities.', 'Dr. Ahmad Zaki', 'active'),
(2, 'Cyber Security Club', 'Club for cyber security workshops and competitions.', 'Dr. Nur Aisyah', 'active'),
(3, 'Multimedia Club', 'Club for design, media and creative technology.', 'Dr. Tan Wei', 'active'),
(4, 'Data Science Club', 'Club for analytics, machine learning and data projects.', 'Dr. Sarah Nadia', 'active'),
(5, 'Robotics Club', 'Club for robotics, IoT and embedded systems.', 'Dr. Mohd Faiz', 'active'),
(6, 'Sports & Recreation Club', 'Club for wellness, sports and recreational activities.', 'Dr. Lee Chong', 'active');

INSERT INTO `membership` (`membership_id`, `matric_number`, `club_id`, `membership_status`, `join_date`) VALUES
(1, 'CB24002', 1, 'approved', '2026-01-10'),
(2, 'CB24001', 1, 'approved', '2026-01-15'),
(3, 'CB24003', 2, 'approved', '2026-02-01'),
(4, 'CB24004', 2, 'approved', '2026-02-05'),
(5, 'CB24005', 3, 'approved', '2026-02-08'),
(6, 'CB24006', 4, 'approved', '2026-02-10'),
(7, 'CB24007', 5, 'approved', '2026-02-12'),
(8, 'CB24008', 6, 'approved', '2026-02-15'),
(9, 'CB24009', 3, 'approved', '2026-03-01'),
(10, 'CB24010', 4, 'approved', '2026-03-03'),
(11, 'CB24001', 5, 'approved', '2026-03-05'),
(12, 'CB24003', 6, 'approved', '2026-03-08');

INSERT INTO `committee` (`committee_id`, `membership_id`, `club_id`, `position`, `assigned_date`) VALUES
(1, 1, 1, 'President', '2026-02-10'),
(2, 4, 2, 'President', '2026-02-10'),
(3, 5, 3, 'President', '2026-02-10'),
(4, 6, 4, 'President', '2026-02-10'),
(5, 7, 5, 'President', '2026-02-10'),
(6, 8, 6, 'President', '2026-02-10');

INSERT INTO `event`
(`event_id`, `club_id`, `committee_id`, `event_title`, `event_description`, `event_date`, `event_time`, `end_time`, `venue`, `max_participant`, `event_status`, `registration_open`, `qr_code`)
VALUES
(1, 1, 1, 'Coding Workshop', 'Hands-on coding workshop for FK students.', '2026-07-12', '10:30:00', '17:30:00', 'FK Room A', 40, 'open', 1, NULL),
(2, 2, 2, 'Cyber Awareness Talk', 'Talk about cyber hygiene and online safety.', '2026-06-30', '14:00:00', '16:00:00', 'FK Room B', 50, 'upcoming', 0, NULL),
(3, 1, 1, 'Career Briefing', 'Briefing about internship and career preparation.', '2026-05-18', '14:00:00', '16:00:00', 'FK Room A', 60, 'completed', 0, NULL),
(4, 3, 3, 'Leadership Camp', 'Leadership and teamwork camp for FK students.', '2026-08-05', '08:00:00', '17:00:00', 'FK Room C', 35, 'open', 1, NULL),
(5, 4, 4, 'Data Sprint Challenge', 'Real-time analytics challenge for student teams.', '2026-06-12', '00:00:00', '23:59:00', 'Data Lab 1', 30, 'ongoing', 0, NULL),
(6, 5, 5, 'Robotics Mini Hackathon', 'Small robotics hackathon with limited participant seats.', '2026-06-20', '09:00:00', '17:00:00', 'Robotics Lab', 2, 'full', 1, NULL),
(7, 6, 6, 'Futsal Friendly Match', 'Recreational futsal match for FK students.', '2026-06-25', '17:00:00', '19:00:00', 'Sports Complex', 20, 'cancelled', 0, NULL);

INSERT INTO `eventregistration`
(`registration_id`, `matric_number`, `event_id`, `registration_date`, `registration_status`)
VALUES
(1, 'CB24001', 3, '2026-05-01 09:15:00', 'registered'),
(2, 'CB24003', 3, '2026-05-02 10:30:00', 'registered'),
(3, 'CB24009', 3, '2026-05-03 11:45:00', 'registered'),
(4, 'CB24001', 1, '2026-06-01 10:00:00', 'registered'),
(5, 'CB24003', 1, '2026-06-01 10:10:00', 'cancelled'),
(6, 'CB24009', 4, '2026-06-02 09:00:00', 'registered'),
(7, 'CB24010', 4, '2026-06-02 09:10:00', 'registered'),
(8, 'CB24001', 5, '2026-06-10 08:30:00', 'registered'),
(9, 'CB24003', 5, '2026-06-10 08:40:00', 'registered'),
(10, 'CB24001', 6, '2026-06-11 08:00:00', 'registered'),
(11, 'CB24003', 6, '2026-06-11 08:05:00', 'registered'),
(12, 'CB24009', 7, '2026-06-11 12:00:00', 'registered'),
(13, 'CB24010', 7, '2026-06-11 12:15:00', 'cancelled');

INSERT INTO `eventwaitinglist`
(`waiting_id`, `matric_number`, `event_id`, `waiting_status`, `joined_at`, `notified_at`)
VALUES
(1, 'CB24009', 6, 'waiting', '2026-06-11 08:15:00', NULL),
(2, 'CB24010', 6, 'notified', '2026-06-11 08:20:00', '2026-06-11 09:00:00'),
(3, 'CB24005', 6, 'cancelled', '2026-06-11 08:25:00', NULL);

INSERT INTO `attendance`
(`attendance_id`, `registration_id`, `attendance_status`, `check_in_time`, `point_awarded`)
VALUES
(1, 1, 'present', '2026-05-18 14:05:00', 10),
(2, 2, 'late', '2026-05-18 14:25:00', 5),
(3, 3, 'absent', NULL, 0),
(4, 8, 'present', '2026-06-12 09:05:00', 10),
(5, 9, 'late', '2026-06-12 09:35:00', 5);
