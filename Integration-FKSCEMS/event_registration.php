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

function ensure_registration_open_column($conn)
{
    $result = mysqli_query($conn, "SHOW COLUMNS FROM event LIKE 'registration_open'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query($conn, "ALTER TABLE event ADD COLUMN registration_open TINYINT(1) NOT NULL DEFAULT 1 AFTER event_status");
    }
}

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ((empty($_SESSION['Login']) || $_SESSION['Login'] !== 'YES') && empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db_connect();
ensure_registration_open_column($conn);
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

$matric = $_SESSION['matric'] ?? '';
$message = '';

if (($_GET['registered'] ?? '') === '1') {
    $message = 'Event registration successful.';
}

mysqli_query($conn, "
    UPDATE eventwaitinglist ew
    INNER JOIN event e ON e.event_id=ew.event_id
    SET ew.waiting_status='cancelled'
    WHERE ew.waiting_status IN ('waiting','notified')
      AND NOW() >= CONCAT(e.event_date, ' ', e.event_time)
");

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'waiting') {
    $eventId = $_POST['event_id'] ?? '';
    if ($eventId !== '' && $matric !== '') {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO eventwaitinglist (matric_number, event_id, waiting_status, joined_at)
            SELECT ?, e.event_id, 'waiting', NOW()
            FROM event e
            WHERE e.event_id=?
              AND e.event_status IN ('full','open','upcoming')
              AND NOW() <= CONCAT(e.event_date, ' ', e.end_time)
              AND (
                  SELECT COUNT(*)
                  FROM eventregistration er
                  WHERE er.event_id=e.event_id
                    AND er.registration_status='registered'
              ) >= e.max_participant
              AND NOW() < CONCAT(e.event_date, ' ', e.event_time)
            ON DUPLICATE KEY UPDATE waiting_status = 'waiting', joined_at = NOW(), notified_at = NULL
        ");
        mysqli_stmt_bind_param($stmt, 'si', $matric, $eventId);
        mysqli_stmt_execute($stmt);
        $message = mysqli_stmt_affected_rows($stmt) > 0 ? 'You have joined the waiting list.' : 'Unable to join waiting list for this event.';
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
        e.registration_open,
        CASE
            WHEN e.event_status = 'cancelled' THEN 'cancelled'
            WHEN NOW() BETWEEN CONCAT(e.event_date, ' ', e.event_time)
                 AND CONCAT(e.event_date, ' ', e.end_time) THEN 'ongoing'
            WHEN e.event_status = 'full' THEN 'full'
            WHEN e.event_status = 'open' AND e.registration_open = 1
                 AND NOW() <= CONCAT(e.event_date, ' ', e.end_time) THEN 'open'
            WHEN e.event_date < CURDATE() THEN 'completed'
            WHEN NOW() < CONCAT(e.event_date, ' ', e.event_time) THEN 'upcoming'
            ELSE 'completed'
        END AS current_status,
        c.club_name,
        COUNT(er.registration_id) AS registered_count,
        MAX(CASE WHEN mine.registration_status = 'registered' THEN 1 ELSE 0 END) AS already_registered,
        COALESCE(MAX(mine.registration_status), '') AS my_registration_status,
        COALESCE(MAX(ew.waiting_status), '') AS my_waiting_status,
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
       AND ew.waiting_status IN ('waiting','notified')
       AND NOW() < CONCAT(e.event_date, ' ', e.event_time)
    GROUP BY e.event_id
    ORDER BY
        CASE
            WHEN e.event_status IN ('completed','cancelled') THEN 2
            ELSE 1
        END ASC,
        e.event_date DESC,
        e.event_time DESC
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
    $row['event_status'] = $row['current_status'];
    $row['is_full'] = (int) $row['registered_count'] >= (int) $row['max_participant'];
    $row['registration_open'] = (int) $row['registration_open'];
    $row['my_registration_status'] = strtolower((string) $row['my_registration_status']);
    $row['my_waiting_status'] = strtolower((string) $row['my_waiting_status']);
    $row['is_notified'] = $row['my_waiting_status'] === 'notified';
    $row['can_register'] = $row['registration_open'] === 1 && in_array($row['event_status'], ['open', 'upcoming'], true);
    $row['can_register_from_waiting'] = $row['is_notified'] && !$row['is_full'] && !in_array($row['event_status'], ['completed', 'cancelled'], true);
    $row['display_status'] = ucfirst($row['event_status']);

    if ($row['event_status'] === 'open' || $row['event_status'] === 'upcoming') {
        $availableEvents++;
    }

    if ((int) $row['already_registered'] === 1) {
        $registeredEvents++;
        $row['display_status'] = 'Registered';
    } elseif ($row['is_notified']) {
        $waitingEvents++;
        $row['display_status'] = 'Notified';
    } elseif ((int) $row['already_waiting'] === 1) {
        $waitingEvents++;
        $row['display_status'] = 'Waiting';
    } elseif (!$row['can_register']) {
        $row['display_status'] = ucfirst($row['event_status']);
    } elseif ($row['is_full']) {
        $row['display_status'] = 'Full';
    } elseif ($row['event_status'] === 'open') {
        $row['display_status'] = 'Open';
    }

    $hasStudentRecord = (int) $row['already_registered'] === 1
        || $row['my_registration_status'] !== ''
        || $row['my_waiting_status'] !== '';

    if ($row['event_status'] === 'completed' && !$hasStudentRecord) {
        continue;
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
    <link rel="stylesheet" href="student.css?v=student-notified-2">

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

        .pagination-custom {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 18px;
        }

        .pagination-btn {
            padding: 8px 14px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #ffffff;
            color: #212529;
            cursor: pointer;
            min-width: 48px;
            text-align: center;
            font-size: 0.95rem;
        }

        .pagination-btn.active,
        .pagination-btn:hover {
            background: #1c3f95;
            color: #ffffff;
            border-color: #1c3f95;
        }

        .pagination-btn.disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #dee2e6;
        }
    </style>
</head>

<body>

    <?php include('studentHeader.php') ?>

    <!-- Main Content -->
    <main class="student-content">

        <!-- Title -->
        <h1 class="page-title">Event Registration</h1>
        <div id="successMessage" class="alert alert-success alert-dismissible fade show <?php echo $message ? '' : 'd-none'; ?>" role="alert">
            <strong>Success!</strong> <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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

            <a href="event_registration_history.php" class="btn-history-event text-decoration-none">
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
                    <option value="Upcoming">Upcoming</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Full">Full</option>
                    <option value="Registered">Registered</option>
                    <option value="Waiting">Waiting</option>
                    <option value="Notified">Notified</option>
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
                            <th>No.</th>
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
                                <td colspan="8" class="text-center py-4 text-muted">No events found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php $eventNo = 1; ?>
                        <?php foreach ($events as $event): ?>
                            <tr
                                data-title="<?php echo e(strtolower($event['event_title'])); ?>"
                                data-club="<?php echo e($event['club_name']); ?>"
                                data-status="<?php echo e($event['display_status']); ?>"
                                data-month="<?php echo e(month_name($event['event_date'])); ?>">
                                <td class="event-row-no"><?php echo e($eventNo++); ?></td>
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
                                    <a href="student_event_view.php?id=<?php echo e($event['event_id']); ?>" class="btn-view-event" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <?php if ((int) $event['already_registered'] === 1): ?>
                                        <span class="action-text-muted">Registered</span>
                                    <?php elseif ($event['can_register_from_waiting']): ?>
                                        <a href="event_registration_form.php?id=<?php echo e($event['event_id']); ?>" class="btn-register-event">Register</a>
                                    <?php elseif ((int) $event['already_waiting'] === 1): ?>
                                        <span class="btn-register-event registered-disabled">Waiting</span>
                                    <?php elseif ($event['is_full'] && !in_array($event['event_status'], ['completed', 'cancelled'], true)): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Join waiting list for this event?');">
                                            <input type="hidden" name="event_id" value="<?php echo e($event['event_id']); ?>">
                                            <input type="hidden" name="action" value="waiting">
                                            <button type="submit" class="btn-register-event">Join Waiting List</button>
                                        </form>
                                    <?php elseif ($event['can_register'] && $event['my_registration_status'] === 'cancelled' && !$event['is_full']): ?>
                                        <a href="event_registration_form.php?id=<?php echo e($event['event_id']); ?>" class="btn-register-event">Register</a>
                                    <?php elseif (!$event['can_register']): ?>
                                        <span class="action-text-muted"><?php echo e($event['display_status']); ?></span>
                                    <?php else: ?>
                                        <a href="event_registration_form.php?id=<?php echo e($event['event_id']); ?>" class="btn-register-event">Register</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

            <!-- Pagination -->
            <div class="pagination-custom" id="paginationContainer">
            </div>

        </div>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var currentPage = 1;
        var rowsPerPage = 10;

        function getFilteredRows() {
            var searchTitle = document.getElementById("searchTitle").value.toLowerCase();
            var clubFilter = document.getElementById("clubFilter").value;
            var statusFilter = document.getElementById("statusFilter").value;
            var monthFilter = document.getElementById("monthFilter").value;
            var rows = Array.from(document.querySelectorAll("#eventTableBody tr[data-title]"));

            return rows.filter(function(row) {
                var matchTitle = row.dataset.title.indexOf(searchTitle) !== -1;
                var matchClub = clubFilter === "all" || row.dataset.club === clubFilter;
                var matchStatus = statusFilter === "all" || row.dataset.status === statusFilter;
                var matchMonth = monthFilter === "all" || row.dataset.month === monthFilter;
                return matchTitle && matchClub && matchStatus && matchMonth;
            });
        }

        function showPage(page) {
            var rows = Array.from(document.querySelectorAll("#eventTableBody tr[data-title]"));
            var filteredRows = getFilteredRows();
            var totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));

            currentPage = Math.min(Math.max(page, 1), totalPages);

            rows.forEach(function(row) {
                row.style.display = "none";
            });

            filteredRows.forEach(function(row, index) {
                var rowPage = Math.floor(index / rowsPerPage) + 1;
                row.style.display = rowPage === currentPage ? "" : "none";
            });

            renderPagination(totalPages, filteredRows.length);
        }

        function renderPagination(totalPages, rowCount) {
            var container = document.getElementById("paginationContainer");
            container.innerHTML = "";

            if (rowCount <= rowsPerPage) {
                return;
            }

            var prevBtn = document.createElement("button");
            prevBtn.type = "button";
            prevBtn.className = "pagination-btn" + (currentPage === 1 ? " disabled" : "");
            prevBtn.textContent = "Previous";
            prevBtn.onclick = function() {
                if (currentPage > 1) showPage(currentPage - 1);
            };
            container.appendChild(prevBtn);

            for (var i = 1; i <= totalPages; i++) {
                var pageBtn = document.createElement("button");
                pageBtn.type = "button";
                pageBtn.className = "pagination-btn" + (i === currentPage ? " active" : "");
                pageBtn.textContent = i;
                pageBtn.onclick = (function(pageNumber) {
                    return function() {
                        showPage(pageNumber);
                    };
                })(i);
                container.appendChild(pageBtn);
            }

            var nextBtn = document.createElement("button");
            nextBtn.type = "button";
            nextBtn.className = "pagination-btn" + (currentPage === totalPages ? " disabled" : "");
            nextBtn.textContent = "Next";
            nextBtn.onclick = function() {
                if (currentPage < totalPages) showPage(currentPage + 1);
            };
            container.appendChild(nextBtn);
        }

        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("searchTitle").addEventListener("keyup", function() {
                showPage(1);
            });
            document.getElementById("clubFilter").addEventListener("change", function() {
                showPage(1);
            });
            document.getElementById("statusFilter").addEventListener("change", function() {
                showPage(1);
            });
            document.getElementById("monthFilter").addEventListener("change", function() {
                showPage(1);
            });
            showPage(1);
        });
    </script>
</body>

</html>
