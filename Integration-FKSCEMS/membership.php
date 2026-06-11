<?php
// 1. SESSION INITIALIZATION
session_start();

// 2. SESSION GUARD: Standardized to match login.php session keys
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 3. CACHE CONTROL
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

// Fetch Metrics using the new table structure
// Count Students
$student_query = "SELECT COUNT(*) as total FROM user WHERE role = 'Student'";
$count_students = $conn->query($student_query)->fetch_assoc()['total'];

// Count Committees
$committee_query = "SELECT COUNT(*) as total FROM user WHERE role = 'Committee'";
$count_committees = $conn->query($committee_query)->fetch_assoc()['total'];

$total_combined = $count_students + $count_committees;

// Count Inactive Users (Looking at student table status)
$inactive_query = "SELECT COUNT(*) as total FROM student WHERE status = 'Inactive'";
$total_inactive = $conn->query($inactive_query)->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Management - FK Student Club</title>
    <link rel="stylesheet" href="membership.css">
    <script>
        window.onpageshow = function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                window.location.reload();
            }
        };
    </script>
</head>
<body>

<?php include('adminHeader.php') ?>

<div class="page-container">
    <header class="page-header">
        <h1>Membership Management</h1>
        <p>Admin Dashboard Metrics</p>
    </header>

    <div class="metrics-container">
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Total Students (Incl. Committee)</span>
                <h3 class="metric-value"><?php echo $total_combined; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">👤</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Active Committees</span>
                <h3 class="metric-value"><?php echo $count_committees; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">👥</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Inactive Users</span>
                <h3 class="metric-value" style="color:#dc3545;"><?php echo $total_inactive; ?></h3>
            </div>
            <div class="metric-icon-box red-icon">⚠️</div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-top-bar">
            <h3>Member List</h3>
            <div class="table-controls" style="display: flex; gap: 15px; align-items: center;">
                <button class="btn-register-new" onclick="window.location.href='register.php'">
                    + Register New User
                </button>

                <form action="membership.php" method="GET" style="display: flex; gap: 5px;">
                    <input type="text" name="search" placeholder="Search Matric or Name..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                           style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="submit" class="action-btn edit" style="cursor: pointer;">Search</button>
                    
                    <?php if(isset($_GET['search']) && $_GET['search'] !== ''): ?>
                        <a href="membership.php" class="action-btn" style="text-decoration:none; background:#6c757d; color:white; padding: 6px 12px; border-radius:4px; font-size: 12px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="member-table">
                <thead>
                    <tr>
                        <th>Matric Number</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
                    
                    $sql = "SELECT u.user_id, u.email, u.role, s.matric_number, s.name, s.status 
                            FROM user u 
                            LEFT JOIN student s ON u.user_id = s.user_id 
                            WHERE u.role != 'Admin'";

                    if ($search != '') {
                        $sql .= " AND (s.matric_number LIKE '%$search%' OR s.name LIKE '%$search%')";
                    }
                    
                    $sql .= " ORDER BY s.name ASC";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $uid = $row['user_id'];
                            $role_class = ($row['role'] == 'Committee') ? "committee-badge" : "student-badge";
                            
                            echo "<tr>";
                            echo "<td><strong>" . htmlspecialchars($row['matric_number'] ?? 'N/A') . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td><span class='badge $role_class'>" . htmlspecialchars($row['role']) . "</span></td>";
                            echo "<td>" . htmlspecialchars($row['status'] ?? 'Active') . "</td>";
                            echo "<td class='text-center'>
                                    <a href='editUser.php?id=$uid' class='action-btn edit'>Edit</a>
                                    <a href='deleteUser.php?id=$uid' class='action-btn delete' 
                                       onclick='return confirm(\"Delete user: " . htmlspecialchars($row['name'] ?? 'This user') . "?\")'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>No records found.</td></tr>";
                    }
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>