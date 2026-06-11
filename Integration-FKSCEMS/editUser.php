<?php
session_start();

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
} else {
    header("Location: membership.php");
    exit();
}

// 3. HANDLE THE UPDATE REQUEST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    $status = $conn->real_escape_string($_POST['status']);

    // Start Transaction to update two tables safely
    $conn->begin_transaction();

    try {
        // A. Update the 'user' table
        $update_user = "UPDATE user SET email='$email', role='$role' WHERE user_id='$user_id'";
        $conn->query($update_user);

        // B. Update the 'student' table
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
                <option value="Student" <?php if($user['role'] == 'Student') echo 'selected'; ?>>Student</option>
                <option value="Committee" <?php if($user['role'] == 'Committee') echo 'selected'; ?>>Committee Member</option>
                <option value="Admin" <?php if($user['role'] == 'Admin') echo 'selected'; ?>>Administrator</option>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="Active" <?php if($user['status'] == 'Active') echo 'selected'; ?>>Active</option>
                <option value="Inactive" <?php if($user['status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn-save">Save Changes</button>
            <a href="membership.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>