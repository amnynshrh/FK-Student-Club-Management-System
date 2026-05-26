<?php
session_start();
header("Content-Type: application/json");

require_once "../../config/db.php";
require_once "../../config/event_status.php";

if (!isset($_SESSION["Login"]) || $_SESSION["Login"] !== "YES" || ($_SESSION["role"] ?? "") !== "admin") {
    echo json_encode([
        "success" => false,
        "message" => "Please login as admin first."
    ]);
    exit;
}

updateEventStatuses($conn);

function fetchSingleValue($conn, $sql, $defaultValue = 0)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $defaultValue;
    }

    $row = mysqli_fetch_row($result);
    return $row ? $row[0] : $defaultValue;
}

function fetchRows($conn, $sql)
{
    $rows = [];
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

$totalEvents = (int) fetchSingleValue($conn, "SELECT COUNT(*) FROM `event`");
$totalRegistrations = (int) fetchSingleValue(
    $conn,
    "SELECT COUNT(*) FROM `eventregistration` WHERE `registration_status` = 'registered'"
);

$mostActiveClubRows = fetchRows($conn, "
    SELECT c.`club_name`, COUNT(e.`event_id`) AS total_events
    FROM `club` c
    LEFT JOIN `event` e ON e.`club_id` = c.`club_id`
    GROUP BY c.`club_id`, c.`club_name`
    ORDER BY total_events DESC, c.`club_name` ASC
    LIMIT 1
");

$popularEventRows = fetchRows($conn, "
    SELECT
        e.`event_title`,
        c.`club_name`,
        COUNT(er.`registration_id`) AS total_registrations
    FROM `event` e
    INNER JOIN `club` c ON c.`club_id` = e.`club_id`
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    GROUP BY e.`event_id`, e.`event_title`, c.`club_name`
    ORDER BY total_registrations DESC, e.`event_title` ASC
    LIMIT 1
");

$statusRows = fetchRows($conn, "
    SELECT `event_status`, COUNT(*) AS total
    FROM `event`
    GROUP BY `event_status`
");

$statusCounts = [
    "upcoming" => 0,
    "ongoing" => 0,
    "completed" => 0,
    "cancelled" => 0
];

foreach ($statusRows as $row) {
    $key = strtolower($row["event_status"]);
    if (isset($statusCounts[$key])) {
        $statusCounts[$key] = (int) $row["total"];
    }
}

$trendRows = fetchRows($conn, "
    SELECT
        WEEK(`registration_date`, 1) - WEEK(DATE_SUB(`registration_date`, INTERVAL DAYOFMONTH(`registration_date`) - 1 DAY), 1) + 1 AS week_number,
        COUNT(*) AS total
    FROM `eventregistration`
    WHERE `registration_status` = 'registered'
      AND YEAR(`registration_date`) = YEAR(CURDATE())
      AND MONTH(`registration_date`) = MONTH(CURDATE())
    GROUP BY week_number
    ORDER BY week_number ASC
");

$registrationTrend = [];
for ($week = 1; $week <= 5; $week++) {
    $registrationTrend[$week] = [
        "label" => "Week " . $week,
        "total" => 0
    ];
}

foreach ($trendRows as $row) {
    $weekNumber = (int) $row["week_number"];
    if ($weekNumber >= 1 && $weekNumber <= 5) {
        $registrationTrend[$weekNumber]["total"] = (int) $row["total"];
    }
}

$eventsByClubRows = fetchRows($conn, "
    SELECT c.`club_name`, COUNT(e.`event_id`) AS total_events
    FROM `club` c
    LEFT JOIN `event` e ON e.`club_id` = c.`club_id`
    GROUP BY c.`club_id`, c.`club_name`
    ORDER BY total_events DESC, c.`club_name` ASC
    LIMIT 6
");

$participantsRows = fetchRows($conn, "
    SELECT
        e.`event_id`,
        e.`event_title`,
        COUNT(er.`registration_id`) AS total_registrations
    FROM `event` e
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    GROUP BY e.`event_id`, e.`event_title`
    ORDER BY total_registrations DESC, e.`event_title` ASC
    LIMIT 4
");

$popularEventsRows = fetchRows($conn, "
    SELECT
        e.`event_title`,
        c.`club_name`,
        COUNT(er.`registration_id`) AS total_registrations
    FROM `event` e
    INNER JOIN `club` c ON c.`club_id` = e.`club_id`
    LEFT JOIN `eventregistration` er
        ON er.`event_id` = e.`event_id`
        AND er.`registration_status` = 'registered'
    GROUP BY e.`event_id`, e.`event_title`, c.`club_name`
    ORDER BY total_registrations DESC, e.`event_title` ASC
    LIMIT 4
");

$mostActiveClub = [
    "name" => $mostActiveClubRows[0]["club_name"] ?? "No club yet",
    "totalEvents" => isset($mostActiveClubRows[0]) ? (int) $mostActiveClubRows[0]["total_events"] : 0
];

$mostPopularEvent = [
    "title" => $popularEventRows[0]["event_title"] ?? "No event yet",
    "clubName" => $popularEventRows[0]["club_name"] ?? "-",
    "totalRegistrations" => isset($popularEventRows[0]) ? (int) $popularEventRows[0]["total_registrations"] : 0
];

echo json_encode([
    "success" => true,
    "summary" => [
        "totalEvents" => $totalEvents,
        "totalRegistrations" => $totalRegistrations,
        "mostActiveClub" => $mostActiveClub,
        "mostPopularEvent" => $mostPopularEvent
    ],
    "statusCounts" => $statusCounts,
    "registrationTrend" => array_values($registrationTrend),
    "eventsByClub" => array_map(function ($row) {
        return [
            "clubName" => $row["club_name"],
            "totalEvents" => (int) $row["total_events"]
        ];
    }, $eventsByClubRows),
    "participantsByEvent" => array_map(function ($row) {
        return [
            "eventId" => (int) $row["event_id"],
            "title" => $row["event_title"],
            "totalRegistrations" => (int) $row["total_registrations"]
        ];
    }, $participantsRows),
    "popularEvents" => array_map(function ($row) {
        return [
            "title" => $row["event_title"],
            "clubName" => $row["club_name"],
            "totalRegistrations" => (int) $row["total_registrations"]
        ];
    }, $popularEventsRows)
]);
