<?php
session_start();

// 1. Database connection
$conn = new mysqli("localhost", "root", "", "fk_scems_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2. Collect and sanitize input
    $selected_role = $_POST['role'];       // Role from dropdown (Admin, Student, etc.)
    $input_user    = trim($_POST['username']); 
    $input_pass    = $_POST['password'];

    // 3. Prepare query to find the user by their username
    $sql = "SELECT * FROM user WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $input_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // 4. Validate Role Match
        // We use strcasecmp to ensure "Admin" vs "admin" doesn't break the login
        if (strcasecmp($selected_role, $row['role']) == 0) {
            
            // 5. Validate Password
            // Note: If you have hashed passwords, use password_verify($input_pass, $row['password'])
            if ($input_pass === $row['password']) {
                
                // 6. Setup Sessions
                $_SESSION['SESS_USER_ID']   = $row['user_id'];
                $_SESSION['SESS_USERNAME']  = $row['username'];
                $_SESSION['SESS_ROLE']      = $row['role'];

                setcookie("session_timeout", "active", time() + 300, "/");

                // 7. Role-based Redirection
                // Ensure the case matches exactly what is in your database 'role' column
                if ($row['role'] === 'Admin') {
                    header("Location: admin.php");
                } else if ($row['role'] === 'Student') {
                    header("Location: studentDashboard.php");
                } else if ($row['role'] === 'Committee') {
                    $sql_committee = "SELECT c.committee_id
                    FROM student s
                    INNER JOIN membership m
                    ON s.matric_number = m.matric_number
                    INNER JOIN committee c
                    ON m.membership_id = c.membership_id
                    WHERE s.user_id = ?
                    LIMIT 1
                    ";

                    $stmt_committee = $conn->prepare($sql_committee);
                    $stmt_committee->bind_param("i", $row['user_id']);
                    $stmt_committee->execute();
                    $result_committee = $stmt_committee->get_result();
                    if ($result_committee->num_rows > 0) {
                        $committee_data = $result_committee->fetch_assoc();
                        $_SESSION['SESS_COMMITTEE_ID'] = $committee_data['committee_id'];
                    }
                    header("Location: committeeDashboard.php");
                } else {
                    // Fallback if role is valid but no page is assigned
                    header("Location: index.php");
                }
                exit();

            } else {
                header("Location: login.php?error=invalid_password");
                exit();
            }
        } else {
            // User exists, but they selected the wrong role in the dropdown
            header("Location: login.php?error=role_mismatch");
            exit();
        }
    } else {
        // No user found with that username
        header("Location: login.php?error=user_not_found");
        exit();
    }
}

$conn->close();
?>