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
function ensure_registration_open_column($conn)
{
    $result = mysqli_query($conn, "SHOW COLUMNS FROM event LIKE 'registration_open'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query($conn, "ALTER TABLE event ADD COLUMN registration_open TINYINT(1) NOT NULL DEFAULT 1 AFTER event_status");
    }
}
function badge($status)
{
    $s = strtolower((string)$status);
    return '<span class="status-badge ' . e($s) . '">' . e(ucfirst($s)) . '</span>';
}
logout_if_requested();
$conn = db_connect();
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

require_login(['student', 'committee', 'admin']);
ensure_registration_open_column($conn);
update_event_status($conn);
$mode = $_GET['mode'] ?? 'student';
$eventId = $_GET['id'] ?? '';
if ($eventId === '') {
    die('Event ID is required.');
}
$st = mysqli_prepare($conn, "SELECT e.*, c.club_name, COUNT(er.registration_id) registered_count FROM event e INNER JOIN club c ON c.club_id=e.club_id LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' WHERE e.event_id=? GROUP BY e.event_id");
mysqli_stmt_bind_param($st, 'i', $eventId);
mysqli_stmt_execute($st);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if (!$event) {
    die('Event not found.');
}
$displayStatus = ((int) ($event['registration_open'] ?? 1) === 1 && in_array($event['event_status'], ['open', 'upcoming'], true))
    ? 'Open'
    : ucfirst($event['event_status']);
$p = mysqli_prepare($conn, "SELECT s.name,s.matric_number,s.course,u.email,COALESCE(a.attendance_status,'not marked') attendance_status FROM eventregistration er INNER JOIN student s ON s.matric_number=er.matric_number INNER JOIN user u ON u.user_id=s.user_id LEFT JOIN attendance a ON a.registration_id=er.registration_id WHERE er.event_id=? AND er.registration_status='registered' ORDER BY er.registration_date ASC");
mysqli_stmt_bind_param($p, 'i', $eventId);
mysqli_stmt_execute($p);
$participants = mysqli_stmt_get_result($p);
$participantCount = mysqli_num_rows($participants);
$w = mysqli_prepare($conn, "SELECT s.name,s.matric_number,ew.waiting_status FROM eventwaitinglist ew INNER JOIN student s ON s.matric_number=ew.matric_number WHERE ew.event_id=? AND ew.waiting_status IN ('waiting','notified') ORDER BY ew.joined_at ASC");
mysqli_stmt_bind_param($w, 'i', $eventId);
mysqli_stmt_execute($w);
$waiting = mysqli_stmt_get_result($w);
$waitingCount = mysqli_num_rows($waiting);
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event Details</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="committee.css?v=committee-detail-1">

    <style>
        body {
            background-color: #f4f7f6;
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

        .view-event-subtitle {
            margin-top: 8px;
            margin-bottom: 16px;
        }

        .participant-list-card {
            margin: 24px 0;
        }

        .status-badge.open {
            background-color: #009e96;
            color: #ffffff;
        }
    </style>
</head>

<body>
    <?php include('committeeHeader.php') ?>

    <main class="student-content">
        <div class="view-event-page">

            <div class="view-event-header">
                <h1 class="page-title">View Event Details</h1>
                <p class="view-event-subtitle">Review complete event information and participation details.</p>
            </div>

            <section class="view-event-card">

                <div class="view-event-top">
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
                            <p class="view-detail-value"><?php echo e(dmy($event['event_date'])); ?></p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Capacity</p>
                            <p class="view-detail-value"><?php echo e($event['registered_count']); ?>/<?php echo e($event['max_participant']); ?> Participants</p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Event Time</p>
                            <p class="view-detail-value"><?php echo e(t($event['event_time'])); ?> - <?php echo e(t($event['end_time'])); ?></p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Status</p>
                            <?php echo badge($displayStatus); ?>
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
                    <a href="manage_events.php" class="view-event-back-btn">
                        <i class="bi bi-arrow-left"></i>
                        Back
                    </a>
                </div>

            </section>

            <?php if ($mode === 'committee'): ?><section class="view-event-card participant-list-card">
                    <div class="participant-section-header">
                        <div>
                            <h2 class="participant-section-title">Participant List</h2>
                            <p class="view-event-subtitle">Students who registered for this event.</p>
                        </div>
                        <span class="participant-count-pill" id="participantListCount"><?php echo e($participantCount); ?> registered</span>
                    </div>

                    <div class="table-responsive">
                        <table class="event-table-custom">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Name</th>
                                    <th>Matric</th>
                                    <th>Course</th>
                                    <th>Email</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody id="participantTableBody"><?php $participantIndex = 0;
                                                                while ($row = mysqli_fetch_assoc($participants)): $participantIndex++; ?><tr>
                                        <td><?php echo e($participantIndex); ?></td>
                                        <td><?php echo e($row['name']); ?></td>
                                        <td><?php echo e($row['matric_number']); ?></td>
                                        <td><?php echo e($row['course']); ?></td>
                                        <td><?php echo e($row['email']); ?></td>
                                        <td><?php echo e($row['attendance_status']); ?></td>
                                    </tr><?php endwhile; ?></tbody>
                        </table>
                    </div>
                </section>

                <section class="view-event-card participant-list-card">
                    <div class="participant-section-header">
                        <div>
                            <h2 class="participant-section-title">Waiting List</h2>
                            <p class="view-event-subtitle">Students waiting for an available slot.</p>
                        </div>
                        <span class="participant-count-pill" id="waitingListCount"><?php echo e($waitingCount); ?> waiting</span>
                    </div>

                    <div class="table-responsive">
                        <table class="event-table-custom">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Name</th>
                                    <th>Matric</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="waitingTableBody"><?php $waitingIndex = 0;
                                                            while ($row = mysqli_fetch_assoc($waiting)): $waitingIndex++; ?><tr>
                                        <td><?php echo e($waitingIndex); ?></td>
                                        <td><?php echo e($row['name']); ?></td>
                                        <td><?php echo e($row['matric_number']); ?></td>
                                        <td><?php echo e($row['waiting_status']); ?></td>
                                    </tr><?php endwhile; ?></tbody>
                        </table>
                    </div>
                </section>
        </div>
    </main>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php endif; ?>
</body>

</html>
