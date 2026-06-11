<?php
// Start the session
session_start();

// Connect to your existing database
require_once 'config/db.php';

// Set timezone to ensure accurate date comparisons
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_date = date('Y-m-d');

$user_id = $_SESSION['user_id'];
$sql_student = "
SELECT matric_number
FROM student
WHERE user_id = ?
";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$row_student = $result_student->fetch_assoc();

$logged_in_matric = $row_student['matric_number'];
$message = "";


// ACTION HANDLER: INSERT INTO MEMBERSHIP TABLE WHEN STUDENT REGISTERS

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['join_club'])) {
    $club_id = intval($_POST['club_id']);

    // Anti-duplicate protection: Verify if a relationship mapping entry is already active
    $check_stmt = $conn->prepare("SELECT membership_id FROM membership WHERE matric_number = ? AND club_id = ?");
    $check_stmt->bind_param("si", $logged_in_matric, $club_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "
        <div class='alert alert-warning alert-dismissible fade show container mt-3' role='alert'>
            You are already a registered member of this organization!
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    } else {
        // INSERT Query: Places student registration parameter blocks into membership mapping
        $join_stmt = $conn->prepare("INSERT INTO membership (matric_number, club_id, membership_status, join_date) VALUES (?, ?, 'approved', NOW())");
        $join_stmt->bind_param("si", $logged_in_matric, $club_id);

        if ($join_stmt->execute()) {
            $message = "
            <div class='alert alert-success alert-dismissible fade show container mt-3' role='alert'>
                 <strong>Success!</strong> Your registration was processed. You have successfully joined the club.
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
        } else {
            $message = "
            <div class='alert alert-danger alert-dismissible fade show container mt-3' role='alert'>
                System Error: Failed to execute registration process: " . htmlspecialchars($conn->error) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
        }
        $join_stmt->close();
    }
    $check_stmt->close();
}

// FETCH LOGIC: COMPILING ACTIVE CLUBS + LOGGED IN USER MEMBERSHIP MAPS

$club_query = "
    SELECT c.*, m.membership_status 
    FROM club c 
    LEFT JOIN membership m ON c.club_id = m.club_id AND m.matric_number = ?
    WHERE c.club_status = 'active'";

$main_stmt = $conn->prepare($club_query);
$main_stmt->bind_param("s", $logged_in_matric);
$main_stmt->execute();
$club_result = $main_stmt->get_result();

$clubs_for_modals = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Student Clubs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">

    <?php include('studentHeader.php') ?>

    <?php echo $message; ?>

    <div class="view-wrapper container py-5">
        <div class="view-header text-center mb-4">
            <h2>Faculty of Computing Clubs</h2>
            <br>
            <hr>
        </div>

        <div class="row justify-content-center mb-5">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        🔍
                    </span>
                    <input type="text" id="clubSearchInput" class="form-control border-start-0 ps-1" placeholder="Search clubs by name or advisor..." onkeyup="filterClubs()">
                </div>
            </div>
        </div>

        <div class="clubs-native-grid row g-4" id="clubsGrid">
            <?php
            if ($club_result && $club_result->num_rows > 0) {
                while ($club = $club_result->fetch_assoc()) {
                    $club_id = $club['club_id'];
                    $clubs_for_modals[] = $club;
                    $already_joined = !empty($club['membership_status']);
            ?>
                    <div class="col-12 col-md-6 col-lg-4 club-card-wrapper" 
                         data-club-name="<?php echo htmlspecialchars(strtolower($club['club_name'])); ?>"
                         data-advisor-name="<?php echo htmlspecialchars(strtolower($club['advisor_name'])); ?>">
                        <div class="native-club-card h-100 d-flex flex-column position-relative">
                            <div class="card-inner-content d-flex flex-column h-100 p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="m-0 fs-5"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                                    <?php if ($already_joined): ?>
                                        <span class="badge bg-success text-white small">Member</span>
                                    <?php endif; ?>
                                </div>

                                <p class="advisor-tag"><strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?></p>
                                <p class="desc-preview-text mb-4" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($club['description']); ?>
                                </p>

                                <button type="button" class="btn-submit mt-auto w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#detailsModal_<?php echo $club_id; ?>">
                                    View Details & Events
                                </button>
                            </div>
                        </div>
                    </div>
            <?php
                }
            } else {
                echo '<div class="col-12"><div class="no-clubs-alert text-center w-100">No active clubs registered within the faculty at this moment.</div></div>';
            }
            ?>
            <div class="col-12 d-none" id="noMatchAlert">
                <div class="no-clubs-alert text-center w-100">No clubs match your search criteria.</div>
            </div>
        </div>
    </div>

    <?php 
    foreach ($clubs_for_modals as $club) {
        $club_id = $club['club_id'];
        $already_joined = !empty($club['membership_status']);

        // 1. Fetch Committees
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

        // Group into arrays
        $upcoming_events = [];
        $past_events = [];

        while ($event = $event_result->fetch_assoc()) {
            // Check status or date logic to split events
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
                            <h3 class="m-0 text-white"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                            <p class="subtitle m-0 mt-1 opacity-75 text-white">Advisor: <?php echo htmlspecialchars($club['advisor_name']); ?></p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-scroll-body modal-body p-4">
                        <div class="info-block mb-4">
                            <h4>About the Club</h4>
                            <p class="block-paragraph text-secondary"><?php echo htmlspecialchars($club['description']); ?></p>
                        </div>

                        <div class="info-block mb-4">
                            <h4>Club Committees</h4>
                            <div class="table-responsive">
                                <table class="native-data-table table mb-0">
                                    <thead>
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
                                        <h5 class="text-primary mb-3">Upcoming Events</h5>
                                        <ul class="pane-list list-unstyled mb-0">
                                            <?php if (!empty($upcoming_events)): ?>
                                                <?php foreach ($upcoming_events as $ev): ?>
                                                    <li class="mb-3 pb-2 border-bottom">
                                                        <strong class="d-block text-dark"><?php echo htmlspecialchars($ev['event_title']); ?></strong>
                                                        <small class="text-muted d-block">📅 <?php echo date('d M Y', strtotime($ev['event_date'])); ?> | 🕒 <?php echo date('h:i A', strtotime($ev['event_time'])); ?></small>
                                                        <small class="text-secondary d-block">📍 <?php echo htmlspecialchars($ev['venue']); ?></small>
                                                        <span class="badge bg-info text-dark mt-1 text-capitalize small"><?php echo htmlspecialchars($ev['event_status']); ?></span>
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
                                        <h5 class="text-secondary mb-3">Past Events</h5>
                                        <ul class="pane-list list-unstyled mb-0">
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

                    <div class="modal-action-footer modal-footer bg-light p-3 d-flex justify-content-between">
                        <button type="button" class="btn-cancel btn btn-secondary" data-bs-dismiss="modal">
                            Close Window
                        </button>

                        <div>
                            <?php if ($already_joined): ?>
                                <button type="button" class="btn btn-success fw-bold px-4" disabled style="cursor: not-allowed;">
                                    ✓ Already a Member
                                </button>
                            <?php disable: else: ?>
                                <form method="POST" action="view_club.php" onsubmit="return confirm('Do you want to confirm registration membership into <?php echo htmlspecialchars($club['club_name']); ?>?');">
                                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                    <button type="submit" name="join_club" class="btn-submit btn btn-primary fw-bold px-4" style="background: #3498db; border: none;">
                                        Join Club Register
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php
        $stmt->close();
        $stmt_event->close();
    }
    $main_stmt->close();
    $conn->close();
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    function filterClubs() {
        let input = document.getElementById('clubSearchInput').value.toLowerCase();
        let cards = document.getElementsByClassName('club-card-wrapper');
        let noMatchAlert = document.getElementById('noMatchAlert');
        let visibleCount = 0;

        for (let i = 0; i < cards.length; i++) {
            let clubName = cards[i].getAttribute('data-club-name');
            let advisorName = cards[i].getAttribute('data-advisor-name');

            if (clubName.includes(input) || advisorName.includes(input)) {
                cards[i].classList.remove('d-none');
                visibleCount++;
            } else {
                cards[i].classList.add('d-none');
            }
        }

        // Show a custom notification message if no matching layout elements remain visible
        if (visibleCount === 0 && input.trim() !== "") {
            noMatchAlert.classList.remove('d-none');
        } else {
            noMatchAlert.classList.add('d-none');
        }
    }
    </script>
</body>

</html>