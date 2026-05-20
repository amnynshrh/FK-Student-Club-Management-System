<?php
$conn = new mysqli("localhost", "root", "", "fk_scems_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize standard string input data fields
    $clubName    = $_POST['clubName'];
    $description = $_POST['clubDescription'];
    $advisorName = $_POST['advisorName'];
    $status      = "Active"; 

    $memberNames  = $_POST['member_names'] ?? [];
    $memberMatric = $_POST['member_matricnum'] ?? []; 
    $memberRoles  = $_POST['member_roles'] ?? [];
    
    // Insert into club table
    $stmt = $conn->prepare("INSERT INTO club (club_name, description, advisor_name, club_status) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $clubName, $description, $advisorName, $status);
    $stmt->execute();
    
    // Capture the newly generated club_id for the relationships
    $club_id = $conn->insert_id; 
    $stmt->close();

    // ==========================================
    // START: COMMITTEES & MEMBERSHIPS HANDLER
    // ==========================================
    
    // Verify that the given matric number exists in the student directory registry
    $stmt_user = $conn->prepare("SELECT user_id FROM student WHERE matric_number = ?");

    // FIXED: Changed first parameter placeholder data matching logic to match strings ($matric_num)
    $stmt_check_member = $conn->prepare("SELECT membership_id FROM membership WHERE matric_number = ? AND club_id = ?");

    // Insert student into membership table
    $stmt_add_member = $conn->prepare("INSERT INTO membership (matric_number, club_id, membership_status, join_date) VALUES (?, ?, 'Active', NOW())");

    // Insert student into committee table
    $stmt_add_committee = $conn->prepare("INSERT INTO committee (membership_id, club_id, position, assigned_date) VALUES (?, ?, ?, NOW())");

    // Loop through the array of submitted matric numbers
    foreach ($memberMatric as $index => $matric_num) {
        
        $matric_num = trim($matric_num);
        // Skip empty input lines
        if (empty($matric_num)) {
            continue;
        }
        
        // Grab the corresponding role or set a default
        $role = !empty($memberRoles[$index]) ? trim($memberRoles[$index]) : 'Committee Member';

        // STEP A: Fetch the student record to confirm validation tracking existence
        $stmt_user->bind_param("s", $matric_num);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();

        if ($row_user = $result_user->fetch_assoc()) {
            // Student is valid! Now look up if they already hold membership parameters inside this specific club
            
            // STEP B: FIXED bind data parameter map (Changed $user_id to string $matric_num to match the query pattern)
            $stmt_check_member->bind_param("si", $matric_num, $club_id);
            $stmt_check_member->execute();
            $result_member = $stmt_check_member->get_result();

            if ($row_member = $result_member->fetch_assoc()) {
                // If they are already a member, grab the existing membership ID
                $membership_id = $row_member['membership_id'];
            } else {
                // If they are not a member, add them to membership first safely
                $stmt_add_member->bind_param("si", $matric_num, $club_id);
                $stmt_add_member->execute();
                $membership_id = $stmt_add_member->insert_id;
            }

            // STEP C: Assign them as a committee member securely using the verified membership record reference key
            if ($membership_id > 0) {
                $stmt_add_committee->bind_param("iis", $membership_id, $club_id, $role);
                $stmt_add_committee->execute();
            }
        } else {
            // Optional: Handle invalid or untracked matric numbers entered by form users
            // error_log("Matric number " . $matric_num . " does not exist in student system.");
        }
    }

    // Close the committee prepared statements
    $stmt_user->close();
    $stmt_check_member->close();
    $stmt_add_member->close();
    $stmt_add_committee->close();

    // ==========================================
    // END: COMMITTEES & MEMBERSHIPS HANDLER
    // ==========================================

    // Output Success View Dashboard UI
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Club Created Successfully</title>
        <link rel="stylesheet" href="style.css">
        <style>
            .modal-overlay {
                display: flex !important; 
            }
        </style>
    </head>
    <body>

        <div id="successModal" class="modal-overlay">
            <div class="modal-box alert-box">
                <div class="success-icon">&#10004;</div>
                <div class="modal-header success-title">Success!</div>
                <div class="modal-body text-center">
                    The club <strong><?php echo htmlspecialchars($clubName); ?></strong> has been successfully created and added to the faculty tracking database.
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn-submit" onclick="redirectToForm()">Dismiss</button>
                </div>
            </div>
        </div>

        <script>
            function redirectToForm() {
                window.location.href = 'create-club.html';
            }
        </script>
    </body>
    </html>
    <?php
} else {
    echo "Invalid Access Request Method.";
}
?>