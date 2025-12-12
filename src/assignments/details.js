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

// --- Initial Page Load ---// details.js - Simple version with inline data
// No fetch required, works directly from computer

// ===== ALL STYLES IN HTML =====
// Note: All CSS is in the HTML file above
// This keeps styling and structure together

// ===== INTERNAL DATA =====
const assignmentsData = [
    {
        id: "asg_1",
        title: "Assignment 1: HTML Basics",
        description: "Create a semantic HTML structure for a personal portfolio. Focus on using proper HTML5 semantic elements to build a clean and accessible layout.",
        dueDate: "November 10, 2025",
        files: ["assignment-brief.pdf", "portfolio-examples.zip", "semantic-html-guide.pdf"]
    },
    {
        id: "asg_2",
        title: "Assignment 2: CSS Styling",
        description: "Style your HTML portfolio using modern CSS techniques with responsive design.",
        dueDate: "November 17, 2025",
        files: ["style-guide.pdf", "css-resources.zip"]
    }
];

const commentsData = {
    "asg_1": [
        { author: "Jane Smith", text: "Is it okay to use a 'section' element inside an 'article'?" },
        { author: "Omar Ali", text: "Do we need a navigation bar for a one-page portfolio?" },
        { author: "Sarah Johnson", text: "What accessibility features should we include?" }
    ],
    "asg_2": [
        { author: "Michael Chen", text: "Can we use CSS frameworks like Bootstrap?" }
    ]
};

// ===== SIMPLE FUNCTIONS =====

// Get assignment ID from URL
function getAssignmentId() {
    const url = new URL(window.location.href);
    return url.searchParams.get('id') || 'asg_1';
}

// Update page with assignment data
function loadAssignment() {
    const assignmentId = getAssignmentId();
    const assignment = assignmentsData.find(a => a.id === assignmentId) || assignmentsData[0];
    const comments = commentsData[assignmentId] || [];
    
    // Update HTML elements
    document.getElementById('assignment-title-text').textContent = assignment.title;
    document.getElementById('assignment-due-date').textContent = `Due: ${assignment.dueDate}`;
    document.getElementById('assignment-description').textContent = assignment.description;
    document.getElementById('assignment-description').className = '';
    
    // Update files list
    const filesList = document.getElementById('assignment-files-list');
    filesList.innerHTML = '';
    
    assignment.files.forEach(file => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = file;
        a.onclick = (e) => {
            e.preventDefault();
            alert(`File: ${file}\n\nThis is a demo. In real app, it would download.`);
        };
        li.appendChild(a);
        filesList.appendChild(li);
    });
    
    // Load and display comments
    loadComments(comments);
    
    // Load saved comments from localStorage
    loadSavedComments(assignmentId);
}

// Display comments
function loadComments(comments) {
    const container = document.getElementById('comments-container');
    
    if (comments.length === 0) {
        container.innerHTML = `
            <div class="no-comments">
                <p>📝 No comments yet</p>
                <p>Be the first to ask a question!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = '';
    comments.forEach(comment => {
        const article = document.createElement('article');
        article.innerHTML = `
            <p>${comment.text}</p>
            <footer><small>Posted by: ${comment.author}</small></footer>
        `;
        container.appendChild(article);
    });
}

// Load saved comments from localStorage
function loadSavedComments(assignmentId) {
    const saved = localStorage.getItem(`comments_${assignmentId}`);
    if (saved) {
        try {
            const savedComments = JSON.parse(saved);
            const container = document.getElementById('comments-container');
            
            savedComments.forEach(comment => {
                // Check if comment already exists
                const existingComments = Array.from(container.querySelectorAll('article p'))
                    .map(p => p.textContent);
                
                if (!existingComments.includes(comment.text)) {
                    const article = document.createElement('article');
                    article.innerHTML = `
                        <p>${comment.text}</p>
                        <footer><small>Posted by: ${comment.author}</small></footer>
                    `;
                    container.appendChild(article);
                }
            });
        } catch (e) {
            console.log('No saved comments');
        }
    }
}

// Save comments to localStorage
function saveComment(commentText, assignmentId) {
    const saved = localStorage.getItem(`comments_${assignmentId}`);
    let comments = saved ? JSON.parse(saved) : [];
    
    const newComment = {
        author: 'You',
        text: commentText,
        date: new Date().toLocaleString()
    };
    
    comments.push(newComment);
    localStorage.setItem(`comments_${assignmentId}`, JSON.stringify(comments));
}

// Safe back button
function goBackSafe() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        const hasAdmin = document.querySelector('a[href="admin.html"]');
        if (hasAdmin) {
            window.location.href = 'admin.html';
        } else {
            alert('No previous page. You can close this tab.');
        }
    }
}

// Show message
function showMessage(text, type = 'success') {
    const message = document.createElement('div');
    message.className = `temp-message ${type}-message`;
    message.textContent = text;
    
    const form = document.getElementById('comment-form');
    form.parentNode.insertBefore(message, form);
    
    setTimeout(() => {
        message.style.opacity = '0';
        setTimeout(() => message.remove(), 500);
    }, 3000);
}

// Handle comment submission
function setupCommentForm() {
    const form = document.getElementById('comment-form');
    const textarea = document.getElementById('new-comment');
    const assignmentId = getAssignmentId();
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const commentText = textarea.value.trim();
        
        if (!commentText) {
            showMessage('Please write a comment first', 'error');
            return;
        }
        
        if (commentText.length < 5) {
            showMessage('Comment should be at least 5 characters', 'error');
            return;
        }
        
        // Add comment to page
        const container = document.getElementById('comments-container');
        const article = document.createElement('article');
        article.innerHTML = `
            <p>${commentText}</p>
            <footer><small>Posted by: You</small></footer>
        `;
        container.appendChild(article);
        
        // Save to localStorage
        saveComment(commentText, assignmentId);
        
        // Clear form
        textarea.value = '';
        
        // Show success message
        showMessage('✓ Comment posted successfully!');
        
        // Scroll to new comment
        article.scrollIntoView({ behavior: 'smooth' });
    });
}

// Initialize everything
function initialize() {
    loadAssignment();
    setupCommentForm();
    
    // Add hover effects to comments
    document.addEventListener('mouseover', function(e) {
        if (e.target.closest('#comments-container article')) {
            const article = e.target.closest('#comments-container article');
            article.style.transform = 'translateY(-2px)';
        }
    });
    
    document.addEventListener('mouseout', function(e) {
        if (e.target.closest('#comments-container article')) {
            const article = e.target.closest('#comments-container article');
            article.style.transform = '';
        }
    });
}

// Start when page loads
document.addEventListener('DOMContentLoaded', initialize);
initializePage();
