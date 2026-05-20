<?php
if ($_SESSION['SESS_USER_ID'] == null) {
    header("Location: login.php?error=session_ended");
}

if (!isset($_COOKIE['session_timeout'])) {

    session_unset();
    session_destroy();

    header("Location: login.php?error=session_ended");
    exit();
}

// Reset timeout timer
setcookie("session_timeout", "active", time() + 300, "/");
?>