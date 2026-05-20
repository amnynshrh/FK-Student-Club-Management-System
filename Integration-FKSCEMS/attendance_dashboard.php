<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$club_name = $_GET['club_name'] ?? '';
$semester = $_GET['semester'] ?? '';
$event_name = $_GET['event_name'] ?? '';

$sql_clubs = "
SELECT club_name
FROM club
ORDER BY club_name ASC
";

$result_clubs = $conn->query($sql_clubs);

$where_conditions = [];
$params = [];
$types = "";

if (!empty($club_name)) {
    $where_conditions[] = "c.club_name = ?";
    $params[] = $club_name;
    $types .= "s";
}

if (!empty($event_name)) {
    $where_conditions[] = "e.event_title LIKE ?";
    $params[] = "%$event_name%";
    $types .= "s";
}

$where_sql = "";

if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

$sql_total_events = "
SELECT COUNT(DISTINCT e.event_id) AS total_events
FROM event e
INNER JOIN club c
ON e.club_id = c.club_id
$where_sql
";

$stmt_total_events = $conn->prepare($sql_total_events);
if (!empty($params)) {
    $stmt_total_events->bind_param($types, ...$params);
}
$stmt_total_events->execute();
$result_total_events = $stmt_total_events->get_result();
$total_events = $result_total_events->fetch_assoc()['total_events'] ?? 0;

$sql_total_participants = "
SELECT 
COUNT(DISTINCT m.membership_id) AS total_participants
FROM membership m
INNER JOIN club c
ON m.club_id = c.club_id
LEFT JOIN event e
ON c.club_id = e.club_id
$where_sql
";

$stmt_total_participants = $conn->prepare($sql_total_participants);
if (!empty($params)) {
    $stmt_total_participants->bind_param($types, ...$params);
}
$stmt_total_participants->execute();
$result_total_participants = $stmt_total_participants->get_result();
$total_participants = $result_total_participants->fetch_assoc()['total_participants'] ?? 0;

$sql_avg_attendance = "
SELECT ROUND((COUNT(a.attendance_id)/NULLIF(COUNT(er.registration_id), 0)) * 100, 2)
AS attendance_rate
FROM attendance a
INNER JOIN eventregistration er
ON a.registration_id = er.registration_id
INNER JOIN event e
ON er.event_id = e.event_id
INNER JOIN club c
ON e.club_id = c.club_id
$where_sql
";

$stmt_avg_attendance = $conn->prepare($sql_avg_attendance);
if (!empty($params)) {
    $stmt_avg_attendance->bind_param($types, ...$params);
}
$stmt_avg_attendance->execute();
$result_avg_attendance = $stmt_avg_attendance->get_result();
$attendance_rate = $result_avg_attendance->fetch_assoc()['attendance_rate'] ?? 0;

$sql_total_points = "
SELECT SUM(a.point_awarded) AS total_points
FROM attendance a
INNER JOIN eventregistration er
ON a.registration_id = er.registration_id
INNER JOIN event e
ON er.event_id = e.event_id
INNER JOIN club c
ON e.club_id = c.club_id
$where_sql
";

$stmt_total_points = $conn->prepare($sql_total_points);
if (!empty($params)) {
    $stmt_total_points->bind_param($types, ...$params);
}
$stmt_total_points->execute();
$result_total_points = $stmt_total_points->get_result();
$total_points = $result_total_points->fetch_assoc()['total_points'] ?? 0;

if (empty($club_name)) {
    $sql_top_club = "
    SELECT c.club_name,
    COUNT(DISTINCT e.event_id) AS total_events,
    COUNT(DISTINCT er.registration_id) AS total_participants,
    COALESCE(SUM(a.point_awarded), 0) AS total_points
    FROM club c
    LEFT JOIN event e
    ON c.club_id = e.club_id
    LEFT JOIN eventregistration er
    ON e.event_id = er.event_id
    LEFT JOIN attendance a
    ON er.registration_id = a.registration_id
    GROUP BY c.club_id, c.club_name
    ORDER BY total_points DESC
    LIMIT 3
    ";

    $result_top_club = $conn->query($sql_top_club);
}
else {
    $sql_committee = "
    SELECT s.name, cm.position
    FROM committee cm
    INNER JOIN membership m
    ON cm.membership_id = m.membership_id
    INNER JOIN student s
    ON m.matric_number = s.matric_number
    INNER JOIN club c
    ON cm.club_id = c.club_id
    WHERE c.club_name = ?
    ORDER BY cm.position ASC
    ";

    $stmt_committee = $conn->prepare($sql_committee);
    $stmt_committee->bind_param("s", $club_name);
    $stmt_committee->execute();
    $result_committee = $stmt_committee->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="attendance_dashboard.css">
</head>
<body>
    <?php include ('adminHeader.php'); ?>
    <div class="page-container">
        <div class="dashboard-filter-container">
            <form method="GET" class="dashboard-filter-form">
                <div class="filter-group">
                    <label>Club Name</label>
                    <input
                        type="search"
                        name="club_name"
                        list="club-list"
                        placeholder="Search club..."
                        value="<?php echo htmlspecialchars($club_name); ?>"
                    >
                    <datalist id="club-list">
                        <?php while($club = $result_clubs->fetch_assoc()) : ?>
                            <option value="<?php echo htmlspecialchars($club['club_name']); ?>">
                        <?php endwhile; ?>
                    </datalist>
                </div>
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester">
                        <option value="">All Semesters</option>
                        <option value="Semester 1">Semester 1</option>
                        <option value="Semester 2">Semester 2</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Event Name</label>
                    <input
                        type="search"
                        name="event_name"
                        placeholder="Search event..."
                        value="<?php echo htmlspecialchars($event_name); ?>"
                    >
                </div>
                <div class="filter-button-group">
                    <button type="submit" class="search-btn">
                        Filter Dashboard
                    </button>
                </div>
            </form>
        </div>
        <header class="page-header">
            <h1>Participation & Attendance Dashboard</h1>
            <p>wait</p>
        </header>
    
        <div class="metrics-container">
            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-label">TOTAL EVENTS</span>
                    <h3 class="metric-value"><?php echo number_format($total_events); ?></h3>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-label">TOTAL PARTICIPANTS</span>
                    <h3 class="metric-value"><?php echo $total_participants; ?></h3>
                </div>
                <div class="metric-icon-box blue-icon">🏘️</div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-label">AVERAGE ATTENDANCE RATE</span>
                    <h3 class="metric-value" style="color: #dc3545;"><?php echo $attendance_rate; ?></h3>
                </div>
                <div class="metric-icon-box red-icon">⚠️</div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-label">TOTAL POINTS DISTRIBUTED</span>
                    <h3 class="metric-value" style="color: #dc3545;"><?php echo $total_points; ?></h3>
                </div>
                <div class="metric-icon-box red-icon">⚠️</div>
            </div>
        </div>
        <div class="chart-container">
            <?php
            $barLabels = [];
            $barData = [];

            $sql = "
            SELECT c.club_name,
            ROUND((
            SUM(CASE 
            WHEN a.attendance_status = 'Present'
            THEN 1
            ELSE 0
            END)/NULLIF(COUNT(er.registration_id), 0)) * 100, 2)
            AS attendance_rate
            FROM club c
            LEFT JOIN event e
            ON c.club_id = e.club_id
            LEFT JOIN eventregistration er
            ON e.event_id = er.event_id
            LEFT JOIN attendance a
            ON er.registration_id = a.registration_id
            GROUP BY c.club_id, c.club_name
            ORDER BY attendance_rate DESC
            ";

            $result = $conn->query($sql);
            while($row = $result->fetch_assoc()) {
                $barLabels[] = $row['club_name'];
                $barData[] = $row['attendance_rate'];
            }

            $eventLabels = [];
            $eventData = [];

            if (!empty($club_name)) {
                $sql_events = "
                SELECT e.event_title,
                COUNT(er.registration_id) AS total_participants
                FROM event e
                LEFT JOIN eventregistration er
                ON e.event_id = er.event_id
                INNER JOIN club c
                ON e.club_id = c.club_id
                WHERE c.club_name = ?
                GROUP BY e.event_id, e.event_title
                ORDER BY total_participants DESC
                ";

                $stmt_events = $conn->prepare($sql_events);
                $stmt_events->bind_param("s", $club_name);
                $stmt_events->execute();
                $result_events = $stmt_events->get_result();
                while($row = $result_events->fetch_assoc()) {
                    $eventLabels[] = $row['event_title'];
                    $eventData[] = $row['total_participants'];
                }
            }
            ?>
            <div class="bar-chart">
                <?php if (empty($club_name)) : ?>
                    <span class="metric-label">
                        CLUB ATTENDANCE RATE
                    </span>
                    <div class="bar-div">
                        <canvas id="barChart"></canvas>
                    </div>
                <?php else : ?>
                    <span class="metric-label">
                        EVENTS BY <?php echo htmlspecialchars(strtoupper($club_name)); ?>
                    </span>
                    <div class="bar-div">
                        <canvas id="eventChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $donutLabels = [];
            $donutData = [];
            $sql = "
            SELECT a.attendance_status,
            COUNT(*) AS total
            FROM attendance a
            INNER JOIN eventregistration er
            ON a.registration_id = er.registration_id
            INNER JOIN event e
            ON er.event_id = e.event_id
            INNER JOIN club c
            ON e.club_id = c.club_id
            $where_sql
            GROUP BY a.attendance_status
            ";

            $stmt_donut = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt_donut->bind_param($types, ...$params);
            }
            $stmt_donut->execute();
            $result = $stmt_donut->get_result();
            while($row = $result->fetch_assoc()) {
                $donutLabels[] = $row['attendance_status'];
                $donutData[] = $row['total'];
            }
            ?>
            <div class="donut-chart">
                <div class="metric-info">
                    <span class="metric-label">PARTICIPATION STATUS</span>
                    <canvas id="donutChart"></canvas>
                </div>
            </div>
        </div>
        <div class="bottom-container">
            <?php
            
            $lineLabels = [];
            $lineData = [];

            $sql = "SELECT name FROM student";
            $result = $conn->query($sql);

            while($row = $result->fetch_assoc()) {
                // $barLabels[] = $row['name'];
                $lineLabels[] = "akmal";
                $lineData[] = 10;
                // $row['attendance_rate'];
            }
            
            $sql_top_active = "SELECT s.matric_number, s.name, s.course,
            SUM(a.point_awarded) AS total_points
            FROM student s
            INNER JOIN eventregistration er
            ON s.matric_number = er.matric_number
            INNER JOIN attendance a
            ON er.registration_id = a.registration_id
            GROUP BY
            s.matric_number, s.name, s.course
            ORDER BY total_points DESC
            LIMIT 5;";

            $result_top_active = $conn->query($sql_top_active);

            ?>
            <div class="bar-chart">
                <span class="metric-label">ja RATE</span>
                <div class="bar-div">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
            <div class="metric-card">
                <span class="metric-label">
                    TOP ACTIVE STUDENT
                </span>
                <div class="top-students-container">
                <?php while($row = $result_top_active->fetch_assoc()) : ?>
                <div class="metric-card">
                    <div class="metric-info">
                        <h3 class="metric-value">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </h3>
                        <p class="metric-points">
                            <?php echo number_format($row['total_points']); ?> Points
                        </p>
                    </div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <?php if (empty($club_name)) : ?>
                        <span class="metric-label">
                            TOP ACTIVE CLUBS
                        </span>
                        <div class="top-students-container">
                            <?php while($club = $result_top_club->fetch_assoc()) : ?>
                                <div class="metric-card">
                                    <div class="metric-info">
                                        <h3 class="metric-value">
                                            <?php echo htmlspecialchars($club['club_name']); ?>
                                        </h3>
                                        <p class="metric-points">
                                            <?php echo $club['total_points']; ?> Points
                                        </p>
                                        <p class="metric-points">
                                            <?php echo $club['total_events']; ?> Events
                                        </p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                            <?php else : ?>
                                <span class="metric-label">
                                    CLUB COMMITTEE MEMBERS
                                </span>
                                <div class="top-students-container">
                                    <?php while($committee = $result_committee->fetch_assoc()) : ?>
                                        <div class="metric-card">
                                            <div class="metric-info">
                                                <h3 class="metric-value">
                                                    <?php echo htmlspecialchars($committee['name']); ?>
                                                </h3>
                                                <p class="metric-points">
                                                    <?php echo htmlspecialchars($committee['position']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="charts.js"></script>
</body>