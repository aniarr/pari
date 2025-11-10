<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
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

$userName = "User"; // Default name
if ($row = $result->fetch_assoc()) {
    $userName = $row['name'];
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit Dashboard</title>
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
                    <a href="home.php" class="nav-link active flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white transition-colors">
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
                <a href="#dashboard" class="mobile-nav-link active flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span class="text-xs">Dashboard</span>
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

    <!-- Main Content -->
    <br><br>
    <main class="pt-20 min-h-screen p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
                    Welcome back, <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500" id="welcomeName"><?php echo htmlspecialchars(explode(" ", $userName)[0]); ?></span>!
                </h1>
                <p class="text-gray-400 text-lg">Ready to crush your fitness goals today?</p>
            </div>

            <!-- Stats removed per request -->

            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <div onclick="window.location.href='nutrition.php'" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:bg-gray-800/70 hover:scale-105 transition-all duration-300 cursor-pointer group">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                                <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                                <path d="M12 18h.01"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Nutrition Calculator</h3>
                        <p class="text-gray-400 leading-relaxed">Track your daily calories, macros, and nutritional goals with our advanced calculator</p>
                        <div class="mt-6 flex items-center text-orange-500 group-hover:text-orange-400 transition-colors">
                            <span class="font-medium">Get Started</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-2 group-hover:translate-x-1 transition-transform">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                    <div onclick="window.location.href='trainer.php'" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:bg-gray-800/70 hover:scale-105 transition-all duration-300 cursor-pointer group">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-orange-500 to-red-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Select Trainer</h3>
                        <p class="text-gray-400 leading-relaxed">Browse and book sessions with our expert trainers specialized in various fitness disciplines</p>
                        <div class="mt-6 flex items-center text-orange-500 group-hover:text-orange-400 transition-colors">
                            <span class="font-medium">Browse Trainers</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-2 group-hover:translate-x-1 transition-transform">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                     <div onclick="window.location.href='feed.php'" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:bg-gray-800/70 hover:scale-105 transition-all duration-300 cursor-pointer group">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-pink-500 to-red-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Watch Bitz</h3>
                        <p class="text-gray-400 leading-relaxed">Explore and enjoy Bitz from the RawFit community</p>
                        <div class="mt-6 flex items-center text-orange-500 group-hover:text-orange-400 transition-colors">
                            <span class="font-medium">Browse Bitz</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-2 group-hover:translate-x-1 transition-transform">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                     
                    <div onclick="window.location.href='display_gym.php'" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:bg-gray-800/70 hover:scale-105 transition-all duration-300 cursor-pointer group">
                            <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-red-500 to-pink-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                                    <path d="M3 12h4l1.5-6 3 12 3-9 1.5 6h4"/>
                                    <path d="M8 22v-6m8 6v-6"/>
                                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-white mb-2">Find Gyms Nearby</h3>
                            <p class="text-gray-400 leading-relaxed">Discover top-rated gyms, view facilities, check timings, and book your workout sessions instantly</p>
                            <div class="mt-6 flex items-center text-orange-500 group-hover:text-orange-400 transition-colors">
                                <span class="font-medium">Explore Now</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-2 group-hover:translate-x-1 transition-transform">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    
                   <div onclick="window.location.href='upload_reel.php'" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:bg-gray-800/70 hover:scale-105 transition-all duration-300 cursor-pointer group">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-purple-500 to-indigo-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                <polyline points="22 6 12 2 2 6"/>
                                <line x1="2" y1="6" x2="2" y2="14"/>
                                <line x1="22" y1="6" x2="22" y2="14"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Upload Bitz Video</h3>
                        <p class="text-gray-400 leading-relaxed">Share your fitness journey by uploading a Bitz video</p>
                        <div class="mt-6 flex items-center text-orange-500 group-hover:text-orange-400 transition-colors">
                            <span class="font-medium">Get Started</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-2 group-hover:translate-x-1 transition-transform">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div> 
                </div>
            </div>

            <!-- Recent Activity removed per request -->
        </div>
    </main>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dashboard
        // Minimal dashboard initialization
        initializeDashboard();
        updateNavigation();
    });

    function initializeDashboard() {
        // Set welcome name only
        const userNameElement = document.getElementById('userName');
        const welcomeNameElement = document.getElementById('welcomeName');
        const name = '<?php echo htmlspecialchars($userName); ?>';
        if (userNameElement) userNameElement.textContent = name;
        if (welcomeNameElement) welcomeNameElement.textContent = name.split(' ')[0];
    }
    

    function updateNavigation() {
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-link');
        const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
        
        // Remove active class from all links
        [...navLinks, ...mobileNavLinks].forEach(link => {
            link.classList.remove('active', 'bg-orange-500', 'text-white', 'text-orange-500');
            link.classList.add('text-gray-300', 'hover:text-white', 'hover:bg-gray-800');
        });
        
        // Add active class to Home link when on home/index (show orange around Home)
        if (currentPage === 'index.php' || currentPage === 'home.php' || currentPage === '') {
            const homeLinks = document.querySelectorAll('a[href="home.php"], a[href="index.php"], a[href="#dashboard"]');
            homeLinks.forEach(link => {
                if (link.classList.contains('mobile-nav-link')) {
                    link.classList.add('active', 'text-orange-500');
                    link.classList.remove('text-gray-400');
                } else {
                    link.classList.add('active', 'bg-orange-500', 'text-white');
                    link.classList.remove('text-gray-300', 'hover:text-white', 'hover:bg-gray-800');
                }
            });
        }
    }

    // Profile button functionality
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', function(e) {
            e.preventDefault();
            profileDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
    </script>
</body>
</html>