<?php
session_start();

// 1. SESSION GUARD: Matches the keys from authenticate.php
if (!isset($_SESSION['SESS_ROLE']) || $_SESSION['SESS_ROLE'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 2. Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 3. Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; // Updated to match your actual DB name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 4. Fetch Statistics
// Total Students from user table
$sql_students = "SELECT COUNT(*) as total FROM user WHERE role = 'Student'";
$result_students = $conn->query($sql_students);
$total_students = ($result_students) ? $result_students->fetch_assoc()['total'] : 0;

$total_clubs = 309; // Hardcoded for now

// Pending Registrations (logic based on user table status)
$sql_pending = "SELECT COUNT(*) as total FROM student WHERE status = 'Pending'";
$result_pending = $conn->query($sql_pending);
$total_pending = ($result_pending) ? $result_pending->fetch_assoc()['total'] : 0;

// 5. Fetch Recent Registrations (Joining user and student for Names)
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
                <h3 class="metric-value"><?php echo number_format($total_students); ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">👤</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">TOTAL ACTIVE CLUBS</span>
                <h3 class="metric-value"><?php echo $total_clubs; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">🏘️</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">PENDING REGISTRATIONS</span>
                <h3 class="metric-value" style="color: #dc3545;"><?php echo $total_pending; ?></h3>
            </div>
            <div class="metric-icon-box red-icon">⚠️</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="chart-card">
            <h6>Registration Trends (6 Months)</h6>
            <canvas id="lineChart"></canvas>
        </div>
        <div class="chart-card">
            <h6>User Participation by Role</h6>
            <div style="height: 250px; display: flex; justify-content: center;">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-top-bar">
            <h3>Recent User Registrations</h3>
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
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{ 
                label: 'Registrations', 
                data: [5, 20, 14, 26, 27, 14], 
                borderColor: '#004a99', 
                backgroundColor: 'rgba(0, 74, 153, 0.1)',
                fill: true,
                tension: 0.3 
            }]
        }
    });

    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Committee', 'Student', 'Admin'],
            datasets: [{ 
                data: [<?php echo $total_pending; ?>, <?php echo $total_students; ?>, 1], 
                backgroundColor: ['#004a99', '#ffcc00', '#ff061a'],
                borderWidth: 0
            }]
        }
    });
</script>
</body>
</html>