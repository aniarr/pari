<?php
session_start();

// === 1. OPTIONAL: Remove login gate for public access ===
// if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// === 2. DB CONNECTION ===
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// === 3. INPUT FILTERS ===
$cityFilter     = trim($_GET['city'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');
$timeFilter     = trim($_GET['time'] ?? '');
$lat            = $_GET['lat'] ?? null;
$lng            = $_GET['lng'] ?? null;
$radius         = 10; // km

// === 4. BUILD TEXT FILTERS ===
$filterWhere = [];
$filterTypes = '';
$filterVals  = [];

if ($cityFilter !== '') {
    $filterWhere[] = 'gym_city LIKE ?';
    $filterTypes   .= 's';
    $filterVals[]   = "%$cityFilter%";
}
if ($locationFilter !== '') {
    $filterWhere[] = 'location LIKE ?';
    $filterTypes   .= 's';
    $filterVals[]   = "%$locationFilter%";
}
if ($timeFilter !== '') {
    $filterWhere[] = 'timings LIKE ?';
    $filterTypes   .= 's';
    $filterVals[]   = "%$timeFilter%";
}
// Always show approved gyms
$filterWhere[] = 'status = ?';
$filterTypes   .= 'i';
$filterVals[]   = 1;

// === 5. DISTANCE OR NORMAL SEARCH ===
$useDistance = ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng));

if ($useDistance) {
    $sql = "
        SELECT *,
               (6371 * acos(
                   cos(radians(?)) * cos(radians(lat)) *
                   cos(radians(lng) - radians(?)) +
                   sin(radians(?)) * sin(radians(lat))
               )) AS distance
        FROM gyms
        WHERE " . implode(' AND ', $filterWhere) . "
        HAVING distance < ? OR lat IS NULL
        ORDER BY distance ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die('Prepare failed: ' . $conn->error);
    $bindTypes = 'dddd' . $filterTypes;
    $bindVals  = [$lat, $lng, $lat, $radius];
    $bindVals  = array_merge($bindVals, $filterVals);
    $stmt->bind_param($bindTypes, ...$bindVals);
} else {
    $sql = "SELECT * FROM gyms WHERE " . implode(' AND ', $filterWhere) . " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die('Prepare failed: ' . $conn->error);
    $stmt->bind_param($filterTypes, ...$filterVals);
}

$stmt->execute();
$result = $stmt->get_result();

// === 6. FETCH ALL GYMS AT ONCE (Avoid "out of sync") ===
$gymRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close(); // Free main result

// === 7. USER NAME (for navbar) ===
$userName = 'User';
if (isset($_SESSION['user_id'])) {
    $u = $conn->prepare('SELECT name FROM register WHERE id = ?');
    $u->bind_param('i', $_SESSION['user_id']);
    $u->execute();
    $u->bind_result($userName);
    $u->fetch();
    $u->close();
}

// === 8. HELPER: GET GYM IMAGES ===
function getGymImages(mysqli $conn, int $gym_id): array {
    $imgs = [];
    $st = $conn->prepare('SELECT filename FROM gym_images WHERE gym_id = ? ORDER BY uploaded_at DESC');
    $st->bind_param('i', $gym_id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $imgs[] = $r['filename'];
    $st->close();
    return $imgs;
}
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
        body {font-family:'Inter',sans-serif;}
        .card{@apply bg-gray-800 rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 border border-gray-700;}
        .badge{@apply absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-semibold tracking-wider;}
        .badge.available{@apply bg-emerald-500 text-white;}
        .nav-link.active{@apply bg-orange-500 text-white;}
        .mobile-nav-link.active{@apply text-orange-500;}
        .star{@apply w-4 h-4;}
        .star-filled{@apply text-yellow-400 fill-current;}
        .star-empty{@apply text-gray-600;}
        #locationBtn{@apply bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-medium rounded-xl px-4 py-3 transition shadow-md flex items-center gap-2;}
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- ==================== NAVBAR ==================== -->
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
                <span>Courses</span>
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
</nav>

<!-- ==================== FILTER BAR + LOCATION BUTTON ==================== -->
<div class="max-w-7xl mx-auto mt-24 px-4">
    <div class="text-center mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
            Search Your Nearby <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">Gym</span>
        </h1>
        <p class="text-gray-400 text-lg"></p>
    </div>
<br><br>
    <form method="GET" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-700">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <input type="text" name="city" value="<?= htmlspecialchars($cityFilter) ?>" placeholder="City"
                   class="bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 w-full transition shadow-sm">

            <input type="text" name="location" value="<?= htmlspecialchars($locationFilter) ?>" placeholder="Location"
                   class="bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 w-full transition shadow-sm">

            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">

            <button type="button" id="locationBtn" onclick="getLocation()">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span id="locationText">Use My Location</span>
            </button>

            <button type="submit"
                    class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-medium rounded-xl px-6 py-3 transition shadow-md">
                Search
            </button>
        </div>
    </form>
</div>

<!-- ==================== GYM CARDS ==================== -->
<div class="max-w-8xl mx-auto px-4 py-12">
    <div class="bg-gradient-to-b from-gray-900 via-gray-800/20 to-gray-900 rounded-3xl p-8 shadow-2xl">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php if (!empty($gymRows)): ?>
                <?php foreach ($gymRows as $row): ?>
                    <?php
                    $imgs = getGymImages($conn, (int)$row['gym_id']);
                    if (!empty($row['gym_image']) && !in_array($row['gym_image'], $imgs)) {
                        array_unshift($imgs, $row['gym_image']);
                    }
                    $urls = array_map(fn($i) => 'uploads/gyms/' . $i, $imgs);
                    $hero = $urls[0] ?? '';
                    $urlsJson = htmlspecialchars(json_encode($urls), ENT_QUOTES);

                    // RATING
                    $r = $conn->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM gym_reviews WHERE gym_id = ?');
                    $r->bind_param('i', $row['gym_id']);
                    $r->execute();
                    $resR = $r->get_result()->fetch_assoc();
                    $avgRating = $resR['avg'] ? round($resR['avg'], 1) : 0;
                    $reviewCount = (int)$resR['cnt'];
                    $r->close();

                    $distance = $row['distance'] ?? null;
                    $distanceText = $distance !== null ? round($distance, 1) . ' km away' : 'Distance N/A';
                    ?>
                    <div class="relative group bg-gray-800/90 backdrop-blur-sm rounded-2xl overflow-hidden shadow-lg border border-gray-700 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
                        <?php if ($hero): ?>
                            <div class="relative h-48 overflow-hidden rounded-t-2xl">
                                <button class="preview-button w-full h-full block" data-images="<?= $urlsJson ?>">
                                    <img src="<?= htmlspecialchars($hero) ?>" alt="<?= htmlspecialchars($row['gym_name']) ?>"
                                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                </button>
                                <div class="absolute bottom-2 left-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                    <?= $distanceText ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="h-48 bg-gray-700 flex items-center justify-center rounded-t-2xl">
                                <span class="text-gray-400 text-sm">No Image</span>
                            </div>
                        <?php endif; ?>

                        <div class="p-5 space-y-3">
                            <h3 class="text-lg font-bold text-orange-400 line-clamp-1"><?= htmlspecialchars($row['gym_name']) ?></h3>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($row['location'] ?? 'Location N/A') ?></p>

                            <div class="flex items-center space-x-0.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-4 h-4 <?= $i <= $avgRating ? 'text-yellow-400 fill-current' : 'text-gray-600' ?>" viewBox="0 0 24 24">
                                        <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481 8.279-6.064-5.828 8.332-1.151z"/>
                                    </svg>
                                <?php endfor; ?>
                                <span class="ml-1 text-xs text-gray-400">(<?= $reviewCount ?>)</span>
                            </div>

                            <a href="gym_idv_details.php?gym_id=<?= (int)$row['gym_id'] ?>"
                               class="w-full inline-block text-center bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-medium text-sm py-2.5 rounded-lg transition shadow-md">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
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
        <button id="galleryClose" class="absolute -top-12 right-0 text-white text-3xl hover:text-gray-300 transition">×</button>
        <button id="galleryPrev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition">&lt;</button>
        <button id="galleryNext" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition">&gt;</button>
        <img id="galleryImg" src="" alt="" class="w-full max-h-[80vh] object-contain rounded-xl mx-auto">
        <div id="galleryThumbs" class="flex gap-2 mt-4 overflow-x-auto justify-center pb-2"></div>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
    // Profile dropdown
    const profileBtn = document.getElementById('profileButton');
    const dropdown = document.getElementById('profileDropdown');
    profileBtn.addEventListener('click', () => dropdown.classList.toggle('hidden'));

    // Geolocation
    function getLocation() {
        const btn = document.getElementById('locationBtn');
        const txt = document.getElementById('locationText');
        if (!navigator.geolocation) return alert('Geolocation not supported');

        btn.disabled = true;
        txt.innerHTML = 'Detecting...';

        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('lat').value = pos.coords.latitude;
                document.getElementById('lng').value = pos.coords.longitude;
                txt.innerHTML = 'Found!';
                setTimeout(() => btn.closest('form').submit(), 400);
            },
            () => {
                btn.disabled = false;
                txt.innerHTML = 'Use My Location';
                alert('Location access denied – please enable it.');
            },
            { timeout: 10000 }
        );
    }

    // Gallery
    let currentGallery = [], currentIndex = 0;
    const modal = document.getElementById('galleryModal');
    const img = document.getElementById('galleryImg');
    const thumbs = document.getElementById('galleryThumbs');

    function openGallery(urls, idx = 0) {
        currentGallery = urls; currentIndex = idx;
        img.src = urls[idx]; renderThumbs(); modal.classList.remove('hidden');
    }
    function renderThumbs() {
        thumbs.innerHTML = '';
        currentGallery.forEach((url, i) => {
            const t = document.createElement('img');
            t.src = url;
            t.className = `w-20 h-14 object-cover rounded-lg cursor-pointer border-2 transition ${i===currentIndex?'border-white':'border-transparent hover:border-gray-400'}`;
            t.onclick = () => { currentIndex = i; img.src = url; renderThumbs(); };
            thumbs.appendChild(t);
        });
    }
    document.getElementById('galleryPrev').onclick = () => {
        currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length;
        img.src = currentGallery[currentIndex]; renderThumbs();
    };
    document.getElementById('galleryNext').onclick = () => {
        currentIndex = (currentIndex + 1) % currentGallery.length;
        img.src = currentGallery[currentIndex]; renderThumbs();
    };
    document.getElementById('galleryClose').onclick = () => modal.classList.add('hidden');
    document.addEventListener('keydown', e => {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'ArrowLeft') document.getElementById('galleryPrev').click();
        if (e.key === 'ArrowRight') document.getElementById('galleryNext').click();
        if (e.key === 'Escape') document.getElementById('galleryClose').click();
    });
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