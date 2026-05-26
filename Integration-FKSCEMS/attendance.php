<?php
session_start();

include ('session.php');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; // Ensure this matches your registration/login DB name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$user_id = $_SESSION['SESS_USER_ID'];

$sql_events = "
SELECT 
    e.event_id,
    e.event_title,
    e.event_date,
    e.event_time,
    e.venue,
    er.registration_id
FROM student s
INNER JOIN eventregistration er
ON s.matric_number = er.matric_number
INNER JOIN event e
ON er.event_id = e.event_id
WHERE s.user_id = ?
ORDER BY e.event_date DESC
";

$stmt_events = $conn->prepare($sql_events);
$stmt_events->bind_param("i", $user_id);
$stmt_events->execute();
$result_events = $stmt_events->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    date_default_timezone_set(
        'Asia/Kuala_Lumpur'
    );

    $registration_id =
        $_POST['registration_id'];

    /* =========================================
       GET EVENT TIME
    ========================================= */

    $sql_event = "

    SELECT 

        e.event_date,
        e.event_time

    FROM eventregistration er

    INNER JOIN event e
    ON er.event_id = e.event_id

    WHERE er.registration_id = ?

    LIMIT 1

    ";

    $stmt_event = $conn->prepare($sql_event);

    $stmt_event->bind_param(
        "i",
        $registration_id
    );

    $stmt_event->execute();

    $result_event =
        $stmt_event->get_result();

    $event_data =
        $result_event->fetch_assoc();

    /* =========================================
       CURRENT TIME
    ========================================= */

    $current_time = time();

    $event_start = strtotime(

        $event_data['event_date']
        . ' ' .
        $event_data['event_time']

    );

    /* =========================================
       LATE AFTER 20 MINUTES
    ========================================= */

    $late_limit =
        $event_start + (20 * 60);

    $attendance_status =
    $_POST['attendance_status'];

    /* =========================================
    IF ABSENT
    ========================================= */

    if ($attendance_status == 'Absent') {

        $point_awarded = 0;
    }

    /* =========================================
    IF PRESENT
    ========================================= */

    else {

        if ($current_time <= $late_limit) {

            $attendance_status = 'Present';

            $point_awarded = 10;

        } else {

            $attendance_status = 'Late';

            $point_awarded = 5;
        }
    }

    /* =========================================
       CHECK IN TIME
    ========================================= */

    $check_in_time =
        date('Y-m-d H:i:s');

    /* =========================================
       PREVENT DUPLICATE
    ========================================= */

    $sql_duplicate = "

    SELECT attendance_id

    FROM attendance

    WHERE registration_id = ?

    ";

    $stmt_duplicate =
        $conn->prepare($sql_duplicate);

    $stmt_duplicate->bind_param(
        "i",
        $registration_id
    );

    $stmt_duplicate->execute();

    $duplicate_result =
        $stmt_duplicate->get_result();

    if ($duplicate_result->num_rows == 0) {

        $check_in_time = date('Y-m-d H:i:s');

        $sql_insert = "

        INSERT INTO attendance (

            registration_id,
            attendance_status,
            point_awarded,
            check_in_time

        )

        VALUES (?, ?, ?, ?)

        ";

        $stmt_insert =
            $conn->prepare($sql_insert);

        $stmt_insert->bind_param(

            "isis",

            $registration_id,
            $attendance_status,
            $point_awarded,
            $check_in_time
        );

        $stmt_insert->execute();
    }

    header("Location: attendance.php?success=1");

    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance</title>
    <link rel="stylesheet" href="attendance.css">
</head>
<body>
    <?php include ('studentHeader.php') ?>

    <div class="page-container">
        <table class="member-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Venue</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                <?php while($event = $result_events->fetch_assoc()) : ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($event['event_title']); ?>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($event['event_date'])); ?>
                    </td>
                    <td>
                        <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($event['venue']); ?>
                    </td>
                    <td>

                    <?php

                    $sql_check = "

                    SELECT 

                        attendance_id,
                        attendance_status,
                        check_in_time

                    FROM attendance

                    WHERE registration_id = ?

                    LIMIT 1

                    ";

                    $stmt_check = $conn->prepare($sql_check);

                    $stmt_check->bind_param(
                        "i",
                        $event['registration_id']
                    );

                    $stmt_check->execute();

                    $result_check = $stmt_check->get_result();

                    ?>

                    <?php if($result_check->num_rows > 0) : ?>

                        <?php

                        $attendance_data =
                            $result_check->fetch_assoc();

                        $status =
                            $attendance_data['attendance_status'];

                        ?>

                        <div class="attendance-result">

                            <!-- STATUS -->

                            <?php if($status == 'Present') : ?>

                                <span class="completed-badge present">

                                    ✓ Present

                                </span>

                            <?php else : ?>

                                <span class="completed-badge absent">

                                    ✗ Absent

                                </span>

                            <?php endif; ?>

                            <!-- CHECK IN TIME -->

                            <small class="checkin-time">

                                Checked in:
                                <?php

                                echo date(
                                    'd M Y h:i A',
                                    strtotime($attendance_data['check_in_time'])
                                );

                                ?>

                            </small>

                        </div>

                    <?php else : ?>

                        <button
                            type="button"
                            class="action-btn edit"
                            onclick="openProofModal(<?php echo $event['registration_id']; ?>)"
                        >

                            Attendance

                        </button>

                    <?php endif; ?>

                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div id="proofModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Submit Attendance Proof</h3>
                <button
                    class="close-btn"
                    onclick="closeProofModal()"
                >
                    &times;
                </button>
            </div>
            <form
                method="POST"
                action=""
                enctype="multipart/form-data"
            >
                <input
                    type="hidden"
                    name="registration_id"
                    id="registrationIdInput"
                >
                <div class="mb-3">
                    <label class="modal-label">
                        Upload Proof/Reason (If Absent)
                    </label>
                    <input
                        type="file"
                        name="proof_file"
                        class="modal-input"
                        required
                    >
                </div>
                <div class="mb-3">
                    <label class="modal-label">
                        Attendance Status
                    </label>
                    <select
                        name="attendance_status"
                        class="modal-input"
                    >

                        <option value="Present">
                            Present
                        </option>

                        <option value="Absent">
                            Absent
                        </option>

                    </select>
                </div>
                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-btn"
                        onclick="closeProofModal()"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="submit-btn"
                    >
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function openProofModal(registrationId) {
        document.getElementById('proofModal')
            .style.display = 'flex';

        document.getElementById('registrationIdInput')
            .value = registrationId;
    }
    function closeProofModal() {
        document.getElementById('proofModal')
            .style.display = 'none';
    }
    </script>
</body>
</html>