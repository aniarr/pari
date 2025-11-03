<?php
session_start();

// === 1. DB CONNECTION ===
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// === 2. INSERT GYM DETAILS ===
// Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header('Location: login_owner.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id      = $_SESSION['owner_id'];
    $gym_name      = $_POST['gym_name'];
    $location      = $_POST['location'];
    $phone         = $_POST['phone'];
    $timings       = $_POST['timings'];
    $facilities    = $_POST['facilities'];
    $gym_address   = $_POST['gym_address'];
    $gym_state     = $_POST['gym_state'];
    $gym_zip       = $_POST['gym_zip'];
    $gym_phone     = $_POST['gym_phone'];
    $gym_email     = $_POST['gym_email'];
    $gym_description = $_POST['gym_description'];
    $capacity        = $_POST['capacity'];
    $num_trainers   = $_POST['num_trainers'];
    $experience_years = $_POST['experience_years'];
    $registrations   = isset($_POST['registrations']) ? $_POST['registrations'] : 0;

    $stmt = $conn->prepare("INSERT INTO gyms 
        (owner_id, gym_name, location, phone, timings, facilities, gym_address, gym_state, gym_zip, 
        gym_phone, gym_email, gym_description, capacity, num_trainers, experience_years, registrations, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssssssssssiiii",
        $owner_id, $gym_name, $location, $phone, $timings, $facilities, $gym_address,
        $gym_state, $gym_zip, $gym_phone, $gym_email, $gym_description, $capacity, $num_trainers,
        $experience_years, $registrations);

    if ($stmt->execute()) {
        $gym_id = $stmt->insert_id;

        // === 3. HANDLE IMAGE UPLOAD ===
        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = "uploads/gyms/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                $originalName = basename($_FILES['images']['name'][$key]);
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = uniqid('gym_', true) . '.' . strtolower($ext);
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    // Insert into gym_images table
                    $stmt = $conn->prepare("INSERT INTO gym_images (gym_id, filename) VALUES (?, ?)");
                    $stmt->bind_param("is", $gym_id, $filename);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $message = "<div class='success-msg'>Gym details added successfully!</div>";
    } else {
        $message = "<div class='error-msg'>Error adding gym. Please try again.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Gym | RawFit</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>

<body class="bg-gradient-to-br from-gray-950 via-gray-900 to-black text-white min-h-screen flex flex-col items-center pt-28 pb-20">

  <!-- Navbar -->
  <nav class="fixed top-0 inset-x-0 z-50 bg-black/70 backdrop-blur-xl border-b border-gray-800 shadow-xl">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-red-600 shadow-lg shadow-orange-600/50 animate-pulse">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-white">
            <path d="M6.5 6.5h11v11h-11z" />
            <path d="M6.5 6.5L2 2M17.5 6.5L22 2M6.5 17.5L2 22M17.5 17.5L22 22" />
          </svg>
        </div>
        <span class="text-2xl font-black bg-gradient-to-r from-orange-400 to-red-600 bg-clip-text text-transparent tracking-tight">RawFit</span>
      </div>

      <div class="hidden md:flex space-x-10">
        <a href="owner_home.php" class="text-orange-400 hover:text-orange-300 font-semibold transition flex items-center gap-2">
          <i class="fas fa-home"></i> Home
        </a>
        <a href="display_gym.php" class="text-gray-300 hover:text-white font-medium transition flex items-center gap-2">
          <i class="fas fa-dumbbell"></i> View Gyms
        </a>
      </div>

      <div class="text-orange-300 font-medium text-sm flex items-center gap-2">
        <i class="fas fa-user-circle"></i>
        <?php echo htmlspecialchars($_SESSION['owner_name'] ?? 'Owner'); ?>
      </div>
    </div>
  </nav>

  <!-- Title -->
  <div class="text-center mb-12 animate-fadeIn">
    <h1 class="text-5xl md:text-6xl font-extrabold bg-gradient-to-r from-orange-400 via-red-500 to-pink-600 bg-clip-text text-transparent drop-shadow-2xl">
      Add Your Gym
    </h1>
    <p class="text-gray-400 mt-3 text-lg">Fill in the details to showcase your fitness empire</p>
  </div>

  <!-- Glass Card -->
  <div class="w-full max-w-5xl bg-white/5 backdrop-blur-xl rounded-3xl p-8 md:p-12 shadow-2xl border border-white/10 hover:border-orange-500/30 transition-all duration-500">

    <?= $message ?>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6" id="gymForm">
      <!-- Left Column - Basic Info -->
      <div class="space-y-6">
        <div class="relative">
          <input type="text" name="gym_name" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Gym Name
          </label>
        </div>

        <div class="relative">
          <input type="text" name="location" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Location Area
          </label>
        </div>

        <div class="relative">
          <input type="text" name="gym_address" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Full Address
          </label>
        </div>

        <div class="relative">
          <input type="text" name="gym_state" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            State
          </label>
        </div>

        <div class="relative">
          <input type="text" name="gym_zip" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            ZIP Code
          </label>
        </div>

        <!-- Left Column Stats -->
        <div class="relative">
          <input type="number" name="capacity" required placeholder=" " min="0" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Maximum Capacity
          </label>
        </div>

        <div class="relative">
          <input type="number" name="registrations" placeholder=" " min="0" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Current Registrations
          </label>
        </div>
      </div>

      <!-- Right Column - Contact & Additional Info -->
      <div class="space-y-6">
        <div class="relative">
          <input type="text" name="phone" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Owner Phone
          </label>
        </div>

        <div class="relative">
          <input type="text" name="gym_phone" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Gym Contact
          </label>
        </div>

        <div class="relative">
          <input type="email" name="gym_email" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Gym Email
          </label>
        </div>

        <div class="relative">
          <input type="text" name="timings" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Timings (e.g. 5AM - 11PM)
          </label>
        </div>

        <div class="relative">
          <input type="text" name="facilities" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Facilities (comma separated)
          </label>
        </div>

        <!-- Right Column Stats -->
        <div class="relative">
          <input type="number" name="num_trainers" required placeholder=" " min="0" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Number of Trainers
          </label>
        </div>

        <div class="relative">
          <input type="number" name="experience_years" required placeholder=" " min="0" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Years of Experience
          </label>
        </div>
      </div>

      <!-- File Upload -->
      <div class="md:col-span-2">
        <div class="relative border-2 border-dashed border-orange-500/50 rounded-xl p-8 text-center cursor-pointer hover:border-orange-400 transition"
             onclick="document.getElementById('images').click()">
          <input type="file" name="images[]" id="images" multiple accept="image/*"
                 class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                 onchange="previewImages(this)">
          <i class="fas fa-cloud-upload-alt text-4xl text-orange-400 mb-3"></i>
          <p class="text-gray-300 font-medium">Click to upload gym images (multiple)</p>
        </div>
        <div id="preview" class="flex flex-wrap gap-3 mt-4"></div>
      </div>

      <!-- Submit -->
      <div class="md:col-span-2 flex justify-center">
        <button type="submit"
                class="w-full md:w-auto px-12 py-4 bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 rounded-xl font-bold text-white shadow-lg hover:shadow-orange-600/40 transition-all duration-300 flex items-center justify-center gap-2 text-lg">
          <i class="fas fa-plus"></i> Add Gym Now
        </button>
      </div>
    </form>
  </div>

  <!-- Back Link -->
  <a href="home.php" class="mt-12 text-orange-400 hover:text-orange-300 font-medium flex items-center gap-2 transition">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
  </a>

  <!-- Success / Error Messages (Tailwind only) -->
  <style>
    .success-msg {
      @apply bg-gradient-to-r from-green-900/30 to-green-800/20 border border-green-600/40 text-green-300 py-3 px-6 rounded-xl text-center font-semibold animate-pulse;
    }
    .error-msg {
      @apply bg-gradient-to-r from-red-900/30 to-red-800/20 border border-red-600/40 text-red-300 py-3 px-6 rounded-xl text-center font-semibold animate-pulse;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:none; } }
    .animate-fadeIn { animation: fadeIn .5s ease-out; }
  </style>

  <script>
    function previewImages(input) {
      const preview = document.getElementById('preview');
      preview.innerHTML = '';
      Array.from(input.files).forEach(file => {
        if (file.type.match('image.*')) {
          const reader = new FileReader();
          reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'w-24 h-24 object-cover rounded-lg border-2 border-orange-500/30 shadow-lg hover:scale-105 transition';
            preview.appendChild(img);
          };
          reader.readAsDataURL(file);
        }
      });
    }
  </script>
</body>
</html>