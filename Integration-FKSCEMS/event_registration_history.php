<?php
session_start();

include ('session.php');
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
function month_name($date)
{
    return date('F', strtotime($date));
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
    if ((empty($_SESSION['Login']) || $_SESSION['Login'] !== 'YES') && empty($_SESSION['user_id'])) {
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
    mysqli_query($conn, "ALTER TABLE attendance MODIFY point_awarded TINYINT DEFAULT 0");
}
function sync_absent_attendance_for_completed_events($conn)
{
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
}
function badge($status)
{
    $s = strtolower((string)$status);
    return '<span class="status-badge ' . e($s) . '">' . e(ucfirst($s)) . '</span>';
}
function notify_next_waiting_student($conn, $eventId)
{
    $slotStmt = mysqli_prepare($conn, "
        SELECT
            e.max_participant,
            COUNT(er.registration_id) AS registered_count
        FROM event e
        LEFT JOIN eventregistration er
            ON er.event_id=e.event_id
           AND er.registration_status='registered'
        WHERE e.event_id=?
        GROUP BY e.event_id
    ");
    mysqli_stmt_bind_param($slotStmt, 'i', $eventId);
    mysqli_stmt_execute($slotStmt);
    $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($slotStmt));

    if (!$slot || (int) $slot['registered_count'] >= (int) $slot['max_participant']) {
        return;
    }

    $notifyStmt = mysqli_prepare($conn, "
        UPDATE eventwaitinglist
        SET waiting_status='notified',
            notified_at=NOW()
        WHERE event_id=?
          AND waiting_status='waiting'
        ORDER BY joined_at ASC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($notifyStmt, 'i', $eventId);
    mysqli_stmt_execute($notifyStmt);
}
logout_if_requested();
$conn = db_connect();
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

require_login(['student', 'committee']);
update_event_status($conn);
ensure_event_registration_schema($conn);
sync_absent_attendance_for_completed_events($conn);
mysqli_query($conn, "
    UPDATE eventwaitinglist ew
    INNER JOIN event e ON e.event_id=ew.event_id
    SET ew.waiting_status='cancelled'
    WHERE ew.waiting_status IN ('waiting','notified')
      AND NOW() >= CONCAT(e.event_date, ' ', e.event_time)
");
$matric = $_SESSION['matric'] ?? '';
$message = '';
$messageClass = 'alert-success';
$messageTitle = 'Success!';
if (($_GET['cancelled'] ?? '') === '1') {
    $message = 'Registration cancelled successfully.';
} elseif (($_GET['cancelled'] ?? '') === '0') {
    $message = 'This event cannot be cancelled.';
    $messageClass = 'alert-warning';
    $messageTitle = 'Notice!';
} elseif (($_GET['waiting'] ?? '') === '1') {
    $message = 'You have joined the waiting list.';
} elseif (($_GET['waiting'] ?? '') === '0') {
    $message = 'Unable to join waiting list for this event.';
    $messageClass = 'alert-warning';
    $messageTitle = 'Notice!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $id = $_POST['event_id'] ?? '';
    $st = mysqli_prepare($conn, "
        UPDATE eventregistration er
        INNER JOIN event e ON e.event_id = er.event_id
        SET er.registration_status='cancelled'
        WHERE er.matric_number=?
          AND er.event_id=?
          AND er.registration_status='registered'
          AND e.event_status IN ('open','upcoming','ongoing','full')
          AND NOW() <= CONCAT(e.event_date, ' ', e.end_time)
    ");
    mysqli_stmt_bind_param($st, 'si', $matric, $id);
    mysqli_stmt_execute($st);
    $cancelled = mysqli_stmt_affected_rows($st) > 0 ? '1' : '0';
    if ($cancelled === '1') {
        notify_next_waiting_student($conn, (int) $id);
    }
    header('Location: event_registration_history.php?cancelled=' . $cancelled);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'waiting') {
    $id = $_POST['event_id'] ?? '';
    $st = mysqli_prepare($conn, "
        INSERT INTO eventwaitinglist (matric_number, event_id, waiting_status, joined_at)
        SELECT ?, e.event_id, 'waiting', NOW()
        FROM event e
        WHERE e.event_id=?
          AND e.event_status IN ('full','open','upcoming','ongoing')
          AND NOW() < CONCAT(e.event_date, ' ', e.event_time)
          AND (
              SELECT COUNT(*)
              FROM eventregistration er
              WHERE er.event_id=e.event_id
                AND er.registration_status='registered'
          ) >= e.max_participant
        ON DUPLICATE KEY UPDATE waiting_status='waiting', joined_at=NOW(), notified_at=NULL
    ");
    mysqli_stmt_bind_param($st, 'si', $matric, $id);
    mysqli_stmt_execute($st);
    $waiting = mysqli_stmt_affected_rows($st) > 0 ? '1' : '0';
    header('Location: event_registration_history.php?waiting=' . $waiting);
    exit;
}

$st = mysqli_prepare($conn, "
    SELECT
        er.registration_status,
        e.event_id,
        e.event_title,
        e.event_date,
        e.event_time,
        e.end_time,
        e.venue,
        e.event_status,
        e.registration_open,
        e.max_participant,
        (
            SELECT COUNT(*)
            FROM eventregistration count_er
            WHERE count_er.event_id=e.event_id
              AND count_er.registration_status='registered'
        ) AS registered_count,
        COALESCE((
            SELECT MAX(ew.waiting_status)
            FROM eventwaitinglist ew
            WHERE ew.event_id=e.event_id
              AND ew.matric_number=er.matric_number
              AND ew.waiting_status IN ('waiting','notified')
              AND NOW() < CONCAT(e.event_date, ' ', e.event_time)
        ), '') AS waiting_status,
        CASE
            WHEN e.event_status = 'cancelled' THEN 'cancelled'
            WHEN e.event_date < CURDATE() THEN 'completed'
            WHEN NOW() BETWEEN CONCAT(e.event_date, ' ', e.event_time)
                 AND CONCAT(e.event_date, ' ', e.end_time) THEN 'ongoing'
            WHEN e.event_status = 'full' THEN 'full'
            WHEN e.registration_open = 1 THEN 'open'
            WHEN NOW() < CONCAT(e.event_date, ' ', e.event_time) THEN 'upcoming'
            ELSE 'completed'
        END AS current_status,
        c.club_name,
        COALESCE(a.attendance_status,'') attendance_status,
        COALESCE(a.point_awarded,0) point_awarded
    FROM eventregistration er
    INNER JOIN event e ON e.event_id=er.event_id
    INNER JOIN club c ON c.club_id=e.club_id
    LEFT JOIN attendance a ON a.registration_id=er.registration_id
    WHERE er.matric_number=?
    ORDER BY er.registration_date DESC
");
mysqli_stmt_bind_param($st, 's', $matric);
mysqli_stmt_execute($st);
$history = mysqli_stmt_get_result($st);
$total = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM eventregistration WHERE matric_number='" . mysqli_real_escape_string($conn, $matric) . "' AND registration_status='registered'"))[0] ?? 0;
$cancelled = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM eventregistration WHERE matric_number='" . mysqli_real_escape_string($conn, $matric) . "' AND registration_status='cancelled'"))[0] ?? 0;
$attended = 0;
$upcoming = 0;
$historyRows = [];
$years = [];

while ($row = mysqli_fetch_assoc($history)) {
    $isCompleted = $row['current_status'] === 'completed';
    $attendanceStatus = strtolower((string) $row['attendance_status']);
    $displayStatus = ucfirst($row['registration_status']);
    $displayPoints = (int) $row['point_awarded'];

    if ($row['registration_status'] === 'cancelled') {
        $displayStatus = 'Cancelled';
        $displayPoints = 0;
    } elseif ($attendanceStatus === 'present') {
        $displayStatus = 'Attend';
        $attended++;
    } elseif ($attendanceStatus === 'late') {
        $displayStatus = 'Late';
        $attended++;
    } elseif ($attendanceStatus === 'absent') {
        $displayStatus = 'Unattend';
        $displayPoints = 0;
    } elseif ($isCompleted) {
        $displayStatus = 'Unattend';
        $displayPoints = 0;
    } elseif ($row['registration_status'] === 'registered') {
        $displayStatus = 'Registered';
        $upcoming++;
    }

    $row['is_completed'] = $isCompleted;
    $row['is_full'] = (int) $row['registered_count'] >= (int) $row['max_participant'];
    $row['waiting_status'] = strtolower((string) $row['waiting_status']);
    $row['is_notified'] = $row['waiting_status'] === 'notified';
    $row['already_waiting'] = in_array($row['waiting_status'], ['waiting', 'notified'], true);
    if ($row['is_notified']) {
        $displayStatus = 'Notified';
    }

    $row['can_register_again'] = !$isCompleted && !$row['is_notified'] && !$row['is_full'] && $row['registration_status'] === 'cancelled' && (int) $row['registration_open'] === 1;
    $row['can_join_waiting'] = !$isCompleted && $row['is_full'] && $row['registration_status'] === 'cancelled' && !$row['already_waiting'];
    $row['display_status'] = $displayStatus;
    $row['display_points'] = $displayPoints;
    $row['event_year'] = date('Y', strtotime($row['event_date']));
    $row['event_month'] = month_name($row['event_date']);
    $years[$row['event_year']] = true;
    $historyRows[] = $row;
}

krsort($years);
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration History - Student</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Student Theme CSS -->
    <link rel="stylesheet" href="student.css?v=student-history-status-3">

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
    <main class="student-content history-page">

        <!-- Title -->
        <div class="history-header">
            <h1 class="page-title">Events Registration History</h1>
            <p class="page-subtitle">
                View all events you have registered, attended, upcoming, or cancelled.
            </p>
        </div>
        <div id="successMessage" class="alert <?php echo e($messageClass); ?> alert-dismissible fade show <?php echo $message ? '' : 'd-none'; ?>" role="alert">
            <strong><?php echo e($messageTitle); ?></strong> <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <!-- Summary Cards -->
        <div class="history-summary-cards">

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-person-check"></i>
                </div>

                <div>
                    <p class="summary-label">Total Registered</p>
                    <h3 class="summary-number"><?php echo e($total); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon teal">
                    <i class="bi bi-star"></i>
                </div>

                <div>
                    <p class="summary-label">Attended</p>
                    <h3 class="summary-number"><?php echo e($attended); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <p class="summary-label">Upcoming</p>
                    <h3 class="summary-number"><?php echo e($upcoming); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon teal">
                    <i class="bi bi-calendar-x"></i>
                </div>

                <div>
                    <p class="summary-label">Cancelled</p>
                    <h3 class="summary-number"><?php echo e($cancelled); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

        </div>

        <!-- Controls -->
        <div class="controls-row-custom history-controls">

            <div class="search-box-custom">
                <span class="search-icon-custom">
                    <i class="bi bi-search"></i>
                </span>

                <input type="text" id="searchTitle" placeholder="Search by Event Title" oninput="filterRows('historyTableBody', this.value)">
            </div>

            <a href="event_registration.php" class="btn-back-registration text-decoration-none">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Registration
            </a>

        </div>

        <!-- Table Card -->
        <div class="table-card-custom">

            <!-- Filters -->
            <div class="table-filter-row history-filter-row">

                <select class="filter-select-custom" id="statusFilter">
                    <option value="all">Show All Status</option>
                    <option value="Registered">Registered</option>
                    <option value="Attend">Attend</option>
                    <option value="Late">Late</option>
                    <option value="Unattend">Unattend</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <select class="filter-select-custom" id="yearFilter">
                    <option value="all">List of Year</option>
                    <?php foreach (array_keys($years) as $year): ?>
                        <option value="<?php echo e($year); ?>"><?php echo e($year); ?></option>
                    <?php endforeach; ?>
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
                            <th>Status</th>
                            <th>Points Earn</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="historyTableBody">
                        <?php $historyNo = 1; ?>
                        <?php foreach ($historyRows as $row): ?>
                            <tr
                                data-search="<?php echo e(strtolower($row['event_title'] . ' ' . $row['venue'] . ' ' . $row['display_status'])); ?>"
                                data-status="<?php echo e($row['display_status']); ?>"
                                data-year="<?php echo e($row['event_year']); ?>"
                                data-month="<?php echo e($row['event_month']); ?>">
                                <td class="history-row-no"><?php echo e($historyNo++); ?></td>
                                <td><?php echo e($row['event_title']); ?></td>
                                <td><?php echo e(dmy($row['event_date'])); ?></td>
                                <td><?php echo e(t($row['event_time'])); ?> - <?php echo e(t($row['end_time'])); ?></td>
                                <td><?php echo e($row['venue']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo e(strtolower($row['display_status'])); ?>">
                                        <?php echo e($row['display_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($row['display_points']); ?></td>
                                <td><a href="student_event_view.php?id=<?php echo e($row['event_id']); ?>&from=history" class="btn-view-event"><i class="bi bi-eye"></i></a>
                                    <?php if ($row['is_completed']): ?>
                                        <span class="action-text-muted">Completed</span>
                                    <?php elseif ($row['registration_status'] === 'registered'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this registration?');">
                                            <input type="hidden" name="action" value="cancel"><input type="hidden" name="event_id" value="<?php echo e($row['event_id']); ?>">
                                            <button class="btn-register-event" type="submit">Cancel</button>
                                        </form>
                                    <?php elseif ($row['can_register_again']): ?>
                                        <a href="event_registration_form.php?id=<?php echo e($row['event_id']); ?>" class="btn-register-event text-decoration-none">Register</a>
                                    <?php elseif ($row['is_notified']): ?>
                                        <span class="btn-register-event notified-disabled">Notified</span>
                                    <?php elseif ($row['already_waiting']): ?>
                                        <span class="btn-register-event registered-disabled">Waiting</span>
                                    <?php elseif ($row['can_join_waiting']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Join waiting list for this event?');">
                                            <input type="hidden" name="action" value="waiting">
                                            <input type="hidden" name="event_id" value="<?php echo e($row['event_id']); ?>">
                                            <button class="btn-register-event" type="submit">Join Waiting List</button>
                                        </form>
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
        var rowsPerPage = 10;
        var currentPage = 1;

        function getFilteredRows() {
            var query = (document.getElementById('searchTitle').value || '').toLowerCase();
            var status = document.getElementById('statusFilter').value;
            var year = document.getElementById('yearFilter').value;
            var month = document.getElementById('monthFilter').value;
            var rows = Array.prototype.slice.call(document.getElementById('historyTableBody').querySelectorAll('tr[data-search]'));

            return rows.filter(function(row) {
                var matchSearch = row.dataset.search.indexOf(query) > -1;
                var matchStatus = status === 'all' || row.dataset.status === status;
                var matchYear = year === 'all' || row.dataset.year === year;
                var matchMonth = month === 'all' || row.dataset.month === month;
                return matchSearch && matchStatus && matchYear && matchMonth;
            });
        }

        function showPage(page) {
            var rows = Array.prototype.slice.call(document.getElementById('historyTableBody').querySelectorAll('tr[data-search]'));
            var filteredRows = getFilteredRows();
            var totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
            currentPage = Math.min(Math.max(page, 1), totalPages);

            rows.forEach(function(row) {
                row.style.display = 'none';
            });

            filteredRows.forEach(function(row, index) {
                var pageNumber = Math.floor(index / rowsPerPage) + 1;
                row.style.display = pageNumber === currentPage ? '' : 'none';
            });

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            var container = document.getElementById('paginationContainer');
            container.innerHTML = '';

            if (totalPages <= 1) {
                return;
            }

            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'page-btn-custom' + (currentPage === 1 ? ' disabled' : '');
            prevBtn.innerHTML = '&laquo;';
            prevBtn.onclick = function() {
                if (currentPage > 1) {
                    showPage(currentPage - 1);
                }
            };
            container.appendChild(prevBtn);

            for (var i = 1; i <= totalPages; i++) {
                var pageBtn = document.createElement('button');
                pageBtn.type = 'button';
                pageBtn.className = 'page-btn-custom' + (i === currentPage ? ' active' : '');
                pageBtn.textContent = i;
                pageBtn.onclick = (function(pageNumber) {
                    return function() {
                        showPage(pageNumber);
                    };
                })(i);
                container.appendChild(pageBtn);
            }

            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'page-btn-custom' + (currentPage === totalPages ? ' disabled' : '');
            nextBtn.innerHTML = '&raquo;';
            nextBtn.onclick = function() {
                if (currentPage < totalPages) {
                    showPage(currentPage + 1);
                }
            };
            container.appendChild(nextBtn);
        }

        function filterRows(tbodyId, q) {
            showPage(1);
        }

        document.addEventListener('DOMContentLoaded', function() {
            showPage(1);
            document.getElementById('statusFilter').addEventListener('change', function() {
                showPage(1);
            });
            document.getElementById('yearFilter').addEventListener('change', function() {
                showPage(1);
            });
            document.getElementById('monthFilter').addEventListener('change', function() {
                showPage(1);
            });
        });
    </script>
</body>

</html>