<?php
require_once "config/db.php";
require_once "includes/functions.php";

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

if ($eventId > 0) {
    $eventSql = "
        SELECT
            event_id,
            event_title,
            event_date,
            event_time,
            end_time,
            venue,
            event_status
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
    <link rel="stylesheet" href="assets/css/committee.css">

    <style>
        body {
            background-color: #ffffff;
        }

        .table> :not(caption)>*>* {
            border-bottom-color: #eeeff2;
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

        .nav-right .nav-link.active-link {
            color: #1c3f95;
            font-weight: 700;
        }

        .attendance-table-scroll {
            max-height: 620px;
            overflow-y: auto;
        }

        .attendance-table-scroll thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }
        .qr-preview {
            width: 100%;
            max-width: 240px;
            height: auto;
            border: 1px solid #eeeff2;
            border-radius: 10px;
            background-color: #f8fafc;
            padding: 0.5rem;
        }

        .status-choice {
            min-width: 86px;
            text-align: center;
        }

        .status-choice input {
            margin-right: 0.35rem;
        }

        .qr-modal-image {
            width: min(90vw, 900px);
            height: auto;
            border-radius: 10px;
            background: #fff;
            padding: 1rem;
        }

        .search-row {
            gap: 0.75rem;
        }

        .event-attendance-header .event-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1c3f95;
            margin-bottom: 0.5rem;
        }

        .event-attendance-header .event-meta {
            color: #495057;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .event-attendance-header .event-meta strong {
            font-weight: 600;
            color: #212529;
        }

        .points-plus-10 {
            color: #198754;
            font-weight: 700;
        }

        .points-plus-5 {
            color: #009e96;
            font-weight: 700;
        }

        .points-minus-10 {
            color: #dc3545;
            font-weight: 700;
        }

        .points-neutral {
            color: #6c757d;
        }
    </style>
</head>

<body>

    <?php include('committeeHeader.php') ?>

    <main class="student-content">
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
            <div>
                <div class="event-attendance-header mb-4">
                    <h1 class="event-name mb-0"><?php echo htmlspecialchars($event["event_title"]); ?></h1>
                    <p class="event-meta mb-0">Date: <?php echo htmlspecialchars($eventDateFormatted); ?></p>
                    <p class="event-meta mb-0">Time: <?php echo htmlspecialchars(trim($startFmt)); ?> - <?php echo htmlspecialchars(trim($endFmt)); ?></p>
                    <p class="event-meta mb-0">Venue: <?php echo htmlspecialchars((string) $event["venue"]); ?></p>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h5 class="mb-3">Event QR Attendance</h5>
                            <?php
                            $dummyQr = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=Demo+QR+Preview";
                            $realQrData = "event-attendance:" . (int) $event["event_id"] . ":" . urlencode((string) $event["event_title"]);
                            $realQr = "https://api.qrserver.com/v1/create-qr-code/?size=900x900&data=" . $realQrData;
                            ?>
                            <img src="<?php echo htmlspecialchars($dummyQr); ?>" alt="Dummy QR Code" class="qr-preview mb-3">
                            <div>
                                <button
                                    type="button"
                                    class="btn btn-umpsa-teal"
                                    data-bs-toggle="modal"
                                    data-bs-target="#fullQrModal"
                                >
                                    Show Full Page QR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message !== ""): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
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
                                                                    type="checkbox"
                                                                    class="status-checkbox"
                                                                    data-group="attendance-<?php echo $regId; ?>"
                                                                    name="attendance[<?php echo $regId; ?>]"
                                                                    value="present"
                                                                    <?php echo $current === "present" ? "checked" : ""; ?>
                                                                >Present
                                                            </label>
                                                            <label class="status-choice">
                                                                <input
                                                                    type="checkbox"
                                                                    class="status-checkbox"
                                                                    data-group="attendance-<?php echo $regId; ?>"
                                                                    name="attendance[<?php echo $regId; ?>]"
                                                                    value="absent"
                                                                    <?php echo $current === "absent" ? "checked" : ""; ?>
                                                                >Absent
                                                            </label>
                                                            <label class="status-choice">
                                                                <input
                                                                    type="checkbox"
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

            <div class="modal fade" id="fullQrModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen">
                    <div class="modal-content bg-dark bg-opacity-75 border-0">
                        <div class="modal-header border-0">
                            <h5 class="modal-title text-white"><?php echo htmlspecialchars($event["event_title"]); ?> - Full QR</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body d-flex justify-content-center align-items-center">
                            <img src="<?php echo htmlspecialchars($realQr); ?>" alt="Full Event QR Code" class="qr-modal-image">
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

        document.querySelectorAll(".status-checkbox").forEach(function (checkbox) {
            checkbox.addEventListener("change", function () {
                if (!this.checked) {
                    return;
                }
                var group = this.getAttribute("data-group");
                document.querySelectorAll('.status-checkbox[data-group="' + group + '"]').forEach(function (other) {
                    if (other !== checkbox) {
                        other.checked = false;
                    }
                });
            });
        });
    </script>
</body>

</html>
