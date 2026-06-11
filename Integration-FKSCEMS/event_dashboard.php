<?php
session_start();

if (
    !(
        (isset($_SESSION['SESS_ROLE']) && strtolower((string)$_SESSION['SESS_ROLE']) === 'admin') ||
        (isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin')
    )
) {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/config/db.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function single_value($conn, $sql, $default = 0)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $default;
    }

    $row = mysqli_fetch_row($result);
    return $row[0] ?? $default;
}

function fetch_rows($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

mysqli_query($conn, "UPDATE event SET event_status=CASE WHEN event_status='cancelled' THEN 'cancelled' WHEN NOW()>CONCAT(event_date,' ',end_time) THEN 'completed' WHEN NOW() BETWEEN CONCAT(event_date,' ',event_time) AND CONCAT(event_date,' ',end_time) THEN 'ongoing' WHEN (SELECT COUNT(*) FROM eventregistration er WHERE er.event_id=event.event_id AND er.registration_status='registered')>=max_participant THEN 'full' WHEN registration_open=1 THEN 'open' ELSE 'upcoming' END");

$total_events = (int) single_value($conn, "SELECT COUNT(*) FROM event");
$total_registrations = (int) single_value($conn, "SELECT COUNT(*) FROM eventregistration WHERE registration_status='registered'");

$most_active = fetch_rows($conn, "SELECT c.club_name, COUNT(e.event_id) AS total_events FROM club c LEFT JOIN event e ON e.club_id=c.club_id GROUP BY c.club_id, c.club_name ORDER BY total_events DESC, c.club_name ASC LIMIT 1");
$most_active_club = $most_active[0] ?? ['club_name' => 'No club yet', 'total_events' => 0];

$popular = fetch_rows($conn, "SELECT e.event_title, COUNT(er.registration_id) AS total_registration FROM event e LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' GROUP BY e.event_id, e.event_title ORDER BY total_registration DESC, e.event_title ASC LIMIT 1");
$popular_event = $popular[0] ?? ['event_title' => 'No event yet', 'total_registration' => 0];

$events_by_club = fetch_rows($conn, "SELECT c.club_name, COUNT(e.event_id) AS total_events FROM club c LEFT JOIN event e ON e.club_id=c.club_id GROUP BY c.club_id, c.club_name ORDER BY total_events DESC, c.club_name ASC LIMIT 6");
$participants_by_event = fetch_rows($conn, "SELECT e.event_title, COUNT(er.registration_id) AS total_participants FROM event e LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' GROUP BY e.event_id, e.event_title ORDER BY total_participants DESC, e.event_title ASC LIMIT 6");
$popular_events = fetch_rows($conn, "SELECT e.event_title, COUNT(er.registration_id) AS total_registration FROM event e LEFT JOIN eventregistration er ON er.event_id=e.event_id AND er.registration_status='registered' GROUP BY e.event_id, e.event_title ORDER BY total_registration DESC, e.event_title ASC LIMIT 5");
$status_rows = fetch_rows($conn, "SELECT event_status, COUNT(*) AS total FROM event GROUP BY event_status ORDER BY total DESC");

$club_chart_labels = array_column($events_by_club, 'club_name');
$club_chart_values = array_map('intval', array_column($events_by_club, 'total_events'));
$participant_chart_labels = array_column($participants_by_event, 'event_title');
$participant_chart_values = array_map('intval', array_column($participants_by_event, 'total_participants'));
$popular_chart_labels = array_column($popular_events, 'event_title');
$popular_chart_values = array_map('intval', array_column($popular_events, 'total_registration'));
$status_chart_labels = array_map('ucfirst', array_column($status_rows, 'event_status'));
$status_chart_values = array_map('intval', array_column($status_rows, 'total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Dashboard - FK Student Club</title>
    <link rel="stylesheet" href="admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .event-dashboard {
            max-width: 1200px;
            margin: 30px auto 0;
            padding: 0 20px 30px;
        }

        .event-dashboard .page-header {
            text-align: center;
            margin: 34px 0 28px;
        }

        .event-dashboard .page-header h1 {
            color: #1c3f95;
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .dashboard-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .event-card,
        .dashboard-panel,
        .quick-insight {
            background: #ffffff;
            border: 1px solid #d9dee8;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(28, 63, 149, 0.06);
        }

        .event-card {
            display: flex;
            gap: 18px;
            align-items: center;
            min-height: 126px;
            padding: 20px;
        }

        .event-card-icon {
            width: 66px;
            height: 66px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.8rem;
            color: #1c3f95;
            background: #eef5ff;
            flex: 0 0 auto;
        }

        .event-card-icon.teal {
            background: #e7f8f6;
            color: #009e96;
        }

        .event-card p,
        .panel-subtitle {
            color: #667085;
            font-size: 0.9rem;
            margin: 0 0 6px;
        }

        .event-card h2 {
            color: #1f2a44;
            font-size: 1.85rem;
            margin: 0;
        }

        .event-card strong {
            color: #1f2a44;
            display: block;
            margin-bottom: 5px;
        }

        .dashboard-panels {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .dashboard-panel,
        .quick-insight {
            padding: 24px;
        }

        .dashboard-panel h3,
        .quick-insight h3 {
            color: #1f2a44;
            font-size: 1rem;
            margin: 0 0 8px;
            text-align: center;
        }

        .chart-wrap {
            height: 260px;
            margin-top: 18px;
            position: relative;
        }

        .quick-insight .chart-wrap {
            height: 310px;
        }

        .bar-list {
            display: grid;
            gap: 14px;
            margin-top: 22px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: minmax(105px, 1fr) 2fr 34px;
            gap: 10px;
            align-items: center;
            font-size: 0.85rem;
        }

        .bar-track {
            height: 12px;
            border-radius: 999px;
            overflow: hidden;
            background: #eef1f6;
        }

        .bar-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #1c3f95;
        }

        .bar-fill.teal {
            background: #009e96;
        }

        .popular-list {
            display: grid;
            gap: 12px;
            margin-top: 22px;
        }

        .popular-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eef1f6;
            font-size: 0.9rem;
        }

        .popular-item span:last-child {
            color: #1c3f95;
            font-weight: 700;
            white-space: nowrap;
        }

        .quick-insight {
            max-width: 720px;
            margin: 0 auto;
        }

        .status-graph {
            display: flex;
            align-items: end;
            justify-content: center;
            gap: 18px;
            min-height: 210px;
            padding-top: 26px;
        }

        .status-bar {
            display: grid;
            justify-items: center;
            gap: 8px;
            min-width: 82px;
        }

        .status-column {
            width: 42px;
            min-height: 14px;
            border-radius: 8px 8px 0 0;
            background: #1c3f95;
        }

        .status-label {
            color: #667085;
            font-size: 0.78rem;
            text-transform: capitalize;
        }

        .site-footer {
            color: #667085;
            font-size: 0.85rem;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 980px) {
            .dashboard-summary,
            .dashboard-panels {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .dashboard-summary,
            .dashboard-panels {
                grid-template-columns: 1fr;
            }

            .bar-row {
                grid-template-columns: 1fr;
            }

            .status-graph {
                overflow-x: auto;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
<?php include('adminHeader.php'); ?>

<main class="event-dashboard">
    <header class="page-header">
        <p>Event Management System</p>
        <h1>Event Dashboard</h1>
    </header>

    <section class="dashboard-summary" aria-label="Event summary">
        <article class="event-card">
            <div class="event-card-icon">📋</div>
            <div>
                <p>Total Events</p>
                <h2><?php echo number_format($total_events); ?></h2>
            </div>
        </article>

        <article class="event-card">
            <div class="event-card-icon teal">🗓</div>
            <div>
                <p>Total Registration</p>
                <h2><?php echo number_format($total_registrations); ?></h2>
            </div>
        </article>

        <article class="event-card">
            <div class="event-card-icon">🏆</div>
            <div>
                <p>Most Active Club</p>
                <strong><?php echo e($most_active_club['club_name']); ?></strong>
                <span><?php echo number_format((int)$most_active_club['total_events']); ?> events organized</span>
            </div>
        </article>

        <article class="event-card">
            <div class="event-card-icon teal">⭐</div>
            <div>
                <p>Most Popular Event</p>
                <strong><?php echo e($popular_event['event_title']); ?></strong>
                <span><?php echo number_format((int)$popular_event['total_registration']); ?> Registration</span>
            </div>
        </article>
    </section>

    <section class="dashboard-panels">
        <article class="dashboard-panel">
            <h3>Events Organized by Each Club</h3>
            <p class="panel-subtitle">Bar graph</p>
            <div class="chart-wrap">
                <canvas id="clubEventsChart"></canvas>
            </div>
        </article>

        <article class="dashboard-panel">
            <h3>Participants for Each Event</h3>
            <p class="panel-subtitle">Horizontal graph</p>
            <div class="chart-wrap">
                <canvas id="participantsChart"></canvas>
            </div>
        </article>

        <article class="dashboard-panel">
            <h3>Popular Events by Registration Count</h3>
            <p class="panel-subtitle">Doughnut graph</p>
            <div class="chart-wrap">
                <canvas id="popularEventsChart"></canvas>
            </div>
        </article>
    </section>

    <section class="quick-insight">
        <h3>Quick Insight</h3>
        <p class="panel-subtitle" style="text-align: center;">Event status graph</p>
        <div class="chart-wrap">
            <canvas id="statusChart"></canvas>
        </div>
    </section>
</main>

<footer class="site-footer">
    Copyright © 2026 Faculty of Computing - Universiti Malaysia Pahang Al Sultan Abdullah
</footer>
<script>
    const chartColors = {
        navy: '#1c3f95',
        teal: '#009e96',
        gold: '#ffcc00',
        red: '#dc3545',
        slate: '#667085',
        paleBlue: 'rgba(28, 63, 149, 0.12)'
    };

    const tooltipStyle = {
        backgroundColor: '#1f2a44',
        titleFont: { size: 13, weight: 'bold' },
        bodyFont: { size: 12 },
        padding: 10
    };

    const axisOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: tooltipStyle
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0, color: '#667085' },
                grid: { color: '#eef1f6' }
            },
            x: {
                ticks: { color: '#667085', maxRotation: 35, minRotation: 0 },
                grid: { display: false }
            }
        }
    };

    if (typeof Chart !== 'undefined') {
    new Chart(document.getElementById('clubEventsChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($club_chart_labels); ?>,
            datasets: [{
                label: 'Events Organized',
                data: <?php echo json_encode($club_chart_values); ?>,
                backgroundColor: chartColors.navy,
                borderRadius: 8,
                maxBarThickness: 42
            }]
        },
        options: axisOptions
    });

    new Chart(document.getElementById('participantsChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($participant_chart_labels); ?>,
            datasets: [{
                label: 'Participants',
                data: <?php echo json_encode($participant_chart_values); ?>,
                backgroundColor: chartColors.teal,
                borderRadius: 8,
                maxBarThickness: 34
            }]
        },
        options: {
            ...axisOptions,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#667085' },
                    grid: { color: '#eef1f6' }
                },
                y: {
                    ticks: { color: '#667085' },
                    grid: { display: false }
                }
            }
        }
    });

    new Chart(document.getElementById('popularEventsChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($popular_chart_labels); ?>,
            datasets: [{
                label: 'Registrations',
                data: <?php echo json_encode($popular_chart_values); ?>,
                backgroundColor: [chartColors.navy, chartColors.teal, chartColors.gold, chartColors.red, chartColors.slate],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { boxWidth: 12, color: '#667085' }
                },
                tooltip: tooltipStyle
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($status_chart_labels); ?>,
            datasets: [{
                label: 'Event Status Count',
                data: <?php echo json_encode($status_chart_values); ?>,
                borderColor: chartColors.navy,
                backgroundColor: chartColors.paleBlue,
                pointBackgroundColor: chartColors.teal,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            ...axisOptions,
            plugins: {
                ...axisOptions.plugins,
                legend: { display: true, labels: { color: '#667085' } }
            }
        }
    });
    } else {
        const fallbackData = {
            clubs: {
                labels: <?php echo json_encode($club_chart_labels); ?>,
                values: <?php echo json_encode($club_chart_values); ?>
            },
            participants: {
                labels: <?php echo json_encode($participant_chart_labels); ?>,
                values: <?php echo json_encode($participant_chart_values); ?>
            },
            popular: {
                labels: <?php echo json_encode($popular_chart_labels); ?>,
                values: <?php echo json_encode($popular_chart_values); ?>
            },
            statuses: {
                labels: <?php echo json_encode($status_chart_labels); ?>,
                values: <?php echo json_encode($status_chart_values); ?>
            }
        };

        function setupCanvas(id) {
            const canvas = document.getElementById(id);
            const rect = canvas.parentElement.getBoundingClientRect();
            const scale = window.devicePixelRatio || 1;
            canvas.width = Math.max(320, rect.width) * scale;
            canvas.height = Math.max(220, rect.height) * scale;
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            const ctx = canvas.getContext('2d');
            ctx.scale(scale, scale);
            return { canvas, ctx, width: canvas.width / scale, height: canvas.height / scale };
        }

        function label(text, max = 15) {
            return String(text || '').length > max ? String(text).slice(0, max - 1) + '.' : String(text || '');
        }

        function drawBarChart(id, labels, values, color) {
            const { ctx, width, height } = setupCanvas(id);
            const padding = { top: 24, right: 18, bottom: 62, left: 42 };
            const chartW = width - padding.left - padding.right;
            const chartH = height - padding.top - padding.bottom;
            const max = Math.max(1, ...values);
            ctx.clearRect(0, 0, width, height);
            ctx.strokeStyle = '#eef1f6';
            ctx.fillStyle = '#667085';
            ctx.font = '12px Segoe UI';
            for (let i = 0; i <= 4; i++) {
                const y = padding.top + chartH - (chartH / 4) * i;
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(width - padding.right, y);
                ctx.stroke();
                ctx.fillText(Math.round((max / 4) * i), 8, y + 4);
            }
            const gap = 14;
            const barW = Math.max(18, (chartW - gap * Math.max(0, values.length - 1)) / Math.max(1, values.length));
            values.forEach((value, index) => {
                const barH = (value / max) * chartH;
                const x = padding.left + index * (barW + gap);
                const y = padding.top + chartH - barH;
                ctx.fillStyle = color;
                ctx.beginPath();
                ctx.roundRect(x, y, barW, barH, 7);
                ctx.fill();
                ctx.fillStyle = '#1f2a44';
                ctx.font = 'bold 12px Segoe UI';
                ctx.fillText(value, x + barW / 2 - 4, y - 7);
                ctx.save();
                ctx.translate(x + barW / 2, height - 16);
                ctx.rotate(-0.42);
                ctx.fillStyle = '#667085';
                ctx.font = '11px Segoe UI';
                ctx.fillText(label(labels[index]), -30, 0);
                ctx.restore();
            });
        }

        function drawHorizontalBarChart(id, labels, values, color) {
            const { ctx, width, height } = setupCanvas(id);
            const padding = { top: 20, right: 34, bottom: 24, left: 110 };
            const chartW = width - padding.left - padding.right;
            const rowH = (height - padding.top - padding.bottom) / Math.max(1, values.length);
            const max = Math.max(1, ...values);
            ctx.clearRect(0, 0, width, height);
            values.forEach((value, index) => {
                const y = padding.top + index * rowH + rowH * 0.26;
                const barW = (value / max) * chartW;
                ctx.fillStyle = '#667085';
                ctx.font = '12px Segoe UI';
                ctx.fillText(label(labels[index], 14), 8, y + 15);
                ctx.fillStyle = '#eef1f6';
                ctx.beginPath();
                ctx.roundRect(padding.left, y, chartW, 18, 9);
                ctx.fill();
                ctx.fillStyle = color;
                ctx.beginPath();
                ctx.roundRect(padding.left, y, Math.max(8, barW), 18, 9);
                ctx.fill();
                ctx.fillStyle = '#1f2a44';
                ctx.font = 'bold 12px Segoe UI';
                ctx.fillText(value, padding.left + barW + 8, y + 14);
            });
        }

        function drawDoughnutChart(id, labels, values) {
            const { ctx, width, height } = setupCanvas(id);
            const colors = [chartColors.navy, chartColors.teal, chartColors.gold, chartColors.red, chartColors.slate];
            const total = values.reduce((sum, value) => sum + value, 0) || 1;
            const cx = width / 2;
            const cy = height / 2 - 18;
            const outer = Math.min(width, height) * 0.28;
            const inner = outer * 0.58;
            let start = -Math.PI / 2;
            ctx.clearRect(0, 0, width, height);
            values.forEach((value, index) => {
                const angle = (value / total) * Math.PI * 2;
                ctx.beginPath();
                ctx.arc(cx, cy, outer, start, start + angle);
                ctx.arc(cx, cy, inner, start + angle, start, true);
                ctx.closePath();
                ctx.fillStyle = colors[index % colors.length];
                ctx.fill();
                start += angle;
            });
            ctx.fillStyle = '#1f2a44';
            ctx.font = 'bold 18px Segoe UI';
            ctx.textAlign = 'center';
            ctx.fillText(total, cx, cy + 6);
            ctx.textAlign = 'left';
            ctx.font = '11px Segoe UI';
            labels.slice(0, 5).forEach((item, index) => {
                const x = 18 + (index % 2) * (width / 2 - 16);
                const y = height - 50 + Math.floor(index / 2) * 18;
                ctx.fillStyle = colors[index % colors.length];
                ctx.fillRect(x, y - 9, 10, 10);
                ctx.fillStyle = '#667085';
                ctx.fillText(label(item, 16), x + 16, y);
            });
        }

        function drawLineChart(id, labels, values) {
            const { ctx, width, height } = setupCanvas(id);
            const padding = { top: 30, right: 26, bottom: 48, left: 42 };
            const chartW = width - padding.left - padding.right;
            const chartH = height - padding.top - padding.bottom;
            const max = Math.max(1, ...values);
            ctx.clearRect(0, 0, width, height);
            ctx.strokeStyle = '#eef1f6';
            ctx.fillStyle = '#667085';
            ctx.font = '12px Segoe UI';
            for (let i = 0; i <= 4; i++) {
                const y = padding.top + chartH - (chartH / 4) * i;
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(width - padding.right, y);
                ctx.stroke();
                ctx.fillText(Math.round((max / 4) * i), 8, y + 4);
            }
            const points = values.map((value, index) => ({
                x: padding.left + (chartW / Math.max(1, values.length - 1)) * index,
                y: padding.top + chartH - (value / max) * chartH
            }));
            ctx.beginPath();
            points.forEach((point, index) => index ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y));
            ctx.lineTo(points[points.length - 1]?.x || padding.left, padding.top + chartH);
            ctx.lineTo(points[0]?.x || padding.left, padding.top + chartH);
            ctx.closePath();
            ctx.fillStyle = chartColors.paleBlue;
            ctx.fill();
            ctx.beginPath();
            points.forEach((point, index) => index ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y));
            ctx.strokeStyle = chartColors.navy;
            ctx.lineWidth = 3;
            ctx.stroke();
            points.forEach((point, index) => {
                ctx.fillStyle = chartColors.teal;
                ctx.beginPath();
                ctx.arc(point.x, point.y, 5, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#667085';
                ctx.font = '12px Segoe UI';
                ctx.fillText(label(labels[index], 11), point.x - 18, height - 18);
            });
        }

        drawBarChart('clubEventsChart', fallbackData.clubs.labels, fallbackData.clubs.values, chartColors.navy);
        drawHorizontalBarChart('participantsChart', fallbackData.participants.labels, fallbackData.participants.values, chartColors.teal);
        drawDoughnutChart('popularEventsChart', fallbackData.popular.labels, fallbackData.popular.values);
        drawLineChart('statusChart', fallbackData.statuses.labels, fallbackData.statuses.values);
    }
</script>
</body>
</html>
