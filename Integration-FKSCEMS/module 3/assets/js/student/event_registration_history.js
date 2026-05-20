let searchTitle = null;
let statusFilter = null;
let yearFilter = null;
let monthFilter = null;
let tableBody = null;
let paginationContainer = null;

const rowsPerPage = 10;

let currentPage = 1;
let allHistory = [];
let filteredHistory = [];

document.addEventListener("DOMContentLoaded", function () {
  searchTitle = document.getElementById("searchTitle");
  statusFilter = document.getElementById("statusFilter");
  yearFilter = document.getElementById("yearFilter");
  monthFilter = document.getElementById("monthFilter");
  tableBody = document.getElementById("historyTableBody");
  paginationContainer = document.getElementById("paginationContainer");

  if (!searchTitle || !statusFilter || !yearFilter || !monthFilter || !tableBody || !paginationContainer) {
    return;
  }

  searchTitle.addEventListener("keyup", filterHistory);
  statusFilter.addEventListener("change", filterHistory);
  yearFilter.addEventListener("change", filterHistory);
  monthFilter.addEventListener("change", filterHistory);

  loadHistory();
  showSuccessMessage();
});

function loadHistory() {
  fetch("../api/student/events/history.php")
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

      allHistory = data.history;
      updateSummaryCards(data.summary);
      populateYearFilter();
      filterHistory();
    })
    .catch(function () {
      tableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-danger">
            Failed to load registration history from database.
          </td>
        </tr>
      `;
    });
}

function populateYearFilter() {
  const selectedValue = yearFilter.value;
  const years = [];

  allHistory.forEach(function (item) {
    const year = new Date(item.date).getFullYear().toString();
    if (!years.includes(year)) {
      years.push(year);
    }
  });

  yearFilter.innerHTML = `<option value="all">List of Year</option>`;

  years.sort().reverse().forEach(function (year) {
    const option = document.createElement("option");
    option.value = year;
    option.textContent = year;
    yearFilter.appendChild(option);
  });

  if (selectedValue) {
    yearFilter.value = selectedValue;
  }
}

function filterHistory() {
  const searchValue = searchTitle.value.toLowerCase().trim();
  const selectedStatus = statusFilter.value;
  const selectedYear = yearFilter.value;
  const selectedMonth = monthFilter.value;

  filteredHistory = allHistory.filter(function (item) {
    const title = item.title.toLowerCase();
    const displayDate = formatDisplayDate(item.date);
    const displayStatus = getHistoryStatus(item);
    const year = new Date(item.date).getFullYear().toString();

    const matchTitle = title.includes(searchValue);
    const matchStatus = selectedStatus === "all" || displayStatus === selectedStatus;
    const matchYear = selectedYear === "all" || year === selectedYear;
    const matchMonth = selectedMonth === "all" || displayDate.includes(selectedMonth);

    return matchTitle && matchStatus && matchYear && matchMonth;
  });

  currentPage = 1;
  displayPage();
}

function displayPage() {
  tableBody.innerHTML = "";

  if (filteredHistory.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-4 text-muted">
          No registration history found
        </td>
      </tr>
    `;
    paginationContainer.style.display = "none";
    return;
  }

  const startIndex = (currentPage - 1) * rowsPerPage;
  const endIndex = startIndex + rowsPerPage;
  const itemsToShow = filteredHistory.slice(startIndex, endIndex);

  itemsToShow.forEach(function (item) {
    const row = document.createElement("tr");
    const displayStatus = getHistoryStatus(item);

    row.innerHTML = `
      <td>${escapeHtml(item.title)}</td>
      <td>${formatDisplayDate(item.date)}</td>
      <td>${formatDisplayTime(item.startTime)} - ${formatDisplayTime(item.endTime)}</td>
      <td>${escapeHtml(item.venue)}</td>
      <td>
        <span class="status-badge ${statusClass(displayStatus)}">
          ${displayStatus}
        </span>
      </td>
      <td>${item.points}</td>
      <td>
        <a href="view_event.html?id=${item.eventId}" class="btn-view-event" title="View Details">
          <i class="bi bi-eye"></i>
        </a>
        ${renderCancelButton(item)}
      </td>
    `;

    tableBody.appendChild(row);
  });

  setupCancelButtons();
  setupPagination();
}

function renderCancelButton(item) {
  if (item.registrationStatus !== "registered") {
    return "";
  }

  if (item.eventStatus === "Completed" || item.eventStatus === "Cancelled") {
    return "";
  }

  return `<button type="button" class="btn btn-sm btn-outline-danger ms-2 cancel-registration-btn" data-id="${item.registrationId}">Cancel</button>`;
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

function getHistoryStatus(item) {
  if (item.registrationStatus === "cancelled" || item.eventStatus === "Cancelled") {
    return "Cancelled";
  }

  if (item.registrationStatus === "waiting") {
    return "Waiting List";
  }

  if (item.registrationStatus === "notified") {
    return "Slot Available";
  }

  if (item.attendanceStatus === "present") {
    return "Attended";
  }

  if (item.attendanceStatus === "late") {
    return "Late";
  }

  return item.eventStatus;
}

function updateSummaryCards(summary) {
  const numbers = document.querySelectorAll(".summary-number");
  if (numbers[0]) numbers[0].textContent = summary.totalRegistered;
  if (numbers[1]) numbers[1].textContent = summary.attended;
  if (numbers[2]) numbers[2].textContent = summary.upcoming;
  if (numbers[3]) numbers[3].textContent = summary.cancelled;
}

function showSuccessMessage() {
  if (sessionStorage.getItem("studentRegistrationCancelled") === "true") {
    displaySuccessMessage("Registration cancelled successfully.");
    sessionStorage.removeItem("studentRegistrationCancelled");
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

function setupPagination() {
  paginationContainer.innerHTML = "";
  const totalPages = Math.ceil(filteredHistory.length / rowsPerPage);

  if (filteredHistory.length <= rowsPerPage) {
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

function statusClass(status) {
  return String(status ?? "").toLowerCase().replace(/\s+/g, "-");
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
