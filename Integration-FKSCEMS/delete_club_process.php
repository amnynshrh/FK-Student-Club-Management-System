<?php
session_start();

include('session.php');
require_once 'config/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: delete_club.php?error=invalid_request");
    exit;
}

$clubId = filter_var($_POST['club_id'] ?? null, FILTER_VALIDATE_INT);

if (!$clubId) {
    header("Location: delete_club.php?error=invalid_club");
    exit;
}

mysqli_begin_transaction($conn);

try {
    $eventIds = [];
    $eventStmt = mysqli_prepare($conn, "SELECT event_id FROM event WHERE club_id = ?");
    mysqli_stmt_bind_param($eventStmt, "i", $clubId);
    mysqli_stmt_execute($eventStmt);
    $eventResult = mysqli_stmt_get_result($eventStmt);
    while ($row = mysqli_fetch_assoc($eventResult)) {
        $eventIds[] = (int) $row['event_id'];
    }

    if ($eventIds) {
        $attendanceStmt = mysqli_prepare($conn, "DELETE a FROM attendance a INNER JOIN eventregistration er ON er.registration_id = a.registration_id WHERE er.event_id = ?");
        $waitingStmt = mysqli_prepare($conn, "DELETE FROM eventwaitinglist WHERE event_id = ?");
        $registrationStmt = mysqli_prepare($conn, "DELETE FROM eventregistration WHERE event_id = ?");
        $eventDeleteStmt = mysqli_prepare($conn, "DELETE FROM event WHERE event_id = ?");

        foreach ($eventIds as $eventId) {
            mysqli_stmt_bind_param($attendanceStmt, "i", $eventId);
            mysqli_stmt_execute($attendanceStmt);

            mysqli_stmt_bind_param($waitingStmt, "i", $eventId);
            mysqli_stmt_execute($waitingStmt);

            mysqli_stmt_bind_param($registrationStmt, "i", $eventId);
            mysqli_stmt_execute($registrationStmt);

            mysqli_stmt_bind_param($eventDeleteStmt, "i", $eventId);
            mysqli_stmt_execute($eventDeleteStmt);
        }
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM committee WHERE club_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $clubId);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM membership WHERE club_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $clubId);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM club WHERE club_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $clubId);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) < 1) {
        throw new RuntimeException("Club not found.");
    }

    mysqli_commit($conn);
    header("Location: delete_club.php?deleted=1");
    exit;
} catch (Throwable $th) {
    mysqli_rollback($conn);
    header("Location: delete_club.php?error=delete_failed");
    exit;
}
