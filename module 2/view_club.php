<?php
// Connect to your existing database
$conn = new mysqli("localhost", "root", "Amni102030.", "fk_club_management");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch only active clubs so students only see operational organizations
$club_query = "SELECT * FROM club WHERE club_status = 'Active'";
$club_result = $conn->query($club_query);

// Keep a copy of the results to loop over again for generating the popup elements
$clubs_for_modals = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Student Clubs</title>

    <!-- 1. Bootstrap 5 Base Framework Setup -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- 2. Your Custom Style File (Loaded last to successfully override Bootstrap colors/fonts) -->
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">

    <!-- Kept your custom native wrapper class -->
    <div class="view-wrapper container py-5">

        <!-- Kept your native header wrapper structure -->
        <div class="view-header text-center mb-5">
            <h2>Faculty of Computing Clubs</h2>
            <p class="subtitle">Explore student organizations, check committees, and view club events.</p>
            <hr>
        </div>

        <!-- Integrated Bootstrap's grid system inside your native grid selector -->
        <div class="clubs-native-grid row g-4">
            <?php
            if ($club_result && $club_result->num_rows > 0) {
                while ($club = $club_result->fetch_assoc()) {
                    $club_id = $club['club_id'];
                    $clubs_for_modals[] = $club;
            ?>

                    <!-- Bootstrap manages layout sizes across screen types smoothly -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <!-- Combined your native card styles with Bootstrap utility spacing -->
                        <div class="native-club-card h-100 d-flex flex-column">
                            <div class="card-inner-content d-flex flex-column h-100 p-3">
                                <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
                                <p class="advisor-tag"><strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?></p>
                                <p class="desc-preview-text mb-4" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($club['description']); ?>
                                </p>

                                <!-- Trigger native Bootstrap JavaScript modals instead of old display:flex styles -->
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
        </div>
    </div>

    <!-- BOOTSTRAP-DRIVEN POPUPS (Styled elegantly using your native internal CSS) -->
    <?php foreach ($clubs_for_modals as $club) {
        $club_id = $club['club_id'];

        $comm_query = "SELECT s.name, c.position 
                       FROM committee c 
                       JOIN membership m ON c.membership_id = m.membership_id 
                       JOIN student s ON m.matric_number = s.matric_number 
                       WHERE c.club_id = ?";

        $stmt = $conn->prepare($comm_query);
        $stmt->bind_param("i", $club_id);
        $stmt->execute();
        $comm_result = $stmt->get_result();
    ?>

        <!-- Bootstrap Modal structure handles backdrops and opening/closing without layout breaks -->
        <div class="modal fade" id="detailsModal_<?php echo $club_id; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <!-- Swapped your modal-box selector for Bootstrap container structure -->
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
                            <!-- Native Bootstrap class ensures tables scale safely down to mobile widths -->
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
                            <!-- Bootstrap grid columns break layout naturally into stacked mobile boxes -->
                            <div class="events-dual-layout row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="event-pane upcoming-pane border rounded p-3">
                                        <h5>Upcoming Events</h5>
                                        <ul class="pane-list list-unstyled mb-0 m-0">
                                            <li class="text-muted small">No upcoming activities.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="event-pane past-pane border rounded p-3">
                                        <h5>Past Events</h5>
                                        <ul class="pane-list list-unstyled mb-0 m-0">
                                            <li class="text-muted small">No past items recorded.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-action-footer modal-footer bg-light p-3">
                        <!-- Added Bootstrap data dismissal hook directly into your custom close button -->
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                            Close Window
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php
        $stmt->close();
    } ?>

    <!-- Mandatory Bootstrap 5 JavaScript Engine Execution Hook -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>