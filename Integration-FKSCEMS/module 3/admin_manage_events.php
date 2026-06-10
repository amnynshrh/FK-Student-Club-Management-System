<?php
session_start();
function db_connect(){ $conn=mysqli_connect('localhost','root','Amni102030.','fk_scems_db'); if(!$conn){ die('Database connection failed: '.mysqli_connect_error()); } mysqli_set_charset($conn,'utf8mb4'); return $conn; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function t($time){ return date('g:i A', strtotime($time)); }
function dmy($date){ return date('d M Y', strtotime($date)); }
function logout_if_requested(){ if(($_GET['action']??'')==='logout'){ session_destroy(); header('Location: ../index.html'); exit; } }
function require_login($roles=[]){ if(empty($_SESSION['Login']) || $_SESSION['Login']!=='YES'){ header('Location: ../index.html'); exit; } if($roles && !in_array($_SESSION['role']??'', $roles, true)){ echo '<p style="padding:20px;color:#b00020;">Access denied.</p>'; exit; } }
function update_event_status($conn){ mysqli_query($conn,"UPDATE event SET event_status=CASE WHEN NOW()<CONCAT(event_date,' ',event_time) THEN 'upcoming' WHEN NOW() BETWEEN CONCAT(event_date,' ',event_time) AND CONCAT(event_date,' ',end_time) THEN 'ongoing' ELSE 'completed' END WHERE event_status!='cancelled'"); }
function badge($status){ $s=strtolower((string)$status); return '<span class="status-badge '.e($s).'">'.e(ucfirst($s)).'</span>'; }
logout_if_requested(); $conn=db_connect(); setcookie('last_event_page', basename($_SERVER['PHP_SELF']), time()+86400, '/');

require_login(['admin']); update_event_status($conn);
$events=mysqli_query($conn,"SELECT e.*, c.club_name, COUNT(er.registration_id) registered_count FROM event e INNER JOIN club c ON c.club_id=e.club_id LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' GROUP BY e.event_id ORDER BY e.event_date DESC");
$total=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM event"))[0]??0; $registered=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM eventregistration WHERE registration_status='registered'"))[0]??0; $waiting=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM eventwaitinglist WHERE waiting_status IN ('waiting','notified')"))[0]??0;
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Dashboard - Admin</title>

    <!-- Admin CSS -->
    <link rel="stylesheet" href="admin.css?v=admin-events-db-1">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

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

    <!-- Navbar -->
    <header class="top-navbar">

        <div class="nav-left">
            <img src="logo-fk.png" alt="FK Logo" class="nav-logo"
                onerror="this.src='logo-fk.png';">

            <div class="nav-brand">
                FK Student Club &<br>Management System
            </div>
        </div>

        <div class="nav-right">
            <a href="home.php" class="nav-link">Home</a>
            <a href="manage_users.php" class="nav-link">Manage Users</a>
            <a href="manage_committees.php" class="nav-link">Manage Committees</a>
            <a href="manage_clubs.php" class="nav-link">Manage Clubs</a>
            <a href="admin_manage_events.php" class="nav-link active-link">Manage Events</a>
            <a href="reports.php" class="nav-link">Report</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="?action=logout" class="nav-link">Log Out</a>
        </div>

    </header>

    <!-- Main Content -->
    <main class="admin-content event-dashboard-page">

        <!-- Page Title -->
        <div class="dashboard-header">
            <h1 class="page-title">Event Dashboard</h1>
            <p class="page-subtitle">
                Overview of event performance, registrations, and club activity.
            </p>
        </div>

        <!-- Top Summary Cards -->
        <div class="dashboard-summary-grid">

            <!-- Total Events -->
            <div class="dashboard-summary-card">
                <div class="dashboard-summary-icon navy">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <p class="dashboard-summary-label">Total Events</p>
                    <h3 class="dashboard-summary-number" id="totalEventsNumber">0</h3>
                </div>
            </div>

            <!-- Total Registration -->
            <div class="dashboard-summary-card">
                <div class="dashboard-summary-icon teal">
                    <i class="bi bi-people"></i>
                </div>

                <div>
                    <p class="dashboard-summary-label">Total Registration</p>
                    <h3 class="dashboard-summary-number" id="totalRegistrationNumber">0</h3>
                </div>
            </div>

            <!-- Most Active Club -->
            <div class="dashboard-summary-card">
                <div class="dashboard-summary-icon navy">
                    <i class="bi bi-award"></i>
                </div>

                <div>
                    <p class="dashboard-summary-label">Most Active Club</p>
                    <h3 class="dashboard-summary-title" id="mostActiveClubTitle">Loading...</h3>
                    <span class="dashboard-summary-small" id="mostActiveClubSmall">0 events organized</span>
                </div>
            </div>

            <!-- Most Popular Event -->
            <div class="dashboard-summary-card">
                <div class="dashboard-summary-icon teal">
                    <i class="bi bi-star"></i>
                </div>

                <div>
                    <p class="dashboard-summary-label">Most Popular Event</p>
                    <h3 class="dashboard-summary-title" id="mostPopularEventTitle">Loading...</h3>
                    <span class="dashboard-summary-small" id="mostPopularEventSmall">0 registrations</span>
                </div>
            </div>

        </div>

        <!-- Dashboard Analytics Section -->
        <div class="dashboard-analytics-grid">

            <!-- Donut Chart Card -->
            <div class="dashboard-card donut-chart-card">

                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Events by Status</h2>
                    <p class="dashboard-card-subtitle">Monthly event status overview</p>
                </div>

                <div class="donut-chart-content">

                    <div class="donut-chart" id="statusDonutChart">
                        <div class="donut-center">
                            <h3 id="statusMonthLabel">Month</h3>
                            <p id="statusYearLabel">Year</p>
                        </div>
                    </div>

                    <div class="donut-legend" id="statusLegend">

                        <div class="legend-item">
                            <span class="legend-color upcoming"></span>
                            <div>
                                <strong>0</strong>
                                <p>Upcoming</p>
                            </div>
                        </div>

                        <div class="legend-item">
                            <span class="legend-color ongoing"></span>
                            <div>
                                <strong>0</strong>
                                <p>Ongoing</p>
                            </div>
                        </div>

                        <div class="legend-item">
                            <span class="legend-color completed"></span>
                            <div>
                                <strong>0</strong>
                                <p>Completed</p>
                            </div>
                        </div>

                        <div class="legend-item">
                            <span class="legend-color cancelled"></span>
                            <div>
                                <strong>0</strong>
                                <p>Cancelled</p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <!-- Registration Trend Chart Card -->
            <div class="dashboard-card line-chart-card">

                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Registration Trend</h2>
                    <p class="dashboard-card-subtitle">Total registrations by week</p>
                </div>

                <div class="registration-trend-chart">

                    <div class="trend-chart-summary">
                        <div>
                            <span class="trend-total-label">Total this period</span>
                            <strong class="trend-total-number" id="trendTotalNumber">0</strong>
                        </div>
                        <span class="trend-growth-badge" id="trendGrowthBadge">
                            <i class="bi bi-graph-up-arrow"></i>
                            +0%
                        </span>
                    </div>

                    <div class="trend-chart-area" aria-label="Weekly registration trend chart">
                        <div class="trend-y-axis" id="trendYAxis">
                            <span>5</span>
                            <span>4</span>
                            <span>3</span>
                            <span>1</span>
                            <span>0</span>
                        </div>

                        <div class="trend-line-plot" id="trendLinePlot">
                            <div class="text-muted small">Loading registration trend...</div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- Dashboard Middle Section -->
        <div class="dashboard-chart-grid">

            <!-- Events Organized by Each Club -->
            <div class="dashboard-card">

                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Events Organized by Each Club</h2>
                    <p class="dashboard-card-subtitle">Number of events created by club</p>
                </div>

                <div class="simple-bar-chart" id="eventsByClubChart">
                    <p class="text-muted mb-0">Loading club event data...</p>

                </div>

            </div>

            <!-- Participants for Each Event -->
            <div class="dashboard-card">

                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Participants for Each Event</h2>
                    <p class="dashboard-card-subtitle">Current registration count</p>
                </div>

                <div class="participant-event-chart">

                    <div class="participant-chart-scale" id="participantChartScale">
                        <span>5</span>
                        <span>3</span>
                        <span>1</span>
                        <span>0</span>
                    </div>

                    <div class="participant-chart-bars" id="participantChartBars">
                        <p class="text-muted mb-0">Loading participant data...</p>
                    </div>

                    <div class="participant-chart-note" id="participantChartNote">
                        <i class="bi bi-trophy"></i>
                        Loading top participant event.
                    </div>

                </div>

            </div>

            <!-- Popular Events -->
            <div class="dashboard-card">

                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Popular Events by Registration Count</h2>
                    <p class="dashboard-card-subtitle">Top registered events</p>
                </div>

                <div class="popular-event-list" id="popularEventList">
                    <p class="text-muted mb-0">Loading popular events...</p>

                </div>

            </div>

        </div>

        <!-- Quick Insight -->
        <div class="dashboard-insight-card">

            <div class="dashboard-card-header text-center">
                <h2 class="dashboard-card-title">Quick Insight</h2>
                <p class="dashboard-card-subtitle">Event registration performance summary</p>
            </div>

            <div class="insight-content">

                <div class="insight-box">
                    <i class="bi bi-graph-up-arrow"></i>
                    <h4>High Engagement</h4>
                    <p id="insightPopularText">Loading engagement insight.</p>
                </div>

                <div class="insight-box">
                    <i class="bi bi-building"></i>
                    <h4>Most Active Club</h4>
                    <p id="insightClubText">Loading club insight.</p>
                </div>

                <div class="insight-box">
                    <i class="bi bi-person-check"></i>
                    <h4>Total Participants</h4>
                    <p id="insightParticipantText">Loading participant insight.</p>
                </div>

            </div>

        </div>

        <!-- Footer -->
        <footer class="dashboard-footer">
            Copyright © 2026 Faculty of Computing - Universiti Malaysia Pahang Al Sultan Abdullah
        </footer>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>function filterRows(q){var rows=document.querySelectorAll('#adminEventTableBody tr[data-search]'); q=(q||'').toLowerCase(); rows.forEach(function(r){r.style.display=r.dataset.search.indexOf(q)>-1?'':'none';});}</script>
</body>

</html>
