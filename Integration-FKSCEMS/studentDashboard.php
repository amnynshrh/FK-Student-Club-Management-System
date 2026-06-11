<?php       

session_start();

// SECURITY: Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. CHECK LOGIN & ROLE: Aligned to standard login.php session variables
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit();
}

// 3. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// Set timezone to ensure accurate date comparisons for the modal blocks
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_date = date('Y-m-d');

// Map user tracking ID across from session states safely
$user_id = $_SESSION['user_id']; 

// 4. FETCH STUDENT & USER DATA (Joining tables based on your schema)
$sql_profile = "SELECT u.username, u.email, s.name, s.matric_number, s.course, s.profile_photo 
                FROM user u 
                JOIN student s ON u.user_id = s.user_id 
                WHERE u.user_id = ?";

$stmt = $conn->prepare($sql_profile);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
} else {
    die("Profile Query Error: " . $conn->error);
}

// 5. FETCH CLUB COUNT: Pointed to your real schema table 'membership'
$club_count = 0;
if ($user_data && isset($user_data['matric_number'])) {
    $matric = $user_data['matric_number'];
    
    // FIX: Match the database status enum value 'approved' instead of 'Active'
    $sql_clubs = "SELECT COUNT(*) as total 
                  FROM membership
                  WHERE matric_number = ? 
                  AND membership_status = 'approved'";
                  
    $stmt_c = $conn->prepare($sql_clubs);
    if ($stmt_c) {
        $stmt_c->bind_param("s", $matric);
        $stmt_c->execute();
        $result = $stmt_c->get_result();
        
        // Safe verification fetch fallback layout handling
        if ($result && $row = $result->fetch_assoc()) {
            $club_count = (int)$row['total'];
        }
        $stmt_c->close();
    }
}

// total points calculation for the student
$user_id = $_SESSION['user_id']; 
$sql_points = "SELECT SUM(a.point_awarded) as total_points 
               FROM student s
               JOIN eventregistration er ON s.matric_number = er.matric_number
               JOIN attendance a ON er.registration_id = a.registration_id
               WHERE s.user_id = ?";

$total_student_points = 0; // Initialize cleanly with a safe numerical base layout
$stmt_points = $conn->prepare($sql_points);

if ($stmt_points) {
    $stmt_points->bind_param("i", $user_id);
    $stmt_points->execute();
    $result_points = $stmt_points->get_result();
    
    if ($result_points && $row = $result_points->fetch_assoc()) {
        // If they haven't attended events yet, SUM() returns NULL, fallback to 0 safely
        $total_student_points = isset($row['total_points']) ? (int)$row['total_points'] : 0; 
    }
    $stmt_points->close();
} else {
    // Optional fallback error diagnostic handling output injection logic
    die("Points Engine Component Calculation Error: " . $conn->error);
}

// Fetch club memberships with position and status for the logged-in student
$user_id = $_SESSION['user_id'];
$sql_clubs = "SELECT 
                c.club_id,
                c.club_name,
                c.advisor_name,
                c.description,
                COALESCE(com.position, 'Member') AS club_position,
                m.membership_status
              FROM student s
              JOIN membership m ON s.matric_number = m.matric_number
              JOIN club c ON m.club_id = c.club_id
              LEFT JOIN committee com ON m.membership_id = com.membership_id
              WHERE s.user_id = ?";

$stmt_clubs = $conn->prepare($sql_clubs);
$stmt_clubs->bind_param("i", $user_id);
$stmt_clubs->execute();
$result_clubs = $stmt_clubs->get_result();

// Save records into an array so we can reuse them to render modals later in the document
$clubs_array = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - FK Student Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="studentDashboard.css">
    
    <script>
        // Force reload if user uses the 'Back' button to ensure session check runs
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) { 
                window.location.reload(); 
            }
        });
    </script>
    <style>
        /* Maintain alignment continuity for native tables within Bootstrap contexts */
        .member-table td, .member-table th { padding: 12px; }
        .modal-body p { color: #495057; font-size: 0.95rem; line-height: 1.5; }
        .modal-body h4 { font-size: 1.15rem; color: #004a99; margin-top: 15px; border-bottom: 2px solid #eef5ff; padding-bottom: 5px; }
    </style>
</head>
<body>

 <?php include('studentHeader.php') ?>

<div class="page-container">
    <header class="page-header">
        <h1>Hello, <?php echo htmlspecialchars(explode(' ', trim($user_data['name'] ?? 'Student'))[0]); ?>!</h1>
        <p>Your personal student activity hub.</p>
        <small>Matric: <?php echo htmlspecialchars($user_data['matric_number'] ?? 'N/A'); ?> | Course: <?php echo htmlspecialchars($user_data['course'] ?? 'N/A'); ?></small>
    </header>

    <div class="metrics-container">
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Joined Clubs</span>
                <h3 class="metric-value"><?php echo $club_count; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">🏘️</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">My Merit Points</span>
                <h3 class="metric-value"><?php echo $total_student_points; ?></h3>
            </div>
            <div class="metric-icon-box blue-icon">⭐</div>
        </div>
        <div class="metric-card">
            <div class="metric-info">
                <span class="metric-label">Event Attendance</span>
                <h3 class="metric-value">85%</h3>
            </div>
            <div class="metric-icon-box blue-icon">📉</div>
        </div>
    </div>

    <div class="user-grid">
       <div class="table-container">
            <h3 class="card-title">My Registered Clubs</h3>
            <table class="member-table">
                <thead>
                    <tr>
                        <th>Club Name</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_clubs && $result_clubs->num_rows > 0): ?>
                    <?php while ($club = $result_clubs->fetch_assoc()): 
                        $clubs_array[] = $club; // Stash copy for footer rendering sequence
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($club['club_name']); ?></strong></td>
                             <td><?php echo htmlspecialchars($club['club_position']); ?></td>
                             <td>
                                <span class="badge student-badge <?php echo htmlspecialchars(strtolower($club['membership_status'])); ?>">
                                     <?php echo htmlspecialchars(ucfirst($club['membership_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="action-btn edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailsModal_<?php echo $club['club_id']; ?>">
                                    View Club
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 15px; color: #666;">
                                You have not registered for any clubs yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="event-sidebar">
            <div class="progress-container">
                <h3 class="card-title">My Attendance Goal</h3>
                <span class="metric-label">8 out of 10 events</span>
                <div class="progress-bar-bg"><div class="progress-fill" style="width: 80%;"></div></div>
                
                <h3 class="card-title" style="margin-top:20px;">Available Events</h3>
                <div class="event-list">
                    <div class="event-item">
                        <div class="event-date"><span>OCT</span><strong>24</strong></div>
                        <div class="event-info">
                            <div style="font-weight:bold;">Hackathon 2026</div>
                            <small>8:00 AM - Library</small>
                        </div>
                        <button class="action-btn edit">Register</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
foreach ($clubs_array as $club) {
    $club_id = $club['club_id'];

    // 1. Fetch Committees for this Club
    $comm_query = "SELECT s.name, c.position 
                   FROM committee c 
                   JOIN membership m ON c.membership_id = m.membership_id 
                   JOIN student s ON m.matric_number = s.matric_number 
                   WHERE c.club_id = ?";
    $stmt = $conn->prepare($comm_query);
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $comm_result = $stmt->get_result();

    // 2. Fetch Events associated with this Club
    $event_query = "SELECT event_title, event_date, venue, event_status, event_time 
                    FROM event 
                    WHERE club_id = ? 
                    ORDER BY event_date ASC";
    $stmt_event = $conn->prepare($event_query);
    $stmt_event->bind_param("i", $club_id);
    $stmt_event->execute();
    $event_result = $stmt_event->get_result();

    $upcoming_events = [];
    $past_events = [];

    while ($event = $event_result->fetch_assoc()) {
        if ($event['event_status'] === 'completed' || $event['event_date'] < $current_date) {
            $past_events[] = $event;
        } else {
            $upcoming_events[] = $event;
        }
    }
?>

    <div class="modal fade" id="detailsModal_<?php echo $club_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">

                <div class="modal-main-header p-4 bg-dark text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="m-0 text-white fs-4"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                        <p class="subtitle m-0 mt-1 opacity-75 text-white small">Advisor: <?php echo htmlspecialchars($club['advisor_name']); ?></p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-scroll-body modal-body p-4 text-start">
                    <div class="info-block mb-4">
                        <h4>About the Club</h4>
                        <p class="block-paragraph text-secondary"><?php echo htmlspecialchars($club['description']); ?></p>
                    </div>

                    <div class="info-block mb-4">
                        <h4>Club Committees</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Position</th>
                                        <th>Full Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($comm_result && $comm_result->num_rows > 0) {
                                        while ($member = $comm_result->fetch_assoc()) { ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($member['position']); ?></td>
                                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-3">No committee members assigned to this club.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="info-block">
                        <h4>Events & Activities</h4>
                        <div class="events-dual-layout row g-3">
                            
                            <div class="col-12 col-md-6">
                                <div class="event-pane upcoming-pane border rounded p-3 bg-white h-100">
                                    <h5 class="text-primary mb-3 font-weight-bold">Upcoming Events</h5>
                                    <ul class="pane-list list-unstyled mb-0 small">
                                        <?php if (!empty($upcoming_events)): ?>
                                            <?php foreach ($upcoming_events as $ev): ?>
                                                <li class="mb-3 pb-2 border-bottom">
                                                    <strong class="d-block text-dark"><?php echo htmlspecialchars($ev['event_title']); ?></strong>
                                                    <small class="text-muted d-block">📅 <?php echo date('d M Y', strtotime($ev['event_date'])); ?> | 🕒 <?php echo date('h:i A', strtotime($ev['event_time'])); ?></small>
                                                    <small class="text-secondary d-block">📍 <?php echo htmlspecialchars($ev['venue']); ?></small>
                                                    <span class="badge bg-info text-dark mt-1 text-capitalize"><?php echo htmlspecialchars($ev['event_status']); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="text-muted small py-2">No upcoming activities scheduled.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="event-pane past-pane border rounded p-3 bg-white h-100">
                                    <h5 class="text-secondary mb-3 font-weight-bold">Past Events</h5>
                                    <ul class="pane-list list-unstyled mb-0 small">
                                        <?php if (!empty($past_events)): ?>
                                            <?php foreach ($past_events as $ev): ?>
                                                <li class="mb-2 pb-2 border-bottom opacity-75">
                                                    <strong class="d-block text-muted"><?php echo htmlspecialchars($ev['event_title']); ?></strong>
                                                    <small class="text-muted d-block">📅 <?php echo date('d M Y', strtotime($ev['event_date'])); ?></small>
                                                    <small class="text-muted d-block">📍 <?php echo htmlspecialchars($ev['venue']); ?></small>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="text-muted small py-2">No past items recorded.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close Window</button>
                    <button type="button" class="btn btn-success fw-bold px-4" disabled style="cursor: not-allowed;">
                        ✓ Registered Member
                    </button>
                </div>

            </div>
        </div>
    </div>
<?php
    $stmt->close();
    $stmt_event->close();
}

$stmt_clubs->close();
$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>