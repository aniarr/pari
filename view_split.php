<?php
require 'config/db.php';

// -------------------------------------------------
// 1. GET THE SPLIT
// -------------------------------------------------
$id   = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM workout_splits WHERE id = ?");
$stmt->execute([$id]);
$split = $stmt->fetch();

if (!$split) {
    die("Split not found");
}

// -------------------------------------------------
// 2. GET ALL EXERCISES GROUPED BY DAY
// -------------------------------------------------
$stmt = $pdo->prepare("
    SELECT sd.day_name, se.*, e.*
    FROM split_days sd
    JOIN split_exercises se ON sd.id = se.split_day_id
    JOIN exercises e ON se.exercise_id = e.id
    WHERE sd.split_id = ?
    ORDER BY sd.day_number
");
$stmt->execute([$id]);
$exercises = $stmt->fetchAll();

$days = [];
foreach ($exercises as $ex) {
    $days[$ex['day_name']][] = $ex;
}

// -------------------------------------------------
// 3. SAVE LOGIC (POST request) – NO REDIRECT
// -------------------------------------------------
$savedMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_split'])) {
    session_start();
    if (empty($_SESSION['user_id'])) {
        $savedMessage = '<p class="text-red-400">Warning: You must be logged in to save a workout.</p>';
    } else {
        $userId = (int)$_SESSION['user_id'];

        // Check if already saved
        $chk = $pdo->prepare("SELECT id FROM user_workouts WHERE user_id = ? AND split_id = ?");
        $chk->execute([$userId, $id]);
        if ($chk->fetch()) {
            $savedMessage = '<p class="text-yellow-400">Check: This split is already in your workouts.</p>';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert personal copy record
                $ins = $pdo->prepare("
                    INSERT INTO user_workouts (user_id, split_id, name)
                    VALUES (?, ?, ?)
                ");
                $ins->execute([$userId, $id, $split['name'] . ' (copy)']);
                $userWorkoutId = $pdo->lastInsertId();

                // 2. Copy days
                foreach ($days as $dayName => $dayExercises) {
                    $origDay = $pdo->prepare("
                        SELECT id, day_number
                        FROM split_days
                        WHERE split_id = ? AND day_name = ?
                    ");
                    $origDay->execute([$id, $dayName]);
                    $orig = $origDay->fetch();

                    $insDay = $pdo->prepare("
                        INSERT INTO user_workout_days (user_workout_id, day_name, day_number)
                        VALUES (?, ?, ?)
                    ");
                    $insDay->execute([$userWorkoutId, $dayName, $orig['day_number'] ?? 0]);
                    $userDayId = $pdo->lastInsertId();

                    // 3. Copy exercises
                    foreach ($dayExercises as $ex) {
                        $insEx = $pdo->prepare("
                            INSERT INTO user_workout_exercises
                                (user_workout_day_id, exercise_id, sets, reps, rest_seconds)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insEx->execute([
                            $userDayId,
                            $ex['exercise_id'],
                            $ex['sets'],
                            $ex['reps'],
                            $ex['rest_seconds']
                        ]);
                    }
                }

                $pdo->commit();
                $savedMessage = '<p class="text-green-400">Check: Workout saved to your library!</p>';

            } catch (Exception $e) {
                $pdo->rollBack();
                $savedMessage = '<p class="text-red-400">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title><?= htmlspecialchars($split['name']) ?> - RawFit</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .glass { background: rgba(255,255,255,0.08); backdrop-filter: blur(12px); }
    .fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white min-h-screen p-8">
<div class="max-w-5xl mx-auto">
  <div class="glass p-8 rounded-2xl shadow-2xl border border-gray-700">

    <!-- Header + Save Button -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
      <div>
        <h1 class="text-4xl font-bold bg-gradient-to-r from-orange-400 to-red-600 bg-clip-text text-transparent">
          <?= htmlspecialchars($split['name']) ?>
        </h1>
        <p class="text-gray-400">
          Created on <?= date('M j, Y', strtotime($split['created_at'])) ?>
        </p>
      </div>

      <!-- Save Button (Stays on Page) -->
      <form method="post" class="mt-4 sm:mt-0">
        <button type="submit" name="save_split" value="1"
                class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-2 rounded-xl font-semibold
                       hover:from-emerald-600 hover:to-green-700 transition transform hover:scale-105 shadow-lg flex items-center gap-2">
          <i class="fas fa-save"></i> Save 
        </button>
      </form>
    </div>

    <!-- Feedback Message (Fades In) -->
    <?php if ($savedMessage): ?>
      <div class="mb-6 p-4 rounded-lg bg-gray-800/50 border border-gray-600 fade-in">
        <?= $savedMessage ?>
      </div>
    <?php endif; ?>

    <!-- Days -->
    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
      <?php if (!empty($days[$day])): ?>
        <div class="mb-10 pb-8 border-b border-gray-700">
          <h2 class="text-2xl font-bold text-orange-400 mb-6 flex items-center gap-2">
            <i class="fas fa-calendar-day"></i> <?= $day ?>
          </h2>

          <div class="space-y-5">
            <?php foreach ($days[$day] as $ex): ?>
              <div class="bg-gray-700/50 p-6 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shadow-lg border border-gray-600 hover:border-orange-500 transition">
                <div class="flex-1">
                  <h4 class="font-bold text-lg text-white">
                    <?= htmlspecialchars($ex['name']) ?>
                  </h4>
                  <p class="text-sm text-orange-300 mt-1">
                    <?= $ex['sets'] ?> sets × <?= $ex['reps'] ?> reps | Rest: <?= $ex['rest_seconds'] ?>s
                  </p>
                  <p class="text-xs text-gray-400 mt-2">
                    Target: <?= htmlspecialchars($ex['target']) ?>
                    • <?= htmlspecialchars($ex['bodyPart']) ?>
                    • <?= htmlspecialchars($ex['equipment']) ?>
                  </p>
                </div>

                <img src='<?= $ex['image_url']
                      ? "https://wger.de" . htmlspecialchars($ex['image_url'])
                      : "https://via.placeholder.com/100x100/1a1a1a/ffffff?text=No+Image" ?>'
                     alt='<?= htmlspecialchars($ex['name']) ?>'
                     class="w-24 h-24 object-cover rounded-lg shadow-md border border-gray-600"
                     onerror='this.src="https://via.placeholder.com/100x100/1a1a1a/ffffff?text=No+Image"'>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <!-- Back Buttons -->
    <div class="flex flex-col sm:flex-row gap-4 mt-8">
      <a href="my_splits.php"
         class="flex-1 bg-gradient-to-r from-purple-500 to-pink-600 text-white px-8 py-3 rounded-xl font-semibold text-center
                hover:from-pink-600 hover:to-purple-700 transition transform hover:scale-105 shadow-lg flex items-center justify-center gap-2">
        <i class="fas fa-list"></i> My Splits
      </a>
      <a href="workout.php"
         class="flex-1 bg-gradient-to-r from-orange-500 to-red-600 text-white px-8 py-3 rounded-xl font-semibold text-center
                hover:from-red-600 hover:to-orange-700 transition transform hover:scale-105 shadow-lg flex items-center justify-center gap-2">
        <i class="fas fa-plus"></i> New Split
      </a>
    </div>
  </div>
</div>
</body>
</html>