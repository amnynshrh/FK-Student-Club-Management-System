document.addEventListener("DOMContentLoaded", function () {
  loadAdminEventDashboard();
});

function loadAdminEventDashboard() {
  fetch("../api/admin/manage_events.php")
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data.success) {
        showDashboardError(data.message);
        return;
      }

      renderSummary(data.summary);
      renderStatusChart(data.statusCounts);
      renderRegistrationTrend(data.registrationTrend);
      renderEventsByClub(data.eventsByClub);
      renderParticipantsByEvent(data.participantsByEvent);
      renderPopularEvents(data.popularEvents);
      renderInsights(data.summary);
    })
    .catch(function () {
      showDashboardError("Failed to load admin event dashboard from database.");
    });
}

function renderSummary(summary) {
  setText("totalEventsNumber", summary.totalEvents);
  setText("totalRegistrationNumber", summary.totalRegistrations);
  setText("mostActiveClubTitle", summary.mostActiveClub.name);
  setText("mostActiveClubSmall", summary.mostActiveClub.totalEvents + " events organized");
  setText("mostPopularEventTitle", summary.mostPopularEvent.title);
  setText("mostPopularEventSmall", summary.mostPopularEvent.totalRegistrations + " registrations");
}

function renderStatusChart(statusCounts) {
  const total =
    statusCounts.upcoming +
    statusCounts.ongoing +
    statusCounts.completed +
    statusCounts.cancelled;
  const donut = document.getElementById("statusDonutChart");
  const monthLabel = document.getElementById("statusMonthLabel");
  const yearLabel = document.getElementById("statusYearLabel");
  const legend = document.getElementById("statusLegend");

  if (monthLabel) monthLabel.textContent = new Date().toLocaleString("en-US", { month: "long" });
  if (yearLabel) yearLabel.textContent = new Date().getFullYear();

  if (donut) {
    if (total === 0) {
      donut.style.background = "conic-gradient(#eeeff2 0deg 360deg)";
    } else {
      const upcomingEnd = (statusCounts.upcoming / total) * 360;
      const ongoingEnd = upcomingEnd + (statusCounts.ongoing / total) * 360;
      const completedEnd = ongoingEnd + (statusCounts.completed / total) * 360;
      donut.style.background =
        "conic-gradient(#009e96 0deg " + upcomingEnd + "deg, " +
        "#1c3f95 " + upcomingEnd + "deg " + ongoingEnd + "deg, " +
        "#334155 " + ongoingEnd + "deg " + completedEnd + "deg, " +
        "#ff4d4f " + completedEnd + "deg 360deg)";
    }
  }

  if (!legend) {
    return;
  }

  legend.innerHTML = ["upcoming", "ongoing", "completed", "cancelled"].map(function (status) {
    return `
      <div class="legend-item">
        <span class="legend-color ${status}"></span>
        <div>
          <strong>${statusCounts[status]}</strong>
          <p>${capitalize(status)}</p>
        </div>
      </div>
    `;
  }).join("");
}

function renderRegistrationTrend(trend) {
  const total = trend.reduce(function (sum, item) {
    return sum + item.total;
  }, 0);
  const previousTotal = trend.slice(0, -1).reduce(function (sum, item) {
    return sum + item.total;
  }, 0);
  const latestTotal = trend.length ? trend[trend.length - 1].total : 0;
  const growth = previousTotal > 0 ? Math.round(((latestTotal - previousTotal) / previousTotal) * 100) : 0;
  const plot = document.getElementById("trendLinePlot");
  const yAxis = document.getElementById("trendYAxis");
  const totalNumber = document.getElementById("trendTotalNumber");
  const growthBadge = document.getElementById("trendGrowthBadge");

  if (totalNumber) totalNumber.textContent = total;
  if (growthBadge) {
    growthBadge.innerHTML = `<i class="bi bi-graph-up-arrow"></i> ${growth >= 0 ? "+" : ""}${growth}%`;
  }

  if (!plot || !yAxis) {
    return;
  }

  const maxValue = Math.max(1, ...trend.map(function (item) {
    return item.total;
  }));
  const axisMax = Math.max(5, Math.ceil(maxValue / 5) * 5);
  const axisValues = [axisMax, Math.round(axisMax * 0.75), Math.round(axisMax * 0.5), Math.round(axisMax * 0.25), 0];

  yAxis.innerHTML = axisValues.map(function (value) {
    return `<span>${value}</span>`;
  }).join("");

  const points = trend.map(function (item, index) {
    const x = trend.length === 1 ? 50 : 8 + (index * (84 / (trend.length - 1)));
    const y = 85 - ((item.total / axisMax) * 70);
    return { x: x, y: y, label: item.label, total: item.total };
  });
  const polylinePoints = points.map(function (point) {
    return point.x + "," + point.y;
  }).join(" ");
  const areaPath = points.length
    ? "M " + points[0].x + " " + points[0].y + " L " + points.map(function (point) {
        return point.x + " " + point.y;
      }).join(" L ") + " L " + points[points.length - 1].x + " 100 L " + points[0].x + " 100 Z"
    : "";

  plot.innerHTML = `
    <svg class="trend-line-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="trendLineGradient" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stop-color="#009e96" />
          <stop offset="100%" stop-color="#1c3f95" />
        </linearGradient>
        <linearGradient id="trendAreaGradient" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#009e96" stop-opacity="0.18" />
          <stop offset="100%" stop-color="#009e96" stop-opacity="0" />
        </linearGradient>
      </defs>
      <path class="trend-area-fill" d="${areaPath}" />
      <polyline class="trend-line-path" points="${polylinePoints}" />
    </svg>
    ${points.map(function (point) {
      return `
        <div class="trend-point" style="left:${point.x}%; top:${point.y}%;">
          <span class="trend-value">${point.total}</span>
          <span class="trend-marker"></span>
          <span class="trend-week">${point.label}</span>
        </div>
      `;
    }).join("")}
  `;
}

function renderEventsByClub(clubs) {
  const container = document.getElementById("eventsByClubChart");

  if (!container) {
    return;
  }

  if (clubs.length === 0) {
    container.innerHTML = `<p class="text-muted mb-0">No club event data found.</p>`;
    return;
  }

  const maxValue = Math.max(1, ...clubs.map(function (club) {
    return club.totalEvents;
  }));

  container.innerHTML = clubs.map(function (club) {
    const width = Math.max(8, Math.round((club.totalEvents / maxValue) * 100));
    return `
      <div class="bar-row">
        <span>${escapeHtml(shortText(club.clubName, 14))}</span>
        <div class="bar-track">
          <div class="bar-fill" style="width: ${width}%;"></div>
        </div>
        <strong>${club.totalEvents}</strong>
      </div>
    `;
  }).join("");
}

function renderParticipantsByEvent(events) {
  const bars = document.getElementById("participantChartBars");
  const scale = document.getElementById("participantChartScale");
  const note = document.getElementById("participantChartNote");

  if (!bars || !scale || !note) {
    return;
  }

  if (events.length === 0) {
    bars.innerHTML = `<p class="text-muted mb-0">No participant data found.</p>`;
    note.innerHTML = `<i class="bi bi-info-circle"></i> No event registrations recorded yet.`;
    return;
  }

  const maxValue = Math.max(1, ...events.map(function (event) {
    return event.totalRegistrations;
  }));
  const axisMax = Math.max(5, Math.ceil(maxValue / 5) * 5);
  scale.innerHTML = [axisMax, Math.round(axisMax * 0.66), Math.round(axisMax * 0.33), 0].map(function (value) {
    return `<span>${value}</span>`;
  }).join("");

  bars.innerHTML = events.map(function (event) {
    const height = Math.max(12, Math.round((event.totalRegistrations / axisMax) * 100));
    return `
      <div class="participant-chart-item">
        <span class="participant-count">${event.totalRegistrations}</span>
        <div class="participant-bar" style="height: ${height}%;"></div>
        <span class="participant-event-name">${escapeHtml(shortText(event.title, 12))}</span>
      </div>
    `;
  }).join("");

  const topEvent = events[0];
  note.innerHTML = `<i class="bi bi-trophy"></i> ${escapeHtml(topEvent.title)} leads with ${topEvent.totalRegistrations} participants.`;
}

function renderPopularEvents(events) {
  const container = document.getElementById("popularEventList");

  if (!container) {
    return;
  }

  if (events.length === 0) {
    container.innerHTML = `<p class="text-muted mb-0">No popular event data found.</p>`;
    return;
  }

  container.innerHTML = events.map(function (event) {
    return `
      <div class="popular-event-item">
        <div>
          <h4>${escapeHtml(event.title)}</h4>
          <p>${escapeHtml(event.clubName)}</p>
        </div>
        <span>${event.totalRegistrations}</span>
      </div>
    `;
  }).join("");
}

function renderInsights(summary) {
  setText("insightPopularText", summary.mostPopularEvent.title + " has the highest registration count with " + summary.mostPopularEvent.totalRegistrations + " participants.");
  setText("insightClubText", summary.mostActiveClub.name + " organized the most events compared to other clubs.");
  setText("insightParticipantText", "There are " + summary.totalRegistrations + " total active registrations recorded across all events.");
}

function showDashboardError(message) {
  const header = document.querySelector(".dashboard-header");
  if (!header) {
    alert(message);
    return;
  }

  const errorBox = document.createElement("div");
  errorBox.className = "alert alert-danger";
  errorBox.textContent = message;
  header.after(errorBox);
}

function setText(id, value) {
  const element = document.getElementById(id);
  if (element) {
    element.textContent = value;
  }
}

function capitalize(value) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function shortText(value, maxLength) {
  const text = String(value ?? "");
  if (text.length <= maxLength) {
    return text;
  }
  return text.slice(0, maxLength - 1) + "...";
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
