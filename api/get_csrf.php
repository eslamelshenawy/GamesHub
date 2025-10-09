<?php
require_once 'security.php';
ensure_session();
ensure_csrf_token();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['csrf_token' => get_csrf_token()]);
