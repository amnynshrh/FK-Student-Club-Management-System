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

$matricNumber = $_SESSION["matric"] ?? "";

if ($matricNumber === "") {
    echo json_encode([
        "success" => false,
        "message" => "Student matric number not found in session."
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
mysqli_query($conn, "ALTER TABLE `eventregistration` MODIFY `registration_status` ENUM('registered','cancelled','waiting','notified') NOT NULL");

$sql = "
    SELECT
        er.`registration_id`,
        er.`registration_status`,
        er.`confirmation_status`,
        e.`event_id`,
        e.`event_title`,
        e.`event_date`,
        e.`event_time`,
        e.`end_time`,
        e.`venue`,
        e.`event_status`,
        c.`club_name`,
        COALESCE(a.`attendance_status`, '') AS attendance_status,
        COALESCE(a.`point_awarded`, 0) AS point_awarded
    FROM `eventregistration` er
    INNER JOIN `event` e ON e.`event_id` = er.`event_id`
    INNER JOIN `club` c ON c.`club_id` = e.`club_id`
    LEFT JOIN `attendance` a ON a.`registration_id` = er.`registration_id`
    WHERE er.`matric_number` = ?
    ORDER BY er.`registration_date` DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $matricNumber);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$history = [];
$totalRegistered = 0;
$attended = 0;
$upcoming = 0;
$cancelled = 0;

while ($row = mysqli_fetch_assoc($result)) {
    if ($row["registration_status"] === "registered") {
        $totalRegistered++;
    }

    if (in_array($row["attendance_status"], ["present", "late"])) {
        $attended++;
    }

    if ($row["event_status"] === "upcoming" && $row["registration_status"] === "registered") {
        $upcoming++;
    }

    if ($row["registration_status"] === "cancelled" || $row["event_status"] === "cancelled") {
        $cancelled++;
    }

    $history[] = [
        "registrationId" => $row["registration_id"],
        "registrationStatus" => $row["registration_status"],
        "confirmationStatus" => $row["confirmation_status"],
        "eventId" => $row["event_id"],
        "title" => $row["event_title"],
        "date" => $row["event_date"],
        "startTime" => $row["event_time"],
        "endTime" => $row["end_time"],
        "venue" => $row["venue"],
        "eventStatus" => ucfirst($row["event_status"]),
        "clubName" => $row["club_name"],
        "attendanceStatus" => $row["attendance_status"],
        "points" => (int) $row["point_awarded"]
    ];
}

echo json_encode([
    "success" => true,
    "summary" => [
        "totalRegistered" => $totalRegistered,
        "attended" => $attended,
        "upcoming" => $upcoming,
        "cancelled" => $cancelled
    ],
    "history" => $history
]);
