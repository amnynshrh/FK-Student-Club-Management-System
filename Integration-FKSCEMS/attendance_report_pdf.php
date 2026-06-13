<?php

$conn = new mysqli("localhost", "root", "", "fk_scems_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function fetch_rows($conn, $sql, $types = "", $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query prepare failed: " . $conn->error);
    }
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function fetch_row($conn, $sql, $types = "", $params = [])
{
    $rows = fetch_rows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function report_filters()
{
    $clubName = trim($_GET['club_name'] ?? '');
    $monthYear = trim($_GET['month_year'] ?? '');
    $eventName = trim($_GET['event_name'] ?? '');

    $filters = [];
    $params = [];
    $types = "";

    if ($clubName !== "") {
        $filters[] = "c.club_name = ?";
        $params[] = $clubName;
        $types .= "s";
    }

    if ($monthYear !== "") {
        $filters[] = "DATE_FORMAT(e.event_date, '%Y-%m') = ?";
        $params[] = $monthYear;
        $types .= "s";
    }

    if ($eventName !== "") {
        $filters[] = "e.event_title LIKE ?";
        $params[] = "%" . $eventName . "%";
        $types .= "s";
    }

    return [
        "club_name" => $clubName,
        "month_year" => $monthYear,
        "event_name" => $eventName,
        "where" => $filters ? "WHERE " . implode(" AND ", $filters) : "",
        "params" => $params,
        "types" => $types,
    ];
}

function pdf_clean($text)
{
    $text = (string) $text;
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
}

class SimplePdf
{
    private $pages = [];
    private $content = "";
    private $width = 842;
    private $height = 595;
    private $margin = 34;
    private $y = 0;

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage()
    {
        if ($this->content !== "") {
            $this->pages[] = $this->content;
        }
        $this->content = "";
        $this->y = $this->height - $this->margin;
    }

    private function escape($text)
    {
        return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], pdf_clean($text));
    }

    private function ensureSpace($needed = 18)
    {
        if ($this->y - $needed < $this->margin) {
            $this->addPage();
        }
    }

    public function text($x, $size, $text, $bold = false)
    {
        $this->ensureSpace($size + 6);
        $font = $bold ? "F2" : "F1";
        $this->content .= "BT /{$font} {$size} Tf {$x} {$this->y} Td (" . $this->escape($text) . ") Tj ET\n";
    }

    public function newLine($height = 14)
    {
        $this->y -= $height;
    }

    public function section($title)
    {
        $this->newLine(8);
        $this->text($this->margin, 13, $title, true);
        $this->newLine(18);
    }

    public function row($columns, $widths, $bold = false, $height = 16)
    {
        $this->ensureSpace($height + 2);
        $x = $this->margin;
        foreach ($columns as $index => $value) {
            $width = $widths[$index] ?? 80;
            $text = substr((string) $value, 0, max(8, (int) ($width / 4.5)));
            $this->content .= "BT /" . ($bold ? "F2" : "F1") . " " . ($bold ? "8.5" : "8") . " Tf {$x} {$this->y} Td (" . $this->escape($text) . ") Tj ET\n";
            $x += $width;
        }
        $this->y -= $height;
    }

    public function output($filename)
    {
        if ($this->content !== "") {
            $this->pages[] = $this->content;
            $this->content = "";
        }

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        $pageObjects = [];
        $contentObjects = [];
        $fontObjectNumber = 3;
        $boldFontObjectNumber = 4;
        $nextObject = 5;

        foreach ($this->pages as $pageContent) {
            $contentNumber = $nextObject++;
            $pageNumber = $nextObject++;
            $kids[] = "{$pageNumber} 0 R";
            $contentObjects[$contentNumber] = "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream";
            $pageObjects[$pageNumber] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->width} {$this->height}] /Resources << /Font << /F1 {$fontObjectNumber} 0 R /F2 {$boldFontObjectNumber} 0 R >> >> /Contents {$contentNumber} 0 R >>";
        }

        $objects[] = "<< /Type /Pages /Kids [" . implode(" ", $kids) . "] /Count " . count($kids) . " >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        for ($i = 5; $i < $nextObject; $i++) {
            $objects[] = $contentObjects[$i] ?? $pageObjects[$i];
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectNumber = $index + 1;
            $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Content-Length: " . strlen($pdf));
        echo $pdf;
        exit;
    }
}

$filter = report_filters();
$where = $filter["where"];
$types = $filter["types"];
$params = $filter["params"];

$summary = fetch_row(
    $conn,
    "SELECT
        COUNT(DISTINCT e.event_id) AS total_events,
        COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END) AS total_participants,
        COALESCE(SUM(CASE WHEN er.registration_status = 'registered' THEN a.point_awarded ELSE 0 END), 0) AS total_points,
        ROUND(SUM(CASE WHEN er.registration_status = 'registered' AND LOWER(a.attendance_status) IN ('present', 'late') THEN 1 ELSE 0 END)
            / NULLIF(COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END), 0) * 100, 2) AS attendance_rate
     FROM event e
     INNER JOIN club c ON e.club_id = c.club_id
     LEFT JOIN eventregistration er ON e.event_id = er.event_id
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $where",
    $types,
    $params
);

$eventRows = fetch_rows(
    $conn,
    "SELECT
        e.event_title,
        c.club_name,
        e.event_date,
        COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END) AS participant_count,
        SUM(CASE WHEN er.registration_status = 'registered' AND LOWER(a.attendance_status) IN ('present', 'late') THEN 1 ELSE 0 END) AS attended_count,
        ROUND(SUM(CASE WHEN er.registration_status = 'registered' AND LOWER(a.attendance_status) IN ('present', 'late') THEN 1 ELSE 0 END)
            / NULLIF(COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END), 0) * 100, 2) AS attendance_rate
     FROM event e
     INNER JOIN club c ON e.club_id = c.club_id
     LEFT JOIN eventregistration er ON e.event_id = er.event_id
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $where
     GROUP BY e.event_id, e.event_title, c.club_name, e.event_date
     ORDER BY e.event_date DESC, e.event_title ASC",
    $types,
    $params
);

$studentEventRows = fetch_rows(
    $conn,
    "SELECT
        e.event_title,
        s.name,
        s.matric_number,
        COALESCE(a.point_awarded, 0) AS points,
        COALESCE(a.attendance_status, 'not marked') AS attendance_status
     FROM event e
     INNER JOIN club c ON e.club_id = c.club_id
     INNER JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
     INNER JOIN student s ON er.matric_number = s.matric_number
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $where
     ORDER BY e.event_date DESC, e.event_title ASC, s.name ASC",
    $types,
    $params
);

$studentOverallRows = fetch_rows(
    $conn,
    "SELECT
        s.name,
        s.matric_number,
        s.course,
        COUNT(DISTINCT e.event_id) AS events_joined,
        COALESCE(SUM(a.point_awarded), 0) AS total_points
     FROM event e
     INNER JOIN club c ON e.club_id = c.club_id
     INNER JOIN eventregistration er ON e.event_id = er.event_id AND er.registration_status = 'registered'
     INNER JOIN student s ON er.matric_number = s.matric_number
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     $where
     GROUP BY s.matric_number, s.name, s.course
     ORDER BY total_points DESC, s.name ASC",
    $types,
    $params
);

$clubRows = fetch_rows(
    $conn,
    "SELECT
        c.club_name,
        COUNT(DISTINCT e.event_id) AS events_organized,
        COUNT(DISTINCT CASE WHEN er.registration_status = 'registered' THEN er.registration_id END) AS participants,
        COALESCE(SUM(CASE WHEN er.registration_status = 'registered' THEN a.point_awarded ELSE 0 END), 0) AS points
     FROM club c
     LEFT JOIN event e ON c.club_id = e.club_id
     LEFT JOIN eventregistration er ON e.event_id = er.event_id
     LEFT JOIN attendance a ON er.registration_id = a.registration_id
     " . ($where !== "" ? str_replace("WHERE", "WHERE", $where) : "") . "
     GROUP BY c.club_id, c.club_name
     ORDER BY events_organized DESC, participants DESC, points DESC, c.club_name ASC",
    $types,
    $params
);

$pdf = new SimplePdf();
$pdf->text(34, 17, "Participation and Points Dynamic Report", true);
$pdf->newLine(18);
$pdf->text(34, 9, "Generated: " . date("Y-m-d H:i:s"));
$pdf->newLine(12);
$filterSummary = "Filters: Club=" . ($filter["club_name"] ?: "All") . " | Month=" . ($filter["month_year"] ?: "All") . " | Event=" . ($filter["event_name"] ?: "All");
$pdf->text(34, 9, $filterSummary);
$pdf->newLine(18);

$pdf->section("1. Executive Summary");
$pdf->row(["Total Events", "Participants", "Attendance Rate", "Points Distributed"], [130, 130, 130, 130], true);
$pdf->row([
    number_format((int) ($summary["total_events"] ?? 0)),
    number_format((int) ($summary["total_participants"] ?? 0)),
    number_format((float) ($summary["attendance_rate"] ?? 0), 2) . "%",
    number_format((int) ($summary["total_points"] ?? 0))
], [130, 130, 130, 130]);

$pdf->section("2. Participants and Attendance Rate by Event");
$pdf->row(["Event", "Club", "Date", "Participants", "Attended", "Rate"], [210, 160, 80, 85, 75, 70], true);
foreach ($eventRows as $row) {
    $pdf->row([
        $row["event_title"],
        $row["club_name"],
        $row["event_date"],
        (int) $row["participant_count"],
        (int) $row["attended_count"],
        number_format((float) ($row["attendance_rate"] ?? 0), 2) . "%"
    ], [210, 160, 80, 85, 75, 70]);
}

$pdf->section("3. Points Accumulated by Student per Event");
$pdf->row(["Event", "Student", "Matric", "Status", "Points"], [240, 190, 85, 90, 60], true);
foreach ($studentEventRows as $row) {
    $pdf->row([
        $row["event_title"],
        $row["name"],
        $row["matric_number"],
        ucfirst((string) $row["attendance_status"]),
        (int) $row["points"]
    ], [240, 190, 85, 90, 60]);
}

$pdf->section("4. Overall Semester Points by Student");
$pdf->row(["Student", "Matric", "Course", "Events", "Total Points"], [190, 85, 250, 60, 80], true);
foreach ($studentOverallRows as $row) {
    $pdf->row([
        $row["name"],
        $row["matric_number"],
        $row["course"],
        (int) $row["events_joined"],
        (int) $row["total_points"]
    ], [190, 85, 250, 60, 80]);
}

$pdf->section("5. Most Active Clubs by Event Organization");
$pdf->row(["Club", "Events Organized", "Participants", "Points"], [260, 125, 110, 90], true);
foreach ($clubRows as $row) {
    $pdf->row([
        $row["club_name"],
        (int) $row["events_organized"],
        (int) $row["participants"],
        (int) $row["points"]
    ], [260, 125, 110, 90]);
}

$pdf->output("Participation_Points_Report_" . date("Ymd_His") . ".pdf");
