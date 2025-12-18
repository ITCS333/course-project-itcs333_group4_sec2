<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'Database.php';
$db = (new Database())->getConnection();

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id       = $_GET['id'] ?? null;
$data     = json_decode(file_get_contents('php://input'), true);

/* ===================== TOPICS ===================== */

function getAllTopics($db) {
    $stmt = $db->query(
        "SELECT id, subject, message, author, DATE(created_at) AS created_at
         FROM topics
         ORDER BY created_at DESC"
    );
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getTopic($db, $id) {
    if (!$id) sendResponse(['error' => 'ID required'], 400);

    $stmt = $db->prepare(
        "SELECT id, subject, message, author, DATE(created_at) AS created_at
         FROM topics WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$topic) sendResponse(['error' => 'Topic not found'], 404);
    sendResponse($topic);
}

function createTopic($db, $data) {
    if (empty($data['subject']) || empty($data['message']) || empty($data['author'])) {
        sendResponse(['error' => 'Missing fields'], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO topics (subject, message, author)
         VALUES (:subject, :message, :author)"
    );
    $stmt->execute([
        ':subject' => sanitize($data['subject']),
        ':message' => sanitize($data['message']),
        ':author'  => sanitize($data['author'])
    ]);

    sendResponse(['message' => 'Topic created'], 201);
}

function updateTopic($db, $data) {
    if (empty($data['id'])) sendResponse(['error' => 'ID required'], 400);

    $stmt = $db->prepare(
        "UPDATE topics SET subject = :subject, message = :message
         WHERE id = :id"
    );
    $stmt->execute([
        ':subject' => sanitize($data['subject']),
        ':message' => sanitize($data['message']),
        ':id'      => $data['id']
    ]);

    sendResponse(['message' => 'Topic updated']);
}

function deleteTopic($db, $id) {
    if (!$id) sendResponse(['error' => 'ID required'], 400);

    $db->prepare("DELETE FROM replies WHERE topic_id = :id")->execute([':id' => $id]);
    $db->prepare("DELETE FROM topics WHERE id = :id")->execute([':id' => $id]);

    sendResponse(['message' => 'Topic deleted']);
}

/* ===================== REPLIES ===================== */

function getReplies($db, $topicId) {
    if (!$topicId) sendResponse(['error' => 'Topic ID required'], 400);

    $stmt = $db->prepare(
        "SELECT id, text, author, DATE(created_at) AS created_at
         FROM replies
         WHERE topic_id = :id
         ORDER BY created_at ASC"
    );
    $stmt->execute([':id' => $topicId]);

    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function createReply($db, $data) {
    if (
        empty($data['topic_id']) ||
        empty($data['text']) ||
        empty($data['author'])
    ) {
        sendResponse(['error' => 'Missing fields'], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO replies (topic_id, text, author)
         VALUES (:topic_id, :text, :author)"
    );
    $stmt->execute([
        ':topic_id' => $data['topic_id'],
        ':text'     => sanitize($data['text']),
        ':author'   => sanitize($data['author'])
    ]);

    sendResponse(['message' => 'Reply added'], 201);
}

function deleteReply($db, $id) {
    if (!$id) sendResponse(['error' => 'ID required'], 400);

    $db->prepare("DELETE FROM replies WHERE id = :id")->execute([':id' => $id]);
    sendResponse(['message' => 'Reply deleted']);
}

/* ===================== ROUTER ===================== */

switch ($method) {
    case 'GET':
        if ($resource === 'topics') {
            $id ? getTopic($db, $id) : getAllTopics($db);
        } elseif ($resource === 'replies') {
            getReplies($db, $id);
        }
        break;

    case 'POST':
        if ($resource === 'topics') createTopic($db, $data);
        if ($resource === 'replies') createReply($db, $data);
        break;

    case 'PUT':
        updateTopic($db, $data);
        break;

    case 'DELETE':
        if ($resource === 'topics') deleteTopic($db, $id);
        if ($resource === 'replies') deleteReply($db, $id);
        break;

    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

/* ===================== HELPERS ===================== */

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitize($value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}
