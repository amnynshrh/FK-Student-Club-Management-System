<?php
header("Content-Type: application/json");

require_once "../../config/db.php";

$eventId = $_GET["id"] ?? "";

if (empty($eventId)) {
    echo json_encode([
        "success" => false,
        "message" => "Event ID is required."
    ]);
    exit;
}

$sql = "
    SELECT
        `event_id`,
        `event_title`,
        `event_description`,
        `event_date`,
        `event_time`,
        `end_time`,
        `venue`,
        `max_participant`
    FROM `event`
    WHERE `event_id` = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare event query."
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $eventId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row) {
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
            "participants" => $row["max_participant"]
        ]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Event not found."
    ]);
}
