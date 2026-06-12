<?php
// Connect to your existing database
require_once 'config/db.php';

$message = "";

// 1. HANDLER: UPDATE CLUB PARAMETERS

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_club'])) {
    $club_id = intval($_POST['club_id']);
    $club_name = trim($_POST['club_name']);
    $advisor_name = trim($_POST['advisor_name']);
    $description = trim($_POST['description']);
    $club_status = $_POST['club_status'];

    $up_stmt = $conn->prepare("UPDATE club SET club_name = ?, advisor_name = ?, description = ?, club_status = ? WHERE club_id = ?");
    $up_stmt->bind_param("ssssi", $club_name, $advisor_name, $description, $club_status, $club_id);

    if ($up_stmt->execute()) {
        $message = "<div class='alert-success'>Club details updated successfully!</div>";
    } else {
        $message = "<div class='alert-error'>Error updating club details: " . $conn->error . "</div>";
    }
    $up_stmt->close();
}

// 2. HANDLER: SAVE COMMITTEE ROLE ASSIGNMENTS
$core_committee_positions = ['President', 'Vice President', 'Secretary', 'Treasurer'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_committee_roles'])) {
    $club_id = intval($_POST['club_id']);
    $role_assignments = [];
    $assigned_membership_ids = [];

    foreach ($core_committee_positions as $position) {
        $membership_id = intval($_POST['committee_roles'][$position] ?? 0);
        $role_assignments[$position] = $membership_id;

        if ($membership_id > 0) {
            if (in_array($membership_id, $assigned_membership_ids, true)) {
                $message = "<div class='alert-error'>Each member can only hold one committee role. Please review your selections.</div>";
                break;
            }
            $assigned_membership_ids[] = $membership_id;
        }
    }

    if (empty($message)) {
        $verify_member_stmt = $conn->prepare("SELECT membership_id FROM membership WHERE membership_id = ? AND club_id = ?");
        $find_role_stmt = $conn->prepare("SELECT committee_id FROM committee WHERE club_id = ? AND position = ?");
        $update_role_stmt = $conn->prepare("UPDATE committee SET membership_id = ?, assigned_date = CURDATE() WHERE committee_id = ?");
        $insert_role_stmt = $conn->prepare("INSERT INTO committee (membership_id, club_id, position, assigned_date) VALUES (?, ?, ?, CURDATE())");
        $delete_role_stmt = $conn->prepare("DELETE FROM committee WHERE committee_id = ?");
        $memberSyncStmt = $conn->prepare("
            SELECT membership_id
            FROM membership
            WHERE club_id = ?
        ");

        $memberSyncStmt->bind_param("i", $club_id);
        $memberSyncStmt->execute();

        $memberSyncResult = $memberSyncStmt->get_result();

        $membersToSync = [];

        while ($memberRow = $memberSyncResult->fetch_assoc()) {
            $membersToSync[] = $memberRow;
        }

        $memberSyncStmt->close();

        $save_failed = false;
        $conn->begin_transaction();

        foreach ($role_assignments as $position => $membership_id) {
            if ($membership_id > 0) {
                $verify_member_stmt->bind_param("ii", $membership_id, $club_id);
                $verify_member_stmt->execute();
                $verify_result = $verify_member_stmt->get_result();

                if ($verify_result->num_rows === 0) {
                    $save_failed = true;
                    $message = "<div class='alert-error'>One or more selected members are not part of this club.</div>";
                    break;
                }
            }

            $find_role_stmt->bind_param("is", $club_id, $position);
            $find_role_stmt->execute();
            $existing_role = $find_role_stmt->get_result()->fetch_assoc();
            $existing_committee_id = intval($existing_role['committee_id'] ?? 0);

            if ($membership_id > 0) {
                if ($existing_committee_id > 0) {
                    $update_role_stmt->bind_param("ii", $membership_id, $existing_committee_id);
                    if (!$update_role_stmt->execute()) {
                        $save_failed = true;
                        $break;
                    }
                } else {
                    $insert_role_stmt->bind_param("iis", $membership_id, $club_id, $position);
                    if (!$insert_role_stmt->execute()) {
                        $save_failed = true;
                        $break;
                    }
                }
            } elseif ($existing_committee_id > 0) {
                $delete_role_stmt->bind_param("i", $existing_committee_id);
                if (!$delete_role_stmt->execute()) {
                    $save_failed = true;
                    $break;
                }
            }
        }

        if ($save_failed) {
            $conn->rollback();
            if (empty($message)) {
                $message = "<div class='alert-error'>Failed saving committee assignments: " . htmlspecialchars($conn->error) . "</div>";
            }
        } else {

            $conn->commit();
        
            /*
            |--------------------------------------------------------------------------
            | Synchronize user.role with committee table
            |--------------------------------------------------------------------------
            */
        
            foreach ($membersToSync as $member) {
        
                $membership_id = (int)$member['membership_id'];
        
                // Check if member currently holds any committee position
                $checkStmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM committee
                    WHERE membership_id = ?
                ");
        
                $checkStmt->bind_param("i", $membership_id);
                $checkStmt->execute();
        
                $committeeCount = (int)$checkStmt
                    ->get_result()
                    ->fetch_assoc()['total'];
        
                $checkStmt->close();
        
                $newRole = ($committeeCount > 0)
                    ? 'committee'
                    : 'student';
        
                $updateStmt = $conn->prepare("
                    UPDATE user u
                    INNER JOIN student s
                        ON u.user_id = s.user_id
                    INNER JOIN membership m
                        ON s.matric_number = m.matric_number
                    SET u.role = ?
                    WHERE m.membership_id = ?
                ");
        
                $updateStmt->bind_param(
                    "si",
                    $newRole,
                    $membership_id
                );
        
                $updateStmt->execute();
                $updateStmt->close();
            }
        
            $message = "<div class='alert-success'>Committee roles updated successfully!</div>";
        }

        $verify_member_stmt->close();
        $find_role_stmt->close();
        $update_role_stmt->close();
        $insert_role_stmt->close();
        $delete_role_stmt->close();
    }
}

// LIVE STATS EXTRACTION FOR METRICS
// Fetch Total Number of Clubs
$total_clubs_res = $conn->query("SELECT COUNT(*) as total FROM club");
$total_clubs = $total_clubs_res->fetch_assoc()['total'] ?? 0;

// Fetch Total Active Clubs
$active_clubs_res = $conn->query("SELECT COUNT(*) as total FROM club WHERE LOWER(club_status) = 'active'");
$active_clubs = $active_clubs_res->fetch_assoc()['total'] ?? 0;

// Fetch Total Number of Students Involved in Clubs
$total_students_res = $conn->query("SELECT COUNT(DISTINCT matric_number) as total FROM membership WHERE matric_number IS NOT NULL AND matric_number != ''");
$total_students = $total_students_res->fetch_assoc()['total'] ?? 0;

// Fetch Distribution of Students across Clubs
$distribution_query = "SELECT c.club_name, COUNT(m.membership_id) as member_count 
                           FROM club c 
                           LEFT JOIN membership m ON c.club_id = m.club_id 
                           GROUP BY c.club_id, c.club_name
                           ORDER BY member_count DESC, c.club_name ASC";
$distribution_result = $conn->query($distribution_query);

$inactive_clubs = max(0, intval($total_clubs) - intval($active_clubs));
$distribution_data = [];

if ($distribution_result && $distribution_result->num_rows > 0) {
    while ($dist = $distribution_result->fetch_assoc()) {
        $distribution_data[] = $dist;
    }
}

$distribution_labels = array_column($distribution_data, 'club_name');
$distribution_values = array_map('intval', array_column($distribution_data, 'member_count'));


// ==========================================
// NEW FUNCTIONALITY: PDF REPORT GENERATION
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'generate_pdf') {
    // If you use third-party libraries like FPDF, ensure fpdf.php is uploaded to your environment
    // Falling back to custom-styled clean system printing stream or basic framework output
    if (file_exists('libs/fpdf.php') || file_exists('fpdf.php')) {
        include_once(file_exists('fpdf.php') ? 'fpdf.php' : 'libs/fpdf.php');
        
        class ClubReportPDF extends FPDF {
            function Header() {
                $this->SetFont('Arial', 'B', 15);
                $this->SetTextColor(37, 99, 235);
                $this->Cell(0, 10, 'FACULTY STUDENT CLUB MANAGEMENT SYSTEM', 0, 1, 'C');
                $this->SetFont('Arial', 'I', 10);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(0, 5, 'Official Analytics & Statistical Summary Report', 0, 1, 'C');
                $this->Ln(10);
                $this->Line(10, 30, 200, 30);
            }
            function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial', 'I', 8);
                $this->SetTextColor(148, 163, 184);
                $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | Generated on: ' . date('Y-m-d H:i:s'), 0, 0, 'C');
            }
        }

        $pdf = new ClubReportPDF();
        $pdf->AddPage();
        $pdf->SetMargins(15, 20, 15);
        $pdf->Ln(5);

        // Core Metrics Table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(0, 10, '1. Executive Core Metrics Summary', 0, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(241, 245, 249);
        $pdf->Cell(100, 8, 'Metric Indicator Description', 1, 0, 'L', true);
        $pdf->Cell(70, 8, 'Aggregated Value Summary', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(100, 8, 'Total Registered Clubs', 1, 0, 'L');
        $pdf->Cell(70, 8, $total_clubs, 1, 1, 'C');
        $pdf->Cell(100, 8, 'Active Operational Clubs', 1, 0, 'L');
        $pdf->Cell(70, 8, $active_clubs, 1, 1, 'C');
        $pdf->Cell(100, 8, 'Inactive/Suspended Clubs', 1, 0, 'L');
        $pdf->Cell(70, 8, $inactive_clubs, 1, 1, 'C');
        $pdf->Cell(100, 8, 'Total Distinct Students Involved', 1, 0, 'L');
        $pdf->Cell(70, 8, number_format($total_students), 1, 1, 'C');
        
        $pdf->Ln(10);

        // Distribution Table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, '2. Faculty Distribution Matrix Across Clubs', 0, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(241, 245, 249);
        $pdf->Cell(110, 8, 'Registered Club Profile Name', 1, 0, 'L', true);
        $pdf->Cell(60, 8, 'Active Members Counts', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 10);
        if (!empty($distribution_data)) {
            foreach ($distribution_data as $row) {
                $pdf->Cell(110, 8, iconv('UTF-8', 'windows-1252', $row['club_name']), 1, 0, 'L');
                $pdf->Cell(60, 8, $row['member_count'] . ' Students', 1, 1, 'C');
            }
        } else {
            $pdf->Cell(170, 8, 'No distribution metric variables available.', 1, 1, 'C');
        }

        ob_end_clean();
        $pdf->Output('D', 'Faculty_Club_Statistical_Report_' . date('Ymd') . '.pdf');
        exit;
    } else {
        // Fallback Native Native High-Fidelity Browser Engine Controller Printable Document Interface 
        // if external library configuration variables are absent from base architecture
        echo "<script>
            window.onload = function() {
                window.print();
                setTimeout(function() { window.location.href = 'admin_manage_club.php'; }, 500);
            };
        </script>";
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <title>Club Statistics Report</title>
            <style>
                body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #334155; padding: 40px; }
                .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 30px; }
                .header h1 { color: #2563eb; margin: 0 0 5px 0; font-size: 24px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { border: 1px solid #cbd5e1; padding: 12px; text-align: left; }
                th { background-color: #f8fafc; font-weight: bold; }
                .text-center { text-align: center; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>FACULTY STUDENT CLUB MANAGEMENT SYSTEM</h1>
                <p>Official Analytics & Statistical Summary Report</p>
                <p style="font-size: 12px; color: #64748b;">Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            <h2>1. Executive Core Metrics Summary</h2>
            <table>
                <thead>
                    <tr><th>Metric Indicator Description</th><th class="text-center">Aggregated Value Summary</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total Registered Clubs</td><td class="text-center"><strong><?php echo $total_clubs; ?></strong></td></tr>
                    <tr><td>Active Operational Clubs</td><td class="text-center" style="color: #16a34a;"><strong><?php echo $active_clubs; ?></strong></td></tr>
                    <tr><td>Inactive/Suspended Clubs</td><td class="text-center" style="color: #64748b;"><strong><?php echo $inactive_clubs; ?></strong></td></tr>
                    <tr><td>Total Distinct Students Involved</td><td class="text-center"><strong><?php echo number_format($total_students); ?></strong></td></tr>
                </tbody>
            </table>
            <h2>2. Faculty Distribution Matrix Across Clubs</h2>
            <table>
                <thead>
                    <tr><th>Registered Club Profile Name</th><th class="text-center">Active Members Counts</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($distribution_data as $row): ?>
                        <tr><td><?php echo htmlspecialchars($row['club_name']); ?></td><td class="text-center"><?php echo $row['member_count']; ?> Students</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}


// 3. HANDLER: SEARCH AND SELECTION LOGIC
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="style1.css">
</head>

<body>

    <?php include('adminHeader.php') ?>

    <div class="admin-wrapper">
        <div class="main-dashboard-container">

            <div class="container-fluid px-0 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 mb-1 text-dark fw-bold">Club Management Dashboard </h2>
                    </div>
                    <div>
                        <a href="admin_manage_club.php?action=generate_pdf" target="_blank" class="btn btn-danger d-flex align-items-center gap-2 shadow-sm fw-semibold">
                            <i class="bi bi-file-earmark-pdf-fill"></i> Generate PDF Report
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
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

                <?php $has_distribution_chart = count($distribution_data) > 0; ?>
                <div class="row g-3 club-dashboard-charts">
                    <div class="col-12 <?php echo $has_distribution_chart ? 'col-lg-5' : 'col-lg-8'; ?>">
                        <div class="card shadow-sm bg-white h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="m-0 fw-bold text-secondary text-uppercase tracking-wider small">Faculty Club Overview</h6>
                                <p class="mb-0 mt-1 small text-muted">Summarized club and student participation metrics</p>
                            </div>
                            <div class="card-body">
                                <div class="club-chart-wrap">
                                    <canvas id="clubOverviewChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 <?php echo $has_distribution_chart ? 'col-lg-3' : 'col-lg-4'; ?>">
                        <div class="card shadow-sm bg-white h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="m-0 fw-bold text-secondary text-uppercase tracking-wider small">Club Activity Status</h6>
                                <p class="mb-0 mt-1 small text-muted">Active vs inactive clubs</p>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div class="club-chart-wrap club-chart-wrap-donut">
                                    <canvas id="clubStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($has_distribution_chart) { ?>
                    <div class="col-12 col-lg-4">
                        <div class="card shadow-sm bg-white h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="m-0 fw-bold text-secondary text-uppercase tracking-wider small">Student Involvement Share</h6>
                                <p class="mb-0 mt-1 small text-muted">Percentage of students per club</p>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div class="club-chart-wrap club-chart-wrap-donut">
                                    <canvas id="studentShareChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    
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
                        $badge_class = (strtolower((string)$club['club_status']) == 'active') ? 'status-active' : 'status-inactive';
                ?>

                        <div class="native-club-card">
                            <div class="card-inner-content">
                                <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst((string)$club['club_status'])); ?></span>
                                <h3 style="margin-top: 10px;"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                                <p class="advisor-tag"><strong>Advisor:</strong> <?php echo htmlspecialchars($club['advisor_name']); ?></p>
                                <p class="desc-preview-text"><?php echo htmlspecialchars($club['description']); ?></p>

                                <div class="admin-actions">
                                    <button type="button" class="btn-edit" onclick="document.getElementById('editModal_<?php echo $club_id; ?>').style.display = 'flex';">
                                        Edit Details & Committee
                                    </button>
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

        // 1. Fetch current committee role assignments for this club
        $comm_query = "SELECT c.committee_id, c.position, m.membership_id, s.name 
                        FROM committee c 
                        JOIN membership m ON c.membership_id = m.membership_id 
                        JOIN student s ON m.matric_number = s.matric_number 
                        WHERE c.club_id = ?";
        $c_stmt = $conn->prepare($comm_query);
        $c_stmt->bind_param("i", $club_id);
        $c_stmt->execute();
        $comm_result = $c_stmt->get_result();

        $current_role_assignments = [];
        while ($comm_row = $comm_result->fetch_assoc()) {
            $current_role_assignments[$comm_row['position']] = $comm_row['membership_id'];
        }

        // 2. Fetch all club members from membership for role assignment dropdowns
        $members_list_query = "
            SELECT
                m.membership_id,
                s.name,
                m.matric_number
            FROM membership m
            INNER JOIN student s
                ON m.matric_number = s.matric_number
            WHERE m.club_id = ?
            AND (
                m.matric_number NOT IN (
                    SELECT m2.matric_number
                    FROM committee c
                    INNER JOIN membership m2
                        ON c.membership_id = m2.membership_id
                    WHERE c.club_id <> ?
                )
                OR m.membership_id IN (
                    SELECT c3.membership_id
                    FROM committee c3
                    WHERE c3.club_id = ?
                )
            )
            ORDER BY s.name ASC
        ";

        $m_stmt = $conn->prepare($members_list_query);
        $m_stmt->bind_param(
            "iii",
            $club_id,
            $club_id,
            $club_id
        );
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
                                <option value="active" <?php echo (strtolower((string)$club['club_status']) == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (strtolower((string)$club['club_status']) == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3" required><?php echo htmlspecialchars($club['description']); ?></textarea>
                        </div>
                        <button type="submit" name="update_club" class="btn-submit" style="background:#2ecc71; margin-bottom:20px;">Save General Details</button>
                    </form>

                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

                    <h4>Committee Role Assignment</h4>
                    <p style="font-size:12px; color:#666; margin-bottom:5px;">Assign club members to core leadership positions. Only members registered under this club are available.</p>

                    <?php if (count($available_members) === 0) { ?>
                        <p style="color:#e74c3c; font-size:13px; margin-top: 10px;">No club members found in membership records. Students must join this club before committee roles can be assigned.</p>
                    <?php } else { ?>
                        <form method="POST" action="admin_manage_club.php">
                            <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                            <div class="committee-grid-container">
                                <?php foreach ($core_committee_positions as $position) {
                                    $selected_membership_id = intval($current_role_assignments[$position] ?? 0);
                                ?>
                                    <div class="mini-inline-form">
                                        <div class="form-item-pos">
                                            <label style="font-size: 11px; font-weight: bold; color: #475569;">Position</label>
                                            <input type="text" value="<?php echo htmlspecialchars($position); ?>" readonly>
                                        </div>

                                        <div class="form-item-member">
                                            <label style="font-size: 11px; font-weight: bold; color: #475569;">Member</label>
                                            <select name="committee_roles[<?php echo htmlspecialchars($position); ?>]">
                                                <option value="">-- Unassigned --</option>
                                                <?php foreach ($available_members as $member_option) {
                                                    $selected = ($member_option['membership_id'] == $selected_membership_id) ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo $member_option['membership_id']; ?>" <?php echo $selected; ?>>
                                                        <?php echo htmlspecialchars($member_option['name'] . " (" . $member_option['matric_number'] . ")"); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <button type="submit" name="save_committee_roles" class="btn-submit" style="background:#3498db; margin-top: 15px;">Save Committee Roles</button>
                        </form>
                    <?php } ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const clubChartColors = {
            primary: '#2563eb',
            success: '#16a34a',
            info: '#0891b2',
            warning: '#d97706',
            danger: '#dc2626',
            slate: '#64748b',
            palette: ['#2563eb', '#16a34a', '#0891b2', '#d97706', '#7c3aed', '#dc2626', '#0f766e', '#ea580c']
        };

        const clubChartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#475569',
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#e2e8f0',
                    padding: 12,
                    cornerRadius: 8
                }
            }
        };

        new Chart(document.getElementById('clubOverviewChart'), {
            type: 'bar',
            data: {
                labels: ['Total Clubs', 'Active Clubs', 'Students Involved'],
                datasets: [{
                    label: 'Count',
                    data: [
                        <?php echo intval($total_clubs); ?>,
                        <?php echo intval($active_clubs); ?>,
                        <?php echo intval($total_students); ?>
                    ],
                    backgroundColor: [clubChartColors.primary, clubChartColors.success, clubChartColors.info],
                    borderRadius: 8,
                    maxBarThickness: 72
                }]
            },
            options: {
                ...clubChartDefaults,
                plugins: {
                    ...clubChartDefaults.plugins,
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e2e8f0' },
                        ticks: {
                            color: '#64748b',
                            precision: 0
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('clubStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Active Clubs', 'Inactive Clubs'],
                datasets: [{
                    data: [
                        <?php echo intval($active_clubs); ?>,
                        <?php echo intval($inactive_clubs); ?>
                    ],
                    backgroundColor: [clubChartColors.success, clubChartColors.slate],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                ...clubChartDefaults,
                cutout: '62%',
                plugins: {
                    ...clubChartDefaults.plugins,
                    legend: { position: 'bottom' }
                }
            }
        });

        <?php if (count($distribution_data) > 0) { ?>
        const distributionLabels = <?php echo json_encode($distribution_labels); ?>;
        const distributionValues = <?php echo json_encode($distribution_values); ?>;

        new Chart(document.getElementById('studentShareChart'), {
            type: 'pie',
            data: {
                labels: distributionLabels,
                datasets: [{
                    data: distributionValues,
                    backgroundColor: clubChartColors.palette,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                ...clubChartDefaults,
                plugins: {
                    ...clubChartDefaults.plugins,
                    legend: { position: 'bottom' }
                }
            }
        });

        new Chart(document.getElementById('studentDistributionChart'), {
            type: 'bar',
            data: {
                labels: distributionLabels,
                datasets: [{
                    label: 'Students',
                    data: distributionValues,
                    backgroundColor: clubChartColors.palette,
                    borderRadius: 8,
                    maxBarThickness: 48
                }]
            },
            options: {
                ...clubChartDefaults,
                indexAxis: 'y',
                plugins: {
                    ...clubChartDefaults.plugins,
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#e2e8f0' },
                        ticks: {
                            color: '#64748b',
                            precision: 0
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            color: '#334155',
                            font: { weight: '600' }
                        }
                    }
                }
            }
        });
        <?php } ?>
    </script>
</body>

</html>