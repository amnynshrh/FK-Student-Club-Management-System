<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fk_scems_db"; 

$conn = new mysqli($servername, $username, $password, $dbname);

$events = [];
$sql = "
    SELECT
        event_id,
        event_title,
        event_date,
        event_time,
        end_time,
        venue,
        event_status
    FROM event
    ORDER BY event_date DESC, event_time DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Committee</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom Theme UMPSA -->
    <link rel="stylesheet" href="assets/css/committee.css">

    <style>
        body {
            background-color: #f4f7f6;
        }

        .student-content {
            max-width: 1200px;
            margin: 30px auto;
        }

        .table> :not(caption)>*>* {
            border-bottom-color: #eeeff2;
        }

        .btn-umpsa-teal {
            background-color: #009e96;
            color: white;
            transition: 0.2s;
        }

        .btn-umpsa-teal:hover {
            background-color: #1c3f95;
            color: white;
        }

        .nav-right .nav-link.active-link {
            color: #1c3f95;
            font-weight: 700;
        }

        .attendance-table-scroll {
            max-height: 960px;
            overflow-y: auto;
        }

        .attendance-table-scroll thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }

        .attendance-search-bar .form-control,
        .attendance-search-bar .form-select,
        .attendance-search-bar .btn {
            min-height: 42px;
        }

        .btn-clear-filter {
            background-color: transparent;
            border: 1px solid #dbdae3;
            color: #39364f;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-clear-filter:hover {
            background-color: #f8f8fa;
            border-color: #6f7287;
            color: #39364f;
        }

        .event-status .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
            white-space: nowrap;
        }

        .event-status .status-badge.ongoing {
            background-color: #009e96;
        }

        .event-status .status-badge.upcoming {
            background-color: #6f7287;
        }

        .event-status .status-badge.completed {
            background-color: #1c3f95;
        }

        .event-status .status-badge.cancelled {
            background-color: #ff4d4f;
        }
    </style>
</head>

<body>

    <?php include('committeeHeader.php') ?>

    <main class="student-content">
        <h1 class="page-title">Manage Attendance</h1>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="row g-3 align-items-end attendance-search-bar mb-3">
                    <div class="col-12 col-md-3">
                        <label for="filterTitle" class="form-label mb-1">Event Title</label>
                        <input type="text" id="filterTitle" class="form-control" placeholder="Search title">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="filterDate" class="form-label mb-1">Date</label>
                        <input type="text" id="filterDate" class="form-control" placeholder="dd-mm-yyyy" maxlength="10" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="filterVenue" class="form-label mb-1">Venue</label>
                        <input type="text" id="filterVenue" class="form-control" placeholder="Search venue">
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="filterStatus" class="form-label mb-1">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1 d-none d-md-block">&nbsp;</label>
                        <button type="button" id="clearFilterBtn" class="btn btn-clear-filter w-1000">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="table-responsive attendance-table-scroll">
                    <table class="table align-middle mb-0" id="attendanceTable">
                        <thead>
                            <tr>
                                <th scope="col">No.</th>
                                <th scope="col">Event Title</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Venue</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($events)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No events found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $index => $event): ?>
                                    <?php
                                    $eventDateRaw = (string)($event['event_date'] ?? '');
                                    $eventDateFormatted = $eventDateRaw;
                                    if ($eventDateRaw !== '') {
                                        $eventDateTimestamp = strtotime($eventDateRaw);
                                        if ($eventDateTimestamp !== false) {
                                            $eventDateFormatted = date('d-m-Y', $eventDateTimestamp);
                                        }
                                    }
                                    $eventStatusLower = strtolower(trim((string)($event['event_status'] ?? '')));
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></td>
                                        <td class="event-date" data-filter-date="<?php echo htmlspecialchars($eventDateFormatted); ?>"><?php echo htmlspecialchars($eventDateFormatted); ?></td>
                                        <td>
                                            <?php
                                            $startRaw = $event['event_time'] ?? '';
                                            $endRaw = $event['end_time'] ?? '';

                                            $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
                                            $endTs = $endRaw !== '' ? strtotime($endRaw) : false;

                                            $startFmt = $startTs !== false ? date('g:i A', $startTs) : $startRaw;
                                            $endFmt = $endTs !== false ? date('g:i A', $endTs) : $endRaw;

                                            echo htmlspecialchars(trim($startFmt));
                                            if (trim((string)$endFmt) !== '') {
                                                echo " - " . htmlspecialchars(trim($endFmt));
                                            }
                                            ?>
                                        </td>
                                        <td class="event-venue"><?php echo htmlspecialchars($event['venue']); ?></td>
                                        <td class="event-status" data-filter-status="<?php echo htmlspecialchars($eventStatusLower); ?>">
                                            <span class="status-badge <?php echo htmlspecialchars($eventStatusLower); ?>">
                                                <?php echo htmlspecialchars(ucfirst($event['event_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a
                                                href="event_attendance.php?event_id=<?php echo urlencode($event['event_id']); ?>"
                                                class="btn btn-sm btn-umpsa-teal"
                                            >
                                                View Attendance
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const filterTitle = document.getElementById('filterTitle');
            const filterDate = document.getElementById('filterDate');
            const filterVenue = document.getElementById('filterVenue');
            const filterStatus = document.getElementById('filterStatus');
            const clearFilterBtn = document.getElementById('clearFilterBtn');
            const tableBody = document.querySelector('#attendanceTable tbody');

            if (!tableBody) {
                return;
            }

            const rows = Array.from(tableBody.querySelectorAll('tr'));
            const noResultRow = document.createElement('tr');
            noResultRow.id = 'noFilterResultRow';
            noResultRow.innerHTML = '<td colspan="7" class="text-center py-4 text-muted">No matching events found.</td>';

            const applyFilters = function() {
                const titleValue = (filterTitle.value || '').trim().toLowerCase();
                const dateValue = (filterDate.value || '').trim();
                const venueValue = (filterVenue.value || '').trim().toLowerCase();
                const statusValue = (filterStatus.value || '').trim().toLowerCase();
                let visibleCount = 0;

                rows.forEach(function(row) {
                    const titleText = (row.querySelector('.event-title')?.textContent || '').trim().toLowerCase();
                    const dateText = (row.querySelector('.event-date')?.dataset.filterDate || '').trim();
                    const venueText = (row.querySelector('.event-venue')?.textContent || '').trim().toLowerCase();
                    const statusText = (row.querySelector('.event-status')?.dataset.filterStatus || '').trim().toLowerCase();

                    const matchTitle = titleText.includes(titleValue);
                    const matchDate = dateText.includes(dateValue);
                    const matchVenue = venueText.includes(venueValue);
                    const matchStatus = statusValue === '' || statusText === statusValue;
                    const shouldShow = matchTitle && matchDate && matchVenue && matchStatus;

                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) {
                        visibleCount += 1;
                    }
                });

                if (visibleCount === 0 && rows.length > 0) {
                    if (!document.getElementById('noFilterResultRow')) {
                        tableBody.appendChild(noResultRow);
                    }
                } else {
                    const existingNoResultRow = document.getElementById('noFilterResultRow');
                    if (existingNoResultRow) {
                        existingNoResultRow.remove();
                    }
                }
            };

            const formatDateInput = function(value) {
                const digitsOnly = (value || '').replace(/\D/g, '').slice(0, 8);
                if (digitsOnly.length <= 2) {
                    return digitsOnly;
                }
                if (digitsOnly.length <= 4) {
                    return digitsOnly.slice(0, 2) + '-' + digitsOnly.slice(2);
                }
                return digitsOnly.slice(0, 2) + '-' + digitsOnly.slice(2, 4) + '-' + digitsOnly.slice(4);
            };

            [filterTitle, filterVenue].forEach(function(input) {
                input.addEventListener('input', applyFilters);
                input.addEventListener('change', applyFilters);
            });
            filterDate.addEventListener('input', function() {
                filterDate.value = formatDateInput(filterDate.value);
                applyFilters();
            });
            filterDate.addEventListener('change', applyFilters);
            filterStatus.addEventListener('change', applyFilters);
            clearFilterBtn.addEventListener('click', function() {
                filterTitle.value = '';
                filterDate.value = '';
                filterVenue.value = '';
                filterStatus.value = '';
                applyFilters();
            });
        })();
    </script>
</body>

</html>
