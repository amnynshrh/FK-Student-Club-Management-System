<?php
// 1. SESSION INITIALIZATION
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "webTestDB";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. PROCESS FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = trim($_POST['username']);
    $input_pass = $_POST['password']; 
    $input_role = $_POST['role']; // The role selected in the login form dropdown

    // CHANGED: Search ONLY by username so we can fetch Committee accounts even when they select "Student"
    $sql = "SELECT user_id, username, password, role FROM user WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $input_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $db_role = $user['role']; // The actual role saved in your database user table

        // Verify the plain text password against the securely encrypted database hash
        if (password_verify($input_pass, $user['password'])) {
            
            // 3. ROLE VALIDATION LOGIC (With dual-role exception for Committee)
            $access_granted = false;

            if ($input_role === $db_role) {
                // Scenario A: Exact match (Student logs in as Student, Committee logs in as Committee, etc.)
                $access_granted = true;
            } elseif ($db_role === 'Committee' && $input_role === 'Student') {
                // Scenario B: Dual-role authorization bypass
                // Database says they are a Committee member, but they selected "Student" in the form.
                $access_granted = true;
                
                // Override the role variable so they get treated as a Student for this session
                $db_role = 'Student'; 
            }

            if ($access_granted) {
                // Credentials match and role is authorized! Save tracking keys to Session
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $db_role; // Stores 'Student' if the bypass was triggered

                // Role-based redirection based on the authorized session role
                if ($db_role === 'Admin') {
                    header("Location: admin.php");
                } elseif ($db_role === 'Committee') {
                    header("Location: committeeDashboard.php");
                } else {
                    // Real students and Committee members logging in as students land here
                    header("Location: studentDashboard.php");
                }
                exit();
            } else {
                // Role mismatch handler (e.g., a real Student account selecting "Admin" or "Committee")
                header("Location: login.php?error=1");
                exit();
            }

        } else {
            // Password didn't match hash
            header("Location: login.php?error=1");
            exit();
        }
    } else {
        // No user found matching that username
        header("Location: login.php?error=1");
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>