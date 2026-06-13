<?php        

session_start();

include ('session.php');

// 2. CHECK LOGIN & ROLE: Standardized to match login.php session variables
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Committee') {
    header("Location: login.php");
    exit();
}

// 3. CACHE CONTROL: Prevent "Back" button from showing sensitive data after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 4. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// Map user tracking ID across from standard session states safely
$user_id = $_SESSION['user_id']; 

// 5. FETCH COMMITTEE DATA (Joining user and student tables)
$user_query = "SELECT u.*, s.name, s.matric_number 
               FROM user u 
               JOIN student s ON u.user_id = s.user_id 
               WHERE u.user_id = ?";

$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

$matric_number = $user_data['matric_number'] ?? '';
$committee_club = null;
$club_id = 0;

$club_stmt = $conn->prepare("
    SELECT c.club_id, c.club_name, cm.committee_id
    FROM committee cm
    INNER JOIN membership m ON cm.membership_id = m.membership_id
    INNER JOIN club c ON cm.club_id = c.club_id
    WHERE m.matric_number = ?
    LIMIT 1
");
$club_stmt->bind_param("s", $matric_number);
$club_stmt->execute();
$committee_club = $club_stmt->get_result()->fetch_assoc();
$club_id = (int) ($committee_club['club_id'] ?? 0);

$member_count = 0;
$events_organized = 0;
$avg_attendance = 0;
$recent_participants = [];
$capacity_events = [];

if ($club_id > 0) {
    $member_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM membership WHERE club_id = ? AND membership_status = 'approved'");
    $member_stmt->bind_param("i", $club_id);
    $member_stmt->execute();
    $member_count = (int) ($member_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $event_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM event WHERE club_id = ?");
    $event_count_stmt->bind_param("i", $club_id);
    $event_count_stmt->execute();
    $events_organized = (int) ($event_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $attendance_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT er.registration_id) AS total_registered,
            SUM(CASE WHEN LOWER(a.attendance_status) IN ('present', 'late') THEN 1 ELSE 0 END) AS total_attended
        FROM event e
        LEFT JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
        LEFT JOIN attendance a ON er.registration_id = a.registration_id
        WHERE e.club_id = ?
    ");
    $attendance_stmt->bind_param("i", $club_id);
    $attendance_stmt->execute();
    $attendance_data = $attendance_stmt->get_result()->fetch_assoc();
    $registered_total = (int) ($attendance_data['total_registered'] ?? 0);
    $attended_total = (int) ($attendance_data['total_attended'] ?? 0);
    $avg_attendance = $registered_total > 0 ? round(($attended_total / $registered_total) * 100) : 0;

    $recent_stmt = $conn->prepare("
        SELECT
            s.name,
            s.matric_number,
            e.event_id,
            e.event_title,
            COALESCE(a.attendance_status, 'not marked') AS attendance_status
        FROM event e
        INNER JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
        INNER JOIN student s ON er.matric_number = s.matric_number
        LEFT JOIN attendance a ON er.registration_id = a.registration_id
        WHERE e.club_id = ?
        ORDER BY er.registration_date DESC
        LIMIT 6
    ");
    $recent_stmt->bind_param("i", $club_id);
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    while ($row = $recent_result->fetch_assoc()) {
        $recent_participants[] = $row;
    }

    $capacity_stmt = $conn->prepare("
        SELECT
            e.event_id,
            e.event_title,
            e.max_participant,
            COUNT(er.registration_id) AS registered_count
        FROM event e
        LEFT JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
        WHERE e.club_id = ?
        GROUP BY e.event_id, e.event_title, e.max_participant
        ORDER BY e.event_date DESC, e.event_time DESC
        LIMIT 4
    ");
    $capacity_stmt->bind_param("i", $club_id);
    $capacity_stmt->execute();
    $capacity_result = $capacity_stmt->get_result();
    while ($row = $capacity_result->fetch_assoc()) {
        $capacity_events[] = $row;
    }
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
        <p>Manage <?php echo htmlspecialchars($committee_club['club_name'] ?? 'your club'); ?> members and organize upcoming events.</p>
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
                <h3 class="metric-value"><?php echo $events_organized; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">📅</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Avg. Attendance</span>
                <h3 class="metric-value"><?php echo $avg_attendance; ?>%</h3>
            </div>
            <div class="metric-icon-box blue-icon">📊</div>
        </div>
    </div>

    <div class="user-grid">
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 class="card-title" style="margin: 0;">Recent Event Participants</h3>
                <button class="action-btn edit" onclick="window.location.href='add_event.php'">+ Create Event</button>
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
                    <?php if (!empty($recent_participants)): ?>
                        <?php foreach ($recent_participants as $participant): ?>
                            <?php
                            $status = strtolower((string) $participant['attendance_status']);
                            $badgeStyle = $status === 'present'
                                ? 'background:#28a745; color:white;'
                                : ($status === 'late'
                                    ? 'background:#ffc107; color:#222;'
                                    : ($status === 'absent' ? 'background:#dc3545; color:white;' : 'background:#6c757d; color:white;'));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($participant['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($participant['event_title']); ?></td>
                                <td><span class="badge" style="<?php echo $badgeStyle; ?> padding: 5px 10px; border-radius: 4px;"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                                <td><button class="action-btn edit" onclick="window.location.href='event_attendance.php?event_id=<?php echo (int) $participant['event_id']; ?>'">Details</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#6c757d;">No recent event participants found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="event-sidebar">
            <div class="progress-container">
                <h3 class="card-title">Event Capacity</h3>
                <?php if (!empty($capacity_events)): ?>
                    <?php foreach ($capacity_events as $event): ?>
                        <?php
                        $max = max(1, (int) $event['max_participant']);
                        $registered = (int) $event['registered_count'];
                        $percent = min(100, round(($registered / $max) * 100));
                        ?>
                        <span class="metric-label"><?php echo htmlspecialchars($event['event_title']); ?>: <?php echo $registered; ?> / <?php echo $max; ?> registered</span>
                        <div class="progress-bar-bg" style="background: #eee; border-radius: 10px; margin: 10px 0 16px; height: 12px;">
                            <div class="progress-fill" style="width: <?php echo $percent; ?>%; background: #004a99; height: 100%; border-radius: 10px;"></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#6c757d; font-size:0.9rem;">No club events found.</p>
                <?php endif; ?>
                
                <h3 class="card-title" style="margin-top:20px;">Quick Actions</h3>
                <div class="event-list">
                    <button class="action-btn edit" style="width: 100%; margin-bottom: 10px; padding: 12px; cursor: pointer;" onclick="window.location.href='manage_attendance.php'">📷 Open QR Scanner</button>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
