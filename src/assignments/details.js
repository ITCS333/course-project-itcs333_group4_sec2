// --- Global Data Store ---
let currentAssignmentId = null;
let currentComments = [];

// --- Element Selections ---
const assignmentTitle = document.querySelector('#assignment-title');
const assignmentDueDate = document.querySelector('#assignment-due-date');
const assignmentDescription = document.querySelector('#assignment-description');
const assignmentFilesList = document.querySelector('#assignment-files-list');
const commentList = document.querySelector('#comment-list');
const commentForm = document.querySelector('#comment-form');
const newCommentText = document.querySelector('#new-comment-text');

// --- Functions ---

// Get assignment ID from URL query string
function getAssignmentIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// Render assignment details
function renderAssignmentDetails(assignment) {
    assignmentTitle.textContent = assignment.title;
    assignmentDueDate.textContent = "Due: " + assignment.dueDate;
    assignmentDescription.textContent = assignment.description || "";

    // Clear existing files
    assignmentFilesList.innerHTML = "";
    if (assignment.files && assignment.files.length > 0) {
        assignment.files.forEach(file => {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = "#"; // Replace with actual file link if needed
            a.textContent = file;
            li.appendChild(a);
            assignmentFilesList.appendChild(li);
        });
    }
}

// Create a comment <article>
function createCommentArticle({author, text}) {
    const article = document.createElement('article');
    const p = document.createElement('p');
    p.textContent = text;
    const footer = document.createElement('footer');
    footer.textContent = `Posted by: ${author}`;
    article.appendChild(p);
    article.appendChild(footer);
    return article;
}

// Render all comments
function renderComments() {
    commentList.innerHTML = "";
    currentComments.forEach(comment => {
        const commentArticle = createCommentArticle(comment);
        commentList.appendChild(commentArticle);
    });
}

// Handle adding a new comment
function handleAddComment(event) {
    event.preventDefault();

    const commentText = newCommentText.value.trim();
    if (!commentText) return;

    const newComment = {
        author: 'Student',
        text: commentText
    };

    currentComments.push(newComment);
    renderComments();
    newCommentText.value = "";
}

// Initialize page
async function initializePage() {
    currentAssignmentId = getAssignmentIdFromURL();

    if (!currentAssignmentId) {
        alert("No assignment ID found in URL.");
        return;
    }

    try {
        const [assignmentsResponse, commentsResponse] = await Promise.all([
            fetch('assignments.json'),
            fetch('comments.json')
        ]);

        const assignments = await assignmentsResponse.json();
        const allComments = await commentsResponse.json();

        const assignment = assignments.find(a => a.id === currentAssignmentId);
        currentComments = allComments[currentAssignmentId] || [];

        if (assignment) {
            renderAssignmentDetails(assignment);
            renderComments();
            commentForm.addEventListener('submit', handleAddComment);
        } else {
            alert("Assignment not found.");
        }
    } catch (error) {
        console.error("Error loading data:", error);
        alert("Error loading assignment data.");
    }
}

// --- Initial Page Load ---
initializePage();
