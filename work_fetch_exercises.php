<?php
header('Content-Type: application/json');
require '../config/db.php';

$query = $_GET['q'] ?? '';
if (strlen($query) < 2) {
    echo json_encode(['exercises' => []]);
    exit;
}

$apiKey = 'YOUR_RAPIDAPI_KEY'; // REPLACE WITH YOUR KEY
$url = "https://exercisedb.p.rapidapi.com/exercises";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-RapidAPI-Key: ' . $apiKey,
    'X-RapidAPI-Host: exercisedb.p.rapidapi.com'
]);

$response = curl_exec($ch);
curl_close($ch);

$allExercises = json_decode($response, true);

$results = array_filter($allExercises, function($ex) use ($query) {
    return 
        stripos($ex['name'], $query) !== false ||
        stripos($ex['target'], $query) !== false ||
        stripos($ex['bodyPart'], $query) !== false ||
        stripos($ex['equipment'], $query) !== false;
});

$limited = array_slice($results, 0, 30);

foreach ($limited as &$ex) {
    $stmt = $pdo->prepare("INSERT INTO exercises (id, name, bodyPart, equipment, target, gifUrl, instructions, secondaryMuscles)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE name=name");
    $instructions = is_array($ex['instructions']) ? implode('; ', $ex['instructions']) : $ex['instructions'];
    $stmt->execute([
        $ex['id'], $ex['name'], $ex['bodyPart'], $ex['equipment'], $ex['target'],
        $ex['gifUrl'], $instructions, json_encode($ex['secondaryMuscles'] ?? [])
    ]);
}

echo json_encode(['exercises' => array_values($limited)]);
?>