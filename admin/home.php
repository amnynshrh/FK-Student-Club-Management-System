<?php
// Start up your PHP Session
session_start();
// If the user is not logged in send him/her to the login form
if (!isset($_SESSION["Login"]) || $_SESSION["Login"] != "YES") {
    header("Location: ../index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Small overrides to make Bootstrap and Theme play nicely */
        body {
            background-color: #ffffff;
        }

        .table> :not(caption)>*>* {
            border-bottom-color: #eeeff2;
        }

        .btn-umpsa-teal {
            background-color: #009e96;
            color: white;
            transition: 0.2s;
        }

        .btn-umpsa-teal:hover {
            background-color: #1c3f95;
            color: white;
        }
    </style>
</head>

<body>

    <header class="top-navbar">
        <div class="nav-left">
            <img src="../assets/images/uploads/logo-fk.png" alt="FK Logo" class="nav-logo"
                onerror="this.src='../assets/images/logo-fk.png';">
            <div class="nav-brand">
                FK Student Club &<br>Management System
            </div>
        </div>

        <div class="nav-right">
            <a href="home.php" class="nav-link active-link">Home</a>
            <a href="manage_users.html" class="nav-link">Manage Users</a>
            <a href="manage_committees.html" class="nav-link">Manage Committees</a>
            <a href="manage_clubs.html" class="nav-link">Manage Clubs</a>
            <a href="manage_events.html" class="nav-link">Manage Events</a>
            <a href="reports.html" class="nav-link">Report</a>
            <a href="profile.html" class="nav-link">Profile</a>
            <a href="../api/auth/logout.php" class="nav-link">Log Out</a>
        </div>
    </header>

    <main class="student-content">
        <h1 class="page-title">Admin Dashboard</h1>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>