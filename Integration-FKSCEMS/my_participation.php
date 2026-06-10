<?php
session_start();

include 'session.php';

$servername = "localhost";
$username = "root";
$password = "Amni102030.";
$dbname = "fk_scems_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['SESS_USER_ID'];
$sql_student = "
SELECT *
FROM student
WHERE user_id = ?
";

$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$student = $result_student->fetch_assoc();
$matric_number = $student['matric_number'];
$student_name = $student['name'];
$profile_photo = $student['profile_photo'];

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
";

$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("s", $matric_number);
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
    SUM(a.attendance_status = 'Present') AS total_present,
    SUM(a.attendance_status = 'Late') AS total_late,
    SUM(a.attendance_status = 'Volunteer') AS total_volunteer,
    SUM(a.attendance_status = 'Absent') AS total_absent
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

$user_id = $_SESSION['SESS_USER_ID']; 

// 3. FETCH DATA (JOINING USER AND STUDENT)
$sql = "SELECT u.email, u.contact_no, s.name, s.profile_photo 
        FROM user u 
        JOIN student s ON u.user_id = s.user_id 
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data) { die("Profile not found."); }
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

<?php include('studentHeader.php') ?>

<div class="page-container">

    <!-- TOP SECTION -->
    <div class="top-section">

        <!-- PROFILE -->
        <div class="student-profile">

            <img src="assets/images/uploads/<?php echo htmlspecialchars($user_data['profile_photo']); ?>" alt="Student"> 

            <div class="student-info">
                <h2><?php echo htmlspecialchars($student_name); ?></h2>
                <p><?php echo htmlspecialchars($matric_number); ?></p>
                <p>Faculty of Computer</p>
            </div>

        </div>

        <!-- TOTAL POINTS -->
        <div class="points-box">

            <p>Total Points</p>
            <h1><?php echo $total_points; ?></h1>

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
        <?php echo htmlspecialchars($row['event_title']); ?>
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