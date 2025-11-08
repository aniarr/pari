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

// DELETE USER - Secure & Complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }

    $user_id = intval($_POST['delete_user']);

    // Prevent deleting the last admin
    $admin_count = $conn->query("SELECT COUNT(*) as count FROM register WHERE role = 'admin'")->fetch_assoc()['count'];
    $is_last_admin = $conn->query("SELECT role FROM register WHERE id = $user_id")->fetch_assoc()['role'] === 'admin' && $admin_count <= 1;

    if ($is_last_admin) {
        $error = "Cannot delete the last admin account.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM course_downloads WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM users_details WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM register WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = "User deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to delete user.";
        }
    }

    $_SESSION['admin_msg'] = $success ?? $error ?? null;
    header("Location: admin_users.php");
    exit;
}

// Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE register SET name=?, email=?, phone=?, role=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $email, $phone, $role, $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['admin_msg'] = "User updated successfully.";
    header("Location: admin_users.php");
    exit;
}

// Search
$user_search = $_GET['user_search'] ?? '';
$sql = "SELECT id, name, email, phone, role FROM register WHERE 1=1";
$params = [];
$types = "";

if (!empty($user_search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $search_term = "%$user_search%";
    $params[] = &$search_term;
    $params[] = &$search_term;
    $types .= "ss";
}

$sql .= " ORDER BY id ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Users | RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
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
        .role-badge { @apply px-3 py-1 text-xs font-bold rounded-full; }
        .role-user { @apply bg-green-900/40 text-green-400 border border-green-700; }
        .role-trainer { @apply bg-blue-900/40 text-blue-400 border border-blue-700; }
        .role-admin { @apply bg-red-900/40 text-red-400 border border-red-700; }
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
        <h2 class="text-4xl sm:text-5xl font-bold font-display gradient-text mb-3">Manage Users</h2>
        <p class="text-gray-400">View, edit, or delete user accounts</p>
    </div>

    <!-- Alert -->
    <?php if (isset($_SESSION['admin_msg'])): ?>
        <div class="glass rounded-xl p-4 mb-6 border-l-4 <?= strpos($_SESSION['admin_msg'], 'successfully') !== false ? 'border-green-500 bg-green-900/30' : 'border-red-500 bg-red-900/30' ?> animate-slide-up">
            <p class="text-sm font-medium flex items-center gap-2">
                <i class="fas <?= strpos($_SESSION['admin_msg'], 'successfully') !== false ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400' ?>"></i>
                <?= htmlspecialchars($_SESSION['admin_msg']) ?>
            </p>
        </div>
        <?php unset($_SESSION['admin_msg']); ?>
    <?php endif; ?>

    <!-- Search Bar -->
    <form method="GET" class="mb-8">
        <div class="relative max-w-xl mx-auto">
            <input type="text" name="user_search" placeholder="Search by name or email..." 
                   value="<?= htmlspecialchars($user_search); ?>"
                   class="w-full pl-12 pr-4 py-4 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-primary to-primary-hover text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition transform hover:scale-105">
                Search
            </button>
        </div>
    </form>

    <!-- Users Table -->
    <div class="glass rounded-2xl overflow-hidden shadow-2xl border border-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-primary to-primary-hover text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Name</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php 
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()): 
                        $full_stmt = $conn->prepare("
                            SELECT r.*, ud.* 
                            FROM register r 
                            LEFT JOIN users_details ud ON r.id = ud.id 
                            WHERE r.id = ?
                        ");
                        $full_stmt->bind_param("i", $row['id']);
                        $full_stmt->execute();
                        $full_result = $full_stmt->get_result();
                        $user = $full_result->fetch_assoc();
                        $full_stmt->close();

                        $course_stmt = $conn->prepare("
                            SELECT tc.title, t.trainer_name, cd.downloaded_at 
                            FROM course_downloads cd
                            JOIN trainer_courses tc ON cd.course_id = tc.id
                            JOIN trainerlog t ON tc.trainer_id = t.trainer_id
                            WHERE cd.user_id = ?
                            ORDER BY cd.downloaded_at DESC
                        ");
                        $course_stmt->bind_param("i", $row['id']);
                        $course_stmt->execute();
                        $courses_result = $course_stmt->get_result();
                        $courses = [];
                        while ($c = $courses_result->fetch_assoc()) {
                            $courses[] = $c;
                        }
                        $course_stmt->close();
                    ?>
                        <tr class="hover:bg-gray-800/50 transition-all duration-200 cursor-pointer group"
                            onclick='openUserModal(<?= json_encode($user); ?>, <?= json_encode($courses); ?>)'>
                            <td class="px-6 py-4 text-sm font-medium">#<?= $row['id']; ?></td>
                            <td class="px-6 py-4 text-sm font-semibold"><?= htmlspecialchars($row['name']); ?></td>
                            <td class="px-6 py-4 text-sm text-blue-400 hover:underline">
                                <a href="mailto:<?= htmlspecialchars($row['email']); ?>"><?= htmlspecialchars($row['email']); ?></a>
                            </td>
                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($row['phone'] ?? '—'); ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="role-badge role-<?= $row['role']; ?>">
                                    <i class="fas <?= $row['role'] === 'admin' ? 'fa-crown' : ($row['role'] === 'trainer' ? 'fa-chalkboard-teacher' : 'fa-user') ?>"></i>
                                    <?= ucfirst($row['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center" onclick="event.stopPropagation();">
                                <div class="flex justify-center gap-2">
                                    <button onclick="event.stopPropagation(); openEditModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES); ?>', '<?= htmlspecialchars($row['email'], ENT_QUOTES); ?>', '<?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES); ?>', '<?= $row['role']; ?>')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="event.stopPropagation(); confirmDelete(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES); ?>')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-danger hover:bg-danger-hover text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-3 block"></i>
                                <p class="text-lg">No users found.</p>
                                <?php if (!empty($user_search)): ?>
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

<!-- User Details Modal -->
<div id="userDetailsModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto border border-gray-700">
        <div class="sticky top-0 glass border-b border-gray-700 p-5 flex justify-between items-center">
            <h3 class="text-2xl font-bold gradient-text font-display">User Profile</h3>
            <button onclick="closeUserModal()" class="text-gray-400 hover:text-white text-3xl transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="userDetailsBody" class="p-6 space-y-6 text-gray-200"></div>
        <div class="p-6 border-t border-gray-700">
            <h4 class="text-lg font-bold text-orange-500 mb-3" id="courseCount">Courses Taken: 0</h4>
            <div id="courseList" class="space-y-2"></div>
        </div>
        <div class="sticky bottom-0 glass border-t border-gray-700 p-5 flex justify-end">
            <button onclick="closeUserModal()" class="px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-700">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-2xl font-bold gradient-text font-display">Edit User</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
                    <input type="text" name="name" id="edit_name" required
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                    <input type="email" name="email" id="edit_email" required
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Phone</label>
                    <input type="text" name="phone" id="edit_phone"
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Role</label>
                    <select name="role" id="edit_role"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="user">User</option>
                        <option value="trainer">Trainer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="update_user"
                            class="flex-1 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-xl transition transform hover:scale-105 flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">
                        Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-700">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-red-500">Confirm Delete</h3>
            <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-white text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
        <p class="text-sm text-gray-400 mt-2">This will permanently remove all their data.</p>
        <form method="POST" class="mt-6 flex justify-end gap-3">
            <input type="hidden" name="delete_user" id="delete_user_id">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">
                Cancel
            </button>
            <button type="submit" class="px-5 py-2.5 bg-danger hover:bg-danger-hover text-white font-bold rounded-xl transition transform hover:scale-105">
                Delete
            </button>
        </form>
    </div>
</div>

<script>
    // Delete Confirmation
    function confirmDelete(id, name) {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // User Details Modal
    function openUserModal(user, courses) {
        const body = document.getElementById('userDetailsBody');
        let html = '';

        if (user['profile_image']) {
            html += `
                <div class="flex justify-center mb-6">
                    <img src="${user['profile_image']}" alt="Profile" class="w-32 h-32 rounded-full object-cover border-4 border-primary shadow-lg">
                </div>`;
        }

        const fields = [
            { key: 'id', label: 'User ID' },
            { key: 'name', label: 'Name' },
            { key: 'email', label: 'Email', type: 'email' },
            { key: 'phone', label: 'Phone' },
            { key: 'role', label: 'Role', type: 'role' },
            { key: 'address', label: 'Address' },
            { key: 'age', label: 'Age' },
            { key: 'dob', label: 'Date of Birth', type: 'date' },
            { key: 'blood_group', label: 'Blood Group' },
            { key: 'location', label: 'Location' },
            { key: 'gender', label: 'Gender' },
            { key: 'interests', label: 'Interests' },
            { key: 'website', label: 'Website', type: 'url' },
            { key: 'social_links', label: 'Social Links', type: 'json' },
            { key: 'privacy_setting', label: 'Privacy' },
            { key: 'created_at', label: 'Joined', type: 'date' },
            { key: 'updated_at', label: 'Last Updated', type: 'date' }
        ];

        fields.forEach(field => {
            if (user.hasOwnProperty(field.key)) {
                let val = user[field.key] ?? '';
                if (field.type === 'date' && val) {
                    val = new Date(val).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
                } else if (field.type === 'email' && val) {
                    val = `<a href="mailto:${val}" class="text-primary hover:underline">${htmlspecialchars(val)}</a>`;
                } else if (field.type === 'url' && val) {
                    val = `<a href="${val}" target="_blank" class="text-primary hover:underline">${htmlspecialchars(val)}</a>`;
                } else if (field.type === 'json' && val) {
                    try {
                        const links = JSON.parse(val);
                        val = Object.entries(links).map(([platform, url]) => 
                            `<a href="${url}" target="_blank" class="text-primary hover:underline mr-3">${platform}</a>`
                        ).join('');
                    } catch { val = htmlspecialchars(val); }
                } else if (field.type === 'role') {
                    const icons = { admin: 'fa-crown text-red-400', trainer: 'fa-chalkboard-teacher text-blue-400', user: 'fa-user text-green-400' };
                    val = `<span class="inline-flex items-center gap-1"><i class="fas ${icons[val]}"></i> ${val.charAt(0).toUpperCase() + val.slice(1)}</span>`;
                } else if (!val) {
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

        body.innerHTML = html;
        document.getElementById('courseCount').textContent = `Courses Taken: ${courses.length}`;
        document.getElementById('courseList').innerHTML = courses.length > 0 
            ? courses.map(c => `
                <div class="glass p-3 rounded-lg border border-gray-700">
                    <strong class="text-primary">${htmlspecialchars(c.title)}</strong> by ${htmlspecialchars(c.trainer_name)}<br>
                    <small class="text-gray-400">Downloaded: ${new Date(c.downloaded_at).toLocaleString()}</small>
                </div>
            `).join('')
            : '<em class="text-gray-500">No courses downloaded yet.</em>';

        document.getElementById('userDetailsModal').classList.remove('hidden');
    }

    function closeUserModal() {
        document.getElementById('userDetailsModal').classList.add('hidden');
    }

    // Edit Modal
    function openEditModal(id, name, email, phone, role) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_phone').value = phone || '';
        document.getElementById('edit_role').value = role;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // Close on backdrop
    document.querySelectorAll('.fixed.inset-0').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) modal.classList.add('hidden');
        });
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