<?php
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'deleted per user request (archive entry cleared)'], JSON_UNESCAPED_UNICODE);
exit;
