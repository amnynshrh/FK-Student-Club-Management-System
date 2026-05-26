document.addEventListener("DOMContentLoaded", function () {
  const addEventForm = document.getElementById("addEventForm");

  if (!addEventForm) {
    return;
  }

  addEventForm.addEventListener("submit", function (event) {
    event.preventDefault();

    clearErrors();

    const title = document.getElementById("eventTitle").value.trim();
    const description = document
      .getElementById("eventDescription")
      .value.trim();
    const date = document.getElementById("eventDate").value;
    const startTime = document.getElementById("eventStartTime").value;
    const endTime = document.getElementById("eventEndTime").value;
    const venue = document.getElementById("eventVenue").value.trim();
    const participants = document
      .getElementById("eventParticipants")
      .value.trim();

    let isValid = true;

    if (title === "") {
      showError("eventTitle", "titleError", "Please enter event title.");
      isValid = false;
    }

    if (description === "") {
      showError(
        "eventDescription",
        "descriptionError",
        "Please enter event description.",
      );
      isValid = false;
    }

    if (date === "") {
      showError("eventDate", "dateError", "Please select event date.");
      isValid = false;
    }

    if (startTime === "") {
      showError(
        "eventStartTime",
        "startTimeError",
        "Please select start time.",
      );
      isValid = false;
    }

    if (endTime === "") {
      showError("eventEndTime", "endTimeError", "Please select end time.");
      isValid = false;
    }

    if (startTime !== "" && endTime !== "" && endTime <= startTime) {
      showError(
        "eventEndTime",
        "endTimeError",
        "End time must be after start time.",
      );
      isValid = false;
    }

    if (venue === "") {
      showError("eventVenue", "venueError", "Please enter venue.");
      isValid = false;
    }

    if (participants === "") {
      showError(
        "eventParticipants",
        "participantsError",
        "Please enter maximum participants.",
      );
      isValid = false;
    } else if (Number(participants) <= 0) {
      showError(
        "eventParticipants",
        "participantsError",
        "Maximum participants must be more than 0.",
      );
      isValid = false;
    }

    if (!isValid) {
      return;
    }

    const formData = new FormData();

    formData.append("title", title);
    formData.append("description", description);
    formData.append("date", date);
    formData.append("startTime", startTime);
    formData.append("endTime", endTime);
    formData.append("venue", venue);
    formData.append("participants", participants);

    /*
      Untuk testing sahaja.
      Pastikan club_id = 1 dan committee_id = 1 wujud dalam database.
    */
    formData.append("clubId", 1);
    formData.append("committeeId", 1);

    fetch("../api/events/save_event.php", {
      method: "POST",
      body: formData,
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.success) {
          sessionStorage.setItem("eventSaved", "true");
          window.location.href = "manage_events.html";
        } else {
          showFormMessage(data.message);
        }
      })
      .catch(function () {
        showFormMessage("Something went wrong while saving event.");
      });
  });
});

function showError(inputId, errorId, message) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);

  if (input && error) {
    input.classList.add("input-error");
    error.textContent = message;
  }
}

function clearErrors() {
  const errorMessages = document.querySelectorAll(".error-message");
  const inputs = document.querySelectorAll("input, textarea, select");

  errorMessages.forEach(function (error) {
    error.textContent = "";
  });

  inputs.forEach(function (input) {
    input.classList.remove("input-error");
  });

  const oldMessage = document.getElementById("formMessage");

  if (oldMessage) {
    oldMessage.remove();
  }
}

function showFormMessage(message) {
  let formMessage = document.getElementById("formMessage");

  if (!formMessage) {
    formMessage = document.createElement("div");
    formMessage.id = "formMessage";
    formMessage.className = "alert alert-danger mt-3";

    const form = document.getElementById("addEventForm");
    form.prepend(formMessage);
  }

  formMessage.textContent = message;
}
