<?php
session_start();
header("Content-Type: application/json");

require_once "../../../config/db.php";

if (!isset($_SESSION["Login"]) || $_SESSION["Login"] !== "YES" || ($_SESSION["role"] ?? "") !== "student") {
    echo json_encode([
        "success" => false,
        "message" => "Please login as student first."
    ]);
    exit;
}

$eventId = $_GET["id"] ?? "";
$matricNumber = $_SESSION["matric"] ?? "";

if (empty($eventId)) {
    echo json_encode([
        "success" => false,
        "message" => "Event ID is required."
    ]);
    exit;
}

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

$sql = "
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
        COUNT(er.`registration_id`) AS registered_count,
        MAX(mine.`registration_id`) AS my_registration_id,
        MAX(CASE WHEN mine.`registration_id` IS NOT NULL THEN 1 ELSE 0 END) AS already_registered
    FROM `event` e
    INNER JOIN `club` c ON c.`club_id` = e.`club_id`
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    LEFT JOIN `eventregistration` mine
        ON mine.`event_id` = e.`event_id`
        AND mine.`matric_number` = ?
        AND mine.`registration_status` = 'registered'
    WHERE e.`event_id` = ?
    GROUP BY e.`event_id`
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $matricNumber, $eventId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo json_encode([
        "success" => false,
        "message" => "Event not found."
    ]);
    exit;
}

$registeredCount = (int) $row["registered_count"];
$maxParticipant = (int) $row["max_participant"];

echo json_encode([
    "success" => true,
    "event" => [
        "id" => $row["event_id"],
        "title" => $row["event_title"],
        "description" => $row["event_description"],
        "date" => $row["event_date"],
        "startTime" => $row["event_time"],
        "endTime" => $row["end_time"],
        "venue" => $row["venue"],
        "participants" => $maxParticipant,
        "registeredCount" => $registeredCount,
        "status" => ucfirst($row["event_status"]),
        "clubName" => $row["club_name"],
        "registrationId" => $row["my_registration_id"],
        "alreadyRegistered" => (int) $row["already_registered"] === 1,
        "isFull" => $maxParticipant > 0 && $registeredCount >= $maxParticipant
    ]
]);
