<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'owner') {
  header('Location: ownerlogin.php');
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>RawFit Owner Home</title>
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
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
            <path d="M6.5 6.5h11v11h-11z" />
            <path d="M6.5 6.5L2 2" />
            <path d="M17.5 6.5L22 2" />
            <path d="M6.5 17.5L2 22" />
            <path d="M17.5 17.5L22 22" />
          </svg>
        </div>
        <span class="text-2xl font-bold">RawFit</span>
      </div>
  
      <div class="text-gray-300 text-sm">
        <?php echo htmlspecialchars($_SESSION['owner_name'] ?? 'Owner'); ?>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="pt-28 pb-10 text-center">
    <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">
      Manage Your Gym Here
    </h1>
    <p class="mt-3 text-gray-400 text-lg">
      Manage your gyms, trainers, and reviews — all in one place.
    </p>
  </section>

  <!-- Dashboard Links -->
  <section class="max-w-6xl mx-auto px-6 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
    <!-- Card Template -->
    <a href="Manage_Gyms.php" class="group rounded-2xl border border-gray-800 bg-gray-900/60 p-8 hover:bg-gray-900 hover:border-orange-500/50 transition-all shadow-lg hover:shadow-orange-500/20">
      <div class="flex flex-col items-center text-center space-y-4">
        <div class="h-14 w-14 flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-red-500 shadow-md shadow-orange-500/30">
          <svg width="26" height="26" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M3 9h18M9 21V9" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold">Add Gyms</h3>
        <p class="text-gray-400 text-sm">Add, edit, or remove your gym listings easily.</p>
      </div>
    </a>

 

    <a href="gym_reviews_display.php" class="group rounded-2xl border border-gray-800 bg-gray-900/60 p-8 hover:bg-gray-900 hover:border-orange-500/50 transition-all shadow-lg hover:shadow-orange-500/20">
      <div class="flex flex-col items-center text-center space-y-4">
        <div class="h-14 w-14 flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-red-500 shadow-md shadow-orange-500/30">
          <svg width="26" height="26" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M12 17l-5 3 1.9-5.6L4 9h6l2-6 2 6h6l-4.9 5.4L17 20z" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold">View Reviews</h3>
        <p class="text-gray-400 text-sm">Check feedback and ratings from gym members.</p>
      </div>
    </a>

    <a href="gym_profile.php" class="group rounded-2xl border border-gray-800 bg-gray-900/60 p-8 hover:bg-gray-900 hover:border-orange-500/50 transition-all shadow-lg hover:shadow-orange-500/20">
      <div class="flex flex-col items-center text-center space-y-4">
        <div class="h-14 w-14 flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-red-500 shadow-md shadow-orange-500/30">
          <svg width="26" height="26" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5z" />
            <path d="M12 14c-4.33 0-8 2.17-8 5v3h16v-3c0-2.83-3.67-5-8-5z" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold">Manage Gyms</h3>
        <p class="text-gray-400 text-sm">View or update your owner profile information.</p>
      </div>
    </a>

      <a href="owner_messages.php" class="group rounded-2xl border border-gray-800 bg-gray-900/60 p-8 hover:bg-gray-900 hover:border-orange-500/50 transition-all shadow-lg hover:shadow-orange-500/20">
      <div class="flex flex-col items-center text-center space-y-4">
        <div class="h-14 w-14 flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-red-500 shadow-md shadow-orange-500/30">
          <svg width="26" height="26" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5z" />
            <path d="M12 14c-4.33 0-8 2.17-8 5v3h16v-3c0-2.83-3.67-5-8-5z" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold">Enquries</h3>
        <p class="text-gray-400 text-sm">View or update your owner profile information.</p>
      </div>
    </a>
    <a href="logout.php" class="group rounded-2xl border border-gray-800 bg-gray-900/60 p-8 hover:bg-gray-900 hover:border-red-500/60 transition-all shadow-lg hover:shadow-red-500/20">
      <div class="flex flex-col items-center text-center space-y-4">
        <div class="h-14 w-14 flex items-center justify-center rounded-xl bg-gradient-to-r from-red-500 to-orange-500 shadow-md shadow-red-500/30">
          <svg width="26" height="26" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold">Logout</h3>
        <p class="text-gray-400 text-sm">Sign out of your RawFit owner account.</p>
      </div>
    </a>
  </section>

  <!-- Footer -->
  <footer class="mt-16 border-t border-gray-800 text-center py-6 text-gray-500 text-sm">
    © <?php echo date('Y'); ?> RawFit. All rights reserved.
  </footer>
</body>
</html>