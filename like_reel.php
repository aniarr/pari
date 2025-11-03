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

// Check if already liked
$check = $conn->query("SELECT id FROM reel_likes WHERE reel_id=$reel_id AND user_id=$user_id");
if ($check->num_rows > 0) {
    $conn->query("DELETE FROM reel_likes WHERE reel_id=$reel_id AND user_id=$user_id");
    $liked = false;
} else {
    $stmt = $conn->prepare("INSERT INTO reel_likes (reel_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $reel_id, $user_id);
    $stmt->execute();
    $liked = true;
}

// Get updated like count
$likeCount = $conn->query("SELECT COUNT(*) as c FROM reel_likes WHERE reel_id=$reel_id")->fetch_assoc()['c'];

echo json_encode(['status'=>'success','liked'=>$liked,'likeCount'=>$likeCount]);
?>
