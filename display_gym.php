<?php
session_start();

// === 1. FORCE LOGIN ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// === 2. DB CONNECTION ===
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// === 3. FILTERS ===
$cityFilter     = trim($_GET['city'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');
$timeFilter     = trim($_GET['time'] ?? '');

$filters = []; $types = ''; $values = [];

if ($cityFilter !== '')     { $filters[] = 'gym_city LIKE ?';    $types .= 's'; $values[] = "%$cityFilter%"; }
if ($locationFilter !== '') { $filters[] = 'location LIKE ?';    $types .= 's'; $values[] = "%$locationFilter%"; }
if ($timeFilter !== '')     { $filters[] = 'timings LIKE ?';     $types .= 's'; $values[] = "%$timeFilter%"; }

$where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
$sql   = "SELECT * FROM gyms $where ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $gyms = $conn->query("SELECT * FROM gyms ORDER BY created_at DESC");
} else {
    if ($values) {
        $refs = [];
        foreach ($values as $k => $v) $refs[$k] = &$values[$k];
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $gyms = $stmt->get_result();
}

// === 4. REVIEW TABLE ===
$conn->query("CREATE TABLE IF NOT EXISTS gym_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gym_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(191) DEFAULT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(gym_id), INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// === 5. HELPER: GET IMAGES ===
function getGymImages($conn, $gym_id) {
    $imgs = [];
    $st = $conn->prepare('SELECT filename FROM gym_images WHERE gym_id = ? ORDER BY uploaded_at DESC');
    $st->bind_param('i', $gym_id); $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $imgs[] = $r['filename'];
    $st->close();
    return $imgs;
}

// Fix user name fetching at the top
$userName = "";
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name FROM register WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($userName);
    $stmt->fetch();
    $stmt->close();
}
$userName = $userName ?: 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gyms - RawFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card {
            @apply bg-gray-800 rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 border border-gray-700;
        }
        .badge {
            @apply absolute top-3 right-3 px-2. f py-1 rounded-full text-xs font-semibold tracking-wider;
        }
        .badge.available   { @apply bg-emerald-500 text-white; }
        .badge.unavailable { @apply bg-rose-500 text-white; }
        .nav-link.active   { @apply bg-orange-500 text-white; }
        .mobile-nav-link.active { @apply text-orange-500; }
        .star { @apply w-4 h-4; }
        .star-filled { @apply text-yellow-400 fill-current; }
        .star-empty  { @apply text-gray-600; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- ==================== NAVIGATION ==================== -->
<nav class="fixed top-0 inset-x-0 z-50 bg-black/95 backdrop-blur-lg border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center shadow-md">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                    <path d="M6.5 6.5h11v11h-11z"/>
                    <path d="M6.5 6.5L2 2"/><path d="M17.5 6.5L22 2"/>
                    <path d="M6.5 17.5L2 22"/><path d="M17.5 17.5L22 22"/>
                </svg>
            </div>
            <span class="font-bold text-xl">RawFit</span>
        </div>

        <div class="hidden md:flex items-center space-x-6">
            <a href="home.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white transition">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                <span>Home</span>
            </a>
            <a href="nutrition.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white transition">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                <span>Nutrition</span>
            </a>
            <a href="trainer.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white transition">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Trainers</span>
            </a>
            <a href="display_gym.php" class="nav-link active flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                <span>Gyms</span>
            </a>
        </div>

        <div class="relative flex items-center space-x-3">
            <span class="hidden sm:block text-sm font-medium"><?= htmlspecialchars($userName) ?></span>
            <div id="profileButton" class="w-9 h-9 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center cursor-pointer shadow-md">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/95 backdrop-blur-md border border-gray-700 rounded-lg shadow-xl hidden z-50">
                <a href="profile.php" class="block px-4 py-2.5 text-sm text-white hover:bg-gray-700 rounded-t-lg">View Profile</a>
                <a href="logout.php" class="block px-4 py-2.5 text-sm text-white hover:bg-gray-700 rounded-b-lg">Logout</a>
            </div>
        </div>
    </div>

    <!-- Mobile Nav -->
    <div class="md:hidden flex justify-around py-2 border-t border-gray-800 bg-black/95">
        <a href="home.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-gray-400">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
            <span class="text-xs">Home</span>
        </a>
        <a href="nutrition.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-gray-400">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
            <span class="text-xs">Nutrition</span>
        </a>
        <a href="trainer.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-gray-400">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="text-xs">Trainers</span>
        </a>
        <a href="display_gym.php" class="mobile-nav-link active text-orange-500 flex flex-col items-center space-y-1 px-3 py-2">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
            <span class="text-xs">Gyms</span>
        </a>
    </div>
</nav>

<!-- ==================== FILTER BAR ==================== -->
<div class="max-w-7xl mx-auto mt-24 px-4">
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
            Search Your Nearby <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">Gym</span>
        </h1>
        <p class="text-gray-400 text-lg">Book sessions with our certified expert trainers</p>
    </div>

        <!-- Filter Form -->
        <form method="GET" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">

        <!-- City -->
        <input type="text" name="city" 
            value="<?= htmlspecialchars($cityFilter) ?>" 
            placeholder="City"
            class="bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white 
            placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 
            w-full transition shadow-sm">

        <!-- Location -->
        <input type="text" name="location"
            value="<?= htmlspecialchars($locationFilter) ?>" 
            placeholder="Location"
            class="bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white 
            placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 
            w-full transition shadow-sm">

        <!-- Buttons -->
        <div class="flex flex-col sm:flex-row gap-3 md:justify-end">
            <button type="submit"
                class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 
                text-white font-medium rounded-xl px-6 py-3 transition shadow-md w-full sm:w-auto">
                Search
            </button>

            <a href="display_gym.php"
                class="bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-xl px-6 py-3
                transition shadow-md w-full sm:w-auto text-center">
                Clear
            </a>
        </div>

    </div>

    </form>
</div>

<!-- ==================== GYM CARDS ==================== -->
<div class="max-w-8xl mx-auto px-4 py-12">
    <div class="bg-gradient-to-b from-gray-900 via-gray-800/20 to-gray-900 rounded-3xl p-8 shadow-2xl">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php if ($gyms && $gyms->num_rows > 0): ?>
                <?php while ($row = $gyms->fetch_assoc()): ?>
                    <?php
                    $imgs = getGymImages($conn, $row['gym_id']);
                    if (!empty($row['gym_image']) && !in_array($row['gym_image'], $imgs)) {
                        array_unshift($imgs, $row['gym_image']);
                    }
                    $urls = array_map(fn($i) => 'uploads/gyms/' . $i, $imgs);
                    $hero = $urls[0] ?? '';
                    $urlsJson = htmlspecialchars(json_encode($urls), ENT_QUOTES);

                    $stmtR = $conn->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM gym_reviews WHERE gym_id = ?');
                    $stmtR->bind_param('i', $row['gym_id']); $stmtR->execute();
                    $resR = $stmtR->get_result()->fetch_assoc();
                    $avgRating = $resR['avg'] ? round($resR['avg'], 1) : 0;
                    $reviewCount = (int)$resR['cnt'];
                    $stmtR->close();

                    $location = $row['location'] ?? $row['gym_city'] ?? 'Location not specified';
                    $price = rand(55, 70);
                    $time = rand(7, 30);
                    ?>
                    <!-- Card -->
                    <div class="relative group bg-gray-800/90 backdrop-blur-sm rounded-2xl overflow-hidden shadow-lg border border-gray-700 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
                        <!-- Hero Image -->
                        <?php if ($hero): ?>
                            <div class="relative h-48 overflow-hidden rounded-t-2xl">
                                <button class="preview-button w-full h-full block" data-images="<?= $urlsJson ?>">
                                    <img src="<?= htmlspecialchars($hero) ?>" alt="<?= htmlspecialchars($row['gym_name']) ?>"
                                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="h-48 bg-gray-700 flex items-center justify-center rounded-t-2xl">
                                <span class="text-gray-400 text-sm">No Image</span>
                            </div>
                        <?php endif; ?>

                        <!-- Content -->
                        <div class="p-5 space-y-3">
                            <h3 class="text-lg font-bold text-orange-400 line-clamp-1"><?= htmlspecialchars($row['gym_name']) ?></h3>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($location) ?></p>

                            <!-- Stars -->
                            <div class="flex items-center space-x-0.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-4 h-4 <?= $i <= $avgRating ? 'text-yellow-400 fill-current' : 'text-gray-600' ?>" viewBox="0 0 24 24">
                                        <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z"/>
                                    </svg>
                                <?php endfor; ?>
                                <span class="ml-1 text-xs text-gray-400">(<?= $reviewCount ?>)</span>
                            </div>

                          <br>

                            <!-- CTA: open individual gym details page -->
                            <a href="gym_idv_details.php?gym_id=<?= (int)$row['gym_id'] ?>" class="w-full inline-block text-center bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-medium text-sm py-2.5 rounded-lg transition shadow-md">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-16">
                    <p class="text-gray-400 text-lg font-medium">No gyms found. Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== GALLERY MODAL ==================== -->
<div id="galleryModal" class="fixed inset-0 hidden flex items-center justify-center bg-black/90 z-50 p-4">
    <div class="relative max-w-4xl w-full">
        <button id="galleryClose" class="absolute -top-12 right-0 text-white text-3xl hover:text-gray-300 transition">&times;</button>
        <button id="galleryPrev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition">&lt;</button>
        <button id="galleryNext" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition">&gt;</button>
        <img id="galleryImg" src="" alt="" class="w-full max-h-[80vh] object-contain rounded-xl mx-auto">
        <div id="galleryThumbs" class="flex gap-2 mt-4 overflow-x-auto justify-center pb-2"></div>
    </div>
</div>


<!-- ==================== JAVASCRIPT ==================== -->
<script>
    // Profile Dropdown
    const profileBtn = document.getElementById('profileButton');
    const dropdown = document.getElementById('profileDropdown');
    profileBtn.addEventListener('click', () => dropdown.classList.toggle('hidden'));
    document.addEventListener('click', e => {
        if (!profileBtn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Gallery
    let currentGallery = [], currentIndex = 0;
    const modal = document.getElementById('galleryModal');
    const img = document.getElementById('galleryImg');
    const thumbs = document.getElementById('galleryThumbs');

    function openGallery(urls, idx = 0) {
        currentGallery = urls;
        currentIndex = idx;
        img.src = urls[idx];
        renderThumbs();
        modal.classList.remove('hidden');
    }

    function renderThumbs() {
        thumbs.innerHTML = '';
        currentGallery.forEach((url, i) => {
            const thumb = document.createElement('img');
            thumb.src = url;
            thumb.className = `w-20 h-14 object-cover rounded-lg cursor-pointer border-2 transition ${i === currentIndex ? 'border-white' : 'border-transparent hover:border-gray-400'}`;
            thumb.onclick = () => { currentIndex = i; img.src = url; renderThumbs(); };
            thumbs.appendChild(thumb);
        });
    }

    document.getElementById('galleryPrev').onclick = () => {
        currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length;
        img.src = currentGallery[currentIndex];
        renderThumbs();
    };

    document.getElementById('galleryNext').onclick = () => {
        currentIndex = (currentIndex + 1) % currentGallery.length;
        img.src = currentGallery[currentIndex];
        renderThumbs();
    };

    document.getElementById('galleryClose').onclick = () => modal.classList.add('hidden');

    document.addEventListener('keydown', e => {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'ArrowLeft') document.getElementById('galleryPrev').click();
        if (e.key === 'ArrowRight') document.getElementById('galleryNext').click();
        if (e.key === 'Escape') document.getElementById('galleryClose').click();
    });

    // Open gallery
    document.addEventListener('click', e => {
        const btn = e.target.closest('.preview-button');
        if (!btn) return;
        let urls = [];
        try { urls = JSON.parse(btn.dataset.images); } catch (_) { return; }
        openGallery(urls);
    });
</script>
</body>
</html>