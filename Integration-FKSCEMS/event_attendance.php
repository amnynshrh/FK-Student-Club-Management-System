<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

$eventId = (int) ($_POST["event_id"] ?? $_GET["event_id"] ?? 0);
$search = trim($_GET["search"] ?? "");
$statusFilter = strtolower(trim($_GET["status"] ?? ""));
$validStatus = ["all", "present", "absent", "late"];
if (!in_array($statusFilter, $validStatus, true)) {
    $statusFilter = "all";
}

$event = null;
$attendees = [];
$message = "";
$messageType = "success";

mysqli_query($conn, "ALTER TABLE attendance MODIFY point_awarded TINYINT DEFAULT 0");
mysqli_query($conn, "
    INSERT INTO attendance (registration_id, attendance_status, check_in_time, point_awarded)
    SELECT
        er.registration_id,
        'absent',
        NULL,
        -10
    FROM eventregistration er
    INNER JOIN event e ON e.event_id = er.event_id
    LEFT JOIN attendance a ON a.registration_id = er.registration_id
    WHERE er.registration_status = 'registered'
      AND a.attendance_id IS NULL
      AND NOW() > (
          CASE
              WHEN e.end_time <= e.event_time
              THEN DATE_ADD(CONCAT(e.event_date, ' ', e.end_time), INTERVAL 1 DAY)
              ELSE CONCAT(e.event_date, ' ', e.end_time)
          END
      )
");

if ($eventId > 0) {
    $eventSql = "
        SELECT
            event_id,
            event_title,
            event_date,
            event_time,
            end_time,
            venue,
            event_status,
            qr_code
        FROM event
        WHERE event_id = ?
        LIMIT 1
    ";
    $eventStmt = mysqli_prepare($conn, $eventSql);
    if ($eventStmt) {
        mysqli_stmt_bind_param($eventStmt, "i", $eventId);
        mysqli_stmt_execute($eventStmt);
        $eventResult = mysqli_stmt_get_result($eventStmt);
        $event = mysqli_fetch_assoc($eventResult) ?: null;
        mysqli_stmt_close($eventStmt);
    }
}

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!function_exists('attendancePointsForStatus')) {
    function attendancePointsForStatus($status) {
        $status = strtolower(trim((string) $status));
        if ($status === 'present') {
            return 10;
        }
        if ($status === 'late') {
            return 5;
        }
        if ($status === 'absent') {
            return -10;
        }
        return 0;
    }
}

if (!function_exists('attendancePointsClass')) {
    function attendancePointsClass($points) {
        $points = (int) $points;
        if ($points > 0) {
            return "badge bg-success-subtle text-success";
        } elseif ($points < 0) {
            return "badge bg-danger-subtle text-danger";
        }
        return "badge bg-secondary-subtle text-secondary";
    }
}

if (!function_exists('formatAttendancePoints')) {
    function formatAttendancePoints($points) {
        $points = (int) $points;
        if ($points > 0) {
            return "+" . $points;
        }
        return (string) $points;
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && $eventId > 0) {
    $attendanceInput = $_POST["attendance"] ?? [];

    $selectSql = "
        SELECT attendance_id, attendance_status, check_in_time
        FROM attendance
        WHERE registration_id = ?
        LIMIT 1
    ";

    $insertSql = "
        INSERT INTO attendance (
            registration_id,
            attendance_status,
            check_in_time,
            point_awarded
        )
        VALUES (?, ?, ?, ?)
    ";

    $updateSql = "
        UPDATE attendance
        SET
            attendance_status = ?,
            check_in_time = ?,
            point_awarded = ?
        WHERE registration_id = ?
    ";

    $selectStmt = mysqli_prepare($conn, $selectSql);
    $insertStmt = mysqli_prepare($conn, $insertSql);
    $updateStmt = mysqli_prepare($conn, $updateSql);

    if ($selectStmt && $insertStmt && $updateStmt) {

        mysqli_begin_transaction($conn);

        try {

            foreach ($attendanceInput as $registrationId => $status) {

                $registrationId = (int) $registrationId;
                $normalizedStatus = strtolower(trim((string) $status));

                if (!in_array($normalizedStatus, ["present", "absent", "late"], true)) {
                    continue;
                }

                $points = attendancePointsForStatus($normalizedStatus);

                // Check existing attendance
                mysqli_stmt_bind_param($selectStmt, "i", $registrationId);
                mysqli_stmt_execute($selectStmt);

                $existingResult = mysqli_stmt_get_result($selectStmt);
                $existingAttendance = mysqli_fetch_assoc($existingResult);

                if ($existingAttendance) {

                    $oldStatus = strtolower($existingAttendance["attendance_status"]);

                    // Only update check-in time if status changed
                    if ($oldStatus !== $normalizedStatus) {
                        $checkInTime = $normalizedStatus === "absent"
                            ? null
                            : date("Y-m-d H:i:s");
                    } else {
                        $checkInTime = $existingAttendance["check_in_time"];
                    }

                    mysqli_stmt_bind_param(
                        $updateStmt,
                        "ssii",
                        $normalizedStatus,
                        $checkInTime,
                        $points,
                        $registrationId
                    );

                    if (!mysqli_stmt_execute($updateStmt)) {
                        throw new RuntimeException(mysqli_stmt_error($updateStmt));
                    }

                } else {

                    // New attendance record
                    $checkInTime = $normalizedStatus === "absent"
                        ? null
                        : date("Y-m-d H:i:s");

                    mysqli_stmt_bind_param(
                        $insertStmt,
                        "issi",
                        $registrationId,
                        $normalizedStatus,
                        $checkInTime,
                        $points
                    );

                    if (!mysqli_stmt_execute($insertStmt)) {
                        throw new RuntimeException(mysqli_stmt_error($insertStmt));
                    }
                }
            }

            mysqli_commit($conn);

            $message = "Attendance has been saved successfully.";
            $messageType = "success";

        } catch (Throwable $th) {

            mysqli_rollback($conn);

            $message = "Failed to save attendance. Please try again.";
            $messageType = "danger";
        }

    } else {

        $message = "Unable to prepare attendance update query.";
        $messageType = "danger";
    }

    if ($selectStmt) mysqli_stmt_close($selectStmt);
    if ($insertStmt) mysqli_stmt_close($insertStmt);
    if ($updateStmt) mysqli_stmt_close($updateStmt);
}

if ($event) {
    $attendanceSql = "
        SELECT
            er.registration_id,
            s.matric_number,
            s.name,
            COALESCE(a.attendance_status, 'absent') AS attendance_status,
            a.check_in_time,
            a.point_awarded
        FROM eventregistration er
        INNER JOIN student s ON s.matric_number = er.matric_number
        LEFT JOIN attendance a ON a.registration_id = er.registration_id
        WHERE er.event_id = ?
          AND er.registration_status = 'registered'
          AND (
                ? = ''
                OR s.name LIKE CONCAT('%', ?, '%')
                OR s.matric_number LIKE CONCAT('%', ?, '%')
              )
          AND (
                ? = 'all'
                OR COALESCE(a.attendance_status, 'absent') = ?
              )
        ORDER BY s.name ASC
    ";
    $attendanceStmt = mysqli_prepare($conn, $attendanceSql);

    if ($attendanceStmt) {
        mysqli_stmt_bind_param($attendanceStmt, "isssss", $eventId, $search, $search, $search, $statusFilter, $statusFilter);
        mysqli_stmt_execute($attendanceStmt);
        $attendanceResult = mysqli_stmt_get_result($attendanceStmt);
        while ($row = mysqli_fetch_assoc($attendanceResult)) {
            $attendees[] = $row;
        }
        mysqli_stmt_close($attendanceStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance - Committee</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="committee.css">

    <style>
        body {
            background-color: #ffffff;
        }

        .page-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .event-name {
            font-size: 2rem;
            font-weight: 700;
            color: #1c3f95;
            margin-bottom: 1rem;
        }

        .event-meta {
            font-size: 1rem;
            color: #495057;
            margin-bottom: .5rem;
        }

        .event-meta strong {
            color: #212529;
        }

        .card {
            border-radius: 12px;
        }

        .qr-card {
            position: sticky;
            top: 100px;
        }

        .qr-preview {
            width: 100%;
            max-width: 250px;
            height: auto;
            margin: auto;
            display: block;
            border: 1px solid #eeeff2;
            border-radius: 10px;
            padding: .5rem;
            background: #f8fafc;
        }

        .search-row {
            row-gap: 1rem;
        }

        .attendance-table-scroll {
            max-height: 600px;
            overflow-y: auto;
        }

        .attendance-table-scroll thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
        }

        .status-choice {
            min-width: 90px;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .btn-umpsa-teal {
            background-color: #009e96;
            color: white;
            transition: 0.2s;
        }

        .btn-umpsa-teal:hover {
            background-color: #1c3f95;
            color: white;
        }

        .qr-modal-image {
            max-width: 90%;
            max-height: 80vh;
            object-fit: contain;
            border: 10px solid #fff;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .attendance-points {
            padding: 0.25em 0.6em;
            font-size: 0.85em;
            font-weight: 600;
            border-radius: 4px;
        }

        .fs-7 {
            font-size: 0.85rem;
        }

        @media (max-width: 991px) {
            .qr-card {
                position: static;
            }
        }
    </style>
</head>

<body>

    <?php include('committeeHeader.php') ?>

    <main class="page-container">
        <?php if (!$event): ?>
            <div class="alert alert-warning mt-4">
                Invalid or missing event. Please go back to Manage Attendance and select an event.
            </div>
        <?php else: ?>
            <?php
            $eventDateRaw = (string) ($event["event_date"] ?? "");
            $eventDateFormatted = $eventDateRaw;
            if ($eventDateRaw !== "") {
                $eventDateTs = strtotime($eventDateRaw);
                if ($eventDateTs !== false) {
                    $eventDateFormatted = date("d-m-Y", $eventDateTs);
                }
            }
            $startTs = strtotime((string) $event["event_time"]);
            $endTs = strtotime((string) $event["end_time"]);
            $startFmt = $startTs ? date("g:i A", $startTs) : (string) $event["event_time"];
            $endFmt = $endTs ? date("g:i A", $endTs) : (string) $event["end_time"];
            ?>
            <div class="row g-4">
                <!-- Left Column: Back button, Event details, and Attendance Table -->
                <div class="col-lg-8 col-xl-9 order-2 order-lg-1">
                    
                    <!-- Back Button -->
                    <div class="mb-3">
                        <a href="manage_attendance.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Manage Attendance
                        </a>
                    </div>

                    <!-- Event Info Header -->
                    <div class="event-attendance-header mb-4">
                        <h1 class="event-name mb-1"><?php echo htmlspecialchars($event["event_title"]); ?></h1>
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            <span class="event-meta">
                                <i class="bi bi-calendar3 me-1"></i> <strong>Date:</strong> <?php echo htmlspecialchars($eventDateFormatted); ?>
                            </span>
                            <span class="event-meta">
                                <i class="bi bi-clock me-1"></i> <strong>Time:</strong> <?php echo htmlspecialchars(trim($startFmt)); ?> - <?php echo htmlspecialchars(trim($endFmt)); ?>
                            </span>
                            <span class="event-meta">
                                <i class="bi bi-geo-alt me-1"></i> <strong>Venue:</strong> <?php echo htmlspecialchars((string) $event["venue"]); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($message !== ""): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title fw-bold mb-3 text-dark">Student Attendance Records</h5>
                            <form method="GET" class="mb-3" id="attendanceFilterForm">
                                <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                <div class="row search-row align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold">Search Student</label>
                                        <input
                                            type="search"
                                            class="form-control"
                                            name="search"
                                            id="attendanceSearch"
                                            value="<?php echo htmlspecialchars($search); ?>"
                                            placeholder="Search by name or matric number"
                                        >
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Status</label>
                                        <select name="status" class="form-select" id="attendanceStatus">
                                            <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>All</option>
                                            <option value="present" <?php echo $statusFilter === "present" ? "selected" : ""; ?>>Present</option>
                                            <option value="absent" <?php echo $statusFilter === "absent" ? "selected" : ""; ?>>Absent</option>
                                            <option value="late" <?php echo $statusFilter === "late" ? "selected" : ""; ?>>Late</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <a href="event_attendance.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                <div class="table-responsive attendance-table-scroll">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">No.</th>
                                                <th scope="col">Matric No.</th>
                                                <th scope="col">Student Name</th>
                                                <th scope="col">Attendance Status</th>
                                                <th scope="col">Points</th>
                                                <th scope="col">Check In Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($attendees)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4 text-muted">No registered students found.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($attendees as $index => $student): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($student["matric_number"]); ?></td>
                                                        <td><?php echo htmlspecialchars($student["name"]); ?></td>
                                                        <td>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <?php
                                                                $current = strtolower((string) $student["attendance_status"]);
                                                                $regId = (int) $student["registration_id"];
                                                                ?>
                                                                <label class="status-choice">
                                                                    <input
                                                                        type="radio"
                                                                        class="status-checkbox"
                                                                        data-group="attendance-<?php echo $regId; ?>"
                                                                        name="attendance[<?php echo $regId; ?>]"
                                                                        value="present"
                                                                        <?php echo $current === "present" ? "checked" : ""; ?>
                                                                    >Attend
                                                                </label>
                                                                <label class="status-choice">
                                                                    <input
                                                                        type="radio"
                                                                        class="status-checkbox"
                                                                        data-group="attendance-<?php echo $regId; ?>"
                                                                        name="attendance[<?php echo $regId; ?>]"
                                                                        value="absent"
                                                                        <?php echo $current === "absent" ? "checked" : ""; ?>
                                                                    >Unattend
                                                                </label>
                                                                <label class="status-choice">
                                                                    <input
                                                                        type="radio"
                                                                        class="status-checkbox"
                                                                        data-group="attendance-<?php echo $regId; ?>"
                                                                        name="attendance[<?php echo $regId; ?>]"
                                                                        value="late"
                                                                        <?php echo $current === "late" ? "checked" : ""; ?>
                                                                    >Late
                                                                </label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($student["point_awarded"] === null): ?>
                                                                <span class="text-muted">-</span>
                                                            <?php else: ?>
                                                                <?php $points = (int) $student["point_awarded"]; ?>
                                                                <span class="attendance-points <?php echo htmlspecialchars(attendancePointsClass($points)); ?>">
                                                                    <?php echo htmlspecialchars(formatAttendancePoints($points)); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if (!empty($student["check_in_time"])) {
                                                                echo htmlspecialchars(date("d M Y, g:i A", strtotime((string) $student["check_in_time"])));
                                                            } else {
                                                                echo '<span class="text-muted">-</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($attendees)): ?>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-umpsa-teal">Save Attendance</button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sticky Event QR Attendance Card -->
                <div class="col-lg-4 col-xl-3 order-1 order-lg-2">
                    <div class="card border-0 shadow-sm qr-card mb-4">
                        <div class="card-body text-center">
                            <h5 class="mb-3 fw-bold text-dark">Event QR Attendance</h5>
                            <?php
                            $qrFile = trim((string) ($event["qr_code"] ?? ""));
                            $qrPath = $qrFile !== "" ? "eventsQR/" . basename($qrFile) : "";
                            $qrExists = $qrPath !== "" && is_file(__DIR__ . DIRECTORY_SEPARATOR . $qrPath);
                            ?>
                            <?php if ($qrExists): ?>
                                <img src="<?php echo htmlspecialchars($qrPath); ?>" alt="Event QR Code" class="qr-preview mb-3">
                            <?php else: ?>
                                <div class="alert alert-warning text-start py-2 px-3 fs-7 mb-3">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> QR image has not been generated for this event yet.
                                    <div class="mt-2">
                                        <a href="generate_event_qr.php?event_id=<?php echo (int) $event["event_id"]; ?>" class="btn btn-sm btn-warning w-100">Generate now</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <button
                                    type="button"
                                    class="btn btn-umpsa-teal w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#fullQrModal"
                                    <?php echo $qrExists ? "" : "disabled"; ?>
                                >
                                    <i class="bi bi-fullscreen me-1"></i> Show Full Page QR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="fullQrModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen">
                    <div class="modal-content bg-dark bg-opacity-75 border-0">
                        <div class="modal-header border-0">
                            <h5 class="modal-title text-white"><?php echo htmlspecialchars($event["event_title"]); ?> - Full QR</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body d-flex justify-content-center align-items-center">
                            <?php if ($qrExists): ?>
                                <img src="<?php echo htmlspecialchars($qrPath); ?>" alt="Full Event QR Code" class="qr-modal-image">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var filterForm = document.getElementById("attendanceFilterForm");
            var searchInput = document.getElementById("attendanceSearch");
            var statusSelect = document.getElementById("attendanceStatus");
            var searchDebounceTimer;

            if (!filterForm) {
                return;
            }

            if (statusSelect) {
                statusSelect.addEventListener("change", function () {
                    filterForm.submit();
                });
            }

            if (searchInput) {
                searchInput.addEventListener("input", function () {
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(function () {
                        filterForm.submit();
                    }, 350);
                });
            }
        })();

    </script>
</body>

</html>
