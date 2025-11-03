<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ✅ Only allow admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminlogin.php?action=login");
    exit;
}

// ✅ Logout handling
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: adminlogin.php?action=login");
    exit;
}

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// CSRF token for sensitive actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Reusable deletion function that validates table and cleans dependent data
function perform_deletion($conn, $table, $delete_id) {
    $allowed = [
        'register' => 'id',
        'admins' => 'admin_id',
        'trainerlog' => 'trainer_id',
        'reels' => 'id',
        'messages' => 'id'
    ];
    if (!isset($allowed[$table])) return false;
    $idColumn = $allowed[$table];

    // If deleting a trainer, clean up dependent trainer_courses and related data to avoid FK constraint errors
    if ($table === 'trainerlog') {
        $fetch = $conn->prepare("SELECT id, doc_path, image_path FROM trainer_courses WHERE trainer_id = ?");
        if ($fetch) {
            $fetch->bind_param('i', $delete_id);
            $fetch->execute();
            $res = $fetch->get_result();
            $courseIds = [];
            while ($c = $res->fetch_assoc()) {
                $courseIds[] = intval($c['id']);
                if (!empty($c['doc_path']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $c['doc_path'])) {
                    @unlink(__DIR__ . DIRECTORY_SEPARATOR . $c['doc_path']);
                }
                if (!empty($c['image_path']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $c['image_path'])) {
                    @unlink(__DIR__ . DIRECTORY_SEPARATOR . $c['image_path']);
                }
            }
            $fetch->close();

            if (!empty($courseIds)) {
                $in = implode(',', array_map('intval', $courseIds));
                $conn->query("DELETE FROM course_downloads WHERE course_id IN ($in)");
                $conn->query("DELETE FROM trainer_courses WHERE id IN ($in)");
            }
        }
    }

    $sql = "DELETE FROM `$table` WHERE `$idColumn` = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $delete_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    // fallback
    return (bool) $conn->query("DELETE FROM `$table` WHERE `$idColumn` = " . intval($delete_id));
}

// Handle secure POST delete (preferred)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid CSRF token';
        exit;
    }
    $delete_id = intval($_POST['delete']);
    $table = $_POST['table'] ?? 'register';
    perform_deletion($conn, $table, $delete_id);
    header('Location: admin.php');
    exit;
}

// Handle update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $table = $_POST['table'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];

    if ($table === 'admins') {
        $stmt = $conn->prepare("UPDATE admins SET username=?, email=?, password=?, created_at=CURRENT_TIMESTAMP WHERE admin_id=?");
        $stmt->bind_param("sssi", $name, $email, $phone, $id); // Using phone as placeholder for password
    } elseif ($table === 'trainerlog') {
        $stmt = $conn->prepare("UPDATE trainerlog SET trainer_name=?, trainer_email=?, trainer_phone=?, trainer_password=? WHERE trainer_id=?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE register SET name=?, email=?, phone=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $role, $id);
    }
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php");
    exit;
}

// Handle approve/reject reel actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_reel']) || isset($_POST['reject_reel']))) {
    $reel_id = intval($_POST['reel_id']);
    if (isset($_POST['approve_reel'])) {
        $stmt = $conn->prepare("UPDATE reels SET status = 'approved' WHERE id = ?");
        $message = "Reel approved successfully!";
    } elseif (isset($_POST['reject_reel'])) {
        $stmt = $conn->prepare("UPDATE reels SET status = 'rejected' WHERE id = ?");
        $message = "Reel rejected successfully!";
    }
    $stmt->bind_param("i", $reel_id);
    $stmt->execute();
    $stmt->close();
    // No redirect to keep the page state
}

// Search & filter for users
$user_search = $_GET['user_search'] ?? '';
$user_filter_role = $_GET['user_role'] ?? '';

$sql_users = "SELECT id, name, email, phone, role FROM register WHERE 1=1";
$params_users = [];
$types_users = "";

if (!empty($user_search)) {
    $sql_users .= " AND (name LIKE ? OR email LIKE ?)";
    $search_term = "%$user_search%";
    $params_users[] = &$search_term;
    $params_users[] = &$search_term;
    $types_users .= "ss";
}

if (!empty($user_filter_role)) {
    $sql_users .= " AND role = ?";
    $params_users[] = &$user_filter_role;
    $types_users .= "s";
}

$sql_users .= " ORDER BY id ASC";
$stmt_users = $conn->prepare($sql_users);

if (!empty($params_users)) {
    array_unshift($params_users, $types_users);
    call_user_func_array([$stmt_users, 'bind_param'], $params_users);
}

$stmt_users->execute();
$result = $stmt_users->get_result();

// Search & filter for admins
$admin_search = $_GET['admin_search'] ?? '';

$sql_admins = "SELECT * FROM admins WHERE 1=1";
$params_admins = [];
$types_admins = "";

if (!empty($admin_search)) {
    $sql_admins .= " AND (username LIKE ? OR email LIKE ?)";
    $search_term = "%$admin_search%";
    $params_admins[] = &$search_term;
    $params_admins[] = &$search_term;
    $types_admins .= "ss";
}

$sql_admins .= " ORDER BY admin_id ASC";
$stmt_admins = $conn->prepare($sql_admins);

if (!empty($params_admins)) {
    array_unshift($params_admins, $types_admins);
    call_user_func_array([$stmt_admins, 'bind_param'], $params_admins);
}

$stmt_admins->execute();
$adminsResult = $stmt_admins->get_result();

// Search & filter for trainers
$trainer_search = $_GET['trainer_search'] ?? '';

$sql_trainers = "SELECT * FROM trainerlog WHERE 1=1";
$params_trainers = [];
$types_trainers = "";

if (!empty($trainer_search)) {
    $sql_trainers .= " AND (trainer_name LIKE ? OR trainer_email LIKE ?)";
    $search_term = "%$trainer_search%";
    $params_trainers[] = &$search_term;
    $params_trainers[] = &$search_term;
    $types_trainers .= "ss";
}

$sql_trainers .= " ORDER BY trainer_id ASC";
$stmt_trainers = $conn->prepare($sql_trainers);

if (!empty($params_trainers)) {
    array_unshift($params_trainers, $types_trainers);
    call_user_func_array([$stmt_trainers, 'bind_param'], $params_trainers);
}

$stmt_trainers->execute();
$trainerlogResult = $stmt_trainers->get_result();

// Fetch pending reels
$reelResult = $conn->query("SELECT r.id, r.video_url, rg.name AS username FROM reels r JOIN register rg ON r.user_id = rg.id WHERE r.status = 'pending'");

// ✅ Fetch messages for admin
$msgResult = $conn->query("SELECT * FROM messages ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=M+PLUS+Rounded+1c:wght@700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #000; font-family: 'Inter', sans-serif; margin: 0; padding: 0; color: #fff; }
        header { background: #1A1F2E; padding: 20px; text-align: center; font-family: 'Orbitron', sans-serif; font-size: 28px; color: #F97316; display: flex; justify-content: space-between; align-items: center; }
        header .logout { font-size: 16px; background: #EF4444; color: #fff; padding: 8px 12px; border-radius: 6px; text-decoration: none; }
        header .logout:hover { background: #F87171; }
        .container { padding: 20px; max-width: 1100px; margin: auto; }
        h2 { color: #F97316; margin-bottom: 20px; }
        form.search-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        input[type="text"], select { padding: 10px; border-radius: 8px; border: none; background: #2D2D2D; color: #fff; }
        .btn-search { background: #F97316; color: white; border: none; border-radius: 8px; padding: 10px 15px; cursor: pointer; font-weight: bold; }
        .btn-search:hover { background: #FBA63C; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #1A1F2E; border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #F97316; color: #fff; }
        tr:hover { background: #2D2D2D; }
        .btn { padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-edit { background: #3B82F6; color: white; }
        .btn-delete { background: #EF4444; color: white; }
        .btn-edit:hover { background: #60A5FA; }
        .btn-delete:hover { background: #F87171; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); justify-content: center; align-items: center; }
        .modal-content { background: #1A1F2E; padding: 20px; border-radius: 12px; width: 400px; }
        .modal-content h2 { color: #F97316; margin-bottom: 20px; }
        input, select { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 8px; border: none; background: #2D2D2D; color: #fff; }
        .btn-save { background: #22C55E; color: white; }
        .btn-save:hover { background: #4ADE80; }
        .close-btn { background: #EF4444; color: white; float: right; border-radius: 6px; padding: 5px 10px; cursor: pointer; }
    </style>
</head>
<body>
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

                <div class="flex space-x-4">
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
                <a href="manage_course.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-orange-500">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span class="text-xs">Nutrition</span>
                </a>
                <a href="trainer.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-orange-500">
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
    <br><br>
    <div class="container">
        <br><br>
        <!-- Tabbed navigation to declutter the page -->
        <div class="mb-6">
            <div class="flex items-center space-x-3">
                <button class="tab-btn px-4 py-2 rounded-md bg-orange-500 text-white" data-target="users">Users</button>
                <button class="tab-btn px-4 py-2 rounded-md bg-gray-700 text-white" data-target="admins">Admins</button>
                <button class="tab-btn px-4 py-2 rounded-md bg-gray-700 text-white" data-target="trainers">Trainers</button>
                <button class="tab-btn px-4 py-2 rounded-md bg-gray-700 text-white" data-target="reels">Reels</button>
                <button class="tab-btn px-4 py-2 rounded-md bg-gray-700 text-white" data-target="messages">Messages</button>
            </div>
        </div>

        <!-- Users Section -->
        <section id="section-users" class="tab-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold">Manage Users</h2>
                <form method="GET" class="flex items-center space-x-2">
                    <input type="text" name="user_search" placeholder="Search name or email" value="<?= htmlspecialchars($user_search); ?>" class="bg-gray-700 px-3 py-2 rounded-md text-white">
                    <button type="submit" class="btn-search">Search</button>
                </form>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']); ?></td>
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td><?= htmlspecialchars($row['phone']); ?></td>
                            <td><?= htmlspecialchars($row['role']); ?></td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    <button class="btn btn-edit" onclick="openModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES); ?>', '<?= htmlspecialchars($row['email'], ENT_QUOTES); ?>', '<?= htmlspecialchars($row['phone'], ENT_QUOTES); ?>', '<?= $row['role']; ?>')">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="delete" value="<?= $row['id']; ?>">
                                        <input type="hidden" name="table" value="register">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Admins Section -->
        <section id="section-admins" class="tab-section hidden">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold">Manage Admins</h2>
                <form method="GET" class="flex items-center space-x-2">
                    <input type="text" name="admin_search" placeholder="Search username or email" value="<?= htmlspecialchars($admin_search); ?>" class="bg-gray-700 px-3 py-2 rounded-md text-white">
                    <button type="submit" class="btn-search">Search</button>
                </form>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Created At</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($admin = $adminsResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($admin['admin_id']); ?></td>
                            <td><?= htmlspecialchars($admin['username']); ?></td>
                            <td><?= htmlspecialchars($admin['created_at']); ?></td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    <button class="btn btn-edit" onclick="openModal(<?= $admin['admin_id']; ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES); ?>', '<?= htmlspecialchars($admin['email'], ENT_QUOTES); ?>', '<?= htmlspecialchars($admin['password'], ENT_QUOTES); ?>', 'admin')">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                        <input type="hidden" name="delete" value="<?= $admin['admin_id']; ?>">
                                        <input type="hidden" name="table" value="admins">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Trainers Section -->
        <section id="section-trainers" class="tab-section hidden">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold">Manage Trainers</h2>
                <form method="GET" class="flex items-center space-x-2">
                    <input type="text" name="trainer_search" placeholder="Search trainer name or email" value="<?= htmlspecialchars($trainer_search); ?>" class="bg-gray-700 px-3 py-2 rounded-md text-white">
                    <button type="submit" class="btn-search">Search</button>
                </form>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($trainer = $trainerlogResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($trainer['trainer_id']); ?></td>
                            <td><?= htmlspecialchars($trainer['trainer_name']); ?></td>
                            <td><?= htmlspecialchars($trainer['trainer_email']); ?></td>
                            <td><?= htmlspecialchars($trainer['trainer_phone']); ?></td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    <button class="btn btn-edit" onclick="openModal(<?= $trainer['trainer_id']; ?>, '<?= htmlspecialchars($trainer['trainer_name'], ENT_QUOTES); ?>', '<?= htmlspecialchars($trainer['trainer_email'], ENT_QUOTES); ?>', '<?= htmlspecialchars($trainer['trainer_phone'], ENT_QUOTES); ?>', 'trainer')">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this trainer?');">
                                        <input type="hidden" name="delete" value="<?= $trainer['trainer_id']; ?>">
                                        <input type="hidden" name="table" value="trainerlog">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Reels Section -->
        <section id="section-reels" class="tab-section hidden">
            <div class="mb-4">
                <h2 class="text-xl font-semibold">Manage Reels</h2>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full admin-table">
                    <thead>
                        <tr>
                            <th>Reel ID</th>
                            <th>Uploaded By</th>
                            <th>Preview</th>
                            <th style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($reelResult->num_rows > 0): ?>
                        <?php while ($reel = $reelResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($reel['id']); ?></td>
                                <td><?= htmlspecialchars($reel['username']); ?></td>
                                <td>
                                    <video controls class="w-64">
                                        <source src="<?= htmlspecialchars($reel['video_url']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </td>
                                <td>
                                    <div class="flex items-center space-x-2">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="reel_id" value="<?= $reel['id']; ?>">
                                            <button type="submit" name="approve_reel" class="btn btn-edit">Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="reel_id" value="<?= $reel['id']; ?>">
                                            <button type="submit" name="reject_reel" class="btn btn-delete">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No pending reels to review.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Messages Section -->
        <section id="section-messages" class="tab-section hidden">
            <div class="mb-4">
                <h2 class="text-xl font-semibold">Contact Messages</h2>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($msg = $msgResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= intval($msg['id']); ?></td>
                            <td><?= htmlspecialchars($msg['name']); ?></td>
                            <td><?= htmlspecialchars($msg['email']); ?></td>
                            <td><?= htmlspecialchars($msg['phone']); ?></td>
                            <td><?= htmlspecialchars($msg['message']); ?></td>
                            <td><?= htmlspecialchars($msg['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">X</span>
            <h2>Edit User</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="table" id="edit_table">
                <label>Name</label>
                <input type="text" name="name" id="edit_name" required>
                <label>Email</label>
                <input type="email" name="email" id="edit_email" required>
                <label>Phone/Password</label>
                <input type="text" name="phone" id="edit_phone" required>
                <label>Role</label>
                <select name="role" id="edit_role">
                    <option value="user">User</option>
                    <option value="trainer">Trainer</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" name="update_user" class="btn btn-save">Save</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, name, email, phone, role) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name || '';
            document.getElementById("edit_email").value = email || '';
            document.getElementById("edit_phone").value = phone || '';
            document.getElementById("edit_role").value = role || 'user';
            document.getElementById("edit_table").value = role === 'admin' ? 'admins' : (role === 'trainer' ? 'trainerlog' : 'register');
            document.getElementById("editModal").style.display = "flex";
        }
        function closeModal() {
            document.getElementById("editModal").style.display = "none";
        }
    </script>
    <style>
        .admin-table th, .admin-table td { padding: 10px; border-bottom: 1px solid #2b2b2b; }
        .admin-table thead th { background: #111827; color: #fff; }
        .tab-section { display: block; }
        .tab-section.hidden { display: none; }
    </style>
    <script>
        // Tab switching
        (function(){
            const tabs = document.querySelectorAll('.tab-btn');
            const sections = document.querySelectorAll('.tab-section');
            function show(target){
                sections.forEach(s=> s.classList.add('hidden'));
                document.getElementById('section-' + target).classList.remove('hidden');
                tabs.forEach(t=> t.classList.toggle('bg-orange-500', t.dataset.target === target));
            }
            tabs.forEach(tb => tb.addEventListener('click', ()=> show(tb.dataset.target)));
            // show from hash if present
            const hash = location.hash.replace('#','');
            if (hash && document.querySelector(`.tab-btn[data-target="${hash}"]`)) show(hash);
            else show('users');
        })();
    </script>
</body>
</html>
<?php $conn->close(); ?>