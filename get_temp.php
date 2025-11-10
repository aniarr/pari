<?php
// get_temp.php
require 'config/db.php';

header('Content-Type: application/json');

$user = 'aswa'; // temporary

$stmt = $pdo->prepare("
    SELECT exercise_id as id, exercise_name as name, target, bodyPart, equipment, gifUrl, sets, reps, rest
    FROM split_exercises_temp
    WHERE user = ?
    ORDER BY day_index, id
");
$stmt->execute([$user]);
$rows = $stmt->fetchAll();

$temp = array_fill(0, 7, []);
foreach ($rows as $r) {
    $temp[$r['day_index']][] = $r;
}

echo json_encode($temp);
?>