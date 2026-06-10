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

$matricNumber = $_SESSION["matric"] ?? "";

if ($matricNumber === "") {
    echo json_encode([
        "success" => false,
        "message" => "Student matric number not found in session."
    ]);
    exit;
}

updateEventStatuses($conn);
mysqli_query($conn, "
    UPDATE `eventwaitinglist` ew
    INNER JOIN `event` e ON e.`event_id` = ew.`event_id`
    SET ew.`waiting_status` = 'cancelled'
    WHERE ew.`waiting_status` IN ('waiting','notified')
      AND NOW() >= CONCAT(e.`event_date`, ' ', e.`event_time`)
");

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
        MAX(CASE WHEN mine.`registration_id` IS NOT NULL THEN 1 ELSE 0 END) AS already_registered,
        MAX(waiting.`waiting_id`) AS my_waiting_id,
        MAX(waiting.`waiting_status`) AS my_waiting_status
    FROM `event` e
    INNER JOIN `club` c ON c.`club_id` = e.`club_id`
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    LEFT JOIN `eventregistration` mine
        ON mine.`event_id` = e.`event_id`
        AND mine.`matric_number` = ?
        AND mine.`registration_status` = 'registered'
    LEFT JOIN `eventwaitinglist` waiting
        ON waiting.`event_id` = e.`event_id`
        AND waiting.`matric_number` = ?
        AND waiting.`waiting_status` IN ('waiting', 'notified')
        AND NOW() < CONCAT(e.`event_date`, ' ', e.`event_time`)
    GROUP BY e.`event_id`
    ORDER BY e.`event_date` ASC, e.`event_time` ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $matricNumber, $matricNumber);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve events."
    ]);
    exit;
}

$events = [];
$available = 0;
$registered = 0;
$waiting = 0;
$reminders = [];

while ($row = mysqli_fetch_assoc($result)) {
    $isRegistered = (int) $row["already_registered"] === 1;
    $status = strtolower($row["event_status"]);
    $registeredCount = (int) $row["registered_count"];
    $maxParticipant = (int) $row["max_participant"];
    $isFull = $maxParticipant > 0 && $registeredCount >= $maxParticipant;
    $waitingStatus = $row["my_waiting_status"] ?? "";
    $isWaiting = in_array($waitingStatus, ["waiting", "notified"]);

    if (!$isRegistered && !$isFull && !in_array($status, ["completed", "cancelled"])) {
        $available++;
    }

    if ($isRegistered) {
        $registered++;
    }

    if ($isWaiting) {
        $waiting++;
    }

    if ($isWaiting && !$isFull && !in_array($status, ["completed", "cancelled"])) {
        $reminders[] = [
            "eventId" => $row["event_id"],
            "title" => $row["event_title"],
            "message" => "A slot is available for " . $row["event_title"] . ". You can register now."
        ];
    }

    $events[] = [
        "id" => $row["event_id"],
        "title" => $row["event_title"],
        "description" => $row["event_description"],
        "date" => $row["event_date"],
        "startTime" => $row["event_time"],
        "endTime" => $row["end_time"],
        "venue" => $row["venue"],
        "participants" => $maxParticipant,
        "registeredCount" => $registeredCount,
        "status" => ucfirst($status),
        "clubName" => $row["club_name"],
        "registrationId" => $row["my_registration_id"],
        "waitingId" => $row["my_waiting_id"],
        "waitingStatus" => $waitingStatus,
        "isWaiting" => $isWaiting,
        "alreadyRegistered" => $isRegistered,
        "isFull" => $isFull
    ];
}

echo json_encode([
    "success" => true,
    "summary" => [
        "available" => $available,
        "registered" => $registered,
        "waiting" => $waiting
    ],
    "reminders" => $reminders,
    "events" => $events
]);
