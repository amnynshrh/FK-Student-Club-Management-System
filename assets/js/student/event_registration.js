let searchTitle = null;
let clubFilter = null;
let statusFilter = null;
let monthFilter = null;
let tableBody = null;
let paginationContainer = null;
let availableEvents = null;
let registeredEvents = null;
let waitingEvents = null;

const rowsPerPage = 10;

let currentPage = 1;
let allEvents = [];
let filteredEvents = [];

document.addEventListener("DOMContentLoaded", function () {
  searchTitle = document.getElementById("searchTitle");
  clubFilter = document.getElementById("clubFilter");
  statusFilter = document.getElementById("statusFilter");
  monthFilter = document.getElementById("monthFilter");
  tableBody = document.getElementById("eventTableBody");
  paginationContainer = document.getElementById("paginationContainer");
  availableEvents = document.getElementById("availableEvents");
  registeredEvents = document.getElementById("registeredEvents");
  waitingEvents = document.getElementById("waitingEvents");

  if (
    !searchTitle ||
    !clubFilter ||
    !statusFilter ||
    !monthFilter ||
    !tableBody ||
    !paginationContainer ||
    !availableEvents ||
    !registeredEvents ||
    !waitingEvents
  ) {
    return;
  }

  searchTitle.addEventListener("keyup", filterEvents);
  clubFilter.addEventListener("change", filterEvents);
  statusFilter.addEventListener("change", filterEvents);
  monthFilter.addEventListener("change", filterEvents);

  loadEvents();
  showSuccessMessage();
});

function loadEvents() {
  fetch("../api/student/events/list_events.php")
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data.success) {
        tableBody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center py-4 text-danger">
              ${data.message}
            </td>
          </tr>
        `;
        return;
      }

      allEvents = data.events;
      updateSummaryCards(data.summary);
      showWaitingReminders(data.reminders || []);
      populateClubFilter();
      filterEvents();
    })
    .catch(function () {
      tableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-danger">
            Failed to load events from database.
          </td>
        </tr>
      `;
    });
}

function populateClubFilter() {
  const selectedValue = clubFilter.value;
  const clubs = [];

  allEvents.forEach(function (event) {
    if (event.clubName && !clubs.includes(event.clubName)) {
      clubs.push(event.clubName);
    }
  });

  clubFilter.innerHTML = `<option value="all">List of Club</option>`;

  clubs.sort().forEach(function (club) {
    const option = document.createElement("option");
    option.value = club;
    option.textContent = club;
    clubFilter.appendChild(option);
  });

  if (selectedValue) {
    clubFilter.value = selectedValue;
  }
}

function filterEvents() {
  const searchValue = searchTitle.value.toLowerCase().trim();
  const selectedClub = clubFilter.value;
  const selectedStatus = statusFilter.value;
  const selectedMonth = monthFilter.value;

  filteredEvents = allEvents.filter(function (event) {
    const title = event.title.toLowerCase();
    const displayDate = formatDisplayDate(event.date);
    const displayStatus = getListStatus(event);

    const matchTitle = title.includes(searchValue);
    const matchClub = selectedClub === "all" || event.clubName === selectedClub;
    const matchStatus = selectedStatus === "all" || displayStatus === selectedStatus || event.status === selectedStatus;
    const matchMonth = selectedMonth === "all" || displayDate.includes(selectedMonth);

    return matchTitle && matchClub && matchStatus && matchMonth;
  });

  currentPage = 1;
  displayPage();
}

function displayPage() {
  tableBody.innerHTML = "";

  if (filteredEvents.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-4 text-muted">
          No event found
        </td>
      </tr>
    `;

    paginationContainer.style.display = "none";
    return;
  }

  const startIndex = (currentPage - 1) * rowsPerPage;
  const endIndex = startIndex + rowsPerPage;
  const eventsToShow = filteredEvents.slice(startIndex, endIndex);

  eventsToShow.forEach(function (event) {
    const row = document.createElement("tr");
    const displayStatus = getListStatus(event);
    const statusClass = displayStatus.toLowerCase();

    row.innerHTML = `
      <td>${escapeHtml(event.title)}</td>
      <td>${formatDisplayDate(event.date)}</td>
      <td>${formatDisplayTime(event.startTime)} - ${formatDisplayTime(event.endTime)}</td>
      <td>${escapeHtml(event.venue)}</td>
      <td>${event.registeredCount}/${event.participants}</td>
      <td>
        <span class="status-badge ${statusClass}">
          ${displayStatus}
        </span>
      </td>
      <td>
        <a href="view_event.html?id=${event.id}" class="btn-view-event" title="View Details">
          <i class="bi bi-eye"></i>
        </a>
        ${renderActionButton(event)}
      </td>
    `;

    tableBody.appendChild(row);
  });

  setupCancelButtons();
  setupWaitingButtons();
  setupPagination();
}

function renderActionButton(event) {
  if (event.alreadyRegistered) {
    if (event.status === "Completed" || event.status === "Cancelled") {
      return `<span class="status-badge registered ms-2">Registered</span>`;
    }

    return `
      <span class="status-badge registered ms-2">Registered</span>
      <button type="button" class="btn btn-sm btn-outline-danger cancel-registration-btn" data-id="${event.registrationId}">
        Cancel
      </button>
    `;
  }

  if (event.status === "Completed" || event.status === "Cancelled") {
    return `<span class="text-muted fw-semibold ms-2">Closed</span>`;
  }

  if (event.isFull) {
    if (event.isWaiting) {
      return `<span class="status-badge waiting ms-2">${event.waitingStatus === "notified" ? "Slot Available" : "Waiting List"}</span>`;
    }

    return `
      <span class="status-badge full ms-2">Full</span>
      <button type="button" class="btn-waiting-list join-waiting-btn" data-id="${event.id}">
        Waiting List
      </button>
    `;
  }

  if (event.isWaiting) {
    return `
      <span class="status-badge waiting ms-2">Slot Available</span>
      <a href="event_register_form.html?id=${event.id}" class="btn-register-event">Register</a>
    `;
  }

  return `<a href="event_register_form.html?id=${event.id}" class="btn-register-event">Register</a>`;
}

function setupCancelButtons() {
  const buttons = document.querySelectorAll(".cancel-registration-btn");

  buttons.forEach(function (button) {
    button.addEventListener("click", function () {
      const registrationId = button.getAttribute("data-id");
      const confirmCancel = confirm("Are you sure you want to cancel this registration?");

      if (!confirmCancel) {
        return;
      }

      const formData = new FormData();
      formData.append("registrationId", registrationId);

      fetch("../api/student/events/cancel_registration.php", {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data.success) {
            sessionStorage.setItem("studentRegistrationCancelled", "true");
            window.location.href = "event_registration.html";
          } else {
            alert(data.message);
          }
        })
        .catch(function () {
          alert("Something went wrong while cancelling registration.");
        });
    });
  });
}

function setupWaitingButtons() {
  const buttons = document.querySelectorAll(".join-waiting-btn");

  buttons.forEach(function (button) {
    button.addEventListener("click", function () {
      const eventId = button.getAttribute("data-id");
      const confirmJoin = confirm("This event is full. Do you want to join the waiting list?");

      if (!confirmJoin) {
        return;
      }

      const formData = new FormData();
      formData.append("eventId", eventId);

      fetch("../api/student/events/join_waiting_list.php", {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data.success) {
            sessionStorage.setItem("studentWaitingJoined", "true");
            window.location.href = "event_registration.html";
          } else {
            alert(data.message);
          }
        })
        .catch(function () {
          alert("Something went wrong while joining waiting list.");
        });
    });
  });
}

function getListStatus(event) {
  if (event.status === "Completed" || event.status === "Cancelled") {
    return event.status;
  }

  if (event.isFull) {
    return "Full";
  }

  return "Open";
}

function setupPagination() {
  paginationContainer.innerHTML = "";

  const totalPages = Math.ceil(filteredEvents.length / rowsPerPage);

  if (filteredEvents.length <= rowsPerPage) {
    paginationContainer.style.display = "none";
    return;
  }

  paginationContainer.style.display = "flex";

  const prevButton = document.createElement("button");
  prevButton.className = "page-btn-custom";
  prevButton.innerHTML = "&laquo;";
  prevButton.disabled = currentPage === 1;
  prevButton.addEventListener("click", function () {
    if (currentPage > 1) {
      currentPage--;
      displayPage();
    }
  });
  paginationContainer.appendChild(prevButton);

  for (let i = 1; i <= totalPages; i++) {
    const pageButton = document.createElement("button");
    pageButton.className = "page-btn-custom";
    pageButton.textContent = i;

    if (i === currentPage) {
      pageButton.classList.add("active");
    }

    pageButton.addEventListener("click", function () {
      currentPage = i;
      displayPage();
    });

    paginationContainer.appendChild(pageButton);
  }

  const nextButton = document.createElement("button");
  nextButton.className = "page-btn-custom";
  nextButton.innerHTML = "&raquo;";
  nextButton.disabled = currentPage === totalPages;
  nextButton.addEventListener("click", function () {
    if (currentPage < totalPages) {
      currentPage++;
      displayPage();
    }
  });
  paginationContainer.appendChild(nextButton);
}

function updateSummaryCards(summary) {
  availableEvents.textContent = summary.available;
  registeredEvents.textContent = summary.registered;
  waitingEvents.textContent = summary.waiting;
}

function showSuccessMessage() {
  if (sessionStorage.getItem("studentRegistered") === "true") {
    displaySuccessMessage("Event registered successfully.");
    sessionStorage.removeItem("studentRegistered");
    return;
  }

  if (sessionStorage.getItem("studentRegistrationCancelled") === "true") {
    displaySuccessMessage("Registration cancelled successfully.");
    sessionStorage.removeItem("studentRegistrationCancelled");
    return;
  }

  if (sessionStorage.getItem("studentWaitingJoined") === "true") {
    displaySuccessMessage("Joined waiting list successfully.");
    sessionStorage.removeItem("studentWaitingJoined");
  }
}

function displaySuccessMessage(message) {
  const successMessage = document.getElementById("successMessage");

  if (!successMessage) {
    return;
  }

  successMessage.textContent = message;
  successMessage.classList.remove("d-none");
  successMessage.scrollIntoView({ behavior: "smooth", block: "center" });
}

function formatDisplayDate(dateValue) {
  const date = new Date(dateValue);
  const day = date.getDate();
  const month = date.toLocaleString("en-US", { month: "long" });
  const year = date.getFullYear();
  return day + " " + month + " " + year;
}

function formatDisplayTime(timeValue) {
  if (!timeValue) {
    return "";
  }

  const timeParts = timeValue.split(":");
  let hour = parseInt(timeParts[0], 10);
  const minute = timeParts[1] || "00";

  if (Number.isNaN(hour)) {
    return "";
  }

  const ampm = hour >= 12 ? "PM" : "AM";
  hour = hour % 12;
  hour = hour ? hour : 12;
  return hour + ":" + minute + " " + ampm;
}

function showWaitingReminders(reminders) {
  const successMessage = document.getElementById("successMessage");

  if (!successMessage || reminders.length === 0) {
    return;
  }

  successMessage.textContent = reminders[0].message;
  successMessage.classList.remove("d-none");
  successMessage.scrollIntoView({ behavior: "smooth", block: "center" });
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
