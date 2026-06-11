<?php
session_start();

include ('session.php');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; // Ensure this matches your registration/login DB name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$user_id = $_SESSION['user_id'];

function attendance_points_for_status($status)
{
    if ($status === 'present') {
        return 10;
    }
    if ($status === 'late') {
        return 5;
    }
    return 0;
}

function attendance_event_end_datetime($eventDate, $startTime, $endTime)
{
    $start = new DateTime($eventDate . ' ' . $startTime);
    $end = new DateTime($eventDate . ' ' . $endTime);
    if ($end <= $start) {
        $end->modify('+1 day');
    }
    return $end;
}

function mark_qr_attendance($conn, $userId, $eventId)
{
    date_default_timezone_set('Asia/Kuala_Lumpur');

    $sql = "
        SELECT
            er.registration_id,
            e.event_title,
            e.event_date,
            e.event_time,
            e.end_time,
            a.attendance_id,
            a.attendance_status
        FROM student s
        INNER JOIN eventregistration er
        ON s.matric_number = er.matric_number
        INNER JOIN event e
        ON er.event_id = e.event_id
        LEFT JOIN attendance a
        ON er.registration_id = a.registration_id
        WHERE s.user_id = ?
          AND e.event_id = ?
          AND er.registration_status = 'registered'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        return ["success" => false, "message" => "You are not registered for this event."];
    }

    $existingStatus = strtolower((string) ($data["attendance_status"] ?? ""));
    if (in_array($existingStatus, ["present", "late"], true)) {
        return ["success" => true, "message" => "Attendance already recorded as " . ucfirst($existingStatus) . "."];
    }

    $now = new DateTime();
    $start = new DateTime($data["event_date"] . " " . $data["event_time"]);
    $end = attendance_event_end_datetime($data["event_date"], $data["event_time"], $data["end_time"]);

    if ($now < $start) {
        return ["success" => false, "message" => "Attendance scanning opens when the event starts."];
    }

    if ($now > $end) {
        return ["success" => false, "message" => "Attendance scanning has closed for this event."];
    }

    $lateLimit = clone $start;
    $lateLimit->modify('+20 minutes');
    $status = $now <= $lateLimit ? 'present' : 'late';
    $points = attendance_points_for_status($status);
    $checkInTime = $now->format('Y-m-d H:i:s');
    $registrationId = (int) $data["registration_id"];

    if (!empty($data["attendance_id"])) {
        $update = $conn->prepare("
            UPDATE attendance
            SET attendance_status = ?, point_awarded = ?, check_in_time = ?
            WHERE registration_id = ?
        ");
        $update->bind_param("sisi", $status, $points, $checkInTime, $registrationId);
        $update->execute();
    } else {
        $insert = $conn->prepare("
            INSERT INTO attendance (registration_id, attendance_status, point_awarded, check_in_time)
            VALUES (?, ?, ?, ?)
        ");
        $insert->bind_param("isis", $registrationId, $status, $points, $checkInTime);
        $insert->execute();
    }

    return ["success" => true, "message" => "Attendance recorded as " . ucfirst($status) . " for " . $data["event_title"] . "."];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'qr_checkin') {
    header('Content-Type: application/json');
    $eventId = (int) ($_POST['event_id'] ?? 0);

    if ($eventId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid QR code."]);
        exit;
    }

    echo json_encode(mark_qr_attendance($conn, $user_id, $eventId));
    exit;
}

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
    strtolower((string) $_POST['attendance_status']);

    /* =========================================
    IF ABSENT
    ========================================= */

    if ($attendance_status == 'absent') {

        $point_awarded = 0;
    }

    /* =========================================
    IF PRESENT
    ========================================= */

    else {

        if ($current_time <= $late_limit) {

            $attendance_status = 'present';

            $point_awarded = 10;

        } else {

            $attendance_status = 'late';

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
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <?php include ('studentHeader.php') ?>

    <div class="page-container">
        <div class="qr-scanner-card">
            <div class="qr-scanner-copy">
                <h2>Scan Event QR</h2>
                <p>Allow camera access and scan the event QR code shown by the committee.</p>
            </div>
            <div id="reader"></div>
            <div class="scanner-result" id="scannerResult">
                No QR scanned yet.
            </div>
        </div>
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

                        <option value="present">
                            Present
                        </option>

                        <option value="absent">
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

    function setScannerResult(message, isSuccess) {
        const resultBox = document.getElementById('scannerResult');
        if (!resultBox) {
            return;
        }
        resultBox.textContent = message;
        resultBox.classList.toggle('success', Boolean(isSuccess));
        resultBox.classList.toggle('error', !isSuccess);
    }

    function extractEventId(decodedText) {
        const rawText = String(decodedText || '').trim();

        if (/^\d+$/.test(rawText)) {
            return rawText;
        }

        const legacyMatch = rawText.match(/^event-attendance:(\d+)/);
        if (legacyMatch) {
            return legacyMatch[1];
        }

        try {
            const scannedUrl = new URL(rawText);
            return scannedUrl.searchParams.get('event_id');
        } catch (error) {
            return null;
        }
    }

    function submitQrAttendance(eventId) {
        return fetch('attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'qr_checkin',
                event_id: eventId
            })
        }).then(function (response) {
            return response.json();
        });
    }

    function startQrScanner() {
        const reader = document.getElementById('reader');
        if (!reader || typeof Html5QrcodeScanner === 'undefined') {
            setScannerResult('QR scanner library could not be loaded.', false);
            return;
        }

        const scanner = new Html5QrcodeScanner('reader', {
            fps: 10,
            qrbox: 250
        });

        scanner.render(function (decodedText) {
            const eventId = extractEventId(decodedText);
            if (!eventId) {
                setScannerResult('Invalid event QR code.', false);
                return;
            }

            setScannerResult('QR scanned. Updating attendance...', true);
            scanner.clear();

            submitQrAttendance(eventId)
                .then(function (result) {
                    setScannerResult(result.message || 'Attendance updated.', Boolean(result.success));
                    if (result.success) {
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                    }
                })
                .catch(function () {
                    setScannerResult('Unable to update attendance. Please try again.', false);
                });
        }, function () {});
    }

    startQrScanner();
    </script>
</body>
</html>


