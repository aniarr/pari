<?php
session_start();

// Only allow admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminlogin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <title>RawFit Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js (required for x-data, @click, etc.) -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Orbitron', 'sans-serif'],
                    },
                    colors: {
                        primary: '#F97316',
                        'primary-hover': '#FBA63C',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.4s ease-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: 0 }, '100%': { transform: 'translateY(0)', opacity: 1 } }
                    }
                }
            }
        }
    </script>
    <style>
        .glass {
            background: rgba(30, 35, 50, 0.7);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 30px rgba(249, 115, 22, 0.2);
        }
        .active-card {
            border: 2px solid #F97316;
            box-shadow: 0 0 20px rgba(249, 115, 22, 0.3);
        }
        .gradient-text {
            background: linear-gradient(to right, #F97316, #FBA63C);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-gray-900 to-black text-white min-h-screen">

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
                <a href="admin.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span>Home</span>
                </a>
                    <!-- Dropdown Menu -->
            <div class="relative" x-data="{ open: false }">
                <!-- Toggle Button -->
                <button @click="open = !open"
                        class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a3 3 0 0 0-3 3v1H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3V5a3 3 0 0 0-3-3z"/>
                    <path d="M9 5a3 3 0 0 1 6 0v1H9V5z"/>
                </svg>
                <span>Sections</span>
                <svg :class="{ 'rotate-180': open }" class="w-4 h-4 ml-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                </button>

                <!-- Dropdown Panel – hidden until `open` is true -->
                <div x-show="open"
                    @click.away="open = false"
                    x-transition
                    class="absolute left-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden z-50">
                <a href="admin_user.php"
                    class="flex items-center space-x-3 px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                    </svg>
                    <span>Users</span>
                </a>

                <a href="admin_trainers.php"
                    class="flex items-center space-x-3 px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Trainers</span>
                </a>

                <a href="admin_gyms.php"
                    class="flex items-center space-x-3 px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                    </svg>
                    <span>Gyms</span>
                </a>
                 <a href="admin_reels.php"
                    class="flex items-center space-x-3 px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                    </svg>
                    <span>Reels</span>
                </a>
                 <a href="admin_messages.php"
                    class="flex items-center space-x-3 px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                    </svg>
                    <span>Message</span>
                </a>
                </div>
            </div>
            </div>

           
                <!-- Dropdown Menu -->
           
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
            <a href="admin.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
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
                <span class="text-xs">users</span>
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

    [...navLinks, ...mobileNavLinks].forEach(link => {
        link.classList.remove('active', 'bg-orange-500', 'text-white', 'text-orange-500');
        link.classList.add('text-gray-300', 'hover:text-white', 'hover:bg-gray-800');
    });

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
}

// Profile dropdown toggle
const profileButton = document.getElementById('profileButton');
const profileDropdown = document.getElementById('profileDropdown');

if (profileButton && profileDropdown) {
    profileButton.addEventListener('click', function(e) {
        e.preventDefault();
        profileDropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', function(e) {
        if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
}
</script>
<!-- Main Content -->
<main class="pt-24 pb-12 px-6 max-w-7xl mx-auto">
    <!-- Title -->
    <div class="text-center mb-10 animate-fade-in">
        <h2 class="text-4xl font-bold gradient-text font-display mb-2">Admin Dashboard</h2>
        <p class="text-gray-400">Manage users, trainers, gyms, and more</p>
    </div>

    <!-- Quick Actions Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
     <!-- Users -->
        <a href="admin_user.php" class="card-hover group block">
            <div class="glass rounded-2xl p-6">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-4 shadow-lg">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Users</h3>
                <p class="text-gray-400 text-sm mb-4">Manage registered users, roles, and profiles</p>
                <span class="inline-flex items-center text-orange-500 font-semibold text-sm group-hover:text-orange-400 transition">
                    Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>
                </span>
            </div>
        </a>

       <!-- Trainers -->
            <a href="admin_trainers.php" class="card-hover group block">
                <div class="glass rounded-2xl p-6 <?= basename($_SERVER['PHP_SELF']) === 'admin_trainers.php' ? 'ring-2 ring-orange-500 ring-offset-2 ring-offset-gray-900' : '' ?>">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-4 shadow-lg">
                        <i class="fas fa-dumbbell text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Trainers</h3>
                    <p class="text-gray-400 text-sm mb-4">Approve, edit, and manage fitness trainers</p>
                    <span class="inline-flex items-center text-orange-500 font-semibold text-sm group-hover:text-orange-400 transition">
                        Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>
                    </span>
                </div>
            </a>

            <!-- Gyms -->
            <a href="admin_gyms.php" class="card-hover group block">
                <div class="glass rounded-2xl p-6 <?= basename($_SERVER['PHP_SELF']) === 'admin_gyms.php' ? 'ring-2 ring-orange-500 ring-offset-2 ring-offset-gray-900' : '' ?>">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mb-4 shadow-lg">
                        <i class="fas fa-building text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Gyms</h3>
                    <p class="text-gray-400 text-sm mb-4">Manage gym listings and owner details</p>
                    <span class="inline-flex items-center text-orange-500 font-semibold text-sm group-hover:text-orange-400 transition">
                        Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>
                    </span>
                </div>
            </a>

            <!-- Reels -->
            <a href="admin_reels.php" class="card-hover group block">
                <div class="glass rounded-2xl p-6 <?= basename($_SERVER['PHP_SELF']) === 'admin_reels.php' ? 'ring-2 ring-orange-500 ring-offset-2 ring-offset-gray-900' : '' ?>">
                    <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl flex items-center justify-center mb-4 shadow-lg">
                        <i class="fas fa-video text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Reels</h3>
                    <p class="text-gray-400 text-sm mb-4">Review and approve short fitness videos</p>
                    <span class="inline-flex items-center text-orange-500 font-semibold text-sm group-hover:text-orange-400 transition">
                        Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>
                    </span>
                </div>
            </a>

            <!-- Admins -->
            <a href="admin_admins.php" class="card-hover group block">
                <div class="glass rounded-2xl p-6 <?= basename($_SERVER['PHP_SELF']) === 'admin_admins.php' ? 'ring-2 ring-orange-500 ring-offset-2 ring-offset-gray-900' : '' ?>">
                    <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center mb-4 shadow-lg">
                        <i class="fas fa-user-shield text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Admins</h3>
                    <p class="text-gray-400 text-sm mb-4">Manage admin accounts and permissions</p>
                    <span class="inline-flex items-center text-orange-500 font-semibold text-sm group-hover:text-orange-400 transition">
                        Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>
                    </span>
                </div>
            </a>

            <!-- Messages -->
            <a href="admin_messages.php" class="card-hover group block">
                <div class="glass rounded-2xl p-6 <?= basename($_SERVER['PHP_SELF']) === 'admin_messages.php' ? 'ring-2 ring-orange-500 ring-offset-2 ring-offset-gray-900' : '' ?>">
                    <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center mb-4 shadow-lg">
                        <i class="fas fa-envelope text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Messages</h3>
                    <p class="text-gray-400 text-sm mb-4">View and respond to user inquiries</p>
                    <span class="inline-flex items-center text-orange-500 font-semibold text-sm group-hover:text-orange-400 transition">
                        Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>
                    </span>
                </div>
            </a>
    </div>

   
</main>

<script>
const sections = ['users', 'trainers', 'gyms', 'reels', 'admins', 'messages'];
const baseUrl = 'admin.php?section=';

function setActiveCard(section) {
    document.querySelectorAll('[data-section]').forEach(card => {
        const div = card.querySelector('div');
        const span = card.querySelector('span');
        if (card.dataset.section === section) {
            div.classList.add('active-card');
            span.innerHTML = 'Active';
        } else {
            div.classList.remove('active-card');
            span.innerHTML = 'Open <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition"></i>';
        }
    });
}

function loadSection(section) {
    const container = document.getElementById('content');
    container.innerHTML = `
        <div class="flex justify-center items-center h-48">
            <div class="animate-spin w-10 h-10 border-4 border-orange-500 border-t-transparent rounded-full"></div>
        </div>`;

    fetch(baseUrl + section)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
            setActiveCard(section);
            history.replaceState(null, null, '#' + section);
        })
        .catch(() => {
            container.innerHTML = '<p class="text-red-400 text-center">Failed to load section.</p>';
        });
}

// Click handler
document.querySelectorAll('[data-section]').forEach(card => {
    card.addEventListener('click', e => {
        e.preventDefault();
        const section = card.dataset.section;
        loadSection(section);
    });
});

// On load
const hash = location.hash.slice(1);
const validHash = sections.includes(hash) ? hash : 'users';
loadSection(validHash);
</script>

</body>
</html>