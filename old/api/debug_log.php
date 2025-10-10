<?php
// api/debug_log.php
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$seller_id = isset($input['seller_id']) ? $input['seller_id'] : null;
$source = isset($input['source']) ? $input['source'] : 'unknown';
file_put_contents('../debug.log', "[JS] seller_id from $source: " . json_encode($seller_id) . "\n", FILE_APPEND);
echo json_encode(['success' => true]);
