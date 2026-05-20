<?php
header("Content-Type: application/json");

require_once "../../config/db.php";

$eventId = $_POST["eventId"] ?? "";

if (empty($eventId)) {
    echo json_encode([
        "success" => false,
        "message" => "Event ID is required."
    ]);
    exit;
}

$sql = "DELETE FROM `event` WHERE `event_id` = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare delete statement."
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $eventId);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "success" => true,
        "message" => "Event deleted successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to delete event."
    ]);
}
