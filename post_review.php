<?php
header('Content-Type: application/json');
session_start();

// Basic validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$gym_id = intval($_POST['gym_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($gym_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection error']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? ($_POST['user_name'] ?? null);

$stmt = $conn->prepare('INSERT INTO gym_reviews (gym_id, user_id, user_name, rating, comment) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('iisis', $gym_id, $user_id, $user_name, $rating, $comment);
$ok = $stmt->execute();
if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $stmt->error]);
    exit;
}
$stmt->close();

// return updated average and recent reviews
$resAvg = $conn->query("SELECT AVG(rating) AS avg_rating, COUNT(*) AS count FROM gym_reviews WHERE gym_id = $gym_id");
$avg = $resAvg->fetch_assoc();
$avgRating = round(floatval($avg['avg_rating']), 2);
$count = intval($avg['count']);

$resList = $conn->query("SELECT user_name, rating, comment, created_at FROM gym_reviews WHERE gym_id = $gym_id ORDER BY created_at DESC LIMIT 10");
$reviews = [];
while ($r = $resList->fetch_assoc()) $reviews[] = $r;

echo json_encode(['success' => true, 'avg' => $avgRating, 'count' => $count, 'reviews' => $reviews]);
$conn->close();
