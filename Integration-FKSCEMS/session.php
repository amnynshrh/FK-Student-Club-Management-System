<?php
if ($_SESSION['user_id'] == null) {
    header("Location: login.php?error=session_ended");
    unset($_COOKIE['session_timeout']);    
    exit();
}

if (!isset($_COOKIE['session_timeout'])) {

    session_unset();
    session_destroy();

    header("Location: login.php?error=session_ended");
    unset($_COOKIE['session_timeout']);    
    exit();
}


// Reset timeout timer
setcookie("session_timeout", "active", time() + 300, "/");
?>