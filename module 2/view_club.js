function openClubDetails(button) {
    // 1. Pull values from HTML element data tags securely
    const clubName = button.getAttribute('data-clubname');
    const description = button.getAttribute('data-desc');
    const advisorName = button.getAttribute('data-advisor');
    const committeesJson = button.getAttribute('data-committees');
    const clubId = button.getAttribute('data-clubid');

    // 2. Assign standard modal text UI indicators
    document.getElementById('modalClubName').innerText = clubName;
    document.getElementById('modalClubAdvisor').innerText = "Advisor: " + advisorName;
    document.getElementById('modalClubDesc').innerText = description;

    // 3. Reset the contents of the target table rows element block
    const tableBody = document.getElementById('modalCommitteeTable');
    tableBody.innerHTML = '';

    try {
        // 4. Parse JSON string tokens safely back to object schemas
        const committees = JSON.parse(committeesJson);

        if (committees && committees.length > 0) {
            committees.forEach(member => {
                const row = document.createElement('tr');
                
                const positionCell = document.createElement('td');
                positionCell.innerText = member.position; 
                
                const nameCell = document.createElement('td');
                nameCell.innerText = member.name; 

                row.appendChild(positionCell);
                row.appendChild(nameCell);
                tableBody.appendChild(row);
            });
        } else {
            tableBody.innerHTML = '<tr><td colspan="2" style="text-align:center;">No committee members assigned to this club.</td></tr>';
        }
    } catch (e) {
        console.error("JSON processing error: ", e);
        tableBody.innerHTML = '<tr><td colspan="2" style="text-align:center; color:red;">Error loading committee data.</td></tr>';
    }

    // 5. Open Modal
    document.getElementById('detailsModal').style.display = 'flex'; 
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Function to close the modal window
function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}