<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

$user_id = 0;
if (isset($_GET['user_id']) && intval($_GET['user_id']) > 0) {
    $user_id = intval($_GET['user_id']);
} elseif (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

if ($user_id <= 0) {
    echo json_encode(['count' => 0]);
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM accounts WHERE user_id = ?');
$stmt->execute([$user_id]);
$row = $stmt->fetch();
$count = $row ? intval($row['cnt']) : 0;

echo json_encode(['count' => $count]);
exit;