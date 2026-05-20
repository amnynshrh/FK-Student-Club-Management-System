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

$search = $_GET['search'] ?? '';

/* =========================================
   STUDENT PARTICIPATION RANKING
========================================= */

$sql_students = "

SELECT

    s.matric_number,
    s.name,
    s.course,

    COALESCE(SUM(a.point_awarded), 0) AS total_points

FROM student s

LEFT JOIN eventregistration er
ON s.matric_number = er.matric_number

LEFT JOIN attendance a
ON er.registration_id = a.registration_id

WHERE
    s.name LIKE ?
    OR s.matric_number LIKE ?
    OR s.course LIKE ?

GROUP BY
    s.matric_number,
    s.name,
    s.course

ORDER BY total_points DESC

";

$stmt_students = $conn->prepare($sql_students);

$search_param = "%" . $search . "%";

$stmt_students->bind_param(
    "sss",
    $search_param,
    $search_param,
    $search_param
);

$stmt_students->execute();

$students_result = $stmt_students->get_result();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="student_list.css">
</head>
<body>

<?php include('adminHeader.php') ?>

<div class="page-container">
    <header class="page-header">
        <h1>Participation Tracking</h1>
        <p>Student participation in events.</p>
    </header>

    <div class="search-container">
        <form method="GET" class="search-form">
            <input 
                type="search"
                name="search"
                class="search-input"
                placeholder="Search student..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <button type="submit" class="search-btn">
                Search    
            </button>
        </form>
    </div>
    <div class="table-container">

        <div class="table-wrapper">
            <table class="member-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Total Points</th>
                        <th>Recognition Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;

                    while($row = $students_result->fetch_assoc()) :
                        $total_points = $row['total_points'];
                        /* =========================================
                        RECOGNITION LEVEL
                        ========================================= */
                        if ($total_points < 20) {
                            $recognition = "Warning";
                            $badge_class = "admin-red-badge";
                        }
                        elseif ($total_points >= 20 && $total_points <= 49) {
                            $recognition = "Participation Certificate";
                            $badge_class = "student-badge";
                        }
                        elseif ($total_points >= 50 && $total_points <= 79) {
                            $recognition = "Active Student";
                            $badge_class = "committee-badge";
                        }
                        else {
                            $recognition = "Outstanding Participant";
                            $badge_class = "student-badge";
                        }
                    ?>
                    <tr
                        class="clickable-row"
                        onclick="window.location='student_participation.php?matric_number=<?php echo $row['matric_number']; ?>'"
                    >
                        <td>
                            <?php echo $rank++; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['course']); ?>
                        </td>
                        <td>
                            <?php echo $total_points; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo $recognition; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>

document.querySelector('.search-input').addEventListener('search', function () {

    // If search field is cleared
    if (this.value === '') {

        this.form.submit();
    }
});

</script>
</body>
</html>
?>