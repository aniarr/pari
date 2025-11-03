<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $trainer_id = isset($data['trainer_id']) ? intval($data['trainer_id']) : 0;
    $course_id = isset($data['course_id']) ? intval($data['course_id']) : 0;
    $message = isset($data['message']) ? $data['message'] : '';
    $sender_type = isset($data['sender_type']) ? $data['sender_type'] : 'user';
    
    if (!$message || !$trainer_id || !$course_id) {
        die(json_encode(['success' => false, 'error' => 'Missing required fields']));
    }

    $stmt = $conn->prepare("INSERT INTO trainer_messages (user_id, trainer_id, course_id, message, sender_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $user_id, $trainer_id, $course_id, $message, $sender_type);
    
    $success = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $success]);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $trainer_id = isset($_GET['trainer_id']) ? intval($_GET['trainer_id']) : 0;
    
    $sql = "SELECT * FROM trainer_messages WHERE course_id = ? AND trainer_id = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $course_id, $trainer_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $messages = [];
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    $stmt->close();
    echo json_encode($messages);
}

$conn->close();
