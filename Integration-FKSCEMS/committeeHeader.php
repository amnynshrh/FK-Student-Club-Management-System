<?php

$current_page = basename($_SERVER['PHP_SELF']);

$committee_name = $user_data['name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? '';
if ($committee_name === '' && !empty($_SESSION['user_id'])) {
    $header_conn = $conn ?? null;
    if (!$header_conn && file_exists(__DIR__ . '/config/db.php')) {
        require __DIR__ . '/config/db.php';
        $header_conn = $conn ?? null;
    }

    if ($header_conn) {
        $header_stmt = mysqli_prepare($header_conn, "SELECT s.name FROM student s WHERE s.user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($header_stmt, 'i', $_SESSION['user_id']);
        mysqli_stmt_execute($header_stmt);
        $header_user = mysqli_fetch_assoc(mysqli_stmt_get_result($header_stmt));
        $committee_name = $header_user['name'] ?? '';
    }
}

if ($committee_name === '') {
    $committee_name = $_SESSION['SESS_USERNAME'] ?? 'Committee';
}

// treat individual event pages as part of the "Manage Events" section
$manage_pages = [
    'manage_events.php',
    'view_event.php',
    'add_event.php',
    'edit_event.php'
];
$manage_active = in_array($current_page, $manage_pages, true) ? 'active' : '';

?>
<nav class="top-navigation">
    <div class="nav-wrapper">
        <div class="nav-left">
            <img
                src="fk.png"
                width="200"
                alt="FK Logo"
                class="nav-logo">
            
        </div>
        <ul class="menu-links">
            <li>
                <a
                    href="committeeDashboard.php"
                    class="<?php echo ($current_page == 'committeeDashboard.php') ? 'active' : ''; ?>">
                    Management
                </a>
            </li>
            <li>
                <a
                    href="manage_events.php"
                    class="<?php echo $manage_active ? 'active' : ''; ?>">
                    Manage Events
                </a>
            </li>
            <li>
                <a
                    href="manage_attendance.php"
                    class="<?php echo ($current_page == 'manage_attendance.php') ? 'active' : ''; ?>">
                    Manage Attendance
                </a>
            </li>
            <li>
                <a
                    href="editProfile.php"
                    class="<?php echo ($current_page == 'editProfile.php') ? 'active' : ''; ?>">
                    Profile
                </a>
            </li>
            <li>
                <a
                    href="logout.php"
                    class="logout-link"
                    style="color: red;">
                    Logout
                </a>
            </li>
        </ul>
        <div class="admin-profile">
            <span>
                Committee:
                <?php echo htmlspecialchars($committee_name); ?>
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
        margin: 0;
        padding: 0;
    }

    .menu-links a {
        text-decoration: none;
        color: #444;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        padding: 10px 15px;
        border-radius: 5px;
        transition: 0.3s;
    }

    .menu-links a:hover {
        color: var(--fk-blue);
    }

    .menu-links a.active {
        background-color: #eef5ff;
        color: var(--fk-blue);
        border-bottom: 3px solid var(--fk-blue);
    }

    .admin-profile {
        font-weight: bold;
        color: #004a99;
        white-space: nowrap;
    }
</style>
