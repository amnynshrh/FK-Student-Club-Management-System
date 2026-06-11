<?php
// 1. SESSION INITIALIZATION
session_start();

// 2. CHECK LOGIN & ROLE: Standardized to match login.php session keys
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 3. Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 4. Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//total committees, students, admins from user table
$sql_committees = "SELECT COUNT(*) as total FROM user WHERE role = 'Committee'";
$result_committees = $conn->query($sql_committees);
$total_committees = ($result_committees) ? $result_committees->fetch_assoc()['total'] : 0;

$sql_students = "SELECT COUNT(*) as total FROM user WHERE role = 'Student'";
$result_students = $conn->query($sql_students);
$total_students = ($result_students) ? $result_students->fetch_assoc()['total'] : 0;

$sql_admins = "SELECT COUNT(*) as total FROM user WHERE role = 'Admin'";
$result_admins = $conn->query($sql_admins);
$total_admins = ($result_admins) ? $result_admins->fetch_assoc()['total'] : 0;

//total clubs from user table
$sql_clubs = "SELECT COUNT(*) as total FROM club WHERE club_status = 'active'";
$result_clubs = $conn->query($sql_clubs);
$total_clubs = ($result_clubs) ? $result_clubs->fetch_assoc()['total'] : 0;

// Pending Registrations (logic based on user table status)
$sql_pending = "SELECT COUNT(*) as total FROM student WHERE status = 'Pending'";
$result_pending = $conn->query($sql_pending);
$total_pending = ($result_pending) ? $result_pending->fetch_assoc()['total'] : 0;

// 6. Fetch Recent Registrations (Joining user and student for Names)
$sql_recent = "SELECT u.username, u.email, u.role, s.name 
               FROM user u 
               LEFT JOIN student s ON u.user_id = s.user_id 
               WHERE u.role != 'Admin'
               ORDER BY u.user_id DESC LIMIT 5";
$recent_result = $conn->query($sql_recent);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FK Student Club</title>
    <link rel="stylesheet" href="admin.css">
    <script>
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) { window.location.reload(); }
        });
    </script>
</head>
<body>

 <?php include('adminHeader.php') ?>

<div class="page-container">
    <header class="page-header">
        <h1>Administrator Dashboard</h1>
        <p>System overview and statistics</p>
    </header>

    <div class="metrics-container">
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">TOTAL STUDENTS</span>
                <h3 class="metric-value"><?php echo number_format($total_students + $total_committees); ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">👤</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">TOTAL CLUBS</span>
                <h3 class="metric-value"><?php echo $total_clubs; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">🏘️</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">INACTIVE USERS</span>
                <h3 class="metric-value" style="color: #dc3545;"><?php echo $total_pending; ?></h3>
            </div>
            <div class="metric-icon-box red-icon">⚠️</div>
        </div>
    </div>

    <div class="dashboard-grid">
       
        <div class="chart-card">
            <h6>Registered User by Role</h6>
            <div style="height: 250px; display: flex; justify-content: center;">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-top-bar">
            <h3>Recent User Registrations Details</h3>
        </div>

        <div class="table-wrapper">
            <table class="member-table">
                <thead>
                    <tr>
                        <th>Username/ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($recent_result && $recent_result->num_rows > 0) {
                        while($row = $recent_result->fetch_assoc()) {
                            $role = strtolower($row['role']);
                            $badge = ($role == 'student') ? 'student-badge' : 'committee-badge';
                            
                            echo "<tr>";
                            echo "<td><strong>" . htmlspecialchars($row['username']) . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td><span class='badge $badge'>" . htmlspecialchars($row['role']) . "</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center'>No recent registrations found.</td></tr>";
                    }
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>  
<script>

   new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: [ 'Committee','Student', 'Admin'],
            datasets: [{ 
                data: [
                    
                    <?php echo $total_committees; ?>, 
                    <?php echo $total_students; ?>, 
                    <?php echo $total_admins; ?>, 
                ], 
                backgroundColor: ['#ffcc00', '#004a99', '#ff061a'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>
</body>
</html>