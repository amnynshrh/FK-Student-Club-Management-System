<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$committee_id = $_SESSION['SESS_COMMITTEE_ID'];
$total_events = 0;
$sql_total_events = "
SELECT 
COUNT(*) AS total_events
FROM event
WHERE committee_id = ?
";

$stmt_total = $conn->prepare($sql_total_events);
$stmt_total->bind_param("i", $committee_id);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_events = $result_total->fetch_assoc()['total_events'];

$upcoming_events = 0;
$sql_upcoming = "
SELECT 
COUNT(*) AS upcoming_events
FROM event
WHERE committee_id = ?
AND CONCAT(event_date, ' ', event_time) > NOW()
";

$stmt_upcoming = $conn->prepare($sql_upcoming);
$stmt_upcoming->bind_param("i", $committee_id);
$stmt_upcoming->execute();
$result_upcoming = $stmt_upcoming->get_result();
$upcoming_events = $result_upcoming->fetch_assoc()['upcoming_events'];

$completed_events = 0;
$sql_completed = "
SELECT 
COUNT(*) AS completed_events
FROM event
WHERE committee_id = ?
AND CONCAT(event_date, ' ', event_time) <= NOW()
";

$stmt_completed = $conn->prepare($sql_completed);
$stmt_completed->bind_param("i", $committee_id);
$stmt_completed->execute();
$result_completed = $stmt_completed->get_result();
$completed_events = $result_completed->fetch_assoc()['completed_events'];
?>
<!doctype html>
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
    <?php include('committeeHeader.php') ?>

    <!-- Main Content -->
    <main class="student-content">
      <!-- Title -->
      <h1 class="page-title">Manage Events</h1>

      <!-- Success Message -->
      <div id="successMessage" class="alert alert-success d-none">
        Event saved successfully.
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
            <h3 class="summary-number" id="totalEvents"><?php echo $total_events ?></h3>
          </div>
        </div>

        <!-- Upcoming Events -->
        <div class="summary-card">
          <div class="summary-icon teal">
            <i class="bi bi-alarm"></i>
          </div>

          <div>
            <p class="summary-label">Upcoming Events</p>
            <h3 class="summary-number" id="upcomingEvents"><?php echo $upcoming_events ?></h3>
          </div>
        </div>

        <!-- Completed Events -->
        <div class="summary-card">
          <div class="summary-icon navy">
            <i class="bi bi-calendar-check"></i>
          </div>

          <div>
            <p class="summary-label">Completed Events</p>
            <h3 class="summary-number" id="completedEvents"><?php echo $completed_events ?></h3>
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

          <input type="text" id="searchTitle" placeholder="Event Title" />
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
              <?php
              $committee_id = $_SESSION['SESS_COMMITTEE_ID'];
              $sql_events = "
              SELECT 
                  event_id,
                  event_title,
                  event_date,
                  event_time,
                  venue,
                  max_participant,
                  event_status
              FROM event
              WHERE committee_id = ?
              ORDER BY event_date DESC
              ";

              $stmt_events = $conn->prepare($sql_events);
              $stmt_events->bind_param("i", $committee_id);
              $stmt_events->execute();
              $result_events = $stmt_events->get_result();
              while($event = $result_events->fetch_assoc()) :
              ?>
              <tr>
                  <td>
                      <?php echo htmlspecialchars($event['event_title']); ?>
                  </td>
                  <td>
                      <?php echo date('d M Y', strtotime($event['event_date'])); ?>
                  </td>
                  <td>
                      <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                  </td>
                  <td>
                      <?php echo htmlspecialchars($event['venue']); ?>
                  </td>
                  <td>
                      <?php echo $event['max_participant']; ?>
                  </td>
                  <td>
                      <?php if ($event['event_status'] == 'Upcoming') : ?>
                          <span class="badge bg-primary">
                              Upcoming
                          </span>
                      <?php elseif ($event['event_status'] == 'Completed') : ?>
                          <span class="badge bg-success">
                              Completed
                          </span>
                      <?php else : ?>
                          <span class="badge bg-secondary">
                              <?php echo htmlspecialchars($event['event_status']); ?>
                          </span>
                      <?php endif; ?>
                  </td>
                  <td>
                      <a 
                          href="viewEvent.php?event_id=<?php echo $event['event_id']; ?>"
                          class="btn btn-sm btn-outline-primary"
                      >
                          View
                      </a>
                      <a 
                          href="editEvent.php?event_id=<?php echo $event['event_id']; ?>"
                          class="btn btn-sm btn-outline-warning"
                      >
                          Edit
                      </a>
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
    <script src="../assets/js/committee/manage_event.js?v=delete-message-fix-2"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
