<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$today   = date('Y-m-d');

$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
$conn->set_charset('utf8mb4');

$sql = "SELECT food_name AS name,
               calories,
               protein,
               carbs,
               fats
        FROM food_log
        WHERE user_id = ? AND log_date = ?
        ORDER BY id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $user_id, $today);
$stmt->execute();

if ($stmt->error) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $stmt->error]);
    $stmt->close(); $conn->close(); exit;
}

$res = $stmt->get_result();
$log = [];
while ($row = $res->fetch_assoc()) {
    $log[] = [
        'name'     => $row['name'],
        'calories' => (int)$row['calories'],
        'protein'  => (int)$row['protein'],
        'carbs'    => (int)$row['carbs'],
        'fats'     => (int)$row['fats']
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'foodLog' => $log]);
?>