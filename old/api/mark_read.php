<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method not allowed']);
    exit;
}

$other = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$csrf = $_POST['csrf_token'] ?? '';
$me = intval($_SESSION['user_id']);

if ($other <= 0 || !validate_csrf_token($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
    $stmt->execute([$other, $me]);
    $count = $stmt->rowCount();
    echo json_encode(['success' => true, 'updated' => $count]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('[mark_read] PDOException: '.$e->getMessage());
    echo json_encode(['success' => false, 'error' => 'db error']);
}
