<?php
/**
 * Weekly Course Breakdown API
 * 
 * This is a RESTful API that handles all CRUD operations for weekly course content
 * and discussion comments. It uses JSON files for data storage instead of a database.
 * 
 * JSON File Structures:
 * 
 * weeks.json: Array of week objects
 *   - id (INT, auto-incremented)
 *   - week_id (STRING, UNIQUE) - Unique identifier (e.g., "week_1")
 *   - title (STRING)
 *   - start_date (STRING, YYYY-MM-DD)
 *   - description (STRING)
 *   - links (ARRAY) - Array of links
 *   - created_at (STRING, ISO 8601)
 *   - updated_at (STRING, ISO 8601)
 * 
 * comments.json: Array of comment objects
 *   - id (INT, auto-incremented)
 *   - week_id (STRING) - Reference to weeks.week_id
 *   - author (STRING)
 *   - text (STRING)
 *   - created_at (STRING, ISO 8601)
 * 
 * HTTP Methods Supported:
 *   - GET: Retrieve week(s) or comment(s)
 *   - POST: Create a new week or comment
 *   - PUT: Update an existing week
 *   - DELETE: Delete a week or comment
 * 
 * Response Format: JSON
 */

// ============================================================================
// SETUP AND CONFIGURATION
// ============================================================================

// TODO: Set headers for JSON response and CORS
// Set Content-Type to application/json
// Allow cross-origin requests (CORS) if needed
// Allow specific HTTP methods (GET, POST, PUT, DELETE, OPTIONS)
// Allow specific headers (Content-Type, Authorization)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust as needed for security
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// TODO: Handle preflight OPTIONS request
// If the request method is OPTIONS, return 200 status and exit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Define file paths (assuming files are in the same 'api' folder as index.php)
$weeksFile = 'weeks.json';
$commentsFile = 'comments.json';

// TODO: Get the HTTP request method
// Use $_SERVER['REQUEST_METHOD']
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Get the request body for POST and PUT requests
// Use file_get_contents('php://input') to get raw POST data
// Decode JSON data using json_decode()
$data = null;
if (in_array($method, ['POST', 'PUT'])) {
    $data = json_decode(file_get_contents('php://input'), true);
}

// TODO: Parse query parameters
// Get the 'resource' parameter to determine if request is for weeks or comments
$resource = $_GET['resource'] ?? 'weeks';

// ============================================================================
// HELPER FUNCTIONS FOR JSON FILE OPERATIONS
// ============================================================================

/**
 * Load data from JSON file
 * @param string $filePath
 * @return array
 */
function loadJsonData($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Save data to JSON file
 * @param string $filePath
 * @param array $data
 */
function saveJsonData($filePath, $data) {
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Get next ID for a data array
 * @param array $data
 * @return int
 */
function getNextId($data) {
    if (empty($data)) {
        return 1;
    }
    $maxId = max(array_column($data, 'id'));
    return $maxId + 1;
}

// ============================================================================
// WEEKS CRUD OPERATIONS
// ============================================================================

/**
 * Function: Get all weeks or search for specific weeks
 * Method: GET
 * Resource: weeks
 * 
 * Query Parameters:
 *   - search: Optional search term to filter by title or description
 *   - sort: Optional field to sort by (title, start_date, created_at)
 *   - order: Optional sort order (asc or desc, default: asc)
 */
function getAllWeeks() {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    $search = $_GET['search'] ?? null;
    $sort = $_GET['sort'] ?? 'start_date';
    $order = $_GET['order'] ?? 'asc';
    
    $allowedSortFields = ['title', 'start_date', 'created_at'];
    if (!in_array($sort, $allowedSortFields)) {
        $sort = 'start_date';
    }
    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'asc';
    }
    
    // Filter by search
    if ($search) {
        $weeks = array_filter($weeks, function($week) use ($search) {
            return stripos($week['title'], $search) !== false || stripos($week['description'], $search) !== false;
        });
    }
    
    // Sort
    usort($weeks, function($a, $b) use ($sort, $order) {
        if ($sort === 'start_date') {
            $aVal = strtotime($a[$sort]);
            $bVal = strtotime($b[$sort]);
        } else {
            $aVal = $a[$sort];
            $bVal = $b[$sort];
        }
        if ($order === 'asc') {
            return $aVal <=> $bVal;
        } else {
            return $bVal <=> $aVal;
        }
    });
    
    sendResponse(['success' => true, 'data' => array_values($weeks)]);
}

/**
 * Function: Get a single week by week_id
 * Method: GET
 * Resource: weeks
 * 
 * Query Parameters:
 *   - week_id: The unique week identifier (e.g., "week_1")
 */
function getWeekById($weekId) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!$weekId) {
        sendError('week_id is required', 400);
        return;
    }
    
    foreach ($weeks as $week) {
        if ($week['week_id'] === $weekId) {
            sendResponse(['success' => true, 'data' => $week]);
            return;
        }
    }
    
    sendError('Week not found', 404);
}

/**
 * Function: Create a new week
 * Method: POST
 * Resource: weeks
 * 
 * Required JSON Body:
 *   - week_id: Unique week identifier (e.g., "week_1")
 *   - title: Week title (e.g., "Week 1: Introduction to HTML")
 *   - start_date: Start date in YYYY-MM-DD format
 *   - description: Week description
 *   - links: Array of resource links (optional)
 */
function createWeek($data) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!isset($data['week_id'], $data['title'], $data['start_date'], $data['description'])) {
        sendError('week_id, title, start_date, and description are required', 400);
        return;
    }
    
    $data['week_id'] = trim($data['week_id']);
    $data['title'] = trim($data['title']);
    $data['description'] = trim($data['description']);
    
    if (!validateDate($data['start_date'])) {
        sendError('Invalid start_date format. Use YYYY-MM-DD', 400);
        return;
    }
    
    // Check for duplicate week_id
    foreach ($weeks as $week) {
        if ($week['week_id'] === $data['week_id']) {
            sendError('Week with this week_id already exists', 409);
            return;
        }
    }
    
    $newWeek = [
        'id' => getNextId($weeks),
        'week_id' => $data['week_id'],
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'description' => $data['description'],
        'links' => $data['links'] ?? [],
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];
    
    $weeks[] = $newWeek;
    saveJsonData($weeksFile, $weeks);
    
    sendResponse(['success' => true, 'data' => $newWeek], 201);
}

/**
 * Function: Update an existing week
 * Method: PUT
 * Resource: weeks
 * 
 * Required JSON Body:
 *   - week_id: Unique week identifier (e.g., "week_1")
 *   - title: Week title (optional)
 *   - start_date: Start date in YYYY-MM-DD format (optional)
 *   - description: Week description (optional)
 *   - links: Array of resource links (optional)
 */
function updateWeek($data) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!isset($data['week_id'])) {
        sendError('week_id is required', 400);
        return;
    }
    
    $data['week_id'] = trim($data['week_id']);
    
    $found = false;
    foreach ($weeks as &$week) {
        if ($week['week_id'] === $data['week_id']) {
            if (isset($data['title'])) {
                $week['title'] = trim($data['title']);
            }
            if (isset($data['start_date'])) {
                if (!validateDate($data['start_date'])) {
                    sendError('Invalid start_date format. Use YYYY-MM-DD', 400);
                    return;
                }
                $week['start_date'] = $data['start_date'];
            }
            if (isset($data['description'])) {
                $week['description'] = trim($data['description']);
            }
            if (isset($data['links']) && is_array($data['links'])) {
                $week['links'] = $data['links'];
            }
            $week['updated_at'] = date('c');
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        sendError('Week not found', 404);
        return;
    }
    
    saveJsonData($weeksFile, $weeks);
    sendResponse(['success' => true, 'message' => 'Week updated successfully']);
}

/**
 * Function: Delete a week
 * Method: DELETE
 * Resource: weeks
 * 
 * Query Parameters or JSON Body:
 *   - week_id: The week identifier
 */
function deleteWeek($weekId) {
    global $weeksFile, $commentsFile;
    $weeks = loadJsonData($weeksFile);
    $comments = loadJsonData($commentsFile);
    
    if (!$weekId) {
        sendError('week_id is required', 400);
        return;
    }
    
    $found = false;
    $weeks = array_filter($weeks, function($week) use ($weekId, &$found) {
        if ($week['week_id'] === $weekId) {
            $found = true;
            return false;
        }
        return true;
    });
    
    if (!$found) {
        sendError('Week not found', 404);
        return;
    }
    
    // Delete associated comments
    $comments = array_filter($comments, function($comment) use ($weekId) {
        return $comment['week_id'] !== $weekId;
    });
    
    saveJsonData($weeksFile, array_values($weeks));
    saveJsonData($commentsFile, array_values($comments));
    
    sendResponse(['success' => true, 'message' => 'Week and associated comments deleted successfully']);
}

// ============================================================================
// COMMENTS CRUD OPERATIONS
// ============================================================================

/**
 * Function: Get all comments for a specific week
 * Method: GET
 * Resource: comments
 * 
 * Query Parameters:
 *   - week_id: The week identifier to get comments for
 */
function getCommentsByWeek($weekId) {
    global $commentsFile;
    $comments = loadJsonData($commentsFile);
    
    if (!$weekId) {
        sendError('week_id is required', 400);
        return;
    }
    
    $filteredComments = array_filter($comments, function($comment) use ($weekId) {
        return $comment['week_id'] === $weekId;
    });
    
    // Sort by created_at
    usort($filteredComments, function($a, $b) {
        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    });
    
    sendResponse(['success' => true, 'data' => array_values($filteredComments)]);
}

/**
 * Function: Create a new comment
 * Method: POST
 * Resource: comments
 * 
 * Required JSON Body:
 *   - week_id: The week identifier this comment belongs to
 *   - author: Comment author name
 *   - text: Comment text content
 */
function createComment($data) {
    global $weeksFile, $commentsFile;
    $weeks = loadJsonData($weeksFile);
    $comments = loadJsonData($commentsFile);
    
    if (!isset($data['week_id'], $data['author'], $data['text'])) {
        sendError('week_id, author, and text are required', 400);
        return;
    }
    
    $data['week_id'] = trim($data['week_id']);
    $data['author'] = trim($data['author']);
    $data['text'] = trim($data['text']);
    
    if (empty($data['text'])) {
        sendError('Comment text cannot be empty', 400);
        return;
    }
    
    // Check if week exists
    $weekExists = false;
    foreach ($weeks as $week) {
        if ($week['week_id'] === $data['week_id']) {
            $weekExists = true;
            break;
        }
    }
    if (!$weekExists) {
        sendError('Week not found', 404);
        return;
    }
    
    $newComment = [
        'id' => getNextId($comments),
        'week_id' => $data['week_id'],
        'author' => $data['author'],
        'text' => $data['text'],
        'created_at' => date('c')
    ];
    
    $comments[] = $newComment;
    saveJsonData($commentsFile, $comments);
    
    sendResponse(['success' => true, 'data' => $newComment], 201);
}

/**
 * Function: Delete a comment
 * Method: DELETE
 * Resource: comments
 * 
 * Query Parameters or JSON Body:
 *   - id: The comment ID to delete
 */
function deleteComment($commentId) {
    global $commentsFile;
    $comments = loadJsonData($commentsFile);
    
    if (!$commentId) {
        sendError('id is required', 400);
        return;
    }
    
    $found = false;
    $comments = array_filter($comments, function($comment) use ($commentId, &$found) {
        if ($comment['id'] == $commentId) {
            $found = true;
            return false;
        }
        return true;
    });
    
    if (!$found) {
        sendError('Comment not found', 404);
        return;
    }
    
    saveJsonData($commentsFile, array_values($comments));
    sendResponse(['success' => true, 'message' => 'Comment deleted successfully']);
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    // Route based on resource type and HTTP method
    
    // ========== WEEKS ROUTES ==========
    if ($resource === 'weeks') {
        
        if ($method === 'GET') {
            $weekId = $_GET['week_id'] ?? null;
            if ($weekId) {
                getWeekById($weekId);
            } else {
                getAllWeeks();
            }
        } elseif ($method === 'POST') {
            createWeek($data);
        } elseif ($method === 'PUT') {
            updateWeek($data);
        } elseif ($method === 'DELETE') {
            $weekId = $_GET['week_id'] ?? ($data['week_id'] ?? null);
            deleteWeek($weekId);
        } else {
            sendError('Method not allowed', 405);
        }
    }
    
    // ========== COMMENTS ROUTES ==========
    elseif ($resource === 'comments') {
        
        if ($method === 'GET') {
            $weekId = $_GET['week_id'] ?? null;
            getCommentsByWeek($weekId);
        } elseif ($method === 'POST') {
            createComment($data);
        } elseif ($method === 'DELETE') {
            $commentId = $_GET['id'] ?? ($data['id'] ?? null);
            deleteComment($commentId);
        } else {
            sendError('Method not allowed', 405);
        }
    }
    
    // ========== INVALID RESOURCE ==========
    else {
        sendError("Invalid resource. Use 'weeks' or 'comments'", 400);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    sendError('An error occurred', 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper function to send JSON response
 * 
 * @param mixed $data - Data to send (will be JSON encoded)
 * @param int $statusCode - HTTP status code (default: 200)
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Helper function to send error response
 * 
 * @param string $message - Error message
 * @param int $statusCode - HTTP status code
 */
function sendError($message, $statusCode = 400) {
    $error = ['success' => false, 'error' => $message];
    sendResponse($error, $statusCode);
}

/**
 * Helper function to validate date format (YYYY-MM-DD)
 * 
 * @param string $date - Date string to validate
 * @return bool - True if valid, false otherwise
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Helper function to sanitize input
 * 
 * @param string $data - Data to sanitize
 * @return string - Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}


/**
 * Helper function to validate allowed sort fields
 * 
 * @param string $field - Field name to validate
 * @param array $allowedFields - Array of allowed field names
 * @return bool - True if valid, false otherwise
 */
function isValidSortField($field, $allowedFields) {
    return in_array($field, $allowedFields);
}

?>
