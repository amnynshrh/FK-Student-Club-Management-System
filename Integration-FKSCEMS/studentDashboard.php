<?php
session_start();

include ('session.php');

// 1. SECURITY: Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 3. DATABASE CONNECTION
// Ensure the database name matches what you used in register.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// Get logged-in user ID from session
$user_id = $_SESSION['SESS_USER_ID'];

/* =========================================
   FETCH STUDENT + USER DATA
========================================= */

$sql_profile = "

SELECT 

    u.username,
    u.email,

    s.name,
    s.matric_number,
    s.course,
    s.profile_photo

FROM user u

INNER JOIN student s
ON u.user_id = s.user_id

WHERE u.user_id = ?

";

$stmt = $conn->prepare($sql_profile);

if ($stmt) {

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $user_data = $result->fetch_assoc();

}
else {

    die("Profile Query Error: " . $conn->error);
}

/* =========================================
   FETCH CLUB COUNT
========================================= */

$club_count = 0;

$sql_club_count = "

SELECT COUNT(*) AS total

FROM membership m

INNER JOIN student s
ON m.matric_number = s.matric_number

WHERE s.user_id = ?

";

$stmt_club = $conn->prepare($sql_club_count);

if ($stmt_club) {

    $stmt_club->bind_param("i", $user_id);

    $stmt_club->execute();

    $result_club = $stmt_club->get_result();

    $club_count = $result_club->fetch_assoc()['total'];

}
else {

    die("Club Count Query Error: " . $conn->error);
}

$total_points = 0;

$sql_points = "

SELECT 

    COALESCE(SUM(a.point_awarded), 0) AS total_points

FROM attendance a

INNER JOIN eventregistration er
ON a.registration_id = er.registration_id

INNER JOIN student s
ON er.matric_number = s.matric_number

WHERE s.user_id = ?

";

$stmt_points = $conn->prepare($sql_points);

if ($stmt_points) {

    $stmt_points->bind_param("i", $user_id);

    $stmt_points->execute();

    $result_points = $stmt_points->get_result();

    $total_points = $result_points->fetch_assoc()['total_points'];

}
else {

    die("Points Query Error: " . $conn->error);
}

/* =========================================
   EVENT ATTENDANCE RATE
========================================= */

$attendance_rate = 0;

$sql_attendance_rate = "

SELECT 

    ROUND(

        (
            SUM(
                CASE
                    WHEN a.attendance_status = 'Present'
                    THEN 1
                    ELSE 0
                END
            )

            /

            NULLIF(COUNT(er.registration_id), 0)

        ) * 100,

        0

    ) AS attendance_rate

FROM eventregistration er

LEFT JOIN attendance a
ON er.registration_id = a.registration_id

INNER JOIN student s
ON er.matric_number = s.matric_number

WHERE s.user_id = ?

";

$stmt_attendance = $conn->prepare($sql_attendance_rate);

if ($stmt_attendance) {

    $stmt_attendance->bind_param("i", $user_id);

    $stmt_attendance->execute();

    $result_attendance = $stmt_attendance->get_result();

    $attendance_rate =
        $result_attendance->fetch_assoc()['attendance_rate'];

}
else {

    die("Attendance Rate Query Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - FK Student Club</title>
    <link rel="stylesheet" href="studentDashboard.css">
    
    <script>
        // Force reload if user uses the 'Back' button to ensure session check runs
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) { 
                window.location.reload(); 
            }
        });
    </script>
</head>
<body>

<?php include ('studentHeader.php') ?>

<div class="page-container">
    <header class="page-header">
        <h1>Hello, <?php echo htmlspecialchars(explode(' ', trim($user_data['name'] ?? 'Student'))[0]); ?>!</h1>
        <p>Your personal student activity hub.</p>
        <small>Matric: <?php echo htmlspecialchars($user_data['matric_number'] ?? 'N/A'); ?> | Course: <?php echo htmlspecialchars($user_data['course'] ?? 'N/A'); ?></small>
    </header>

    <div class="metrics-container">
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Joined Clubs</span>
                <h3 class="metric-value"><?php echo $club_count; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">🏘️</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">My Merit Points</span>
                <h3 class="metric-value"><?php echo $total_points; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">⭐</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Event Attendance</span>
                <h3 class="metric-value"><?php echo $attendance_rate; ?>%</h3>
            </div>
            <div class="metric-icon-box blue-icon">📉</div>
        </div>
    </div>

    <div class="user-grid">
        <div class="table-container">
            <h3 class="card-title">My Registered Clubs</h3>
            <table class="member-table">
                <thead>
                    <tr>
                        <th>Club Name</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $user_id = $_SESSION['SESS_USER_ID'];
                $sql_clubs = "
                SELECT 
                    c.club_id,
                    c.club_name,
                    m.membership_status,
                    COALESCE(cm.position, 'Member') AS position
                FROM student s
                INNER JOIN membership m
                ON s.matric_number = m.matric_number
                INNER JOIN club c
                ON m.club_id = c.club_id
                LEFT JOIN committee cm
                ON m.membership_id = cm.membership_id
                AND c.club_id = cm.club_id
                WHERE s.user_id = ?
                ORDER BY c.club_name ASC
                ";

                $stmt_clubs = $conn->prepare($sql_clubs);
                $stmt_clubs->bind_param("i", $user_id);
                $stmt_clubs->execute();
                $result_clubs = $stmt_clubs->get_result();
                while($club = $result_clubs->fetch_assoc()) :
                ?>
                <tr>
                    <td>
                        <strong>
                            <?php echo htmlspecialchars($club['club_name']); ?>
                        </strong>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($club['position']); ?>
                    </td>
                    <td>
                        <?php if ($club['membership_status'] == 'Active') : ?>
                            <span class="badge student-badge">
                                Active
                            </span>
                        <?php else : ?>
                            <span class="badge admin-red-badge">
                                Inactive
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a 
                            href="#"
                            //href="viewClub.php?club_id=<?php echo $club['club_id']; ?>"
                            class="action-btn edit"
                        >
                            View Club
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="event-sidebar">
            <div class="progress-container">
                <h3 class="card-title">My Attendance Goal</h3>
                <span class="metric-label">8 out of 10 events</span>
                <div class="progress-bar-bg"><div class="progress-fill" style="width: 80%;"></div></div>
                
                <h3 class="card-title" style="margin-top:20px;">Available Events</h3>
                <div class="event-list">
                    <div class="event-item">
                        <div class="event-date"><span>OCT</span><strong>24</strong></div>
                        <div class="event-info">
                            <div style="font-weight:bold;">Hackathon 2026</div>
                            <small>8:00 AM - Library</small>
                        </div>
                        <button class="action-btn edit">Register</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>