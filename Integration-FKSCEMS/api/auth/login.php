<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../index.html");
    exit();
}

require_once "../../config/db.php";

$selected_role = $_POST['role'] ?? '';
$input_user = trim($_POST['username'] ?? '');
$input_pass = $_POST['password'] ?? '';

if ($selected_role === '' || $input_user === '' || $input_pass === '') {
    header("Location: ../../index.html?error=missing");
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

if (mysqli_num_rows($result) === 1) {
    $row = mysqli_fetch_assoc($result);

    if (strcasecmp($selected_role, $row['role']) === 0) {
        if ($input_pass === $row['password']) {
            $role = strtolower($row['role']);
            $displayName = $row['student_name'] ?: ($row['admin_name'] ?: $row['username']);

            $_SESSION["Login"] = "YES";
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['name'] = $displayName;
            $_SESSION['user_name'] = $displayName;
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $role;
            $_SESSION['matric'] = $row['matric_number'] ?? '';
            $_SESSION['photo'] = $row['profile_photo'] ?? '';

            $_SESSION['SESS_USER_ID'] = $row['user_id'];
            $_SESSION['SESS_USERNAME'] = $row['username'];
            $_SESSION['SESS_ROLE'] = $role;

            if ($role === 'admin') {
                header("Location: ../../admin/home.php");
            } else if ($role === 'student') {
                header("Location: ../../student/home.php");
            } else if ($role === 'committee') {
                header("Location: ../../committee/home.php");
            } else {
                header("Location: ../../index.html");
            }
            exit();
        }

        $_SESSION["Login"] = "NO";
        header("Location: ../../index.html?error=invalid_password");
        exit();
    }

    $_SESSION["Login"] = "NO";
    header("Location: ../../index.html?error=role_mismatch");
    exit();
}

$_SESSION["Login"] = "NO";
header("Location: ../../index.html?error=user_not_found");
exit();
