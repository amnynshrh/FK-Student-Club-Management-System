<?php
session_start();

// 1. SECURITY CHECK: Ensure user is logged in via user_id session and has Admin role privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
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

// 3. PROCESS DELETE WITH INACTIVE STATUS GUARDRAIL
if (isset($_GET['id'])) {
    $uid = intval($_GET['id']); // Ensure ID is an integer for security

    // Prevent administrators from accidentally deleting their own currently active session
    if ($uid === intval($_SESSION['user_id'])) {
        echo "<script>
                alert('Security Violation: You cannot delete your own administrative account while logged in.');
                window.location.href='membership.php';
              </script>";
        exit();
    }

    // A. STATUS VERIFICATION CHECK: Query database to ensure student account is marked 'inactive'
    $sql_check = "SELECT status FROM student WHERE user_id = ?";
    $stmt_c = $conn->prepare($sql_check);
    $stmt_c->bind_param("i", $uid);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();

    if ($res_c->num_rows > 0) {
        $student_data = $res_c->fetch_assoc();
        // Lowercase check directly matching ENUM('active','inactive') from db schema
        if (strtolower($student_data['status']) !== 'inactive') {
            echo "<script>
                    alert('Deletion Cancelled: Only accounts marked as Inactive can be deleted.');
                    window.location.href='membership.php';
                  </script>";
            exit();
        }
    } else {
        // Handle scenario where it might be a user row without a student profile record
        echo "<script>
                alert('Error: Specified user profile records could not be found.');
                window.location.href='membership.php';
              </script>";
        exit();
    }
    $stmt_c->close();

    // START TRANSACTION: Ensure all steps complete successfully without orphaned data anomalies
    $conn->begin_transaction();

    try {
        // B. Clear Profile Photo Assets from Server
        $sql_photo = "SELECT profile_photo FROM student WHERE user_id = ?";
        $stmt_p = $conn->prepare($sql_photo);
        $stmt_p->bind_param("i", $uid);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        
        if ($res_p->num_rows > 0) {
            $photo_name = $res_p->fetch_assoc()['profile_photo'];
            $file_path = "uploads/" . $photo_name;
            if (!empty($photo_name) && file_exists($file_path)) {
                unlink($file_path); // Remove the actual image file from server asset directory
            }
        }
        $stmt_p->close();

        // C. Delete from 'student' table first (Child relation row)
        $sql1 = "DELETE FROM student WHERE user_id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $uid);
        $stmt1->execute();
        $stmt1->close();

        // D. Delete from 'user' table (Parent authentication record row)
        $sql2 = "DELETE FROM user WHERE user_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $uid);
        $stmt2->execute();
        $stmt2->close();

        // Commit database changes together cleanly
        $conn->commit();
        
        // Success alert feedback
        echo "<script>
                alert('User account and all associated profile registry data deleted successfully.');
                window.location.href='membership.php';
              </script>";
        exit();

    } catch (Exception $e) {
        // Rollback transaction to protect database structure if errors occur
        $conn->rollback();
        echo "<script>
                alert('System Error deleting user record: " . addslashes($conn->error) . "');
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