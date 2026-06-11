<?php
session_start();

/*
  Student View Event Details
  Lecture concepts used:
  - PHP session checking with $_SESSION.
  - MySQL SELECT with mysqli prepared statement.
  - Same-page PHP rendering with htmlspecialchars output safety.
*/

function db_connect()
{
    $conn = mysqli_connect('localhost', 'root', 'Amni102030.', 'fk_scems_db');
    if (!$conn) {
        die('Database connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function display_time($time)
{
    return date('g:i A', strtotime($time));
}

function display_date($date)
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

function require_student_login()
{
    if ((empty($_SESSION['Login']) || $_SESSION['Login'] !== 'YES') && empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function update_event_status($conn)
{
    mysqli_query($conn, "
        UPDATE event
        SET event_status = CASE
            WHEN NOW() > CONCAT(event_date, ' ', end_time) THEN 'completed'
            WHEN NOW() BETWEEN CONCAT(event_date, ' ', event_time)
                 AND CONCAT(event_date, ' ', end_time) THEN 'ongoing'
            WHEN (
                SELECT COUNT(*)
                FROM eventregistration er
                WHERE er.event_id = event.event_id
                  AND er.registration_status = 'registered'
            ) >= max_participant THEN 'full'
            WHEN registration_open = 1 THEN 'open'
            WHEN NOW() < CONCAT(event_date, ' ', event_time) THEN 'upcoming'
            ELSE 'completed'
        END
        WHERE event_status != 'cancelled'
    ");
}

function status_badge($status)
{
    $statusClass = strtolower((string) $status);
    return '<span class="status-badge ' . e($statusClass) . '">' . e(ucfirst($statusClass)) . '</span>';
}

logout_if_requested();
require_student_login();

$conn = db_connect();
update_event_status($conn);
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

$eventId = $_GET['id'] ?? '';
if ($eventId === '') {
    die('Event ID is required.');
}

$backLink = (($_GET['from'] ?? '') === 'history') ? 'event_registration_history.php' : 'event_registration.php';

$stmt = mysqli_prepare($conn, "
    SELECT
        e.event_id,
        e.event_title,
        e.event_description,
        e.event_date,
        e.event_time,
        e.end_time,
        e.venue,
        e.max_participant,
        e.event_status,
        e.registration_open,
        c.club_name,
        COUNT(er.registration_id) AS registered_count
    FROM event e
    INNER JOIN club c ON c.club_id = e.club_id
    LEFT JOIN eventregistration er
        ON er.event_id = e.event_id
       AND er.registration_status = 'registered'
    WHERE e.event_id = ?
    GROUP BY e.event_id
");
mysqli_stmt_bind_param($stmt, 'i', $eventId);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$event) {
    die('Event not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student View Event Details</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="student.css?v=student-detail-open-3">

    <style>
        body {
            background-color: #ffffff;
        }

        .table> :not(caption)>*>* {
            border-bottom-color: #eeeff2;
        }
    </style>
</head>

<body>

    <?php include('studentHeader.php') ?>

    <main class="student-content">
        <div class="view-event-page">

            <div class="view-event-header">
                <h1 class="page-title">View Event Details</h1>
                <p class="view-event-subtitle">Review complete event information before registration.</p>
            </div>

            <section class="view-event-card">

                <div class="view-event-top">
                    <div class="view-event-icon navy">
                        <i class="bi bi-calendar2-event"></i>
                    </div>
                    <div>
                        <h2 class="view-event-name"><?php echo e($event['event_title']); ?></h2>
                        <p class="view-event-organizer">
                            <i class="bi bi-person-badge"></i>
                            Organizer: <?php echo e($event['club_name']); ?>
                        </p>
                    </div>
                </div>

                <div class="view-event-details-grid">

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Event Date</p>
                            <p class="view-detail-value"><?php echo e(display_date($event['event_date'])); ?></p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Capacity</p>
                            <p class="view-detail-value">
                                <?php echo e($event['registered_count']); ?>/<?php echo e($event['max_participant']); ?> Participants
                            </p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Event Time</p>
                            <p class="view-detail-value">
                                <?php echo e(display_time($event['event_time'])); ?> -
                                <?php echo e(display_time($event['end_time'])); ?>
                            </p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Status</p>
                            <?php echo status_badge($event['event_status']); ?>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Venue</p>
                            <p class="view-detail-value"><?php echo e($event['venue']); ?></p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-card-text"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">About Event</p>
                            <p class="view-detail-value"><?php echo e($event['event_description']); ?></p>
                        </div>
                    </div>

                </div>

                <div class="view-event-actions">
                    <a href="<?php echo e($backLink); ?>" class="view-event-back-btn">
                        <i class="bi bi-arrow-left"></i>
                        Back
                    </a>
                </div>

            </section>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
