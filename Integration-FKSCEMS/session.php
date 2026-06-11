<?php
if (empty($_SESSION['SESS_USER_ID']) && !empty($_SESSION['user_id'])) {
    $_SESSION['SESS_USER_ID'] = $_SESSION['user_id'];
}

if (empty($_SESSION['SESS_USER_ID'])) {
    header("Location: login.php?error=session_ended");
    exit();
}

if (!isset($_COOKIE['session_timeout'])) {
    setcookie("session_timeout", "active", time() + 300, "/");
} else {
    setcookie("session_timeout", "active", time() + 300, "/");
}
?>
