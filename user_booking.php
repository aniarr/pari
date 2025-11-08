<?php
// -----------------------------
// COURSE DETAILS PAGE - FULLY FIXED
// -----------------------------

ini_set('display_errors', 1); // dev only
error_reporting(E_ALL);

session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Logged-in user info
$user_id = intval($_SESSION['user_id']);
$userName = "User";
$stmt = $conn->prepare("SELECT name FROM register WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $userName = $row['name'];
}
$stmt->close();

// Get course_id from URL
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

$course = null;
$trainer = null;

if ($course_id > 0) {
    // Fetch course details
    $sql = "SELECT * FROM trainer_courses WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $course = [
            'id' => $row['id'],
            'trainer_id' => $row['trainer_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category' => $row['category'],
            'duration' => $row['duration'],
            'status' => $row['status'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'image' => !empty($row['image_path']) ? "uploads/" . $row['image_path'] : "https://via.placeholder.com/400x300",
            'price' => rand(60, 85) // demo price
        ];
    }
    $stmt->close();

    // Fetch trainer details
    if ($course) {
        $tstmt = $conn->prepare("SELECT * FROM trainer_details WHERE id = ? LIMIT 1");
        $tstmt->bind_param("i", $course['trainer_id']);
        $tstmt->execute();
        $tres = $tstmt->get_result();
        if ($trow = $tres->fetch_assoc()) {
            $trainer = $trow;
        }
        $tstmt->close();

        // Fallback if trainer not found
        if (!$trainer) {
            $trainer = [
                'id' => '',
                'name' => 'Unknown Trainer',
                'email' => '',
                'phone' => '',
                'address' => '',
                'age' => '',
                'dob' => '',
                'blood_group' => '',
                'location' => '',
                'gender' => '',
                'intrests' => '',
                'website' => '',
                'created_at' => '',
                'updated_at' => '',
                'trainer_image' => ''
            ];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>RawFit - Course Details</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { inter: ['Inter', 'sans-serif'] } } }
    }
  </script>
  <style>
    .trainer-avatar {
      transition: transform 0.3s ease;
    }
    .trainer-avatar:hover {
      transform: scale(1.08);
    }
  </style>
</head>
<body class="bg-gray-900 font-inter">

<!-- Navbar -->
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

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="home.php" class="nav-link active flex items-center space-x-2 px-3 py-2 rounded-lg bg-orange-500 text-white transition-colors">
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
                </a>
                 <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Gyms</span>
                    </a> 
            </div>

            <!-- User Info -->
            <div class="relative flex items-center space-x-4">
                <div class="hidden sm:block text-right">
                    <p class="text-white font-medium" id="userName"><?php echo htmlspecialchars($userName); ?></p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center cursor-pointer" id="profileButton">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <!-- Dropdown -->
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden z-50">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
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
            <a href="trainer.php" class="mobile-nav-link active flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="text-xs">Trainer</span>
            </a>
        </div>
    </div>
</nav>
    <br><br>
<main class="pt-24 min-h-screen p-4 sm:p-6 lg:p-8">
  <div class="max-w-7xl mx-auto">
    <?php if ($course && $trainer): ?>
      
      <!-- Course Heading -->
      <div class="mb-8 ">
        <h1 class="text-3xl sm:text-4xl font-bold mb-2 bg-gradient-to-r from-orange-500 to-red-500 text-transparent bg-clip-text">
          <?php echo htmlspecialchars($course['title']); ?>
          </h1>
        <p class="text-gray-400 text-lg">
          Led by <span class="font-semibold text-white bg-gradient-to-r from-orange-400 to-red-400 text-transparent bg-clip-text"><?php echo htmlspecialchars($trainer['name']); ?></span>
        </p>
      </div>

      <!-- Course & Details -->
      <div class="grid lg:grid-cols-2 gap-8 mb-12">
        <div>
          <img src="<?php echo htmlspecialchars($course['image']); ?>" alt="Course Image"
               class="w-full h-96 object-cover rounded-xl shadow-lg">
        </div>
        <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
          <h2 class="text-2xl font-bold text-white mb-4">Course Details</h2>
          <div class="space-y-4">
            <div>
              <h3 class="text-lg font-semibold text-orange-400">Category</h3>
              <p class="text-gray-300"><?php echo htmlspecialchars($course['category']); ?></p>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-orange-400">Duration</h3>
              <p class="text-gray-300"><?php echo htmlspecialchars($course['duration']); ?></p>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-orange-400">Schedule</h3>
              <p class="text-gray-300">
                From <?php echo htmlspecialchars(date('M d, Y', strtotime($course['start_date']))); ?> 
                to <?php echo htmlspecialchars(date('M d, Y', strtotime($course['end_date']))); ?>
              </p>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-orange-400">Status</h3>
              <p class="text-gray-300"><?php echo htmlspecialchars($course['status']); ?></p>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-orange-400">Description</h3>
              <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
            </div>
           <div class="flex flex-col sm:flex-row items-center gap-3 mt-4">
            <!-- Get Course Button -->
            <a href="get_course.php?course_id=<?php echo $course['id']; ?>"
              class="flex-1 text-center bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600
                      text-white font-semibold py-3 px-6 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-[1.02]">
              Get Course
            </a>

            <!-- Message Trainer Button -->
            <?php if ($trainer['id']): ?>
            <a href="messages.php?trainer_id=<?php echo intval($trainer['id']); ?>&course_id=<?php echo intval($course_id); ?>" 
              class="flex-1 inline-flex justify-center items-center space-x-2 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600
                      text-white font-semibold py-3 px-6 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-[1.02]">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                  class="stroke-current">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <span>Message Trainer</span>
            </a>
            <?php endif; ?>
          </div>

        </div>

          </div>
        </div>
      </div>

   <!-- Trainer Info - Enhanced & Attractive Design -->
<div class="bg-gradient-to-br from-gray-800/60 via-gray-900/80 to-black/50 backdrop-blur-xl rounded-2xl p-6 md:p-8 border border-orange-500/20 shadow-2xl relative overflow-hidden">
  <!-- Subtle Glow Effect -->
  <div class="absolute inset-0 bg-gradient-to-tr from-orange-500/5 to-red-600/5 pointer-events-none"></div>
  
  <div class="relative z-10">
    <h2 class="text-3xl md:text-4xl font-bold text-white mb-6 flex items-center gap-3">
      <span class="inline-block w-2 h-8 bg-gradient-to-b from-orange-400 to-red-500 rounded-full"></span>
      About Your Trainer
    </h2>

    <div class="flex flex-col md:flex-row gap-6 lg:gap-8">
      
      <!-- Trainer Avatar with Glow & Animation -->
      <div class="trainer-avatar relative group">
        <div class="w-36 h-36 md:w-40 md:h-40 rounded-full overflow-hidden border-4 border-orange-500/30 shadow-xl 
                     ">
          <?php 
          $trainerImg = '';
          $imageField = trim($trainer['trainer_image'] ?? '');

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
                 alt="<?php echo htmlspecialchars($trainer['name']); ?>" 
                 class="w-full h-full object-cover  ">
          <?php else: ?>
            <!-- Animated Gradient Avatar with Initial -->
            <div class="w-full h-full bg-gradient-to-br from-orange-500 via-red-500 to-pink-600 
                        flex items-center justify-center text-white text-4xl md:text-5xl font-bold
                 ">
              <?php echo strtoupper(substr($trainer['name'], 0, 1)); ?>
            </div>
          <?php endif; ?>
        </div>

    
      </div>

      <!-- Trainer Details with Icons & Styling -->
      <div class="flex-1 space-y-4">
        <!-- Name & Title -->
        <div>
          <h3 class="text-2xl md:text-3xl font-bold text-white flex items-center gap-2">
            <?php echo htmlspecialchars($trainer['name']); ?>
           
          </h3>
          <p class="text-orange-400 font-medium mt-1 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <?php echo htmlspecialchars($trainer['email']); ?>
          </p>
        </div>

        <!-- Grid of Key Info -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
          <!-- Age -->
          <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-3 border border-gray-700/50">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-400">Age</p>
              <p class="text-white font-medium"><?php echo htmlspecialchars($trainer['age']); ?> years</p>
            </div>
          </div>

          <!-- Address -->
          <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-3 border border-gray-700/50">
            <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-400">Address</p>
              <p class="text-white font-medium"><?php echo htmlspecialchars($trainer['address']); ?></p>
            </div>
          </div>

          <!-- Phone -->
          <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-3 border border-gray-700/50">
            <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-400">Phone</p>
              <p class="text-white font-medium"><?php echo htmlspecialchars($trainer['phone']); ?></p>
            </div>
          </div>

          <!-- Location -->
          <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-3 border border-gray-700/50">
            <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-400">Location</p>
              <p class="text-white font-medium"><?php echo htmlspecialchars($trainer['location']); ?></p>
            </div>
          </div>

          <!-- Blood Group -->
          <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-3 border border-gray-700/50">
            <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H9m12 0a1 1 0 001-1V10a1 1 0 00-1-1H9a1 1 0 00-1 1v9a1 1 0 001 1m-3-9v.01M12 7v.01"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-400">Blood Group</p>
              <p class="text-white font-medium"><?php echo htmlspecialchars($trainer['blood_group']); ?></p>
            </div>
          </div>

          <!-- Gender -->
          <div class="flex items-center gap-3 bg-gray-800/50 backdrop-blur-sm rounded-xl p-3 border border-gray-700/50">
            <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-400">Gender</p>
              <p class="text-white font-medium"><?php echo htmlspecialchars($trainer['gender']); ?></p>
            </div>
          </div>
        </div>

        <!-- Interests -->
        <?php if (!empty(trim($trainer['intrests']))): ?>
          <div class="mt-4">
            <p class="text-sm text-gray-400 mb-2">Specializations</p>
            <div class="flex flex-wrap gap-2">
              <?php 
              $interests = array_filter(array_map('trim', explode(',', $trainer['intrests'])));
              foreach ($interests as $interest): 
              ?>
                <span class="px-3 py-1 bg-gradient-to-r from-orange-500/20 to-red-500/20 text-orange-300 
                             border border-orange-500/30 rounded-full text-sm font-medium">
                  <?php echo htmlspecialchars($interest); ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Website Link -->
        <?php if (!empty(trim($trainer['website']))): ?>
          <div class="mt-4">
            <a href="<?php echo htmlspecialchars($trainer['website']); ?>" target="_blank" 
               class="inline-flex items-center gap-2 text-orange-400 hover:text-orange-300 transition-colors text-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
              </svg>
              <?php echo htmlspecialchars(parse_url($trainer['website'], PHP_URL_HOST)); ?>
            </a>
          </div>
        <?php endif; ?>

        <!-- Meta Info -->
        <div class="flex items-center gap-6 text-xs text-gray-500 mt-6 pt-4 border-t border-gray-700/50">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Joined <?php echo date('M Y', strtotime($trainer['created_at'])); ?>
          </div>
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Updated <?php echo date('M d, Y', strtotime($trainer['updated_at'])); ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Message Button -->
    <div class="mt-8 flex justify-center md:justify-end">
      <?php if ($trainer['id']): ?>
        <a href="messages.php?trainer_id=<?php echo intval($trainer['id']); ?>&course_id=<?php echo intval($course_id); ?>" 
           class="group inline-flex items-center gap-3 bg-gradient-to-r from-orange-500 to-red-600 
                  hover:from-orange-600 hover:to-red-700 text-white font-bold py-3 px-6 rounded-xl 
                  shadow-lg transition-all duration-300 transform hover:scale-105 hover:shadow-2xl">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
               class="transition-transform group-hover:translate-x-1">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <span>Message Trainer</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

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
     
    <?php else: ?>
      <!-- Course Not Found -->
      <div class="text-center py-12">
        <h3 class="text-xl font-semibold text-gray-300 mb-2">Course Not Found</h3>
        <p class="text-gray-500">The requested course could not be found.</p>
        <a href="trainer.php" class="mt-4 inline-block bg-gradient-to-r from-orange-500 to-red-500 text-white font-semibold py-3 px-6 rounded-lg">
          Back to Trainers
        </a>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
  // Profile dropdown toggle
  document.getElementById('profileButton').addEventListener('click', () => {
    document.getElementById('profileDropdown').classList.toggle('hidden');
  });
</script>
</body>
</html>