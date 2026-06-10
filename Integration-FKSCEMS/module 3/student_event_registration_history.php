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

require_login(['student','committee']); update_event_status($conn); $matric=$_SESSION['matric']??''; $message='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel'){ $id=$_POST['event_id']??''; $st=mysqli_prepare($conn,"UPDATE eventregistration SET registration_status='cancelled' WHERE matric_number=? AND event_id=?"); mysqli_stmt_bind_param($st,'si',$matric,$id); mysqli_stmt_execute($st); $message='Registration cancelled.'; }
$st=mysqli_prepare($conn,"SELECT er.registration_status, e.event_id,e.event_title,e.event_date,e.event_time,e.end_time,e.venue,e.event_status,c.club_name,COALESCE(a.attendance_status,'') attendance_status,COALESCE(a.point_awarded,0) point_awarded FROM eventregistration er INNER JOIN event e ON e.event_id=er.event_id INNER JOIN club c ON c.club_id=e.club_id LEFT JOIN attendance a ON a.registration_id=er.registration_id WHERE er.matric_number=? ORDER BY er.registration_date DESC"); mysqli_stmt_bind_param($st,'s',$matric); mysqli_stmt_execute($st); $history=mysqli_stmt_get_result($st);
$total=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM eventregistration WHERE matric_number='".mysqli_real_escape_string($conn,$matric)."' AND registration_status='registered'"))[0]??0; $cancelled=mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM eventregistration WHERE matric_number='".mysqli_real_escape_string($conn,$matric)."' AND registration_status='cancelled'"))[0]??0; $attended=0; $upcoming=0;
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration History - Student</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Student Theme CSS -->
    <link rel="stylesheet" href="student.css?v=student-layout-1">

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
            <a href="view_club.php" class="nav-link">Find Clubs</a>
            <a href="student_event_registration.php" class="nav-link active-link">Event Registration</a>
            <a href="attendance_record.php" class="nav-link">Attendance</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="?action=logout" class="nav-link">Log Out</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="student-content history-page">

        <!-- Title -->
        <div class="history-header">
            <h1 class="page-title">Events Registration History</h1>
            <p class="page-subtitle">
                View all events you have registered, attended, upcoming, or cancelled.
            </p>
        </div>
        <div id="successMessage" class="alert alert-success d-none" role="alert"></div>

        <!-- Summary Cards -->
        <div class="history-summary-cards">

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-person-check"></i>
                </div>

                <div>
                    <p class="summary-label">Total Registered</p>
                    <h3 class="summary-number"><?php echo e($total); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon teal">
                    <i class="bi bi-star"></i>
                </div>

                <div>
                    <p class="summary-label">Attended</p>
                    <h3 class="summary-number"><?php echo e($attended); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon navy">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <p class="summary-label">Upcoming</p>
                    <h3 class="summary-number"><?php echo e($upcoming); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon teal">
                    <i class="bi bi-calendar-x"></i>
                </div>

                <div>
                    <p class="summary-label">Cancelled</p>
                    <h3 class="summary-number"><?php echo e($cancelled); ?></h3>
                    <span class="summary-small-text">Events</span>
                </div>
            </div>

        </div>

        <!-- Controls -->
        <div class="controls-row-custom history-controls">

            <div class="search-box-custom">
                <span class="search-icon-custom">
                    <i class="bi bi-search"></i>
                </span>

                <input type="text" id="searchTitle" placeholder="Search by Event Title" oninput="filterRows('historyTableBody', this.value)">
            </div>

            <a href="student_event_registration.php" class="btn-back-registration text-decoration-none">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Registration
            </a>

        </div>

        <!-- Table Card -->
        <div class="table-card-custom">

            <!-- Filters -->
            <div class="table-filter-row history-filter-row">

                <select class="filter-select-custom" id="statusFilter">
                    <option value="all">Show All Status</option>
                    <option value="Attended">Attended</option>
                    <option value="Late">Late</option>
                    <option value="Upcoming">Upcoming</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <select class="filter-select-custom" id="yearFilter">
                    <option value="all">List of Year</option>
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
                            <th>Status</th>
                            <th>Points Earn</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="historyTableBody">
<?php while($row=mysqli_fetch_assoc($history)): ?><tr data-search="<?php echo e(strtolower($row['event_title'].' '.$row['venue'].' '.$row['registration_status'])); ?>"><td><?php echo e($row['event_title']); ?></td><td><?php echo e(dmy($row['event_date'])); ?></td><td><?php echo e(t($row['event_time'])); ?> - <?php echo e(t($row['end_time'])); ?></td><td><?php echo e($row['venue']); ?></td><td><?php echo e(ucfirst($row['registration_status'])); ?></td><td><?php echo e($row['point_awarded']); ?></td><td><a href="event_view.php?mode=student&id=<?php echo e($row['event_id']); ?>" class="btn-view-event"><i class="bi bi-eye"></i></a><?php if($row['registration_status']==='registered'): ?><form method="POST" class="d-inline" onsubmit="return confirm('Cancel this registration?');"><input type="hidden" name="action" value="cancel"><input type="hidden" name="event_id" value="<?php echo e($row['event_id']); ?>"><button class="btn-register-event" type="submit">Cancel</button></form><?php endif; ?></td></tr><?php endwhile; ?></tbody>

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

<script>function filterRows(tbodyId, q){ var rows=document.getElementById(tbodyId).querySelectorAll('tr[data-search]'); q=(q||'').toLowerCase(); rows.forEach(function(r){ r.style.display=r.dataset.search.indexOf(q)>-1?'':'none'; }); }</script>
</body>

</html>
