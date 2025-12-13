<?php

// ============================================================================
// SETUP AND CONFIGURATION
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$weeksFile = 'weeks.json';
$commentsFile = 'comments.json';

$method = $_SERVER['REQUEST_METHOD'];

$data = null;
if (in_array($method, ['POST', 'PUT'])) {
    $data = json_decode(file_get_contents('php://input'), true);
}

$resource = $_GET['resource'] ?? 'weeks';

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function loadJsonData($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

function saveJsonData($filePath, $data) {
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

function getNextId($data) {
    if (empty($data)) {
        return 1;
    }
    $maxId = max(array_column($data, 'id'));
    return $maxId + 1;
}

// Load comments as flat array from nested object
function loadCommentsAsArray() {
    global $commentsFile;
    $commentsObj = loadJsonData($commentsFile);
    $flatComments = [];
    $idCounter = 1;
    foreach ($commentsObj as $weekId => $comments) {
        foreach ($comments as $comment) {
            $flatComments[] = [
                'id' => $idCounter++,
                'week_id' => $weekId,
                'author' => $comment['author'],
                'text' => $comment['text'],
                'created_at' => date('c')  // Add if missing
            ];
        }
    }
    return $flatComments;
}

// Save comments back as nested object
function saveCommentsAsObject($flatComments) {
    global $commentsFile;
    $nested = [];
    foreach ($flatComments as $comment) {
        $weekId = $comment['week_id'];
        if (!isset($nested[$weekId])) {
            $nested[$weekId] = [];
        }
        $nested[$weekId][] = [
            'author' => $comment['author'],
            'text' => $comment['text']
        ];
    }
    saveJsonData($commentsFile, $nested);
}

// ============================================================================
// WEEKS CRUD
// ============================================================================

function getAllWeeks() {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    $search = $_GET['search'] ?? null;
    $sort = $_GET['sort'] ?? 'startDate';
    $order = $_GET['order'] ?? 'asc';
    
    $allowedSortFields = ['title', 'startDate'];
    if (!in_array($sort, $allowedSortFields)) {
        $sort = 'startDate';
    }
    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'asc';
    }
    
    if ($search) {
        $weeks = array_filter($weeks, function($week) use ($search) {
            return stripos($week['title'], $search) !== false || stripos($week['description'], $search) !== false;
        });
    }
    
    usort($weeks, function($a, $b) use ($sort, $order) {
        if ($sort === 'startDate') {
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

function getWeekById($weekId) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!$weekId) {
        sendError('week_id is required', 400);
        return;
    }
    
    foreach ($weeks as $week) {
        if ($week['id'] === $weekId) {  // 'id' is the week_id
            sendResponse(['success' => true, 'data' => $week]);
            return;
        }
    }
    
    sendError('Week not found', 404);
}

function createWeek($data) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!isset($data['id'], $data['title'], $data['startDate'], $data['description'])) {
        sendError('id, title, startDate, and description are required', 400);
        return;
    }
    
    $data['id'] = trim($data['id']);
    $data['title'] = trim($data['title']);
    $data['description'] = trim($data['description']);
    
    if (!validateDate($data['startDate'])) {
        sendError('Invalid startDate format. Use YYYY-MM-DD', 400);
        return;
    }
    
    foreach ($weeks as $week) {
        if ($week['id'] === $data['id']) {
            sendError('Week with this id already exists', 409);
            return;
        }
    }
    
    $newWeek = [
        'id' => $data['id'],
        'title' => $data['title'],
        'startDate' => $data['startDate'],
        'description' => $data['description'],
        'links' => $data['links'] ?? []
    ];
    
    $weeks[] = $newWeek;
    saveJsonData($weeksFile, $weeks);
    
    sendResponse(['success' => true, 'data' => $newWeek], 201);
}

function updateWeek($data) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!isset($data['id'])) {
        sendError('id is required', 400);
        return;
    }
    
    $data['id'] = trim($data['id']);
    
    $found = false;
    foreach ($weeks as &$week) {
        if ($week['id'] === $data['id']) {
            if (isset($data['title'])) {
                $week['title'] = trim($data['title']);
            }
            if (isset($data['startDate'])) {
                if (!validateDate($data['startDate'])) {
                    sendError('Invalid startDate format. Use YYYY-MM-DD', 400);
                    return;
                }
                $week['startDate'] = $data['startDate'];
            }
            if (isset($data['description'])) {
                $week['description'] = trim($data['description']);
            }
            if (isset($data['links']) && is_array($data['links'])) {
                $week['links'] = $data['links'];
            }
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

function deleteWeek($weekId) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    
    if (!$weekId) {
        sendError('week_id is required', 400);
        return;
    }
    
    $found = false;
    $weeks = array_filter($weeks, function($week) use ($weekId, &$found) {
        if ($week['id'] === $weekId) {
            $found = true;
            return false;
        }
        return true;
    });
    
    if (!$found) {
        sendError('Week not found', 404);
        return;
    }
    
    saveJsonData($weeksFile, array_values($weeks));
    // Delete associated comments
    $comments = loadCommentsAsArray();
    $comments = array_filter($comments, function($comment) use ($weekId) {
        return $comment['week_id'] !== $weekId;
    });
    saveCommentsAsObject($comments);
    
    sendResponse(['success' => true, 'message' => 'Week and associated comments deleted successfully']);
}

// ============================================================================
// COMMENTS CRUD
// ============================================================================

function getCommentsByWeek($weekId) {
    $comments = loadCommentsAsArray();
    
    if (!$weekId) {
        sendError('week_id is required', 400);
        return;
    }
    
    $filteredComments = array_filter($comments, function($comment) use ($weekId) {
        return $comment['week_id'] === $weekId;
    });
    
    usort($filteredComments, function($a, $b) {
        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    });
    
    sendResponse(['success' => true, 'data' => array_values($filteredComments)]);
}

function createComment($data) {
    global $weeksFile;
    $weeks = loadJsonData($weeksFile);
    $comments = loadCommentsAsArray();
    
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
    
    $weekExists = false;
    foreach ($weeks as $week) {
        if ($week['id'] === $data['week_id']) {
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
    saveCommentsAsObject($comments);
    
    sendResponse(['success' => true, 'data' => $newComment], 201);
}

function deleteComment($commentId) {
    $comments = loadCommentsAsArray();
    
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
    
    saveCommentsAsObject($comments);
    sendResponse(['success' => true, 'message' => 'Comment deleted successfully']);
}

// ============================================================================
// MAIN ROUTER
// ============================================================================

try {
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
    } elseif ($resource === 'comments') {
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
    } else {
        sendError("Invalid resource. Use 'weeks' or 'comments'", 400);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    sendError('An error occurred', 500);
}

// ============================================================================
// HELPERS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError($message, $statusCode = 400) {
    $error = ['success' => false, 'error' => $message];
    sendResponse($error, $statusCode);
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
?>
