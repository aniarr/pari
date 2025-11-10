<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php?error=login_required");
    exit;
}
$user_id = intval($_SESSION['user_id']);

// Fetch approved reels
$sql = "SELECT r.id, r.video_url, r.caption, r.supplement_tag, r.calories_info,
               r.created_at, r.user_id AS creator_id, rg.name AS username
        FROM reels r
        JOIN register rg ON r.user_id = rg.id
        WHERE r.status = 'approved'
        ORDER BY r.created_at DESC";
$result = $conn->query($sql);
if (!$result) die("Query failed: " . $conn->error);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RawFit Reels</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { 'inter': ['Inter', 'sans-serif'] } } }
    }
  </script>
  <style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

    .snap-container {
      scroll-snap-type: y mandatory;
      overflow-y: scroll;
      height: 100vh;
      scroll-behavior: smooth;
    }

    .reel {
      scroll-snap-align: center;
      height: 100vh;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #000;
    }

    .reel-video {
      width: 100%;
      height: 100%;
      object-fit: contain;
      background: #000;
    }

    .overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
      color: white;
      padding: 2rem 1.5rem 6rem 1.5rem; /* Extra bottom padding for buttons */
      font-family: 'Inter', sans-serif;
    }

    /* Fixed Bottom-Right Buttons */
    .action-buttons {
      position: absolute;
      bottom: 6rem;
      right: 1rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      z-index: 30;
    }

    .action-btn {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      width: 56px;
      height: 56px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .action-btn:hover {
      transform: scale(1.1);
      background: rgba(255, 255, 255, 0.25);
    }

    .action-btn.liked {
      color: #ff3040;
    }

    .action-btn span {
      font-size: 0.65rem;
      margin-top: 0.25rem;
      font-weight: 600;
    }

    /* Right Slide Comment Panel */
    .comment-modal {
      position: fixed;
      top: 0;
      right: 0;
      width: 100%;
      max-width: 420px;
      height: 100vh;
      background: rgba(0, 0, 0, 0.98);
      backdrop-filter: blur(16px);
      transform: translateX(100%);
      transition: transform 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
      z-index: 50;
      padding: 1rem;
      overflow-y: auto;
      border-left: 1px solid #333;
    }

    @media (min-width: 768px) {
      .comment-modal { width: 420px; }
    }

    .comment-modal.visible {
      transform: translateX(0);
    }

    .comment-input {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 1rem;
      background: rgba(0, 0, 0, 0.9);
      border-top: 1px solid #333;
      z-index: 10;
    }

    .mute-indicator {
      position: absolute;
      bottom: 7rem;
      right: 1.5rem;
      background: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 1rem;
      font-size: 0.9rem;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .mute-indicator.show {
      opacity: 1;
    }
  </style>
</head>
<body class="bg-black text-white font-inter">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">
      <div class="flex items-center space-x-3">
        <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
            <path d="M6.5 6.5h11v11h-11z"/>
            <path d="M6.5 6.5L2 2M17.5 6.5L22 2M6.5 17.5L2 22M17.5 17.5L22 22"/>
          </svg>
        </div>
        <span class="text-white font-bold text-xl">RawFit</span>
      </div>
      <div class="hidden md:flex space-x-6 text-sm">
        <a href="home.php" class="text-gray-300 hover:text-orange-500 transition">Home</a>
        <a href="upload_reel.php" class="text-gray-300 hover:text-orange-500 transition">Upload</a>
        <a href="logout.php" class="text-gray-300 hover:text-orange-500 transition">Logout</a>
      </div>
      <div class="md:hidden flex space-x-4 text-xl">
        <a href="home.php">Home</a>
        <a href="upload_reel.php">Upload</a>
        <a href="logout.php">Exit</a>
      </div>
    </div>
  </div>
</nav>

<!-- Reels Feed -->
<main class="snap-container scrollbar-hide">
  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()):
      $reel_id = $row['id'];
      $likeCount = $conn->query("SELECT COUNT(*) FROM reel_likes WHERE reel_id = $reel_id")->fetch_column();
      $isLiked = $conn->query("SELECT 1 FROM reel_likes WHERE reel_id = $reel_id AND user_id = $user_id")->num_rows > 0;
      $commentCount = $conn->query("SELECT COUNT(*) FROM reel_comments WHERE reel_id = $reel_id")->fetch_column();
      $comments = $conn->query("SELECT rc.comment, rc.created_at, rg.name AS commenter
                                FROM reel_comments rc
                                JOIN register rg ON rc.user_id = rg.id
                                WHERE rc.reel_id = $reel_id
                                ORDER BY rc.created_at DESC LIMIT 5");
    ?>
    <section class="reel">
      <div class="relative w-full h-full flex items-center justify-center">
        <!-- Video -->
        <video muted playsinline loop class="reel-video" id="video-<?= $reel_id ?>">
          <source src="<?= htmlspecialchars($row['video_url']) ?>" type="video/mp4">
          Your browser does not support video.
        </video>

        <!-- Mute Indicator -->
        <div id="mute-<?= $reel_id ?>" class="mute-indicator">Muted</div>

        <!-- Overlay Info -->
        <div class="overlay">
          <div class="max-w-xl">
            <p class="text-lg font-bold drop-shadow-lg">@<?= htmlspecialchars($row['username']) ?></p>
            <?php if (!empty($row['caption'])): ?>
              <p class="mt-1 text-sm text-gray-100 leading-relaxed drop-shadow"><?= htmlspecialchars($row['caption']) ?></p>
            <?php endif; ?>
            <div class="flex flex-wrap gap-2 mt-2">
              <?php if (!empty($row['supplement_tag'])): ?>
                <span class="inline-block bg-gradient-to-r from-orange-500 to-red-600 text-xs font-bold px-3 py-1 rounded-full shadow-md">
                  <?= htmlspecialchars($row['supplement_tag']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($row['calories_info'])): ?>
                <span class="text-xs text-green-400 font-medium">Calories: <?= htmlspecialchars($row['calories_info']) ?></span>
              <?php endif; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2"><?= date('M j, Y \a\t g:i A', strtotime($row['created_at'])) ?></p>
          </div>
        </div>

        <!-- Fixed Bottom-Right Action Buttons -->
        <div class="action-buttons">
          <!-- Like Button -->
          <button data-reel="<?= $reel_id ?>" class="action-btn like-btn <?= $isLiked ? 'liked' : '' ?>">
            <div><?= $isLiked ? "❤" : "🤍" ?></div>
            <span><?= $likeCount ?></span>
          </button>

          <!-- Comment Button -->
          <button onclick="openComments('comments-<?= $reel_id ?>')" class="action-btn">
            <div>💬</div>
            <span><?= $commentCount ?></span>
          </button>
        </div>
      </div>

      <!-- Right Slide Comment Panel -->
      <div id="comments-<?= $reel_id ?>" class="comment-modal">
        <div class="flex justify-between items-center mb-4 sticky top-0 bg-black/90 backdrop-blur z-10 py-3 border-b border-gray-800">
          <h3 class="text-lg font-bold">Comments (<?= $commentCount ?>)</h3>
          <button onclick="closeComments('comments-<?= $reel_id ?>')" class="text-2xl text-gray-400 hover:text-white">×</button>
        </div>

        <div class="space-y-3 pb-20">
          <?php if ($comments && $comments->num_rows > 0): ?>
            <?php while ($c = $comments->fetch_assoc()): ?>
              <div class="bg-white/5 p-3 rounded-xl">
                <p class="font-semibold text-orange-400 text-sm"><?= htmlspecialchars($c['commenter']) ?></p>
                <p class="text-gray-200 text-sm"><?= htmlspecialchars($c['comment']) ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= date('M j, g:i A', strtotime($c['created_at'])) ?></p>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p class="text-center text-gray-500 py-10">No comments yet. Start the conversation!</p>
          <?php endif; ?>
        </div>

        <!-- Fixed Comment Input -->
        <form class="comment-input comment-form" data-reel="<?= $reel_id ?>">
          <div class="flex gap-2">
            <input type="text" name="comment" placeholder="Add a comment..." required
                   class="flex-1 bg-white/10 text-white rounded-l-full px-5 py-3 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <button type="submit" class="bg-gradient-to-r from-orange-500 to-red-600 text-white px-6 rounded-r-full font-bold text-sm hover:from-red-600 hover:to-orange-700 transition">
              Post
            </button>
          </div>
        </form>
      </div>
    </section>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="reel flex items-center justify-center">
      <div class="text-center">
        <p class="text-6xl mb-4">No Reels</p>
        <p class="text-gray-400 mb-6">No approved reels yet.</p>
        <a href="upload_reel.php" class="inline-block bg-gradient-to-r from-orange-500 to-red-600 text-white px-8 py-3 rounded-xl font-bold hover:from-red-600 hover:to-orange-700 transition transform hover:scale-105 shadow-lg">
          Upload Your First Reel
        </a>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const videos = document.querySelectorAll(".reel-video");

  // Auto-play on scroll
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const video = entry.target;
      if (entry.isIntersecting && entry.intersectionRatio > 0.7) {
        videos.forEach(v => { if (v !== video) v.pause(); });
        video.play().catch(() => {});
      } else {
        video.pause();
      }
    });
  }, { threshold: 0.7 });
  videos.forEach(v => observer.observe(v));

  // Tap to mute/unmute
  videos.forEach(video => {
    const reelId = video.id.split('-')[1];
    const indicator = document.getElementById(`mute-${reelId}`);
    video.addEventListener("click", () => {
      video.muted = !video.muted;
      indicator.textContent = video.muted ? "Muted" : "Unmuted";
      indicator.classList.add("show");
      setTimeout(() => indicator.classList.remove("show"), 800);
    });
  });

  // Like button
  document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const reelId = btn.dataset.reel;
      fetch('like_reel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ reel_id: reelId })
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          const icon = btn.querySelector('div');
          const count = btn.querySelector('span');
          icon.innerHTML = data.liked ? "❤" : "🤍";
          count.textContent = data.likeCount;
          btn.classList.toggle('liked', data.liked);
        }
      });
    });
  });

  // Comment form
  document.querySelectorAll('.comment-form').forEach(form => {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const reelId = form.dataset.reel;
      const input = form.querySelector('input[name="comment"]');
      const comment = input.value.trim();
      if (!comment) return;

      fetch('comment_reel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ reel_id: reelId, comment })
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          const container = form.previousElementSibling.querySelector('.space-y-3') || form.previousElementSibling;
          const commentEl = document.createElement('div');
          commentEl.className = 'bg-white/5 p-3 rounded-xl';
          commentEl.innerHTML = `
            <p class="font-semibold text-orange-400 text-sm">${data.commenter}</p>
            <p class="text-gray-200 text-sm">${data.comment}</p>
            <p class="text-xs text-gray-500 mt-1">Just now</p>
          `;
          container.insertBefore(commentEl, container.firstChild);
          input.value = '';
        }
      });
    });
  });
});

// Open/Close Comment Panel
function openComments(id) {
  document.getElementById(id).classList.add("visible");
}
function closeComments(id) {
  document.getElementById(id).classList.remove("visible");
}
</script>

</body>
</html>

<?php if (isset($conn)) $conn->close(); ?>