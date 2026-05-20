<?php
// Connect to your existing database
$conn = new mysqli("localhost", "root", "Amni102030.", "fk_club_management");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// ==========================================
// 1. HANDLER: DELETE CLUB ACTION
// ==========================================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $del_stmt = $conn->prepare("DELETE FROM club WHERE club_id = ?");
    $del_stmt->bind_param("i", $delete_id);

    if ($del_stmt->execute()) {
        $message = "<div class='alert-success'>Club deleted successfully!</div>";
    } else {
        $message = "<div class='alert-error'>Error deleting club: " . $conn->error . "</div>";
    }
    $del_stmt->close();
}

// ==========================================
// 2. HANDLER: UPDATE CLUB PARAMETERS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_club'])) {
    $club_id = intval($_POST['club_id']);
    $club_name = trim($_POST['club_name']);
    $advisor_name = trim($_POST['advisor_name']);
    $description = trim($_POST['description']);
    $club_status = $_POST['club_status'];

    $up_stmt = $conn->prepare("UPDATE club SET club_name = ?, advisor_name = ?, description = ?, club_status = ? WHERE club_id = ?");
    $up_stmt->bind_param("ssssi", $club_name, $advisor_name, $description, $club_status, $club_id);

    if ($up_stmt->execute()) {
        $message = "<div class='alert-success'>Club parameters updated successfully!</div>";
    } else {
        $message = "<div class='alert-error'>Error updating club details: " . $conn->error . "</div>";
    }
    $up_stmt->close();
}

// ==========================================
// 3. HANDLER: UPDATE COMMITTEE MEMBERS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_committee'])) {
    $committee_id = intval($_POST['committee_id']);
    $new_membership_id = intval($_POST['membership_id']);
    $new_position = trim($_POST['position']);

    $comm_up_stmt = $conn->prepare("UPDATE committee SET membership_id = ?, position = ? WHERE committee_id = ?");
    $comm_up_stmt->bind_param("isi", $new_membership_id, $new_position, $committee_id);

    if ($comm_up_stmt->execute()) {
        $message = "<div class='alert-success'>Committee assignment saved successfully!</div>";
    } else {
        $message = "<div class='alert-error'>Failed modifying committee values: " . $conn->error . "</div>";
    }
    $comm_up_stmt->close();
}

// ==========================================
// EXTRA STAGE: LIVE STATS EXTRACTION FOR METRICS
// ==========================================
// Fetch Total Number of Clubs
$total_clubs_res = $conn->query("SELECT COUNT(*) as total FROM club");
$total_clubs = $total_clubs_res->fetch_assoc()['total'] ?? 0;

// Fetch Total Active Clubs
$active_clubs_res = $conn->query("SELECT COUNT(*) as total FROM club WHERE club_status = 'Active'");
$active_clubs = $active_clubs_res->fetch_assoc()['total'] ?? 0;

// Fetch Total Number of Students Involved in Clubs
$total_students_res = $conn->query("SELECT COUNT(DISTINCT matric_number) as total FROM membership");
$total_students = $total_students_res->fetch_assoc()['total'] ?? 0;

// Fetch Distribution of Students across Clubs
$distribution_query = "SELECT c.club_name, COUNT(m.membership_id) as member_count 
                           FROM club c 
                           LEFT JOIN membership m ON c.club_id = m.club_id 
                           GROUP BY c.club_id 
                           ORDER BY member_count DESC";
$distribution_result = $conn->query($distribution_query);

// ==========================================
// 4. HANDLER: SEARCH AND SELECTION LOGIC
// ==========================================
$search_term = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $search_query = "%" . $search_term . "%";
    $club_stmt = $conn->prepare("SELECT * FROM club WHERE club_name LIKE ? OR advisor_name LIKE ?");
    $club_stmt->bind_param("ss", $search_query, $search_query);
    $club_stmt->execute();
    $club_result = $club_stmt->get_result();
} else {
    $club_query = "SELECT * FROM club";
    $club_result = $conn->query($club_query);
}

$admin_clubs_cache = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Student Clubs</title>

    <!-- Bootstrap 5 CDN + Icons added for UI Metrics Layout -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Native Stylesheet Context -->
    <link rel="stylesheet" href="style1.css">
</head>

<body>

    <div class="admin-wrapper">
        <div class="main-dashboard-container">

            <!-- BOOTSTRAP METRICS HEADER -->
            <div class="container-fluid px-0 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 mb-1 text-dark fw-bold">Club Management Dashboard </h2>
                        <p class="text-muted mb-0">Search, update basic details, or manage active committee rosters.</p>
                    </div>
                    <span class="badge bg-primary px-3 py-2 fs-6">Admin Panel</span>
                </div>

                <!-- Row 1: KPI Stats Cards -->
                <div class="row g-3 mb-4">
                    <!-- Total Clubs Card -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card shadow-sm h-100 border-start border-primary border-4 bg-white">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-muted small text-uppercase fw-bold">Total Clubs</div>
                                    <div class="h3 mb-0 fw-bold text-dark"><?php echo $total_clubs; ?></div>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-collection text-primary fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Clubs Card -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card shadow-sm h-100 border-start border-success border-4 bg-white">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-muted small text-uppercase fw-bold">Active Clubs</div>
                                    <div class="h3 mb-0 fw-bold text-dark"><?php echo $active_clubs; ?></div>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-check-circle text-success fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Students Involved Card -->
                    <div class="col-12 col-lg-4">
                        <div class="card shadow-sm h-100 border-start border-info border-4 bg-white">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-muted small text-uppercase fw-bold">Students Involved</div>
                                    <div class="h3 mb-0 fw-bold text-dark"><?php echo number_format($total_students); ?></div>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-people text-info fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Distribution Bars Component -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm bg-white">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="m-0 fw-bold text-secondary text-uppercase tracking-wider small">Student Distribution Across Registered Clubs</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                if ($distribution_result && $distribution_result->num_rows > 0) {
                                    // Array of colors to rotate through for styling uniqueness
                                    $bar_colors = ['bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger'];
                                    $color_index = 0;

                                    while ($dist = $distribution_result->fetch_assoc()) {
                                        $club_name = htmlspecialchars($dist['club_name']);
                                        $member_count = intval($dist['member_count']);

                                        // Compute live logic percentage mapping
                                        $percentage = ($total_students > 0) ? round(($member_count / $total_students) * 100, 1) : 0;
                                        $current_color = $bar_colors[$color_index % count($bar_colors)];
                                        $color_index++;
                                ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-bold text-dark"><?php echo $club_name; ?></span>
                                                <span class="text-muted fw-semibold"><?php echo $member_count; ?> Students (<?php echo $percentage; ?>%)</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar <?php echo $current_color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                <?php
                                    }
                                } else {
                                    echo '<div class="text-muted small">No structural distribution telemetry found.</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr style="border:0; border-top: 1px solid #e2e8f0; margin: 25px 0 25px 0;">

            <?php echo $message; ?>

            <form method="GET" action="admin_manage_club.php" class="search-container">
                <input type="text" name="search" class="search-input" placeholder="Search by Club Name or Advisor..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn-search">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="admin_manage_club.php" class="btn-cancel" style="text-decoration: none; padding: 10px; display: inline-block;">Clear Filter</a>
                <?php endif; ?>
            </form>

            <div class="clubs-native-grid">
                <?php
                if ($club_result && $club_result->num_rows > 0) {
                    while ($club = $club_result->fetch_assoc()) {
                        $club_id = $club['club_id'];
                        $admin_clubs_cache[] = $club;
                        $badge_class = ($club['club_status'] == 'Active') ? 'status-active' : 'status-inactive';
                ?>

                        <div class="native-club-card">
                            <div class="card-inner-content">
                                <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($club['club_status']); ?></span>
                                <h3 style="margin-top: 10px;"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                                <p class="advisor-tag"><strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?></p>
                                <p class="desc-preview-text"><?php echo htmlspecialchars($club['description']); ?></p>

                                <div class="admin-actions">
                                    <button type="button" class="btn-edit" onclick="document.getElementById('editModal_<?php echo $club_id; ?>').style.display = 'flex';">
                                        Edit Details & Committee
                                    </button>
                                    <a href="admin_manage_club.php?delete_id=<?php echo $club_id; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this club?');">
                                        Delete Club
                                    </a>
                                </div>
                            </div>
                        </div>

                <?php
                    }
                } else {
                    echo '<div class="no-clubs-alert" style="width: 100%;">No registered clubs match your search parameters.</div>';
                }
                ?>
            </div>

        </div>
    </div>

    <?php foreach ($admin_clubs_cache as $club) {
        $club_id = $club['club_id'];

        // 1. Fetch current active committee allocations for this specific club
        $comm_query = "SELECT c.committee_id, c.position, m.membership_id, s.name 
                        FROM committee c 
                        JOIN membership m ON c.membership_id = m.membership_id 
                        JOIN student s ON m.matric_number = s.matric_number 
                        WHERE c.club_id = ?";
        $c_stmt = $conn->prepare($comm_query);
        $c_stmt->bind_param("i", $club_id);
        $c_stmt->execute();
        $comm_result = $c_stmt->get_result();

        // 2. Fetch all registered student members of THIS club to fill out assignment selector maps
        $members_list_query = "SELECT m.membership_id, s.name, m.matric_number 
                                FROM membership m 
                                JOIN student s ON m.matric_number = s.matric_number 
                                WHERE m.club_id = ?";
        $m_stmt = $conn->prepare($members_list_query);
        $m_stmt->bind_param("i", $club_id);
        $m_stmt->execute();
        $members_result = $m_stmt->get_result();

        $available_members = [];
        while ($m_row = $members_result->fetch_assoc()) {
            $available_members[] = $m_row;
        }
    ?>

        <div id="editModal_<?php echo $club_id; ?>" class="modal-overlay" style="display: none;">
            <div class="modal-box wide-modal animate-slide-up" style="max-width: 800px;">
                <div class="modal-main-header">
                    <h3>Manage Club Configuration</h3>
                    <p class="subtitle">System Registry ID: #<?php echo $club_id; ?></p>
                </div>

                <div class="modal-scroll-body" style="padding: 20px;">
                    <form method="POST" action="admin_manage_club.php">
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                        <h4>Basic Information</h4>

                        <div class="form-group">
                            <label>Club Name</label>
                            <input type="text" name="club_name" required value="<?php echo htmlspecialchars($club['club_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Advisor Name</label>
                            <input type="text" name="advisor_name" required value="<?php echo htmlspecialchars($club['advisor_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Club Status</label>
                            <select name="club_status">
                                <option value="Active" <?php echo ($club['club_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($club['club_status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3" required><?php echo htmlspecialchars($club['description']); ?></textarea>
                        </div>
                        <button type="submit" name="update_club" class="btn-submit" style="background:#2ecc71; margin-bottom:20px;">Save General Details</button>
                    </form>

                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

                    <h4>Committee Roster Assignment</h4>
                    <p style="font-size:12px; color:#666; margin-bottom:5px;">Assign club members to active leadership positions below:</p>

                    <?php
                    if ($comm_result && $comm_result->num_rows > 0) {
                    ?>
                        <div class="committee-grid-container">
                            <?php
                            while ($member = $comm_result->fetch_assoc()) {
                            ?>
                                <form method="POST" action="admin_manage_club.php" class="mini-inline-form">
                                    <input type="hidden" name="committee_id" value="<?php echo $member['committee_id']; ?>">

                                    <div class="form-item-pos">
                                        <label style="font-size: 11px; font-weight: bold; color: #475569;">Position</label>
                                        <input type="text" name="position" required value="<?php echo htmlspecialchars($member['position']); ?>" placeholder="e.g. President">
                                    </div>

                                    <div class="form-item-member">
                                        <label style="font-size: 11px; font-weight: bold; color: #475569;">Member</label>
                                        <select name="membership_id" required>
                                            <?php foreach ($available_members as $member_option) {
                                                $selected = ($member_option['membership_id'] == $member['membership_id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $member_option['membership_id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($member_option['name'] . " (" . $member_option['matric_number'] . ")"); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="form-item-btn">
                                        <button type="submit" name="update_committee" class="btn-submit" style="background:#3498db;">Update Role</button>
                                    </div>
                                </form>
                            <?php
                            }
                            ?>
                        </div> <?php
                            } else {
                                echo '<p style="color:#e74c3c; font-size:13px; margin-top: 10px;">No existing positions registered. Assign students to memberships first to establish committee targets.</p>';
                            }
                                ?>
                </div>

                <div class="modal-action-footer">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('editModal_<?php echo $club_id; ?>').style.display = 'none';">
                        Close Dashboard
                    </button>
                </div>
            </div>
        </div>
    <?php
        $c_stmt->close();
        $m_stmt->close();
    } ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>