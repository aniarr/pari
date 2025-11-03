<?php
// -----------------------------
// TRAINER DASHBOARD
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

// Fetch trainer details
$trainer = null;
$stmt = $conn->prepare("SELECT * FROM trainer_details WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $trainer = $row;
}
$stmt->close();

// Fallback: all fields empty if not found
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

// Fetch unread messages count
$unreadCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE trainer_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $unreadCount = $row['count'];
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>RawFit - Trainer Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { inter: ['Inter', 'sans-serif'] } } }
    }
  </script>
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

                <!-- Navigation Links -->
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
                    <!-- Dropdown Menu -->
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
    <!-- Dashboard Heading -->
    <div class="mb-8">
      <h1 class="text-3xl sm:text-4xl font-bold mb-2 bg-gradient-to-r from-orange-500 to-red-500 text-transparent bg-clip-text">
        Welcome, <?php echo htmlspecialchars($trainer['name']); ?>
      </h1>
    </div>

    <!-- Trainer Info -->
    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700 mb-8">
      <h2 class="text-2xl font-bold text-white mb-4">Your Profile</h2>
      <div class="flex flex-col md:flex-row gap-6">
        <div class="w-32 h-32 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center overflow-hidden">
          <?php if (!empty($trainer['trainer_image'])): ?>
            <img src="<?php echo htmlspecialchars($trainer['trainer_image']); ?>" alt="Trainer Image" class="w-32 h-32 object-cover rounded-full">
          <?php else: ?>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          <?php endif; ?>
        </div>
        <div>
          <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($trainer['name']); ?></h3>
          <p class="text-orange-400 font-medium mb-2"><?php echo htmlspecialchars($trainer['email']); ?></p>
          <p class="text-gray-300 mb-2">Phone: <?php echo htmlspecialchars($trainer['phone']); ?></p>
          <p class="text-gray-300 mb-2">Address: <?php echo htmlspecialchars($trainer['address']); ?></p>
          <p class="text-gray-300 mb-2">Age: <?php echo htmlspecialchars($trainer['age']); ?></p>
          <p class="text-gray-300 mb-2">DOB: <?php echo htmlspecialchars($trainer['dob']); ?></p>
          <p class="text-gray-300 mb-2">Blood Group: <?php echo htmlspecialchars($trainer['blood_group']); ?></p>
          <p class="text-gray-300 mb-2">Location: <?php echo htmlspecialchars($trainer['location']); ?></p>
          <p class="text-gray-300 mb-2">Gender: <?php echo htmlspecialchars($trainer['gender']); ?></p>
          <p class="text-gray-300 mb-2">Interests: <?php echo nl2br(htmlspecialchars($trainer['intrests'])); ?></p>
          <p class="text-gray-300 mb-2">Website: <?php echo htmlspecialchars($trainer['website']); ?></p>
          <p class="text-gray-300 mb-2">Created At: <?php echo htmlspecialchars($trainer['created_at']); ?></p>
          <p class="text-gray-300 mb-2">Updated At: <?php echo htmlspecialchars($trainer['updated_at']); ?></p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
      <a href="manage_courses.php" class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold py-4 px-6 rounded-lg shadow-md flex items-center justify-center transition-all duration-300">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2">
          <path d="M3 7l9-4 9 4v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <path d="M9 22V12h6v10"/>
        </svg>
        Manage Courses
      </a>
      <a href="trainer_messages.php" class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold py-4 px-6 rounded-lg shadow-md flex items-center justify-center transition-all duration-300">
        <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Messages
        <?php if ($unreadCount > 0): ?>
          <span class="ml-auto bg-orange-500 text-white text-xs px-2 py-1 rounded-full" id="unreadCount"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- Recent Messages -->
    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
      <h2 class="text-2xl font-bold text-white mb-4">Recent Messages</h2>
      <div id="messagesContainer" class="space-y-4">
        <!-- Messages will be loaded here by JavaScript -->
      </div>
    </div>
  </div>
</main>

<!-- Add before closing body tag -->
<script>
document.getElementById('messageForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const message = document.getElementById('messageContent').value;
  const courseId = document.getElementById('course_id').value;
  const trainerId = document.getElementById('trainer_id').value;

  try {
    const response = await fetch('handle_message.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        message,
        course_id: courseId,
        trainer_id: trainerId
      })
    });

    const result = await response.json();
    if (result.success) {
      document.getElementById('messageContent').value = '';
      loadMessages();
    }
  } catch (error) {
    console.error('Error:', error);
  }
});

async function loadMessages() {
  const courseId = document.getElementById('course_id').value;
  try {
    const response = await fetch(`handle_message.php?course_id=${courseId}`);
    const messages = await response.json();
    const container = document.getElementById('messagesContainer');
    
    container.innerHTML = messages.map(msg => `
      <div class="bg-gray-700/50 rounded-lg p-4 ${msg.sender_id == <?php echo $user_id; ?> ? 'ml-auto' : ''}">
        <p class="text-sm text-orange-400">${msg.sender_name}</p>
        <p class="text-white">${msg.message}</p>
        <p class="text-xs text-gray-400">${msg.created_at}</p>
      </div>
    `).join('');
  } catch (error) {
    console.error('Error:', error);
  }
}

// Load messages on page load
loadMessages();
// Refresh messages every 10 seconds
setInterval(loadMessages, 10000);
</script>
</body>
</html>