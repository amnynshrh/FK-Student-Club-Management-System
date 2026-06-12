<?php
session_start();
function db_connect()
{
    $conn = mysqli_connect('localhost', 'root', '', 'fk_scems_db');
    if (!$conn) {
        die('Database connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function t($time)
{
    return date('g:i A', strtotime($time));
}
function dmy($date)
{
    return date('d M Y', strtotime($date));
}
function logout_if_requested()
{
    if (($_GET['action'] ?? '') === 'logout') {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
function require_login($roles = [])
{
    if (empty($_SESSION['Login']) || $_SESSION['Login'] !== 'YES') {
        header('Location: login.php');
        exit;
    }
}
function update_event_status($conn)
{
    mysqli_query($conn, "UPDATE event SET event_status=CASE WHEN NOW()>CONCAT(event_date,' ',end_time) THEN 'completed' WHEN NOW() BETWEEN CONCAT(event_date,' ',event_time) AND CONCAT(event_date,' ',end_time) THEN 'ongoing' WHEN (SELECT COUNT(*) FROM eventregistration er WHERE er.event_id=event.event_id AND er.registration_status='registered')>=max_participant THEN 'full' WHEN registration_open=1 THEN 'open' WHEN NOW()<CONCAT(event_date,' ',event_time) THEN 'upcoming' ELSE 'completed' END WHERE event_status!='cancelled'");
}
function ensure_event_registration_schema($conn)
{
    mysqli_query($conn, "ALTER TABLE eventregistration MODIFY registration_status ENUM('registered','cancelled') NOT NULL");
}
function badge($status)
{
    $s = strtolower((string)$status);
    return '<span class="status-badge ' . e($s) . '">' . e(ucfirst($s)) . '</span>';
}
logout_if_requested();
$conn = db_connect();
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

require_login(['student', 'committee']);
update_event_status($conn);
ensure_event_registration_schema($conn);
$eventId = $_GET['id'] ?? $_POST['event_id'] ?? '';
$matric = $_SESSION['matric'] ?? '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['agree_condition'])) {
        $message = 'Please tick the confirmation checkbox.';
    // } elseif ($matric === '') {
    //     $message = 'Student matric number was not found in the session. Please login again.';
    } else {
        $check = mysqli_prepare($conn, "
            SELECT
                e.event_status,
                e.registration_open,
                e.max_participant,
                COUNT(er.registration_id) AS registered_count,
                COALESCE(MAX(ew.waiting_status), '') AS waiting_status
            FROM event e
            LEFT JOIN eventregistration er
                ON er.event_id=e.event_id
               AND er.registration_status='registered'
            LEFT JOIN eventwaitinglist ew
                ON ew.event_id=e.event_id
               AND ew.matric_number=?
            WHERE e.event_id=?
            GROUP BY e.event_id
        ");
        mysqli_stmt_bind_param($check, 'si', $matric, $eventId);
        mysqli_stmt_execute($check);
        $canRegisterRow = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        $isNotified = strtolower((string) ($canRegisterRow['waiting_status'] ?? '')) === 'notified';
        $isFull = (int) ($canRegisterRow['registered_count'] ?? 0) >= (int) ($canRegisterRow['max_participant'] ?? 0);
        $eventStatus = strtolower((string) ($canRegisterRow['event_status'] ?? ''));

        if (!$canRegisterRow || in_array($eventStatus, ['completed', 'cancelled', 'ongoing'], true)) {
            $message = 'This event is not open for registration.';
        } elseif ($isFull && !$isNotified) {
            $message = 'This event is full. Please join the waiting list.';
        } else {
            $st = mysqli_prepare($conn, "INSERT INTO eventregistration (matric_number,event_id,registration_status) VALUES (?,?,'registered') ON DUPLICATE KEY UPDATE registration_status='registered', registration_date=NOW()");
            mysqli_stmt_bind_param($st, 'si', $matric, $eventId);
            if (mysqli_stmt_execute($st)) {
                $waitingUpdate = mysqli_prepare($conn, "UPDATE eventwaitinglist SET waiting_status='registered' WHERE matric_number=? AND event_id=? AND waiting_status='notified'");
                mysqli_stmt_bind_param($waitingUpdate, 'si', $matric, $eventId);
                mysqli_stmt_execute($waitingUpdate);
                header('Location: event_registration.php?registered=1');
                exit;
            }
            $message = 'Registration failed: ' . mysqli_stmt_error($st);
        }
    }
}
$st = mysqli_prepare($conn, "SELECT e.*, c.club_name, COUNT(er.registration_id) registered_count FROM event e INNER JOIN club c ON c.club_id=e.club_id LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' WHERE e.event_id=? GROUP BY e.event_id");
mysqli_stmt_bind_param($st, 'i', $eventId);
mysqli_stmt_execute($st);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if (!$event) {
    die('Event not found.');
}
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration Form- Student</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="student.css?v=student-layout-1">

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
    </style>
</head>

<body>

    <?php include('studentHeader.php') ?>

    <!-- Main Content -->
    <main class="student-content register-event-page">

        <div class="register-page-header">
            <h1 class="page-title">Event Registration Form</h1>
            <p class="page-subtitle">
                Review the event details before confirming your registration.
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-warning" role="alert">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <form class="register-event-card" method="POST" action="event_registration_form.php" onsubmit="return validateRegisterForm();">
            <input type="hidden" name="event_id" value="<?php echo e($event['event_id']); ?>">

            <!-- Event Header -->
            <div class="register-event-top">
                <div class="register-event-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <h2 class="register-event-title"><?php echo e($event['event_title']); ?></h2>
                    <p class="register-event-organizer"><i class="bi bi-people-fill"></i> Organized by <?php echo e($event['club_name']); ?></p>
                </div>
            </div>

            <!-- Event Details -->
            <div class="register-event-grid">

                <div class="register-detail-item">
                    <div class="register-detail-icon navy">
                        <i class="bi bi-calendar3"></i>
                    </div>

                    <div>
                        <p class="register-detail-label">Event Date</p>
                        <h4 class="register-detail-value"><?php echo e(dmy($event['event_date'])); ?></h4>
                    </div>
                </div>

                <div class="register-detail-item">
                    <div class="register-detail-icon teal">
                        <i class="bi bi-people"></i>
                    </div>

                    <div>
                        <p class="register-detail-label">Capacity</p>
                        <h4 class="register-detail-value"><?php echo e($event['registered_count']); ?>/<?php echo e($event['max_participant']); ?> Participants</h4>
                    </div>
                </div>

                <div class="register-detail-item">
                    <div class="register-detail-icon teal">
                        <i class="bi bi-clock"></i>
                    </div>

                    <div>
                        <p class="register-detail-label">Event Time</p>
                        <h4 class="register-detail-value"><?php echo e(t($event['event_time'])); ?> - <?php echo e(t($event['end_time'])); ?></h4>
                    </div>
                </div>

                <div class="register-detail-item">
                    <div class="register-detail-icon navy">
                        <i class="bi bi-info-circle"></i>
                    </div>

                    <div>
                        <p class="register-detail-label">About Event</p>
                        <h4 class="register-detail-value"><?php echo e($event['event_description']); ?></h4>
                    </div>
                </div>

                <div class="register-detail-item">
                    <div class="register-detail-icon navy">
                        <i class="bi bi-geo-alt"></i>
                    </div>

                    <div>
                        <p class="register-detail-label">Venue</p>
                        <h4 class="register-detail-value"><?php echo e($event['venue']); ?></h4>
                    </div>
                </div>

                <div class="register-detail-item condition-item">
                    <div class="register-detail-icon teal">
                        <i class="bi bi-check2-square"></i>
                    </div>

                    <div class="condition-content">
                        <p class="register-detail-label">Confirmation</p>

                        <label class="condition-check">
                            <input type="checkbox" id="agree-condition" name="agree_condition" required>
                            <span>I confirm that I want to register for this event.</span>
                        </label>
                    </div>
                </div>

            </div>

            <!-- Actions -->
            <div class="register-event-actions">
                <a href="event_registration.php" class="btn-register-cancel">
                    Cancel
                </a>

                <button type="submit" class="btn-register-confirm">
                    Confirm Registration
                </button>
            </div>

        </form>

    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function validateRegisterForm() {
            if (!document.getElementById('agree-condition').checked) {
                alert('Please tick the confirmation checkbox.');
                return false;
            }
            return true;
        }
    </script>
</body>

</html>
