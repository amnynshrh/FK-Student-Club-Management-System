<?php
session_start();

$conn = mysqli_connect('localhost', 'root', '', 'fk_scems_db');
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$eventId = (int) ($_GET['event_id'] ?? 0);
if ($eventId <= 0) {
    header('Location: manage_events.php?error=missing_event');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT event_id, event_title FROM event WHERE event_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $eventId);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$event) {
    header('Location: manage_events.php?error=missing_event');
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$attendanceUrl = $scheme . '://' . $host . ($basePath === '' ? '' : $basePath) . '/attendance.php?event_id=' . $eventId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generating Event QR</title>
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            background: #f4f7fb;
            font-family: Segoe UI, Tahoma, sans-serif;
            color: #172033;
        }
        .qr-card {
            width: min(92vw, 420px);
            padding: 28px;
            border-radius: 12px;
            background: #fff;
            text-align: center;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.12);
        }
        #eventQr {
            display: inline-block;
            margin: 20px auto;
            padding: 14px;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            background: #fff;
        }
        .qr-status {
            color: #64748b;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="qr-card">
        <h1>Generating QR Code</h1>
        <p><?php echo e($event['event_title']); ?></p>
        <div id="eventQr"></div>
        <p class="qr-status" id="qrStatus">Preparing QR image...</p>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="QR.js"></script>
    <script>
        (function () {
            var statusBox = document.getElementById('qrStatus');

            EventQrGenerator.createPng({
                containerId: 'eventQr',
                text: <?php echo json_encode($attendanceUrl); ?>,
                size: 512
            }).then(function (pngDataUrl) {
                statusBox.textContent = 'Saving QR image...';
                return fetch('save_event_qr.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        event_id: <?php echo json_encode((string) $eventId); ?>,
                        qr_png: pngDataUrl
                    })
                });
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (!result.success) {
                    throw new Error(result.message || 'Unable to save QR image.');
                }
                statusBox.textContent = 'QR saved. Redirecting...';
                window.location.href = 'manage_events.php?success=event_added';
            }).catch(function (error) {
                statusBox.textContent = error.message;
            });
        })();
    </script>
</body>
</html>
