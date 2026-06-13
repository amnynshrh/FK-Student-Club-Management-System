<?php

$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $user_data['name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? '';

if ($admin_name === '' && !empty($_SESSION['user_id'])) {
    $header_conn = $conn ?? null;
    if (!$header_conn && file_exists(__DIR__ . '/config/db.php')) {
        require __DIR__ . '/config/db.php';
        $header_conn = $conn ?? null;
    }

    if ($header_conn) {
        // Correctly targets the admin table name column
        $header_stmt = mysqli_prepare($header_conn, "SELECT a.name FROM admin a WHERE a.user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($header_stmt, 'i', $_SESSION['user_id']); // FIXED key here
        mysqli_stmt_execute($header_stmt);
        $header_user = mysqli_fetch_assoc(mysqli_stmt_get_result($header_stmt));
        $admin_name = $header_user['name'] ?? '';
    }
}

if ($admin_name === '') {
    $admin_name = $_SESSION['username'] ?? 'Admin'; // FIXED fallback key here
}

?>
<nav class="top-navigation">
    <div class="nav-wrapper">
        <div class="nav-left">
            <img 
                src="fk.png"
                alt="FK Logo"
                class="nav-logo"
                width="200"
            >
            
        </div>
        <ul class="menu-links">
            <li>
                <a 
                    href="admin.php"
                    class="<?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>"
                >
                    Dashboard
                </a>
            </li>
            <li class="dropdown">
                <a href="membership.php" class="<?php echo ($current_page == 'membership.php') ? 'active' : ''; ?>">
                    User Management ▾
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a 
                            href="membership.php"
                            class="<?php echo ($current_page == 'membership.php') ? 'active' : ''; ?>"
                        >
                            View Students
                        </a>
                    </li>
                    <li>
                        <a 
                            href="register.php"
                            class="<?php echo ($current_page == 'register.php') ? 'active' : ''; ?>"
                        >
                            Register Student
                        </a>
                    </li>
                    <li>
                        <a 
                            href="adminRegister.php"
                            class="<?php echo ($current_page == 'adminRegister.php') ? 'active' : ''; ?>"
                        >
                            Register Admin
                        </a>
                    </li>
                </ul>
            </li>
            <li class="dropdown">
                <a href="admin_manage_club.php" class="<?php echo ($current_page == 'admin_manage_club.php') ? 'active' : ''; ?>">
                    Club Management ▾
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a 
                            href="admin_manage_club.php"
                            class="<?php echo ($current_page == 'admin_manage_club.php') ? 'active' : ''; ?>"
                        >
                            Club Dashboard
                        </a>
                    </li>
                    <li>
                        <a 
                            href="add_club.php"
                            class="<?php echo ($current_page == 'add_club.php') ? 'active' : ''; ?>"
                        >
                            Create Club
                        </a>
                    </li>
                    <li>
                        <a 
                            href="delete_club.php"
                            class="<?php echo ($current_page == 'delete_club.php') ? 'active' : ''; ?>"
                        >
                            Delete Club
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a 
                    href="event_dashboard.php"
                    class="<?php echo ($current_page == 'event_dashboard.php') ? 'active' : ''; ?>"
                >
                    Event Dashboard
                </a>
            </li>
            <li class="dropdown">
                <a href="attendance_dashboard.php" class="<?php echo ($current_page == 'attendance_dashboard.php') ? 'active' : ''; ?>">
                    Attendance Dashboard ▾
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a 
                            href="attendance_dashboard.php"
                            class="<?php echo ($current_page == 'attendance_dashboard.php') ? 'active' : ''; ?>"
                        >
                            Attendance Dashboard
                        </a>
                    </li>
                    <li>
                        <a 
                            href="student_list.php"
                            class="<?php echo ($current_page == 'student_list.php' || $current_page == 'student_participation.php') ? 'active' : ''; ?>"
                        >
                            Participation Tracking
                        </a>
                    </li>
                </ul>
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
                Admin: <?php echo htmlspecialchars($admin_name); ?>      
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

.dropdown {
    position: relative;
}

.dropdown-toggle {
    cursor: pointer;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    min-width: 220px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    padding: 10px 0;
    display: none;
    z-index: 1000;
}

.dropdown-menu li {
    list-style: none;
}

.dropdown-menu a {
    display: block;
    padding: 12px 18px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    transition: 0.2s;
}

.dropdown-menu a:hover {
    background: #f5f7fb;
    color: var(--fk-blue);
}

.dropdown:hover .dropdown-menu {
    display: block;
}

</style>
