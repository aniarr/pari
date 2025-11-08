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

// DELETE Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }
    $admin_id = intval($_POST['delete_admin']);
    $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['msg'] = "Admin deleted successfully.";
    header("Location: admin_admins.php");
    exit;
}

// UPDATE Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET username=?, email=?, password=? WHERE admin_id=?");
        $stmt->bind_param("sssi", $username, $email, $hashed, $id);
    } else {
        $stmt = $conn->prepare("UPDATE admins SET username=?, email=? WHERE admin_id=?");
        $stmt->bind_param("ssi", $username, $email, $id);
    }
    $stmt->execute();
    $stmt->close();
    $_SESSION['msg'] = "Admin updated successfully.";
    header("Location: admin_admins.php");
    exit;
}

// SEARCH Admins
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM admins WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $term = "%$search%";
    $params[] = &$term;
    $params[] = &$term;
    $types .= "ss";
}

$sql .= " ORDER BY admin_id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}
$stmt->execute();
$admins = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Admins | RawFit</title>
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
<main class="pt-24 pb-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto animate-fade-in">
    <div class="text-center mb-10">
        <h2 class="text-4xl sm:text-5xl font-bold font-display gradient-text mb-3">Manage Admins</h2>
        <p class="text-gray-400">Add, edit, or remove admin accounts</p>
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
            <input type="text" name="search" placeholder="Search by username or email..." 
                   value="<?= htmlspecialchars($search); ?>"
                   class="w-full pl-12 pr-4 py-4 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-primary to-primary-hover text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition transform hover:scale-105">
                Search
            </button>
        </div>
    </form>

    <!-- Admins Table -->
    <div class="glass rounded-2xl overflow-hidden shadow-2xl border border-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-primary to-primary-hover text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Username</th>
                     
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Created</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php while ($admin = $admins->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-800/50 transition-all duration-200 group">
                            <td class="px-6 py-4 text-sm font-medium">#<?= $admin['admin_id']; ?></td>
                            <td class="px-6 py-4 text-sm font-semibold"><?= htmlspecialchars($admin['username']); ?></td>
                          
                            <td class="px-6 py-4 text-sm text-gray-400">
                                <?= date('M j, Y', strtotime($admin['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick="openEditModal(<?= $admin['admin_id']; ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES); ?>', '<?= htmlspecialchars($admin['email'], ENT_QUOTES); ?>')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this admin permanently?');">
                                        <input type="hidden" name="delete_admin" value="<?= $admin['admin_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-danger hover:bg-danger-hover text-white text-xs font-bold rounded-lg transition transform hover:scale-105">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($admins->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-user-shield text-4xl mb-3 block"></i>
                                <p class="text-lg">No admins found.</p>
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

<!-- Edit Admin Modal -->
<div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 animate-fade-in">
    <div class="glass rounded-2xl shadow-2xl w-full max-w-lg p-6 border border-gray-700">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-2xl font-bold gradient-text font-display">Edit Admin</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                    <input type="text" name="username" id="edit_username" required
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="edit_password"
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="update_admin"
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

<script>
    function openEditModal(id, username, email) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_password').value = '';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // Close on backdrop
    document.getElementById('editModal').addEventListener('click', e => {
        if (e.target === document.getElementById('editModal')) closeEditModal();
    });

    // Auto-hide alerts
    setTimeout(() => {
        const alert = document.querySelector('.glass.border-l-4');
        if (alert) alert.style.opacity = '0';
    }, 5000);
</script>

</body>
</html>
<?php $conn->close(); ?>