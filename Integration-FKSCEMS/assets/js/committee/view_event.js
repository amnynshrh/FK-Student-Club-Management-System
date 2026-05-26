document.addEventListener("DOMContentLoaded", function () {
  const params = new URLSearchParams(window.location.search);
  const eventId = params.get("id");

  if (!eventId) {
    showViewError("Event ID is missing.");
    return;
  }

  fetch("api/committee/event_detail.php?id=" + eventId)
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data.success) {
        showViewError(data.message);
        return;
      }

      renderEvent(data.event);
      renderParticipants(data.participants || []);
      renderWaitingList(data.waitingList || []);
    })
    .catch(function () {
      showViewError("Failed to load event details from database.");
    });
});

function renderEvent(event) {
  const title = document.querySelector(".view-event-name");
  const organizer = document.querySelector(".view-event-organizer");
  const values = document.querySelectorAll(".view-detail-value");
  const statusBadge = document.querySelector(".view-detail-status");

  if (title) title.textContent = event.title;
  if (organizer) organizer.innerHTML = `<i class="bi bi-person-badge"></i> Organizer: ${escapeHtml(event.clubName)}`;
  if (values[0]) values[0].textContent = formatDisplayDate(event.date);
  if (values[1]) values[1].textContent = event.registeredCount + "/" + event.participants + " Participants";
  if (values[2]) values[2].textContent = formatDisplayTime(event.startTime) + " - " + formatDisplayTime(event.endTime);
  if (values[3]) values[3].textContent = event.venue;
  if (values[4]) values[4].textContent = event.description;

  if (statusBadge) {
    statusBadge.textContent = event.status;
    statusBadge.className = "status-badge view-detail-status " + event.status.toLowerCase();
  }
}

function renderParticipants(participants) {
  const tableBody = document.getElementById("participantTableBody");
  const count = document.getElementById("participantListCount");

  if (count) {
    count.textContent = participants.length + " registered";
  }

  if (!tableBody) {
    return;
  }

  if (participants.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center py-4 text-muted">No registered participants yet.</td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = participants.map(function (participant, index) {
    return `
      <tr>
        <td>${index + 1}</td>
        <td>${escapeHtml(participant.name)}</td>
        <td>${escapeHtml(participant.matricNumber)}</td>
        <td>${escapeHtml(participant.course)}</td>
        <td>${escapeHtml(participant.email)}</td>
        <td><span class="status-badge ${participant.attendanceStatus.replace(" ", "-")}">${escapeHtml(capitalizeWords(participant.attendanceStatus))}</span></td>
      </tr>
    `;
  }).join("");
}

function renderWaitingList(waitingList) {
  const tableBody = document.getElementById("waitingTableBody");
  const count = document.getElementById("waitingListCount");

  if (count) {
    count.textContent = waitingList.length + " waiting";
  }

  if (!tableBody) {
    return;
  }

  if (waitingList.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center py-4 text-muted">No students in waiting list.</td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = waitingList.map(function (student, index) {
    return `
      <tr>
        <td>${index + 1}</td>
        <td>${escapeHtml(student.name)}</td>
        <td>${escapeHtml(student.matricNumber)}</td>
        <td><span class="status-badge waiting">${student.waitingStatus === "notified" ? "Slot Available" : "Waiting"}</span></td>
      </tr>
    `;
  }).join("");
}

function showViewError(message) {
  const card = document.querySelector(".view-event-card");

  if (!card) {
    alert(message);
    return;
  }

  const error = document.createElement("div");
  error.className = "alert alert-danger";
  error.textContent = message;
  card.prepend(error);
}

function formatDisplayDate(dateValue) {
  const date = new Date(dateValue);
  const day = date.getDate();
  const month = date.toLocaleString("en-US", { month: "long" });
  const year = date.getFullYear();
  return day + " " + month + " " + year;
}

function formatDisplayTime(timeValue) {
  if (!timeValue) return "";
  const timeParts = timeValue.split(":");
  let hour = parseInt(timeParts[0], 10);
  const minute = timeParts[1] || "00";
  if (Number.isNaN(hour)) return "";
  const ampm = hour >= 12 ? "PM" : "AM";
  hour = hour % 12;
  hour = hour ? hour : 12;
  return hour + ":" + minute + " " + ampm;
}

function capitalizeWords(value) {
  return String(value ?? "").replace(/\b\w/g, function (letter) {
    return letter.toUpperCase();
  });
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
