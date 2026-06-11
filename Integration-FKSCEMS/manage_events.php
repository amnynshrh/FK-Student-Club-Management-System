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
  $sessionRole = strtolower((string)($_SESSION['role'] ?? ''));
  $allowedRoles = array_map('strtolower', $roles);
  if ($roles && !in_array($sessionRole, $allowedRoles, true)) {
    echo '<p style="padding:20px;color:#b00020;">Access denied.</p>';
    exit;
  }
}
function update_event_status($conn)
{
  mysqli_query($conn, "UPDATE event SET event_status=CASE WHEN NOW()>(CASE WHEN end_time<=event_time THEN DATE_ADD(CONCAT(event_date,' ',end_time), INTERVAL 1 DAY) ELSE CONCAT(event_date,' ',end_time) END) THEN 'completed' WHEN NOW() BETWEEN CONCAT(event_date,' ',event_time) AND (CASE WHEN end_time<=event_time THEN DATE_ADD(CONCAT(event_date,' ',end_time), INTERVAL 1 DAY) ELSE CONCAT(event_date,' ',end_time) END) THEN 'ongoing' WHEN (SELECT COUNT(*) FROM eventregistration er WHERE er.event_id=event.event_id AND er.registration_status='registered')>=max_participant THEN 'full' WHEN registration_open=1 THEN 'open' WHEN NOW()<CONCAT(event_date,' ',event_time) THEN 'upcoming' ELSE 'completed' END WHERE event_status!='cancelled'");
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

require_login(['committee']);
ensure_registration_open_column($conn);
update_event_status($conn);
$message = '';
if (isset($_GET['success']) && $_GET['success'] === 'event_added') {
  $message = 'Event added successfully.';
} elseif (isset($_GET['success']) && $_GET['success'] === 'event_updated') {
  $message = 'Event updated successfully.';
} elseif (isset($_GET['success']) && $_GET['success'] === 'event_cancelled') {
  $message = 'Event cancelled successfully.';
} elseif (isset($_GET['error']) && $_GET['error'] === 'completed_event') {
  $message = 'Completed events cannot be edited.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = $_POST['event_id'] ?? '';
  if ($id !== '' && $action === 'cancel') {
    $st = mysqli_prepare($conn, "UPDATE event SET event_status='cancelled', registration_open=0 WHERE event_id=?");
    mysqli_stmt_bind_param($st, 'i', $id);
    mysqli_stmt_execute($st);
    $message = 'Event cancelled successfully.';
  } elseif ($id !== '' && $action === 'delete') {
    mysqli_begin_transaction($conn);
    $ok = true;

    $st = mysqli_prepare($conn, "DELETE a FROM attendance a INNER JOIN eventregistration er ON er.registration_id=a.registration_id WHERE er.event_id=?");
    mysqli_stmt_bind_param($st, 'i', $id);
    $ok = $ok && mysqli_stmt_execute($st);

    $st = mysqli_prepare($conn, "DELETE FROM eventwaitinglist WHERE event_id=?");
    mysqli_stmt_bind_param($st, 'i', $id);
    $ok = $ok && mysqli_stmt_execute($st);

    $st = mysqli_prepare($conn, "DELETE FROM eventregistration WHERE event_id=?");
    mysqli_stmt_bind_param($st, 'i', $id);
    $ok = $ok && mysqli_stmt_execute($st);

    $st = mysqli_prepare($conn, "DELETE FROM event WHERE event_id=?");
    mysqli_stmt_bind_param($st, 'i', $id);
    $ok = $ok && mysqli_stmt_execute($st);

    if ($ok) {
      mysqli_commit($conn);
      $message = 'Event deleted from database successfully.';
    } else {
      mysqli_rollback($conn);
      $message = 'Event delete failed: ' . mysqli_error($conn);
    }
  }
}
$events = mysqli_query($conn, "SELECT e.*, c.club_name, COUNT(er.registration_id) registered_count FROM event e INNER JOIN club c ON c.club_id=e.club_id LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' GROUP BY e.event_id ORDER BY e.event_date DESC");
$total = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM event"))[0] ?? 0;
$upcoming = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM event WHERE event_status='upcoming'"))[0] ?? 0;
$completed = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM event WHERE event_status='completed'"))[0] ?? 0;
?>
<!DOCTYPE html>

<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Event - Committee</title>

  <!-- Bootstrap 5 CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet" />

  <!-- Bootstrap Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet" />

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Custom Theme UMPSA -->
  <link rel="stylesheet" href="committee.css?v=committee-status-2" />

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

    .status-badge.open {
      background-color: #009e96;
      color: #ffffff;
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

    .action-buttons {
      display: inline-flex;
      gap: 8px;
      align-items: center;
    }

    .action-form {
      display: inline-block;
      margin: 0;
    }

  </style>
</head>

<body>
  <?php include('committeeHeader.php') ?>

  <!-- Main Content -->
  <main class="student-content">
    <!-- Title -->
    <h1 class="page-title">Manage Events</h1>

    <div id="successMessage" class="alert alert-success alert-dismissible fade show <?php echo $message ? '' : 'd-none'; ?>" role="alert">
      <strong>Success!</strong> <?php echo e($message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards-custom">
      <!-- Total Events -->
      <div class="summary-card">
        <div class="summary-icon navy">
          <i class="bi bi-bar-chart-line"></i>
        </div>

        <div>
          <p class="summary-label">Total Events</p>
          <h3 class="summary-number" id="totalEvents"><?php echo e($total); ?></h3>
        </div>
      </div>

      <!-- Upcoming Events -->
      <div class="summary-card">
        <div class="summary-icon teal">
          <i class="bi bi-alarm"></i>
        </div>

        <div>
          <p class="summary-label">Upcoming Events</p>
          <h3 class="summary-number" id="upcomingEvents"><?php echo e($upcoming); ?></h3>
        </div>
      </div>

      <!-- Completed Events -->
      <div class="summary-card">
        <div class="summary-icon navy">
          <i class="bi bi-calendar-check"></i>
        </div>

        <div>
          <p class="summary-label">Completed Events</p>
          <h3 class="summary-number" id="completedEvents"><?php echo e($completed); ?></h3>
        </div>
      </div>
    </div>

    <!-- Controls -->
    <div class="controls-row-custom">
      <!-- Search -->
      <div class="search-box-custom">
        <span class="search-icon-custom">
          <i class="bi bi-search"></i>
        </span>

        <input type="text" id="searchTitle" placeholder="Event Title" oninput="filterRows('eventTableBody', this.value)" />
      </div>

      <!-- Add Button -->
      <a href="add_event.php" class="btn-add-event text-decoration-none">
        <i class="bi bi-plus-circle me-2"></i>
        Add New Event
      </a>
    </div>

    <!-- Table Card -->
    <div class="table-card-custom">
      <!-- Filters -->
      <div class="table-filter-row">
        <select class="filter-select-custom" id="statusFilter">
          <option value="all">Show All Status</option>
          <option value="Open">Open</option>
          <option value="Upcoming">Upcoming</option>
          <option value="Ongoing">Ongoing</option>
          <option value="Full">Full</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>

        <select class="filter-select-custom" id="monthFilter">
          <option value="all">List of month</option>
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
              <th>Max Participants</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody id="eventTableBody">
            <?php $eventNo = 1; ?>
            <?php while ($row = mysqli_fetch_assoc($events)): ?>
              <?php
              $displayStatus = ((int) ($row['registration_open'] ?? 1) === 1 && in_array($row['event_status'], ['open', 'upcoming'], true))
                ? 'Open'
                : ucfirst($row['event_status']);
              ?>
              <tr data-search="<?php echo e(strtolower($row['event_title'] . ' ' . $row['venue'] . ' ' . $displayStatus)); ?>" data-status="<?php echo e(strtolower($displayStatus)); ?>" data-month="<?php echo e(date('F', strtotime($row['event_date']))); ?>">
                <td class="event-row-no"><?php echo e($eventNo++); ?></td>
                <td><?php echo e($row['event_title']); ?></td>
                <td><?php echo e(dmy($row['event_date'])); ?></td>
                <td><?php echo e(t($row['event_time'])); ?> - <?php echo e(t($row['end_time'])); ?></td>
                <td><?php echo e($row['venue']); ?></td>
                <td><?php echo e($row['registered_count']); ?>/<?php echo e($row['max_participant']); ?></td>
                <td><?php echo badge($displayStatus); ?></td>
                <td>
                  <div class="action-buttons">
                    <a href="view_event.php?mode=committee&id=<?php echo e($row['event_id']); ?>" class="action-btn view-btn" title="View Event"><i class="bi bi-eye"></i></a>
                    <?php if (strtolower((string)$row['event_status']) !== 'completed'): ?>
                      <a href="edit_event.php?id=<?php echo e($row['event_id']); ?>" class="action-btn edit-btn" title="Edit Event"><i class="bi bi-pencil"></i></a>
                    <?php endif; ?>
                    <?php if (!in_array(strtolower((string)$row['event_status']), ['cancelled', 'completed'], true)): ?>
                      <form method="POST" class="action-form" onsubmit="return confirm('Cancel this event? Students will see it as cancelled.');">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="event_id" value="<?php echo e($row['event_id']); ?>">
                        <button class="action-btn cancel-btn" type="submit" title="Cancel Event"><i class="bi bi-slash-circle"></i></button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" class="action-form" onsubmit="return confirm('Delete this event permanently from database?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="event_id" value="<?php echo e($row['event_id']); ?>">
                      <button class="action-btn delete-btn" type="submit" title="Delete Event"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-custom" id="paginationContainer">
        <!-- Pagination buttons will be generated by JavaScript -->
      </div>
    </div>
  </main>

  <!-- Custom JavaScript -->

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    var currentPage = 1;
    var rowsPerPage = 10;

    function getFilteredRows(tbodyId) {
      return Array.from(document.getElementById(tbodyId).querySelectorAll('tr[data-search]')).filter(function(r) {
        return r.style.display !== 'none';
      });
    }

    function showPage(page) {
      var rows = Array.from(document.getElementById('eventTableBody').querySelectorAll('tr[data-search]'));
      var query = document.getElementById('searchTitle').value.toLowerCase();
      var statusFilter = document.getElementById('statusFilter').value.toLowerCase();
      var monthFilter = document.getElementById('monthFilter').value.toLowerCase();

      var filteredRows = rows.filter(function(r) {
        var matchesSearch = r.dataset.search.indexOf(query) > -1;
        var matchesStatus = statusFilter === 'all' || r.dataset.status === statusFilter;
        var matchesMonth = monthFilter === 'all' || r.dataset.month.toLowerCase() === monthFilter;
        return matchesSearch && matchesStatus && matchesMonth;
      });

      var totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
      currentPage = Math.min(Math.max(page, 1), totalPages);

      rows.forEach(function(r) {
        r.style.display = 'none';
      });

      filteredRows.forEach(function(r, index) {
        var pageIndex = Math.floor(index / rowsPerPage) + 1;
        r.style.display = pageIndex === currentPage ? '' : 'none';
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
      prevBtn.className = 'pagination-btn' + (currentPage === 1 ? ' disabled' : '');
      prevBtn.textContent = 'Previous';
      prevBtn.onclick = function() {
        if (currentPage > 1) showPage(currentPage - 1);
      };
      container.appendChild(prevBtn);

      for (var i = 1; i <= totalPages; i++) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.className = 'pagination-btn' + (i === currentPage ? ' active' : '');
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
      nextBtn.className = 'pagination-btn' + (currentPage === totalPages ? ' disabled' : '');
      nextBtn.textContent = 'Next';
      nextBtn.onclick = function() {
        if (currentPage < totalPages) showPage(currentPage + 1);
      };
      container.appendChild(nextBtn);
    }

    function filterRows(tbodyId, q) {
      showPage(1);
    }

    document.addEventListener('DOMContentLoaded', function() {
      showPage(1);
      document.getElementById('searchTitle').addEventListener('input', function() {
        showPage(1);
      });
      document.getElementById('statusFilter').addEventListener('change', function() {
        showPage(1);
      });
      document.getElementById('monthFilter').addEventListener('change', function() {
        showPage(1);
      });
    });
  </script>
</body>

</html>
