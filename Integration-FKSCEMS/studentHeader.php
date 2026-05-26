<?php

$current_page = basename($_SERVER['PHP_SELF']);

?>
<nav class="top-navigation">
    <div class="nav-wrapper">
        <div class="nav-left">
            <img src="assets/images/logo-fk.png" alt="FK Logo" class="nav-logo">
            <div class="nav-brand">
                FK Student Club &<br>Event Management System
            </div>
        </div>
        <ul class="menu-links">
            <li>
                <a href="studentDashboard.php" class="<?php echo ($current_page == 'studentDashboard.php') ? 'active' : ''; ?>">
                    Home
                </a>
            </li>
            <li>
                <a href="view_club.php" class="<?php echo ($current_page == 'view_club.php') ? 'active' : ''; ?>">
                    Explore Clubs
                </a>
            </li>
            <li>
                <a href="event_registration.php" class="<?php echo ($current_page == 'event_registration.php') ? 'active' : ''; ?>">
                    Activities
                </a>
            </li>
            <li>
                <a href="my_participation.php" class="<?php echo ($current_page == 'my_participation.php') ? 'active' : ''; ?>">
                    My Participation
                </a>
            </li>
            <li>
                <a href="attendance.php" class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
                    Attendance
                </a>
            </li>
            <li>
                <a href="editProfile.php" class="<?php echo ($current_page == 'editProfile.php') ? 'active' : ''; ?>">
                    Profile
                </a>
            </li>
            <li>
                <a href="logout.php" class="logout-link" style="color: red; font-weight: 700">
                    Logout
                </a>
            </li>
        </ul>
        <div class="admin-profile">
            <span>
                Student: 
                <?php echo htmlspecialchars($_SESSION['SESS_USERNAME'] ?? 'Student'); ?>
            </span>
        </div>
    </div>
</nav>

<style>

:root {
    --fk-blue: #004a99;
    --bg-gray: #f4f7f6;
    --white: #ffffff;
    --text-muted: #6c757d;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

.top-navigation {
    background: var(--white);
    border-bottom: 1px solid #ddd;
    padding: 10px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
}

.nav-wrapper {
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}

.nav-left {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.nav-logo {
    height: 55px;
    margin-right: 25px;
    object-fit: contain;
}

.nav-brand {
    font-size: 16px;
    font-weight: 700;
    color: #1c3f95;
    /* UMPSA Navy */
    line-height: 1.3;
    border-left: 2px solid #eeeff2;
    padding-left: 20px;
}

.menu-links {
    list-style: none;
    display: flex;
    gap: 10px;
}

.menu-links a {
    text-decoration: none;
    color: #444;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 10px 15px;
    border-radius: 5px;
}

.menu-links a.active {
    background-color: #eef5ff;
    color: var(--fk-blue);
    border-bottom: 3px solid var(--fk-blue);
}

.admin-profile {
    font-weight: bold;
    color: var(--fk-blue);
}

</style>