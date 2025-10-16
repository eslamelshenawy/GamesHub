<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Update user role to admin
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
    $stmt->execute(['Mada4605123@gmail.com']);

    // Verify the change
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
    $stmt->execute(['Mada4605123@gmail.com']);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'User updated to admin',
        'user' => $user
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
