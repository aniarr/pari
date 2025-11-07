<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check login + fetch user name
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php?error=login_required");
    exit;
}
$user_id = intval($_SESSION['user_id']);

// Fetch user name from `register` table (based on your DB structure)
$userName = "Guest";
$stmt = $conn->prepare("SELECT name FROM register WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($userName);
$stmt->fetch();
$stmt->close();

$msg = "";

// ================== DELETE REEL ==================
if (isset($_POST['delete_reel']) && isset($_POST['delete_reel_id'])) {
    $reel_id = intval($_POST['delete_reel_id']);
    $stmt = $conn->prepare("SELECT video_url FROM reels WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $reel_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $file_path = __DIR__ . '/' . $row['video_url'];
        if (file_exists($file_path)) unlink($file_path);
        $del_stmt = $conn->prepare("DELETE FROM reels WHERE id=? AND user_id=?");
        $del_stmt->bind_param("ii", $reel_id, $user_id);
        $del_stmt->execute();
        $del_stmt->close();
        $msg = "Reel deleted.";
    }
    $stmt->close();
}

// ================== UPLOAD REEL ==================
if (isset($_FILES['reel_video']) && $_FILES['reel_video']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['reel_video'];
    $max_size = 32 * 1024 * 1024; // 32MB
    $allowed_ext = ['mp4', 'webm', 'mov', 'ogg', 'mkv', 'm4v'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext)) {
        $msg = "Invalid file type.";
    } elseif ($file['size'] > $max_size) {
        $msg = "File too large. Max 32MB.";
    } else {
        $upload_rel = "uploads/reels";
        $upload_dir = __DIR__ . "/" . $upload_rel . "/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $uniq = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
        $target_path = $upload_dir . $uniq;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $caption = trim($_POST['caption'] ?? '');
            $supplement_tag = trim($_POST['supplement_tag'] ?? '');
            $calories_info = trim($_POST['calories_info'] ?? '');
            $video_path_db = $upload_rel . "/" . $uniq;

            $stmt = $conn->prepare("INSERT INTO reels (user_id, video_url, caption, supplement_tag, calories_info, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issss", $user_id, $video_path_db, $caption, $supplement_tag, $calories_info);
            if ($stmt->execute()) {
                $msg = "Upload successful! Reel ID: " . $stmt->insert_id;
            } else {
                unlink($target_path);
                $msg = "DB error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $msg = "Upload failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Bitz - RawFit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #0f172a;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
        }
        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
        }
        .input, .select {
            background-color: #334155;
            border: 1px solid #475569;
            border-radius: 8px;
            color: #e2e8f0;
            padding: 0.75rem 1rem;
        }
        .input:focus, .select:focus {
            outline: none;
            ring: 2px solid #f97316;
            border-color: #f97316;
        }
        .btn-primary {
            background: linear-gradient(to right, #f97316, #ef4444);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }
        .btn-edit {
            background-color: #3b82f6;
            color: white;
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            text-align: center;
        }
        .btn-delete {
            background-color: #ef4444;
            color: white;
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }
        .status-pending { color: #fbbf24; }
        .status-approved { color: #34d399; }
        .status-rejected { color: #f87171; }
        .text-gradient {
            background: linear-gradient(to right, #f97316, #ef4444);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-link {
            transition: all 0.2s;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .mobile-nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .mobile-nav-link.active {
            color: #f97316;
        }
        #profileDropdown {
            display: none;
        }
        #profileDropdown.show {
            display: block;
        }
    </style>
</head>
<body class="p-4 md:p-6 lg:p-8">

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

            <!-- Desktop Nav -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="home.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span>Home</span>
                </a>
                <a href="nutrition.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12h4l3 9 4-18 3 9h4"/>
                    </svg>
                    <span>Nutrition</span>
                </a>
                <a href="trainer.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Trainers</span>
                </a>
                <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <span>Gyms</span>
                </a>
            </div>

            <!-- User Profile -->
            <div class="relative">
                <div class="flex items-center space-x-3 cursor-pointer" id="profileButton">
                    <div class="hidden sm:block text-right">
                        <p class="text-white font-medium text-sm"><?= htmlspecialchars($userName) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                </div>
            </div>
        </div>

        <!-- Mobile Nav -->
        <div class="md:hidden flex justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 text-orange-500">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="nutrition.php" class="mobile-nav-link flex flex-col items-center space-y-1 text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h4l3 9 4-18 3 9h4"/>
                </svg>
                <span class="text-xs">Nutrition</span>
            </a>
            <a href="trainer.php" class="mobile-nav-link flex flex-col items-center space-y-1 text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="text-xs">Trainers</span>
            </a>
            <a href="display_gym.php" class="mobile-nav-link flex flex-col items-center space-y-1 text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span class="text-xs">Gyms</span>
            </a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto mt-24">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">
            <span class="text-white">Upload Your</span> 
            <span class="text-gradient"> Bitz</span>
        </h1>
        <p class="text-gray-400">Share your fitness moments with the community</p>
    </div>

    <!-- Message -->
    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-lg <?= strpos($msg, 'success') !== false ? 'bg-green-900/20 border border-green-700 text-green-300' : 'bg-red-900/20 border border-red-700 text-red-300' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

  <!-- Upload Form -->
<div class="card p-6 md:p-8 mb-12 shadow-xl">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-600 rounded-full flex items-center justify-center shadow-lg">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-white">Create New Bitz</h2>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Video Upload -->
        <div class="group">
            <label class="block text-sm font-semibold text-gray-300 mb-3">
                Video File <span class="text-red-400">*</span>
            </label>
            <div class="relative">
                <input type="file" name="reel_video" accept="video/*" required 
                       class="block w-full text-sm text-gray-300 file:mr-4 file:py-3 file:px-6 
                              file:rounded-full file:border-0 file:text-sm file:font-medium
                              file:bg-gradient-to-r file:from-orange-500 file:to-red-600 file:text-white
                              hover:file:from-orange-600 hover:file:to-red-700 cursor-pointer
                              bg-gray-800/50 border border-gray-700 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-orange-500 transition-all">
                <p class="mt-2 text-xs text-gray-500">Max 32MB • MP4, WebM, MOV</p>
            </div>
        </div>

        <!-- Caption -->
        <div>
            <label class="block text-sm font-semibold text-gray-300 mb-3">Caption</label>
            <textarea name="caption" rows="3" placeholder="What's your workout vibe today? 💪" 
                      class="input w-full resize-none placeholder-gray-500 focus:ring-2 focus:ring-orange-500"></textarea>
        </div>

        <!-- Supplement & Calories -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Supplement Tag
                </label>
                <input type="text" name="supplement_tag" placeholder="e.g., Whey Protein, Creatine" 
                       class="input w-full focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                    </svg>
                    Calories Burned
                </label>
                <input type="text" name="calories_info" placeholder="e.g., 450 kcal" 
                       class="input w-full focus:ring-2 focus:ring-red-500">
            </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-4">
            <button type="submit" class="btn-primary w-full md:w-auto px-10 py-3.5 text-lg font-bold 
                   flex items-center justify-center gap-3 transform transition-all duration-200 hover:scale-105">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Upload Bitz
            </button>
        </div>
    </form>
</div>

    <!-- My Bitz -->
    <h2 class="text-2xl font-bold mb-6 text-white">My Bitz</h2>
    
    <?php
    $stmt_reels = $conn->prepare("SELECT * FROM reels WHERE user_id=? ORDER BY created_at DESC");
    $stmt_reels->bind_param("i", $user_id);
    $stmt_reels->execute();
    $result = $stmt_reels->get_result();
    
    if ($result->num_rows === 0): ?>
        <div class="text-center py-12">
            <p class="text-gray-400 text-lg">No Bitz uploaded yet. Start sharing!</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ($reel = $result->fetch_assoc()): 
                $reel_id = $reel['id'];
                $likeCount = $conn->query("SELECT COUNT(*) as c FROM reel_likes WHERE reel_id=$reel_id")->fetch_assoc()['c'];
                $comment_sql = "SELECT rc.comment, rg.name AS commenter 
                                FROM reel_comments rc 
                                JOIN register rg ON rc.user_id = rg.id 
                                WHERE rc.reel_id = $reel_id 
                                ORDER BY rc.created_at DESC LIMIT 2";
                $comments = $conn->query($comment_sql);
            ?>
            <div class="card p-5 flex flex-col h-full">
                <video class="w-full rounded-lg mb-3" controls>
                    <source src="<?= htmlspecialchars($reel['video_url']) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                
                <div class="flex-1 space-y-2">
                    <p class="font-medium text-white line-clamp-2">
                        <?= htmlspecialchars($reel['caption'] ?: 'No caption') ?>
                    </p>
                    <?php if ($reel['supplement_tag']): ?>
                        <p class="text-sm text-orange-400">Tag: <?= htmlspecialchars($reel['supplement_tag']) ?></p>
                    <?php endif; ?>
                    <?php if ($reel['calories_info']): ?>
                        <p class="text-sm text-red-400">Calories: <?= htmlspecialchars($reel['calories_info']) ?></p>
                    <?php endif; ?>
                    <p class="text-sm">
                        Status: <span class="status-<?= htmlspecialchars($reel['status']) ?> font-medium">
                            <?= ucfirst(htmlspecialchars($reel['status'])) ?>
                        </span>
                    </p>
                    
                    <p class="text-pink-400 text-sm">Heart <?= $likeCount ?> <?= $likeCount == 1 ? 'like' : 'likes' ?></p>
                    
                    <?php if ($comments && $comments->num_rows > 0): ?>
                        <div class="text-xs text-gray-400 space-y-1">
                            <?php while($c = $comments->fetch_assoc()): ?>
                                <p class="line-clamp-1">
                                    <span class="font-medium"><?= htmlspecialchars($c['commenter']) ?>:</span>
                                    <?= htmlspecialchars($c['comment']) ?>
                                </p>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex gap-2 mt-4">
                    <a href="edit_reel.php?id=<?= $reel_id ?>" class="flex-1 btn-edit">Edit</a>
                    <form method="POST" onsubmit="return confirm('Delete this Bitz?');" class="flex-1">
                        <input type="hidden" name="delete_reel_id" value="<?= $reel_id ?>">
                        <button type="submit" name="delete_reel" class="w-full btn-delete">Delete</button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; 
    $stmt_reels->close(); ?>
</div>

<script>
    // Toggle profile dropdown
    document.getElementById('profileButton').addEventListener('click', function() {
        document.getElementById('profileDropdown').classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!document.getElementById('profileButton').contains(e.target)) {
            document.getElementById('profileDropdown').classList.remove('show');
        }
    });
</script>

</body>
</html>
<?php $conn->close(); ?>