<?php

$current_page = basename($_SERVER['PHP_SELF']);
$event_pages = [
    'event_registration.php',
    'event_registration_form.php',
    'event_registration_history.php',
    'student_event_view.php',
];


$student_name = $user_data['name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? '';
if ($student_name === '' && !empty($_SESSION['SESS_USER_ID'])) {
    $header_conn = $conn ?? null;
    if (!$header_conn && file_exists(__DIR__ . '/config/db.php')) {
        require __DIR__ . '/config/db.php';
        $header_conn = $conn ?? null;
    }

    if ($header_conn) {
        $header_stmt = mysqli_prepare($header_conn, "SELECT s.name FROM student s WHERE s.user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($header_stmt, 'i', $_SESSION['SESS_USER_ID']);
        mysqli_stmt_execute($header_stmt);
        $header_user = mysqli_fetch_assoc(mysqli_stmt_get_result($header_stmt));
        $student_name = $header_user['name'] ?? '';
    }
}

if ($student_name === '') {
    $student_name = $_SESSION['SESS_USERNAME'] ?? 'Student';
}

?>
<nav class="top-navigation">
    <div class="nav-wrapper">
        <div class="nav-left">
            <img src="fk.png" width="200" alt="FK Logo" class="nav-logo">
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
                <a href="event_registration.php" class="<?php echo in_array($current_page, $event_pages, true) ? 'active' : ''; ?>">
                    Event Registration
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
                <?php echo htmlspecialchars($student_name); ?>
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
    width: 100%;
}

.nav-wrapper {
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    width: 100%;
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
    margin: 0;
    padding: 0;
    align-items: center;
}

.menu-links li {
    list-style: none;
}

.menu-links a {
    text-decoration: none;
    color: #444;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 10px 15px;
    border-radius: 5px;
    display: inline-block;
    line-height: 1.2;
    margin: 0;
}

.menu-links a.active {
    background-color: #eef5ff;
    color: var(--fk-blue);
    border-bottom: 3px solid var(--fk-blue);
}

.admin-profile {
    font-weight: bold;
    color: var(--fk-blue);
    font-size: 0.9rem;
    white-space: nowrap;
}

body .top-navigation {
    background: #ffffff !important;
    border-bottom: 1px solid #ddd !important;
    padding: 10px 0 !important;
}

body .top-navigation .nav-wrapper {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 0 20px !important;
}

body .top-navigation .menu-links {
    display: flex !important;
    gap: 10px !important;
    margin: 0 !important;
    padding: 0 !important;
}

body .top-navigation .menu-links a {
    color: #444 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    padding: 10px 15px !important;
    border-radius: 5px !important;
}

body .top-navigation .menu-links a.active {
    background-color: #eef5ff !important;
    color: var(--fk-blue) !important;
    border-bottom: 3px solid var(--fk-blue) !important;
}

</style>
