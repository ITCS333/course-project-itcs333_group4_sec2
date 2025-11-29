
// --- Element Selections ---
const listSection = document.querySelector('#assignment-list-section');

// --- Functions ---

// Create an <article> for one assignment
function createAssignmentArticle({id, title, dueDate, description}) {
    const article = document.createElement('article');

    // Title
    const h2 = document.createElement('h2');
    h2.textContent = title;

    // Due date
    const dueP = document.createElement('p');
    dueP.textContent = `Due: ${dueDate}`;

    // Description
    const descP = document.createElement('p');
    descP.textContent = description || "";

    // Link to details page
    const link = document.createElement('a');
    link.href = `details.html?id=${id}`;
    link.textContent = "View Details & Discussion";

    // Append elements to article
    article.appendChild(h2);
    article.appendChild(dueP);
    article.appendChild(descP);
    article.appendChild(link);

    return article;
}

// Load assignments from JSON and display them
async function loadAssignments() {
    try {
        const response = await fetch('assignments.json');
        const assignments = await response.json();

        // Clear existing content
        listSection.innerHTML = "";

        // Add each assignment to the section
        assignments.forEach(assignment => {
            const article = createAssignmentArticle(assignment);
            listSection.appendChild(article);
        });
    } catch (error) {
        console.error("Error loading assignments:", error);
        listSection.innerHTML = "<p>Failed to load assignments.</p>";
    }
}

// --- Initial Page Load ---
loadAssignments();
