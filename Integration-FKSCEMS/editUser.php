<?php
session_start();

include ('session.php');

// 1. SECURITY: Only admins allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$club_memberships = [];

// 2. FETCH USER DATA (JOINED)
if (isset($_GET['id'])) {
    $user_id = $conn->real_escape_string($_GET['id']);
    
    // Join tables to get all information for the form
    $sql = "SELECT u.*, s.name, s.matric_number, s.status 
            FROM user u 
            LEFT JOIN student s ON u.user_id = s.user_id 
            WHERE u.user_id = '$user_id'";
            
    $result = $conn->query($sql);
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        die("User not found.");
    }

    // NEW: Fetch club membership and committee positions if user is a student
    if (!empty($user['matric_number'])) {
        $matric_number = $conn->real_escape_string($user['matric_number']);
        $sql_clubs = "SELECT c.club_name, m.membership_status, COALESCE(com.position, 'Member') as club_position
                      FROM membership m
                      JOIN club c ON m.club_id = c.club_id
                      LEFT JOIN committee com ON m.membership_id = com.membership_id
                      WHERE m.matric_number = '$matric_number'";
        $result_clubs = $conn->query($sql_clubs);
        while ($row = $result_clubs->fetch_assoc()) {
            $club_memberships[] = $row;
        }
    }
} else {
    header("Location: membership.php");
    exit();
}

// 3. HANDLE THE UPDATE REQUEST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    // CRITICAL ENUM FIX: Convert status value to lowercase to match ('active', 'inactive') in DB
    $status = strtolower($conn->real_escape_string($_POST['status'])); 

    // Start Transaction to update two tables safely
    $conn->begin_transaction();

    try {
        // A. Update the 'user' table
        $update_user = "UPDATE user SET email='$email', role='$role' WHERE user_id='$user_id'";
        $conn->query($update_user);

        // B. Update the 'student' table with lowercase status
        $update_student = "UPDATE student SET name='$name', status='$status' WHERE user_id='$user_id'";
        $conn->query($update_student);

        // Commit changes
        $conn->commit();
        
        echo "<script>
                alert('User profile updated successfully!');
                window.location.href='membership.php';
              </script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating record: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo htmlspecialchars($user['matric_number'] ?? $user_id); ?></title>
    <link rel="stylesheet" href="membership.css"> 
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; }
        .edit-container { max-width: 500px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .edit-container h2 { margin-top: 0; color: #004a99; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-group { margin-top: 20px; display: flex; gap: 10px; }
        .btn-save { background: #004a99; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; flex: 2; font-weight: bold; }
        .btn-cancel { background: #6c757d; color: white; text-decoration: none; padding: 12px 20px; border-radius: 4px; flex: 1; text-align: center; }
        .btn-save:hover { background: #003366; }
        .btn-cancel:hover { background: #5a6268; }
        .info-tag { background: #e9ecef; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; color: #333; }
        
        /* New styling for the added club section */
        .membership-panel { margin-top: 20px; padding: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .membership-panel-title { font-size: 14px; font-weight: bold; color: #004a99; margin-bottom: 10px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .club-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 13px; border-bottom: 1px dashed #e2e8f0; }
        .club-row:last-child { border-bottom: none; }
        .badge-status { font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 600; text-transform: capitalize; }
        .badge-approved { background-color: #dcfce7; color: #15803d; }
        .badge-pending { background-color: #fef9c3; color: #a16207; }
        .badge-rejected { background-color: #fee2e2; color: #b91c1c; }
        .position-label { color: #64748b; font-style: italic; }
    </style>
</head>
<body>

<div class="edit-container">
    <h2>Edit User Profile</h2>
    <div style="margin-bottom: 20px;">
        <span class="info-tag">Matric: <strong><?php echo htmlspecialchars($user['matric_number'] ?? 'N/A'); ?></strong></span>
        <span class="info-tag">Username: <strong><?php echo htmlspecialchars($user['username']); ?></strong></span>
    </div>

    <?php if(isset($error_message)) echo "<p style='color:red;'>$error_message</p>"; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role">
                <option value="student" <?php if(strtolower($user['role']) == 'student') echo 'selected'; ?>>Student</option>
                <option value="committee" <?php if(strtolower($user['role']) == 'committee') echo 'selected'; ?>>Committee Member</option>
                <option value="admin" <?php if(strtolower($user['role']) == 'admin') echo 'selected'; ?>>Administrator</option>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php if(strtolower($user['status'] ?? '') == 'active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if(strtolower($user['status'] ?? '') == 'inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>

        <div class="membership-panel">
            <div class="membership-panel-title">Club Membership Information</div>
            <?php if (!empty($club_memberships)): ?>
                <?php foreach ($club_memberships as $club): 
                    $current_status = strtolower($club['membership_status']); // Matches pending/approved/rejected
                    $badge_style = 'badge-pending';
                    if ($current_status === 'approved') $badge_style = 'badge-approved';
                    if ($current_status === 'rejected') $badge_style = 'badge-rejected';
                ?>
                    <div class="club-row">
                        <div>
                            <strong><?php echo htmlspecialchars($club['club_name']); ?></strong> 
                            <span class="position-label"> - <?php echo htmlspecialchars($club['club_position']); ?></span>
                        </div>
                        <span class="badge-status <?php echo $badge_style; ?>">
                            <?php echo htmlspecialchars($current_status); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="font-size: 12px; color: #94a3b8; text-align: center; padding: 5px 0;">
                    Not registered in any club memberships currently.
                </div>
            <?php endif; ?>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn-save">Save Updates</button>
            <a href="membership.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>