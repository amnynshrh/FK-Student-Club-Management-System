<?php
session_start();

/*
  Module 3: Event Registration
  Lecture concepts used:
  - PHP same-page form handling with $_POST.
  - MySQL data retrieval with mysqli.
  - Session checking with $_SESSION.
  - JavaScript DOM filtering and confirm popup.
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

function month_name($date)
{
    return date('F', strtotime($date));
}

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: ../index.html');
    exit;
}

if (empty($_SESSION['Login']) || $_SESSION['Login'] !== 'YES') {
    header('Location: ../index.html');
    exit;
}

$conn = db_connect();
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

$matric = $_SESSION['matric'] ?? '';
$message = '';

mysqli_query($conn, "
    UPDATE event
    SET event_status = CASE
        WHEN NOW() < CONCAT(event_date, ' ', event_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(event_date, ' ', event_time)
             AND CONCAT(event_date, ' ', end_time) THEN 'ongoing'
        ELSE 'completed'
    END
    WHERE event_status != 'cancelled'
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'waiting') {
    $eventId = $_POST['event_id'] ?? '';
    if ($eventId !== '' && $matric !== '') {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO eventwaitinglist (matric_number, event_id, waiting_status, joined_at)
            VALUES (?, ?, 'waiting', NOW())
            ON DUPLICATE KEY UPDATE waiting_status = 'waiting', joined_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, 'si', $matric, $eventId);
        mysqli_stmt_execute($stmt);
        $message = 'You have joined the waiting list.';
    }
}

$eventsSql = "
    SELECT
        e.event_id,
        e.event_title,
        e.event_date,
        e.event_time,
        e.end_time,
        e.venue,
        e.max_participant,
        e.event_status,
        c.club_name,
        COUNT(er.registration_id) AS registered_count,
        MAX(CASE WHEN mine.registration_status = 'registered' THEN 1 ELSE 0 END) AS already_registered,
        MAX(CASE WHEN ew.waiting_status IN ('waiting','notified') THEN 1 ELSE 0 END) AS already_waiting
    FROM event e
    INNER JOIN club c ON c.club_id = e.club_id
    LEFT JOIN eventregistration er
        ON er.event_id = e.event_id
       AND er.registration_status = 'registered'
    LEFT JOIN eventregistration mine
        ON mine.event_id = e.event_id
       AND mine.matric_number = ?
    LEFT JOIN eventwaitinglist ew
        ON ew.event_id = e.event_id
       AND ew.matric_number = ?
    WHERE e.event_status != 'cancelled'
    GROUP BY e.event_id
    ORDER BY e.event_date ASC
";

$stmt = mysqli_prepare($conn, $eventsSql);
mysqli_stmt_bind_param($stmt, 'ss', $matric, $matric);
mysqli_stmt_execute($stmt);
$eventsResult = mysqli_stmt_get_result($stmt);

$events = [];
$clubs = [];
$availableEvents = 0;
$registeredEvents = 0;
$waitingEvents = 0;

while ($row = mysqli_fetch_assoc($eventsResult)) {
    $row['is_full'] = (int) $row['registered_count'] >= (int) $row['max_participant'];
    $row['display_status'] = ucfirst($row['event_status']);

    if ($row['event_status'] === 'upcoming' || $row['event_status'] === 'ongoing') {
        $availableEvents++;
    }

    if ((int) $row['already_registered'] === 1) {
        $registeredEvents++;
        $row['display_status'] = 'Registered';
    } elseif ((int) $row['already_waiting'] === 1) {
        $waitingEvents++;
        $row['display_status'] = 'Waiting';
    } elseif ($row['is_full']) {
        $row['display_status'] = 'Full';
    } elseif ($row['event_status'] === 'upcoming' || $row['event_status'] === 'ongoing') {
        $row['display_status'] = 'Open';
    }

    $clubs[$row['club_name']] = true;
    $events[] = $row;
}

ksort($clubs);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration - Student</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="student.css?v=student-waiting-1">

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

    <!-- Eventbrite Style Navbar -->
    <header class="top-navbar">
        <div class="nav-left">
            <img src="logo-fk.png" alt="FK Logo" class="nav-logo">
            <div class="nav-brand">
                FK Student Club &<br>Management System
            </div>
        </div>

        <div class="nav-right">
            <a href="../student/home.php" class="nav-link">Home</a>
            <a href="../student/view_club.html" class="nav-link">Find Clubs</a>
            <a href="student_event_registration.php" class="nav-link active-link">Event Registration</a>
            <a href="../student/attendance_record.php" class="nav-link">Attendance</a>
            <a href="../student/profile.html" class="nav-link">Profile</a>
            <a href="?action=logout" class="nav-link">Log Out</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="student-content">

        <!-- Title -->
        <h1 class="page-title">Event Registration</h1>
        <div id="successMessage" class="alert alert-success <?php echo $message ? '' : 'd-none'; ?>" role="alert">
            <?php echo e($message); ?>
        </div>

        <!-- Summary Cards - SAME THEME AS COMMITTEE -->
        <div class="summary-cards-custom">

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <p class="summary-label">Available Events</p>
                    <h3 class="summary-number" id="availableEvents"><?php echo e($availableEvents); ?></h3>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon teal">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div>
                    <p class="summary-label">Registered</p>
                    <h3 class="summary-number" id="registeredEvents"><?php echo e($registeredEvents); ?></h3>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div>
                    <p class="summary-label">Waiting List</p>
                    <h3 class="summary-number" id="waitingEvents"><?php echo e($waitingEvents); ?></h3>
                </div>
            </div>

        </div>

        <!-- Controls - SAME THEME AS COMMITTEE -->
        <div class="controls-row-custom">

            <div class="search-box-custom">
                <span class="search-icon-custom">
                    <i class="bi bi-search"></i>
                </span>

                <input type="text" id="searchTitle" placeholder="Search by Event Title">
            </div>

            <a href="student_event_registration_history.php" class="btn-history-event text-decoration-none">
                <i class="bi bi-clock-history me-2"></i>
                Your Event Registration History
            </a>

        </div>

        <!-- Table Card - SAME THEME AS COMMITTEE -->
        <div class="table-card-custom">

            <!-- Filters -->
            <div class="table-filter-row">

                <select class="filter-select-custom" id="clubFilter">
                    <option value="all">List of Club</option>
                    <?php foreach (array_keys($clubs) as $clubName): ?>
                        <option value="<?php echo e($clubName); ?>"><?php echo e($clubName); ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="filter-select-custom" id="statusFilter">
                    <option value="all">Show All Status</option>
                    <option value="Open">Open</option>
                    <option value="Full">Full</option>
                    <option value="Registered">Registered</option>
                    <option value="Waiting">Waiting</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <select class="filter-select-custom" id="monthFilter">
                    <option value="all">List of Month</option>
                    <option value="January">January</option>
                    <option value="February">February</option>
                    <option value="March">March</option>
                    <option value="April">April</option>
                    <option value="May">May</option>
                    <option value="June">June</option>
                    <option value="July">July</option>
                    <option value="August">August</option>
                    <option value="September">September</option>
                    <option value="October">October</option>
                    <option value="November">November</option>
                    <option value="December">December</option>
                </select>

            </div>

            <!-- Table -->
            <div class="table-responsive">

                <table class="event-table-custom">

                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="eventTableBody">
                        <?php if (!$events): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No events found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($events as $event): ?>
                            <tr
                                data-title="<?php echo e(strtolower($event['event_title'])); ?>"
                                data-club="<?php echo e($event['club_name']); ?>"
                                data-status="<?php echo e($event['display_status']); ?>"
                                data-month="<?php echo e(month_name($event['event_date'])); ?>">
                                <td><?php echo e($event['event_title']); ?></td>
                                <td><?php echo e(date('d M Y', strtotime($event['event_date']))); ?></td>
                                <td><?php echo e(display_time($event['event_time'])); ?> - <?php echo e(display_time($event['end_time'])); ?></td>
                                <td><?php echo e($event['venue']); ?></td>
                                <td><?php echo e($event['registered_count']); ?>/<?php echo e($event['max_participant']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo e(strtolower($event['display_status'])); ?>">
                                        <?php echo e($event['display_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../student_event_view.php?id=<?php echo e($event['event_id']); ?>" class="btn-view-event" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <?php if ((int) $event['already_registered'] === 1): ?>
                                        <span class="btn-register-event registered-disabled">Registered</span>
                                    <?php elseif ((int) $event['already_waiting'] === 1): ?>
                                        <span class="btn-register-event registered-disabled">Waiting</span>
                                    <?php elseif ($event['is_full']): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Join waiting list for this event?');">
                                            <input type="hidden" name="event_id" value="<?php echo e($event['event_id']); ?>">
                                            <input type="hidden" name="action" value="waiting">
                                            <button type="submit" class="btn-register-event">Join Waiting List</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="student_event_register_form.php?id=<?php echo e($event['event_id']); ?>" class="btn-register-event">Register</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

            <!-- Pagination -->
            <div class="pagination-custom" id="paginationContainer">
                <button class="page-btn-custom active">1</button>
            </div>

        </div>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var searchTitle = document.getElementById("searchTitle");
            var clubFilter = document.getElementById("clubFilter");
            var statusFilter = document.getElementById("statusFilter");
            var monthFilter = document.getElementById("monthFilter");

            searchTitle.addEventListener("keyup", filterEvents);
            clubFilter.addEventListener("change", filterEvents);
            statusFilter.addEventListener("change", filterEvents);
            monthFilter.addEventListener("change", filterEvents);

            function filterEvents() {
                var title = searchTitle.value.toLowerCase();
                var club = clubFilter.value;
                var status = statusFilter.value;
                var month = monthFilter.value;
                var rows = document.querySelectorAll("#eventTableBody tr[data-title]");

                rows.forEach(function (row) {
                    var matchTitle = row.dataset.title.indexOf(title) !== -1;
                    var matchClub = club === "all" || row.dataset.club === club;
                    var matchStatus = status === "all" || row.dataset.status === status;
                    var matchMonth = month === "all" || row.dataset.month === month;

                    row.style.display = (matchTitle && matchClub && matchStatus && matchMonth) ? "" : "none";
                });
            }
        });
    </script>
</body>

</html>
