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

$eventId = $_POST["eventId"] ?? "";
$matricNumber = $_SESSION["matric"] ?? "";

if (empty($eventId) || empty($matricNumber)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing event or student information."
    ]);
    exit;
}

updateEventStatuses($conn);

$checkSql = "
    SELECT
        e.`event_status`,
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

if (in_array($event["event_status"], ["completed", "cancelled"])) {
    echo json_encode([
        "success" => false,
        "message" => "This event is not open for registration."
    ]);
    exit;
}

$existingSql = "
    SELECT `registration_id`, `registration_status`
    FROM `eventregistration`
    WHERE `matric_number` = ?
      AND `event_id` = ?
    LIMIT 1
";

$existingStmt = mysqli_prepare($conn, $existingSql);
mysqli_stmt_bind_param($existingStmt, "si", $matricNumber, $eventId);
mysqli_stmt_execute($existingStmt);
$existingResult = mysqli_stmt_get_result($existingStmt);

$existing = mysqli_fetch_assoc($existingResult);

if ($existing && $existing["registration_status"] === "registered") {
    echo json_encode([
        "success" => false,
        "message" => "You already registered for this event."
    ]);
    exit;
}

if ((int) $event["registered_count"] >= (int) $event["max_participant"]) {
    echo json_encode([
        "success" => false,
        "message" => "This event is already full."
    ]);
    exit;
}

if ($existing && in_array($existing["registration_status"], ["cancelled", "waiting", "notified"])) {
    $updateSql = "
        UPDATE `eventregistration`
        SET `registration_status` = 'registered',
            `confirmation_status` = 'confirmed',
            `registration_date` = NOW()
        WHERE `registration_id` = ?
          AND `matric_number` = ?
    ";

    $updateStmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($updateStmt, "is", $existing["registration_id"], $matricNumber);

    if (mysqli_stmt_execute($updateStmt)) {
        echo json_encode([
            "success" => true,
            "message" => "Event registered successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to register event."
        ]);
    }
    exit;
}

$insertSql = "
    INSERT INTO `eventregistration`
    (`matric_number`, `event_id`, `registration_status`, `confirmation_status`)
    VALUES (?, ?, 'registered', 'confirmed')
";

$insertStmt = mysqli_prepare($conn, $insertSql);
mysqli_stmt_bind_param($insertStmt, "si", $matricNumber, $eventId);

if (mysqli_stmt_execute($insertStmt)) {
    echo json_encode([
        "success" => true,
        "message" => "Event registered successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to register event."
    ]);
}
