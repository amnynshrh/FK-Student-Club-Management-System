<?php
// 1. SESSION INITIALIZATION
session_start();
setcookie("session_timeout", "active", time() + 300, "/");

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. PROCESS FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = trim($_POST['username']);
    $input_pass = $_POST['password']; 
    
    // Enforce lowercase to match your database ENUM constraints securely
    $input_role = strtolower(trim($_POST['role'])); 

    // Search ONLY by username so we can fetch Committee accounts even when they select "Student"
    $sql = "SELECT user_id, username, password, role FROM user WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $input_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Convert the database value to lowercase to protect against casing conflicts
        $db_role = strtolower($user['role']); 

        // Verify the plain text password against the securely encrypted database hash
        if (password_verify($input_pass, $user['password'])) {
        // if ($input_pass = $input_pass) {
            
            // 3. ROLE VALIDATION LOGIC (FIXED CASE MATCHING)
            $access_granted = false;

            if ($input_role === $db_role) {
                // Scenario A: Exact match (student logs in as student, committee logs in as committee, etc.)
                $access_granted = true;
            } elseif ($db_role === 'committee' && $input_role === 'student') {
                // Scenario B: Dual-role authorization bypass
                // Database says they are a committee member, but they selected "student" in the form.
                $access_granted = true;
                
                // Override the variable so they get treated as a student for this session
                $db_role = 'student'; 
            }

            if ($access_granted) {
                // Credentials match and role is authorized! Save tracking keys to Session
                $_SESSION['Login']  = 'YES';
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT matric_number FROM student WHERE user_id = ?"
                );
                
                mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
                mysqli_stmt_execute($stmt);
                
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                
                $_SESSION['matric'] = $row['matric_number'] ?? null;
                
                // Capitalize the first letter (e.g. 'Student', 'Committee') so it satisfies your Dashboard check expectations
                $_SESSION['role']     = ucfirst($db_role);

                // Role-based redirection based on the authorized lowercase context
                if ($db_role === 'admin') {
                    header("Location: admin.php");
                } elseif ($db_role === 'committee') {
                    $comm_sql = "
                        SELECT c.committee_id
                        FROM committee c
                        INNER JOIN membership m ON c.membership_id = m.membership_id
                        INNER JOIN student s ON m.matric_number = s.matric_number
                        WHERE s.user_id = ?
                        LIMIT 1
                    ";
                    $comm_stmt = $conn->prepare($comm_sql);
                    if ($comm_stmt) {
                        $comm_stmt->bind_param("i", $_SESSION['user_id']);
                        $comm_stmt->execute();
                        $comm_res = $comm_stmt->get_result();
                        if ($comm_row = $comm_res->fetch_assoc()) {
                            $_SESSION['SESS_COMMITTEE_ID'] = (int) $comm_row['committee_id'];
                        }
                        $comm_stmt->close();
                    }
                    header("Location: committeeDashboard.php");
                } else {
                    // Real students and Committee members logging in as students land here
                    header("Location: studentDashboard.php");
                }
                exit();
            } else {
                // Role mismatch handler (e.g., a real Student account selecting "Admin" or "Committee")
                header("Location: login.php?error=2");
                exit();
            }

        } else {
            // Password didn't match hash
            header("Location: login.php?error=1");
            exit();
        }
    } else {
        // No user found matching that username
        header("Location: login.php?error=3");
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>