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
    <link rel="stylesheet" href="assets/css/student.css?v=student-waiting-1">

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
    
    <?php include 'studentHeader.php'; ?>
    
    <!-- Main Content -->
    <main class="student-content">

        <!-- Title -->
        <h1 class="page-title">Event Registration</h1>
        <div id="successMessage" class="alert alert-success d-none" role="alert"></div>

        <!-- Summary Cards - SAME THEME AS COMMITTEE -->
        <div class="summary-cards-custom">

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <p class="summary-label">Available Events</p>
                    <h3 class="summary-number" id="availableEvents">0</h3>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon teal">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div>
                    <p class="summary-label">Registered</p>
                    <h3 class="summary-number" id="registeredEvents">0</h3>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div>
                    <p class="summary-label">Waiting List</p>
                    <h3 class="summary-number" id="waitingEvents">0</h3>
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
                </select>

                <select class="filter-select-custom" id="statusFilter">
                    <option value="all">Show All Status</option>
                    <option value="Open">Open</option>
                    <option value="Full">Full</option>
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
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Loading events...</td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <!-- Pagination -->
            <div class="pagination-custom" id="paginationContainer">

                <button class="page-btn-custom">
                    &laquo;
                </button>

                <button class="page-btn-custom active">
                    1
                </button>

                <button class="page-btn-custom">
                    2
                </button>

                <button class="page-btn-custom">
                    3
                </button>

                <button class="page-btn-custom">
                    &raquo;
                </button>

            </div>

        </div>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/student/event_registration.js?v=student-waiting-3"></script>
</body>

</html>
