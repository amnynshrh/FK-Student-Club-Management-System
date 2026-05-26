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
        <p class="subtitle">Module 2: Manage Student Clubs</p>
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

            <!-- Committee Members Section -->
            <div class="committee-section">
                <h3>Committee Members</h3>
                <p class="section-desc">Assign initial core positions for the club committee.</p>
                
                <div id="committeeContainer">
                    <!-- Default Core Roles: Uses array syntax [] for PHP handling -->
                    <div class="committee-row">
                        <input type="text" name="member_names[]" placeholder="President Name"  >
                        <input type="text" name="member_matricnum[]" placeholder="Matric Number"  >
                        <input type="text" name="member_roles[]" value="President" readonly>
                    </div>
                    <div class="committee-row">
                        <input type="text" name="member_names[]" placeholder="Vice President Name"  >
                        <input type="text" name="member_matricnum[]" placeholder="Matric Number"  >
                        <input type="text" name="member_roles[]" value="Vice President" readonly>
                    </div>
                    <div class="committee-row">
                        <input type="text" name="member_names[]" placeholder="Secretary Name"  >
                        <input type="text" name="member_matricnum[]" placeholder="Matric Number"  >

                        <input type="text" name="member_roles[]" value="Secretary" readonly>
                    </div>
                    <div class="committee-row">
                        <input type="text" name="member_names[]" placeholder="Treasurer Name"  >
                        <input type="text" name="member_matricnum[]" placeholder="Matric Number"  >
                        <input type="text" name="member_roles[]" value="Treasurer" readonly>
                    </div>
                </div>

                <button type="button" id="addMemberBtn" class="btn-secondary">+ Add Custom Committee Role</button>
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
