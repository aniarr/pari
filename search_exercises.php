<?php
// search_exercises.php
require 'config/db.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$q = trim($q);

if ($q === '') {
    echo json_encode(['exercises' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, name, bodyPart, target, equipment, gifUrl, instructions
    FROM exercises
    WHERE name LIKE ? OR target LIKE ? OR bodyPart LIKE ?
    ORDER BY name
    LIMIT 100
");
$like = "%$q%";
$stmt->execute([$like, $like, $like]);

echo json_encode(['exercises' => $stmt->fetchAll()]);
?>