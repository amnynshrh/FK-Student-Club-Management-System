<?php
// 1. SESSION INITIALIZATION
session_start();

// 2. SECURITY: Check if user is logged in (Standardized to match login.php session variables)
if (!isset($_SESSION['SESS_USER_ID'])) {
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

$user_id = $_SESSION['SESS_USER_ID']; 

// 4. FETCH DATA (JOINING USER AND STUDENT)
$sql = "SELECT u.email, u.contact_no, s.name, s.profile_photo 
        FROM user u 
        JOIN student s ON u.user_id = s.user_id 
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data) { 
    die("Profile not found."); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - FK Student Club</title>
    <link rel="stylesheet" href="editProfile.css">
</head>
<body>

<div class="edit-box">
    <h2>Update Profile</h2>
    
    <div style="text-align: center; margin-bottom: 15px;">
        <img src="uploads/<?php echo htmlspecialchars($user_data['profile_photo'] ?? 'default.png'); ?>" 
             alt="Profile" 
             style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #004a99;">
    </div>

    <form action="updateProccess.php" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="contactNo" value="<?php echo htmlspecialchars($user_data['contact_no'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>New Photo</label>
            <input type="file" name="profilePhoto" accept="image/*">
        </div>

        <button type="submit" class="btn-save">Save Changes</button>
        
        <?php 
            // Determine where the "Back" link should go based on standard user roles
            // Re-routed to point directly to studentdfashboard.php 
            $back_link = (isset($_SESSION['role']) && $_SESSION['role'] === 'Committee') ? "committeeDashboard.php" : "studentDashboard.php";
        ?>
        <a href="<?php echo $back_link; ?>" class="btn-cancel">Back to Dashboard</a>
    </form>
</div>

</body>
</html>