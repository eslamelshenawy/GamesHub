<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    // First, check table structure
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['Mada4605123@gmail.com']);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => $user,
            'table_columns' => $columns,
            'is_admin_column_exists' => in_array('is_admin', $columns),
            'role_is_admin' => isset($user['role']) && $user['role'] === 'admin'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'User not found',
            'table_columns' => $columns
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
