<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!isset($data['foodLog']) || !is_array($data['foodLog'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

/* ---------- DB ---------- */
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
$conn->set_charset('utf8mb4');

/* 1. Delete today’s rows – prepared statement */
$today = date('Y-m-d');
$stmtDel = $conn->prepare("DELETE FROM food_log WHERE user_id = ? AND log_date = ?");
$stmtDel->bind_param('is', $user_id, $today);
$stmtDel->execute();

if ($stmtDel->error) {
    echo json_encode(['success' => false, 'message' => 'Delete error: ' . $stmtDel->error]);
    $stmtDel->close(); $conn->close(); exit;
}
$stmtDel->close();

/* 2. Insert new rows – 7 parameters, all bound correctly */
$stmtIns = $conn->prepare(
    "INSERT INTO food_log
        (user_id, food_name, calories, protein, carbs, fats, log_date)
     VALUES (?,?,?,?,?,?,?)"
);
$stmtIns->bind_param(
    'isiiiis',   // i = int, s = string
    $user_id,
    $name,
    $cal,
    $prot,
    $carb,
    $fat,
    $today
);

foreach ($data['foodLog'] as $food) {
    $name = $food['name'] ?? '';
    $cal  = (int)($food['calories'] ?? 0);
    $prot = (int)($food['protein'] ?? 0);
    $carb = (int)($food['carbs'] ?? 0);
    $fat  = (int)($food['fats'] ?? 0);

    $stmtIns->execute();

    if ($stmtIns->error) {
        echo json_encode(['success' => false, 'message' => 'Insert error: ' . $stmtIns->error]);
        $stmtIns->close(); $conn->close(); exit;
    }
}
$stmtIns->close();
$conn->close();

echo json_encode(['success' => true]);
?>