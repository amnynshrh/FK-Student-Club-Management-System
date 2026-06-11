<?php
session_start();
header('Content-Type: application/json');

$conn = mysqli_connect('localhost', 'root', '', 'fk_scems_db');
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

$eventId = (int) ($_POST['event_id'] ?? 0);
$qrPng = (string) ($_POST['qr_png'] ?? '');

if ($eventId <= 0 || $qrPng === '') {
    echo json_encode(['success' => false, 'message' => 'Missing QR data.']);
    exit;
}

if (!preg_match('/^data:image\/png;base64,/', $qrPng)) {
    echo json_encode(['success' => false, 'message' => 'Invalid QR image format.']);
    exit;
}

$binary = base64_decode(substr($qrPng, strpos($qrPng, ',') + 1), true);
if ($binary === false) {
    echo json_encode(['success' => false, 'message' => 'Unable to decode QR image.']);
    exit;
}

$directory = __DIR__ . DIRECTORY_SEPARATOR . 'eventsQR';
if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
    echo json_encode(['success' => false, 'message' => 'Unable to create QR folder.']);
    exit;
}

$fileName = $eventId . '.png';
$filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

if (file_put_contents($filePath, $binary) === false) {
    echo json_encode(['success' => false, 'message' => 'Unable to write QR file.']);
    exit;
}

$stmt = mysqli_prepare($conn, "UPDATE event SET qr_code = ? WHERE event_id = ?");
mysqli_stmt_bind_param($stmt, 'si', $fileName, $eventId);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Unable to update event QR reference.']);
    exit;
}

echo json_encode(['success' => true, 'file_name' => $fileName]);
