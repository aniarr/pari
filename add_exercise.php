<?php
session_start();
if (!isset($_SESSION['user_id'])) die(json_encode(['error'=>'unauth']));

$input = json_decode(file_get_contents('php://input'), true);
$exercise = $input['exercise'];
$day      = (int)$input['day'];
$sets     = (int)($input['sets'] ?? 3);
$reps     = $input['reps'] ?? '8-12';
$rest     = (int)($input['rest'] ?? 60);

$conn = new mysqli('localhost','root','','rawfit');
if ($conn->connect_error) die(json_encode(['error'=>'db']));

// Use a simple table `temp_split` (user_id, day, exercise_json)
$stmt = $conn->prepare(
    "INSERT INTO temp_split (user_id, day, exercise_json) VALUES (?,?,?) 
     ON DUPLICATE KEY UPDATE exercise_json = VALUES(exercise_json)"
);
$json = json_encode((object)[
    'id'       => $exercise['id'],
    'name'     => $exercise['name'],
    'target'   => $exercise['target'],
    'bodyPart' => $exercise['bodyPart'],
    'equipment'=> $exercise['equipment'],
    'gifUrl'   => $exercise['gifUrl'],
    'sets'     => $sets,
    'reps'     => $reps,
    'rest'     => $rest
]);
$stmt->bind_param('iis', $_SESSION['user_id'], $day, $json);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success'=>true]);
?>