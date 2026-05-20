<?php
session_start();
header("Content-Type: application/json");

require_once "../../config/db.php";

if (!isset($_SESSION["Login"]) || $_SESSION["Login"] !== "YES" || ($_SESSION["role"] ?? "") !== "committee") {
    echo json_encode([
        "success" => false,
        "message" => "Please login as committee first."
    ]);
    exit;
}

$eventId = $_GET["id"] ?? "";

if (empty($eventId)) {
    echo json_encode([
        "success" => false,
        "message" => "Event ID is required."
    ]);
    exit;
}

mysqli_query($conn, "ALTER TABLE `eventregistration` MODIFY `registration_status` ENUM('registered','cancelled','waiting','notified') NOT NULL");

$updateSql = "
    UPDATE `event`
    SET `event_status` =
        CASE
            WHEN NOW() < CONCAT(`event_date`, ' ', `event_time`) THEN 'upcoming'
            WHEN NOW() BETWEEN CONCAT(`event_date`, ' ', `event_time`)
                           AND CONCAT(`event_date`, ' ', `end_time`) THEN 'ongoing'
            ELSE 'completed'
        END
    WHERE `event_status` != 'cancelled'
";
mysqli_query($conn, $updateSql);

$eventSql = "
    SELECT
        e.`event_id`,
        e.`event_title`,
        e.`event_description`,
        e.`event_date`,
        e.`event_time`,
        e.`end_time`,
        e.`venue`,
        e.`max_participant`,
        e.`event_status`,
        c.`club_name`,
        COUNT(er.`registration_id`) AS registered_count
    FROM `event` e
    INNER JOIN `club` c ON c.`club_id` = e.`club_id`
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    WHERE e.`event_id` = ?
    GROUP BY e.`event_id`
";

$eventStmt = mysqli_prepare($conn, $eventSql);
mysqli_stmt_bind_param($eventStmt, "i", $eventId);
mysqli_stmt_execute($eventStmt);
$eventResult = mysqli_stmt_get_result($eventStmt);
$event = mysqli_fetch_assoc($eventResult);

if (!$event) {
    echo json_encode([
        "success" => false,
        "message" => "Event not found."
    ]);
    exit;
}

$participantSql = "
    SELECT
        er.`registration_id`,
        er.`registration_date`,
        er.`confirmation_status`,
        s.`matric_number`,
        s.`name`,
        s.`course`,
        u.`email`,
        u.`contact_no`,
        COALESCE(a.`attendance_status`, 'not marked') AS attendance_status,
        COALESCE(a.`point_awarded`, 0) AS point_awarded
    FROM `eventregistration` er
    INNER JOIN `student` s ON s.`matric_number` = er.`matric_number`
    INNER JOIN `user` u ON u.`user_id` = s.`user_id`
    LEFT JOIN `attendance` a ON a.`registration_id` = er.`registration_id`
    WHERE er.`event_id` = ?
      AND er.`registration_status` = 'registered'
    ORDER BY er.`registration_date` ASC
";

$participantStmt = mysqli_prepare($conn, $participantSql);
mysqli_stmt_bind_param($participantStmt, "i", $eventId);
mysqli_stmt_execute($participantStmt);
$participantResult = mysqli_stmt_get_result($participantStmt);

$participants = [];
while ($row = mysqli_fetch_assoc($participantResult)) {
    $participants[] = [
        "registrationId" => (int) $row["registration_id"],
        "registrationDate" => $row["registration_date"],
        "confirmationStatus" => $row["confirmation_status"],
        "matricNumber" => $row["matric_number"],
        "name" => $row["name"],
        "course" => $row["course"],
        "email" => $row["email"],
        "contactNo" => $row["contact_no"],
        "attendanceStatus" => $row["attendance_status"],
        "pointAwarded" => (int) $row["point_awarded"]
    ];
}

$waitingSql = "
    SELECT
        ew.`registration_id`,
        ew.`registration_status`,
        ew.`registration_date`,
        s.`matric_number`,
        s.`name`
    FROM `eventregistration` ew
    INNER JOIN `student` s ON s.`matric_number` = ew.`matric_number`
    WHERE ew.`event_id` = ?
      AND ew.`registration_status` IN ('waiting', 'notified')
    ORDER BY ew.`registration_date` ASC
";

$waitingStmt = mysqli_prepare($conn, $waitingSql);
mysqli_stmt_bind_param($waitingStmt, "i", $eventId);
mysqli_stmt_execute($waitingStmt);
$waitingResult = mysqli_stmt_get_result($waitingStmt);

$waitingList = [];
while ($row = mysqli_fetch_assoc($waitingResult)) {
    $waitingList[] = [
        "waitingId" => (int) $row["registration_id"],
        "matricNumber" => $row["matric_number"],
        "name" => $row["name"],
        "waitingStatus" => $row["registration_status"],
        "joinedAt" => $row["registration_date"],
        "notifiedAt" => null
    ];
}

echo json_encode([
    "success" => true,
    "event" => [
        "id" => (int) $event["event_id"],
        "title" => $event["event_title"],
        "description" => $event["event_description"],
        "date" => $event["event_date"],
        "startTime" => $event["event_time"],
        "endTime" => $event["end_time"],
        "venue" => $event["venue"],
        "participants" => (int) $event["max_participant"],
        "registeredCount" => (int) $event["registered_count"],
        "status" => ucfirst($event["event_status"]),
        "clubName" => $event["club_name"]
    ],
    "participants" => $participants,
    "waitingList" => $waitingList
]);
