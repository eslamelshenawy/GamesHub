<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Security: Only allow on localhost or with admin authentication
$isLocalhost = isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);

$allowExecution = $isLocalhost || (isset($_GET['confirm']) && $_GET['confirm'] === 'yes');

if (!$allowExecution) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Not authorized. Add ?confirm=yes to execute this script.',
        'warning' => 'This will modify the database structure!'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $steps = [];

    // Step 1: Check current structure
    $stmt = $pdo->query("DESCRIBE users");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $idColumn = null;
    foreach ($structure as $col) {
        if ($col['Field'] === 'id') {
            $idColumn = $col;
            break;
        }
    }

    $steps[] = [
        'step' => 1,
        'action' => 'Check current ID column',
        'current_extra' => $idColumn['Extra'] ?? 'NOT FOUND',
        'current_key' => $idColumn['Key'] ?? 'NOT FOUND',
    ];

    // Step 2: Check if id is primary key
    $stmt = $pdo->query("SHOW KEYS FROM users WHERE Key_name = 'PRIMARY'");
    $primaryKey = $stmt->fetch(PDO::FETCH_ASSOC);

    $steps[] = [
        'step' => 2,
        'action' => 'Check PRIMARY KEY',
        'has_primary_key' => !empty($primaryKey),
        'primary_key_column' => $primaryKey['Column_name'] ?? null,
    ];

    // Step 3: Fix the table structure
    $queries = [];

    // If id is not primary key, make it primary key
    if (empty($primaryKey) || $primaryKey['Column_name'] !== 'id') {
        // First, drop any existing primary key
        if (!empty($primaryKey)) {
            $queries[] = "ALTER TABLE users DROP PRIMARY KEY";
        }

        // Add primary key to id
        $queries[] = "ALTER TABLE users ADD PRIMARY KEY (id)";
    }

    // If id doesn't have AUTO_INCREMENT, add it
    if (!stripos($idColumn['Extra'], 'auto_increment')) {
        $queries[] = "ALTER TABLE users MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT";
    }

    $steps[] = [
        'step' => 3,
        'action' => 'Prepare ALTER queries',
        'queries_to_execute' => $queries,
    ];

    // Execute the queries
    $executionResults = [];
    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
            $executionResults[] = [
                'query' => $query,
                'status' => 'SUCCESS',
            ];
        } catch (Exception $e) {
            $executionResults[] = [
                'query' => $query,
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
        }
    }

    $steps[] = [
        'step' => 4,
        'action' => 'Execute ALTER queries',
        'results' => $executionResults,
    ];

    // Step 5: Verify the fix
    $stmt = $pdo->query("DESCRIBE users");
    $newStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $newIdColumn = null;
    foreach ($newStructure as $col) {
        if ($col['Field'] === 'id') {
            $newIdColumn = $col;
            break;
        }
    }

    $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'users'");
    $tableStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    $steps[] = [
        'step' => 5,
        'action' => 'Verify fix',
        'new_id_extra' => $newIdColumn['Extra'] ?? 'NOT FOUND',
        'new_id_key' => $newIdColumn['Key'] ?? 'NOT FOUND',
        'auto_increment_value' => $tableStatus['Auto_increment'] ?? null,
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Table structure fixed successfully!',
        'steps' => $steps,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'steps' => $steps ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
