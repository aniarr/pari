<?php
require 'config/db.php';
session_start();

// Optional: Auto-login test user (remove in production)
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'aswa';
}
$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Guest';

// Count saved splits
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM user_workouts uw 
    JOIN workout_splits ws ON uw.split_id = ws.id 
    WHERE uw.user_id = ?
");
$stmt->execute([$userId]);
$totalSplits = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RawFit Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .card-hover { transition: all 0.1s ease; }
    .card-hover:hover { transform: translateY(-12px) scale(1); box-shadow: 0 25px 50px -12px rgba(85, 51, 23, 0.25); }
    .gradient-text { background: linear-gradient(to right, #f97316, #ea580c); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .btn-glow { box-shadow: 0 0 20px rgba(251,146,60,0.4); }
    .btn-glow:hover { box-shadow: 0 0 30px rgba(251,146,60,0.6); }
    .pulse { animation: pulse 1s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen">

<!-- Floating Background Shapes -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
  <div class="absolute top-20 left-20 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl"></div>
  <div class="absolute bottom-20 right-20 w-80 h-80 bg-red-600/10 rounded-full blur-3xl"></div>
</div>

<div class="relative z-10 container mx-auto px-6 py-12 max-w-7xl">
<?php

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

// Get logged-in user's name
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

$stmt->close();
$conn->close();
?>

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M6.5 6.5h11v11h-11z"/>
                        <path d="M6.5 6.5L2 2"/>
                        <path d="M17.5 6.5L22 2"/>
                        <path d="M6.5 17.5L2 22"/>
                        <path d="M17.5 17.5L22 22"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-xl">RawFit</span>
            </div>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="home.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span>Home</span>
                </a>
                <a href="nutrition.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Nutrition</span>
                </a>
                <a href="trainer.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Trainers</span>
                </a>
                <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Gyms</span>
                </a>
                  <a href="workout_view.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Workout</span>
                    </a> 
            </div>

            <!-- User Info -->
            <div class="relative flex items-center space-x-4">
                <div class="hidden sm:block text-right">
                    <p class="text-white font-medium" id="userName"><?php echo htmlspecialchars($userName); ?></p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center cursor-pointer" id="profileButton">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>

                <!-- Dropdown Menu -->
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden z-50">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="nutrition.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                </svg>
                <span class="text-xs">Nutrition</span>
            </a>
            <a href="trainer.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="text-xs">Trainers</span>
            </a>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    updateNavigation();
});

// Highlight active link
function updateNavigation() {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

    // Reset all links
    [...navLinks, ...mobileNavLinks].forEach(link => {
        link.classList.remove('active', 'bg-orange-500', 'text-white', 'text-orange-500');
        link.classList.add('text-gray-300', 'hover:text-white', 'hover:bg-gray-800');
        if (link.classList.contains('mobile-nav-link')) {
            link.classList.add('text-gray-400');
            link.classList.remove('text-orange-500');
        }
    });

    // Highlight Home
    if (currentPage === 'index.php' || currentPage === 'home.php' || currentPage === '') {
        const homeLinks = document.querySelectorAll('a[href="home.php"], a[href="index.php"]');
        homeLinks.forEach(link => {
            if (link.classList.contains('mobile-nav-link')) {
                link.classList.add('active', 'text-orange-500');
                link.classList.remove('text-gray-400');
            } else {
                link.classList.add('active', 'bg-orange-500', 'text-white');
                link.classList.remove('text-gray-300');
            }
        });
    }

    // Highlight Workout (workout_view.php)
    else if (currentPage === 'workout_view.php') {
        const workoutLinks = document.querySelectorAll('a[href="workout_view.php"]');
        workoutLinks.forEach(link => {
            if (link.classList.contains('mobile-nav-link')) {
                link.classList.add('active', 'text-orange-500');
                link.classList.remove('text-gray-400');
            } else {
                link.classList.add('active', 'bg-orange-500', 'text-white');
                link.classList.remove('text-gray-300', 'hover:text-white', 'hover:bg-gray-800');
            }
        });
    }

    // Add similar blocks for other pages if needed (Nutrition, Trainers, Gyms)
}
</script>
<br><br>
  <!-- Header -->
  <header class="flex flex-col sm:flex-row justify-between items-center mb-16">
    <div class="text-center sm:text-left">
      <h1 class="text-5xl md:text-6xl font-bold gradient-text mb-2">RawFit</h1>
      <p class="text-xl text-gray-300">Welcome back, <span class="text-orange-400 font-semibold"><?= htmlspecialchars($username) ?></span>!</p>
    </div>
    <div class="mt-6 sm:mt-0 flex items-center gap-4">
      <div class="bg-gray-800/50 glass px-5 py-3 rounded-xl border border-gray-700">
        <p class="text-sm text-gray-400">Saved Splits</p>
        <p class="text-3xl font-bold text-orange-400"><?= $totalSplits ?></p>
      </div>
      
    </div>
  </header>

  <!-- Main Cards -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
    
    <!-- Card 1: View Saved Workouts -->
    <a href="my_splits.php" class="block group">
      <div class="glass p-10 rounded-3xl card-hover border border-gray-700 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-40 h-40 bg-gradient-to-br from-orange-500/20 to-red-600/20 rounded-full blur-3xl -translate-y-20 translate-x-20"></div>
        
        <div class="relative z-10">
          <div class="w-20 h-20 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center mb-6 shadow-xl group-hover:scale-110 transition">
            <i class="fas fa-list-ul text-3xl text-white"></i>
          </div>
          <h2 class="text-3xl font-bold mb-3">My Saved Workouts</h2>
          <p class="text-gray-300 mb-6 leading-relaxed">
            Browse all your saved workout splits. View, edit, or start training with your custom programs.
          </p>
          <div class="flex items-center gap-3 text-orange-400 font-semibold group-hover:text-orange-300 transition">
            <span>View Collection</span>
            <i class="fas fa-arrow-right group-hover:translate-x-2 transition"></i>
          </div>
        </div>
      </div>
    </a>

    <!-- Card 2: Create New Workout -->
    <a href="workout.php" class="block group">
      <div class="glass p-10 rounded-3xl card-hover border border-gray-700 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-40 h-40 bg-gradient-to-bl from-emerald-500/20 to-teal-600/20 rounded-full blur-3xl -translate-y-20 -translate-x-20"></div>
        
        <div class="relative z-10">
          <div class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center mb-6 shadow-xl group-hover:scale-110 transition pulse">
            <i class="fas fa-plus text-3xl text-white"></i>
          </div>
          <h2 class="text-3xl font-bold mb-3">Create New Split</h2>
          <p class="text-gray-300 mb-6 leading-relaxed">
            Build a new workout split from scratch. Drag & drop 800+ exercises, customize sets/reps, and save.
          </p>
          <div class="flex items-center gap-3 text-emerald-400 font-semibold group-hover:text-emerald-300 transition">
            <span>Start Building</span>
            <i class="fas fa-arrow-right group-hover:translate-x-2 transition"></i>
          </div>
        </div>
      </div>
    </a>

  </div>

 

  <!-- Footer -->
  <footer class="mt-20 text-center text-gray-500 text-sm">
    <p>© 2025 RawFit • Built with <i class="fas fa-heart text-red-500"></i> for lifters</p>
  </footer>
</div>

</body>
</html>