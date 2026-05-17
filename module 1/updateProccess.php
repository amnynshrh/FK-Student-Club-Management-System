<?php
session_start();

if (!isset($_SESSION['SESS_USER_ID'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "fk_scems_db");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['SESS_USER_ID'];
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $contact = trim($_POST['contactNo']);

    $conn->begin_transaction();

    try {
        // 1. Update USER table
        $sql1 = "UPDATE user SET email = ?, contact_no = ? WHERE user_id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("ssi", $email, $contact, $user_id);
        $stmt1->execute();

        // 2. Update STUDENT table
        if (!empty($_FILES["profilePhoto"]["name"])) {
            $target_dir = "uploads/";
            $file_ext = pathinfo($_FILES["profilePhoto"]["name"], PATHINFO_EXTENSION);
            $file_name = $user_id . "_" . time() . "." . $file_ext;
            
            if (move_uploaded_file($_FILES["profilePhoto"]["tmp_name"], $target_dir . $file_name)) {
                $sql2 = "UPDATE student SET name = ?, profile_photo = ? WHERE user_id = ?";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("ssi", $name, $file_name, $user_id);
            } else {
                throw new Exception("File upload failed.");
            }
        } else {
            $sql2 = "UPDATE student SET name = ? WHERE user_id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("si", $name, $user_id);
        }
        $stmt2->execute();

        $conn->commit();

        // 3. Determine Redirect
        $goto = ($_SESSION['SESS_ROLE'] == 'Committee') ? "committeeDashboard.php" : "studentDashboard.php";

        echo "<script>
                alert('Profile updated successfully!');
                window.location.href='$goto';
              </script>";

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
    }
}
$conn->close();
?>