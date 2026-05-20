document.addEventListener('DOMContentLoaded', () => {
    const committeeContainer = document.getElementById('committeeContainer');
    const addMemberBtn = document.getElementById('addMemberBtn');

    // Dynamically append new rows for custom roles
    addMemberBtn.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'committee-row';

        // Keep the exact same 'name' attributes with [] so PHP parses them together
        row.innerHTML = `
            <input type="text" name="member_names[]" placeholder="Member Name" required>
            <input type="text" name="member_roles[]" placeholder="e.g., Media Officer" required>
            <button type="button" class="btn-remove" title="Remove Member">&times;</button>
        `;

        // Add removal functionality to the button
        row.querySelector('.btn-remove').addEventListener('click', () => {
            row.remove();
        });

        committeeContainer.appendChild(row);
    });
});