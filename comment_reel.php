<?php
session_start();
header('Content-Type: application/json'); // return JSON
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die(json_encode(['status'=>'error','msg'=>'DB connection failed']));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Login required']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$reel_id = intval($_POST['reel_id']);
$comment = trim($_POST['comment']);

if ($comment !== "") {
    $stmt = $conn->prepare("INSERT INTO reel_comments (reel_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $reel_id, $user_id, $comment);
    $stmt->execute();

    // Get username from register table
    $res = $conn->query("SELECT name FROM register WHERE id=$user_id");
    $username = $res->fetch_assoc()['name'] ?? 'You';

    echo json_encode([
        'status'=>'success',
        'comment'=>htmlspecialchars($comment),
        'commenter'=>htmlspecialchars($username),
        'created_at'=>date('M j, g:i A')
    ]);
    exit;
}

echo json_encode(['status'=>'error','msg'=>'Comment empty']);
?>