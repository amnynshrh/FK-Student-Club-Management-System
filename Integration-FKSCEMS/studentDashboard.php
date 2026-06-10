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
$user_id = $_SESSION['user_id']; 

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
    $sql_clubs = "SELECT COUNT(*) as total FROM membership WHERE matric_number = ? AND membership_status = 'Active'";
    $stmt_c = $conn->prepare($sql_clubs);
    if ($stmt_c) {
        $stmt_c->bind_param("s", $matric);
        $stmt_c->execute();
        $club_count = $stmt_c->get_result()->fetch_assoc()['total'];
    }
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
                <h3 class="metric-value">120</h3>
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
                    <tr>
                        <td><strong>FK Coding Club</strong></td>
                        <td>Member</td>
                        <td><span class="badge student-badge">Active</span></td>
                        <td><button class="action-btn edit">View Club</button></td>
                    </tr>
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