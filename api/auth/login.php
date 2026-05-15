<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$matric   = input('matric_number');
$password = input('password');

if (!$matric || !$password) {
    jsonResponse(['error' => 'Matric number and password are required.'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM `User` WHERE matric_number = ? AND status = 'active' LIMIT 1");
$stmt->execute([$matric]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(['error' => 'Invalid matric number or password.'], 401);
}

// Set session
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['name']    = $user['name'];
$_SESSION['role']    = $user['role'];
$_SESSION['matric']  = $user['matric_number'];
$_SESSION['email']   = $user['email'];
$_SESSION['photo']   = $user['profile_photo'] ?? '';

// Determine redirect by role
$redirect = match($user['role']) {
    'admin'     => '../admin/home.html',
    'committee' => '../committee/home.html',
    'student'   => '../student/home.html',
    default     => '../index.html',
};

jsonResponse(['success' => true, 'role' => $user['role'], 'redirect' => $redirect]);
