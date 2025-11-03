<?php
// carryover_daily_logs.php
// Run this via CRON at 00:01 every day

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    error_log("Carryover failed: DB connection error");
    exit;
}

// Get yesterday and today
$yesterday = date('Y-m-d', strtotime('-1 day'));
$today     = date('Y-m-d');

// Get all users who logged food today
$stmt = $conn->prepare("SELECT DISTINCT user_id FROM daily_food_logs WHERE log_date = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$user_ids = [];
while ($row = $result->fetch_assoc()) {
    $user_ids[] = $row['user_id'];
}

if (empty($user_ids)) {
    echo "No logs to carry over.\n";
    exit;
}

$inserted = 0;
foreach ($user_ids as $user_id) {
    // Get today's logs
    $stmt = $conn->prepare("
        SELECT food_name, serving_size, serving_unit, calories, protein, carbs, fats
        FROM daily_food_logs
        WHERE user_id = ? AND log_date = ?
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Insert into yesterday
    $insert = $conn->prepare("
        INSERT INTO daily_food_logs 
        (user_id, food_name, serving_size, serving_unit, calories, protein, carbs, fats, log_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            serving_size = VALUES(serving_size),
            calories = VALUES(calories),
            protein = VALUES(protein),
            carbs = VALUES(carbs),
            fats = VALUES(fats)
    ");

    foreach ($logs as $log) {
        $insert->bind_param(
            "isdsdddds",
            $user_id,
            $log['food_name'],
            $log['serving_size'],
            $log['serving_unit'],
            $log['calories'],
            $log['protein'],
            $log['carbs'],
            $log['fats'],
            $yesterday
        );
        if ($insert->execute()) $inserted++;
    }
}

echo "Carryover complete: $inserted entries copied from $today to $yesterday.\n";
$conn->close();
?>