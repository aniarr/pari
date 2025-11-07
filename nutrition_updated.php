<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get logged-in user name
$user_id = $_SESSION['user_id'];
$sql = "SELECT name FROM register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userName = $result->fetch_assoc()['name'] ?? "User";

// Fetch nutrition profile
$sql = "SELECT age, gender, weight, height, activity_level, goal FROM nutrition_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$nutritionData = [
    'age' => 25, 'gender' => 'male', 'weight' => 70,
    'height' => 175, 'activity_level' => 'moderate', 'goal' => 'maintain'
];
if ($row = $result->fetch_assoc()) {
    $nutritionData = $row;
}

// === SELECTED DATE (from URL or today) ===
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// === FETCH ALL FOOD LOGS FOR SELECTED DATE ===
$stmt = $conn->prepare("
    SELECT id, food_name, serving_size, serving_unit, calories, protein, carbs, fats 
    FROM daily_food_logs 
    WHERE user_id = ? AND log_date = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("is", $user_id, $selectedDate);
$stmt->execute();
$foodLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === DAILY TOTALS FOR SELECTED DATE ===
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(calories), 0) AS total_calories,
        COALESCE(SUM(protein), 0)  AS total_protein,
        COALESCE(SUM(carbs), 0)    AS total_carbs,
        COALESCE(SUM(fats), 0)     AS total_fats
    FROM daily_food_logs 
    WHERE user_id = ? AND log_date = ?
");
$stmt->bind_param("is", $user_id, $selectedDate);
$stmt->execute();
$dailyTotals = $stmt->get_result()->fetch_assoc();

// === RECENT LOGS (Last 7 days) ===
$stmt = $conn->prepare("
    SELECT id, food_name, serving_size, serving_unit, calories, protein, carbs, fats, log_date, created_at
    FROM daily_food_logs
    WHERE user_id = ? 
      AND log_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
    ORDER BY log_date DESC, created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$recentByDate = [];
foreach ($recentRows as $r) {
    $d = $r['log_date'];
    $recentByDate[$d][] = $r;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit - Nutrition Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'inter': ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 font-inter">

    <!-- Main Content -->
    <main class="pt-20 min-h-screen p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">

            <!-- Food Log -->
            <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-gray-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-2xl font-bold text-white">Food Log</h2>
                        <input type="date" id="logDate" 
                               value="<?php echo $selectedDate; ?>" 
                               class="bg-gray-700/50 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <button onclick="openFoodModal()" 
                            class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 flex items-center space-x-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        <span>Add Food</span>
                    </button>
                </div>

                <!-- Daily Summary -->
                <div class="mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Calories</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalCalories">
                            <?php echo number_format($dailyTotals['total_calories']); ?>
                        </div>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Protein</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalProtein">
                            <?php echo number_format($dailyTotals['total_protein']); ?>g
                        </div>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Carbs</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalCarbs">
                            <?php echo number_format($dailyTotals['total_carbs']); ?>g
                        </div>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Fats</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalFats">
                            <?php echo number_format($dailyTotals['total_fats']); ?>g
                        </div>
                    </div>
                </div>

                <!-- Food Log List -->
                <div class="space-y-4" id="foodLogList">
                    <?php if (empty($foodLogs)): ?>
                        <div class="text-gray-400 text-center py-4">No food items logged for this date</div>
                    <?php else: ?>
                        <?php foreach ($foodLogs as $log): ?>
                        <div class="bg-gray-700/30 rounded-lg p-4 flex justify-between items-center">
                            <div>
                                <div class="font-medium text-white"><?php echo htmlspecialchars($log['food_name']); ?></div>
                                <div class="text-sm text-gray-400">
                                    <?php echo htmlspecialchars($log['serving_size'] . ' ' . $log['serving_unit']); ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-white"><?php echo number_format($log['calories']); ?> cal</div>
                                <div class="text-sm text-gray-400">
                                    P: <?php echo number_format($log['protein']); ?>g | 
                                    C: <?php echo number_format($log['carbs']); ?>g | 
                                    F: <?php echo number_format($log['fats']); ?>g
                                </div>
                            </div>
                            <button onclick="deleteLogEntry(<?php echo $log['id']; ?>)" 
                                    class="ml-4 text-red-400 hover:text-red-300">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
                             <a href="nutrition.php" 
                            class="fixed bottom-6 left-6 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white p-4 rounded-full shadow-lg transition transform hover:scale-105 z-50"
                            title="Back to Home">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5.25a.75.75 0 01-.75-.75V15a.75.75 0 00-.75-.75H9.75A.75.75 0 009 15v6.25a.75.75 0 01-.75.75H3a1 1 0 01-1-1V9.75z" />
                            </svg>
                            </a>
            <!-- Recent Logs (Last 7 days) -->
            <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-gray-700 mt-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-white">Recent Logs (Last 7 days)</h2>
                    <div class="flex items-center space-x-2">
                        <input id="recentSearch" type="search" placeholder="Search food items..." class="bg-gray-700/50 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <button id="clearSearch" class="text-sm text-gray-300 hover:text-white">Clear</button>
                    </div>
                </div>

                <div id="recentLogsList" class="space-y-4">
                    <?php if (empty($recentByDate)): ?>
                        <div class="text-gray-400 text-center py-4">No recent logs found</div>
                    <?php else: ?>
                        <?php foreach ($recentByDate as $date => $logsForDate): ?>
                            <div class="rounded-lg p-3 bg-gray-700/30" data-log-date="<?php echo $date; ?>">
                                <div class="text-sm text-gray-300 font-medium mb-2">
                                    <?php echo date('l, F j, Y', strtotime($date)); ?>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($logsForDate as $log): ?>
                                        <div class="recent-log-item flex justify-between items-center p-3 bg-gray-800/20 rounded" 
                                             data-food-name="<?php echo htmlspecialchars(strtolower($log['food_name'])); ?>">
                                            <div>
                                                <div class="font-medium text-white"><?php echo htmlspecialchars($log['food_name']); ?></div>
                                                <div class="text-sm text-gray-400">
                                                    <?php echo htmlspecialchars($log['serving_size'] . ' ' . $log['serving_unit']); ?>
                                                </div>
                                            </div>
                                            <div class="text-right text-sm text-gray-200">
                                                <div class="text-white"><?php echo number_format($log['calories']); ?> cal</div>
                                                <div class="text-gray-400">
                                                    P: <?php echo number_format($log['protein']); ?>g | 
                                                    C: <?php echo number_format($log['carbs']); ?>g | 
                                                    F: <?php echo number_format($log['fats']); ?>g
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Food Modal (your existing modal code here) -->

    <script>
        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            const urlDate = new URLSearchParams(window.location.search).get('date');
            const today = new Date().toISOString().split('T')[0];
            const initialDate = urlDate || today;
            document.getElementById('logDate').value = initialDate;
            loadFoodLogs(initialDate);
        });

        // Reload logs when date changes
        document.getElementById('logDate').addEventListener('change', function() {
            const date = this.value;
            const url = new URL(window.location);
            url.searchParams.set('date', date);
            window.history.replaceState({}, '', url);
            loadFoodLogs(date);
        });

        // Load logs via AJAX
        function loadFoodLogs(date) {
            fetch(`save_daily_food_log.php?date=${date}`)
                .then(r => r.json())
                .then(data => {
                    updateFoodLogUI(data.logs || []);
                    updateDailyTotals(data.totals || {});
                })
                .catch(err => console.error('Load error:', err));
        }

        // Update UI
        function updateFoodLogUI(logs) {
            const container = document.getElementById('foodLogList');
            if (!logs.length) {
                container.innerHTML = '<div class="text-gray-400 text-center py-4">No food items logged for this date</div>';
                return;
            }
            container.innerHTML = logs.map(log => `
                <div class="bg-gray-700/30 rounded-lg p-4 flex justify-between items-center">
                    <div>
                        <div class="font-medium text-white">${escapeHtml(log.food_name)}</div>
                        <div class="text-sm text-gray-400">${log.serving_size} ${log.serving_unit}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-white">${Math.round(log.calories)} cal</div>
                        <div class="text-sm text-gray-400">
                            P: ${Math.round(log.protein)}g | C: ${Math.round(log.carbs)}g | F: ${Math.round(log.fats)}g
                        </div>
                    </div>
                    <button onclick="deleteLogEntry(${log.id})" class="ml-4 text-red-400 hover:text-red-300">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            `).join('');
        }

        function updateDailyTotals(totals) {
            document.getElementById('dailyTotalCalories').textContent = Math.round(totals.total_calories || 0);
            document.getElementById('dailyTotalProtein').textContent = Math.round(totals.total_protein || 0) + 'g';
            document.getElementById('dailyTotalCarbs').textContent = Math.round(totals.total_carbs || 0) + 'g';
            document.getElementById('dailyTotalFats').textContent = Math.round(totals.total_fats || 0) + 'g';
            updateNutritionProgress(totals);
        }

        // Delete entry
        function deleteLogEntry(id) {
            if (!confirm('Delete this entry?')) return;
            fetch(`save_daily_food_log.php?id=${id}`, { method: 'DELETE' })
                .then(r => r.json())
                .then(data => { if (data.success) loadFoodLogs(document.getElementById('logDate').value); })
                .catch(console.error);
        }

        // Save new food
        function saveFoodLog(foodData) {
            const date = document.getElementById('logDate').value;
            fetch('save_daily_food_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...foodData, log_date: date })
            })
            .then(r => r.json())
            .then(data => { if (data.success) { loadFoodLogs(date); closeFoodModal(); } })
            .catch(console.error);
        }

        // Progress update (stub – replace with your real function)
        function updateNutritionProgress(totals) {
            // Your existing progress bar logic
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Recent logs search
        document.getElementById('recentSearch')?.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#recentLogsList .recent-log-item').forEach(item => {
                const name = item.getAttribute('data-food-name') || '';
                item.style.display = (q === '' || name.includes(q)) ? '' : 'none';
            });
            document.querySelectorAll('#recentLogsList > div').forEach(group => {
                const visible = group.querySelectorAll('.recent-log-item:not([style*="display: none"])').length > 0;
                group.style.display = visible ? '' : 'none';
            });
        });

        document.getElementById('clearSearch')?.addEventListener('click', function() {
            const input = document.getElementById('recentSearch');
            input.value = '';
            input.dispatchEvent(new Event('input'));
        });
    </script>
</body>
</html>