<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];

// Get user name
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die("DB error");
$stmt = $conn->prepare("SELECT name FROM register WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userName = $stmt->get_result()->fetch_assoc()['name'] ?? "User";
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit - Nutrition Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #e2e8f0; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .nav-link { transition: all .2s; }
        .nav-link:hover { background: rgba(255,255,255,.1); }
        #profileDropdown { display: none; }
        #profileDropdown.show { display: block; }
        .text-gradient { background: linear-gradient(to right, #f97316, #ef4444); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="p-4 md:p-6 lg:p-8">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M6.5 6.5h11v11h-11z"/><path d="M6.5 6.5L2 2"/><path d="M17.5 6.5L22 2"/><path d="M6.5 17.5L2 22"/><path d="M17.5 17.5L22 22"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-xl">RawFit</span>
            </div>

            <!-- Desktop Nav -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="home.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                    <span>Home</span>
                </a>
                <a href="nutrition_updated.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-orange-500">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3 9 4-18 3 9h4"/></svg>
                    <span>Nutrition</span>
                </a>
                <a href="trainer.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Trainers</span>
                </a>
                <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Gyms</span>
                </a>
            </div>

            <!-- User Profile -->
            <div class="relative">
                <div class="flex items-center space-x-3 cursor-pointer" id="profileButton">
                    <div class="hidden sm:block text-right"><p class="text-white font-medium text-sm"><?= htmlspecialchars($userName) ?></p></div>
                    <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700">Logout</a>
                </div>
            </div>
        </div>

        <!-- Mobile Nav -->
        <div class="md:hidden flex justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="flex flex-col items-center space-y-1 text-orange-500"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg><span class="text-xs">Home</span></a>
            <a href="nutrition_updated.php" class="flex flex-col items-center space-y-1 text-orange-500"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3 9 4-18 3 9h4"/></svg><span class="text-xs">Nutrition</span></a>
            <a href="trainer.php" class="flex flex-col items-center space-y-1 text-gray-400"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="text-xs">Trainers</span></a>
            <a href="display_gym.php" class="flex flex-col items-center space-y-1 text-gray-400"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="text-xs">Gyms</span></a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto mt-24">

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">
            <span class="text-white">Track Your</span> 
            <span class="text-gradient"> Nutrition</span>
        </h1>
        <p class="text-gray-400">Log meals, track macros, and stay on target</p>
    </div>

    <!-- Food Log Card -->
    <div class="card p-6 md:p-8 mb-12 shadow-xl">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-4">
                <h2 class="text-2xl font-bold text-white">Food Log</h2>
                <input type="date" id="logDate" class="bg-gray-700/50 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
        </div>

        <!-- Daily Totals -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-700/30 rounded-lg p-4"><div class="text-sm text-gray-400">Calories</div><div class="text-xl font-bold text-white" id="dailyTotalCalories">0</div></div>
            <div class="bg-gray-700/30 rounded-lg p-4"><div class="text-sm text-gray-400">Protein</div><div class="text-xl font-bold text-white" id="dailyTotalProtein">0g</div></div>
            <div class="bg-gray-700/30 rounded-lg p-4"><div class="text-sm text-gray-400">Carbs</div><div class="text-xl font-bold text-white" id="dailyTotalCarbs">0g</div></div>
            <div class="bg-gray-700/30 rounded-lg p-4"><div class="text-sm text-gray-400">Fats</div><div class="text-xl font-bold text-white" id="dailyTotalFats">0g</div></div>
        </div>

        <!-- Log List -->
        <div id="foodLogList" class="space-y-4"></div>
    </div>

    <!-- Recent Logs -->
    <div class="card p-6 md:p-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-white">Recent Logs (Last 7 Days)</h2>
            <div class="flex items-center space-x-2">
                <input id="recentSearch" type="search" placeholder="Search food..." class="bg-gray-700/50 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                <button id="clearSearch" class="text-sm text-gray-300 hover:text-white">Clear</button>
            </div>
        </div>
        <div id="recentLogsList" class="space-y-4"></div>
    </div>
</div>

<script>
/* ==================== GLOBALS ==================== */
const logDateInput = document.getElementById('logDate');

/* ==================== LOAD LOGS ==================== */
function loadFoodLogs(date) {
    fetch(`save_daily_food_history.php?date=${date}`)
        .then(r => r.json())
        .then(data => {
            renderFoodLog(data.logs || []);
            renderTotals(data.totals || {});
        })
        .catch(err => {
            console.error('Load logs error:', err);
            renderFoodLog([]);
            renderTotals({});
        });
}

function renderFoodLog(logs) {
    const container = document.getElementById('foodLogList');
    if (!logs.length) {
        container.innerHTML = '<div class="text-gray-400 text-center py-4">No food logged for this date</div>';
        return;
    }
    container.innerHTML = logs.map(l => `
        <div class="bg-gray-700/30 rounded-lg p-4 flex justify-between items-center">
            <div>
                <div class="font-medium text-white">${escapeHtml(l.food_name)}</div>
                <div class="text-sm text-gray-400">${l.serving_size} ${l.serving_unit}</div>
            </div>
            <div class="text-right">
                <div class="text-white">${Math.round(l.calories)} cal</div>
                <div class="text-sm text-gray-400">P: ${Math.round(l.protein)}g | C: ${Math.round(l.carbs)}g | F: ${Math.round(l.fats)}g</div>
            </div>
            <button onclick="deleteLog(${l.id})" class="ml-4 text-red-400 hover:text-red-300">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
    `).join('');
}

function renderTotals(t) {
    document.getElementById('dailyTotalCalories').textContent = Math.round(t.total_calories || 0);
    document.getElementById('dailyTotalProtein').textContent = Math.round(t.total_protein || 0) + 'g';
    document.getElementById('dailyTotalCarbs').textContent   = Math.round(t.total_carbs || 0) + 'g';
    document.getElementById('dailyTotalFats').textContent    = Math.round(t.total_fats || 0) + 'g';
}

/* ==================== DELETE ==================== */
function deleteLog(id) {
    if (!confirm('Delete this entry?')) return;
    fetch('save_daily_food_history.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(() => loadFoodLogs(logDateInput.value));
}

/* ==================== DATE CHANGE ==================== */
logDateInput.addEventListener('change', function() {
    const d = this.value;
    const url = new URL(window.location);
    url.searchParams.set('date', d);
    window.history.replaceState({}, '', url);
    loadFoodLogs(d);
});

/* ==================== RECENT LOGS ==================== */
function loadRecentLogs() {
    fetch('save_daily_food_history.php?recent=1')
        .then(r => r.json())
        .then(data => renderRecent(data))
        .catch(err => {
            console.error('Recent logs error:', err);
            renderRecent([]);
        });
}

function renderRecent(rows) {
    const container = document.getElementById('recentLogsList');
    if (!rows.length) {
        container.innerHTML = '<div class="text-gray-400 text-center py-4">No recent logs</div>';
        return;
    }

    const groups = {};
    rows.forEach(r => {
        const d = r.log_date;
        if (!groups[d]) groups[d] = [];
        groups[d].push(r);
    });

    container.innerHTML = Object.keys(groups)
        .sort((a, b) => b.localeCompare(a))
        .map(date => `
            <div class="rounded-lg p-3 bg-gray-700/30">
                <div class="text-sm text-gray-300 font-medium mb-2">
                    ${new Date(date).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}
                </div>
                <div class="space-y-2">
                    ${groups[date].map(l => `
                        <div class="flex justify-between items-center p-3 bg-gray-800/20 rounded" data-food="${escapeHtml(l.food_name.toLowerCase())}">
                            <div>
                                <div class="font-medium text-white">${escapeHtml(l.food_name)}</div>
                                <div class="text-sm text-gray-400">${l.serving_size} ${l.serving_unit}</div>
                            </div>
                            <div class="text-right text-sm text-gray-200">
                                <div class="text-white">${Math.round(l.calories)} cal</div>
                                <div class="text-gray-400">P:${Math.round(l.protein)}g | C:${Math.round(l.carbs)}g | F:${Math.round(l.fats)}g</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
}

/* ==================== SEARCH ==================== */
document.getElementById('recentSearch').addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#recentLogsList [data-food]').forEach(el => {
        el.style.display = (q === '' || el.dataset.food.includes(q)) ? '' : 'none';
    });
    document.querySelectorAll('#recentLogsList > div').forEach(g => {
        const visible = g.querySelectorAll('[data-food]:not([style*="display: none"])').length;
        g.style.display = visible ? '' : 'none';
    });
});
document.getElementById('clearSearch').addEventListener('click', () => {
    const inp = document.getElementById('recentSearch');
    inp.value = ''; inp.dispatchEvent(new Event('input'));
});

/* ==================== INIT ==================== */
document.addEventListener('DOMContentLoaded', () => {
    const urlDate = new URLSearchParams(window.location.search).get('date');
    const today = new Date().toISOString().split('T')[0];
    const initDate = urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate) ? urlDate : today;
    logDateInput.value = initDate;
    loadFoodLogs(initDate);
    loadRecentLogs();
});

/* ==================== PROFILE DROPDOWN ==================== */
document.getElementById('profileButton').addEventListener('click', () => {
    document.getElementById('profileDropdown').classList.toggle('show');
});
document.addEventListener('click', e => {
    if (!document.getElementById('profileButton').contains(e.target)) {
        document.getElementById('profileDropdown').classList.remove('show');
    }
});

function escapeHtml(t) {
    const div = document.createElement('div');
    div.textContent = t;
    return div.innerHTML;
}
</script>

</body>
</html>