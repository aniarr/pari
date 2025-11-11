<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get user name
$stmt = $conn->prepare("SELECT name FROM register WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userName = $result->num_rows > 0 ? $result->fetch_assoc()['name'] : "User";
$stmt->close();

// Fetch only APPROVED courses with trainer name
$sql = "
    SELECT 
        tc.id, tc.title, tc.description, tc.category, tc.duration, 
        tc.start_date, tc.end_date, tc.image_path, tc.created_at,
        td.name AS trainer_name
    FROM trainer_courses tc
    JOIN trainer_details td ON tc.trainer_id = td.id
    WHERE tc.status = 'approved'
    ORDER BY tc.created_at DESC
";
$courses = [];
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'trainer_name' => $row['trainer_name'],
            'category' => $row['category'],
            'description' => $row['description'],
            'duration' => (int)$row['duration'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'image' => $row['image_path'] ? "uploads/" . $row['image_path'] : "https://via.placeholder.com/800x600?text=No+Image",
            'created_at' => $row['created_at']
        ];
    }
}

// Get unique categories for filter
$categories = array_unique(array_column($courses, 'category'));
sort($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RawFit - Book Courses</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#F97316',
                        'primary-hover': '#FBA63C'
                    }
                }
            }
        }
    </script>
    <style>
        .glass { backdrop-filter: blur(12px); background: rgba(30, 30, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); }
        .gradient-text { background: linear-gradient(to right, #F97316, #FBA63C); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-gray-900 to-black text-white min-h-screen">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M6.5 6.5h11v11h-11z"/><path d="M6.5 6.5L2 2"/><path d="M17.5 6.5L22 2"/><path d="M6.5 17.5L2 22"/><path d="M17.5 17.5L22 22"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-xl">RawFit</span>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="home.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                    <span>Home</span>
                </a>
                <a href="nutrition.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                    <span>Nutrition</span>
                </a>
                <a href="trainer.php" class="nav-link active flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Courses</span>
                </a>
                <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                    <span>Gyms</span>
                </a>
                <a href="workout_view.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                    <span>Workout</span>
                </a>
            </div>

            <div class="relative flex items-center space-x-4">
                <div class="hidden sm:block text-right">
                    <p class="text-white font-medium" id="userName"><?= htmlspecialchars($userName) ?></p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center cursor-pointer" id="profileButton">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden z-50">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition">Logout</a>
                </div>
            </div>
        </div>

        <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="trainer_courses.php" class="mobile-nav-link active flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="text-xs">Courses</span>
            </a>
        </div>
    </div>
</nav>

<div class="pt-20"></div>

<main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
            Book Your <span class="gradient-text">Fitness Course</span>
        </h1>
        <p class="text-gray-400 text-lg">Join expert-led programs and transform your fitness</p>
    </div>

    <!-- Alert -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="glass rounded-xl p-4 mb-6 border-l-4 <?= strpos($_SESSION['msg'], 'success') !== false ? 'border-green-500 bg-green-900/30' : 'border-red-500 bg-red-900/30' ?>">
            <p class="text-sm font-medium flex items-center gap-2">
                <i class="fas <?= strpos($_SESSION['msg'], 'success') !== false ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400' ?>"></i>
                <?= htmlspecialchars($_SESSION['msg']) ?>
            </p>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="mb-8 glass rounded-xl p-4 sm:p-6 border border-gray-700">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" id="searchInput" placeholder="Search by course or trainer..." class="w-full bg-gray-800/80 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <select id="categoryFilter" class="bg-gray-800/80 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars(strtolower($cat)) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Courses Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6" id="coursesGrid">
        <?php if (empty($courses)): ?>
            <div class="col-span-full text-center py-12 text-gray-400">
                <h3 class="text-xl font-semibold text-gray-300 mb-2">No courses available</h3>
                <p class="text-gray-500">Check back later for new programs.</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $c): ?>
                <div class="course-card glass rounded-xl overflow-hidden border border-gray-700 hover:border-primary transition-all"
                     data-title="<?= htmlspecialchars(strtolower($c['title'])) ?>"
                     data-trainer="<?= htmlspecialchars(strtolower($c['trainer_name'])) ?>"
                     data-category="<?= htmlspecialchars(strtolower($c['category'])) ?>">
                    <img src="<?= $c['image'] ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-56 object-cover">
                    <div class="p-5">
                        <h3 class="text-lg font-bold text-white mb-1"><?= htmlspecialchars($c['title']) ?></h3>
                        <p class="text-primary text-sm mb-2">by <?= htmlspecialchars($c['trainer_name']) ?></p>
                        <p class="text-gray-400 text-sm mb-3 line-clamp-2"><?= htmlspecialchars($c['description']) ?></p>
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
                            <span><i class="fas fa-calendar"></i> <?= date('M d', strtotime($c['start_date'])) ?> - <?= date('M d', strtotime($c['end_date'])) ?></span>
                            <span><i class="fas fa-clock"></i> <?= $c['duration'] ?> weeks</span>
                        </div>
                      
                        <div class="flex gap-3">
                            <a href="user_booking.php?course_id=<?= (int)$c['id'] ?>"
                               class="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 text-center block">
                                View Details & Message 
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Back to Top -->
<button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
    class="fixed bottom-8 right-8 w-12 h-12 rounded-full bg-gradient-to-t from-primary to-primary-hover flex items-center justify-center shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 border border-orange-300/20 opacity-0 invisible"
    id="backToTopBtn">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-white">
        <path d="M12 19V5M5 12l7-7 7 7"/>
    </svg>
</button>

<script>
// Profile dropdown
document.getElementById('profileButton')?.addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('profileDropdown').classList.toggle('hidden');
});
document.addEventListener('click', () => {
    document.getElementById('profileDropdown').classList.add('hidden');
});

// Filter
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('searchInput');
    const catFilter = document.getElementById('categoryFilter');
    const grid = document.getElementById('coursesGrid');

    function filter() {
        const term = search.value.toLowerCase();
        const cat = catFilter.value;

        document.querySelectorAll('.course-card').forEach(card => {
            const title = card.dataset.title;
            const trainer = card.dataset.trainer;
            const category = card.dataset.category;

            const matchesSearch = !term || title.includes(term) || trainer.includes(term);
            const matchesCat = !cat || category === cat;

            card.style.display = (matchesSearch && matchesCat) ? 'block' : 'none';
        });

        // No results
        if (!grid.querySelector('.course-card[style="display: block;"], .course-card[style=""]')) {
            if (!document.getElementById('noResults')) {
                grid.innerHTML += `<div id="noResults" class="col-span-full text-center py-12 text-gray-400">
                    <h3 class="text-xl font-semibold text-gray-300 mb-2">No courses found</h3>
                    <p class="text-gray-500">Try adjusting your filters.</p>
                </div>`;
            }
        } else {
            document.getElementById('noResults')?.remove();
        }
    }

    search?.addEventListener('input', debounce(filter, 200));
    catFilter?.addEventListener('change', filter);
    filter();
});

// Back to top
window.addEventListener('scroll', () => {
    const btn = document.getElementById('backToTopBtn');
    if (window.scrollY > 300) {
        btn.classList.remove('opacity-0', 'invisible');
        btn.classList.add('opacity-100', 'visible');
    } else {
        btn.classList.remove('opacity-100', 'visible');
        btn.classList.add('opacity-0', 'invisible');
    }
});

function debounce(fn, wait) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

// Auto-hide alert
setTimeout(() => {
    const alert = document.querySelector('.glass.border-l-4');
    if (alert) alert.style.opacity = '0';
}, 4000);
</script>

</body>
</html>
<?php $conn->close(); ?>