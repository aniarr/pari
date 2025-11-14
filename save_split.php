<?php
// save_split.php
require 'config/db.php';
session_start();

header('Content-Type: application/json');

// Must be logged in
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || !isset($input['days'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $splitName = trim($input['name']);
    $daysData  = $input['days']; // array of days with exercises

    $pdo->beginTransaction();

    // 1. Insert split with user_id
    $stmt = $pdo->prepare("INSERT INTO workout_splits (user_id, name) VALUES (?, ?)");
    $stmt->execute([$userId, $splitName]);
    $splitId = $pdo->lastInsertId();

    if (!$splitId) {
        throw new Exception("Failed to create split");
    }

    // 2. Insert days + exercises
    $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    foreach ($daysData as $dayIdx => $exercises) {
        if (empty($exercises)) continue;

        $dayName   = $dayNames[$dayIdx] ?? 'Day ' . ($dayIdx + 1);
        $dayNumber = $dayIdx + 1;

        $stmt = $pdo->prepare("INSERT INTO split_days (split_id, day_name, day_number) VALUES (?,?,?)");
        $stmt->execute([$splitId, $dayName, $dayNumber]);
        $dayId = $pdo->lastInsertId();

        if (!$dayId) {
            throw new Exception("Failed to create day");
        }

        foreach ($exercises as $ex) {
            if (empty($ex['id'])) continue;

            $stmt = $pdo->prepare("
                INSERT INTO split_exercises 
                (split_day_id, exercise_id, sets, reps, rest_seconds)
                VALUES (?,?,?,?,?)
            ");
            $stmt->execute([
                $dayId,
                $ex['id'],
                $ex['sets'] ?? 3,
                $ex['reps'] ?? '8-12',
                $ex['rest'] ?? 60
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'splitId' => $splitId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Save split error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>