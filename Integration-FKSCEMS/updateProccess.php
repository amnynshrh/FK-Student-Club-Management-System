<?php
// 1. SESSION INITIALIZATION
session_start();

// 2. SECURITY: Check if user is logged in (Standardized to match login.php variables)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. DATABASE CONNECTION: Fixed to match your exact project database name
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// 4. PROCESS SUBMISSION FORM
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $contact = trim($_POST['contactNo']); // Matches name attribute from editProfile.php form

    $conn->begin_transaction();

    try {
        // 1. Update USER table
        $sql1 = "UPDATE user SET email = ?, contact_no = ? WHERE user_id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("ssi", $email, $contact, $user_id);
        $stmt1->execute();

        // 2. Update STUDENT table (Handles profile photo file uploads)
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

        // 3. Determine Redirect Route using standard session roles
        // Safely routes to either committeeDashboard.php or studentDashboard.php
        $goto = (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Committee') ? "committeeDashboard.php" : "studentDashboard.php";

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