
/*
// admin.js
/*
  Requirement: Make the "Manage Assignments" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add an `id="assignments-tbody"` to the <tbody> element
     so you can select it.
  
  3. Implement the TODOs below.
*/

// --- Global Data Stores ---
// This will hold the assignments loaded from the JSON file.
let assignments = [];
let comments = {};

// --- Element Selections ---
// TODO: Select the assignment form ('#assignment-form').
const assignmentForm = document.querySelector('#assignment-form');

// TODO: Select the assignments table body ('#assignments-tbody').
const assignmentsTableBody = document.querySelector('#assignments-tbody');
const commentsSection = document.querySelector('#comments-section');

// --- Functions ---

/**
 * TODO: Implement the createAssignmentRow function.
 * It takes one assignment object {id, title, dueDate}.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the `title`.
 * 2. A <td> for the `dueDate`.
 * 3. A <td> containing two buttons:
 * - An "Edit" button with class "edit-btn" and `data-id="${id}"`.
 * - A "Delete" button with class "delete-btn" and `data-id="${id}"`.
 */
function createAssignmentRow(assignment) {
  const tr = document.createElement('tr');
  
  // 1. A <td> for the `title`.
  const tdTitle = document.createElement('td');
  tdTitle.textContent = assignment.title;
  
  // 2. A <td> for the `dueDate`.
  const tdDueDate = document.createElement('td');
  tdDueDate.textContent = assignment.dueDate;
  
  // 3. A <td> containing two buttons
  const tdActions = document.createElement('td');
  
  // Add "Edit" button with class "edit-btn" and `data-id="${id}"`
  const editButton = document.createElement('button');
  editButton.textContent = 'Edit';
  editButton.className = 'edit-btn';
  editButton.setAttribute('data-id', assignment.id);
  
  // Add "Delete" button with class "delete-btn" and `data-id="${id}"`
  const deleteButton = document.createElement('button');
  deleteButton.textContent = 'Delete';
  deleteButton.className = 'delete-btn';
  deleteButton.setAttribute('data-id', assignment.id);
  
  // Add "View Comments" button (extra feature)
  const commentsButton = document.createElement('button');
  commentsButton.textContent = 'View Comments';
  commentsButton.className = 'view-comments-btn';
  commentsButton.setAttribute('data-id', assignment.id);
  
  tdActions.appendChild(editButton);
  tdActions.appendChild(deleteButton);
  tdActions.appendChild(commentsButton);
  
  tr.appendChild(tdTitle);
  tr.appendChild(tdDueDate);
  tr.appendChild(tdActions);
  
  return tr;
}

/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the `assignmentsTableBody`.
 * 2. Loop through the global `assignments` array.
 * 3. For each assignment, call `createAssignmentRow()`, and
 * append the resulting <tr> to `assignmentsTableBody`.
 */
function renderTable() {
  // 1. Clear the `assignmentsTableBody`.
  assignmentsTableBody.innerHTML = '';
  
  // 2. Loop through the global `assignments` array.
  assignments.forEach(assignment => {
    // 3. For each assignment, call `createAssignmentRow()`
    const row = createAssignmentRow(assignment);
    // Append the resulting <tr> to `assignmentsTableBody`
    assignmentsTableBody.appendChild(row);
  });
}

/**
 * Display comments for a specific assignment
 */
function renderComments(asgId) {
  commentsSection.innerHTML = '';
  const list = comments[asgId] || [];
  
  if (list.length === 0) {
    commentsSection.innerHTML = '<p>No comments yet for this assignment.</p>';
    return;
  }
  
  const ul = document.createElement('ul');
  list.forEach(c => {
    const li = document.createElement('li');
    li.innerHTML = `<strong>${c.author}:</strong> ${c.text}`;
    ul.appendChild(li);
  });
  commentsSection.appendChild(ul);
}

/**
 * TODO: Implement the handleAddAssignment function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title, description, due date, and files inputs.
 * 3. Create a new assignment object with a unique ID (e.g., `id: \`asg_${Date.now()}\``).
 * 4. Add this new assignment object to the global `assignments` array (in-memory only).
 * 5. Call `renderTable()` to refresh the list.
 * 6. Reset the form.
 */
function handleAddAssignment(event) {
  // 1. Prevent the form's default submission.
  event.preventDefault();
  
  // 2. Get the values from the inputs
  const title = assignmentForm.querySelector('#title').value.trim();
  const description = assignmentForm.querySelector('#description').value.trim();
  const dueDate = assignmentForm.querySelector('#due-date').value;
  const files = assignmentForm.querySelector('#files').files;
  
  if (!title || !dueDate) {
    alert('Please fill in required fields (Title and Due Date)');
    return;
  }
  
  // 3. Create a new assignment object with a unique ID
  const newAssignment = {
    id: `asg_${Date.now()}`,
    title: title,
    description: description,
    dueDate: dueDate,
    files: Array.from(files).map(file => file.name)
  };
  
  // 4. Add this new assignment object to the global `assignments` array
  assignments.push(newAssignment);
  
  // 5. Call `renderTable()` to refresh the list.
  renderTable();
  
  // 6. Reset the form.
  assignmentForm.reset();
  
  alert('Assignment added successfully!');
}

/**
 * TODO: Implement the handleTableClick function.
 * This is an event listener on the `assignmentsTableBody` (for delegation).
 * It should:
 * 1. Check if the clicked element (`event.target`) has the class "delete-btn".
 * 2. If it does, get the `data-id` attribute from the button.
 * 3. Update the global `assignments` array by filtering out the assignment
 * with the matching ID (in-memory only).
 * 4. Call `renderTable()` to refresh the list.
 */
function handleTableClick(event) {
  // Check if clicked element has the class "delete-btn"
  if (event.target.classList.contains('delete-btn')) {
    // Get the `data-id` attribute from the button
    const id = event.target.getAttribute('data-id');
    
    if (confirm('Are you sure you want to delete this assignment?')) {
      // Update the global `assignments` array by filtering out the assignment
      assignments = assignments.filter(assignment => assignment.id !== id);
      
      // Remove comments for deleted assignment
      if (comments[id]) {
        delete comments[id];
      }
      
      // Call `renderTable()` to refresh the list
      renderTable();
      commentsSection.innerHTML = '<p>Select an assignment to view comments.</p>';
    }
  }
  
  // Handle edit button (extra functionality)
  if (event.target.classList.contains('edit-btn')) {
    const id = event.target.getAttribute('data-id');
    const assignment = assignments.find(a => a.id === id);
    
    if (assignment) {
      // Pre-fill the form with assignment data
      assignmentForm.querySelector('#title').value = assignment.title;
      assignmentForm.querySelector('#description').value = assignment.description || '';
      assignmentForm.querySelector('#due-date').value = assignment.dueDate;
      
      // Remove the old assignment
      assignments = assignments.filter(a => a.id !== id);
      renderTable();
      
      // Scroll to form
      assignmentForm.scrollIntoView({ behavior: 'smooth' });
      alert('Assignment loaded for editing. Update the form and click "Add Assignment" to save changes.');
    }
  }
  
  // Handle view comments button (extra functionality)
  if (event.target.classList.contains('view-comments-btn')) {
    const id = event.target.getAttribute('data-id');
    renderComments(id);
  }
}

/**
 * TODO: Implement the loadAndInitialize function.
 * This function needs to be 'async'.
 * It should:
 * 1. Use `fetch()` to get data from 'assignments.json'.
 * 2. Parse the JSON response and store the result in the global `assignments` array.
 * 3. Call `renderTable()` to populate the table for the first time.
 * 4. Add the 'submit' event listener to `assignmentForm` (calls `handleAddAssignment`).
 * 5. Add the 'click' event listener to `assignmentsTableBody` (calls `handleTableClick`).
 */
async function loadAndInitialize() {
  try {
    // 1. Use `fetch()` to get data from 'assignments.json'
    const response = await fetch('assignments.json');
    
    // 2. Parse the JSON response and store the result in the global `assignments` array
    assignments = await response.json();
  } catch (error) {
    console.warn('Could not load assignments.json:', error);
    assignments = [];
  }
  
  // Load comments data
  try {
    const comResponse = await fetch('comments.json');
    comments = await comResponse.json();
  } catch (error) {
    console.warn('Could not load comments.json:', error);
    comments = {};
  }
  
  // 3. Call `renderTable()` to populate the table for the first time
  renderTable();
  
  // 4. Add the 'submit' event listener to `assignmentForm` (calls `handleAddAssignment`)
  assignmentForm.addEventListener('submit', handleAddAssignment);
  
  // 5. Add the 'click' event listener to `assignmentsTableBody` (calls `handleTableClick`)
  assignmentsTableBody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
