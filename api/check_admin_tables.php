<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $tables = ['admin_chats', 'admin_chat_messages', 'admin_chat_reads'];
    $results = [];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch() !== false;
        $results[$table] = $exists;

        if ($exists) {
            // Get table structure
            $stmt = $pdo->query("DESCRIBE $table");
            $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results[$table . '_structure'] = $structure;
        }
    }

    echo json_encode(['success' => true, 'tables' => $results], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
