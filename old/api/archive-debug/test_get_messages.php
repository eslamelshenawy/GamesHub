<?php
// Test harness for get_messages.php
// Usage: run `php test_get_messages.php` from the api folder or call via webserver.
if (session_status() === PHP_SESSION_NONE) session_start();

// Set a test logged-in user (adjust to an existing user id in your DB)
$_SESSION['user_id'] = 1;

// Set the chat partner id to fetch messages with
$_GET['user_id'] = 2;

// Make sure server variables are present for debugging/logging in the endpoint
if (empty($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

error_log('[test_get_messages] starting; session_user=' . $_SESSION['user_id'] . ' chat_with=' . $_GET['user_id']);

// Include the real endpoint (it will echo JSON)
require_once __DIR__ . '/get_messages.php';

?>
