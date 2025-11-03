<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'owner') {
  header('Location: ownerlogin.php');
  exit;
}

// --- DB CONNECTION ---
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Fetch reviews with gym names owned by this owner
$query = "
  SELECT r.id, r.rating, r.comment, r.user_name, r.created_at, g.gym_name
  FROM gym_reviews r
  JOIN gyms g ON r.gym_id = g.gym_id
  WHERE g.owner_id = ?
  ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['owner_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Gym Reviews | RawFit</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              orange: '#f97316',
              red: '#ef4444',
            },
          },
          fontFamily: {
            inter: ['Inter', 'sans-serif'],
          },
        },
      },
    };
  </script>
</head>

<body class="min-h-screen bg-gray-950 text-white font-inter">
  <!-- Navbar -->
  <nav class="fixed top-0 left-0 right-0 z-50 bg-black/70 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="h-9 w-9 flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-red-500 shadow-lg shadow-orange-500/30">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6.5 6.5h11v11h-11z"/>
            <path d="M6.5 6.5L2 2M17.5 6.5L22 2M6.5 17.5L2 22M17.5 17.5L22 22"/>
          </svg>
        </div>
        <span class="text-2xl font-bold">RawFit</span>
      </div>
      <div class="hidden md:flex space-x-6">
        <a href="owner_home.php" class="text-gray-300 hover:text-orange-400 transition">Home</a>
        <a href="owner_dashboard.php" class="text-gray-300 hover:text-orange-400 transition">Dashboard</a>
        <a href="trainer.php" class="text-gray-300 hover:text-orange-400 transition">Trainers</a>
        <a href="reviews.php" class="text-orange-400 font-medium">Reviews</a>
      </div>
      <div class="text-gray-300 text-sm">
        <?php echo htmlspecialchars($_SESSION['owner_name'] ?? 'Owner'); ?>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <section class="pt-28 pb-10 text-center">
    <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">
      Gym Reviews
    </h1>
    <p class="mt-3 text-gray-400 text-lg">
      Real feedback from members of your gyms.
    </p>
  </section>

  <!-- Reviews Section -->
  <section class="max-w-6xl mx-auto px-6 pb-20 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="group rounded-2xl border border-gray-800 bg-gray-900/60 hover:bg-gray-900 hover:border-orange-500/50 transition-all shadow-md hover:shadow-orange-500/20 p-6 flex flex-col justify-between">
          
          <!-- Gym Name -->
          <h3 class="text-lg font-semibold text-orange-400 mb-2">
            <?php echo htmlspecialchars($row['gym_name']); ?>
          </h3>

          <!-- Review Comment -->
          <p class="text-gray-300 italic mb-4">
            “<?php echo htmlspecialchars($row['comment']); ?>”
          </p>

          <!-- Rating Stars -->
          <div class="flex items-center mb-3">
            <?php
              $stars = intval($row['rating']);
              for ($i = 0; $i < 5; $i++) {
                if ($i < $stars) {
                  echo '<svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.197 3.68a1 1 0 00.95.69h3.862c.969 0 1.371 1.24.588 1.81l-3.125 2.27a1 1 0 00-.364 1.118l1.197 3.68c.3.921-.755 1.688-1.54 1.118L10 14.347l-3.117 2.27c-.784.57-1.838-.197-1.539-1.118l1.197-3.68a1 1 0 00-.364-1.118L3.052 9.107c-.783-.57-.38-1.81.588-1.81h3.862a1 1 0 00.95-.69l1.197-3.68z"/>
                        </svg>';
                } else {
                  echo '<svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.197 3.68a1 1 0 00.95.69h3.862c.969 0 1.371 1.24.588 1.81l-3.125 2.27a1 1 0 00-.364 1.118l1.197 3.68c.3.921-.755 1.688-1.54 1.118L10 14.347l-3.117 2.27c-.784.57-1.838-.197-1.539-1.118l1.197-3.68a1 1 0 00-.364-1.118L3.052 9.107c-.783-.57-.38-1.81.588-1.81h3.862a1 1 0 00.95-.69l1.197-3.68z"/>
                        </svg>';
                }
              }
            ?>
          </div>

          <!-- Reviewer Info -->
          <div class="flex justify-between items-center text-sm text-gray-400">
            <span>By <?php echo htmlspecialchars($row['user_name']); ?></span>
            <span><?php echo date("M d, Y", strtotime($row['created_at'])); ?></span>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-span-full text-center text-gray-400 text-lg mt-10">
        No reviews yet 😔
      </div>
    <?php endif; ?>
  </section>

  <!-- Footer -->
  <footer class="border-t border-gray-800 text-center py-6 text-gray-500 text-sm">
    © <?php echo date('Y'); ?> RawFit. All rights reserved.
  </footer>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
