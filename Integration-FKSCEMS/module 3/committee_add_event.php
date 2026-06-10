<?php
session_start();
function db_connect()
{
  $conn = mysqli_connect('localhost', 'root', 'Amni102030.', 'fk_scems_db');
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
    header('Location: ../index.html');
    exit;
  }
}
function require_login($roles = [])
{
  if (empty($_SESSION['Login']) || $_SESSION['Login'] !== 'YES') {
    header('Location: ../index.html');
    exit;
  }
  if ($roles && !in_array($_SESSION['role'] ?? '', $roles, true)) {
    echo '<p style="padding:20px;color:#b00020;">Access denied.</p>';
    exit;
  }
}
function update_event_status($conn)
{
  mysqli_query($conn, "UPDATE event SET event_status=CASE WHEN NOW()<CONCAT(event_date,' ',event_time) THEN 'upcoming' WHEN NOW() BETWEEN CONCAT(event_date,' ',event_time) AND CONCAT(event_date,' ',end_time) THEN 'ongoing' ELSE 'completed' END WHERE event_status!='cancelled'");
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
$message = '';
$event = ['title' => '', 'description' => '', 'date' => '', 'startTime' => '', 'endTime' => '', 'venue' => '', 'participants' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $event = ['title' => trim($_POST['title'] ?? ''), 'description' => trim($_POST['description'] ?? ''), 'date' => $_POST['date'] ?? '', 'startTime' => $_POST['startTime'] ?? '', 'endTime' => $_POST['endTime'] ?? '', 'venue' => trim($_POST['venue'] ?? ''), 'participants' => $_POST['participants'] ?? ''];
  if ($event['title'] === '' || $event['description'] === '' || $event['date'] === '' || $event['startTime'] === '' || $event['endTime'] === '' || $event['venue'] === '' || $event['participants'] === '') {
    $message = 'Please fill in all required fields.';
  } elseif ($event['endTime'] <= $event['startTime']) {
    $message = 'End time must be after start time.';
  } else {
    $status = date('Y-m-d H:i:s') < $event['date'] . ' ' . $event['startTime'] ? 'upcoming' : 'ongoing';
    $clubId = 1;
    $committeeId = 1;
    $st = mysqli_prepare($conn, "INSERT INTO event (club_id,committee_id,event_title,event_description,event_date,event_time,end_time,venue,max_participant,event_status) VALUES (?,?,?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'iissssssis', $clubId, $committeeId, $event['title'], $event['description'], $event['date'], $event['startTime'], $event['endTime'], $event['venue'], $event['participants'], $status);
    mysqli_stmt_execute($st);
    header('Location: ../manage_events.php?success=event_added');
    exit;
  }
}
?>
<!DOCTYPE html>

<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add New Event - Committee</title>

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

  <!-- Custom Theme -->
  <link rel="stylesheet" href="committee.css" />

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
  </style>
</head>

<body>
  <header class="top-navbar">
    <div class="nav-left">
      <img
        src="logo-fk.png"
        alt="FK Logo"
        class="nav-logo"
        onerror="this.src = 'logo-fk.png'" />

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

  <main class="student-content">
    <div id="formMessage" class="alert alert-danger <?php echo $message ? '' : 'd-none'; ?>"><?php echo e($message); ?></div>
    <div class="page-header">
      <h1 class="page-title">Add New Event</h1>

      <p class="page-subtitle">
        Create and publish a new event for FK students.
      </p>
    </div>

    <!-- Form Card -->
    <div class="event-form-card">
      <form id="eventForm" method="POST" action="committee_add_event.php" onsubmit="return validateEventForm();" novalidate>
        <!-- Event Title -->
        <div class="mb-4">
          <label class="form-label-custom">
            Event Title <span class="required-star">*</span>
          </label>

          <input
            type="text"
            id="eventTitle" name="title" required value="<?php echo e($event['title'] ?? ''); ?>"
            class="form-control form-control-custom"
            placeholder="Enter event title" />

          <small class="error-message" id="titleError"></small>
        </div>

        <!-- Description -->
        <div class="mb-4">
          <label class="form-label-custom">
            Description <span class="required-star">*</span>
          </label>

          <textarea
            id="eventDescription" name="description" required
            class="form-control form-control-custom textarea-custom"
            placeholder="Enter event description"><?php echo e($event['description'] ?? ''); ?></textarea>

          <small class="error-message" id="descriptionError"></small>
        </div>

        <!-- Date & Time -->
        <div class="row">
          <!-- Event Date -->
          <div class="col-md-4 mb-4">
            <label class="form-label-custom">
              Event Date <span class="required-star">*</span>
            </label>

            <div class="custom-input-wrapper">
              <input
                type="date"
                id="eventDate" name="date" required value="<?php echo e($event['date'] ?? ''); ?>"
                class="form-control-custom custom-date-input" />

              <button type="button" class="input-icon-btn">
                <i class="bi bi-calendar-event"></i>
              </button>
            </div>

            <small class="error-message" id="dateError"></small>
          </div>

          <!-- Start Time -->
          <div class="col-md-4 mb-4">
            <label class="form-label-custom">
              Start Time <span class="required-star">*</span>
            </label>

            <div class="custom-input-wrapper">
              <input
                type="time"
                id="eventStartTime" name="startTime" required value="<?php echo e($event['startTime'] ?? ''); ?>"
                class="form-control-custom custom-time-input" />

              <button type="button" class="input-icon-btn">
                <i class="bi bi-clock"></i>
              </button>
            </div>

            <small class="error-message" id="startTimeError"></small>
          </div>

          <!-- End Time -->
          <div class="col-md-4 mb-4">
            <label class="form-label-custom">
              End Time <span class="required-star">*</span>
            </label>

            <div class="custom-input-wrapper">
              <input
                type="time"
                id="eventEndTime" name="endTime" required value="<?php echo e($event['endTime'] ?? ''); ?>"
                class="form-control-custom custom-time-input" />

              <button type="button" class="input-icon-btn">
                <i class="bi bi-clock"></i>
              </button>
            </div>

            <small class="error-message" id="endTimeError"></small>
          </div>
        </div>

        <!-- Venue -->
        <div class="mb-4">
          <label class="form-label-custom">
            Venue <span class="required-star">*</span>
          </label>

          <input
            type="text"
            id="eventVenue" name="venue" required value="<?php echo e($event['venue'] ?? ''); ?>"
            class="form-control form-control-custom"
            placeholder="Enter venue" />

          <small class="error-message" id="venueError"></small>
        </div>

        <!-- Participants & Status -->
        <div class="row">

          <!-- Max Participants -->
          <div class="mb-4">
            <label class="form-label-custom">
              Max Participants <span class="required-star">*</span>
            </label>

            <input
              type="number"
              id="eventParticipants" name="participants" min="1" required value="<?php echo e($event['participants'] ?? ''); ?>"
              class="form-control form-control-custom"
              placeholder="Enter maximum participants" />

            <small class="error-message" id="participantsError"></small>
          </div>


          <!-- Divider -->
          <div class="section-divider"></div>

          <!-- Buttons -->
          <div class="button-group-custom">
            <a href="committee_manage_events.php" class="btn-cancel-custom"> Cancel </a>

            <button type="submit" class="btn-save-custom">
              <i class="bi bi-check-circle me-2"></i>
              Save Event
            </button>
          </div>
      </form>
    </div>
  </main>

  <!-- Custom JavaScript -->

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function validateEventForm() {
      var title = document.getElementById('eventTitle').value.trim();
      var desc = document.getElementById('eventDescription').value.trim();
      var date = document.getElementById('eventDate').value;
      var start = document.getElementById('eventStartTime').value;
      var end = document.getElementById('eventEndTime').value;
      var venue = document.getElementById('eventVenue').value.trim();
      var participants = document.getElementById('eventParticipants').value;
      var box = document.getElementById('formMessage');
      if (box) {
        box.classList.add('d-none');
        box.innerHTML = '';
      }
      if (title === '' || desc === '' || date === '' || start === '' || end === '' || venue === '' || participants === '') {
        if (box) {
          box.classList.remove('d-none');
          box.innerHTML = 'Please fill in all required fields.';
        }
        return false;
      }
      if (end <= start) {
        if (box) {
          box.classList.remove('d-none');
          box.innerHTML = 'End time must be after start time.';
        }
        return false;
      }
      if (Number(participants) <= 0) {
        if (box) {
          box.classList.remove('d-none');
          box.innerHTML = 'Maximum participants must be more than 0.';
        }
        return false;
      }
      return true;
    }
  </script>
</body>

</html>
