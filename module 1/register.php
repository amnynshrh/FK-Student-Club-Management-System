<?php
session_start();

// 1. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; 

// 2. PROCESS FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect Data from Form
    $custom_username = trim($_POST['username']); 
    $matric_id       = trim($_POST['matricNumber']);
    $full_name       = $_POST['name'];
    $email           = $_POST['email'];
    $pass            = $_POST['password'];
    $contact         = $_POST['contactNo'];
    $role            = $_POST['role'];
    $course          = $_POST['course']; 
    $status          = "Active";

    // 3. FILE UPLOAD LOGIC
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

    $file_extension = pathinfo($_FILES["profilePhoto"]["name"], PATHINFO_EXTENSION);
    $file_name = $matric_id . "_" . time() . "." . $file_extension; 
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["profilePhoto"]["tmp_name"], $target_file)) {
        
        // 4. START TRANSACTION
        $conn->begin_transaction();

        try {
            // A. Insert into 'user' table
            $sql_user = "INSERT INTO user (username, email, password, role, contact_no) VALUES (?, ?, ?, ?, ?)";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("sssss", $custom_username, $email, $pass, $role, $contact);
            $stmt_user->execute();
            
            $new_user_id = $conn->insert_id;

            // B. Insert into 'student' table
            $sql_stud = "INSERT INTO student (matric_number, user_id, name, course, profile_photo, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_stud = $conn->prepare($sql_stud);
            $stmt_stud->bind_param("sissss", $matric_id, $new_user_id, $full_name, $course, $file_name, $status);
            $stmt_stud->execute();

            $conn->commit();

            // UPDATED: Success Popup redirects to membership.php
            echo "<script>
                    alert('New member registered successfully!');
                    window.location.href='membership.php';
                  </script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            if (file_exists($target_file)) { unlink($target_file); }
            $message = "<div class='alert error'>Error: Username, Matric Number, or Email already exists.</div>";
        }
    } else {
        $message = "<div class='alert error'>Error uploading profile photo.</div>";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FK Student Club</title>
    <link rel="stylesheet" href="register.css">
    <style>
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: 600; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .row { display: flex; gap: 15px; }
        .input-group { flex: 1; margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .input-group input, .input-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: #004a99; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-bottom: 10px; }
        .btn-submit:hover { background: #003366; }
        
        /* New Back Button Style */
        .btn-back { display: block; width: 100%; padding: 12px; background: #6c757d; color: white; text-decoration: none; text-align: center; border-radius: 5px; font-size: 16px; box-sizing: border-box; }
        .btn-back:hover { background: #5a6268; }
    </style>
</head>
<body>

<div class="main-container" style="max-width: 600px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div class="form-box">
        <div class="header" style="text-align: center; margin-bottom: 25px;">
            <img src="fk.png" alt="Logo" class="logo" style="width: 80px;">
            <h2>Register New Member</h2>
            <p>Admin Registration Management</p>
        </div>

        <?php echo $message; ?>

        <form action="register.php" method="POST" enctype="multipart/form-data">
            
            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="John Doe" required>
            </div>

            <div class="input-group">
                <label>Username (Login ID)</label>
                <input type="text" name="username" placeholder="e.g. jdoe99" required>
            </div>

            <div class="row">
                <div class="input-group">
                    <label>Matric Number</label>
                    <input type="text" name="matricNumber" placeholder="CB21000" required>
                </div>
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="student@umpsa.edu.my" required>
                </div>
            </div>

            <div class="row">
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="input-group">
                    <label>Contact No</label>
                    <input type="text" name="contactNo" placeholder="0123456789" required>
                </div>
            </div>

            <div class="input-group">
                <label>Course Name</label>
                <select name="course" required>
                    <option value="" disabled selected>Select your course</option>
                    <option value="Bachelor of Computer Science (Software Engineering)">Software Engineering</option>
                    <option value="Bachelor of Computer Science (Computer Systems & Networking)">Networking</option>
                    <option value="Bachelor of Computer Science (Graphics & Multimedia)">Graphics & Multimedia</option>
                    <option value="Bachelor of Computer Science (Cyber Security)">Cyber Security</option>
                    <option value="Diploma in Computer Science">Diploma Computer Science</option>
                </select>
            </div>

            <div class="row">
                <div class="input-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="Student">Student</option>
                        <option value="Committee">Committee Member</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Profile Photo</label>
                    <input type="file" name="profilePhoto" accept="image/*" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">Register Member</button>
            
            <a href="membership.php" class="btn-back">Back</a>
        </form>
    </div>
</div>

</body>
</html>