<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Only allow admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminlogin.php?action=login");
    exit;
}

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: adminlogin.php?action=login");
    exit;
}

// DB Connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// DELETE Gym + Image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gym'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }
    $gym_id = intval($_POST['delete_gym']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT gym_image FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $gym = $res->fetch_assoc();
        $stmt->close();

        if (!empty($gym['gym_image']) && file_exists(__DIR__ . '/' . $gym['gym_image'])) {
            @unlink(__DIR__ . '/' . $gym['gym_image']);
        }

        $stmt = $conn->prepare("DELETE FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['msg'] = "Gym deleted successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Delete failed.";
    }
    header("Location: admin_gyms.php");
    exit;
}

// UPDATE Gym
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gym'])) {
    $id = intval($_POST['id']);
    $gym_name = trim($_POST['gym_name']);
    $gym_email = trim($_POST['gym_email']);
    $gym_phone = trim($_POST['gym_phone']);
    $location = trim($_POST['location']);
    $gym_city = trim($_POST['gym_city']);
    $gym_state = trim($_POST['gym_state']);
    $gym_zip = trim($_POST['gym_zip']);
    $gym_address = trim($_POST['gym_address']);
    $timings = trim($_POST['timings']);
    $facilities = trim($_POST['facilities']);
    $gym_description = trim($_POST['gym_description']);
    $year_started = !empty($_POST['year_started']) ? intval($_POST['year_started']) : null;
    $experience_years = !empty($_POST['experience_years']) ? intval($_POST['experience_years']) : null;
    $num_trainers = !empty($_POST['num_trainers']) ? intval($_POST['num_trainers']) : null;
    $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;

    $stmt = $conn->prepare("
        UPDATE gyms SET 
            gym_name=?, gym_email=?, gym_phone=?, location=?, gym_city=?, gym_state=?, gym_zip=?,
            gym_address=?, timings=?, facilities=?, gym_description=?, year_started=?, 
            experience_years=?, num_trainers=?, capacity=?
        WHERE gym_id=?
    ");
    $stmt->bind_param(
        "ssssssssssiiiiii",
        $gym_name, $gym_email, $gym_phone, $location, $gym_city, $gym_state, $gym_zip,
        $gym_address, $timings, $facilities, $gym_description, $year_started,
        $experience_years, $num_trainers, $capacity, $id
    );
    $stmt->execute();
    $stmt->close();

    $_SESSION['msg'] = "Gym updated successfully.";
    header("Location: admin_gyms.php");
    exit;
}

// SEARCH Gyms
$search = $_GET['search'] ?? '';
$sql = "
    SELECT g.*, o.owner_name 
    FROM gyms g 
    LEFT JOIN ownerlog o ON g.owner_id = o.owner_id 
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (g.gym_name LIKE ? OR g.gym_email LIKE ? OR g.location LIKE ? OR g.gym_city LIKE ?)";
    $term = "%$search%";
    $params[] = &$term;
    $params[] = &$term;
    $params[] = &$term;
    $params[] = &$term;
    $types .= "ssss";
}

$sql .= " ORDER BY g.gym_id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}
$stmt->execute();
$gyms = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gyms | RawFit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                        danger: '#EF4444',
                        'danger-hover': '#F87171',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                    },
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: #F97316; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #FBA63C; }
        .glass { backdrop-filter: blur(12px); background: rgba(30, 30, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); }
        .gradient-text { background: linear-gradient(to right, #F97316, #FBA63C); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .badge { @apply px-3 py-1 text-xs font-bold rounded-full; }
        .badge-blue { @apply bg-blue-900/40 text-blue-400 border border-blue-700; }
        .badge-green { @apply bg-green-900/40 text-green-400 border border-green-700; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-gray-900 to-black text-gray-100 min-h-screen">

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
<main class="pt-24 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto animate-fade-in">
    <div class="text-center mb-10">
        <h2 class="text-4xl sm:text-5xl font-bold font-display gradient-text mb-3">Manage Gyms</h2>
        <p class="text-gray-400">Edit, delete, or view gym profiles</p>
    </div>

    <!-- Alert -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="glass rounded-xl p-4 mb-6 border-l-4 <?= strpos($_SESSION['msg'], 'success') !== false ? 'border-green-500 bg-green-900/30' : 'border-red-500 bg-red-900/30' ?> animate-slide-up">
            <p class="text-sm font-medium flex items-center gap-2">
                <i class="fas <?= strpos($_SESSION['msg'], 'success') !== false ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400' ?>"></i>
                <?= htmlspecialchars($_SESSION['msg']) ?>
            </p>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <!-- Search Bar -->
    <form method="GET" class="mb-8">
        <div class="relative max-w-xl mx-auto">
            <input type="text" name="search" placeholder="Search by name, email, city, or location..." 
                   value="<?= htmlspecialchars($search); ?>"
                   class="w-full pl-12 pr-4 py-4 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-primary to-primary-hover text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition transform hover:scale-105">
                Search
            </button>
        </div>
    </form>

    <!-- Gyms Table -->
    <div class="glass rounded-2xl overflow-hidden shadow-2xl border border-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-primary to-primary-hover text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Image</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Gym Name</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Owner</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Location</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Trainers</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Capacity</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php while ($gym = $gyms->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-800/50 transition-all duration-200 cursor-pointer group"
                            onclick='openGymDetails(<?= json_encode($gym); ?>)'>
                            <td class="px-6 py-4 text-sm font-medium">#<?= $gym['gym_id']; ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($gym['gym_image'])): ?>
                                    <img src="<?= htmlspecialchars($gym['gym_image']); ?>" alt="Gym" class="w-16 h-16 object-cover rounded-lg border border-gray-600 shadow">
                                <?php else: ?>
                                    <div class="w-16 h-16 bg-gray-700 rounded-lg flex items-center justify-center text-xs">
                                        <i class="fas fa-image text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold"><?= htmlspecialchars($gym['gym_name']); ?></td>
                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($gym['owner_name'] ?? '—'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-300">
                                <?= htmlspecialchars($gym['gym_city'] . ($gym['gym_state'] ? ', ' . $gym['gym_state'] : '')); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-blue-400 hover:underline">
                                <?= htmlspecialchars($gym['gym_phone'] ?: '—'); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="badge badge-blue"><?= $gym['num_trainers'] ?? 0; ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="badge badge-green"><?= $gym['capacity'] ?? 0; ?></span>
                            </td>
                            <td class="px-6 py-4 text-center" onclick="event.stopPropagation();">
                                <div class="flex justify-center gap-2">
                                    <button onclick="event.stopPropagation(); openEditModal(<?= $gym['gym_id']; ?>, <?= json_encode($gym); ?>)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this gym and its image permanently?');">
                                        <input type="hidden" name="delete_gym" value="<?= $gym['gym_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-danger hover:bg-danger-hover text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($gyms->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-dumbbell text-4xl mb-3 block"></i>
                                <p class="text-lg">No gyms found.</p>
                                <?php if (!empty($search)): ?>
                                    <p class="text-sm mt-2">Try adjusting your search.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Gym Details Modal -->
<div id="gymModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto border border-gray-700">
        <div class="sticky top-0 glass border-b border-gray-700 p-5 flex justify-between items-center">
            <h3 class="text-2xl font-bold gradient-text font-display">Gym Profile</h3>
            <button onclick="closeGymModal()" class="text-gray-400 hover:text-white text-3xl transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="gymDetailsContent" class="p-6 space-y-6 text-gray-200"></div>
        <div class="sticky bottom-0 glass border-t border-gray-700 p-5 flex justify-end">
            <button onclick="closeGymModal()" class="px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Edit Gym Modal -->
<div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-gray-700 p-6">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-2xl font-bold gradient-text font-display">Edit Gym</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div><label class="block text-gray-300 mb-1">Gym Name</label><input type="text" name="gym_name" id="edit_gym_name" required class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">Email</label><input type="email" name="gym_email" id="edit_gym_email" required class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">Phone</label><input type="text" name="gym_phone" id="edit_gym_phone" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">Location</label><input type="text" name="location" id="edit_location" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">City</label><input type="text" name="gym_city" id="edit_gym_city" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">State</label><input type="text" name="gym_state" id="edit_gym_state" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">ZIP</label><input type="text" name="gym_zip" id="edit_gym_zip" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div class="md:col-span-2"><label class="block text-gray-300 mb-1">Address</label><textarea name="gym_address" id="edit_gym_address" rows="2" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></textarea></div>
            <div><label class="block text-gray-300 mb-1">Timings</label><input type="text" name="timings" id="edit_timings" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div class="md:col-span-2"><label class="block text-gray-300 mb-1">Facilities</label><textarea name="facilities" id="edit_facilities" rows="2" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></textarea></div>
            <div class="md:col-span-2"><label class="block text-gray-300 mb-1">Description</label><textarea name="gym_description" id="edit_gym_description" rows="3" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></textarea></div>
            <div><label class="block text-gray-300 mb-1">Year Started</label><input type="number" name="year_started" id="edit_year_started" min="1900" max="2100" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">Experience (Years)</label><input type="number" name="experience_years" id="edit_experience_years" min="0" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">Trainers</label><input type="number" name="num_trainers" id="edit_num_trainers" min="0" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div><label class="block text-gray-300 mb-1">Capacity</label><input type="number" name="capacity" id="edit_capacity" min="0" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"></div>
            <div class="md:col-span-2 flex gap-3 mt-4">
                <button type="submit" name="update_gym"
                        class="flex-1 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-xl transition transform hover:scale-105 flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openGymDetails(gym) {
        const content = document.getElementById('gymDetailsContent');
        let html = '';

        if (gym['gym_image']) {
            html += `
                <div class="flex justify-center mb-6">
                    <img src="${gym['gym_image']}" alt="Gym" class="w-full max-w-3xl h-80 object-cover rounded-xl border-2 border-primary shadow-lg">
                </div>`;
        }

        const fields = [
            { key: 'gym_id', label: 'Gym ID' },
            { key: 'gym_name', label: 'Gym Name' },
            { key: 'owner_name', label: 'Owner' },
            { key: 'gym_email', label: 'Email', type: 'email' },
            { key: 'gym_phone', label: 'Phone' },
            { key: 'location', label: 'Location' },
            { key: 'gym_city', label: 'City' },
            { key: 'gym_state', label: 'State' },
            { key: 'gym_zip', label: 'ZIP' },
            { key: 'gym_address', label: 'Address' },
            { key: 'timings', label: 'Timings' },
            { key: 'facilities', label: 'Facilities' },
            { key: 'gym_description', label: 'Description' },
            { key: 'year_started', label: 'Year Started' },
            { key: 'experience_years', label: 'Experience (Years)' },
            { key: 'num_trainers', label: 'Trainers' },
            { key: 'capacity', label: 'Capacity' },
            { key: 'created_at', label: 'Created At', type: 'date' }
        ];

        fields.forEach(field => {
            if (gym.hasOwnProperty(field.key)) {
                let val = gym[field.key] ?? '';
                if (field.type === 'date' && val) {
                    val = new Date(val).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
                } else if (field.type === 'email' && val) {
                    val = `<a href="mailto:${val}" class="text-primary hover:underline">${htmlspecialchars(val)}</a>`;
                } else if (val === '' || val === null) {
                    val = '<em class="text-gray-500">—</em>';
                } else {
                    val = htmlspecialchars(val);
                }
                html += `
                    <div class="flex flex-col sm:flex-row gap-3 border-b border-gray-700 pb-4 last:border-0">
                        <div class="font-semibold text-gray-400 sm:w-48 shrink-0">${field.label}:</div>
                        <div class="flex-1 break-all">${val}</div>
                    </div>`;
            }
        });

        content.innerHTML = html;
        document.getElementById('gymModal').classList.remove('hidden');
    }

    function closeGymModal() {
        document.getElementById('gymModal').classList.add('hidden');
    }

    function openEditModal(id, gym) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_gym_name').value = gym['gym_name'] || '';
        document.getElementById('edit_gym_email').value = gym['gym_email'] || '';
        document.getElementById('edit_gym_phone').value = gym['gym_phone'] || '';
        document.getElementById('edit_location').value = gym['location'] || '';
        document.getElementById('edit_gym_city').value = gym['gym_city'] || '';
        document.getElementById('edit_gym_state').value = gym['gym_state'] || '';
        document.getElementById('edit_gym_zip').value = gym['gym_zip'] || '';
        document.getElementById('edit_gym_address').value = gym['gym_address'] || '';
        document.getElementById('edit_timings').value = gym['timings'] || '';
        document.getElementById('edit_facilities').value = gym['facilities'] || '';
        document.getElementById('edit_gym_description').value = gym['gym_description'] || '';
        document.getElementById('edit_year_started').value = gym['year_started'] || '';
        document.getElementById('edit_experience_years').value = gym['experience_years'] || '';
        document.getElementById('edit_num_trainers').value = gym['num_trainers'] || '';
        document.getElementById('edit_capacity').value = gym['capacity'] || '';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // Close on backdrop
    document.getElementById('gymModal').addEventListener('click', e => {
        if (e.target === document.getElementById('gymModal')) closeGymModal();
    });
    document.getElementById('editModal').addEventListener('click', e => {
        if (e.target === document.getElementById('editModal')) closeEditModal();
    });

    function htmlspecialchars(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Auto-hide alerts
    setTimeout(() => {
        const alert = document.querySelector('.glass.border-l-4');
        if (alert) alert.style.opacity = '0';
    }, 5000);
</script>

</body>
</html>
<?php $conn->close(); ?>