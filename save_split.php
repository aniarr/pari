<?php
// save_split.php
require 'config/db.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || empty($input['days'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $splitName = $input['name'];
    $daysData  = $input['days'];   // array of days, each day = array of exercises

    $pdo->beginTransaction();

    // 1. Insert the split
    $stmt = $pdo->prepare("INSERT INTO workout_splits (name) VALUES (?)");
    $stmt->execute([$splitName]);
    $splitId = $pdo->lastInsertId();

    // 2. Insert each day + exercises
    foreach ($daysData as $dayIdx => $exercises) {
        $dayName   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$dayIdx];
        $dayNumber = $dayIdx + 1;

        // Insert day
        $stmt = $pdo->prepare("INSERT INTO split_days (split_id, day_name, day_number) VALUES (?,?,?)");
        $stmt->execute([$splitId, $dayName, $dayNumber]);
        $dayId = $pdo->lastInsertId();

        // Insert exercises
        foreach ($exercises as $ex) {
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>