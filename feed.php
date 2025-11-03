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

// Fetch all approved reels
$sql = "SELECT r.id, r.video_url, r.caption, r.supplement_tag, r.calories_info, r.created_at, r.user_id AS creator_id, rg.name AS username 
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script>
tailwind.config = { theme: { extend: { fontFamily: { 'inter': ['Inter', 'sans-serif'] } } } }
</script>
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
            <path d="M6.5 6.5L2 2"/>
            <path d="M17.5 6.5L22 2"/>
            <path d="M6.5 17.5L2 22"/>
            <path d="M17.5 17.5L22 22"/>
          </svg>
        </div>
        <span class="text-white font-bold text-xl">RawFit</span>
      </div>
      <div class="flex space-x-4">
        <a href="home.php" class="text-white hover:text-orange-500 transition-colors">Back to Home</a>
        <a href="upload_reel.php" class="text-white hover:text-orange-500 transition-colors">Upload Bitz</a>
        <a href="logout.php" class="text-white hover:text-orange-500 transition-colors">Logout</a>
      </div>
    </div>
  </div>
</nav>

<!-- Main Feed -->
<main class="pt-16">
  <div class="h-screen overflow-y-scroll snap-y snap-mandatory">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): 
        $reel_id = $row['id'];
        $creator_id = $row['creator_id'];

        $likeCount = $conn->query("SELECT COUNT(*) as c FROM reel_likes WHERE reel_id=$reel_id")->fetch_assoc()['c'];
        $isLiked = $conn->query("SELECT id FROM reel_likes WHERE reel_id=$reel_id AND user_id=$user_id")->num_rows > 0;

        $comments = $conn->query("SELECT rc.comment, rc.created_at, rc.user_id, rg.name AS commenter 
                                  FROM reel_comments rc 
                                  JOIN register rg ON rc.user_id = rg.id 
                                  WHERE rc.reel_id = $reel_id 
                                  ORDER BY rc.created_at DESC 
                                  LIMIT 3");
      ?>
        <div class="relative h-screen w-full snap-start flex items-center justify-center">
          <video 
            muted 
            playsinline 
            class="reel-video w-full h-full object-cover">
            <source src="<?= htmlspecialchars($row['video_url']) ?>" type="video/mp4">
            Your browser does not support the video tag.
          </video>

          <!-- Left: User info & caption -->
          <div class="absolute bottom-20 left-4 text-white space-y-1 max-w-[70%]">
            <p class="font-bold">@<?= htmlspecialchars($row['username']) ?></p>
            <?php if(!empty($row['caption'])): ?>
              <p class="text-sm"><?= htmlspecialchars($row['caption']) ?></p>
            <?php endif; ?>
            <?php if(!empty($row['supplement_tag'])): ?>
              <p class="text-orange-400 text-sm">Promo: <?= htmlspecialchars($row['supplement_tag']) ?></p>
            <?php endif; ?>
            <?php if(!empty($row['calories_info'])): ?>
              <p class="text-green-400 text-sm">Calories: <?= htmlspecialchars($row['calories_info']) ?></p>
            <?php endif; ?>
            <p class="text-gray-400 text-xs"><?= date('F j, Y g:i A', strtotime($row['created_at'])) ?></p>
          </div>

          <!-- Right: Like + Comment buttons -->
          <div class="absolute bottom-24 right-4 flex flex-col items-center space-y-4 text-white">
            <button data-reel="<?= $reel_id ?>" class="like-btn flex flex-col items-center focus:outline-none">
              <span class="text-3xl"><?= $isLiked ? "❤️" : "🤍" ?></span>
              <span class="text-sm"><?= $likeCount ?></span>
            </button>

            <button onclick="toggleComments('comments-<?= $reel_id ?>')" class="flex flex-col items-center focus:outline-none">
              <span class="text-3xl">💬</span>
              <span class="text-sm"><?= $comments->num_rows ?></span>
            </button>
          </div>

          <!-- Comments Drawer -->
          <div id="comments-<?= $reel_id ?>" class="hidden absolute bottom-0 left-0 right-0 max-h-[50%] bg-black/80 backdrop-blur-md p-4 overflow-y-auto">
            <h3 class="text-white font-bold mb-2">Comments</h3>
            <div class="space-y-2 text-sm text-gray-200">
              <?php if($comments && $comments->num_rows > 0): 
                while($c = $comments->fetch_assoc()): ?>
                <p><b><?= htmlspecialchars($c['commenter']) ?>:</b> <?= htmlspecialchars($c['comment']) ?> 
                <span class='text-xs text-gray-400'>(<?= date('M j, g:i A', strtotime($c['created_at'])) ?>)</span></p>
              <?php endwhile; else: ?>
                <p class="text-gray-400">No comments yet.</p>
              <?php endif; ?>
            </div>

            <!-- Comment Form -->
            <form class="comment-form flex mt-2" data-reel="<?= $reel_id ?>">
              <input type="text" name="comment" placeholder="Add a comment..." required class="flex-1 p-2 bg-gray-700 rounded-l text-sm text-white">
              <button type="submit" class="bg-green-500 hover:bg-green-600 px-3 rounded-r text-sm">Post</button>
            </form>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="h-screen flex items-center justify-center text-gray-400">
        No reels available yet.
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Auto Play + Like + Comment Scripts -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const videos = document.querySelectorAll(".reel-video");

  // Auto play/pause with IntersectionObserver
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.play();
      } else {
        entry.target.pause();
        entry.target.currentTime = 0;
      }
    });
  }, { threshold: 0.8 });

  videos.forEach(video => observer.observe(video));

  // LIKE
  document.querySelectorAll('.like-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const reelId = btn.dataset.reel;
      fetch('like_reel.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({reel_id:reelId})
      }).then(res=>res.json()).then(data=>{
        if(data.status==='success'){
          btn.querySelector("span").textContent = data.liked ? "❤️" : "🤍";
          btn.querySelectorAll("span")[1].textContent = data.likeCount;
        } else {
          alert(data.msg || 'Something went wrong');
        }
      }).catch(err=>console.error(err));
    });
  });

  // COMMENT
  document.querySelectorAll('.comment-form').forEach(form=>{
    form.addEventListener('submit', e=>{
      e.preventDefault();
      const reelId = form.dataset.reel;
      const input = form.querySelector('input[name="comment"]');
      const comment = input.value.trim();
      if(!comment) return;

      fetch('comment_reel.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({reel_id:reelId, comment:comment})
      }).then(res=>res.json()).then(data=>{
        if(data.status==='success'){
          const container = form.previousElementSibling;
          const p = document.createElement('p');
          p.innerHTML = `<b>${data.commenter}:</b> ${data.comment} <span class='text-xs text-gray-400'>(${data.created_at})</span>`;
          container.prepend(p);
          input.value='';
        } else {
          alert(data.msg || 'Something went wrong');
        }
      }).catch(err=>console.error(err));
    });
  });
});

// Toggle comments drawer
function toggleComments(id){
  const el = document.getElementById(id);
  el.classList.toggle("hidden");
}
</script>

</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>
