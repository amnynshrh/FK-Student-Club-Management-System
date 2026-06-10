<?php
// 1. SESSION INITIALIZATION
session_start();

// Check for error flags sent back from authenticateLogin.php
$error_msg = "";
if (isset($_GET['error']) && $_GET['error'] == '1') {
    $error_msg = "Invalid username, password, or role.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FK Student Club</title>
    <link rel="stylesheet" href="loginRegister.css">
    <style>
        .row { display: flex; gap: 15px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .input-group input, .input-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        .btn-submit { width: 100%; padding: 12px; background: #004a99; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-bottom: 10px; }
        .btn-submit:hover { background: #003366; }
        .register-link { text-align: center; margin-top: 15px; font-size: 14px; }
        .register-link a { color: #004a99; text-decoration: none; font-weight: bold; }
        .register-link a:hover { text-decoration: underline; }
        
        /* Elegant inline message alert block */
        .error-box { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: 600; }
    </style>
</head>
<body>

<div class="main-container" style="max-width: 450px; margin: 100px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div class="form-box">
        <div class="header" style="text-align: center; margin-bottom: 25px;">
            <img src="fk.png" alt="Logo" class="logo" style="width: 250px;">
            <h2>LOGIN</h2>   
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="error-box"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form action="loginAuthenticate.php" method="POST">
            
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
        </form>
    </div>
</div>

</body>
</html>
