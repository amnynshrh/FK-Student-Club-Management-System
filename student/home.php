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
    <title>Home - FK Student Club</title>
    <link rel="stylesheet" href="../assets/css/student.css">
</head>

<body>

    <!-- Home Page Style Navbar -->
    <header class="top-navbar">
        <div class="nav-left">
            <img src="../assets/images/uploads/logo-fk.png" alt="FK Logo" class="nav-logo"
                onerror="this.src='../assets/images/logo-fk.png';">
            <div class="nav-brand">
                FK Student Club &<br>Management System
            </div>
        </div>

        <div class="nav-right">
            <a href="home.php" class="nav-link">Home</a>
            <a href="view_club.html" class="nav-link">Find Clubs</a>
            <a href="event_registration.html" class="nav-link">Events</a>
            <a href="attendance_record.html" class="nav-link">Attendance</a>
            <a href="profile.html" class="nav-link">Profile</a>
            <a href="../api/auth/logout.php" class="nav-link">Log Out</a>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="student-content">
        <h1 class="page-title">Welcome to FK Student Club</h1>

    </main>

</body>

</html>