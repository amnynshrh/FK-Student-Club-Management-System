<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetch_one($conn, $sql, $types = "", $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query prepare failed: " . $conn->error);
    }
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? ($result->fetch_assoc() ?: []) : [];
}

function fetch_all($conn, $sql, $types = "", $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query prepare failed: " . $conn->error);
    }
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$club_name = trim($_GET['club_name'] ?? '');
$month_year = trim($_GET['month_year'] ?? '');
$event_name = trim($_GET['event_name'] ?? '');

$clubs = fetch_all($conn, "SELECT club_name FROM club ORDER BY club_name ASC");
$months = fetch_all(
    $conn,
    "SELECT DISTINCT DATE_FORMAT(event_date, '%Y-%m') AS month_value,
            DATE_FORMAT(event_date, '%M (%Y)') AS month_label
     FROM event
     ORDER BY month_value DESC"
);

$filters = [];
$params = [];
$types = "";

if ($club_name !== "") {
    $filters[] = "c.club_name = ?";
    $params[] = $club_name;
    $types .= "s";
}

if ($month_year !== "") {
    $filters[] = "DATE_FORMAT(e.event_date, '%Y-%m') = ?";
    $params[] = $month_year;
    $types .= "s";
}

if ($event_name !== "") {
    $filters[] = "e.event_title LIKE ?";
    $params[] = "%" . $event_name . "%";
    $types .= "s";
}

$where_sql = $filters ? "WHERE " . implode(" AND ", $filters) : "";

$summary = fetch_one(
    $conn,
    "SELECT
        COUNT(DISTINCT e.event_id) AS total_events,
        COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END) AS total_participants,
        COALESCE(SUM(CASE WHEN er.registration_status = 'registered' THEN a.point_awarded ELSE 0 END), 0) AS total_points,
        ROUND(
            SUM(CASE WHEN er.registration_status = 'registered' AND LOWER(a.attendance_status) = 'present' THEN 1 ELSE 0 END)
            / NULLIF(COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END), 0) * 100,
            2
        ) AS attendance_rate
     FROM event e
     INNER JOIN club c ON e.club_id = c.club_id
     LEFT JOIN eventregistration er ON e.event_id = er.event_id
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $where_sql",
    $types,
    $params
);

$total_events = (int) ($summary['total_events'] ?? 0);
$total_participants = (int) ($summary['total_participants'] ?? 0);
$attendance_rate = ($summary['attendance_rate'] ?? null) !== null ? (float) $summary['attendance_rate'] : 0;
$total_points = (int) ($summary['total_points'] ?? 0);

$barLabels = [];
$barData = [];
$clubChartFilters = [];
$clubChartParams = [];
$clubChartTypes = "";

if ($month_year !== "") {
    $clubChartFilters[] = "DATE_FORMAT(e.event_date, '%Y-%m') = ?";
    $clubChartParams[] = $month_year;
    $clubChartTypes .= "s";
}

if ($event_name !== "") {
    $clubChartFilters[] = "e.event_title LIKE ?";
    $clubChartParams[] = "%" . $event_name . "%";
    $clubChartTypes .= "s";
}

$clubChartWhere = $clubChartFilters ? "WHERE " . implode(" AND ", $clubChartFilters) : "";
$clubAttendanceRows = fetch_all(
    $conn,
    "SELECT c.club_name,
        ROUND(
            SUM(CASE WHEN er.registration_status = 'registered' AND LOWER(a.attendance_status) = 'present' THEN 1 ELSE 0 END)
            / NULLIF(COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END), 0) * 100,
            2
        ) AS attendance_rate
     FROM club c
     LEFT JOIN event e ON c.club_id = e.club_id
     LEFT JOIN eventregistration er ON e.event_id = er.event_id
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $clubChartWhere
     GROUP BY c.club_id, c.club_name
     ORDER BY attendance_rate DESC, c.club_name ASC",
    $clubChartTypes,
    $clubChartParams
);

foreach ($clubAttendanceRows as $row) {
    $barLabels[] = $row['club_name'];
    $barData[] = $row['attendance_rate'] !== null ? (float) $row['attendance_rate'] : 0;
}

$eventLabels = [];
$eventData = [];

if ($club_name !== "") {
    $eventRows = fetch_all(
        $conn,
        "SELECT e.event_title,
            COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END) AS total_participants
         FROM event e
         INNER JOIN club c ON e.club_id = c.club_id
         LEFT JOIN eventregistration er ON e.event_id = er.event_id
         $where_sql
         GROUP BY e.event_id, e.event_title
         ORDER BY total_participants DESC, e.event_title ASC",
        $types,
        $params
    );

    foreach ($eventRows as $row) {
        $eventLabels[] = $row['event_title'];
        $eventData[] = (int) $row['total_participants'];
    }
}

$statusRows = fetch_all(
    $conn,
    "SELECT attendance_status, COUNT(DISTINCT registration_id) AS total
     FROM (
        SELECT
            er.registration_id,
            CASE
                WHEN MAX(a.attendance_id) IS NULL THEN 'Not Marked'
                ELSE CONCAT(UCASE(LEFT(MAX(a.attendance_status), 1)), SUBSTRING(MAX(a.attendance_status), 2))
            END AS attendance_status
        FROM event e
        INNER JOIN club c ON e.club_id = c.club_id
        INNER JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
        LEFT JOIN attendance a ON er.registration_id = a.registration_id
        $where_sql
        GROUP BY er.registration_id
     ) attendance_summary
     GROUP BY attendance_status
     ORDER BY total DESC",
    $types,
    $params
);

$donutLabels = [];
$donutData = [];
foreach ($statusRows as $row) {
    $donutLabels[] = $row['attendance_status'];
    $donutData[] = (int) $row['total'];
}

$trendRows = fetch_all(
    $conn,
    "SELECT DATE_FORMAT(e.event_date, '%Y-%m') AS month_value,
            DATE_FORMAT(e.event_date, '%b %Y') AS month_label,
            COUNT(DISTINCT er.registration_id) AS registered_total
     FROM event e
     INNER JOIN club c ON e.club_id = c.club_id
     INNER JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
     $where_sql
     GROUP BY month_value, month_label
     ORDER BY month_value ASC",
    $types,
    $params
);

$lineLabels = [];
$lineData = [];
foreach ($trendRows as $row) {
    $lineLabels[] = $row['month_label'];
    $lineData[] = (int) $row['registered_total'];
}

$topStudents = fetch_all(
    $conn,
    "SELECT s.matric_number, s.name, s.course,
            COALESCE(SUM(a.point_awarded), 0) AS total_points
     FROM student s
     INNER JOIN eventregistration er ON s.matric_number = er.matric_number AND er.registration_status = 'registered'
     INNER JOIN event e ON er.event_id = e.event_id
     INNER JOIN club c ON e.club_id = c.club_id
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $where_sql
     GROUP BY s.matric_number, s.name, s.course
     ORDER BY total_points DESC, s.name ASC
     LIMIT 5",
    $types,
    $params
);

if ($club_name === "") {
    $topClubs = fetch_all(
        $conn,
        "SELECT c.club_name,
            COUNT(DISTINCT e.event_id) AS total_events,
            COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END) AS total_participants,
            COALESCE(SUM(CASE WHEN er.registration_status = 'registered' THEN a.point_awarded ELSE 0 END), 0) AS total_points
         FROM club c
         LEFT JOIN event e ON c.club_id = e.club_id
         LEFT JOIN eventregistration er ON e.event_id = er.event_id
         LEFT JOIN attendance a ON er.registration_id = a.registration_id
         $clubChartWhere
         GROUP BY c.club_id, c.club_name
         ORDER BY total_points DESC, total_participants DESC, c.club_name ASC
         LIMIT 3",
        $clubChartTypes,
        $clubChartParams
    );
    $committeeRows = [];
} else {
    $topClubs = [];
    $committeeRows = fetch_all(
        $conn,
        "SELECT s.name, cm.position
         FROM committee cm
         INNER JOIN membership m ON cm.membership_id = m.membership_id
         INNER JOIN student s ON m.matric_number = s.matric_number
         INNER JOIN club c ON cm.club_id = c.club_id
         WHERE c.club_name = ?
         ORDER BY FIELD(cm.position, 'President', 'Vice President', 'Secretary', 'Treasurer'), cm.position ASC, s.name ASC",
        "s",
        [$club_name]
    );
}

$activeFilterCount = ($club_name !== "" ? 1 : 0) + ($month_year !== "" ? 1 : 0) + ($event_name !== "" ? 1 : 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="attendance_dashboard.css">
</head>
<body>
    <?php include('adminHeader.php'); ?>
    <div class="page-container">
        <header class="page-header">
            <div>
                <h2>Participation & Attendance Dashboard</h1>
            </div>
            <div class="header-actions">
                <span class="filter-pill"><?php echo $activeFilterCount; ?> active filter<?php echo $activeFilterCount === 1 ? '' : 's'; ?></span>
                <a class="reset-link" href="attendance_dashboard.php">Reset</a>
            </div>
        </header>

        <div class="dashboard-filter-container">
            <form method="GET" class="dashboard-filter-form" id="dashboardFilterForm">
                <div class="filter-group">
                    <label for="club_name">Club Name</label>
                    <input
                        id="club_name"
                        type="search"
                        name="club_name"
                        list="club-list"
                        placeholder="Search club..."
                        value="<?php echo h($club_name); ?>"
                    >
                    <datalist id="club-list">
                        <?php foreach ($clubs as $club) : ?>
                            <option value="<?php echo h($club['club_name']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="filter-group">
                    <label for="month_year">Month (Year)</label>
                    <select id="month_year" name="month_year">
                        <option value="">All Months</option>
                        <?php foreach ($months as $month) : ?>
                            <option value="<?php echo h($month['month_value']); ?>" <?php echo $month_year === $month['month_value'] ? 'selected' : ''; ?>>
                                <?php echo h($month['month_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="event_name">Event Name</label>
                    <input
                        id="event_name"
                        type="search"
                        name="event_name"
                        placeholder="Search event..."
                        value="<?php echo h($event_name); ?>"
                    >
                </div>
                <div class="filter-button-group">
                    <button type="submit" class="search-btn">Filter Dashboard</button>
                </div>
            </form>
        </div>

        <section class="metrics-container" aria-label="Dashboard summary">
            <div class="metric-card accent-blue">
                <div class="metric-info">
                    <span class="metric-label">Total Events</span>
                    <h3 class="metric-value"><?php echo number_format($total_events); ?></h3>
                </div>
            </div>
            <div class="metric-card accent-green">
                <div class="metric-info">
                    <span class="metric-label">Registered Participants</span>
                    <h3 class="metric-value"><?php echo number_format($total_participants); ?></h3>
                </div>
            </div>
            <div class="metric-card accent-red">
                <div class="metric-info">
                    <span class="metric-label">Average Attendance Rate</span>
                    <h3 class="metric-value"><?php echo number_format($attendance_rate, 2); ?>%</h3>
                </div>
            </div>
            <div class="metric-card accent-gold">
                <div class="metric-info">
                    <span class="metric-label">Total Points Distributed</span>
                    <h3 class="metric-value"><?php echo number_format($total_points); ?></h3>
                </div>
            </div>
        </section>

        <section class="chart-container">
            <div class="dashboard-panel chart-panel wide-panel">
                <div class="panel-header">
                    <div>
                        <span class="metric-label"><?php echo $club_name === "" ? "Club Attendance Rate" : "Events by " . h($club_name); ?></span>
                        <p><?php echo $club_name === "" ? "Click a club bar to filter the dashboard." : "Registered participants by event."; ?></p>
                    </div>
                </div>
                <div class="bar-div">
                    <?php if ($club_name === "") : ?>
                        <canvas id="barChart"></canvas>
                    <?php else : ?>
                        <canvas id="eventChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-panel chart-panel status-panel">
                <div class="panel-header">
                    <div>
                        <span class="metric-label">Attendance Status</span>
                    </div>
                </div>
                <div class="donut-wrap">
                    <canvas id="donutChart"></canvas>
                </div>
            </div>
        </section>

        <section class="bottom-container">
            <div class="dashboard-panel chart-panel wide-panel">
                <div class="panel-header">
                    <div>
                        <span class="metric-label">Monthly Participation Trends</span>
                    </div>
                </div>
                <div class="bar-div">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>

            <div class="dashboard-panel list-panel">
                <div class="panel-header">
                    <div>
                        <span class="metric-label">Top Active Students</span>
                    </div>
                </div>
                <div class="stack-list">
                    <?php if ($topStudents) : ?>
                        <?php foreach ($topStudents as $index => $row) : ?>
                            <div class="rank-row">
                                <span class="rank-number"><?php echo $index + 1; ?></span>
                                <div>
                                    <h3><?php echo h($row['name']); ?></h3>
                                    <p><?php echo h($row['course']); ?></p>
                                </div>
                                <strong><?php echo number_format((int) $row['total_points']); ?> pts</strong>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="empty-state">No student points found for the current filters.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-panel list-panel">
                <div class="panel-header">
                    <div>
                        <span class="metric-label"><?php echo $club_name === "" ? "Top Active Clubs" : "Club Committee Members"; ?></span>
                    </div>
                </div>
                <div class="stack-list">
                    <?php if ($club_name === "") : ?>
                        <?php if ($topClubs) : ?>
                            <?php foreach ($topClubs as $index => $club) : ?>
                                <button type="button" class="rank-row clickable-row" data-club="<?php echo h($club['club_name']); ?>">
                                    <span class="rank-number"><?php echo $index + 1; ?></span>
                                    <div>
                                        <h3><?php echo h($club['club_name']); ?></h3>
                                        <p><?php echo number_format((int) $club['total_events']); ?> events, <?php echo number_format((int) $club['total_participants']); ?> participants</p>
                                    </div>
                                    <strong><?php echo number_format((int) $club['total_points']); ?> pts</strong>
                                </button>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="empty-state">No club activity found.</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php if ($committeeRows) : ?>
                            <?php foreach ($committeeRows as $committee) : ?>
                                <div class="rank-row">
                                    <span class="rank-number">-</span>
                                    <div>
                                        <h3><?php echo h($committee['name']); ?></h3>
                                        <p><?php echo h($committee['position']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="empty-state">No committee members found for this club.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <script>
        const barChartLabels = <?php echo json_encode($barLabels); ?>;
        const barChartData = <?php echo json_encode($barData); ?>;
        const donutChartLabels = <?php echo json_encode($donutLabels); ?>;
        const donutChartData = <?php echo json_encode($donutData); ?>;
        const eventChartLabels = <?php echo json_encode($eventLabels); ?>;
        const eventChartData = <?php echo json_encode($eventData); ?>;
        const lineChartLabels = <?php echo json_encode($lineLabels); ?>;
        const lineChartData = <?php echo json_encode($lineData); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> //Chart.js API
    <script src="charts.js"></script>
</body>
</html>
