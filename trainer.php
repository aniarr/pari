<?php
// trainer_courses.php
// Full page: trainer/course listing + filtering + booking modal
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection (mysqli used in your original)
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get logged-in user name
$user_id = $_SESSION['user_id'];
$sql = "SELECT name FROM register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$userName = "User"; // Default name
if ($row = $result->fetch_assoc()) {
    $userName = $row['name'];
}
$stmt->close();

// Fetch trainer courses
$trainers = [];
$sql = "SELECT id, trainer_id, title, description, category, duration, start_date, end_date, image_path, created_at 
        FROM trainer_courses
        ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Use title as trainer/course name (you can change to actual trainer name if available)
        $rating = rand(4, 5);
        $price = rand(60, 85);
        $duration = (int)$row['duration'] * 7;

        $trainers[] = [
            'id' => $row['id'],
            'name' => $row['title'],
            'specialty' => $row['category'],
            'description' => $row['description'],
            'rating' => $rating,
            'price' => $price,
            'duration' => $duration,
            'image' => $row['image_path'] ? "uploads/" . $row['image_path'] : "https://via.placeholder.com/800x600?text=Course+Image",
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'created_at' => $row['created_at']
        ];
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RawFit - Select Trainer / Courses</title>

    <!-- Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-white">

<!-- Navigation (kept consistent with your original) -->
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
                <a href="home.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span>Home</span>
                </a>
                <a href="nutrition.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Nutrition</span>
                </a>
                <a href="trainer.php" class="nav-link active flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Trainers</span>
                </a>
                <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Gyms</span>
                </a>
                <a href="workout_view.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Workout</span>
                </a>
            </div>

            <!-- User Info -->
            <div class="relative flex items-center space-x-4">
                <div class="hidden sm:block text-right">
                    <p class="text-white font-medium" id="userName"><?php echo htmlspecialchars($userName); ?></p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center cursor-pointer" id="profileButton">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>

                <!-- Dropdown Menu -->
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden z-50">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
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
                <span class="text-xs">Nutrition</span>
            </a>
            <a href="trainer.php" class="mobile-nav-link active flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="text-xs">Trainer</span>
            </a>
        </div>
    </div>
</nav>

<!-- Page padding to account for fixed nav -->
<div class="pt-20"></div>

<main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
            Select Your <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">Trainer</span>
        </h1>
        <p class="text-gray-400 text-lg">Book sessions with our certified expert trainers</p>
    </div>

    <!-- Filter Bar -->
    <div class="mb-8 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 sm:p-6 border border-gray-700">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" id="searchTrainers" placeholder="Search trainers..." class="w-full bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <select id="specialtyFilter" class="bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">All Specialties</option>
                    <option value="strength">Strength Training</option>
                    <option value="cardio">Cardio & HIIT</option>
                    <option value="yoga">Yoga & Flexibility</option>
                    <option value="boxing">Boxing</option>
                    <option value="crossfit">CrossFit</option>
                </select>
            </div>
            <div>
                <select id="ratingFilter" class="bg-gray-700/50 border border-gray-600 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">All Ratings</option>
                    <option value="5">5 Stars</option>
                    <option value="4">4+ Stars</option>
                    <option value="3">3+ Stars</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Trainers Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6" id="trainersGrid">
        <?php if (empty($trainers)): ?>
            <div class="col-span-full text-center py-12 text-gray-400">
                <h3 class="text-xl font-semibold text-gray-300 mb-2">No trainers available</h3>
                <p class="text-gray-500">Please check back later.</p>
            </div>
        <?php else: ?>
            <?php foreach ($trainers as $trainer): ?>
                <div class="trainer-card bg-gray-800/50 backdrop-blur-sm rounded-xl overflow-hidden border border-gray-700 hover:bg-gray-800/70 transition-all duration-300 group"
                     data-name="<?php echo htmlspecialchars(strtolower($trainer['name'])); ?>"
                     data-specialty="<?php echo htmlspecialchars(strtolower($trainer['specialty'])); ?>"
                     data-rating="<?php echo (int)$trainer['rating']; ?>">
                    <div class="relative">
                        <img src="<?php echo htmlspecialchars($trainer['image']); ?>" alt="<?php echo htmlspecialchars($trainer['name']); ?>" class="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-white mb-2 trainer-name"><?php echo htmlspecialchars($trainer['name']); ?></h3>
                        <p class="text-orange-400 font-medium mb-2 trainer-specialty"><?php echo htmlspecialchars($trainer['specialty']); ?></p>
                        <div class="flex items-center space-x-2 mb-3">
                            <div class="flex text-yellow-400">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <span class="text-gray-400 text-sm">(<?php echo (int)$trainer['rating']; ?> Stars)</span>
                        </div>
                        <p class="text-gray-400 text-sm mb-4"><?php echo htmlspecialchars($trainer['description']); ?></p>

                        <div class="flex gap-3">
                            <a href="user_booking.php?course_id=<?php echo (int)$trainer['id']; ?>" class="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 text-center block">
                                View Details
                            </a>

                            <button type="button" onclick="bookTrainer('<?php echo addslashes(htmlspecialchars($trainer['name'])); ?>','<?php echo addslashes(htmlspecialchars($trainer['specialty'])); ?>', '<?php echo (int)$trainer['price']; ?>')"
                                    class="w-36 bg-emerald-600 hover:bg-emerald-700 rounded-lg py-3 px-3 font-semibold transition">
                                Book
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="fixed inset-0 z-60 flex items-center justify-center bg-black/60 hidden">
        <div class="w-full max-w-2xl bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-xl">
            <div class="p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 id="modalTrainerName" class="text-2xl font-bold text-white">Trainer</h2>
                        <p id="modalTrainerSpecialty" class="text-gray-400">Specialty</p>
                    </div>
                    <button onclick="closeBookingModal()" class="text-gray-400 hover:text-white">&times;</button>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Date</label>
                        <input id="sessionDate" type="date" class="w-full bg-gray-800/60 border border-gray-700 px-3 py-2 rounded text-white">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Time</label>
                        <input id="sessionTime" type="time" class="w-full bg-gray-800/60 border border-gray-700 px-3 py-2 rounded text-white">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm text-gray-300 mb-1">Notes (optional)</label>
                    <textarea id="sessionNotes" rows="4" class="w-full bg-gray-800/60 border border-gray-700 px-3 py-2 rounded text-white" placeholder="Any preferences or details..."></textarea>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <div id="modalTrainerPrice" class="text-gray-300 font-semibold">$0/session</div>
                    <div class="flex items-center gap-3">
                        <button onclick="closeBookingModal()" class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600">Cancel</button>
                        <button onclick="confirmBooking()" class="px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white font-semibold">Confirm Booking</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top -->
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
        class="fixed bottom-8 right-8 w-12 h-12 rounded-full bg-gradient-to-t from-orange-500 to-orange-400 
               flex items-center justify-center shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 
               border border-orange-300/20 opacity-0 invisible"
        id="backToTopBtn" aria-label="Back to top">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-white">
            <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
    </button>
</main>

<script>
/* Profile dropdown */
(function(){
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', (e) => {
            e.preventDefault();
            profileDropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
})();

/* Navigation highlight (keeps current behavior) */
function updateNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

    [...navLinks, ...mobileNavLinks].forEach(link => {
        link.classList.remove('active', 'bg-orange-500', 'text-white', 'text-orange-500');
    });

    // Mark trainer link active
    const trainerLinks = document.querySelectorAll('a[href="trainer.php"], a[href="trainer"]');
    trainerLinks.forEach(link => {
        if (link.classList.contains('mobile-nav-link')) {
            link.classList.add('active', 'text-orange-500');
            link.classList.remove('text-gray-400');
        } else {
            link.classList.add('active', 'bg-orange-500', 'text-white');
            link.classList.remove('text-gray-300');
        }
    });
}

/* Back to top visibility */
const backToTopBtn = document.getElementById('backToTopBtn');
window.addEventListener('scroll', () => {
    if (!backToTopBtn) return;
    if (window.scrollY > 300) {
        backToTopBtn.classList.remove('opacity-0', 'invisible');
        backToTopBtn.classList.add('opacity-100', 'visible');
    } else {
        backToTopBtn.classList.remove('opacity-100', 'visible');
        backToTopBtn.classList.add('opacity-0', 'invisible');
    }
});

/* Filtering logic */
document.addEventListener('DOMContentLoaded', function() {
    updateNavigation();

    const searchInput = document.getElementById('searchTrainers');
    const specialtyFilter = document.getElementById('specialtyFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    const trainersGrid = document.getElementById('trainersGrid');

    function filterTrainers() {
        const searchTerm = (searchInput && searchInput.value || '').trim().toLowerCase();
        const specialtyValue = (specialtyFilter && specialtyFilter.value || '').trim().toLowerCase();
        const ratingValue = parseInt(ratingFilter && ratingFilter.value) || 0;

        const trainerCards = document.querySelectorAll('.trainer-card');
        let visibleCount = 0;

        trainerCards.forEach(card => {
            const name = (card.dataset.name || '').toLowerCase();
            const specialty = (card.dataset.specialty || '').toLowerCase();
            const rating = parseInt(card.dataset.rating) || 0;

            let visible = true;

            // Search matches name or specialty text
            if (searchTerm) {
                if (!(name.includes(searchTerm) || specialty.includes(searchTerm) || (card.querySelector('.trainer-name') && card.querySelector('.trainer-name').textContent.toLowerCase().includes(searchTerm)))) {
                    visible = false;
                }
            }

            if (specialtyValue && specialty !== specialtyValue) {
                visible = false;
            }

            if (ratingValue && rating < ratingValue) {
                visible = false;
            }

            card.style.display = visible ? 'block' : 'none';
            if (visible) visibleCount++;
        });

        // No results message
        const existing = document.getElementById('noResultsMessage');
        if (existing) existing.remove();

        if (visibleCount === 0) {
            const div = document.createElement('div');
            div.id = 'noResultsMessage';
            div.className = 'col-span-full text-center py-12 text-gray-400';
            div.innerHTML = `<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="mx-auto mb-4 opacity-50"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><h3 class="text-xl font-semibold text-gray-300 mb-2">No trainers found</h3><p class="text-gray-500">Try adjusting your search criteria or filters</p>`;
            trainersGrid.appendChild(div);
        }
    }

    // Hook events
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterTrainers, 160));
    }
    if (specialtyFilter) {
        specialtyFilter.addEventListener('change', filterTrainers);
    }
    if (ratingFilter) {
        ratingFilter.addEventListener('change', filterTrainers);
    }

    // Initial run
    filterTrainers();

    // Observe for dynamically added cards and re-filter
    const mo = new MutationObserver((mutations) => {
        let added = false;
        for (const m of mutations) {
            if (m.addedNodes && m.addedNodes.length) {
                for (const n of m.addedNodes) {
                    if (n.nodeType === 1 && (n.classList.contains('trainer-card') || n.querySelector && n.querySelector('.trainer-card'))) {
                        added = true; break;
                    }
                }
            }
            if (added) break;
        }
        if (added) filterTrainers();
    });
    mo.observe(trainersGrid, { childList: true, subtree: true });

});

/* Debounce utility */
function debounce(fn, wait) {
    let t;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}

/* Booking modal functions */
window.bookTrainer = function(name, specialty, price) {
    window.selectedTrainer = { name, specialty, price };
    document.getElementById('modalTrainerName').textContent = name;
    document.getElementById('modalTrainerSpecialty').textContent = specialty;
    document.getElementById('modalTrainerPrice').textContent = `$${price}/session`;
    const modal = document.getElementById('bookingModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // set min date to today
        const dateInput = document.getElementById('sessionDate');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
        }
    }
};

window.closeBookingModal = function() {
    const modal = document.getElementById('bookingModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        clearBookingForm();
    }
};

function clearBookingForm() {
    const d = document.getElementById('sessionDate');
    const t = document.getElementById('sessionTime');
    const n = document.getElementById('sessionNotes');
    if (d) d.value = '';
    if (t) t.value = '';
    if (n) n.value = '';
}

function confirmBooking() {
    const date = document.getElementById('sessionDate').value;
    const time = document.getElementById('sessionTime').value;
    const notes = document.getElementById('sessionNotes').value;

    if (!date) {
        showNotification('Please select a date', 'error'); return;
    }
    if (!time) {
        showNotification('Please select a time', 'error'); return;
    }

    const booking = {
        trainer: window.selectedTrainer || {},
        date, time, notes,
        bookingId: 'BK' + Date.now(),
        status: 'confirmed'
    };

    saveBooking(booking);
    showNotification(`Session booked with ${booking.trainer.name} for ${formatDate(date)} at ${formatTime(time)}`, 'success');
    closeBookingModal();
    addToRecentActivity(`Booked session with ${booking.trainer.name}`, 'just now', 'booking');
}

/* Lightweight localStorage booking/save (demo) */
function saveBooking(booking) {
    try {
        const existing = JSON.parse(localStorage.getItem('trainerBookings') || '[]');
        existing.unshift(booking);
        localStorage.setItem('trainerBookings', JSON.stringify(existing));
    } catch (e) {
        console.error(e);
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const date = new Date(2000, 0, 1, hours || 0, minutes || 0);
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function addToRecentActivity(activity, time, type) {
    try {
        const recent = JSON.parse(localStorage.getItem('recentActivity') || '[]');
        recent.unshift({ activity, time, type });
        recent.splice(10);
        localStorage.setItem('recentActivity', JSON.stringify(recent));
    } catch (e) { console.error(e); }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : (type === 'error' ? 'bg-red-500' : 'bg-blue-500')
    } text-white font-medium max-w-sm`;
    notification.textContent = message;
    notification.style.transform = 'translateX(100%)';
    notification.style.opacity = '0';
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
        notification.style.opacity = '1';
    }, 100);

    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) document.body.removeChild(notification);
        }, 300);
    }, 3800);
}

/* Close modal on Escape or click outside */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeBookingModal();
});
document.addEventListener('click', (e) => {
    const modal = document.getElementById('bookingModal');
    if (modal && !modal.classList.contains('hidden') && e.target === modal) {
        closeBookingModal();
    }
});
</script>
</body>
</html>
