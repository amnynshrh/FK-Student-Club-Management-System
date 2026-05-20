document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const eventId = urlParams.get("id");

  if (!eventId) {
    showViewMessage("Event ID is missing.");
    return;
  }

  fetch("../api/student/events/get_event.php?id=" + eventId)
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data.success) {
        showViewMessage(data.message);
        return;
      }

      renderEvent(data.event);
    })
    .catch(function () {
      showViewMessage("Failed to load event details.");
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
    const displayStatus = getDisplayStatus(event);
    statusBadge.textContent = displayStatus;
    statusBadge.className = "status-badge view-detail-status " + displayStatus.toLowerCase();
  }
}

function getDisplayStatus(event) {
  if (event.status === "Completed" || event.status === "Cancelled") {
    return event.status;
  }

  if (event.isFull) {
    return "Full";
  }

  if (event.alreadyRegistered) {
    return "Registered";
  }

  return "Open";
}

function showViewMessage(message) {
  const card = document.querySelector(".view-event-card");

  if (!card) {
    alert(message);
    return;
  }

  const messageBox = document.createElement("div");
  messageBox.className = "alert alert-danger";
  messageBox.textContent = message;
  card.prepend(messageBox);
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

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
