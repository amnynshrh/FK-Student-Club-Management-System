<?php
session_start();

// 1. Database connection
$conn = new mysqli("localhost", "root", "", "fk_scems_db");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$selected_role = $_POST['role'] ?? '';
$input_user    = trim($_POST['username'] ?? '');
$input_pass    = $_POST['password'] ?? '';

if ($selected_role === '' || $input_user === '' || $input_pass === '') {
    header("Location: login.php?error=missing");
    exit();
}

$sql = "
    SELECT
        u.user_id,
        u.username,
        u.email,
        u.password,
        u.role,
        s.name AS student_name,
        s.matric_number,
        s.profile_photo,
        a.name AS admin_name
    FROM `user` u
    LEFT JOIN `student` s ON s.user_id = u.user_id
    LEFT JOIN `admin` a ON a.user_id = u.user_id
    WHERE u.username = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $input_user);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    header("Location: login.php?error=user_not_found");
    exit();
}

$row = mysqli_fetch_assoc($result);

if (strcasecmp($selected_role, $row['role']) !== 0) {
    header("Location: login.php?error=role_mismatch");
    exit();
}

if ($input_pass !== $row['password']) {
    header("Location: login.php?error=invalid_password");
    exit();
}

$role = strtolower($row['role']);
$displayName = $row['student_name'] ?: ($row['admin_name'] ?: $row['username']);

$_SESSION['Login']         = 'YES';
$_SESSION['user_id']       = $row['user_id'];
$_SESSION['username']      = $row['username'];
$_SESSION['name']          = $displayName;
$_SESSION['user_name']     = $displayName;
$_SESSION['email']         = $row['email'] ?? '';
$_SESSION['role']          = $role;
$_SESSION['matric']        = $row['matric_number'] ?? '';
$_SESSION['photo']         = $row['profile_photo'] ?? '';

$_SESSION['SESS_USER_ID']  = $row['user_id'];
$_SESSION['SESS_USERNAME'] = $row['username'];
$_SESSION['SESS_ROLE']     = $row['role'];

setcookie("session_timeout", "active", time() + 300, "/");

if ($role === 'admin') {
    header("Location: admin.php");
    exit();
}

if ($role === 'student') {
    header("Location: studentDashboard.php");
    exit();
}

if ($role === 'committee') {
    $sql_committee = "
        SELECT c.committee_id
        FROM student s
        INNER JOIN membership m ON s.matric_number = m.matric_number
        INNER JOIN committee c ON m.membership_id = c.membership_id
        WHERE s.user_id = ?
        LIMIT 1
    ";

    $stmt_committee = mysqli_prepare($conn, $sql_committee);
    mysqli_stmt_bind_param($stmt_committee, "i", $row['user_id']);
    mysqli_stmt_execute($stmt_committee);
    $result_committee = mysqli_stmt_get_result($stmt_committee);

    if ($result_committee && mysqli_num_rows($result_committee) > 0) {
        $committee_data = mysqli_fetch_assoc($result_committee);
        $_SESSION['SESS_COMMITTEE_ID'] = $committee_data['committee_id'];
    }

    header("Location: committeeDashboard.php");
    exit();
}

header("Location: index.php");
exit();
