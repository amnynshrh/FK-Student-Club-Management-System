<?php
// 1. SESSION INITIALIZATION (Must be at the absolute top before any output)
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Error message variable to render cleanly within the UI layout if validation fails
$error_msg = "";

// 2. PROCESS FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = trim($_POST['username']);
    $input_pass = $_POST['password']; 
    $input_role = $_POST['role']; 

    // Search by username AND the selected role for strict access control
    $sql = "SELECT user_id, username, password, role FROM user WHERE username = ? AND role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $input_user, $input_role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify the plain text password against the securely encrypted database hash
        if (password_verify($input_pass, $user['password'])) {
            
            // Credentials match! Save critical tracking keys to the active Session state
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // INSTANT SERVER-SIDE REDIRECTION (No Popups)
            if ($user['role'] === 'Admin') {
                header("Location: admin.php");
            } elseif ($user['role'] === 'Committee') {
                header("Location: committeeDashboard.php");
            } else {
                header("Location: studentDashboard.php");
            }
            exit(); // Terminate script execution immediately after headers are sent

        } else {
            $error_msg = "Invalid username, password, or role.";
        }
    } else {
        $error_msg = "Invalid username, password, or role.";
    }
    
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FK Student Club</title>
    <link rel="stylesheet" href="register.css">
    <style>
        /* Preserving your design framework styles exactly */
        .row { display: flex; gap: 15px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .input-group input, .input-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        .btn-submit { width: 100%; padding: 12px; background: #004a99; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-bottom: 10px; }
        .btn-submit:hover { background: #003366; }
        .register-link { text-align: center; margin-top: 15px; font-size: 14px; }
        .register-link a { color: #004a99; text-decoration: none; font-weight: bold; }
        .register-link a:hover { text-decoration: underline; }
        
        /* Elegant inline message alert block matching your custom CSS schema */
        .error-box { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: 600; }
    </style>
</head>

<body>

<div class="main-container" style="max-width: 450px; margin: 100px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div class="form-box">
        <div class="header" style="text-align: center; margin-bottom: 25px;">
            <img src="fk.png" alt="Logo" class="logo" style="width: 80px;">
            <h2>Member Login</h2>
            <p>FK SCEMS Portal Access</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="error-box"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <div class="input-group">
                <label>Login As (Role)</label>
                <select name="role" required>
                    <option value="" disabled selected>Select your portal role</option>
                    <option value="Student">Student</option>
                    <option value="Committee">Committee Member</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>

            <p class="register-link">
                <a href="register.php">Register New Account</a>
            </p>
        </form>
    </div>
</div>

</body>

</html>
