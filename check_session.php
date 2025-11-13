<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();

// Check if user/trainer/owner/admin is still logged in
$valid = isset($_SESSION['loggedin']) || isset($_SESSION['trainer_id']) || isset($_SESSION['user_id']);

echo json_encode(['valid' => (bool)$valid]);
exit;
?>
