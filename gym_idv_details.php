<?php
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';

$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die('DB connect error: ' . $conn->connect_error);
}

// Ensure reviews table exists
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

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    $gym_id = (int)($_POST['gym_id'] ?? 0);
    if ($gym_id > 0 && isset($_SESSION['user_id'])) {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $comment = substr(trim($_POST['comment'] ?? ''), 0, 2000);

        $uid = (int)$_SESSION['user_id'];
        $uname = $_SESSION['user_name'] ?? null;

        $ins = $conn->prepare('INSERT INTO gym_reviews (gym_id, user_id, user_name, rating, comment) VALUES (?, ?, ?, ?, ?)');
        $ins->bind_param('iisis', $gym_id, $uid, $uname, $rating, $comment);
        $ins->execute();
        $ins->close();
    }
    header("Location: gym_idv_details.php?gym_id=$gym_id");
    exit;
}
// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $gym_id = (int)$_POST['gym_id'];
    $message = trim($_POST['message'] ?? '');
    if ($gym_id > 0 && !empty($message) && isset($_SESSION['user_id'])) {
        $sender_type = 'user';
        $user_id = (int)$_SESSION['user_id'];
        $ins = $conn->prepare('INSERT INTO gym_messages (gym_id, user_id, sender_type, message) VALUES (?, ?, ?, ?)');
        $ins->bind_param('iiss', $gym_id, $user_id, $sender_type, $message);
        $ins->execute();
        $ins->close();
    }
    header("Location: gym_idv_details.php?gym_id=$gym_id");
    exit;
}

// Get gym ID
$gym_id = (int)($_GET['gym_id'] ?? 0);
if ($gym_id <= 0) {
    http_response_code(400);
    echo "<p class='text-white bg-red-900 p-6 rounded-lg'>Invalid gym ID.</p>";
    exit;
}

// === ONLY FETCH APPROVED GYMS ===
$stmt = $conn->prepare('SELECT * FROM gyms WHERE gym_id = ? AND status = 1 LIMIT 1');
$stmt->bind_param('i', $gym_id);
$stmt->execute();
$res = $stmt->get_result();
$gym = $res->fetch_assoc();
$stmt->close();

if (!$gym) {
    http_response_code(404);
    echo "<div class='max-w-md mx-auto mt-20 text-center'>
            <h1 class='text-4xl font-bold text-gray-400 mb-4'>Gym Not Found</h1>
            <p class='text-gray-500 mb-6'>This gym is either not approved or does not exist.</p>
            <a href='display_gym.php' class='inline-block bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-lg'>Back to Gyms</a>
          </div>";
    exit;
}

// === Continue only if approved ===

function getGymImagesLocal($conn, $gym_id) {
    $out = [];
    $st = $conn->prepare('SELECT filename FROM gym_images WHERE gym_id = ? ORDER BY uploaded_at DESC');
    if ($st) {
        $st->bind_param('i', $gym_id);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $out[] = 'uploads/gyms/' . $row['filename'];
        }
        $st->close();
    }
    return $out;
}

$images = getGymImagesLocal($conn, $gym_id);
if (!empty($gym['gym_image']) && !in_array('uploads/gyms/' . $gym['gym_image'], $images)) {
    array_unshift($images, 'uploads/gyms/' . $gym['gym_image']);
}

// Reviews stats
$stmtR = $conn->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM gym_reviews WHERE gym_id = ?');
$stmtR->bind_param('i', $gym_id);
$stmtR->execute();
$resR = $stmtR->get_result()->fetch_assoc();
$avgRating = $resR['avg'] ? round($resR['avg'], 1) : 0;
$reviewCount = (int)$resR['cnt'];
$stmtR->close();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($gym['gym_name']) ?> — RawFit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto}</style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- NAVIGATION -->
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
            <a href="display_gym.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500/20 text-orange-400">
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
        <a href="home.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-gray-400"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg><span class="text-xs">Home</span></a>
        <a href="nutrition.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-gray-400"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg><span class="text-xs">Nutrition</span></a>
        <a href="trainer.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-gray-400"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="text-xs">Trainers</span></a>
        <a href="display_gym.php" class="flex flex-col items-center space-y-1 px-3 py-2 text-orange-400"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg><span class="text-xs">Gyms</span></a>
    </div>
</nav>

<div class="pt-24">
    <div class="max-w-6xl mx-auto p-6">
        <a href="display_gym.php" class="inline-block mb-4 text-sm text-gray-300 hover:text-white">&larr; Back to gyms</a>

        <div class="bg-gray-800 rounded-2xl overflow-hidden border border-gray-700 shadow-lg">
            <?php if (!empty($images)): ?>
                <div class="h-72 bg-black overflow-hidden">
                    <button class="preview-button w-full h-full block" data-images='<?= htmlspecialchars(json_encode($images), ENT_QUOTES) ?>'>
                        <img src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($gym['gym_name']) ?>" class="w-full h-full object-cover">
                    </button>
                </div>
            <?php else: ?>
                <div class="h-72 bg-gray-700 flex items-center justify-center">
                    <span class="text-gray-400">No image</span>
                </div>
            <?php endif; ?>

            <div class="p-6">
                <h1 class="text-2xl font-bold text-orange-400"><?= htmlspecialchars($gym['gym_name']) ?></h1>
                <p class="text-sm text-gray-400 mt-1"><?= htmlspecialchars($gym['gym_city'] . ' — ' . $gym['location']) ?></p>

                <div class="flex items-center mt-4 space-x-4">
                    <div class="flex items-center">
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <svg class="w-5 h-5 <?= $i <= $avgRating ? 'text-yellow-400 fill-current' : 'text-gray-600' ?>" viewBox="0 0 24 24"><path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z"/></svg>
                        <?php endfor; ?>
                        <span class="ml-2 text-sm text-gray-400">(<?= $reviewCount ?> reviews)</span>
                    </div>
                    <div class="text-sm text-gray-400">Timings: <?= htmlspecialchars($gym['timings'] ?? 'N/A') ?></div>
                </div>

                <p class="mt-4 text-gray-300"><?= nl2br(htmlspecialchars($gym['gym_description'] ?? '')) ?></p>

                <?php if (count($images) > 1): ?>
                    <div class="mt-6 grid grid-cols-3 gap-2">
                        <?php foreach (array_slice($images, 1) as $img): ?>
                            <button class="preview-button w-full h-24 overflow-hidden rounded-lg" data-images='<?= htmlspecialchars(json_encode($images), ENT_QUOTES) ?>'>
                                <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover rounded-lg" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-6 flex space-x-2">
                    <a href="mailto:<?= htmlspecialchars($gym['gym_email'] ?? 'info@example.com') ?>" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">Contact</a>
                    <a href="#reviews" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">See reviews</a>
                </div>
            </div>
        </div>

        <!-- Gym Details -->
        <div class="mt-8 mb-8">
            <h2 class="text-2xl font-bold text-orange-400 mb-4">Gym Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg">
                    <h3 class="text-xl font-bold text-white mb-3">About this gym</h3>
                    <p class="text-gray-300 mb-6 text-sm whitespace-pre-line"><?= nl2br(htmlspecialchars($gym['gym_description'] ?? 'No description.')) ?></p>
                    <div class="mt-4 text-sm text-gray-400">
                        <div>Location: <span class="text-gray-200"><?= htmlspecialchars($gym['gym_city'] ?? '') ?> — <?= htmlspecialchars($gym['location'] ?? '') ?></span></div>
                        <div>Timings: <span class="text-gray-200"><?= htmlspecialchars($gym['timings'] ?? 'N/A') ?></span></div>
                        <div class="mt-2">Phone: <span class="text-gray-200"><?= htmlspecialchars($gym['gym_phone'] ?? '-') ?></span></div>
                        <div>Email: <span class="text-gray-200"><?= htmlspecialchars($gym['gym_email'] ?? '-') ?></span></div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-r from-orange-500 to-red-500 flex items-center justify-center text-white text-lg font-bold">
                            <?= htmlspecialchars(substr($gym['gym_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-white"><?= htmlspecialchars($gym['gym_name']) ?></div>
                            <div class="text-sm text-gray-400"><?= htmlspecialchars($gym['gym_city'] ?? '') ?></div>
                        </div>
                    </div>
                  <?php
                    // … your existing code that fetches $gym …
                    $gym_id = (int)$gym['gym_id'];   // make sure you have the ID
                    ?>

                    <!-- Inside the “Gym Details” card -->
                    <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg">

                        <!-- NEW BUTTON -->
                        <a href="chat.php?gym_id=<?= $gym_id ?>"
                        class="block w-full text-center border-2 border-orange-500 text-orange-300 
                                hover:bg-orange-500 hover:text-white px-4 py-2 rounded-lg font-medium mb-4 
                                transition-colors duration-200">
                        Message Gym
                        </a>

                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-900/40 rounded-lg p-3 text-center"><div class="text-xs text-gray-400">Trainers</div><div class="text-lg font-semibold text-gray-100"><?= $gym['num_trainers'] ?? '-' ?></div></div>
                        <div class="bg-gray-900/40 rounded-lg p-3 text-center"><div class="text-xs text-gray-400">Capacity</div><div class="text-lg font-semibold text-gray-100"><?= $gym['capacity'] ?? '-' ?></div></div>
                        <div class="bg-gray-900/40 rounded-lg p-3 text-center"><div class="text-xs text-gray-400">Registrations</div><div class="text-lg font-semibold text-orange-400"><?= $gym['registrations'] ?? '0' ?></div></div>
                        <div class="bg-gray-900/40 rounded-lg p-3 text-center"><div class="text-xs text-gray-400">Years</div><div class="text-lg font-semibold text-gray-100"><?= $gym['experience_years'] ?? '-' ?></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews -->
        <div id="reviews" class="mt-8">
            <h2 class="text-xl font-semibold text-white mb-3">Add Review</h2>
            <form method="POST" class="bg-gradient-to-br from-gray-800/80 to-gray-800/60 rounded-xl p-4 mb-6 border border-gray-700 shadow-sm">
                <input type="hidden" name="action" value="add_review">
                <input type="hidden" name="gym_id" value="<?= $gym_id ?>">
                <div class="flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-orange-500 to-red-500 flex items-center justify-center text-white font-semibold text-lg"><?= htmlspecialchars(substr($userName,0,1)) ?></div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div class="stars inline-flex items-center">
                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                    <input type="radio" id="star-<?= $s ?>" name="rating" value="<?= $s ?>" class="hidden" <?= $s === 5 ? 'checked' : '' ?>>
                                    <label for="star-<?= $s ?>" class="cursor-pointer text-3xl text-gray-500 hover:text-yellow-400 px-1" data-value="<?= $s ?>">★</label>
                                <?php endfor; ?>
                                <span id="ratingPreview" class="ml-3 text-sm text-gray-300">5/5</span>
                            </div>
                            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-md shadow">Submit</button>
                        </div>
                        <textarea id="reviewComment" name="comment" rows="4" placeholder="Share your experience (2000 characters max)" class="mt-3 w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500" required></textarea>
                        <div class="flex justify-between mt-2 text-xs text-gray-400">
                            <div><span id="charCount">0</span>/2000</div>
                            <div>Be constructive and polite</div>
                        </div>
                    </div>
                </div>
            </form>

            <h2 class="text-xl font-semibold text-white mb-3">Reviews</h2>
            <?php
            $st = $conn->prepare('SELECT user_name, rating, comment, created_at FROM gym_reviews WHERE gym_id = ? ORDER BY created_at DESC LIMIT 50');
            $st->bind_param('i', $gym_id);
            $st->execute();
            $rres = $st->get_result();
            if ($rres->num_rows === 0) {
                echo '<p class="text-gray-400">No reviews yet. Be the first!</p>';
            } else {
                while ($rv = $rres->fetch_assoc()) {
                    $initial = htmlspecialchars(substr($rv['user_name'] ?? 'A', 0, 1));
                    $date = date('M j, Y', strtotime($rv['created_at']));
            ?>
                    <div class="bg-gray-800 rounded-lg p-4 mb-4 border border-gray-700 shadow-sm">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center text-white font-medium"><?= $initial ?></div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-semibold text-white"><?= htmlspecialchars($rv['user_name'] ?? 'Anonymous') ?></div>
                                        <div class="text-xs text-gray-400"><?= $date ?></div>
                                    </div>
                                    <div class="text-sm text-yellow-400 font-medium"><?= str_repeat('★', $rv['rating']) . str_repeat('☆', 5 - $rv['rating']) ?></div>
                                </div>
                                <p class="text-gray-300 mt-3 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
                            </div>
                        </div>
                    </div>
            <?php
                }
            }
            $st->close();
            ?>
        </div>
    </div>
</div>


<!-- GALLERY MODAL -->
<div id="galleryModal" class="fixed inset-0 hidden flex items-center justify-center bg-black/90 z-50 p-4">
    <div class="relative max-w-4xl w-full">
        <button id="galleryClose" class="absolute -top-12 right-0 text-white text-3xl hover:text-gray-300">&times;</button>
        <button id="galleryPrev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300">&lt;</button>
        <button id="galleryNext" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300">&gt;</button>
        <img id="galleryImg" src="" alt="" class="w-full max-h-[80vh] object-contain rounded-xl mx-auto">
        <div id="galleryThumbs" class="flex gap-2 mt-4 overflow-x-auto justify-center pb-2"></div>
    </div>
</div>

<script>
    // Profile dropdown
    const profileBtn = document.getElementById('profileButton');
    const dropdown = document.getElementById('profileDropdown');
    profileBtn?.addEventListener('click', e => { dropdown.classList.toggle('hidden'); e.stopPropagation(); });
    document.addEventListener('click', () => dropdown.classList.add('hidden'));

    // Gallery
    let currentGallery = [], currentIndex = 0;
    const modal = document.getElementById('galleryModal');
    const imgEl = document.getElementById('galleryImg');
    const thumbsEl = document.getElementById('galleryThumbs');

    function openGallery(urls, idx = 0) {
        currentGallery = urls; currentIndex = idx;
        imgEl.src = urls[idx]; renderThumbs(); modal.classList.remove('hidden');
    }
    function renderThumbs() {
        thumbsEl.innerHTML = '';
        currentGallery.forEach((url, i) => {
            const thumb = document.createElement('img');
            thumb.src = url; thumb.className = `w-20 h-14 object-cover rounded-lg cursor-pointer border-2 transition ${i === currentIndex ? 'border-white' : 'border-transparent hover:border-gray-400'}`;
            thumb.onclick = () => { currentIndex = i; imgEl.src = url; renderThumbs(); };
            thumbsEl.appendChild(thumb);
        });
    }
    document.getElementById('galleryPrev').onclick = () => { currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length; imgEl.src = currentGallery[currentIndex]; renderThumbs(); };
    document.getElementById('galleryNext').onclick = () => { currentIndex = (currentIndex + 1) % currentGallery.length; imgEl.src = currentGallery[currentIndex]; renderThumbs(); };
    document.getElementById('galleryClose').onclick = () => modal.classList.add('hidden');
    document.addEventListener('keydown', e => { if (!modal.classList.contains('hidden')) { if (e.key === 'ArrowLeft') document.getElementById('galleryPrev').click(); if (e.key === 'ArrowRight') document.getElementById('galleryNext').click(); if (e.key === 'Escape') document.getElementById('galleryClose').click(); } });
    document.addEventListener('click', e => {
        const btn = e.target.closest('.preview-button');
        if (!btn) return;
        let urls = JSON.parse(btn.dataset.images || '[]');
        let idx = 0;
        if (e.target.tagName === 'IMG') idx = urls.indexOf(e.target.src) >= 0 ? urls.indexOf(e.target.src) : 0;
        openGallery(urls, idx);
    });

    // Star rating
    document.querySelectorAll('.stars label').forEach(lbl => {
        lbl.addEventListener('click', () => {
            const v = lbl.dataset.value;
            lbl.parentElement.querySelectorAll('label').forEach(l => l.classList.toggle('text-yellow-400', l.dataset.value <= v));
            document.getElementById('ratingPreview').textContent = v + '/5';
        });
    });

    // Char counter
    const ta = document.getElementById('reviewComment');
    const cc = document.getElementById('charCount');
    if (ta && cc) ta.addEventListener('input', () => cc.textContent = ta.value.length);
</script>

</body>
</html>
<?php $conn->close(); ?>