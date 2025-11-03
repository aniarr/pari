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

// Ensure daily_food_logs table exists
$conn->query("
CREATE TABLE IF NOT EXISTS daily_food_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_name VARCHAR(255) NOT NULL,
    serving_size DECIMAL(10,2) NOT NULL,
    serving_unit VARCHAR(50) NOT NULL,
    calories DECIMAL(10,2) NOT NULL,
    protein DECIMAL(10,2) NOT NULL,
    carbs DECIMAL(10,2) NOT NULL,
    fats DECIMAL(10,2) NOT NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(log_date),
    FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Get logged-in user name and nutrition profile
$user_id = $_SESSION['user_id'];
$sql = "SELECT name FROM register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$userName = "User"; // Default name
if ($row = $result->fetch_assoc()) {
    $userName = $row['name'];
}

// Fetch existing nutrition profile
$sql = "SELECT age, gender, weight, height, activity_level, goal FROM nutrition_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$nutritionData = [
    'age' => 25,
    'gender' => 'male',
    'weight' => 70,
    'height' => 175,
    'activity_level' => 'moderate',
    'goal' => 'maintain'
];
if ($row = $result->fetch_assoc()) {
    $nutritionData = [
        'age' => $row['age'],
        'gender' => $row['gender'],
        'weight' => $row['weight'],
        'height' => $row['height'],
        'activity_level' => $row['activity_level'],
        'goal' => $row['goal']
    ];
}

// Fetch today's food logs
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT id, food_name, serving_size, serving_unit, calories, protein, carbs, fats 
    FROM daily_food_logs 
    WHERE user_id = ? AND log_date = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$foodLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get daily totals
$stmt = $conn->prepare("
    SELECT 
        SUM(calories) as total_calories,
        SUM(protein) as total_protein,
        SUM(carbs) as total_carbs,
        SUM(fats) as total_fats
    FROM daily_food_logs 
    WHERE user_id = ? AND log_date = ?
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$dailyTotals = $stmt->get_result()->fetch_assoc();

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
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 font-inter">
    <!-- [Previous navigation code remains the same] -->

    <!-- Main Content -->
    <main class="pt-20 min-h-screen p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <!-- [Previous calculator sections remain the same] -->

            <!-- Food Log -->
            <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-gray-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-2xl font-bold text-white">Food Log</h2>
                        <input type="date" id="logDate" 
                            value="<?php echo date('Y-m-d'); ?>" 
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
                            <?php echo number_format($dailyTotals['total_calories'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Protein</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalProtein">
                            <?php echo number_format($dailyTotals['total_protein'] ?? 0); ?>g
                        </div>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Carbs</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalCarbs">
                            <?php echo number_format($dailyTotals['total_carbs'] ?? 0); ?>g
                        </div>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Fats</div>
                        <div class="text-xl font-bold text-white" id="dailyTotalFats">
                            <?php echo number_format($dailyTotals['total_fats'] ?? 0); ?>g
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
        </div>
    </main>

    <!-- Add Food Modal [Your existing modal code] -->

    <script>
        // Initialize date picker to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('logDate').value = today;
            loadFoodLogs(today);
        });

        // Load food logs when date changes
        document.getElementById('logDate').addEventListener('change', function() {
            loadFoodLogs(this.value);
        });

        // Load food logs for selected date
        function loadFoodLogs(date) {
            fetch(`save_daily_food_log.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    updateFoodLogUI(data.logs);
                    updateDailyTotals(data.totals);
                })
                .catch(error => console.error('Error:', error));
        }

        // Update food log UI
        function updateFoodLogUI(logs) {
            const container = document.getElementById('foodLogList');
            if (!logs || logs.length === 0) {
                container.innerHTML = '<div class="text-gray-400 text-center py-4">No food items logged for this date</div>';
                return;
            }

            container.innerHTML = logs.map(log => `
                <div class="bg-gray-700/30 rounded-lg p-4 flex justify-between items-center">
                    <div>
                        <div class="font-medium text-white">${log.food_name}</div>
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
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            `).join('');
        }

        // Update daily totals
        function updateDailyTotals(totals) {
            document.getElementById('dailyTotalCalories').textContent = Math.round(totals.total_calories || 0);
            document.getElementById('dailyTotalProtein').textContent = Math.round(totals.total_protein || 0) + 'g';
            document.getElementById('dailyTotalCarbs').textContent = Math.round(totals.total_carbs || 0) + 'g';
            document.getElementById('dailyTotalFats').textContent = Math.round(totals.total_fats || 0) + 'g';
            
            // Update progress bars and remaining values
            updateNutritionProgress(totals);
        }

        // Delete a log entry
        function deleteLogEntry(id) {
            if (!confirm('Delete this food log entry?')) return;
            
            fetch(`save_daily_food_log.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadFoodLogs(document.getElementById('logDate').value);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Save food log
        function saveFoodLog(foodData) {
            const date = document.getElementById('logDate').value;
            fetch('save_daily_food_log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ...foodData,
                    log_date: date
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadFoodLogs(date);
                    closeFoodModal();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Update nutrition progress
        function updateNutritionProgress(totals) {
            // Get target values
            const targetCalories = parseFloat(document.getElementById('targetCalories').textContent.replace(',', ''));
            const targetProtein = parseFloat(document.getElementById('targetProtein').textContent);
            const targetCarbs = parseFloat(document.getElementById('targetCarbs').textContent);
            const targetFats = parseFloat(document.getElementById('targetFats').textContent);
            
            // Calculate and update progress
            updateProgress('calories', totals.total_calories || 0, targetCalories);
            updateProgress('protein', totals.total_protein || 0, targetProtein);
            updateProgress('carbs', totals.total_carbs || 0, targetCarbs);
            updateProgress('fats', totals.total_fats || 0, targetFats);
        }

        // Your existing JavaScript functions (openFoodModal, closeFoodModal, etc.)
    </script>
</body>
</html>