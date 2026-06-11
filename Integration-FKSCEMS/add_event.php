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
  $sessionRole = strtolower((string) ($_SESSION['role'] ?? ''));
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
function event_end_datetime($date, $startTime, $endTime)
{
  $start = new DateTime($date . ' ' . $startTime);
  $end = new DateTime($date . ' ' . $endTime);
  if ($end <= $start) {
    $end->modify('+1 day');
  }
  return $end;
}
function determine_event_status($date, $startTime, $endTime, $registrationOpen)
{
  $now = new DateTime();
  $start = new DateTime($date . ' ' . $startTime);
  $end = event_end_datetime($date, $startTime, $endTime);

  if ($now > $end) {
    return 'completed';
  }
  if ($now >= $start && $now <= $end) {
    return 'ongoing';
  }
  return $registrationOpen === '1' ? 'open' : 'upcoming';
}
function has_booking_clash($conn, $date, $startTime, $endTime, $venue, $excludeEventId = 0)
{
  $newStart = $date . ' ' . $startTime;
  $newEnd = event_end_datetime($date, $startTime, $endTime)->format('Y-m-d H:i:s');

  $sql = "
    SELECT event_id
    FROM event
    WHERE LOWER(TRIM(venue)) = LOWER(TRIM(?))
      AND event_status != 'cancelled'
      AND ? < (
        CASE
          WHEN end_time <= event_time
          THEN DATE_ADD(CONCAT(event_date, ' ', end_time), INTERVAL 1 DAY)
          ELSE CONCAT(event_date, ' ', end_time)
        END
      )
      AND ? > CONCAT(event_date, ' ', event_time)
      AND event_id <> ?
    LIMIT 1
  ";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, 'sssi', $venue, $newStart, $newEnd, $excludeEventId);
  mysqli_stmt_execute($stmt);
  return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}
logout_if_requested();
$conn = db_connect();
setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time() + 86400, '/');

require_login(['committee']);
update_event_status($conn);
ensure_registration_open_column($conn);
$message = '';

$committeeId = $_SESSION['SESS_COMMITTEE_ID'] ?? 0;
if (empty($committeeId)) {
  $userId = $_SESSION['SESS_USER_ID'] ?? $_SESSION['user_id'] ?? 0;
  $committeeStmt = mysqli_prepare($conn, "SELECT c.committee_id, c.club_id FROM student s INNER JOIN membership m ON s.matric_number=m.matric_number INNER JOIN committee c ON c.membership_id=m.membership_id WHERE s.user_id=? LIMIT 1");
  mysqli_stmt_bind_param($committeeStmt, 'i', $userId);
  mysqli_stmt_execute($committeeStmt);
  $committeeRow = mysqli_fetch_assoc(mysqli_stmt_get_result($committeeStmt));
  $committeeId = $committeeRow['committee_id'] ?? 0;
  $clubId = $committeeRow['club_id'] ?? 0;
  if ($committeeId) {
    $_SESSION['SESS_COMMITTEE_ID'] = $committeeId;
  }
}

$clubId = $clubId ?? 0;
if ($committeeId) {
  $clubStmt = mysqli_prepare($conn, "SELECT club_id FROM committee WHERE committee_id=? LIMIT 1");
  mysqli_stmt_bind_param($clubStmt, 'i', $committeeId);
  mysqli_stmt_execute($clubStmt);
  $clubRow = mysqli_fetch_assoc(mysqli_stmt_get_result($clubStmt));
  $clubId = $clubRow['club_id'] ?? 0;
}

$event = ['title' => '', 'description' => '', 'date' => '', 'startTime' => '', 'endTime' => '', 'venue' => '', 'participants' => '', 'registration_open' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $event = [
    'title' => trim($_POST['title'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'date' => $_POST['date'] ?? '',
    'startTime' => $_POST['startTime'] ?? '',
    'endTime' => $_POST['endTime'] ?? '',
    'venue' => trim($_POST['venue'] ?? ''),
    'participants' => $_POST['participants'] ?? '',
    'registration_open' => ($_POST['registration_open'] ?? '0') === '1' ? '1' : '0'
  ];

  if ($event['title'] === '' || $event['description'] === '' || $event['date'] === '' || $event['startTime'] === '' || $event['endTime'] === '' || $event['venue'] === '' || $event['participants'] === '') {
    $message = 'Please fill in all required fields.';
  } elseif ($event['endTime'] === $event['startTime']) {
    $message = 'End time cannot be the same as start time.';
  } elseif (has_booking_clash($conn, $event['date'], $event['startTime'], $event['endTime'], $event['venue'])) {
    $message = 'Booking clash detected. This venue is already booked during the selected time.';
  } elseif (!$committeeId || !$clubId) {
    $message = 'Committee club information was not found. Please login again.';
  } else {
    $status = determine_event_status($event['date'], $event['startTime'], $event['endTime'], $event['registration_open']);
    $stmt = mysqli_prepare($conn, "INSERT INTO event (club_id, committee_id, event_title, event_description, event_date, event_time, end_time, venue, max_participant, event_status, registration_open) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iissssssisi', $clubId, $committeeId, $event['title'], $event['description'], $event['date'], $event['startTime'], $event['endTime'], $event['venue'], $event['participants'], $status, $event['registration_open']);

    if (mysqli_stmt_execute($stmt)) {
      header('Location: manage_events.php?success=event_added');
      exit;
    }
    $message = 'Event save failed: ' . mysqli_stmt_error($stmt);
  }
}
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
  <link rel="stylesheet" href="committee.css" />

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
  </style>
</head>

<body>
  <?php include('committeeHeader.php') ?>

  <!-- Main Content -->
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
      <form id="eventForm" method="POST" action="add_event.php" onsubmit="return validateEventForm();" novalidate>
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

          <div class="mb-4">
            <label class="form-label-custom">
              Student Registration <span class="required-star">*</span>
            </label>

            <select name="registration_open" id="registrationOpen" class="form-control form-control-custom" required>
              <option value="1" <?php echo ($event['registration_open'] ?? '1') === '1' ? 'selected' : ''; ?>>Open Registration</option>
              <option value="0" <?php echo ($event['registration_open'] ?? '1') === '0' ? 'selected' : ''; ?>>Close Registration</option>
            </select>

            <small class="error-message" id="registrationOpenError"></small>
          </div>


          <!-- Divider -->
          <div class="section-divider"></div>

          <!-- Buttons -->
          <div class="button-group-custom">
            <a href="manage_events.php" class="btn-cancel-custom"> Cancel </a>

            <button type="submit" class="btn-save-custom">
              <i class="bi bi-check-circle me-2"></i>
              Save Event
            </button>
          </div>
      </form>
    </div>
  </main>

  <!-- Custom JavaScript -->

  <!-- Bootstrap JS -->
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
      if (end === start) {
        if (box) {
          box.classList.remove('d-none');
          box.innerHTML = 'End time cannot be the same as start time.';
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