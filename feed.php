<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check user session
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
<title>Bitz Feed - RawFit</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<script>
tailwind.config = { theme: { extend: { fontFamily: { 'inter': ['Inter', 'sans-serif'] } } } }
</script>
<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

/* Feed scroll */
.snap-container {
  scroll-snap-type: y mandatory;
  overflow-y: scroll;
  height: 100vh;
  scroll-behavior: smooth;
}
.reel {
  scroll-snap-align: center;
  height: 650px;
  position: relative;
}

/* Right-side comment panel */
.comment-modal {
  position: absolute;
  top: 0;
  right: 0;
  width: 60%;
  height: 100%;
  background: rgba(0, 0, 0, 0.95);
  backdrop-filter: blur(6px);
  transform: translateX(100%);
  transition: transform 0.3s ease;
  border-left: 1px solid #333;
  padding: 1rem;
  overflow-y: auto;
  z-index: 40;
}
.comment-modal.visible {
  transform: translateX(0);
}
</style>
</head>
<body class="bg-black font-inter min-h-screen">

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
      <div class="flex space-x-4">
        <a href="home.php" class="text-white hover:text-orange-500">Home</a>
        <a href="upload_reel.php" class="text-white hover:text-orange-500">Upload</a>
        <a href="logout.php" class="text-white hover:text-orange-500">Logout</a>
      </div>
    </div>
  </div>
</nav>

<!-- Main Feed -->
<main class="pt-20">
  <div class="snap-container scrollbar-hide">
    <div class="flex flex-col items-center space-y-12 pb-10">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()):
        $reel_id = $row['id'];
        $creator_id = $row['creator_id'];
        $likeCount = $conn->query("SELECT COUNT(*) AS c FROM reel_likes WHERE reel_id=$reel_id")->fetch_assoc()['c'];
        $isLiked = $conn->query("SELECT id FROM reel_likes WHERE reel_id=$reel_id AND user_id=$user_id")->num_rows > 0;
        $comments = $conn->query("SELECT rc.comment, rc.created_at, rg.name AS commenter 
                                  FROM reel_comments rc 
                                  JOIN register rg ON rc.user_id = rg.id 
                                  WHERE rc.reel_id = $reel_id 
                                  ORDER BY rc.created_at DESC LIMIT 3");
      ?>
      <section class="reel w-[380px] bg-gray-900 rounded-2xl overflow-hidden shadow-lg">
        <video muted playsinline loop class="reel-video w-full h-full object-cover cursor-pointer">
          <source src="<?= htmlspecialchars($row['video_url']) ?>" type="video/mp4">
        </video>

        <!-- Overlay Info -->
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent px-5 pb-6 pt-10">
          <div class="max-w-[80%] space-y-1.5">
            <p class="text-lg font-semibold text-white drop-shadow-md">@<?= htmlspecialchars($row['username']) ?></p>
            <?php if(!empty($row['caption'])): ?>
              <p class="text-base text-gray-100 leading-snug drop-shadow-md"><?= htmlspecialchars($row['caption']) ?></p>
            <?php endif; ?>
            <?php if(!empty($row['supplement_tag'])): ?>
              <p class="inline-block bg-gradient-to-r from-orange-500 to-red-500 text-xs text-white font-medium px-2 py-0.5 rounded-md shadow-md">
                🔥 <?= htmlspecialchars($row['supplement_tag']) ?>
              </p>
            <?php endif; ?>
            <?php if(!empty($row['calories_info'])): ?>
              <p class="text-xs text-green-400 font-medium">Calories: <?= htmlspecialchars($row['calories_info']) ?></p>
            <?php endif; ?>
            <p class="text-[11px] text-gray-400 mt-1"><?= date('F j, Y g:i A', strtotime($row['created_at'])) ?></p>
          </div>
        </div>

        <!-- Controls -->
        <div class="absolute bottom-24 right-4 flex flex-col items-center space-y-4 text-white z-30">
          <button data-reel="<?= $reel_id ?>" class="like-btn flex flex-col items-center">
            <span class="text-3xl"><?= $isLiked ? "❤️" : "🤍" ?></span>
            <span class="text-sm"><?= $likeCount ?></span>
          </button>
          <button onclick="openComments('comments-<?= $reel_id ?>')" class="flex flex-col items-center">
            <span class="text-3xl">💬</span>
            <span class="text-sm"><?= $comments->num_rows ?></span>
          </button>
        </div>

        <!-- Comment Panel -->
        <div id="comments-<?= $reel_id ?>" class="comment-modal">
          <div class="flex justify-between items-center mb-2">
            <h3 class="text-white font-bold">Comments</h3>
            <button onclick="closeComments('comments-<?= $reel_id ?>')" class="text-gray-400 text-lg hover:text-red-500">×</button>
          </div>
          <div class="space-y-2 text-sm text-gray-200">
            <?php if($comments->num_rows > 0): while($c = $comments->fetch_assoc()): ?>
              <p><b><?= htmlspecialchars($c['commenter']) ?>:</b> <?= htmlspecialchars($c['comment']) ?>
              <span class="text-xs text-gray-400">(<?= date('M j, g:i A', strtotime($c['created_at'])) ?>)</span></p>
            <?php endwhile; else: ?>
              <p class="text-gray-400">No comments yet.</p>
            <?php endif; ?>
          </div>
          <form class="comment-form flex mt-3" data-reel="<?= $reel_id ?>">
            <input type="text" name="comment" placeholder="Add a comment..." required class="flex-1 p-2 bg-gray-700 rounded-l text-sm text-white">
            <button type="submit" class="bg-green-500 hover:bg-green-600 px-3 rounded-r text-sm">Post</button>
          </form>
        </div>
      </section>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-gray-400 mt-20">No reels yet.</p>
    <?php endif; ?>
    </div>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const videos = document.querySelectorAll(".reel-video");

  // Autoplay one at a time
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const video = entry.target;
      if (entry.isIntersecting) {
        videos.forEach(v => { if (v !== video) { v.pause(); v.currentTime = 0; } });
        video.play();
      } else {
        video.pause();
      }
    });
  }, { threshold: 0.7 });
  videos.forEach(v => observer.observe(v));

  // Tap to mute/unmute
  videos.forEach(video => {
    video.addEventListener("click", () => {
      video.muted = !video.muted;
      const indicator = document.createElement("div");
      indicator.textContent = video.muted ? "🔇" : "🔊";
      indicator.className = "absolute bottom-28 right-6 text-white text-2xl";
      video.parentElement.appendChild(indicator);
      setTimeout(() => indicator.remove(), 800);
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
      }).then(res => res.json()).then(data => {
        if (data.status === 'success') {
          const spans = btn.querySelectorAll('span');
          spans[0].textContent = data.liked ? "❤️" : "🤍";
          spans[1].textContent = data.likeCount;
        }
      });
    });
  });

  // Comment post
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
      }).then(res => res.json()).then(data => {
        if (data.status === 'success') {
          const container = form.previousElementSibling;
          const p = document.createElement('p');
          p.innerHTML = `<b>${data.commenter}:</b> ${data.comment} <span class='text-xs text-gray-400'>(${data.created_at})</span>`;
          container.prepend(p);
          input.value = '';
        }
      });
    });
  });
});

// Open/Close right-side comment modal
function openComments(id) {
  document.getElementById(id).classList.add("visible");
}
function closeComments(id) {
  document.getElementById(id).classList.remove("visible");
}
</script>

</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>
