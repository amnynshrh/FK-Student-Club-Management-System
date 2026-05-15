<?php
// ── Session helper: start if not started ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require a logged-in session.
 * If not logged in, return 401 JSON.
 */
function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorised. Please log in.']);
        exit;
    }
}

/**
 * Require a specific role (or array of roles).
 * Usage: requireRole('admin')  or  requireRole(['admin','committee'])
 */
function requireRole($roles) {
    requireAuth();
    if (is_string($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden. Insufficient permissions.']);
        exit;
    }
}

/**
 * Return current logged-in user info from session.
 */
function currentUser() {
    return [
        'user_id' => $_SESSION['user_id']  ?? null,
        'name'    => $_SESSION['name']     ?? '',
        'role'    => $_SESSION['role']     ?? '',
        'matric'  => $_SESSION['matric']   ?? '',
        'email'   => $_SESSION['email']    ?? '',
        'photo'   => $_SESSION['photo']    ?? '',
    ];
}
