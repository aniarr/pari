<?php
session_start();

// ---------------------------------------------------------------------
// 1. Must be logged in as owner
// ---------------------------------------------------------------------
if (!isset($_SESSION['owner_id'])) {
    header('Location: login_owner.php');
    exit;
}

$owner_id = (int)$_SESSION['owner_id'];
$gym_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($gym_id <= 0) {
    $_SESSION['error'] = 'Invalid gym ID.';
    header('Location: owner_dashboard.php');
    exit;
}

// ---------------------------------------------------------------------
// 2. DB connection
// ---------------------------------------------------------------------
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// ---------------------------------------------------------------------
// 3. Verify gym belongs to this owner
// ---------------------------------------------------------------------
$stmt = $conn->prepare('SELECT gym_id, gym_name FROM gyms WHERE gym_id = ? AND owner_id = ?');
$stmt->bind_param('ii', $gym_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Gym not found or you do not own it.';
    header('Location: owner_dashboard.php');
    exit;
}

$gym = $result->fetch_assoc();
$stmt->close();

// ---------------------------------------------------------------------
// 4. Handle DELETE (with confirmation)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Optional: re-verify ownership
    $check = $conn->prepare('SELECT 1 FROM gyms WHERE gym_id = ? AND owner_id = ?');
    $check->bind_param('ii', $gym_id, $owner_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $_SESSION['error'] = 'Unauthorized delete attempt.';
        $check->close();
        header('Location: owner_dashboard.php');
        exit;
    }
    $check->close();

    // === STEP 1: Delete all images from filesystem & DB ===
    $img_dir = 'uploads/gyms/';

    $img_stmt = $conn->prepare('SELECT filename FROM gym_images WHERE gym_id = ?');
    $img_stmt->bind_param('i', $gym_id);
    $img_stmt->execute();
    $images = $img_stmt->get_result();

    while ($img = $images->fetch_assoc()) {
        $file_path = $img_dir . $img['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    $img_stmt->close();

    // Delete image records
    $del_imgs = $conn->prepare('DELETE FROM gym_images WHERE gym_id = ?');
    $del_imgs->bind_param('i', $gym_id);
    $del_imgs->execute();
    $del_imgs->close();

    // === STEP 2: Delete gym record ===
    $del_gym = $conn->prepare('DELETE FROM gyms WHERE gym_id = ? AND owner_id = ?');
    $del_gym->bind_param('ii', $gym_id, $owner_id);
    $deleted = $del_gym->execute();
    $del_gym->close();

    if ($deleted) {
        $_SESSION['success'] = "Gym '{$gym['gym_name']}' has been deleted.";
    } else {
        $_SESSION['error'] = 'Failed to delete gym.';
    }

    $conn->close();
    header('Location: owner_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Gym - RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { inter: ['Inter', 'sans-serif'] } } } };
    </script>
</head>
<body class="bg-gray-900 font-inter text-white">

<!-- NAV -->
<nav class="fixed top-0 inset-x-0 bg-black/90 backdrop-blur-md border-b border-gray-800 z-50">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                    <path d="M6.5 6.5h11v11h-11z"/><path d="M6.5 6.5L2 2"/><path d="M17.5 6.5L22 2"/>
                    <path d="M6.5 17.5L2 22"/><path d="M17.5 17.5L22 22"/>
                </svg>
            </div>
            <span class="text-xl font-bold">RawFit</span>
        </div>
        <div class="hidden md:flex items-center space-x-6">
            <a href="owner_dashboard.php" class="text-gray-300 hover:text-white">Dashboard</a>
        </div>
        <div class="flex items-center space-x-4">
            <a href="logout.php" class="text-gray-300 hover:text-white">Logout</a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<main class="pt-24 pb-12 max-w-md mx-auto px-4">
    <div class="bg-gray-800 rounded-xl p-8 border border-gray-700 text-center">
        <div class="w-16 h-16 mx-auto mb-6 bg-red-900/50 rounded-full flex items-center justify-center">
            <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold mb-3">Delete Gym?</h1>
        <p class="text-gray-300 mb-6">
            Are you sure you want to <strong>permanently delete</strong> the gym:
        </p>
        <p class="text-xl font-semibold text-orange-400 mb-8">
            <?= htmlspecialchars($gym['gym_name']) ?>
        </p>
        <p class="text-sm text-red-400 mb-8">
            This will delete all photos, reviews, and data. This action <strong>cannot be undone</strong>.
        </p>

        <form method="POST" class="flex gap-4 justify-center">
            <a href="owner_dashboard.php"
               class="px-6 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition">
                Cancel
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                Yes, Delete Gym
            </button>
        </form>
    </div>
</main>

</body>
</html>