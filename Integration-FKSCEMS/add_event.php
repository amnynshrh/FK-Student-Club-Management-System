<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $committee_id = $_SESSION['SESS_COMMITTEE_ID'];
    $sql_club = "
    SELECT club_id
    FROM committee
    WHERE committee_id = ?
    LIMIT 1
    ";

    $stmt_club = $conn->prepare($sql_club);
    $stmt_club->bind_param("i", $committee_id);
    $stmt_club->execute();
    $result_club = $stmt_club->get_result();
    $club_data = $result_club->fetch_assoc();

    $club_id = $club_data['club_id'];
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $max_participant = $_POST['max_participant'];
    $event_status = "Upcoming";
    $qr_code = uniqid("EVENT_");

    $sql_insert = "
    INSERT INTO event (
        club_id,
        committee_id,
        event_title,
        event_description,
        event_date,
        event_time,
        venue,
        max_participant,
        event_status,
        qr_code
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param(
        "iissssisss",
        $club_id,
        $committee_id,
        $event_title,
        $event_description,
        $event_date,
        $event_time,
        $venue,
        $max_participant,
        $event_status,
        $qr_code
    );

    if ($stmt_insert->execute()) {
        header("Location: manageEvents.php?success=1");
        exit();
    } else {
        echo "Insert Error: " . $conn->error;
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add New Event - Committee</title>

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

    <!-- Custom Theme -->
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
    </style>
  </head>

  <body>
    <?php include('committeeHeader.php') ?>

    <main class="student-content">
      <div class="page-header">
        <h1 class="page-title">Add New Event</h1>

        <p class="page-subtitle">
          Create and publish a new event for FK students.
        </p>
      </div>

      <!-- Form Card -->
      <div class="event-form-card">
        <form method="POST" id="addEventForm" novalidate>
          <!-- Event Title -->
          <div class="mb-4">
            <label class="form-label-custom">
              Event Title <span class="required-star">*</span>
            </label>

            <input
              type="text"
              id="eventTitle"
              name="event_title"
              class="form-control form-control-custom"
              placeholder="Enter event title"
            />

            <small class="error-message" id="titleError"></small>
          </div>

          <!-- Description -->
          <div class="mb-4">
            <label class="form-label-custom">
              Description <span class="required-star">*</span>
            </label>

            <textarea
              id="eventDescription"
              name="event_description"
              class="form-control form-control-custom textarea-custom"
              placeholder="Enter event description"
            ></textarea>

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
                  id="eventDate"
                  name="event_date"
                  class="form-control-custom custom-date-input"
                />

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
                  id="eventStartTime"
                  name="event_time"
                  class="form-control-custom custom-time-input"
                />

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
                  id="eventEndTime"
                  name="event_end_time"
                  class="form-control-custom custom-time-input"
                />

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
              id="eventVenue"
              name="venue"
              class="form-control form-control-custom"
              placeholder="Enter venue"
            />

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
    id="eventParticipants"
    name="max_participant"
    class="form-control form-control-custom"
    placeholder="Enter maximum participants"
  />

  <small class="error-message" id="participantsError"></small>
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
    <script src="../assets/js/committee/add_event.js"></script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
