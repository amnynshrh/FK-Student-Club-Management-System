<?php
session_start(); // start session

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_club_management"; // Tukar ke database projek yang betul (asalnya: studentClub)

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uname = $_POST['username']; // name="username" dalam form
    $pass  = $_POST['password']; // name="password" dalam form
    $role  = $_POST['role'];     // name="role" dalam form
    
    // Guna table User dan matric_number (sesuai dgn DB projek anda)
    $stmt = $conn->prepare("SELECT user_id, name, password, role FROM User WHERE matric_number = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Validate Password 
        // Note: Pakai password_verify sebab password dalam DB anda sudah di hash
        if (password_verify($pass, $user['password']) || $pass === $user['password']) {
            
            // Set Session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Redirect ke dashboard masing-masing
            $role_folder = strtolower($user['role']);
            if ($role_folder == 'admin') {
                header("Location: admin/home.html");
            } else if ($role_folder == 'committee') {
                header("Location: committee/home.html");
            } else {
                header("Location: student/home.html");
            }
            exit();

        } else {
            echo "Invalid password.";
        }
    } else {
        echo "No user found with that matric number/username.";
    }
    $stmt->close();
}
$conn->close();
?>
