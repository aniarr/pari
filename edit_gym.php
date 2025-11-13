<?php
session_start();

// ---------------------------------------------------------------------
// 1. Owner must be logged in
// ---------------------------------------------------------------------
if (!isset($_SESSION['owner_id'])) {
    header('Location: login_owner.php');
    exit;
}
$owner_id = (int)$_SESSION['owner_id'];
$gym_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------------------------------------------------------------------
// 2. DB connection
// ---------------------------------------------------------------------
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// ---------------------------------------------------------------------
// 3. Verify that the gym belongs to this owner
// ---------------------------------------------------------------------
$stmt = $conn->prepare('SELECT * FROM gyms WHERE gym_id = ? AND owner_id = ?');
$stmt->bind_param('ii', $gym_id, $owner_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $_SESSION['error'] = 'Gym not found or you do not own it.';
    header('Location: owner_dashboard.php');
    exit;
}
$gym = $res->fetch_assoc();
$stmt->close();

// ---------------------------------------------------------------------
// 4. FORM SUBMISSION
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- collect & sanitize ------------------------------------------------
    $gym_name        = trim($_POST['gym_name']);
    $location        = trim($_POST['location']);
    $gym_address     = trim($_POST['gym_address']);
    $gym_city        = trim($_POST['gym_city']);
    $gym_state       = trim($_POST['gym_state']);
    $gym_zip         = trim($_POST['gym_zip']);
    $gym_phone       = trim($_POST['gym_phone']);
    $gym_email       = trim($_POST['gym_email']);
    $timings         = trim($_POST['timings']);
    $facilities      = trim($_POST['facilities']);
    $gym_description = trim($_POST['gym_description']);
    $year_started    = (int)$_POST['year_started'];
    $num_trainers    = (int)$_POST['num_trainers'];
    $capacity        = (int)$_POST['capacity'];

    // experience_years = current year – year_started
    $experience_years = date('Y') - $year_started;

    // ---- UPDATE gym details ------------------------------------------------
    $sql = "
        UPDATE gyms SET
            gym_name        = ?, location        = ?, gym_address   = ?,
            gym_city        = ?, gym_state       = ?, gym_zip       = ?,
            gym_phone       = ?, gym_email       = ?, timings       = ?,
            facilities      = ?, gym_description = ?, year_started  = ?,
            experience_years= ?, num_trainers    = ?, capacity      = ?
        WHERE gym_id = ? AND owner_id = ?
    ";

    $update = $conn->prepare($sql);
    // 11 strings + 6 integers = 17 placeholders
    $update->bind_param(
        'sssssssssssiiiiii',               // <-- 11 s + 6 i
        $gym_name, $location, $gym_address,
        $gym_city, $gym_state, $gym_zip,
        $gym_phone, $gym_email, $timings,
        $facilities, $gym_description,
        $year_started, $experience_years, $num_trainers, $capacity,
        $gym_id, $owner_id
    );

    if ($update->execute()) {
        $_SESSION['success'] = 'Gym details saved.';
    } else {
        $_SESSION['error'] = 'Failed to save gym details.';
    }
    $update->close();

    // -----------------------------------------------------------------
    // 5. IMAGE UPLOAD (multiple)
    // -----------------------------------------------------------------
    if (!empty($_FILES['gym_images']['name'][0])) {
        $dir = 'uploads/gyms/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $allowed = ['jpg','jpeg','png','webp'];
        foreach ($_FILES['gym_images']['name'] as $k => $name) {
            if ($_FILES['gym_images']['error'][$k] !== 0) continue;

            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $size = $_FILES['gym_images']['size'][$k];

            if (!in_array($ext, $allowed) || $size > 5*1024*1024) continue;

            $filename = uniqid('gym_') . '.' . $ext;
            $path     = $dir . $filename;

            if (move_uploaded_file($_FILES['gym_images']['tmp_name'][$k], $path)) {
                $ins = $conn->prepare('INSERT INTO gym_images (gym_id, filename) VALUES (?, ?)');
                $ins->bind_param('is', $gym_id, $filename);
                $ins->execute();
                $ins->close();
            }
        }
    }

    // -----------------------------------------------------------------
    // 6. DELETE selected images
    // -----------------------------------------------------------------
    if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        $sel = $conn->prepare('SELECT filename FROM gym_images WHERE id = ? AND gym_id = ?');
        $del = $conn->prepare('DELETE FROM gym_images WHERE id = ? AND gym_id = ?');

        foreach ($_POST['delete_images'] as $img_id) {
            $img_id = (int)$img_id;
            $sel->bind_param('ii', $img_id, $gym_id);
            $sel->execute();
            $r = $sel->get_result();
            if ($row = $r->fetch_assoc()) {
                $file = 'uploads/gyms/' . $row['filename'];
                if (file_exists($file)) unlink($file);
            }
            $del->bind_param('ii', $img_id, $gym_id);
            $del->execute();
        }
        $sel->close();
        $del->close();
    }

    // -----------------------------------------------------------------
    // 7. Refresh $gym data for the form (so the page shows fresh values)
    // -----------------------------------------------------------------
    $stmt = $conn->prepare('SELECT * FROM gyms WHERE gym_id = ?');
    $stmt->bind_param('i', $gym_id);
    $stmt->execute();
    $gym = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ---------------------------------------------------------------------
// 8. FETCH current images
// ---------------------------------------------------------------------
$img_stmt = $conn->prepare('SELECT id, filename FROM gym_images WHERE gym_id = ? ORDER BY id DESC');
$img_stmt->bind_param('i', $gym_id);
$img_stmt->execute();
$images = $img_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gym – RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{inter:['Inter','sans-serif']}}}};</script>
</head>
<body class="bg-gray-900 font-inter text-white">

<!-- ==================== NAV ==================== -->
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
            <a href="owner_dashboard.php" class="text-gray-300 hover:text-white">Home</a>
            <a href="Manage_Gyms.php" class="text-gray-300 hover:text-white">Add Gym</a>
        </div>
        <div class="flex items-center space-x-4">
            <a href="logout.php" class="text-gray-300 hover:text-white">Logout</a>
            <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
        </div>
    </div>
</nav>

<!-- ==================== MAIN ==================== -->
<main class="pt-24 pb-12 max-w-5xl mx-auto px-4">

    <h1 class="text-3xl font-bold mb-2">Edit Gym</h1>
    <p class="text-gray-400 mb-8">Update information and manage photos</p>

    <!-- ---- messages ---- -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-6 p-4 bg-green-900/50 border border-green-700 text-green-300 rounded-lg">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-6 p-4 bg-red-900/50 border border-red-700 text-red-300 rounded-lg">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- ---- FORM ---- -->
    <form method="POST" enctype="multipart/form-data" class="space-y-8">

        <!-- ==== BASIC INFO ==== -->
        <section class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h2 class="text-xl font-semibold mb-6">Basic Information</h2>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Gym Name *</label>
                    <input type="text" name="gym_name" required
                           value="<?= htmlspecialchars($gym['gym_name']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Short Location *</label>
                    <input type="text" name="location" required
                           value="<?= htmlspecialchars($gym['location']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Phone *</label>
                    <input type="text" name="gym_phone" required
                           value="<?= htmlspecialchars($gym['gym_phone']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" name="gym_email"
                           value="<?= htmlspecialchars($gym['gym_email']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Timings *</label>
                    <input type="text" name="timings" required placeholder="e.g. 5 AM – 10 PM"
                           value="<?= htmlspecialchars($gym['timings']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Year Started *</label>
                    <input type="number" name="year_started" required min="1900" max="<?= date('Y') ?>"
                           value="<?= $gym['year_started'] ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Trainers *</label>
                    <input type="number" name="num_trainers" required min="0"
                           value="<?= $gym['num_trainers'] ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Capacity *</label>
                    <input type="number" name="capacity" required min="1"
                           value="<?= $gym['capacity'] ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>
            </div>

            <!-- Address fields -->
            <div class="mt-6 grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Address *</label>
                    <input type="text" name="gym_address" required
                           value="<?= htmlspecialchars($gym['gym_address']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">City *</label>
                    <input type="text" name="gym_city" required
                           value="<?= htmlspecialchars($gym['gym_city']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">State *</label>
                    <input type="text" name="gym_state" required
                           value="<?= htmlspecialchars($gym['gym_state']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">ZIP *</label>
                    <input type="text" name="gym_zip" required
                           value="<?= htmlspecialchars($gym['gym_zip']) ?>"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500">
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Facilities (comma-separated)</label>
                <textarea name="facilities" rows="3"
                          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500"><?= htmlspecialchars($gym['facilities']) ?></textarea>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                <textarea name="gym_description" rows="5"
                          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-orange-500"><?= htmlspecialchars($gym['gym_description']) ?></textarea>
            </div>
        </section>

        <!-- ==== PHOTOS ==== -->
        <section class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h2 class="text-xl font-semibold mb-6">Gym Photos</h2>

            <?php if ($images->num_rows): ?>
                <p class="text-sm text-gray-400 mb-3">Check to delete:</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <?php while ($img = $images->fetch_assoc()): ?>
                        <label class="relative group cursor-pointer">
                            <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>" class="sr-only">
                            <img src="uploads/gyms/<?= htmlspecialchars($img['filename']) ?>"
                                 alt="gym photo"
                                 class="w-full h-32 object-cover rounded-lg border-2 border-gray-600 group-hover:border-red-500 transition">
                            <div class="absolute inset-0 bg-red-600/70 opacity-0 group-hover:opacity-100 rounded-lg flex items-center justify-center transition">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                        </label>
                    <?php endwhile; $images->data_seek(0); ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400 mb-6">No photos yet.</p>
            <?php endif; ?>

            <label class="block text-sm font-medium text-gray-300 mb-2">
                Add New Photos (max 5 MB each)
            </label>
            <input type="file" name="gym_images[]" multiple accept="image/*"
                   class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-orange-600 file:text-white hover:file:bg-orange-700">
            <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP</p>
        </section>

        <!-- ==== ACTIONS ==== -->
        <div class="flex justify-end gap-4">
            <a href="gym_profile.php"
               class="px-6 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition">Back</a>
            <button type="submit"
                    class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium">
                Save Changes
            </button>
        </div>
    </form>
</main>

<?php
$img_stmt->close();
$conn->close();
?>
</body>
</html>