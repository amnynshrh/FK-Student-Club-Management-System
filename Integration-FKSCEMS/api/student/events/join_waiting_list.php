<?php
session_start();
header("Content-Type: application/json");

require_once "../../../config/db.php";
require_once "../../../config/event_status.php";

if (!isset($_SESSION["Login"]) || $_SESSION["Login"] !== "YES" || ($_SESSION["role"] ?? "") !== "student") {
    echo json_encode([
        "success" => false,
        "message" => "Please login as student first."
    ]);
    exit;
}

$eventId = $_POST["eventId"] ?? "";
$matricNumber = $_SESSION["matric"] ?? "";

if (empty($eventId) || empty($matricNumber)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing event or student information."
    ]);
    exit;
}

mysqli_query($conn, "ALTER TABLE `eventregistration` MODIFY `registration_status` ENUM('registered','cancelled') NOT NULL");
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `eventwaitinglist` (
        `waiting_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `matric_number` VARCHAR(10) NOT NULL,
        `event_id` INT UNSIGNED NOT NULL,
        `waiting_status` ENUM('waiting','notified') NOT NULL DEFAULT 'waiting',
        `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `notified_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`waiting_id`),
        UNIQUE KEY `unique_student_event_waiting` (`matric_number`, `event_id`)
    )
");
mysqli_query($conn, "
    UPDATE `eventwaitinglist` ew
    INNER JOIN `event` e ON e.`event_id` = ew.`event_id`
    SET ew.`waiting_status` = 'cancelled'
    WHERE ew.`waiting_status` IN ('waiting','notified')
      AND NOW() >= CONCAT(e.`event_date`, ' ', e.`event_time`)
");

$checkSql = "
    SELECT
        e.`event_status`,
        e.`event_date`,
        e.`event_time`,
        e.`max_participant`,
        COUNT(er.`registration_id`) AS registered_count
    FROM `event` e
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    WHERE e.`event_id` = ?
    GROUP BY e.`event_id`
";

$checkStmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($checkStmt, "i", $eventId);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$event = mysqli_fetch_assoc($checkResult);

if (!$event) {
    echo json_encode([
        "success" => false,
        "message" => "Event not found."
    ]);
    exit;
}

if (in_array($event["event_status"], ["completed", "cancelled", "ongoing"]) || date('Y-m-d H:i:s') >= $event["event_date"] . " " . $event["event_time"]) {
    echo json_encode([
        "success" => false,
        "message" => "This event is closed."
    ]);
    exit;
}

if ((int) $event["registered_count"] < (int) $event["max_participant"]) {
    echo json_encode([
        "success" => false,
        "message" => "This event still has a slot. You can register directly."
    ]);
    exit;
}

$existingRegistrationSql = "
    SELECT `registration_id`, `registration_status`
    FROM `eventregistration`
    WHERE `matric_number` = ?
      AND `event_id` = ?
    LIMIT 1
";

$existingRegistrationStmt = mysqli_prepare($conn, $existingRegistrationSql);
mysqli_stmt_bind_param($existingRegistrationStmt, "si", $matricNumber, $eventId);
mysqli_stmt_execute($existingRegistrationStmt);
$existingRegistrationResult = mysqli_stmt_get_result($existingRegistrationStmt);

$existing = mysqli_fetch_assoc($existingRegistrationResult);

if ($existing && $existing["registration_status"] === "registered") {
    echo json_encode([
        "success" => false,
        "message" => "You already registered for this event."
    ]);
    exit;
}

$waitingSql = "
    INSERT INTO `eventwaitinglist` (`matric_number`, `event_id`, `waiting_status`, `joined_at`)
    SELECT ?, ?, 'waiting', NOW()
    ON DUPLICATE KEY UPDATE `waiting_status` = 'waiting', `joined_at` = NOW(), `notified_at` = NULL
";

$waitingStmt = mysqli_prepare($conn, $waitingSql);
mysqli_stmt_bind_param($waitingStmt, "si", $matricNumber, $eventId);

if (mysqli_stmt_execute($waitingStmt)) {
    echo json_encode([
        "success" => true,
        "message" => "You have joined the waiting list."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to join waiting list."
    ]);
}
