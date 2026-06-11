<?php       

session_start();

// SECURITY: Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. CHECK LOGIN & ROLE: Aligned to standard login.php session variables
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit();
}

// 3. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// Map user tracking ID across from session states safely
$user_id = $_SESSION['SESS_USER_ID']; 

// 4. FETCH STUDENT & USER DATA (Joining tables based on your schema)
$sql_profile = "SELECT u.username, u.email, s.name, s.matric_number, s.course, s.profile_photo 
                FROM user u 
                JOIN student s ON u.user_id = s.user_id 
                WHERE u.user_id = ?";

$stmt = $conn->prepare($sql_profile);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
} else {
    die("Profile Query Error: " . $conn->error);
}

// 5. FETCH CLUB COUNT: Pointed to your real schema table 'membership'
$club_count = 0;
if ($user_data) {
    $matric = $user_data['matric_number'];
    $sql_clubs = "SELECT COUNT(*) as total 
                  FROM membership
                  WHERE matric_number = ? 
                  AND membership_status = 'Active'";
    $stmt_c = $conn->prepare($sql_clubs);
    if ($stmt_c) {
        $stmt_c->bind_param("s", $matric);
        $stmt_c->execute();
        $club_count = $stmt_c->get_result()->fetch_assoc()['total'];
    }
}

//total points calculation for the student
$user_id = $_SESSION['SESS_USER_ID']; 
$sql_points = "SELECT SUM(a.point_awarded) as total_points 
               FROM student s
               JOIN eventregistration er ON s.matric_number = er.matric_number
               JOIN attendance a ON er.registration_id = a.registration_id
               WHERE s.user_id = ?";

$stmt_points = $conn->prepare($sql_points);
$stmt_points->bind_param("i", $user_id);
$stmt_points->execute();
$result_points = $stmt_points->get_result();
$total_student_points = 0;
if ($result_points && $row = $result_points->fetch_assoc()) {
    // If they haven't attended events yet, SUM() returns NULL, so we use ?? 0
    $total_student_points = $row['total_points'] ?? 0; 
}

// Fetch club memberships with position and status for the logged-in student
$user_id = $_SESSION['SESS_USER_ID'];
$sql_clubs = "SELECT 
                c.club_name,
                COALESCE(com.position, 'Member') AS club_position,
                m.membership_status
              FROM student s
              JOIN membership m ON s.matric_number = m.matric_number
              JOIN club c ON m.club_id = c.club_id
              LEFT JOIN committee com ON m.membership_id = com.membership_id
              WHERE s.user_id = ?";

$stmt_clubs = $conn->prepare($sql_clubs);
$stmt_clubs->bind_param("i", $user_id);
$stmt_clubs->execute();
$result_clubs = $stmt_clubs->get_result();

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

 <?php include('studentHeader.php') ?>

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
                <h3 class="metric-value"><?php echo $total_student_points; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">⭐</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Event Attendance</span>
                <h3 class="metric-value">85%</h3>
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
                    <?php if ($result_clubs && $result_clubs->num_rows > 0): ?>
                    <?php while ($club = $result_clubs->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($club['club_name']); ?></strong></td>
                             <td><?php echo htmlspecialchars($club['club_position']); ?></td>
                             <td>
                                <span class="badge student-badge <?php echo htmlspecialchars(strtolower($club['membership_status'])); ?>">
                                     <?php echo htmlspecialchars(ucfirst($club['membership_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn edit">View Club</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 15px; color: #666;">
                                You have not registered for any clubs yet.
                            </td>
                        </tr>
                    <?php endif; ?>
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