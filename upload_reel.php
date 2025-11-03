<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php?error=login_required");
    exit;
}
$user_id = intval($_SESSION['user_id']);

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
<title>Upload Bitz- RawFit</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-4">

<h1 class="text-3xl font-bold mb-4">Upload Your Bitz</h1>
<?php if ($msg) echo "<p class='mb-4 text-green-400'>$msg</p>"; ?>

<!-- Upload Form -->
<form method="POST" enctype="multipart/form-data" class="bg-gray-800 p-6 rounded-lg mb-12 border border-gray-700">
    <input type="file" name="reel_video" accept="video/*" required class="mb-4 p-2 w-full bg-gray-700 text-white rounded-lg">
    <input type="text" name="caption" placeholder="Caption" class="mb-4 p-2 w-full bg-gray-700 rounded-lg">
    <input type="text" name="supplement_tag" placeholder="Supplement Tag" class="mb-4 p-2 w-full bg-gray-700 rounded-lg">
    <input type="text" name="calories_info" placeholder="Calories Info" class="mb-4 p-2 w-full bg-gray-700 rounded-lg">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 px-4 py-2 rounded-lg">Upload</button>
</form>

<!-- My Reels -->
<h2 class="text-2xl font-bold mb-4">My Bitz</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
<?php
$stmt_reels = $conn->prepare("SELECT * FROM reels WHERE user_id=? ORDER BY created_at DESC");
$stmt_reels->bind_param("i", $user_id);
$stmt_reels->execute();
$result = $stmt_reels->get_result();
while ($reel = $result->fetch_assoc()):
    $reel_id = $reel['id'];

    // Likes: count + list
    $likeCount = $conn->query("SELECT COUNT(*) as c FROM reel_likes WHERE reel_id=$reel_id")->fetch_assoc()['c'];
    $likes_list = [];
    $like_res = $conn->query("SELECT rg.name FROM reel_likes rl JOIN register rg ON rl.user_id = rg.id WHERE rl.reel_id=$reel_id");
    while ($l = $like_res->fetch_assoc()) $likes_list[] = $l['name'];

    // Comments: all for owner
    $comment_sql = "SELECT rc.comment, rc.created_at, rg.name AS commenter 
                    FROM reel_comments rc 
                    JOIN register rg ON rc.user_id = rg.id 
                    WHERE rc.reel_id = $reel_id 
                    ORDER BY rc.created_at DESC";
    $comments = $conn->query($comment_sql);
?>
<div class="bg-gray-800 p-4 rounded-lg border border-gray-700 flex flex-col">
    <video class="w-full mb-2 rounded" controls>
        <source src="<?= htmlspecialchars($reel['video_url']) ?>" type="video/mp4">
    </video>
    <p class="mb-1 font-medium"><?= htmlspecialchars($reel['caption']) ?></p>
    <p class="text-gray-400 text-sm mb-1">Tag: <?= htmlspecialchars($reel['supplement_tag']) ?></p>
    <p class="text-gray-400 text-sm mb-1">Calories: <?= htmlspecialchars($reel['calories_info']) ?></p>
    <p class="text-gray-400 text-sm mb-2">Status: <?= htmlspecialchars($reel['status']) ?></p>

    <!-- Likes -->
    <p class="text-pink-400 text-sm mb-1">❤️ <?= $likeCount ?> likes</p>
    <?php if (!empty($likes_list)): ?>
        <p class="text-gray-400 text-xs mb-2">Liked by: <?= implode(", ", $likes_list) ?></p>
    <?php endif; ?>

    <!-- Comments -->
    <div class="text-sm text-gray-300 space-y-1 mb-2">
        <?php 
        if ($comments && $comments->num_rows > 0):
            while($c = $comments->fetch_assoc()): ?>
                <p><b><?= htmlspecialchars($c['commenter']) ?>:</b> <?= htmlspecialchars($c['comment']) ?> 
                <span class="text-xs text-gray-500">(<?= date('M j, g:i A', strtotime($c['created_at'])) ?>)</span></p>
            <?php endwhile;
        else: ?>
            <p class="text-gray-500">No comments yet.</p>
        <?php endif; ?>
    </div>

    <!-- Edit / Delete -->
    <div class="flex space-x-2 mt-2">
        <a href="edit_reel.php?id=<?= $reel_id ?>" class="flex-1 bg-blue-500 hover:bg-blue-600 py-1 rounded text-sm text-center">Edit</a>
        <form method="POST" onsubmit="return confirm('Delete this reel?');" class="flex-1">
            <input type="hidden" name="delete_reel_id" value="<?= $reel_id ?>">
            <button type="submit" name="delete_reel" class="w-full bg-red-500 hover:bg-red-600 py-1 rounded text-sm">Delete</button>
        </form>
    </div>
</div>
<?php endwhile; $stmt_reels->close(); ?>
</div>

</body>
</html>
<?php $conn->close(); ?>
