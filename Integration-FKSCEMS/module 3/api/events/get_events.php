<?php
header("Content-Type: application/json");

require_once "../../config/db.php";


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
        `event_id`,
        `event_title`,
        `event_description`,
        `event_date`,
        `event_time`,
        `end_time`,
        `venue`,
        `max_participant`,
        `event_status`
    FROM `event`
    ORDER BY `event_date` ASC, `event_time` ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve events."
    ]);
    exit;
}

$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    $events[] = [
        "id" => $row["event_id"],
        "title" => $row["event_title"],
        "description" => $row["event_description"],
        "date" => $row["event_date"],
        "startTime" => $row["event_time"],
        "endTime" => $row["end_time"],
        "venue" => $row["venue"],
        "participants" => $row["max_participant"],
        "status" => ucfirst($row["event_status"])
    ];
}

echo json_encode([
    "success" => true,
    "events" => $events
]);
