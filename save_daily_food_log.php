<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Create tables if they don't exist
$conn->query("
CREATE TABLE IF NOT EXISTS daily_food_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_name VARCHAR(255) NOT NULL,
    serving_size DECIMAL(10,2) NOT NULL,
    serving_unit VARCHAR(50) NOT NULL,
    calories DECIMAL(10,2) NOT NULL,
    protein DECIMAL(10,2) NOT NULL,
    carbs DECIMAL(10,2) NOT NULL,
    fats DECIMAL(10,2) NOT NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(log_date),
    FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['food_name'], $data['serving_size'], $data['serving_unit'], 
               $data['calories'], $data['protein'], $data['carbs'], $data['fats'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Use current date in user's timezone if not specified
    $log_date = $data['log_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("
        INSERT INTO daily_food_logs 
        (user_id, food_name, serving_size, serving_unit, calories, protein, carbs, fats, log_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param('isssdddds', 
        $user_id, 
        $data['food_name'],
        $data['serving_size'],
        $data['serving_unit'],
        $data['calories'],
        $data['protein'],
        $data['carbs'],
        $data['fats'],
        $log_date
    );

    if ($stmt->execute()) {
        // Return updated totals for the day
        $stmt = $conn->prepare("
            SELECT 
                SUM(calories) as total_calories,
                SUM(protein) as total_protein,
                SUM(carbs) as total_carbs,
                SUM(fats) as total_fats
            FROM daily_food_logs 
            WHERE user_id = ? AND log_date = ?
        ");
        $stmt->bind_param('is', $user_id, $log_date);
        $stmt->execute();
        $totals = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Food logged successfully',
            'totals' => $totals
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save food log']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Log debugging information
    error_log("Loading food log for user_id: $user_id, date: $date");
    
    // Fetch logs for the specified date
    $stmt = $conn->prepare("
        SELECT id, food_name, serving_size, serving_unit, calories, protein, carbs, fats, created_at
        FROM daily_food_logs
        WHERE user_id = ? AND log_date = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param('is', $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    // Log the number of records found
    error_log("Found " . count($logs) . " food log entries");
    
    // Get daily totals
    $stmt = $conn->prepare("
        SELECT 
            SUM(calories) as total_calories,
            SUM(protein) as total_protein,
            SUM(carbs) as total_carbs,
            SUM(fats) as total_fats
        FROM daily_food_logs 
        WHERE user_id = ? AND log_date = ?
    ");
    
    $stmt->bind_param('is', $user_id, $date);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'logs' => $logs,
        'totals' => $totals,
        'date' => $date
    ]);
    exit;
}

// Handle DELETE request to remove a food log entry
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $log_id = $_GET['id'] ?? null;
    if (!$log_id) {
        http_response_code(400);
        echo json_encode(['error' => 'No log ID provided']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM daily_food_logs WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $log_id, $user_id);
    
    if ($stmt->execute()) {
        // Get the current date's updated totals
        $date = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT 
                SUM(calories) as total_calories,
                SUM(protein) as total_protein,
                SUM(carbs) as total_carbs,
                SUM(fats) as total_fats
            FROM daily_food_logs 
            WHERE user_id = ? AND log_date = ?
        ");
        $stmt->bind_param('is', $user_id, $date);
        $stmt->execute();
        $totals = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Log entry deleted',
            'totals' => $totals
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete log entry']);
    }
    exit;
}