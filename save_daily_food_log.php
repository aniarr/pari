<?php
session_start();
header('Content-Type: application/json');

// === 1. AUTHENTICATION ===
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Please log in.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// === 2. DATABASE CONNECTION ===
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Optional: Ensure table exists (safe to run on every request)
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

// === 3. HELPER: Get logs + totals for a date ===
function getLogsAndTotals($conn, $user_id, $date) {
    // Fetch logs
    $stmt = $conn->prepare("
        SELECT 
            id, food_name, serving_size, serving_unit,
            calories, protein, carbs, fats,
            created_at
        FROM daily_food_logs
        WHERE user_id = ? AND log_date = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch totals
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(calories), 0) AS total_calories,
            COALESCE(SUM(protein), 0)  AS total_protein,
            COALESCE(SUM(carbs), 0)    AS total_carbs,
            COALESCE(SUM(fats), 0)     AS total_fats
        FROM daily_food_logs
        WHERE user_id = ? AND log_date = ?
    ");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();

    return ['logs' => $logs, 'totals' => $totals];
}

// === 4. ROUTE: GET (Fetch logs + totals) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');

    // Optional: validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }

    $data = getLogsAndTotals($conn, $user_id, $date);

    echo json_encode([
        'success' => true,
        'date'    => $date,
        'logs'    => $data['logs'],
        'totals'  => $data['totals']
    ]);
    exit;
}

// === 5. ROUTE: POST (Add new food log) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['food_name', 'serving_size', 'serving_unit', 'calories', 'protein', 'carbs', 'fats'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            exit;
        }
    }

    $log_date = $input['log_date'] ?? date('Y-m-d');

    $stmt = $conn->prepare("
        INSERT INTO daily_food_logs 
        (user_id, food_name, serving_size, serving_unit, calories, protein, carbs, fats, log_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isdsdddds",
        $user_id,
        $input['food_name'],
        $input['serving_size'],
        $input['serving_unit'],
        $input['calories'],
        $input['protein'],
        $input['carbs'],
        $input['fats'],
        $log_date
    );

    if ($stmt->execute()) {
        $data = getLogsAndTotals($conn, $user_id, $log_date);

        echo json_encode([
            'success' => true,
            'message' => 'Food logged successfully',
            'logs'    => $data['logs'],
            'totals'  => $data['totals']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save food log']);
    }
    exit;
}

// === 6. ROUTE: DELETE (Remove log entry) ===
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $log_id = $_GET['id'] ?? null;

    if (!$log_id || !is_numeric($log_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing log ID']);
        exit;
    }

    // Get the log_date before deleting
    $stmt = $conn->prepare("SELECT log_date FROM daily_food_logs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $log_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Log entry not found']);
        exit;
    }

    $log_date = $row['log_date'];

    // Now delete
    $stmt = $conn->prepare("DELETE FROM daily_food_logs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $log_id, $user_id);

    if ($stmt->execute()) {
        $data = getLogsAndTotals($conn, $user_id, $log_date);

        echo json_encode([
            'success' => true,
            'message' => 'Log entry deleted',
            'logs'    => $data['logs'],
            'totals'  => $data['totals']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete log entry']);
    }
    exit;
}

// === 7. DEFAULT: Method not allowed ===
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;
?>