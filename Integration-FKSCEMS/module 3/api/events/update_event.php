<?php
header("Content-Type: application/json");

require_once "../../config/db.php";

$eventId = $_POST["eventId"] ?? "";
$title = $_POST["title"] ?? "";
$description = $_POST["description"] ?? "";
$date = $_POST["date"] ?? "";
$startTime = $_POST["startTime"] ?? "";
$endTime = $_POST["endTime"] ?? "";
$venue = $_POST["venue"] ?? "";
$participants = $_POST["participants"] ?? "";

if (
    empty($eventId) ||
    empty($title) ||
    empty($description) ||
    empty($date) ||
    empty($startTime) ||
    empty($endTime) ||
    empty($venue) ||
    empty($participants)
) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill in all required fields."
    ]);
    exit;
}

if ($endTime <= $startTime) {
    echo json_encode([
        "success" => false,
        "message" => "End time must be after start time."
    ]);
    exit;
}

$now = date("Y-m-d H:i:s");
$eventStart = $date . " " . $startTime;
$eventEnd = $date . " " . $endTime;

if ($now < $eventStart) {
    $status = "upcoming";
} else if ($now >= $eventStart && $now <= $eventEnd) {
    $status = "ongoing";
} else {
    $status = "completed";
}

$sql = "
    UPDATE `event`
    SET
        `event_title` = ?,
        `event_description` = ?,
        `event_date` = ?,
        `event_time` = ?,
        `end_time` = ?,
        `venue` = ?,
        `max_participant` = ?,
        `event_status` = ?
    WHERE `event_id` = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare update statement."
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "ssssssisi",
    $title,
    $description,
    $date,
    $startTime,
    $endTime,
    $venue,
    $participants,
    $status,
    $eventId
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "success" => true,
        "message" => "Event updated successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update event."
    ]);
}
