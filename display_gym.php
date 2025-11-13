<?php
session_start();

// === DB CONNECTION ===
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// === INPUT FILTERS ===
$gymNameFilter  = trim($_GET['gym_name'] ?? '');
$cityFilter     = trim($_GET['city'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');
$timeFilter     = trim($_GET['time'] ?? '');
$lat            = $_GET['lat'] ?? null;
$lng            = $_GET['lng'] ?? null;
$radius         = 15; // km

// === BUILD TEXT FILTERS ===
$filterWhere = [];
$filterTypes = '';
$filterVals  = [];

if ($gymNameFilter !== '') {
    $filterWhere[] = 'gym_name LIKE ?';
    $filterTypes   .= 's';
    $filterVals[]   = "%$gymNameFilter%";
}
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
$filterWhere[] = 'status = ?';
$filterTypes   .= 'i';
$filterVals[]   = 1;

// === SAFE SEARCH (NO lat/lng REQUIRED) ===
$sql = "SELECT * FROM gyms WHERE " . implode(' AND ', $filterWhere) . " ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($sql);
if (!$stmt) die('Prepare failed: ' . $conn->error);
$stmt->bind_param($filterTypes, ...$filterVals);
$stmt->execute();
$result = $stmt->get_result();
$gymRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// === USER NAME ===
$userName = 'User';
if (isset($_SESSION['user_id'])) {
    $u = $conn->prepare('SELECT name FROM register WHERE id = ?');
    $u->bind_param('i', $_SESSION['user_id']);
    $u->execute();
    $u->bind_result($userName);
    $u->fetch();
    $u->close();
}

// === HELPER: GET GYM IMAGES ===
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
    <title>Find Gyms - RawFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {font-family:'Inter',sans-serif;}
        .card{@apply bg-gray-800 rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 border border-gray-700;}
        .badge.available{@apply bg-emerald-500 text-white;}
        .nav-link.active{@apply bg-orange-500 text-white;}
        .star-filled{@apply text-yellow-400 fill-current;}
        .star-empty{@apply text-gray-600;}
        .autocomplete-items { position: absolute; border: 1px solid #374151; border-top: none; z-index: 99; top: 100%; left: 0; right: 0; max-height: 200px; overflow-y: auto; background: #1f2937; }
        .autocomplete-items div { padding: 10px; cursor: pointer; background-color: #1f2937; border-bottom: 1px solid #374151; color: white; }
        .autocomplete-items div:hover { background-color: #374151; }

        /* Toast styles */
        .toast-container { position: fixed; right: 16px; top: 18px; z-index: 80; display:flex; flex-direction:column; gap:10px; align-items:flex-end; }
        .toast { min-width:220px; max-width:320px; padding:12px 14px; border-radius:10px; color:#fff; display:flex; gap:10px; align-items:center; box-shadow:0 8px 30px rgba(0,0,0,.45); transform:translateY(-8px); opacity:0; transition:transform .28s, opacity .28s; }
        .toast.show { transform:translateY(0); opacity:1; }
        .toast.success { background: linear-gradient(90deg,#10B981,#059669); }
        .toast.error { background: linear-gradient(90deg,#EF4444,#DC2626); }
        .toast .icon { width:22px; text-align:center; font-weight:700; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- NAVBAR -->
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
             <a href="nutrition.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Nutrition</span>
                    </a>
                    <a href="trainer.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span>Courses</span>
                    </a>
            <a href="display_gym.php" class="nav-link active flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                <span>Gyms</span>
            </a>
            <a href="trainer.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white transition">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Workout</span>
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

<!-- Toast container (for non-blocking messages) -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- HERO + SEARCH -->
<div class="max-w-7xl mx-auto mt-24 px-4">
    <div class="text-center mb-10">
        <h1 class="text-4xl sm:text-5xl font-bold text-white mb-3">
            Find Your Perfect <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">Gym</span>
        </h1>
        <p class="text-gray-400 text-lg">Search by name, location, or use your current location</p>
    </div>

    <!-- SEARCH FORM -->
    <form method="GET" id="searchForm" class="bg-gray-800/50 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-gray-700">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Gym Name -->
            <div class="relative">
                <input type="text" name="gym_name" id="gym_name" value="<?= htmlspecialchars($gymNameFilter) ?>" 
                       placeholder="Gym Name" autocomplete="off"
                       class="w-full bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 transition">
                <div id="gymNameSuggestions" class="autocomplete-items hidden"></div>
            </div>

            <!-- City -->
            <div class="relative">
                <input type="text" name="city" id="city" value="<?= htmlspecialchars($cityFilter) ?>" 
                       placeholder="City" autocomplete="off"
                       class="w-full bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 transition">
                <div id="citySuggestions" class="autocomplete-items hidden"></div>
            </div>

            <!-- Location -->
            <div class="relative">
                <input type="text" name="location" id="location" value="<?= htmlspecialchars($locationFilter) ?>" 
                       placeholder="Area / Locality" autocomplete="off"
                       class="w-full bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 transition">
                <div id="locationSuggestions" class="autocomplete-items hidden"></div>
            </div>

            <!-- Timings -->
            <input type="text" name="time" value="<?= htmlspecialchars($timeFilter) ?>" 
                   placeholder="Timings (e.g. 6AM-10PM)"
                   class="bg-gray-700/80 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 transition">

            <!-- Hidden Lat/Lng -->
            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">

            <!-- ==== LOCATION BUTTON ==== -->
            <div class="relative lg:col-span-1">
                <button type="button"
                        id="locationBtn"
                        class="w-full flex items-center justify-center gap-2
                               bg-gradient-to-r from-green-500 to-emerald-600
                               hover:from-green-600 hover:to-emerald-700
                               text-white font-medium rounded-xl px-5 py-3
                               transition-all duration-200 shadow-md
                               disabled:opacity-70 disabled:cursor-not-allowed">
                    <svg id="locationIcon" class="w-5 h-5" fill="none" stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span id="locationText">Use My Location</span>
                </button>

                <div id="locationSpinner"
                     class="hidden absolute inset-0 flex items-center justify-center
                            bg-emerald-600/90 rounded-xl">
                    <svg class="animate-spin w-5 h-5 text-white" fill="none"
                         viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            <!-- Search Button -->
            <button type="submit" class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-medium rounded-xl px-6 py-3 transition shadow-md lg:col-span-1">
                Search
            </button>
        </div>
    </form>
</div>

<!-- GYM RESULTS -->
<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <?php if (!empty($gymRows)): ?>
            <?php foreach ($gymRows as $row): ?>
                <?php
                $imgs = getGymImages($conn, (int)$row['gym_id']);
                if (!empty($row['gym_image']) && !in_array($row['gym_image'], $imgs)) {
                    array_unshift($imgs, $row['gym_image']);
                }

                // Build robust URLs for images: accept full URLs, leading-slash paths,
                // try uploads/gyms and uploads/gym folders, fall back to placeholder.
                $buildUrl = function($filename) {
                    $filename = trim((string)$filename);
                    if ($filename === '') return 'assets/no-image.png';

                    // Full URL (http/https)
                    if (preg_match('#^https?://#i', $filename)) return $filename;

                    // Leading slash - treat as web-root relative
                    if (strpos($filename, '/') === 0) {
                        return ltrim($filename, '/');
                    }

                    // Default uploads path
                    $candidates = [
                        'uploads/gyms/' . $filename,
                        'uploads/gym/'  . $filename, // common alternative
                        'uploads/'      . $filename
                    ];

                    foreach ($candidates as $p) {
                        if (file_exists(__DIR__ . '/' . $p)) return $p;
                    }

                    // final fallback
                    return 'assets/no-image.png';
                };

                $urls = array_map($buildUrl, $imgs);
                $urls = array_values(array_filter($urls)); // remove any empty entries
                $urls = $urls ?: ['assets/no-image.png'];
                $hero = $urls[0];
                $urlsJson = htmlspecialchars(json_encode($urls), ENT_QUOTES);

                // Fetch average rating and review count for this gym to avoid undefined variables
                $avgRating = 0;
                $reviewCount = 0;

                // Use bind_result/fetch for compatibility (avoids reliance on get_result)
                $r = $conn->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM gym_reviews WHERE gym_id = ?');
                if ($r) {
                    $r->bind_param('i', $row['gym_id']);
                    if ($r->execute()) {
                        $r->bind_result($avgVal, $cntVal);
                        if ($r->fetch()) {
                            $avgRating = !is_null($avgVal) ? round((float)$avgVal, 1) : 0;
                            $reviewCount = (int)$cntVal;
                        }
                    }
                    $r->close();
                }
                ?>
                <div class="card group">
                    <?php if ($hero): ?>
                        <div class="relative h-48 overflow-hidden rounded-t-2xl">
                            <button class="preview-button w-full h-full block" data-images="<?= $urlsJson ?>">
                                <img src="<?= htmlspecialchars($hero) ?>" alt="<?= htmlspecialchars($row['gym_name']) ?>"
                                     onerror="this.onerror=null;this.src='assets/no-image.png';"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="h-48 bg-gray-700 flex items-center justify-center rounded-t-2xl">
                            <span class="text-gray-400 text-sm">No Image</span>
                        </div>
                    <?php endif; ?>

                    <div class="p-5 space-y-3">
                        <h3 class="text-lg font-bold text-orange-400 line-clamp-1"><?= htmlspecialchars($row['gym_name']) ?></h3>
                        <p class="text-xs text-gray-400 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                            <?= htmlspecialchars($row['location'] ?? 'Location N/A') ?>
                        </p>

                        <div class="flex items-center space-x-0.5">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-4 h-4 <?= $i <= $avgRating ? 'star-filled' : 'star-empty' ?>" viewBox="0 0 24 24">
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
                <div class="bg-gray-800 rounded-2xl p-8 max-w-md mx-auto">
                    <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-400 text-lg font-medium">No gyms found.</p>
                    <p class="text-gray-500 text-sm mt-2">Try adjusting your filters or search terms.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- GALLERY MODAL -->
<div id="galleryModal" class="fixed inset-0 hidden flex items-center justify-center bg-black/90 z-50 p-4">
    <div class="relative max-w-4xl w-full">
        <button id="galleryClose" class="absolute -top-12 right-0 text-white text-3xl hover:text-gray-300 transition">×</button>
        <button id="galleryPrev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition">&lt;</button>
        <button id="galleryNext" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition">&gt;</button>
        <img id="galleryImg" src="" alt="" class="w-full max-h-[80vh] object-contain rounded-xl mx-auto">
        <div id="galleryThumbs" class="flex gap-2 mt-4 overflow-x-auto justify-center pb-2"></div>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
// Toast helper
function showToast(type, message, timeout = 4500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast ' + (type === 'success' ? 'success' : 'error');
    toast.innerHTML = `<div class="icon">${type === 'success' ? '✓' : '⚠'}</div><div class="flex-1 text-sm">${message}</div><button aria-label="Close" style="background:transparent;border:none;color:rgba(255,255,255,.9);cursor:pointer;font-weight:700">×</button>`;
    const btn = toast.querySelector('button');
    btn.addEventListener('click', () => { if (toast.parentNode) container.removeChild(toast); });
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { if (toast.parentNode) { toast.classList.remove('show'); setTimeout(() => { if (toast.parentNode) container.removeChild(toast); }, 300); } }, timeout);
}

 /* --------------------------------------------------------------
    PROFILE DROPDOWN
    -------------------------------------------------------------- */
 document.getElementById('profileButton').addEventListener('click', () => {
     document.getElementById('profileDropdown').classList.toggle('hidden');
 });

 /* --------------------------------------------------------------
    GEOLOCATION – robust, with UI feedback
    -------------------------------------------------------------- */
 document.addEventListener('DOMContentLoaded', () => {
     const btn       = document.getElementById('locationBtn');
     const txt       = document.getElementById('locationText');
     const icon      = document.getElementById('locationIcon');
     const spinner   = document.getElementById('locationSpinner');

     if (!btn) return;

     btn.addEventListener('click', () => {
         if (!navigator.geolocation) {
             showToast('error', 'Geolocation is not supported by your browser.');
             return;
         }

         btn.disabled = true;
         txt.textContent = 'Detecting…';
         icon.classList.add('hidden');
         spinner.classList.remove('hidden');

         navigator.geolocation.getCurrentPosition(
             pos => {
                 const lat = pos.coords.latitude.toFixed(6);
                 const lng = pos.coords.longitude.toFixed(6);

                 // set hidden fields temporarily (we will clear before submit to avoid using raw coords)
                 document.getElementById('lat').value = lat;
                 document.getElementById('lng').value = lng;

                 // retrying reverse-geocode with exponential backoff
                 const reverseGeocode = async (lat, lng, attempt = 1, maxAttempts = 3) => {
                     const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=en&addressdetails=1`;
                     try {
                         const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                         if (!res.ok) throw new Error('HTTP ' + res.status);
                         const data = await res.json();
                         // pick best place name as before
                         let place = '';
                         if (data && data.address) {
                             const a = data.address;
                             const prefer = [
                                 'suburb','neighbourhood','city_district','quarter',
                                 'village','town','city','hamlet','locality',
                                 'municipality','county','state_district','state'
                             ];
                             for (const k of prefer) {
                                 if (a[k]) { place = a[k]; break; }
                             }
                             if (!place) {
                                 if (a.suburb && (a.city || a.town)) place = a.suburb + ', ' + (a.city || a.town);
                                 else if ((a.village || a.hamlet) && (a.county || a.state)) place = (a.village || a.hamlet) + ', ' + (a.county || a.state);
                             }
                         }
                         if (!place && data && data.display_name) {
                             const parts = data.display_name.split(',').map(s => s.trim()).filter(Boolean);
                             if (parts.length >= 2) place = parts.slice(0,2).join(', ');
                             else if (parts.length === 1) place = parts[0];
                         }
                         // if we have a place return it
                         if (place) return place;
                         // otherwise consider this attempt failed
                         throw new Error('No locality in response');
                     } catch (err) {
                         console.warn(`Reverse geocode attempt ${attempt} failed:`, err);
                         // if we can retry, wait and retry
                         if (attempt < maxAttempts) {
                             const delay = 500 * Math.pow(2, attempt - 1); // 500, 1000, 2000 ms
                             await new Promise(r => setTimeout(r, delay));
                             return reverseGeocode(lat, lng, attempt + 1, maxAttempts);
                         }
                         // rethrow after final attempt
                         throw err;
                     }
                 };

                 // call reverse geocode and handle result or failure
                 reverseGeocode(lat, lng, 1, 3)
                     .then(place => {
                         // populate location input with the derived locality and clear coords before submit
                         const locInput = document.getElementById('location');
                         if (locInput) locInput.value = place;

                         // Update UI text (truncate if too long) and set full name in title for hover
                         const short = place.length > 28 ? place.slice(0, 25) + '...' : place;
                         txt.textContent = short;
                         btn.title = place;

                         // clear coords so server uses textual locality only
                         document.getElementById('lat').value = '';
                         document.getElementById('lng').value = '';

                         // submit after short delay so user sees update
                         setTimeout(() => document.getElementById('searchForm').submit(), 600);
                     })
                     .catch(err => {
                         console.error('Reverse geocoding ultimately failed:', err);
                         showToast('error', 'Reverse geocoding failed after retries. Please enter area/locality manually.');
                         // reset UI and clear coords
                         btn.disabled = false;
                         txt.textContent = 'Use My Location';
                         icon.classList.remove('hidden');
                         spinner.classList.add('hidden');
                         document.getElementById('lat').value = '';
                         document.getElementById('lng').value = '';
                         // focus the location input so user can type
                         const locInput = document.getElementById('location');
                         if (locInput) { locInput.focus(); locInput.select(); }
                     });
             },
             err => {
                 btn.disabled = false;
                 txt.textContent = 'Use My Location';
                 icon.classList.remove('hidden');
                 spinner.classList.add('hidden');

                 let msg = 'Location access denied.';
                 if (err && err.code === 1) msg = 'Permission denied – please allow location.';
                 else if (err && err.code === 2) msg = 'Position unavailable.';
                 else if (err && err.code === 3) msg = 'Request timed out.';
                 console.warn('Geolocation error:', err);
                 showToast('error', msg + ' If the problem persists, enter area manually.');
             },
             {
                 // tuning: increase timeout to allow a better fix; keep high accuracy enabled for mobile
                 enableHighAccuracy: true,
                 timeout: 20000,
                 maximumAge: 0
             }
         );
     });
 });

 /* --------------------------------------------------------------
    AUTOCOMPLETE
    -------------------------------------------------------------- */
 function setupAutocomplete(inputId, suggestionId, type) {
     const input = document.getElementById(inputId);
     const suggestions = document.getElementById(suggestionId);

     input.addEventListener('input', async () => {
         const query = input.value.trim();
         if (query.length < 2) {
             suggestions.classList.add('hidden');
             return;
         }

         try {
             const res = await fetch(`autocomplete.php?type=${type}&q=${encodeURIComponent(query)}`);
             const items = await res.json();
             suggestions.innerHTML = '';
             if (items.length === 0) {
                 suggestions.classList.add('hidden');
                 return;
             }

             items.forEach(item => {
                 const div = document.createElement('div');
                 div.textContent = item;
                 div.onclick = () => {
                     input.value = item;
                     suggestions.classList.add('hidden');
                     document.getElementById('searchForm').submit();
                 };
                 suggestions.appendChild(div);
             });
             suggestions.classList.remove('hidden');
         } catch (err) {
             console.error('Autocomplete error:', err);
         }
     });

     document.addEventListener('click', (e) => {
         if (!input.contains(e.target) && !suggestions.contains(e.target)) {
             suggestions.classList.add('hidden');
         }
     });
 }

 setupAutocomplete('gym_name', 'gymNameSuggestions', 'gym');
 setupAutocomplete('city', 'citySuggestions', 'city');
 setupAutocomplete('location', 'locationSuggestions', 'location');

 /* --------------------------------------------------------------
    GALLERY MODAL
    -------------------------------------------------------------- */
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