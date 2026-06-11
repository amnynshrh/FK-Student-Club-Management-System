<?php        

session_start();

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

// 6. FETCH MEMBER STATISTICS
$member_count_query = "SELECT COUNT(*) as total FROM user WHERE role = 'Student'"; 
$member_count_result = $conn->query($member_count_query);
$member_count = ($member_count_result) ? $member_count_result->fetch_assoc()['total'] : 0;
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
                <h3 class="metric-value">12</h3>
            </div>
            <div class="metric-icon-box blue-icon">📅</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Avg. Attendance</span>
                <h3 class="metric-value">92%</h3>
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