<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Handle GET request to fetch messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $course_id = intval($_GET['course_id']);
    $user_id = intval($_SESSION['user_id']);

    $sql = "SELECT m.*, 
            CASE 
                WHEN m.sender_id = ? THEN 'You'
                ELSE COALESCE(r.name, t.name, 'Unknown')
            END as sender_name
            FROM messages m
            LEFT JOIN register r ON m.sender_id = r.id
            LEFT JOIN trainer_details t ON m.sender_id = t.id
            WHERE m.course_id = ? 
            AND (m.sender_id = ? OR m.receiver_id = ?)
            ORDER BY m.created_at DESC
            LIMIT 50";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $course_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode($messages);
}

// Handle POST request to send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sender_id = intval($_SESSION['user_id']);
    $trainer_id = intval($data['trainer_id']);
    $course_id = intval($data['course_id']);
    $message = $data['message'];
    
    $sql = "INSERT INTO messages (sender_id, receiver_id, course_id, message) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $sender_id, $trainer_id, $course_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

$conn->close();
