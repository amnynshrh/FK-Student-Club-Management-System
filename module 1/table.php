<?php
/**
 * Database Setup Script for FK_SCEMS_DB
 * Version: 1.1 (Corrected for PHP compatibility)
 */

$host     = '127.0.0.1';
$db_name  = 'fk_scems_db';
$username = 'root';
$password = ''; // Default XAMPP/WAMP password
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$db_name` ");

    // Full SQL Schema
    $sql = "
    -- 1. User Table (Increased password length for hashing)
    CREATE TABLE IF NOT EXISTS `user` (
      `user_id` int(10) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL, 
      `role` varchar(50) NOT NULL,
      `contact_no` varchar(20) NOT NULL,
      PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 2. Student Table
    CREATE TABLE IF NOT EXISTS `student` (
      `matric_number` varchar(8) NOT NULL,
      `user_id` int(10) NOT NULL,
      `name` varchar(100) NOT NULL,
      `course` varchar(50) NOT NULL,
      `profile_photo` varchar(255) NOT NULL,
      `status` varchar(25) NOT NULL,
      PRIMARY KEY (`matric_number`),
      CONSTRAINT `fk_stud_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 3. Admin Table
    CREATE TABLE IF NOT EXISTS `admin` (
      `staff_id` int(10) NOT NULL AUTO_INCREMENT,
      `user_id` int(10) NOT NULL,
      `name` varchar(100) NOT NULL,
      PRIMARY KEY (`staff_id`),
      CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 4. Club Table
    CREATE TABLE IF NOT EXISTS `club` (
      `club_id` int(10) NOT NULL AUTO_INCREMENT,
      `club_name` varchar(100) NOT NULL,
      `description` varchar(200) NOT NULL,
      `advisor_name` varchar(50) NOT NULL,
      `club_status` varchar(20) NOT NULL,
      PRIMARY KEY (`club_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 5. Membership Table
    CREATE TABLE IF NOT EXISTS `membership` (
      `membership_id` int(10) NOT NULL AUTO_INCREMENT,
      `matric_number` varchar(8) NOT NULL,
      `club_id` int(10) NOT NULL,
      `membership_status` varchar(50) NOT NULL,
      `join_date` datetime NOT NULL,
      PRIMARY KEY (`membership_id`),
      CONSTRAINT `fk_membership_club` FOREIGN KEY (`club_id`) REFERENCES `club` (`club_id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_membership_stud` FOREIGN KEY (`matric_number`) REFERENCES `student` (`matric_number`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 6. Committee Table
    CREATE TABLE IF NOT EXISTS `committee` (
      `committee_id` int(10) NOT NULL AUTO_INCREMENT,
      `membership_id` int(10) NOT NULL,
      `club_id` int(10) NOT NULL,
      `position` varchar(30) NOT NULL,
      `assigned_date` datetime NOT NULL,
      PRIMARY KEY (`committee_id`),
      CONSTRAINT `fk_committee_club` FOREIGN KEY (`club_id`) REFERENCES `club` (`club_id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_committee_membership` FOREIGN KEY (`membership_id`) REFERENCES `membership` (`membership_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 7. Event Table
    CREATE TABLE IF NOT EXISTS `event` (
      `event_id` int(10) NOT NULL AUTO_INCREMENT,
      `club_id` int(10) NOT NULL,
      `committee_id` int(10) NOT NULL,
      `event_title` varchar(100) NOT NULL,
      `event_description` varchar(200) NOT NULL,
      `event_start_datetime` datetime NOT NULL,
      `venue` varchar(200) NOT NULL,
      `max_participant` int(11) NOT NULL,
      `event_status` varchar(20) NOT NULL,
      `qr_code` varchar(255) NOT NULL,
      PRIMARY KEY (`event_id`),
      CONSTRAINT `fk_event_club` FOREIGN KEY (`club_id`) REFERENCES `club` (`club_id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_event_committee` FOREIGN KEY (`committee_id`) REFERENCES `committee` (`committee_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 8. Event Registration Table
    CREATE TABLE IF NOT EXISTS `eventregistration` (
      `registration_id` int(10) NOT NULL AUTO_INCREMENT,
      `matric_number` varchar(8) NOT NULL,
      `event_id` int(10) NOT NULL,
      `registration_date` datetime NOT NULL,
      `registration_status` varchar(20) NOT NULL,
      `confirmation_status` varchar(20) NOT NULL,
      PRIMARY KEY (`registration_id`),
      CONSTRAINT `fk_eventReg_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_eventReg_student` FOREIGN KEY (`matric_number`) REFERENCES `student` (`matric_number`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 9. Attendance Table
    CREATE TABLE IF NOT EXISTS `attendance` (
      `attendance_id` int(10) NOT NULL AUTO_INCREMENT,
      `registration_id` int(10) NOT NULL,
      `attendance_status` varchar(20) NOT NULL,
      `check_in_time` datetime NOT NULL,
      `point_awarded` int(10) NOT NULL,
      PRIMARY KEY (`attendance_id`),
      CONSTRAINT `fk_attendance_eventReg` FOREIGN KEY (`registration_id`) REFERENCES `eventregistration` (`registration_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "<h2>Success!</h2>";
    echo "Database and all tables for <b>$db_name</b> have been created successfully.";

} catch (PDOException $e) {
    echo "<h2>Database Error</h2>";
    echo "Message: " . $e->getMessage();
}
?>