<?php
session_start();

include ('session.php');
require_once 'config/db.php';

$search_term = "";
$club_result = null;
$message = "";

if (isset($_GET['deleted'])) {
    $message = "<div class='alert-success'>Club deleted successfully.</div>";
} elseif (isset($_GET['error'])) {
    $message = "<div class='alert-error'>Unable to delete the selected club. Please try again.</div>";
}

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_term = trim($_GET['search']);
    $search_query = '%' . $search_term . '%';
    $club_stmt = $conn->prepare("SELECT * FROM club WHERE club_name LIKE ? OR advisor_name LIKE ? ORDER BY club_name ASC");
    $club_stmt->bind_param('ss', $search_query, $search_query);
    $club_stmt->execute();
    $club_result = $club_stmt->get_result();
} else {
    $club_result = $conn->query("SELECT * FROM club ORDER BY club_name ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clubs - Delete</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-container {
            display: flex;
            gap: 10px;
            margin: 20px 0 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 220px;
            padding: 10px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }

        .search-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-search {
            background-color: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-search:hover {
            background-color: #1d4ed8;
        }

        .status-badge.inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .empty-state {
            text-align: center;
            color: #64748b;
            padding: 24px 12px;
        }

        .alert-success,
        .alert-error {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

    <?php include('adminHeader.php') ?>

    <div class="form-container wide-container">
        <h2>Manage Student Clubs</h2>
        <hr>

        <?php echo $message; ?>

        <form method="GET" action="delete_club.php" class="search-container">
            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by club name or advisor..."
                value="<?php echo htmlspecialchars($search_term); ?>"
            >
            <button type="submit" class="btn-search">Search</button>
            <?php if ($search_term !== '') { ?>
                <a href="delete_club.php" class="btn-cancel" style="text-decoration:none; padding:10px 14px; display:inline-block;">Clear</a>
            <?php } ?>
        </form>

        <table class="club-table">
            <thead>
                <tr>
                    <th>Club Name</th>
                    <th>Advisor Name</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($club_result && $club_result->num_rows > 0) {
                    while ($club = $club_result->fetch_assoc()) {
                        $club_id = intval($club['club_id']);
                        $club_name = htmlspecialchars($club['club_name']);
                        $advisor_name = htmlspecialchars($club['advisor_name']);
                        $is_active = strtolower((string)$club['club_status']) === 'active';
                        $status_class = $is_active ? 'active' : 'inactive';
                        $status_label = htmlspecialchars(ucfirst((string)$club['club_status']));
                        $club_name_js = htmlspecialchars(json_encode($club['club_name']), ENT_QUOTES, 'UTF-8');
                ?>
                        <tr id="club-row-<?php echo $club_id; ?>">
                            <td><strong><?php echo $club_name; ?></strong></td>
                            <td><?php echo $advisor_name; ?></td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-delete"
                                    onclick="confirmDelete(<?php echo $club_id; ?>, <?php echo $club_name_js; ?>)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                <?php
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="4" class="empty-state">
                            <?php if ($search_term !== '') { ?>
                                No clubs match your search.
                            <?php } else { ?>
                                No clubs found in the database.
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">Confirm Deletion</div>
            <div class="modal-body">
                Are you sure you want to permanently delete <strong id="deleteClubName"></strong>? This action cannot be undone and will clear all associated committee records.
            </div>
            <div class="modal-footer">
                <form action="delete_club_process.php" method="POST">
                    <input type="hidden" id="deleteClubId" name="club_id" value="">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-danger-confirm">Yes, Delete Club</button>
                </form>
            </div>
        </div>
    </div>

    <script src="delete.js"></script>
</body>
</html>
