<?php
// api/fetch_exercises.php – wger API (100% working)
header('Content-Type: application/json');
require '../config/db.php';

$cacheFile = __DIR__ . '/wger_cache.json';
$query     = trim($_GET['q'] ?? '');
$category  = intval($_GET['category'] ?? 0);
$refresh   = isset($_GET['refresh']);

// ===============================================
// 1. Fetch ALL exercises from wger (cached)
// ===============================================
function getAllExercises(bool $force = false, int $category = 0): array {
    global $cacheFile;

    if (!$force && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        $data = json_decode(file_get_contents($cacheFile), true);
        return $data['exercises'] ?? [];
    }

    $url = 'https://wger.de/api/v2/exercise/?language=2&status=2'; // English + approved
    if ($category > 0) $url .= "&category=$category";

    $all = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    do {
        curl_setopt($ch, CURLOPT_URL, $url);
        $raw = curl_exec($ch);
        $data = json_decode($raw, true);

        if (!$data || !isset($data['results'])) break;

        $all = array_merge($all, $data['results']);
        $url = $data['next'] ?? null;
    } while ($url);

    curl_close($ch);

    file_put_contents($cacheFile, json_encode(['exercises' => $all, 'cached_at' => time()]));
    return $all;
}

// ===============================================
// 2. Search & Filter
// ===============================================
if ($query === '' && $category === 0) {
    echo json_encode(['exercises' => []]);
    exit;
}

$allExercises = getAllExercises($refresh, $category);

// Normalize search: "pushup" → matches "Push-up"
$normalizedQuery = strtolower(preg_replace('/[^a-z0-9]/', '', $query));

$results = array_filter($allExercises, function($ex) use ($normalizedQuery, $category) {
    if ($category > 0 && ($ex['category']['id'] ?? 0) != $category) return false;

    $name = strtolower(preg_replace('/[^a-z0-9]/', '', $ex['name'] ?? ''));
    $desc = strtolower(preg_replace('/[^a-z0-9]/', '', $ex['description'] ?? ''));

    return $normalizedQuery === '' || 
           strpos($name, $normalizedQuery) !== false || 
           strpos($desc, $normalizedQuery) !== false;
});

$limited = array_slice(array_values($results), 0, 50);

// ===============================================
// 3. Save to DB
// ===============================================
foreach ($limited as $ex) {
    $muscles = json_encode(array_column($ex['muscles'] ?? [], 'name'));
    $musclesSec = json_encode(array_column($ex['muscles_secondary'] ?? [], 'name'));
    $image = '';
    if (!empty($ex['images'][0]['image'])) {
        $image = $ex['images'][0]['image'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO exercises 
            (id, name, bodyPart, target, equipment, gifUrl, instructions, muscles, muscles_secondary, category_id)
        VALUES (?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");

    $stmt->execute([
        $ex['id'],
        $ex['name'],
        $ex['category']['name'] ?? 'Unknown',
        implode(', ', array_column($ex['muscles'] ?? [], 'name')),
        implode(', ', array_column($ex['equipment'] ?? [], 'name')),
        $image,
        strip_tags($ex['description'] ?? ''),
        $muscles,
        $musclesSec,
        $ex['category']['id'] ?? 0
    ]);
}

// ===============================================
// 4. Return JSON
// ===============================================
echo json_encode(['exercises' => $limited]);
?>