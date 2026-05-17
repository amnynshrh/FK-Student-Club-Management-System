<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FK Student Club</title>
    <link rel="stylesheet" href="login.css">
    <style>
        .alert-box { padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center; font-size: 14px; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="form-box">
    <div class="header">
        <img src="fk.png" alt="Logo" class="logo">
        <h2>System Login</h2>
        <p>Please enter your credentials</p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert-box error">
            <?php 
                if ($_GET['error'] == 'invalid_password') echo "Incorrect password. Please try again.";
                elseif ($_GET['error'] == 'role_mismatch') echo "The role selected does not match our records.";
                elseif ($_GET['error'] == 'user_not_found') echo "No account found with that username.";
                else echo "Login failed. Please try again.";
            ?>
        </div>
    <?php endif; ?>

    <form action="authenticate.php" method="POST">
        
        <div class="input-group">
            <label>Login As:</label>
            <select name="role" required>
                <option value="Student">Student</option>
                <option value="Committee">Committee Member</option>
                <option value="Admin">Administrator</option>
            </select>
        </div>

        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter your username" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Sign In</button>
        
        <p style="text-align: center; margin-top: 15px;">
            <a href="register.php">Register New Account</a>
        </p>
    </form>
</div>

<script>
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('logout') === 'success') {
            alert("Your session has been terminated. Please login again.");
            // Clean the URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    };
</script>

</body>
</html>