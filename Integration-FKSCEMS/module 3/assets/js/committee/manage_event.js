let searchTitle = null;
let statusFilter = null;
let monthFilter = null;
let tableBody = null;
let paginationContainer = null;
let totalEvents = null;
let upcomingEvents = null;
let completedEvents = null;

const rowsPerPage = 10;

let currentPage = 1;
let allEvents = [];
let filteredEvents = [];

document.addEventListener("DOMContentLoaded", function () {
  searchTitle = document.getElementById("searchTitle");
  statusFilter = document.getElementById("statusFilter");
  monthFilter = document.getElementById("monthFilter");
  tableBody = document.getElementById("eventTableBody");
  paginationContainer = document.getElementById("paginationContainer");
  totalEvents = document.getElementById("totalEvents");
  upcomingEvents = document.getElementById("upcomingEvents");
  completedEvents = document.getElementById("completedEvents");

  if (
    !searchTitle ||
    !statusFilter ||
    !monthFilter ||
    !tableBody ||
    !paginationContainer ||
    !totalEvents ||
    !upcomingEvents ||
    !completedEvents
  ) {
    return;
  }

  searchTitle.addEventListener("keyup", filterEvents);
  statusFilter.addEventListener("change", filterEvents);
  monthFilter.addEventListener("change", filterEvents);

  loadEvents();
  showSuccessMessage();
});

function loadEvents() {
  fetch("../api/events/get_events.php")
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
      updateSummaryCards();
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

function filterEvents() {
  const searchValue = searchTitle.value.toLowerCase().trim();
  const selectedStatus = statusFilter.value;
  const selectedMonth = monthFilter.value;

  filteredEvents = allEvents.filter(function (event) {
    const title = event.title.toLowerCase();
    const displayDate = formatDisplayDate(event.date);

    const matchTitle = title.includes(searchValue);
    const matchStatus =
      selectedStatus === "all" || event.status === selectedStatus;
    const matchMonth =
      selectedMonth === "all" || displayDate.includes(selectedMonth);

    return matchTitle && matchStatus && matchMonth;
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

    row.innerHTML = `
      <td>${event.title}</td>
      <td>${formatDisplayDate(event.date)}</td>
      <td>${formatDisplayTime(event.startTime)} - ${formatDisplayTime(event.endTime)}</td>
      <td>${event.venue}</td>
      <td>${event.participants}</td>

      <td>
        <span class="status-badge ${event.status.toLowerCase()}">
          ${event.status}
        </span>
      </td>

      <td>
        <div class="action-buttons">

          <a href="view_event.html?id=${event.id}" class="action-btn view-btn text-decoration-none">
            <i class="bi bi-eye"></i>
          </a>

          <a href="edit_event.html?id=${event.id}" class="action-btn edit-btn text-decoration-none">
            <i class="bi bi-pencil-square"></i>
          </a>

          <button type="button" class="action-btn delete-btn" data-id="${event.id}">
            <i class="bi bi-trash"></i>
          </button>

        </div>
      </td>
    `;

    tableBody.appendChild(row);
  });

  setupDeleteButtons();
  setupPagination();
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

function setupDeleteButtons() {
  const deleteButtons = document.querySelectorAll(".delete-btn");

  deleteButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const eventId = button.getAttribute("data-id");

      const confirmDelete = confirm(
        "Are you sure you want to delete this event?",
      );

      if (!confirmDelete) {
        return;
      }

      const formData = new FormData();
      formData.append("eventId", eventId);

      fetch("../api/events/delete_event.php", {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data.success) {
            clearEventMessageFlags();
            sessionStorage.setItem("eventDeleted", "true");
            window.location.href = "manage_events.html";
          } else {
            alert(data.message);
          }
        })
        .catch(function () {
          alert("Something went wrong while deleting event.");
        });
    });
  });
}

function updateSummaryCards() {
  const total = allEvents.length;

  const upcoming = allEvents.filter(function (event) {
    return event.status === "Upcoming";
  }).length;

  const completed = allEvents.filter(function (event) {
    return event.status === "Completed";
  }).length;

  totalEvents.textContent = total;
  upcomingEvents.textContent = upcoming;
  completedEvents.textContent = completed;
}

function showSuccessMessage() {
  if (sessionStorage.getItem("eventDeleted") === "true") {
    displaySuccessMessage("Event deleted successfully.");
    sessionStorage.removeItem("eventDeleted");
    return;
  }

  if (sessionStorage.getItem("eventSaved") === "true") {
    displaySuccessMessage("Event saved successfully.");
    sessionStorage.removeItem("eventSaved");
    return;
  }

  if (sessionStorage.getItem("eventUpdated") === "true") {
    displaySuccessMessage("Event updated successfully.");
    sessionStorage.removeItem("eventUpdated");
    return;
  }
}

function displaySuccessMessage(message) {
  const successMessage = document.getElementById("successMessage");

  if (!successMessage) {
    return;
  }

  successMessage.textContent = message;
  successMessage.classList.remove("alert-danger");
  successMessage.classList.add("alert-success");
  successMessage.classList.remove("d-none");
  successMessage.style.display = "block";
  successMessage.scrollIntoView({ behavior: "smooth", block: "center" });
}

function clearEventMessageFlags() {
  sessionStorage.removeItem("eventSaved");
  sessionStorage.removeItem("eventUpdated");
  sessionStorage.removeItem("eventDeleted");
}

function formatDisplayDate(dateValue) {
  const date = new Date(dateValue);

  const day = date.getDate();
  const month = date.toLocaleString("en-US", {
    month: "long",
  });
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
