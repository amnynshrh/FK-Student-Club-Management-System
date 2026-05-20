// Functions for Handling Deletion Modals
function confirmDelete(clubId, clubName) {
    // Inject contextual parameters into the modal fields
    document.getElementById('deleteClubId').value = clubId;
    document.getElementById('deleteClubName').innerText = clubName;

    // Unhide the UI overlay display container
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal() {
    // Re-hide the UI overlay
    document.getElementById('deleteModal').style.display = 'none';
}

// Optional window close trigger when clicking outside modal contents boundaries
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}