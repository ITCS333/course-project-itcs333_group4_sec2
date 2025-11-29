// --- Global Data Store ---
let assignments = [];

// --- Element Selections ---
const assignmentForm = document.querySelector("#assignment-form");
const assignmentsTableBody = document.querySelector("#assignments-tbody");

// --- Functions ---

// Create a table row for one assignment
function createAssignmentRow(assignment) {
    const tr = document.createElement("tr");

    tr.innerHTML =
        "<td>" + assignment.title + "</td>" +
        "<td>" + assignment.dueDate + "</td>" +
        "<td>" +
            "<button class='edit-btn' data-id='" + assignment.id + "'>Edit</button>" +
            "<button class='delete-btn' data-id='" + assignment.id + "'>Delete</button>" +
        "</td>";

    return tr;
}

// Render the table
function renderTable() {
    assignmentsTableBody.innerHTML = "";
    assignments.forEach(function (assignment) {
        const row = createAssignmentRow(assignment);
        assignmentsTableBody.appendChild(row);
    });
}

// Handle Add Assignment
function handleAddAssignment(event) {
    event.preventDefault();

    const title = assignmentForm.querySelector("#title").value.trim();
    const dueDate = assignmentForm.querySelector("#due-date").value;

    const newAssignment = {
        id: "asg_" + Date.now(),
        title: title,
        dueDate: dueDate
    };

    assignments.push(newAssignment);
    renderTable();
    assignmentForm.reset();
}

// Handle Delete (event delegation)
function handleTableClick(event) {
    if (event.target.classList.contains("delete-btn")) {
        const id = event.target.dataset.id;
        assignments = assignments.filter(function (asg) {
            return asg.id !== id;
        });
        renderTable();
    }
}

// Load JSON + Initialize
async function loadAndInitialize() {
    const response = await fetch("assignments.json");
    assignments = await response.json();

    renderTable();

    assignmentForm.addEventListener("submit", handleAddAssignment);
    assignmentsTableBody.addEventListener("click", handleTableClick);
}

// Initial page load
loadAndInitialize();
