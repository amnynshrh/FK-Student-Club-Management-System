<?php
session_start();

include ('session.php');

// 2. CACHE CONTROL: Prevent "Back" button from showing sensitive data after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 3. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; // Ensure this matches your registration/login DB name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$user_id = $_SESSION['SESS_USER_ID']; 

// 4. FETCH COMMITTEE DATA (Joining user and student tables)
// We need the 'name' from the student table
$user_query = "SELECT u.*, s.name, s.matric_number 
               FROM user u 
               JOIN student s ON u.user_id = s.user_id 
               WHERE u.user_id = ?";

$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

// 5. FETCH MEMBER STATISTICS
$member_count_query = "SELECT COUNT(*) as total FROM user WHERE role = 'Student'"; 
$member_count_result = $conn->query($member_count_query);
$member_count = ($member_count_result) ? $member_count_result->fetch_assoc()['total'] : 0;

$committee_id = $_SESSION['SESS_COMMITTEE_ID'];
$total_events = 0;
$sql_total_events = "
SELECT 
COUNT(*) AS total_events
FROM event
WHERE committee_id = ?
";

$stmt_events = $conn->prepare($sql_total_events);
if ($stmt_events) {
    $stmt_events->bind_param("i", $committee_id);
    $stmt_events->execute();
    $result_events = $stmt_events->get_result();
    $total_events = $result_events->fetch_assoc()['total_events'];
}
else {
    die("Event Count Query Error: " . $conn->error);
}

$committee_id = $_SESSION['SESS_COMMITTEE_ID'];
$attendance_rate = 0;
$sql_attendance_rate = "
SELECT 
ROUND((SUM(CASE
WHEN a.attendance_status = 'Present'
THEN 1
ELSE 0
END)/NULLIF(COUNT(er.registration_id), 0)) * 100, 0) AS attendance_rate
FROM event e
INNER JOIN eventregistration er
ON e.event_id = er.event_id
LEFT JOIN attendance a
ON er.registration_id = a.registration_id
WHERE e.committee_id = ?
";

$stmt_attendance = $conn->prepare($sql_attendance_rate);
if ($stmt_attendance) {
    $stmt_attendance->bind_param("i", $committee_id);
    $stmt_attendance->execute();
    $result_attendance = $stmt_attendance->get_result();
    $attendance_rate = $result_attendance->fetch_assoc()['attendance_rate'];
}
else {
    die("Attendance Query Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Committee Dashboard - FK Student Club</title>
    <link rel="stylesheet" href="committeeDashboard.css">
    
    <script>
        // Force refresh if user clicks back button
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) { 
                window.location.reload(); 
            }
        });
    </script>
</head>
<body>
<?php include('committeeHeader.php') ?>

<div class="page-container">
    <header class="page-header">
        <h1>Committee Hub: <?php echo htmlspecialchars(explode(' ', trim($user_data['name'] ?? 'Committee'))[0]); ?></h1>
        <p>Manage your club members and organize upcoming events.</p>
    </header>

    <div class="metrics-container">
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Total Club Members</span>
                <h3 class="metric-value"><?php echo $member_count; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">👥</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Events Organized</span>
                <h3 class="metric-value"><?php echo $total_events; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">📅</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Avg. Attendance</span>
                <h3 class="metric-value"><?php echo $attendance_rate; ?>%</h3>
            </div>
            <div class="metric-icon-box blue-icon">📊</div>
        </div>
    </div>

    <div class="user-grid">
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 class="card-title" style="margin: 0;">Recent Event Participants</h3>
                <button class="action-btn edit" onclick="window.location.href='manageEvents.php'">+ Create Event</button>
            </div>
            <table class="member-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Ali Bin Abu</strong></td>
                        <td>Hackathon 2026</td>
                        <td><span class="badge committee-badge" style="background:#28a745; color: white; padding: 5px 10px; border-radius: 4px;">Present</span></td>
                        <td><button class="action-btn edit">Details</button></td>
                    </tr>
                    <tr>
                        <td><strong>Siti Aminah</strong></td>
                        <td>Hackathon 2026</td>
                        <td><span class="badge student-badge" style="background:#ffc107; color: black; padding: 5px 10px; border-radius: 4px;">Late</span></td>
                        <td><button class="action-btn edit">Details</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="event-sidebar">
            <div class="progress-container">
                <h3 class="card-title">Event Capacity</h3>
                <span class="metric-label">Hackathon: 45 / 50 registered</span>
                <div class="progress-bar-bg" style="background: #eee; border-radius: 10px; margin: 10px 0; height: 12px;">
                    <div class="progress-fill" style="width: 90%; background: #004a99; height: 100%; border-radius: 10px;"></div>
                </div>
                
                <h3 class="card-title" style="margin-top:20px;">Quick Actions</h3>
                <div class="event-list">
                    <button class="action-btn edit" style="width: 100%; margin-bottom: 10px; padding: 12px; cursor: pointer;" onclick="window.location.href='attendanceScanner.php'">📷 Open QR Scanner</button>
                    <button class="action-btn edit" style="width: 100%; margin-bottom: 10px; padding: 12px; background: #6c757d; border:none; color:white; border-radius: 5px; cursor: pointer;">📝 Generate Report</button>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>