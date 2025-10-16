<?php
// Test database connection
require_once 'db.php';

header('Content-Type: application/json');

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT DATABASE() as db_name, COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'bvize_games_accounts'");
        $result = $stmt->fetch();
        echo json_encode([
            'success' => true,
            'message' => 'Database connected successfully',
            'database' => $result['db_name'],
            'tables' => $result['table_count']
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Query failed: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => isset($GLOBALS['db_error_message']) ? $GLOBALS['db_error_message'] : 'Unknown error'
    ]);
}
