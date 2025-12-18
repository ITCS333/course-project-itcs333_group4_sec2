<?php
// ============================================================================
// START SESSION
// ============================================================================
session_start();

// ============================================================================
// CONFIGURATION
// ============================================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'weeks';
$data = null;
if (in_array($method, ['POST', 'PUT'])) {
    $data = json_decode(file_get_contents('php://input'), true);
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================
try {
    // Example: MySQL
    $pdo = new PDO('mysql:host=localhost;dbname=course', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sendError('Database connection failed: ' . $e->getMessage(), 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError($message, $statusCode = 400) {
    sendResponse(['success' => false, 'error' => $message], $statusCode);
}

// ============================================================================
// WEEKS CRUD
// ============================================================================
function getAllWeeks() {
    global $pdo;
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'startDate';
    $order = $_GET['order'] ?? 'ASC';

    $allowedSort = ['title', 'startDate'];
    if (!in_array($sort, $allowedSort)) $sort = 'startDate';
    if (!in_array(strtoupper($order), ['ASC','DESC'])) $order = 'ASC';

    try {
        if ($search) {
            $stmt = $pdo->prepare("SELECT * FROM weeks WHERE title LIKE :search OR description LIKE :search ORDER BY $sort $order");
            $stmt->execute([':search' => "%$search%"]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM weeks ORDER BY $sort $order");
            $stmt->execute();
        }
        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(['success' => true, 'data' => $weeks]);
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

function getWeekById($weekId) {
    global $pdo;
    if (!$weekId) sendError('week_id is required', 400);

    try {
        $stmt = $pdo->prepare("SELECT * FROM weeks WHERE id = :id");
        $stmt->execute([':id' => $weekId]);
        $week = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($week) {
            sendResponse(['success' => true, 'data' => $week]);
        } else {
            sendError('Week not found', 404);
        }
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

function createWeek($data) {
    global $pdo;
    if (!isset($data['title'], $data['startDate'], $data['description'])) {
        sendError('title, startDate, and description are required', 400);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO weeks (title, startDate, description) VALUES (:title, :startDate, :description)");
        $stmt->execute([
            ':title' => trim($data['title']),
            ':startDate' => trim($data['startDate']),
            ':description' => trim($data['description'])
        ]);
        $id = $pdo->lastInsertId();
        getWeekById($id); // Return the created week
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

function updateWeek($data) {
    global $pdo;
    if (!isset($data['id'])) sendError('id is required', 400);

    $fields = [];
    $params = [':id' => $data['id']];
    if (isset($data['title'])) { $fields[] = "title=:title"; $params[':title'] = trim($data['title']); }
    if (isset($data['startDate'])) { $fields[] = "startDate=:startDate"; $params[':startDate'] = trim($data['startDate']); }
    if (isset($data['description'])) { $fields[] = "description=:description"; $params[':description'] = trim($data['description']); }

    if (empty($fields)) sendError('No fields to update', 400);

    $sql = "UPDATE weeks SET " . implode(', ', $fields) . " WHERE id=:id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendResponse(['success' => true, 'message' => 'Week updated successfully']);
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

function deleteWeek($weekId) {
    global $pdo;
    if (!$weekId) sendError('week_id is required', 400);

    try {
        // Delete comments first
        $stmt = $pdo->prepare("DELETE FROM comments WHERE week_id=:week_id");
        $stmt->execute([':week_id' => $weekId]);

        $stmt = $pdo->prepare("DELETE FROM weeks WHERE id=:id");
        $stmt->execute([':id' => $weekId]);

        sendResponse(['success' => true, 'message' => 'Week and associated comments deleted successfully']);
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

// ============================================================================
// COMMENTS CRUD
// ============================================================================
function getCommentsByWeek($weekId) {
    global $pdo;
    if (!$weekId) sendError('week_id is required', 400);

    try {
        $stmt = $pdo->prepare("SELECT * FROM comments WHERE week_id=:week_id ORDER BY created_at ASC");
        $stmt->execute([':week_id' => $weekId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(['success' => true, 'data' => $comments]);
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

function createComment($data) {
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        sendError('User must be logged in to create comments', 401);
    }

    if (!isset($data['week_id'], $data['author'], $data['text'])) 
        sendError('week_id, author, and text are required', 400);

    try {
        $stmt = $pdo->prepare("INSERT INTO comments (week_id, author, text, created_at) VALUES (:week_id, :author, :text, :created_at)");
        $stmt->execute([
            ':week_id' => $data['week_id'],
            ':author' => trim($data['author']),
            ':text' => trim($data['text']),
            ':created_at' => date('Y-m-d H:i:s')
        ]);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM comments WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        sendResponse(['success' => true, 'data' => $comment], 201);
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}


function deleteComment($commentId) {
    global $pdo;
    if (!$commentId) sendError('id is required', 400);

    try {
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id=:id");
        $stmt->execute([':id' => $commentId]);
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully']);
    } catch (PDOException $e) {
        sendError($e->getMessage(), 500);
    }
}

// ============================================================================
// ROUTER
// ============================================================================
try {
    if ($resource === 'weeks') {
        if ($method === 'GET') {
            $weekId = $_GET['week_id'] ?? null;
            if ($weekId) getWeekById($weekId);
            else getAllWeeks();
        } elseif ($method === 'POST') {
            createWeek($data);
        } elseif ($method === 'PUT') {
            updateWeek($data);
        } elseif ($method === 'DELETE') {
            $weekId = $_GET['week_id'] ?? ($data['week_id'] ?? null);
            deleteWeek($weekId);
        } else sendError('Method not allowed', 405);
    } elseif ($resource === 'comments') {
        if ($method === 'GET') {
            $weekId = $_GET['week_id'] ?? null;
            getCommentsByWeek($weekId);
        } elseif ($method === 'POST') {
            createComment($data);
        } elseif ($method === 'DELETE') {
            $commentId = $_GET['id'] ?? ($data['id'] ?? null);
            deleteComment($commentId);
        } else sendError('Method not allowed', 405);
    } else sendError("Invalid resource. Use 'weeks' or 'comments'", 400);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
