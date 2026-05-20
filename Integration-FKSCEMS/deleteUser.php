<?php
session_start();

// 1. SECURITY CHECK: Only Admin can delete
if (!isset($_SESSION['SESS_ROLE']) || $_SESSION['SESS_ROLE'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 2. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. PROCESS DELETE
if (isset($_GET['id'])) {
    $uid = intval($_GET['id']); // Ensure ID is an integer for security

    // START TRANSACTION: Ensure both tables are cleaned up or none at all
    $conn->begin_transaction();

    try {
        // A. Get the profile photo filename first so we can delete the file from the folder
        $sql_photo = "SELECT profile_photo FROM student WHERE user_id = ?";
        $stmt_p = $conn->prepare($sql_photo);
        $stmt_p->bind_param("i", $uid);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        
        if ($res_p->num_rows > 0) {
            $photo_name = $res_p->fetch_assoc()['profile_photo'];
            $file_path = "uploads/" . $photo_name;
            if (file_exists($file_path) && !empty($photo_name)) {
                unlink($file_path); // Remove the actual image file from server
            }
        }

        // B. Delete from 'student' table first (Child table)
        $sql1 = "DELETE FROM student WHERE user_id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $uid);
        $stmt1->execute();

        // C. Delete from 'user' table (Parent table)
        $sql2 = "DELETE FROM user WHERE user_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $uid);
        $stmt2->execute();

        // If everything is fine, commit the changes
        $conn->commit();
        
        // Success popup
        echo "<script>
                alert('User and associated records deleted successfully.');
                window.location.href='membership.php';
              </script>";
        exit();

    } catch (Exception $e) {
        // If there is an error, undo everything
        $conn->rollback();
        echo "<script>
                alert('Error deleting user: " . $conn->error . "');
                window.location.href='membership.php';
              </script>";
        exit();
    }
} else {
    header("Location: membership.php");
    exit();
}

$conn->close();
?>