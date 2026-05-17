<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .register-container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-save {
            background: #0056b3; /* Placeholder for var(--fk-blue) */
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            font-weight: bold;
        }
        .btn-save:hover { background: #004494; }
    </style>
</head>
<body>

<nav class="top-navigation">
    <div class="nav-wrapper">
        <div class="logo"><img src="fk.png" alt="FK Logo"></div>
        <ul class="menu-links">
            <li><a href="adminRegister.php" class="active">Register</a></li>
        </ul>
        <div class="admin-profile"><span>FK Admin</span></div>
    </div>
</nav>

<div class="page-container">
    <div class="register-container">
        <header class="page-header" style="text-align: center;">
            <h1>Register New Admin</h1>
            <p>Add a new admin to the system</p>
        </header>
        <br>

        <form action="adminRegProccess.php" method="POST">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter full name" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Choose a login username" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="staff@fk.umpsa.my" required>
            </div>

            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_no" placeholder="e.g., 0123456789" required>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="Admin" selected>Admin (FK Staff)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Set initial password" required>
            </div>

            <button type="submit" class="btn-save">Register New Admin</button>
        </form>
    </div>
</div>

</body>
</html>