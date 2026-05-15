<?php
/**
 * Helper functions for FK Club Management System
 */

/** Format a date string for display */
function formatDate(string $date, string $format = 'd M Y'): string {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/** Format datetime string */
function formatDateTime(string $dt): string {
    if (!$dt) return '-';
    return date('d M Y, h:i A', strtotime($dt));
}

/** Return recognition level label based on total points */
function recognitionLevel(int $points): string {
    if ($points >= 50) return 'Outstanding Participant';
    if ($points >= 30) return 'Active Participant';
    if ($points >= 10) return 'Progressing';
    return 'Warning';
}

/** Return CSS class for recognition level */
function recognitionClass(int $points): string {
    if ($points >= 50) return 'level-outstanding';
    if ($points >= 30) return 'level-active';
    if ($points >= 10) return 'level-progressing';
    return 'level-warning';
}

/** Return badge class for event status */
function eventStatusClass(string $status): string {
    return match($status) {
        'upcoming'  => 'badge-info',
        'ongoing'   => 'badge-primary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        default     => 'badge-muted',
    };
}

/** Return badge class for attendance status */
function attendanceClass(string $status): string {
    return match($status) {
        'present' => 'badge-success',
        'late'    => 'badge-warning',
        'absent'  => 'badge-danger',
        default   => 'badge-muted',
    };
}

/** Return badge class for registration status */
function registrationClass(string $status): string {
    return match($status) {
        'registered' => 'badge-success',
        'waitlisted' => 'badge-warning',
        'cancelled'  => 'badge-danger',
        default      => 'badge-muted',
    };
}

/** Send JSON response and exit */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Get POST/GET param safely */
function input(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}

/** Return initials from a name (for avatar fallback) */
function initials(string $name): string {
    $words = explode(' ', trim($name));
    $init  = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $init .= strtoupper($w[0] ?? '');
    }
    return $init ?: 'U';
}
