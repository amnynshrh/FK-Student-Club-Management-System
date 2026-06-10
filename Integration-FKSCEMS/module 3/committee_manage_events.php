<?php
session_start();
function db_connect(){ $conn=mysqli_connect('localhost','root','Amni102030.','fk_scems_db'); if(!$conn){ die('Database connection failed: '.mysqli_connect_error()); } mysqli_set_charset($conn,'utf8mb4'); return $conn; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function t($time){ return date('g:i A', strtotime($time)); }
function dmy($date){ return date('d M Y', strtotime($date)); }
function month_name($date){ return date('F', strtotime($date)); }
function logout_if_requested(){ if(($_GET['action']??'')==='logout'){ session_destroy(); header('Location: ../index.html'); exit; } }
function require_login($roles=[]){ if(empty($_SESSION['Login']) || $_SESSION['Login']!=='YES'){ header('Location: ../index.html'); exit; } if($roles && !in_array($_SESSION['role']??'', $roles, true)){ echo '<p style="padding:20px;color:#b00020;">Access denied.</p>'; exit; } }
function update_event_status($conn){ mysqli_query($conn,"UPDATE event SET event_status=CASE WHEN NOW()<CONCAT(event_date,' ',event_time) THEN 'upcoming' WHEN NOW() BETWEEN CONCAT(event_date,' ',event_time) AND CONCAT(event_date,' ',end_time) THEN 'ongoing' ELSE 'completed' END WHERE event_status!='cancelled'"); }
function badge($status){ $s=strtolower((string)$status); return '<span class="status-badge '.e($s).'">'.e(ucfirst($s)).'</span>'; }
logout_if_requested(); $conn=db_connect(); setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time()+86400, '/');

require_login(['committee']); update_event_status($conn); $message='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete'){ $id=$_POST['event_id']??''; if($id!==''){ $st=mysqli_prepare($conn,"UPDATE event SET event_status='cancelled', registration_open=0 WHERE event_id=?"); mysqli_stmt_bind_param($st,'i',$id); mysqli_stmt_execute($st); $message='Event deleted successfully.'; } }
$events=mysqli_query($conn,"SELECT e.*, c.club_name, COUNT(er.registration_id) registered_count FROM event e INNER JOIN club c ON c.club_id=e.club_id LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' GROUP BY e.event_id ORDER BY e.event_date DESC");
$total=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM event"))[0]??0; $upcoming=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM event WHERE event_status='upcoming'"))[0]??0; $completed=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM event WHERE event_status='completed'"))[0]??0;
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
      rel="stylesheet"
    />

    <!-- Bootstrap Icons -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
      rel="stylesheet"
    />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="committee.css" />

    <style>
      body {
        background-color: #ffffff;
      }

      .table > :not(caption) > * > * {
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
    <header class="top-navbar">
      <div class="nav-left">
        <img
          src="logo-fk.png"
          alt="FK Logo"
          class="nav-logo"
          onerror="this.src = 'logo-fk.png'"
        />

        <div class="nav-brand">FK Student Club &<br />Management System</div>
      </div>

      <div class="nav-right">
        <a href="../committeeDashboard.php" class="nav-link">Management</a>
        <a href="committee_manage_events.php" class="nav-link active-link">Manage Events</a>
        <a href="../manage_attendance.php" class="nav-link">Manage Attendance</a>
        <a href="../editProfile.php" class="nav-link">Profile</a>
        <a href="?action=logout" class="nav-link">Log Out</a>
      </div>
      <div class="committee-profile">Committee: <?php echo e($_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['SESS_USERNAME'] ?? 'Committee'); ?></div>
    </header>

    <!-- Main Content -->
    <main class="student-content">
      <!-- Title -->
      <h1 class="page-title">Manage Events</h1>

      <!-- Success Message -->
      <div id="successMessage" class="alert alert-success <?php echo $message ? '' : 'd-none'; ?>"><?php echo e($message); ?></div>

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
        <a href="committee_add_event.php" class="btn-add-event text-decoration-none">
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
            <option value="Upcoming">Upcoming</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
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
<?php while($row=mysqli_fetch_assoc($events)): ?>
<tr data-search="<?php echo e(strtolower($row['event_title'].' '.$row['venue'].' '.$row['event_status'])); ?>">
<td><?php echo e($row['event_title']); ?></td><td><?php echo e(dmy($row['event_date'])); ?></td><td><?php echo e(t($row['event_time'])); ?> - <?php echo e(t($row['end_time'])); ?></td><td><?php echo e($row['venue']); ?></td><td><?php echo e($row['registered_count']); ?>/<?php echo e($row['max_participant']); ?></td><td><?php echo badge($row['event_status']); ?></td>
<td><a href="event_view.php?mode=committee&id=<?php echo e($row['event_id']); ?>" class="action-btn view-btn text-decoration-none"><i class="bi bi-eye"></i></a><a href="committee_edit_event.php?id=<?php echo e($row['event_id']); ?>" class="action-btn edit-btn text-decoration-none"><i class="bi bi-pencil"></i></a><form method="POST" style="display:inline" onsubmit="return confirm('Delete this event?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?php echo e($row['event_id']); ?>"><button class="action-btn delete-btn" type="submit"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endwhile; ?></tbody>
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
  <script>function filterRows(tbodyId, q){ var rows=document.getElementById(tbodyId).querySelectorAll('tr[data-search]'); q=(q||'').toLowerCase(); rows.forEach(function(r){ r.style.display=r.dataset.search.indexOf(q)>-1?'':'none'; }); }</script>
</body>
</html>
