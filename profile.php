<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get logged-in user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT name, email, phone FROM register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$userData = [
    'name' => 'User',
    'email' => '',
    'phone' => ''
];
if ($row = $result->fetch_assoc()) {
    $userData['name'] = $row['name'];
    $userData['email'] = $row['email'];
    $userData['phone'] = $row['phone'];
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit Profile</title>
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
</head>
<body class="bg-gray-900 font-inter text-white min-h-screen">
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
                    <a href="index.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
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
                        <span>Trainers</span>
                         <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Gyms</span>
                    </a> 
                    </a>
                </div>

                <!-- User Info -->
                <div class="relative flex items-center space-x-4">
                    <div class="hidden sm:block text-right">
                        <p class="text-white font-medium" id="userName"><?php echo htmlspecialchars($userData['name']); ?></p>
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
                <a href="index.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-orange-500">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span class="text-xs">Home</span>
                </a>
                <a href="nutrition.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-orange-500">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span class="text-xs">Nutrition</span>
                </a>
                <a href="trainer.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400 hover:text-orange-500">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="text-xs">Trainers</span>
                </a>
                 <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Gyms</span>
                    </a> 
            </div>
        </div>
    </nav>
            <br><br>
 <!-- Main Content -->
<main class="pt-20 p-4 sm:p-6 lg:p-8">
  <div class="max-w-7xl mx-auto">
    <!-- Profile Header -->
    <div class="mb-8">
      <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">
        Profile - 
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-500">
          <?php echo htmlspecialchars(explode(" ", $userData['name'])[0]); ?>
        </span>
      </h1>
      <p class="text-gray-400 text-lg">Manage your personal details and fitness journey.</p>
    </div>

    <!-- Profile Card - Premium & Attractive Design -->
    <div class="bg-gradient-to-br from-gray-800/60 via-gray-900/80 to-black/50 backdrop-blur-xl rounded-2xl p-6 md:p-8 border border-orange-500/20 shadow-2xl relative overflow-hidden">
      <!-- Subtle Glow Effect -->
      <div class="absolute inset-0 bg-gradient-to-tr from-orange-500/5 to-red-600/5 pointer-events-none"></div>
      
      <div class="relative z-10">
        <!-- Header: Profile Image + Name -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8">
          <!-- Profile Avatar -->
          <div class="trainer-avatar relative group">
            <div class="w-24 h-24 md:w-28 md:h-28 rounded-full overflow-hidden border-4 border-orange-500/30 shadow-xl 
                        transition-all duration-500 group-hover:border-orange-400 group-hover:shadow-2xl 
                        group-hover:scale-105">
              <?php 
              $trainerImg = '';
              $imageField = trim($userData['trainer_image'] ?? '');

              if ($imageField !== '') {
                  if (preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif|webp)$/i', $imageField)) {
                      $trainerImg = 'uploads/' . $imageField;
                  } elseif (strpos($imageField, 'uploads/') === 0) {
                      $trainerImg = $imageField;
                  } elseif (filter_var($imageField, FILTER_VALIDATE_URL)) {
                      $trainerImg = $imageField;
                  }
              }

              $showImage = $trainerImg && file_exists($trainerImg);
              ?>
              
              <?php if ($showImage): ?>
                <img src="<?php echo htmlspecialchars($trainerImg); ?>" 
                     alt="Profile Image" 
                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
              <?php else: ?>
                <!-- Animated Gradient Avatar with Initial -->
                <div class="w-full h-full bg-gradient-to-br from-orange-500 via-red-500 to-pink-600 
                            flex items-center justify-center text-white text-3xl md:text-4xl font-bold
                            animate-pulse-slow">
                  <?php echo strtoupper(substr($userData['name'], 0, 1)); ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Online Status Badge -->
            <div class="absolute -bottom-2 -right-2 w-10 h-10 bg-green-500 rounded-full border-4 border-gray-900 
                        flex items-center justify-center shadow-lg animate-pulse">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
            </div>
          </div>

          <!-- Name & Member Since -->
          <div class="text-center sm:text-left">
            <h2 class="text-3xl md:text-4xl font-bold text-white flex items-center gap-3 justify-center sm:justify-start">
              <?php echo htmlspecialchars($userData['name']); ?>
              <span class="text-sm bg-gradient-to-r from-orange-400 to-red-500 text-white px-3 py-1 rounded-full font-medium">
                Member
              </span>
            </h2>
            <p class="text-gray-400 text-sm mt-1 flex items-center gap-2 justify-center sm:justify-start">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              Member since: <?php echo date('F Y', strtotime($userData['created_at'] ?? '2025-08-01')); ?>
            </p>
          </div>
        </div>

        <!-- Contact & Personal Information Grid -->
        <div class="bg-gray-800/70 backdrop-blur-md rounded-xl p-6 md:p-7 border border-gray-700/50">
          <h3 class="text-xl font-bold text-orange-400 mb-5 flex items-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Personal & Contact Details
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <!-- Email -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Email</p>
                <p class="text-white font-medium truncate"><?php echo htmlspecialchars($userData['email']); ?></p>
              </div>
            </div>

            <!-- Phone -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Phone</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['phone']); ?></p>
              </div>
            </div>

            <!-- Address -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Address</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['address'] ?? '123 Fitness Lane, FitCity'); ?></p>
              </div>
            </div>

            <!-- Age -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Age</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['age'] ?? '35'); ?> years</p>
              </div>
            </div>

            <!-- Date of Birth -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-pink-500 to-rose-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Date of Birth</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['dob'] ?? 'January 15, 1990'); ?></p>
              </div>
            </div>

            <!-- Blood Group -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-pink-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H9m12 0a1 1 0 001-1V10a1 1 0 00-1-1H9a1 1 0 00-1 1v9a1 1 0 001 1m-3-9v.01M12 7v.01"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Blood Group</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['blood_group'] ?? 'A+'); ?></p>
              </div>
            </div>

            <!-- Location -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Location</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['location'] ?? 'FitCity, State, Country'); ?></p>
              </div>
            </div>

            <!-- Gender -->
            <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400">Gender/Pronouns</p>
                <p class="text-white font-medium"><?php echo htmlspecialchars($userData['gender'] ?? 'Male (he/him)'); ?></p>
              </div>
            </div>

            <!-- Interests -->
            <?php if (!empty(trim($userData['intrests'] ?? ''))): ?>
              <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                  </svg>
                </div>
                <div class="flex-1">
                  <p class="text-xs text-gray-400">Interests/Hobbies</p>
                  <div class="flex flex-wrap gap-2 mt-1">
                    <?php 
                    $interests = array_filter(array_map('trim', explode(',', $userData['intrests'])));
                    foreach ($interests as $interest): 
                    ?>
                      <span class="px-2 py-1 bg-gradient-to-r from-orange-500/20 to-red-500/20 text-orange-300 
                                   border border-orange-500/30 rounded-full text-xs font-medium">
                        <?php echo htmlspecialchars($interest); ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Website & Social Links -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-6">
            <!-- Website -->
            <?php if (!empty(trim($userData['website'] ?? ''))): ?>
              <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
                <p class="text-xs text-gray-400 mb-1">Website</p>
                <a href="<?php echo htmlspecialchars($userData['website']); ?>" target="_blank" 
                   class="text-orange-400 hover:text-orange-300 transition-colors text-sm flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                  </svg>
                  <?php echo htmlspecialchars(parse_url($userData['website'], PHP_URL_HOST)); ?>
                </a>
              </div>
            <?php endif; ?>

            <!-- Social Links -->
            <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-4 border border-gray-700/50">
              <p class="text-xs text-gray-400 mb-1">Social Links</p>
              <div class="flex gap-3">
                <a href="https://twitter.com/example" target="_blank" class="text-orange-500 hover:text-orange-300 transition-colors">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8.29 20.25c7.55 0 11.68-6.26 11.68-11.68 0-.18 0-.36-.01-.53A8.35 8.35 0 0022 5.92a8.14 8.14 0 01-2.36.65 4.1 4.1 0 001.8-2.27 8.2 8.2 0 01-2.6.99 4.1 4.1 0 00-7 3.74 11.64 11.64 0 01-8.45-4.29 4.1 4.1 0 001.27 5.48 4.05 4.05 0 01-1.86-.51v.05a4.1 4.1 0 003.29 4.02 4.05 4.05 0 01-1.85.07 4.1 4.1 0 003.83 2.85A8.23 8.23 0 012 19.54a11.6 11.6 0 006.29.71"/>
                  </svg>
                </a>
                <a href="https://github.com/example" target="_blank" class="text-orange-500 hover:text-orange-300 transition-colors">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                  </svg>
                </a>
              </div>
            </div>
          </div>

          <!-- Privacy Settings -->
          <div class="mt-6">
            <p class="text-sm text-gray-400 mb-2">Privacy Settings</p>
            <select class="bg-gray-700/80 text-white rounded-lg px-4 py-2 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
              <option>Public</option>
              <option>Friends Only</option>
              <option>Private</option>
            </select>
          </div>

          <!-- Last Updated -->
          <div class="flex items-center gap-2 text-xs text-gray-500 mt-6 pt-4 border-t border-gray-700/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Last Updated: <?php echo date('M j, Y \a\t g:i A', strtotime($userData['updated_at'] ?? 'now')); ?>
          </div>
        </div>

        <!-- Edit Profile Button -->
        <div class="mt-8 flex justify-center md:justify-end">
          <a href="edit_profile.php" 
             class="group inline-flex items-center gap-3 bg-gradient-to-r from-orange-500 to-red-600 
                    hover:from-orange-600 hover:to-red-700 text-white font-bold py-3 px-6 rounded-xl 
                    shadow-lg transition-all duration-300 transform hover:scale-105 hover:shadow-2xl">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                 class="transition-transform group-hover:translate-x-1">
              <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span>Edit Profile</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Add to <style> in <head> -->
<style>
  @keyframes pulse-slow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }
  .animate-pulse-slow {
    animation: pulse-slow 3s ease-in-out infinite;
  }
  .trainer-avatar .group:hover img {
    filter: brightness(1.1) contrast(1.1);
  }
</style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const profileButton = document.getElementById('profileButton');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileButton && profileDropdown) {
            profileButton.addEventListener('click', function(e) {
                e.preventDefault();
                profileDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', function(e) {
                if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });
        }
    });
    </script>
</body>
</html>