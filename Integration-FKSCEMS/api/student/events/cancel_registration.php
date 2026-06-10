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

$registrationId = $_POST["registrationId"] ?? "";
$matricNumber = $_SESSION["matric"] ?? "";

if (empty($registrationId) || empty($matricNumber)) {
    echo json_encode([
        "success" => false,
        "message" => "Registration ID is required."
    ]);
    exit;
}

mysqli_query($conn, "ALTER TABLE `eventregistration` MODIFY `registration_status` ENUM('registered','cancelled') NOT NULL");

$eventSql = "
    SELECT `event_id`
    FROM `eventregistration`
    WHERE `registration_id` = ?
      AND `matric_number` = ?
    LIMIT 1
";

$eventStmt = mysqli_prepare($conn, $eventSql);
mysqli_stmt_bind_param($eventStmt, "is", $registrationId, $matricNumber);
mysqli_stmt_execute($eventStmt);
$eventResult = mysqli_stmt_get_result($eventStmt);
$eventRow = mysqli_fetch_assoc($eventResult);

$sql = "
    UPDATE `eventregistration`
    SET `registration_status` = 'cancelled'
    WHERE `registration_id` = ?
      AND `matric_number` = ?
      AND `registration_status` = 'registered'
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $registrationId, $matricNumber);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    if ($eventRow) {
        notifyNextWaitingStudent($conn, (int) $eventRow["event_id"]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Registration cancelled successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Unable to cancel this registration."
    ]);
}

function notifyNextWaitingStudent($conn, $eventId)
{
    $slotSql = "
        SELECT
            e.`max_participant`,
            COUNT(er.`registration_id`) AS registered_count
        FROM `event` e
        LEFT JOIN `eventregistration` er
            ON er.`event_id` = e.`event_id`
            AND er.`registration_status` = 'registered'
        WHERE e.`event_id` = ?
        GROUP BY e.`event_id`
    ";

    $slotStmt = mysqli_prepare($conn, $slotSql);
    mysqli_stmt_bind_param($slotStmt, "i", $eventId);
    mysqli_stmt_execute($slotStmt);
    $slotResult = mysqli_stmt_get_result($slotStmt);
    $slot = mysqli_fetch_assoc($slotResult);

    if (!$slot || (int) $slot["registered_count"] >= (int) $slot["max_participant"]) {
        return;
    }

    $notifySql = "
        UPDATE `eventwaitinglist`
        SET `waiting_status` = 'notified',
            `notified_at` = NOW()
        WHERE `event_id` = ?
          AND `waiting_status` = 'waiting'
        ORDER BY `joined_at` ASC
        LIMIT 1
    ";

    $notifyStmt = mysqli_prepare($conn, $notifySql);
    if ($notifyStmt) {
        mysqli_stmt_bind_param($notifyStmt, "i", $eventId);
        mysqli_stmt_execute($notifyStmt);
    }
}
