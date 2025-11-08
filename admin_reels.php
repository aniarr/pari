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

// DELETE Reel + Video File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reel'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }
    $reel_id = intval($_POST['delete_reel']);

    $stmt = $conn->prepare("SELECT video_url FROM reels WHERE id = ?");
    $stmt->bind_param("i", $reel_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $reel = $res->fetch_assoc();
    $stmt->close();

    // Delete file
    if (!empty($reel['video_url']) && file_exists(__DIR__ . '/' . $reel['video_url'])) {
        @unlink(__DIR__ . '/' . $reel['video_url']);
    }

    $stmt = $conn->prepare("DELETE FROM reels WHERE id = ?");
    $stmt->bind_param("i", $reel_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['msg'] = "Reel deleted successfully.";
    header("Location: admin_reels.php");
    exit;
}

// APPROVE / REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_reel']) || isset($_POST['reject_reel']))) {
    $reel_id = intval($_POST['reel_id']);
    $status = isset($_POST['approve_reel']) ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE reels SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $reel_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['msg'] = "Reel " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully.";
    header("Location: admin_reels.php");
    exit;
}

// SEARCH Reels
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, pending, approved, rejected

$sql = "
    SELECT r.*, rg.name AS username, rg.email AS user_email 
    FROM reels r 
    JOIN register rg ON r.user_id = rg.id 
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (rg.name LIKE ? OR rg.email LIKE ?)";
    $term = "%$search%";
    $params[] = &$term;
    $params[] = &$term;
    $types .= "ss";
}

if ($filter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = &$filter;
    $types .= "s";
}

$sql .= " ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}
$stmt->execute();
$reels = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Reels | RawFit</title>
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
                        pending: '#F59E0B',
                        approved: '#10B981',
                        rejected: '#EF4444',
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
        .status-pending { @apply bg-yellow-900/40 text-yellow-400 border border-yellow-700; }
        .status-approved { @apply bg-green-900/40 text-green-400 border border-green-700; }
        .status-rejected { @apply bg-red-900/40 text-red-400 border border-red-700; }
        video { max-width: 100%; height: auto; }
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
        <h2 class="text-4xl sm:text-5xl font-bold font-display gradient-text mb-3">Manage Reels</h2>
        <p class="text-gray-400">Approve, reject, or delete user-uploaded fitness reels</p>
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

    <!-- Search & Filter -->
    <form method="GET" class="mb-8">
        <div class="flex flex-col sm:flex-row gap-4 max-w-2xl mx-auto">
            <div class="flex-1 relative">
                <input type="text" name="search" placeholder="Search by name or email..." 
                       value="<?= htmlspecialchars($search); ?>"
                       class="w-full pl-12 pr-4 py-4 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            <select name="filter" class="px-4 py-4 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Reels</option>
                <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button type="submit" class="bg-gradient-to-r from-primary to-primary-hover text-white px-6 py-4 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105">
                Filter
            </button>
        </div>
    </form>

    <!-- Reels Table -->
    <div class="glass rounded-2xl overflow-hidden shadow-2xl border border-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-primary to-primary-hover text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Video</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Uploader</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Uploaded</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php while ($reel = $reels->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-800/50 transition-all duration-200 cursor-pointer group"
                            onclick='openReelDetails(<?= json_encode($reel); ?>)'>
                            <td class="px-6 py-4 text-sm font-medium">#<?= $reel['id']; ?></td>
                            <td class="px-6 py-4">
                                <video controls class="w-48 h-32 object-cover rounded-lg border border-gray-700">
                                    <source src="<?= htmlspecialchars($reel['video_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold"><?= htmlspecialchars($reel['username']); ?></td>
                            <td class="px-6 py-4 text-sm text-blue-400 hover:underline">
                                <a href="mailto:<?= htmlspecialchars($reel['user_email']); ?>"><?= htmlspecialchars($reel['user_email']); ?></a>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-full status-<?= $reel['status']; ?>">
                                    <i class="fas <?= $reel['status'] === 'pending' ? 'fa-clock' : ($reel['status'] === 'approved' ? 'fa-check' : 'fa-times') ?>"></i>
                                    <?= ucfirst($reel['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400">
                                <?= date('M j, Y', strtotime($reel['created_at'])); ?><br>
                                <span class="text-xs"><?= date('g:i A', strtotime($reel['created_at'])); ?></span>
                            </td>
                            <td class="px-6 py-4 text-center" onclick="event.stopPropagation();">
                                <div class="flex justify-center gap-2">
                                    <?php if ($reel['status'] === 'pending'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="reel_id" value="<?= $reel['id']; ?>">
                                            <button type="submit" name="approve_reel" class="inline-flex items-center gap-1 px-3 py-1.5 bg-approved hover:bg-green-700 text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="reel_id" value="<?= $reel['id']; ?>">
                                            <button type="submit" name="reject_reel" class="inline-flex items-center gap-1 px-3 py-1.5 bg-rejected hover:bg-red-700 text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this reel and its video file permanently?');">
                                        <input type="hidden" name="delete_reel" value="<?= $reel['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-danger hover:bg-danger-hover text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($reels->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-video-slash text-4xl mb-3 block"></i>
                                <p class="text-lg">No reels found.</p>
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <p class="text-sm mt-2">Try adjusting your filters.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Reel Details Modal -->
<div id="reelModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-gray-700">
        <div class="sticky top-0 glass border-b border-gray-700 p-5 flex justify-between items-center">
            <h3 class="text-2xl font-bold gradient-text font-display">Reel Details</h3>
            <button onclick="closeReelModal()" class="text-gray-400 hover:text-white text-3xl transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="reelDetailsContent" class="p-6 space-y-6 text-gray-200"></div>
        <div class="sticky bottom-0 glass border-t border-gray-700 p-5 flex justify-end">
            <button onclick="closeReelModal()" class="px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    function openReelDetails(reel) {
        const content = document.getElementById('reelDetailsContent');
        let html = '';

        // Full Video
        if (reel['video_url']) {
            html += `
                <div class="flex justify-center mb-6">
                    <video controls class="w-full max-w-3xl h-96 object-contain rounded-xl border-2 border-primary shadow-lg">
                        <source src="${reel['video_url']}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>`;
        }

        const fields = [
            { key: 'id', label: 'Reel ID' },
            { key: 'username', label: 'Uploaded By' },
            { key: 'user_email', label: 'Email', type: 'email' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'created_at', label: 'Uploaded At', type: 'date' }
        ];

        fields.forEach(field => {
            let val = reel[field.key] || '';
            if (field.type === 'date') {
                val = new Date(val).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
            } else if (field.type === 'status') {
                const icons = { pending: 'fa-clock', approved: 'fa-check', rejected: 'fa-times' };
                const colors = { pending: 'yellow', approved: 'green', rejected: 'red' };
                val = `
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-full bg-${colors[val]}-900/40 text-${colors[val]}-400 border border-${colors[val]}-700">
                        <i class="fas ${icons[val]}"></i> ${val.charAt(0).toUpperCase() + val.slice(1)}
                    </span>`;
            } else if (field.type === 'email') {
                val = `<a href="mailto:${val}" class="text-primary hover:underline">${htmlspecialchars(val)}</a>`;
            } else if (!val) {
                val = '<em class="text-gray-500">—</em>';
            } else {
                val = htmlspecialchars(val);
            }

            html += `
                <div class="flex flex-col sm:flex-row gap-3 border-b border-gray-700 pb-4 last:border-0">
                    <div class="font-semibold text-gray-400 sm:w-40 shrink-0">${field.label}:</div>
                    <div class="flex-1 break-all">${val}</div>
                </div>`;
        });

        content.innerHTML = html;
        document.getElementById('reelModal').classList.remove('hidden');
    }

    function closeReelModal() {
        document.getElementById('reelModal').classList.add('hidden');
    }

    // Close on backdrop
    document.getElementById('reelModal').addEventListener('click', e => {
        if (e.target === document.getElementById('reelModal')) closeReelModal();
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