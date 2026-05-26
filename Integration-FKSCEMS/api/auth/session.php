<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION["Login"] ?? "") !== "YES") {
    echo json_encode(['loggedIn' => false]);
    exit;
}

echo json_encode([
    'loggedIn' => true,
    'user_id'  => $_SESSION['user_id'],
    'name'     => $_SESSION['name'] ?? '',
    'role'     => $_SESSION['role'] ?? '',
    'matric'   => $_SESSION['matric'] ?? '',
    'email'    => $_SESSION['email'] ?? '',
    'photo'    => $_SESSION['photo'] ?? '',
]);
