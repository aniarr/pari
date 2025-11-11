<?php
require 'config/db.php';
session_start();

// Force login (remove in production)
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // test user
    $_SESSION['username'] = 'aswa';
}
$userId = (int)$_SESSION['user_id'];

// === DELETE HANDLER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_split'])) {
    $splitId = (int)$_POST['split_id'];

    // Verify ownership: original OR personal copy
    $check = $pdo->prepare("
        SELECT 1 FROM workout_splits ws WHERE ws.id = ? AND ws.user_id = ?
        UNION
        SELECT 1 FROM user_workouts uw WHERE uw.split_id = ? AND uw.user_id = ?
    ");
    $check->execute([$splitId, $userId, $splitId, $userId]);

    if ($check->rowCount() > 0) {
        // 1. Remove personal copy
        $pdo->prepare("DELETE FROM user_workouts WHERE split_id = ? AND user_id = ?")
            ->execute([$splitId, $userId]);

        // 2. If user owns original AND no other copies exist → delete base split
        $stmt = $pdo->prepare("SELECT user_id FROM workout_splits WHERE id = ?");
        $stmt->execute([$splitId]);
        $originalOwnerId = $stmt->fetchColumn();

        if ($originalOwnerId == $userId) {
            $copyCheck = $pdo->prepare("SELECT 1 FROM user_workouts WHERE split_id = ? LIMIT 1");
            $copyCheck->execute([$splitId]);
            if ($copyCheck->rowCount() == 0) {
                $pdo->prepare("DELETE FROM workout_splits WHERE id = ?")->execute([$splitId]);
            }
        }
    }

    header("Location: my_splits.php?deleted=1");
    exit();
}

// Get user's name
$stmt = $pdo->prepare("SELECT name FROM register WHERE id = ?");
$stmt->execute([$userId]);
$userRow = $stmt->fetch();
$userName = $userRow['name'] ?? 'User';

// Get user's own splits
$stmt = $pdo->prepare("
    SELECT 
        ws.id,
        ws.name,
        ws.created_at,
        ws.user_id AS original_owner_id,
        uw.id AS user_workout_id,
        CASE WHEN uw.id IS NOT NULL THEN 1 ELSE 0 END AS is_personal_copy,
        CASE WHEN ws.user_id = ? THEN 1 ELSE 0 END AS is_original_owner
    FROM workout_splits ws
    LEFT JOIN user_workouts uw 
           ON uw.split_id = ws.id 
          AND uw.user_id = ?
    WHERE ws.user_id = ? OR uw.user_id = ?
    ORDER BY ws.created_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$splits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Splits - RawFit</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .glass { background: rgba(255,255,255,0.08); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
    .card { transition: all 0.3s ease; }
    .card:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 20px 40px -10px rgba(251,146,60,0.2); }
    .badge-copy { @apply absolute -top-3 -right-3 bg-emerald-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg; }
    .btn-glow { box-shadow: 0 0 15px rgba(251,146,60,0.3); }
    .btn-glow:hover { box-shadow: 0 0 25px rgba(251,146,60,0.5); }
    .btn-delete { background: linear-gradient(to right, #dc2626, #b91c1c); }
    .btn-delete:hover { background: linear-gradient(to right, #b91c1c, #991b1b); }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white min-h-screen p-8">

<!-- Background Orbs -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
  <div class="absolute top-20 left-20 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl"></div>
  <div class="absolute bottom-20 right-20 w-80 h-80 bg-red-600/10 rounded-full blur-3xl"></div>
</div>

<div class="relative z-10 max-w-6xl mx-auto">



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
                <a href="trainer.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Courses</span>
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
                    <p class="text-white font-medium" id="userName"><?= htmlspecialchars($userName) ?></p>
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
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
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

// Profile dropdown
const profileButton = document.getElementById('profileButton');
const profileDropdown = document.getElementById('profileDropdown');
if (profileButton && profileDropdown) {
    profileButton.addEventListener('click', e => {
        e.preventDefault();
        profileDropdown.classList.toggle('hidden');
    });
    document.addEventListener('click', e => {
        if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
}

// Delete confirmation
function confirmDelete(splitId, splitName) {
    if (confirm(`Are you sure you want to delete "${splitName}"? This cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'my_splits.php';
        form.innerHTML = `
            <input type="hidden" name="delete_split" value="1">
            <input type="hidden" name="split_id" value="${splitId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<br><br><br>

<!-- Header -->
<div class="text-center mb-12 mt-20">
  <h1 class="text-5xl md:text-6xl font-bold bg-gradient-to-r from-orange-400 to-red-600 bg-clip-text text-transparent mb-3">
    My Workout Splits
  </h1>
</div>

<!-- No Splits -->
<?php if (empty($splits)): ?>
  <div class="text-center py-20">
    <i class="fas fa-dumbbell text-7xl text-gray-600 mb-6"></i>
    <p class="text-2xl text-gray-400 mb-4">No splits saved yet</p>
    <a href="index.php" class="inline-block bg-gradient-to-r from-orange-500 to-red-600 text-white px-8 py-3 rounded-xl font-bold hover:from-red-600 hover:to-orange-700 transition transform hover:scale-105 shadow-lg">
      Create Your First Split
    </a>
  </div>
<?php else: ?>

  <!-- Splits Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php foreach ($splits as $split): ?>
      <div class="glass p-6 rounded-3xl card border border-gray-700 relative overflow-hidden">

        <!-- Personal Copy Badge -->
        <?php if ($split['is_personal_copy']): ?>
          <span class="badge-copy">Copy</span>
        <?php endif; ?>

        <h3 class="text-2xl font-bold text-white mb-2 pr-8">
          <?= htmlspecialchars($split['name']) ?>
        </h3>
        <p class="text-sm text-gray-400 mb-6">
          Created <?= date('M j, Y', strtotime($split['created_at'])) ?>
        </p>

        <!-- Action Buttons -->
        <div class="flex gap-2 mt-6 flex-wrap">
          <!-- View -->
          <a href="view_split.php?id=<?= $split['id'] ?>"
             class="flex-1 min-w-[100px] bg-gradient-to-r from-orange-500 to-red-600 text-white py-2.5 rounded-xl font-medium text-center hover:from-red-600 hover:to-orange-700 transition btn-glow flex items-center justify-center gap-2">
            <i class="fas fa-eye"></i> View
          </a>

    
          <!-- Delete (only if owned) -->
          <?php if ($split['is_original_owner'] || $split['is_personal_copy']): ?>
            <button onclick="confirmDelete(<?= $split['id'] ?>, '<?= addslashes(htmlspecialchars($split['name'])) ?>')"
                    class="flex-1 min-w-[100px] btn-delete text-white py-2.5 rounded-xl font-medium transition btn-glow flex items-center justify-center gap-2">
              <i class="fas fa-trash"></i> Delete
            </button>
          <?php endif; ?>

          <!-- Save (only if NOT owned) -->
          <?php if (!$split['is_personal_copy'] && !$split['is_original_owner']): ?>
            <form method="post" action="view_split.php?id=<?= $split['id'] ?>" class="flex-1">
              <button type="submit" name="save_split" value="1"
                      class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-2.5 rounded-xl font-medium hover:from-emerald-600 hover:to-green-700 transition btn-glow flex items-center justify-center gap-2">
                <i class="fas fa-save"></i> Save
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

</div>
</body>
</html>