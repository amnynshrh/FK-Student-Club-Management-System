<?php
session_start();

include 'session.php';

// 2. Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$matric_number = $_GET['matric_number'];
$history_page = max(1, (int) ($_GET['history_page'] ?? 1));
$records_per_page = 10;
$history_offset = ($history_page - 1) * $records_per_page;

$sql_student = "
SELECT *
FROM student
WHERE matric_number = ?
";

$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("s", $matric_number);
$stmt_student->execute();

$result_student = $stmt_student->get_result();
$student = $result_student->fetch_assoc();

$matric_number = $student['matric_number'];
$student_name = $student['name'];
$profile_photo = $student['profile_photo'];

$ranking_sql = "
SELECT matric_number, name, total_points
FROM (
    SELECT
        s.matric_number,
        s.name,
        COALESCE(SUM(a.point_awarded), 0) AS total_points
    FROM student s
    LEFT JOIN eventregistration er
    ON s.matric_number = er.matric_number
    LEFT JOIN attendance a
    ON er.registration_id = a.registration_id
    GROUP BY s.matric_number, s.name
) ranked_students
ORDER BY total_points DESC, name ASC
";

$ranking_result = $conn->query($ranking_sql);
$current_rank = 0;
$previous_rank_points = null;
$rank_position_text = "Unranked";
$rank_gap_text = "No ranking data available";
$rank_index = 0;

while ($ranking_row = $ranking_result->fetch_assoc()) {
    $rank_index++;
    if ($ranking_row['matric_number'] === $matric_number) {
        $current_rank = $rank_index;
        $rank_position_text = "Rank " . $current_rank;

        if ($current_rank <= 3) {
            $rank_gap_text = "Rank " . $current_rank;
        } else {
            $points_behind = max(0, (int) $previous_rank_points - (int) $ranking_row['total_points']);
            $rank_gap_text = number_format($points_behind) . " points behind rank " . ($current_rank - 1);
        }
        break;
    }
    $previous_rank_points = $ranking_row['total_points'];
}

$sql_history_count = "
SELECT COUNT(*) AS total_records
FROM attendance a
INNER JOIN eventregistration er
ON a.registration_id = er.registration_id
INNER JOIN student s
ON er.matric_number = s.matric_number
WHERE s.matric_number = ?
";

$stmt_history_count = $conn->prepare($sql_history_count);
$stmt_history_count->bind_param("s", $matric_number);
$stmt_history_count->execute();
$history_count_result = $stmt_history_count->get_result();
$history_total_records = (int) ($history_count_result->fetch_assoc()['total_records'] ?? 0);
$history_total_pages = max(1, (int) ceil($history_total_records / $records_per_page));

if ($history_page > $history_total_pages) {
    $history_page = $history_total_pages;
    $history_offset = ($history_page - 1) * $records_per_page;
}

$sql_history = "
SELECT 
    e.event_title,
    e.event_date,
    a.attendance_status,
    a.point_awarded
FROM attendance a
INNER JOIN eventregistration er
ON a.registration_id = er.registration_id
INNER JOIN student s
ON er.matric_number = s.matric_number
INNER JOIN event e
ON er.event_id = e.event_id
WHERE s.matric_number = ?
ORDER BY e.event_date DESC
LIMIT ? OFFSET ?
";

$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("sii", $matric_number, $records_per_page, $history_offset);
$stmt_history->execute();
$history_result = $stmt_history->get_result();

$sql_points = "
SELECT 
SUM(a.point_awarded) AS total_points
FROM attendance a
INNER JOIN eventregistration er
ON a.registration_id = er.registration_id
INNER JOIN student s
ON er.matric_number = s.matric_number
WHERE s.matric_number = ?
";

$stmt_points = $conn->prepare($sql_points);
$stmt_points->bind_param("s", $matric_number);
$stmt_points->execute();
$points_result = $stmt_points->get_result();
$points_data = $points_result->fetch_assoc();
$total_points = $points_data['total_points'] ?? 0;

$recognition = "";
$recognition_class = "";

if ($total_points < 20) {
    $recognition = "Warning";
    $recognition_class = "recognition-red";
}
elseif ($total_points >= 20 && $total_points <= 49) {
    $recognition = "Participation Certificate";
    $recognition_class = "recognition-yellow";
}
elseif ($total_points >= 50 && $total_points <= 79) {
    $recognition = "Active Student";
    $recognition_class = "recognition-blue";
}
else {
    $recognition = "Outstanding Participant";
    $recognition_class = "recognition-green";
}

$sql_summary = "
SELECT 
COUNT(*) AS total_events,
SUM(LOWER(a.attendance_status) = 'present') AS total_present,
SUM(LOWER(a.attendance_status) = 'late') AS total_late,
SUM(LOWER(a.attendance_status) = 'volunteer') AS total_volunteer,
SUM(LOWER(a.attendance_status) = 'absent') AS total_absent
FROM attendance a
INNER JOIN eventregistration er
ON a.registration_id = er.registration_id
INNER JOIN student s
ON er.matric_number = s.matric_number
WHERE s.matric_number = ?
";

$stmt_summary = $conn->prepare($sql_summary);
$stmt_summary->bind_param("s", $matric_number);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();
$summary = $summary_result->fetch_assoc();


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="participation.css">
</head>
<body>

<?php include('adminHeader.php') ?>

<div class="page-container">

    <!-- TOP SECTION -->
    <div class="top-section">

        <!-- PROFILE -->
        <div class="student-profile">

            <img src="assets/images/uploads/<?php echo h($student['profile_photo']); ?>" alt="Profile" onerror="this.onerror=null; this.src='ProfilePhoto.png';">

            <div class="student-info">
                <h2><?php echo h($student_name); ?></h2>
                <p><?php echo h($matric_number); ?></p>
                <p>Faculty of Computer</p>
            </div>

        </div>

        <!-- TOTAL POINTS -->
        <div class="points-box">

            <p>Total Points</p>
            <h1><?php echo $total_points; ?></h1>

        </div>

        <!-- CURRENT RANKING -->
        <div class="ranking-box">

            <p>Current Ranking</p>
            <h2><?php echo h($rank_position_text); ?></h2>
            <span><?php echo h($rank_gap_text); ?></span>

        </div>

        <!-- RECOGNITION -->
        <div class="recognition-box">

            <p>Recognition Level</p>

            <div class="recognition-level <?php echo $recognition_class; ?>">
            <?php echo $recognition; ?>
            </div>

        </div>

    </div>


    <!-- PROGRESS BAR -->
    <div class="progress-wrapper">

        <div class="progress-bar">

            <div class="segment red"></div>
            <div class="segment yellow"></div>
            <div class="segment dark-blue"></div>
            <div class="segment green"></div>

        </div>

        <div class="progress-labels">
            <span>&lt; 20</span>
            <span>20 - 49</span>
            <span>50 - 79</span>
            <span>80+</span>
        </div>

    </div>


    <!-- BOTTOM SECTION -->
    <div class="bottom-section">

        <!-- PARTICIPATION HISTORY -->
        <div class="history-box">

            <h3>Participation History</h3>

            <table>

                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Points</th>
                    </tr>
                </thead>

                <tbody>

<?php while($row = $history_result->fetch_assoc()) : ?>

<tr>

    <td>
        <?php echo h($row['event_title']); ?>
    </td>

    <td>
        <?php echo date('d/m/Y', strtotime($row['event_date'])); ?>
    </td>

    <td>

        <?php

        $status_class = strtolower($row['attendance_status']);

        ?>

        <span class="badge <?php echo $status_class; ?>">

            <?php echo $row['attendance_status']; ?>

        </span>

    </td>

    <td class="<?php echo ($row['point_awarded'] >= 0) ? 'positive' : 'negative'; ?>">

        <?php echo ($row['point_awarded'] > 0 ? '+' : '') . $row['point_awarded']; ?>

    </td>

</tr>

<?php endwhile; ?>

</tbody>

            </table>

            <?php if ($history_total_pages > 1) : ?>
                <div class="pagination">
                    <?php
                    $base_params = ['matric_number' => $matric_number];
                    $previous_params = array_merge($base_params, ['history_page' => max(1, $history_page - 1)]);
                    $next_params = array_merge($base_params, ['history_page' => min($history_total_pages, $history_page + 1)]);
                    ?>
                    <a class="pagination-link <?php echo $history_page <= 1 ? 'disabled' : ''; ?>" href="student_participation.php?<?php echo h(http_build_query($previous_params)); ?>">Previous</a>
                    <span class="pagination-status">Page <?php echo $history_page; ?> of <?php echo $history_total_pages; ?></span>
                    <a class="pagination-link <?php echo $history_page >= $history_total_pages ? 'disabled' : ''; ?>" href="student_participation.php?<?php echo h(http_build_query($next_params)); ?>">Next</a>
                </div>
            <?php endif; ?>

        </div>


        <!-- SUMMARY -->
        <div class="summary-box">

            <h3>Summary</h3>

            <div class="summary-item">
                <span>Events Joined</span>
                <?php echo $summary['total_events']; ?>
            </div>

            <div class="summary-item">
                <span>Present</span>
                <?php echo $summary['total_present']; ?>
            </div>

            <div class="summary-item">
                <span>Late</span>
                <?php echo $summary['total_late']; ?>
            </div>

            <div class="summary-item">
                <span>Volunteer</span>
                <?php echo $summary['total_volunteer']; ?>
            </div>

            <div class="summary-item">
                <span>Absent</span>
                <?php echo $summary['total_absent']; ?>
            </div>

        </div>

    </div>

</div>

<script>
    //here put the when filter type is changed
</script>
</body>
</html>
?>
