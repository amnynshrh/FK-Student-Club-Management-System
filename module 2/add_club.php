<?php
$conn = new mysqli("localhost", "root", "", "fk_scems_db");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize standard string input data fields
    $clubName    = $_POST['clubName'];
    $description = $_POST['clubDescription'];
    $advisorName = $_POST['advisorName'];
    $status      = "Active"; 

    $memberNames  = $_POST['member_names'] ?? [];
    $memberMatric = $_POST['member_matricnum'] ?? []; // FIXED: Changed variable name so it doesn't overwrite $memberNames
    $memberRoles  = $_POST['member_roles'] ?? [];
    
    // (Your database insertion queries execute here securely)
    $stmt = $conn->prepare("INSERT INTO club (club_name, description, advisor_name, club_status) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $clubName, $description, $advisorName, $status);
    $stmt->execute();
    
    // Capture the newly generated club_id for the relationships
    $club_id = $conn->insert_id; 
    $stmt->close();


    // ==========================================
    // START: COMMITTEES & MEMBERSHIPS HANDLER
    // ==========================================
    
    // 1. Prepare statements outside the loop for security and efficiency
    // Find the user_id tied to the given matric number
    $stmt_user = $conn->prepare("SELECT user_id FROM student WHERE matric_number = ?");

    // Check if membership already exists for this student in this club
    $stmt_check_member = $conn->prepare("SELECT membership_id FROM membership WHERE matric_number = ? AND club_id = ?");

    // Insert student into membership table (Required constraint before becoming a committee member)
    $stmt_add_member = $conn->prepare("INSERT INTO membership (matric_number, club_id, membership_status, join_date) VALUES (?, ?, 'Active', NOW())");

    // Insert student into committee table
    $stmt_add_committee = $conn->prepare("INSERT INTO committee (membership_id, club_id, position, assigned_date) VALUES (?, ?, ?, NOW())");

    // 2. Loop through the array of submitted matric numbers
    foreach ($memberMatric as $index => $matric_num) {
        
        // Skip empty input lines
        if (empty(trim($matric_num))) {
            continue;
        }
        
        // Grab the corresponding role or set a default
        $role = !empty($memberRoles[$index]) ? $memberRoles[$index] : 'Committee Member';

        // STEP A: Fetch the user_id from student table
        $stmt_user->bind_param("s", $matric_num);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();

        if ($row_user = $result_user->fetch_assoc()) {
            $user_id = $row_user['user_id'];

            // STEP B: Handle the membership constraint
            $stmt_check_member->bind_param("ii", $user_id, $club_id);
            $stmt_check_member->execute();
            $result_member = $stmt_check_member->get_result();

            if ($row_member = $result_member->fetch_assoc()) {
                // If they are already a member, grab the existing membership ID
                $membership_id = $row_member['membership_id'];
            } else {
                // If they are not a member, add them to membership first
                $stmt_add_member->bind_param("si", $matric_num, $club_id);
                $stmt_add_member->execute();
                $membership_id = $stmt_add_member->insert_id;
            }

            // STEP C: Assign them as a committee member
            $stmt_add_committee->bind_param("iis", $membership_id, $club_id, $role);
            $stmt_add_committee->execute();
        } else {
            // Optional: Log or handle cases where an entered matric number doesn't exist in system
            // error_log("Matric number " . $matric_num . " does not exist in the system.");
        }
    }

    // 3. Close the committee prepared statements
    $stmt_user->close();
    $stmt_check_member->close();
    $stmt_add_member->close();
    $stmt_add_committee->close();

    // ==========================================
    // END: COMMITTEES & MEMBERSHIPS HANDLER
    // ==========================================


    // 2. Output the Success Page View with the embedded popup modal element directly
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Club Created Successfully</title>
        <!-- Link back to your central external stylesheet structure -->
        <link rel="stylesheet" href="style.css">
        <style>
            /* Specific overrides to force display state on load for Option B */
            .modal-overlay {
                display: flex !important; 
            }
        </style>
    </head>
    <body>

        <!-- The Success Popup Modal Box Content Container Layout -->
        <div id="successModal" class="modal-overlay">
            <div class="modal-box alert-box">
                <div class="success-icon">&#10004;</div>
                <div class="modal-header success-title">Success!</div>
                <div class="modal-body text-center">
                    The club <strong><?php echo $clubName; ?></strong> has been successfully created and added to the faculty tracking database.
                </div>
                <div class="modal-footer flex-center">
                    <!-- Redirect back to form window upon interaction closure -->
                    <button type="button" class="btn-submit" onclick="redirectToForm()">Dismiss</button>
                </div>
            </div>
        </div>

        <script>
            // Navigation routing helper function execution handler
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