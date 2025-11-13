<?php
session_start();

// Check login
if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainer_login.php");
    exit();
}

// Database connection (PDO)
$host = 'localhost';
$dbname = 'rawfit';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$trainer_id = $_SESSION['trainer_id'];

// Fetch trainer details for navbar
$stmt = $pdo->prepare("SELECT name, trainer_image FROM trainer_details WHERE id = ?");
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);

// Default avatar
$trainer_name = $trainer['name'] ?? 'Trainer';
$trainer_image = !empty($trainer['trainer_image']) ? $trainer['trainer_image'] : 'default-avatar.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit - Downloaders</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'inter': ['Inter', 'sans-serif'] },
                    colors: { primary: '#F97316', secondary: '#EF4444' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 font-inter text-white min-h-screen">

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-lg flex items-center justify-center">
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

                <!-- Desktop Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="trainerman.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9,22 9,12 15,12 15,22"/>
                        </svg>
                        <span>Home</span>
                    </a>
                    <a href="manage-courses.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Courses</span>
                    </a>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profile-menu-button" class="flex items-center space-x-3 text-sm rounded-lg px-3 py-2 text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <img class="h-8 w-8 rounded-full object-cover"
                             src="uploads/<?php echo htmlspecialchars($trainer_image); ?>"
                             alt="<?php echo htmlspecialchars($trainer_name); ?>">
                        <span class="hidden md:block font-medium"><?php echo htmlspecialchars($trainer_name); ?></span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-sm rounded-xl border border-gray-700 py-1 z-50">
                        <a href="trainer_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            View Profile
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-red-600/20 hover:text-red-400 transition-colors">
                            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile Nav -->
            <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
                <a href="trainerman.php" class="flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span class="text-xs">Home</span>
                </a>
                <a href="manage-courses.php" class="flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span class="text-xs">Courses</span>
                </a>
            </div>
        </div>
    </nav>
<br><br>
    <!-- Main Content -->
    <main class="pt-20 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">
                    Downloaders - <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary">
                        <?php echo htmlspecialchars(explode(" ", $trainer_name)[0]); ?>
                    </span>
                </h1>
                <p class="text-gray-400 text-lg">Users who downloaded your course materials.</p>
            </div>

            <!-- Downloaders List -->
            <div class="bg-gray-800/50 backdrop-blur-sm ml-10 mr-10 rounded-xl p-6 border border-gray-700">
                <h2 class="text-xl font-semibold text-white mb-5">All Downloaders</h2>

                <?php
                // Query: Get latest download per user + course name
                $sql = "
                    SELECT 
                        r.name AS user_name,
                        tc.title AS course_title,
                        MAX(cd.downloaded_at) AS latest_download
                    FROM course_downloads cd
                    JOIN trainer_courses tc ON tc.id = cd.course_id
                    JOIN register r ON r.id = cd.user_id
                    WHERE tc.trainer_id = ?
                    GROUP BY cd.user_id, cd.course_id
                    ORDER BY latest_download DESC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$trainer_id]);
                $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($downloads) {
                    echo '<div class="space-y-4">';
                    foreach ($downloads as $dl) {
                        $time = date('M j, Y g:i A', strtotime($dl['latest_download']));
                        $course = htmlspecialchars($dl['course_title']);
                        $user = htmlspecialchars($dl['user_name']);
                        echo <<<HTML
                        <div class="p-4 bg-gray-700/30 rounded-lg border border-gray-600">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-white">$user</div>
                                    <div class="text-sm text-primary">Course: $course</div>
                                </div>
                                <div class="text-right text-xs text-gray-400">
                                    Last Download<br>
                                    <span class="text-gray-300">$time</span>
                                </div>
                            </div>
                        </div>
                        HTML;
                    }
                    echo '</div>';
                } else {
                    echo '<p class="text-gray-400 text-center py-8">No downloads recorded yet.</p>';
                }
                ?>
            </div>
        </div>
    </main>

    <!-- Profile Dropdown Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('profile-menu-button');
            const dropdown = document.getElementById('profile-dropdown');

            button?.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>