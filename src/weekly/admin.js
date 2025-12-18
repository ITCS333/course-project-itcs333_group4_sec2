let weeks = [];

const weekForm = document.querySelector("#week-form");
const weeksTableBody = document.querySelector("#weeks-tbody");
const addWeekBtn = document.querySelector("#add-week"); 

function createWeekRow(week) {
  const tr = document.createElement("tr");

  const tdTitle = document.createElement("td");
  tdTitle.textContent = week.title;
  tr.appendChild(tdTitle);

  const tdDescription = document.createElement("td");
  tdDescription.textContent = week.description;
  tr.appendChild(tdDescription);

  const tdActions = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.setAttribute("data-id", week.id);
  tdActions.appendChild(editBtn);

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-btn";
  deleteBtn.setAttribute("data-id", week.id);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdActions);
  return tr;
}

function renderTable() {
  weeksTableBody.innerHTML = "";
  weeks.forEach((week) => {
    weeksTableBody.appendChild(createWeekRow(week));
  });
}

async function handleAddWeek(event) {
  event.preventDefault();

  const title = document.querySelector("#week-title").value.trim();
  const startDate = document.querySelector("#week-start-date").value;
  const description = document.querySelector("#week-description").value.trim();
  const links = document
    .querySelector("#week-links")
    .value.split("\n")
    .map((l) => l.trim())
    .filter((l) => l !== "");

  const editId = weekForm.dataset.editId;
  const payload = { title, startDate, description, links };

  try {
    let response, result;
    if (editId) {
      // For PUT, include id in payload (matches PHP expectation)
      payload.id = editId;
      response = await fetch(`/api.php?resource=weeks`, {  // Remove week_id from URL
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      result = await response.json();
      if (result.success) {
        const index = weeks.findIndex((w) => w.id === editId);
        weeks[index] = result.data;
      }
      delete weekForm.dataset.editId;
      addWeekBtn.textContent = "Add Week";
    } else {
      // For POST, generate and include id (matches PHP requirement)
      payload.id = `week_${Date.now()}`;
      response = await fetch(`/api.php?resource=weeks`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      result = await response.json();
      if (result.success) {
        weeks.push(result.data);
      }
    }

    if (response.ok) {
      renderTable();
      weekForm.reset();
    } else {
      console.error("API Error:", result.error || response.statusText);
    }
  } catch (error) {
    console.error("Fetch Error:", error);
  }
}

async function handleTableClick(event) {
  const target = event.target;
  const id = target.getAttribute("data-id");

  if (target.classList.contains("delete-btn")) {
    try {
      const response = await fetch(`/api.php?resource=weeks&week_id=${id}`, {
        method: "DELETE",
      });
      const result = await response.json();
      if (result.success) {
        weeks = weeks.filter((w) => w.id !== id);
        renderTable();
      } else {
        console.error("Delete API Error:", result.error);
      }
    } catch (error) {
      console.error("Delete Fetch Error:", error);
    }
  }

  if (target.classList.contains("edit-btn")) {
    const week = weeks.find((w) => w.id === id);
    if (week) {
      document.querySelector("#week-title").value = week.title;
      document.querySelector("#week-start-date").value = week.startDate;
      document.querySelector("#week-description").value = week.description;
      document.querySelector("#week-links").value = week.links ? week.links.join("\n") : "";
      weekForm.dataset.editId = id;
      addWeekBtn.textContent = "Update Week";
    }
  }
}

async function loadAndInitialize() {
  try {
    const response = await fetch("/api.php?resource=weeks");
    if (response.ok) {
      const result = await response.json();
      weeks = result.success ? result.data : [];
    } else {
      weeks = [];
      console.error("Load Error:", response.statusText);
    }
  } catch (error) {
    weeks = [];
    console.error("Load Fetch Error:", error);
  }

  renderTable();
  weekForm.addEventListener("submit", handleAddWeek);
  weeksTableBody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
