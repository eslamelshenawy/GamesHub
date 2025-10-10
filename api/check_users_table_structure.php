<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Get table structure
    $stmt = $pdo->query("DESCRIBE users");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get table status
    $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'users'");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get auto_increment value
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
    $autoIncrement = $stmt->fetchColumn();

    // Get last inserted user
    $stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY id DESC LIMIT 5");
    $lastUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'table_structure' => $structure,
        'table_status' => [
            'name' => $status['Name'] ?? null,
            'engine' => $status['Engine'] ?? null,
            'auto_increment' => $status['Auto_increment'] ?? null,
            'collation' => $status['Collation'] ?? null,
        ],
        'auto_increment_value' => $autoIncrement,
        'total_users' => $totalUsers,
        'last_5_users' => $lastUsers,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
