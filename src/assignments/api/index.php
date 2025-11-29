<?php
/**
 * Assignment Management API
 * 
 * This is a RESTful API that handles all CRUD operations for course assignments
 * and their associated discussion comments.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: assignments
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(200))
 *   - description (TEXT)
 *   - due_date (DATE)
 *   - files (TEXT)
 *   - created_at (TIMESTAMP)
 *   - updated_at (TIMESTAMP)
 * 
 * Table: comments
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - assignment_id (VARCHAR(50), FOREIGN KEY)
 *   - author (VARCHAR(100))
 *   - text (TEXT)
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET: Retrieve assignment(s) or comment(s)
 *   - POST: Create a new assignment or comment
 *   - PUT: Update an existing assignment
 *   - DELETE: Delete an assignment or comment
 * 
 * Response Format: JSON
 */

// ============================================================================
// HEADERS AND CORS CONFIGURATION
// ============================================================================

// Set Content-Type header to application/json
header("Content-Type: application/json");

// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

// Include the database connection class
require_once 'db.php';

// Create database connection
$db = new PDO("mysql:host=localhost;dbname=assignments_db;charset=utf8", "username", "password");

// Set PDO to throw exceptions on errors
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ============================================================================
// REQUEST PARSING
// ============================================================================

// Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the request body for POST and PUT requests
$input = json_decode(file_get_contents("php://input"), true);

// Parse query parameters
$queryParams = $_GET;

// ============================================================================
// ASSIGNMENT CRUD FUNCTIONS
// ============================================================================

function getAllAssignments($db) {
    $sql = "SELECT * FROM assignments WHERE 1";
    
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " AND (title LIKE :search OR description LIKE :search)";
        $params[':search'] = "%" . $_GET['search'] . "%";
    }
    
    $allowedSort = ['title', 'due_date', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    $order = strtolower($_GET['order'] ?? 'asc');
    
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    if (!in_array($order, ['asc','desc'])) $order = 'asc';
    
    $sql .= " ORDER BY $sort $order";
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$assignment) {
        $assignment['files'] = json_decode($assignment['files'] ?? '[]', true);
    }
    
    echo json_encode($results);
}

function getAssignmentById($db, $assignmentId) {
    if (empty($assignmentId)) {
        http_response_code(400);
        echo json_encode(['error'=>'Assignment ID required']);
        exit();
    }
    
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id=:id");
    $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
    $stmt->execute();
    
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        http_response_code(404);
        echo json_encode(['error'=>'Assignment not found']);
        exit();
    }
    
    $assignment['files'] = json_decode($assignment['files'] ?? '[]', true);
    echo json_encode($assignment);
}

function createAssignment($db, $data) {
    if (empty($data['title']) || empty($data['description']) || empty($data['due_date'])) {
        http_response_code(400);
        echo json_encode(['error'=>'title, description, due_date required']);
        exit();
    }
    
    $title = htmlspecialchars(trim($data['title']));
    $desc = htmlspecialchars(trim($data['description']));
    $due = $data['due_date'];
    
    $dateObj = DateTime::createFromFormat('Y-m-d', $due);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $due) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid due_date']);
        exit();
    }
    
    $files = !empty($data['files']) ? json_encode($data['files']) : json_encode([]);
    
    $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files, created_at, updated_at) VALUES (:title,:desc,:due,:files,NOW(),NOW())");
    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':desc', $desc);
    $stmt->bindValue(':due', $due);
    $stmt->bindValue(':files', $files);
    
    if ($stmt->execute()) {
        $id = $db->lastInsertId();
        echo json_encode(['success'=>true,'id'=>$id]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Insert failed']);
    }
}

function updateAssignment($db, $data) {
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error'=>'Assignment ID required']);
        exit();
    }
    
    $id = $data['id'];
    
    $stmtCheck = $db->prepare("SELECT * FROM assignments WHERE id=:id");
    $stmtCheck->bindValue(':id', $id);
    $stmtCheck->execute();
    
    if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['error'=>'Assignment not found']);
        exit();
    }
    
    $fields = [];
    if (!empty($data['title'])) $fields['title'] = htmlspecialchars(trim($data['title']));
    if (!empty($data['description'])) $fields['description'] = htmlspecialchars(trim($data['description']));
    if (!empty($data['due_date'])) {
        $due = $data['due_date'];
        $dateObj = DateTime::createFromFormat('Y-m-d', $due);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $due) {
            http_response_code(400);
            echo json_encode(['error'=>'Invalid due_date']);
            exit();
        }
        $fields['due_date'] = $due;
    }
    if (!empty($data['files'])) $fields['files'] = json_encode($data['files']);
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error'=>'No fields to update']);
        exit();
    }
    
    $sql = "UPDATE assignments SET ";
    $sqlParts = [];
    foreach ($fields as $key=>$val) $sqlParts[] = "$key=:$key";
    $sql .= implode(",", $sqlParts) . ", updated_at=NOW() WHERE id=:id";
    
    $stmt = $db->prepare($sql);
    foreach ($fields as $key=>$val) $stmt->bindValue(":$key",$val);
    $stmt->bindValue(":id",$id);
    
    if ($stmt->execute()) {
        echo json_encode(['success'=>true]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Update failed']);
    }
}

function deleteAssignment($db, $assignmentId) {
    if (empty($assignmentId)) {
        http_response_code(400);
        echo json_encode(['error'=>'Assignment ID required']);
        exit();
    }
    
    $stmtCheck = $db->prepare("SELECT * FROM assignments WHERE id=:id");
    $stmtCheck->bindValue(':id',$assignmentId);
    $stmtCheck->execute();
    
    if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['error'=>'Assignment not found']);
        exit();
    }
    
    // Delete comments first
    $stmtDelComments = $db->prepare("DELETE FROM comments WHERE assignment_id=:id");
    $stmtDelComments->bindValue(':id',$assignmentId);
    $stmtDelComments->execute();
    
    $stmt = $db->prepare("DELETE FROM assignments WHERE id=:id");
    $stmt->bindValue(':id',$assignmentId);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Delete failed']);
    }
}

// ============================================================================
// COMMENT CRUD FUNCTIONS
// ============================================================================

function getCommentsByAssignment($db, $assignmentId) {
    if (empty($assignmentId)) {
        http_response_code(400);
        echo json_encode(['error'=>'Assignment ID required']);
        exit();
    }
    
    $stmt = $db->prepare("SELECT * FROM comments WHERE assignment_id=:assignment_id");
    $stmt->bindValue(':assignment_id',$assignmentId);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
}

function createComment($db, $data) {
    if (empty($data['assignment_id']) || empty($data['author']) || empty(trim($data['text']))) {
        http_response_code(400);
        echo json_encode(['error'=>'assignment_id, author, text required']);
        exit();
    }
    
    $stmtCheck = $db->prepare("SELECT * FROM assignments WHERE id=:id");
    $stmtCheck->bindValue(':id',$data['assignment_id']);
    $stmtCheck->execute();
    
    if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['error'=>'Assignment not found']);
        exit();
    }
    
    $author = htmlspecialchars(trim($data['author']));
    $text = htmlspecialchars(trim($data['text']));
    
    $stmt = $db->prepare("INSERT INTO comments (assignment_id, author, text, created_at) VALUES (:aid,:author,:text,NOW())");
    $stmt->bindValue(':aid',$data['assignment_id']);
    $stmt->bindValue(':author',$author);
    $stmt->bindValue(':text',$text);
    
    if ($stmt->execute()) {
        $id = $db->lastInsertId();
        echo json_encode(['success'=>true,'id'=>$id]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Insert failed']);
    }
}

function deleteComment($db, $commentId) {
    if (empty($commentId)) {
        http_response_code(400);
        echo json_encode(['error'=>'Comment ID required']);
        exit();
    }
    
    $stmtCheck = $db->prepare("SELECT * FROM comments WHERE id=:id");
    $stmtCheck->bindValue(':id',$commentId);
    $stmtCheck->execute();
    
    if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['error'=>'Comment not found']);
        exit();
    }
    
    $stmt = $db->prepare("DELETE FROM comments WHERE id=:id");
    $stmt->bindValue(':id',$commentId);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Delete failed']);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) $data = ['message'=>$data];
    echo json_encode($data);
    exit();
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function validateDate($date) {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function validateAllowedValue($value, $allowedValues) {
    return in_array($value, $allowedValues);
}
?>

