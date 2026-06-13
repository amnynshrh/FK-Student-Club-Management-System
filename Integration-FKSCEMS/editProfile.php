<?php
// 1. SESSION INITIALIZATION
session_start();

include ('session.php');

// SECURITY: Prevent browser caching sensitive data
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. SECURITY: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

$user_id = $_SESSION['user_id']; 

// 4. FETCH DATA (JOINING USER AND STUDENT)
$sql = "SELECT u.email, u.contact_no, s.name, s.profile_photo, s.matric_number 
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

// 5. NEW: FETCH CLUB MEMBERSHIP INFORMATION
$club_memberships = [];
if (!empty($user_data['matric_number'])) {
    $matric_number = $user_data['matric_number'];
    
    // Joint query to pull club details alongside their application/membership status
    $sql_clubs = "SELECT c.club_name, m.membership_status, 
                         COALESCE(com.position, 'Member') as club_position
                  FROM membership m
                  JOIN club c ON m.club_id = c.club_id
                  LEFT JOIN committee com ON m.membership_id = com.membership_id
                  WHERE m.matric_number = ?";
                  
    $stmt_clubs = $conn->prepare($sql_clubs);
    $stmt_clubs->bind_param("s", $matric_number);
    $stmt_clubs->execute();
    $result_clubs = $stmt_clubs->get_result();
    
    while ($row = $result_clubs->fetch_assoc()) {
        $club_memberships[] = $row;
    }
    $stmt_clubs->close();
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - FK Student Club</title>
    <link rel="stylesheet" href="editProfile.css">
    <style>
        /* Embedded styling helper for the new club data container */
        .membership-section {
            margin-top: 20px;
            padding: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .membership-title {
            font-size: 14px;
            font-weight: bold;
            color: #334155;
            margin-bottom: 8px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .club-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 13px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .club-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-approved { background-color: #dcfce7; color: #15803d; }
        .status-pending { background-color: #fef9c3; color: #a16207; }
        .status-rejected { background-color: #fee2e2; color: #b91c1c; }
        .position-tag { color: #64748b; font-style: italic; }
    </style>
</head>
<body>

<div class="edit-box">
    <h2>Update Profile</h2>
    
    <div style="text-align: center; margin-bottom: 15px;">
        <img src="uploads/<?php echo htmlspecialchars($user_data['profile_photo'] ?? 'default.png'); ?>" 
             alt="Profile" 
             onerror="this.onerror=null; this.src='ProfilePhoto.png';"
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

        <div class="membership-section">
            <div class="membership-title">Club Membership Information</div>
            <?php if (!empty($club_memberships)): ?>
                <?php foreach ($club_memberships as $club): 
                    // Set color styles dynamically mapping to your ENUM strings: pending, approved, rejected
                    $status = strtolower($club['membership_status']);
                    $badge_class = 'status-pending';
                    if ($status === 'approved') $badge_class = 'status-approved';
                    if ($status === 'rejected') $badge_class = 'status-rejected';
                ?>
                    <div class="club-item">
                        <div>
                            <strong><?php echo htmlspecialchars($club['club_name']); ?></strong> 
                            <span class="position-tag"> - <?php echo htmlspecialchars($club['club_position']); ?></span>
                        </div>
                        <span class="status-badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="font-size: 12px; color: #94a3b8; text-align: center; padding: 5px 0;">
                    Not registered in any club memberships currently.
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-save" style="margin-top: 20px;">Save Changes</button>
        
        <?php 
            // Determine where the "Back" link should go based on standard user roles
            $back_link = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'committee') ? "committeeDashboard.php" : "studentDashboard.php";
        ?>
        <a href="<?php echo $back_link; ?>" class="btn-cancel">Back to Dashboard</a>
    </form>
</div>

</body>
</html>