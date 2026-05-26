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
    <link rel="stylesheet" href="assets/css/committee.css" />

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
            <h3 class="summary-number" id="totalEvents">0</h3>
          </div>
        </div>

        <!-- Upcoming Events -->
        <div class="summary-card">
          <div class="summary-icon teal">
            <i class="bi bi-alarm"></i>
          </div>

          <div>
            <p class="summary-label">Upcoming Events</p>
            <h3 class="summary-number" id="upcomingEvents">0</h3>
          </div>
        </div>

        <!-- Completed Events -->
        <div class="summary-card">
          <div class="summary-icon navy">
            <i class="bi bi-calendar-check"></i>
          </div>

          <div>
            <p class="summary-label">Completed Events</p>
            <h3 class="summary-number" id="completedEvents">0</h3>
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
              <!-- Event data will be loaded from localStorage using JavaScript -->
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
    <script src="assets/js/committee/manage_event.js?v=delete-message-fix-2"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
