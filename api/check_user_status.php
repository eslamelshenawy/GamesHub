<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    // Session is now managed in db.php
require_once 'security.php';
ensure_session();
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['logged_in' => false]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check if user exists and is active
    $stmt = $pdo->prepare('SELECT id, name, status FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['logged_in' => false]);
        exit;
    }
    
    echo json_encode([
        'logged_in' => true,
        'user_id' => $user['id'],
        'name' => $user['name'],
        'status' => $user['status']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
?>