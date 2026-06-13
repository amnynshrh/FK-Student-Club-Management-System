<?php 
session_start();

include ('session.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css" />
  <link rel="stylesheet" href="style.css">
<script
  src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js">
</script>
</head>
<body>

    <?php include('adminHeader.php') ?>

    <div class="form-container">
        <h2>Create Student Club</h2>
        <hr>

        <!-- Form submits directly to the PHP file -->
        <form id="createClubForm" action="add_club_process.php" method="POST">
            
            <!-- Club Name -->
            <div class="form-group">
                <label for="clubName">Club Name <span class="required">*</span></label>
                <input type="text" id="clubName" name="clubName" placeholder="e.g., Computer Science Club" required>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="clubDescription">Description <span class="required">*</span></label>
                <textarea id="clubDescription" name="clubDescription" rows="4" placeholder="Describe the club's core focus and activities..." required></textarea>
            </div>

            <!-- Advisor Name -->
            <div class="form-group">
                <label for="advisorName">Advisor Name <span class="required">*</span></label>
                <input type="text" id="advisorName" name="advisorName" placeholder="e.g., Dr. Ahmad Bin Ali" required>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="window.history.back()">Cancel</button>
                <a href="admin_manage_club.php"><button type="submit" class="btn-submit">Create Club</button></a>
            </div>
        </form>
    </div>

    <script src="add.js"></script>
</body>
</html>
    </section>
</body>
</html>
