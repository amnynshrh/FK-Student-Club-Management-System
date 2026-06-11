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
$sort = $_GET['sort'] ?? 'total_points';
$direction = strtolower($_GET['direction'] ?? 'desc');

$allowed_sorts = [
    'rank' => 'total_points',
    'name' => 's.name',
    'course' => 's.course',
    'total_points' => 'total_points',
    'recognition' => 'recognition_rank',
];

if (!array_key_exists($sort, $allowed_sorts)) {
    $sort = 'total_points';
}

$direction = $direction === 'asc' ? 'asc' : 'desc';
$order_direction = strtoupper($direction);
$order_column = $allowed_sorts[$sort];

if ($sort === 'rank') {
    $order_direction = $direction === 'asc' ? 'DESC' : 'ASC';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sort_link($column, $label, $current_sort, $current_direction, $search)
{
    $next_direction = ($current_sort === $column && $current_direction === 'asc') ? 'desc' : 'asc';
    $params = [
        'sort' => $column,
        'direction' => $next_direction,
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    $icon_class = $current_sort === $column ? 'is-' . $current_direction : 'is-neutral';

    return '<a class="sort-link ' . ($current_sort === $column ? 'active' : '') . '" href="student_list.php?' . h(http_build_query($params)) . '">' .
        '<span>' . h($label) . '</span><span class="sort-icon ' . h($icon_class) . '" aria-hidden="true"></span></a>';
}

/* =========================================
   STUDENT PARTICIPATION RANKING
========================================= */

$sql_students = "

SELECT

    s.matric_number,
    s.name,
    s.course,

    COALESCE(SUM(a.point_awarded), 0) AS total_points,
    CASE
        WHEN COALESCE(SUM(a.point_awarded), 0) < 20 THEN 1
        WHEN COALESCE(SUM(a.point_awarded), 0) BETWEEN 20 AND 49 THEN 2
        WHEN COALESCE(SUM(a.point_awarded), 0) BETWEEN 50 AND 79 THEN 3
        ELSE 4
    END AS recognition_rank

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

ORDER BY $order_column $order_direction, s.name ASC

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
$students = [];

while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}

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

    <div class="search-container">
        <header class="page-header">
            <h1>Participation Tracking</h1>
            <p>Student participation in events.</p>
        </header>
        <form method="GET" class="search-form">
            <input 
                type="search"
                name="search"
                class="search-input"
                placeholder="Search student..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <input type="hidden" name="sort" value="<?php echo h($sort); ?>">
            <input type="hidden" name="direction" value="<?php echo h($direction); ?>">
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
                        <th><?php echo sort_link('rank', 'Rank', $sort, $direction, $search); ?></th>
                        <th><?php echo sort_link('name', 'Student Name', $sort, $direction, $search); ?></th>
                        <th><?php echo sort_link('course', 'Course', $sort, $direction, $search); ?></th>
                        <th><?php echo sort_link('total_points', 'Total Points', $sort, $direction, $search); ?></th>
                        <th><?php echo sort_link('recognition', 'Recognition Level', $sort, $direction, $search); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = ($sort === 'rank' && $direction === 'desc') ? count($students) : 1;

                    foreach($students as $row) :
                        $total_points = $row['total_points'];
                        /* =========================================
                        RECOGNITION LEVEL
                        ========================================= */
                        if ($total_points < 20) {
                            $recognition = "Warning";
                            $badge_class = "recognition-warning";
                        }
                        elseif ($total_points >= 20 && $total_points <= 49) {
                            $recognition = "Participation Certificate";
                            $badge_class = "recognition-certificate";
                        }
                        elseif ($total_points >= 50 && $total_points <= 79) {
                            $recognition = "Active Student";
                            $badge_class = "recognition-active";
                        }
                        else {
                            $recognition = "Outstanding Participant";
                            $badge_class = "recognition-outstanding";
                        }
                    ?>
                    <tr
                        class="clickable-row"
                        onclick="window.location='student_participation.php?matric_number=<?php echo urlencode($row['matric_number']); ?>'"
                    >
                        <td class="center">
                            <?php
                            echo $rank;
                            $rank += ($sort === 'rank' && $direction === 'desc') ? -1 : 1;
                            ?>
                        </td>
                        <td>
                            <?php echo h($row['name']); ?>
                        </td>
                        <td>
                            <?php echo h($row['course']); ?>
                        </td>
                        <td class="center">
                            <?php echo $total_points; ?>
                        </td>
                        <td class="center">
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo $recognition; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
