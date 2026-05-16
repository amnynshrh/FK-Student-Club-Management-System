<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_club_management"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uname = $_POST['username'];
    $pass  = $_POST['password'];
    $role  = $_POST['role'];
    
    $stmt = $conn->prepare("SELECT user_id, name, password, role FROM User WHERE matric_number = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($pass, $user['password']) || $pass === $user['password']) {
            
            // If correct, we set the session to YES
            $_SESSION["Login"] = "YES";
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            $role_folder = strtolower($user['role']);
            if ($role_folder == 'admin') {
                header("Location: ../../admin/home.php");
            } else if ($role_folder == 'committee') {
                header("Location: ../../committee/home.php");
            } else {
                header("Location: ../../student/home.php");
            }
            exit();

        } else {
            // If not correct, we set the session to NO
            $_SESSION["Login"] = "NO";
            echo "<h1>You are NOT logged correctly in </h1>";
            echo "<p><a href='../../index.html'>Link to login file</a></p>";
        }
    } else {
        // If not correct, we set the session to NO
        $_SESSION["Login"] = "NO";
        echo "<h1>You are NOT logged correctly in </h1>";
        echo "<p><a href='../../index.html'>Link to login file</a></p>";
    }
    $stmt->close();
}
$conn->close();
?>
