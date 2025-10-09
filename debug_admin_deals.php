<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

require_once 'api/db.php';
require_once 'api/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode(['status' => 'success', 'message' => 'Debug script working', 'timestamp' => date('Y-m-d H:i:s')]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>