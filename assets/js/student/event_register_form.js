document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const eventId = urlParams.get("id");
  const confirmButton = document.querySelector(".btn-register-confirm");
  const agreeCheckbox = document.getElementById("agree-condition");

  if (!eventId) {
    showFormMessage("Event ID is missing.");
    return;
  }

  loadEvent(eventId);

  if (confirmButton) {
    confirmButton.addEventListener("click", function () {
      if (!agreeCheckbox || !agreeCheckbox.checked) {
        showFormMessage("Please tick the confirmation checkbox first.");
        return;
      }

      const confirmRegister = confirm("Are you sure you want to register for this event?");

      if (!confirmRegister) {
        return;
      }

      const formData = new FormData();
      formData.append("eventId", eventId);

      fetch("../api/student/events/register_event.php", {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data.success) {
            sessionStorage.setItem("studentRegistered", "true");
            window.location.href = "event_registration.html";
          } else {
            showFormMessage(data.message);
          }
        })
        .catch(function () {
          showFormMessage("Something went wrong while registering event.");
        });
    });
  }
});

function loadEvent(eventId) {
  fetch("../api/student/events/get_event.php?id=" + eventId)
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data.success) {
        showFormMessage(data.message);
        return;
      }

      const event = data.event;

      document.querySelector(".register-event-title").textContent = event.title;
      document.querySelector(".register-event-organizer").innerHTML =
        `<i class="bi bi-people-fill"></i> Organized by ${escapeHtml(event.clubName)}`;

      const values = document.querySelectorAll(".register-detail-value");

      if (values[0]) values[0].textContent = formatDisplayDate(event.date);
      if (values[1]) values[1].textContent = `${event.registeredCount}/${event.participants} Participants`;
      if (values[2]) values[2].textContent = `${formatDisplayTime(event.startTime)} - ${formatDisplayTime(event.endTime)}`;
      if (values[3]) values[3].textContent = event.description;
      if (values[4]) values[4].textContent = event.venue;

      const confirmButton = document.querySelector(".btn-register-confirm");

      if (event.alreadyRegistered) {
        confirmButton.disabled = true;
        confirmButton.textContent = "Already Registered";
      }

      if (event.status === "Completed" || event.status === "Cancelled" || event.isFull) {
        confirmButton.disabled = true;
        confirmButton.textContent = "Registration Closed";
      }
    })
    .catch(function () {
      showFormMessage("Failed to load event details.");
    });
}

function showFormMessage(message) {
  let formMessage = document.getElementById("formMessage");

  if (!formMessage) {
    formMessage = document.createElement("div");
    formMessage.id = "formMessage";
    formMessage.className = "alert alert-danger mt-3";

    const card = document.querySelector(".register-event-card");
    if (card) {
      card.prepend(formMessage);
    }
  }

  formMessage.textContent = message;
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
