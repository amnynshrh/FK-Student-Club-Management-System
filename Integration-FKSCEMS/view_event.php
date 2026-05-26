<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event Details- Committee</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="assets/css/committee.css?v=committee-detail-1">

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

    <?php include('committeeHeader.php') ?>

    <main class="student-content">
        <div class="view-event-page">

            <div class="view-event-header">
                <h1 class="page-title">View Event Details</h1>
                <p class="view-event-subtitle">Review complete event information and participation details.</p>
            </div>

            <section class="view-event-card">

                <div class="view-event-top">
                    <div>
                        <h2 class="view-event-name">Loading event...</h2>
                        <p class="view-event-organizer">
                            <i class="bi bi-person-badge"></i>
                            Organizer: -
                        </p>
                    </div>
                </div>

                <div class="view-event-details-grid">

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Event Date</p>
                            <p class="view-detail-value">-</p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Capacity</p>
                            <p class="view-detail-value">-</p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Event Time</p>
                            <p class="view-detail-value">-</p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Status</p>
                            <span class="status-badge view-detail-status upcoming">-</span>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon navy">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">Venue</p>
                            <p class="view-detail-value">-</p>
                        </div>
                    </div>

                    <div class="view-detail-item">
                        <div class="view-detail-icon teal">
                            <i class="bi bi-card-text"></i>
                        </div>
                        <div>
                            <p class="view-detail-label">About Event</p>
                            <p class="view-detail-value">-</p>
                        </div>
                    </div>

                </div>

                <div class="view-event-actions">
                    <a href="manage_events.php" class="view-event-back-btn">
                        <i class="bi bi-arrow-left"></i>
                        Back
                    </a>
                </div>

            </section>

            <section class="view-event-card participant-list-card">
                <div class="participant-section-header">
                    <div>
                        <h2 class="participant-section-title">Participant List</h2>
                        <p class="view-event-subtitle">Students who registered for this event.</p>
                    </div>
                    <span class="participant-count-pill" id="participantListCount">0 registered</span>
                </div>

                <div class="table-responsive">
                    <table class="event-table-custom">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Matric</th>
                                <th>Course</th>
                                <th>Email</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody id="participantTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Loading participants...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="view-event-card participant-list-card">
                <div class="participant-section-header">
                    <div>
                        <h2 class="participant-section-title">Waiting List</h2>
                        <p class="view-event-subtitle">Students waiting for an available slot.</p>
                    </div>
                    <span class="participant-count-pill" id="waitingListCount">0 waiting</span>
                </div>

                <div class="table-responsive">
                    <table class="event-table-custom">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Matric</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="waitingTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Loading waiting list...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/committee/view_event.js?v=committee-detail-2"></script>

</body>

</html>
