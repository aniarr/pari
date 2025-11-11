<?php
session_start();

// === 1. DB CONNECTION ===
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$errors = [];

// Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header('Location: login_owner.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id         = $_SESSION['owner_id'];
    $gym_name         = trim($_POST['gym_name']);
    $area             = trim($_POST['area']);
    $city             = trim($_POST['city']);
    $gym_state        = trim($_POST['gym_state']);
    $gym_zip          = trim($_POST['gym_zip']);
    $gym_address      = trim($_POST['gym_address']);
    $phone            = trim($_POST['phone']);
    $gym_phone        = trim($_POST['gym_phone']);
    $gym_email        = trim($_POST['gym_email']);
    $timings          = trim($_POST['timings']);
    $facilities       = trim($_POST['facilities']);
    $gym_description  = trim($_POST['gym_description']);
    $capacity         = (int)$_POST['capacity'];
    $num_trainers     = (int)$_POST['num_trainers'];
    $experience_years = (int)$_POST['experience_years'];
    $registrations    = isset($_POST['registrations']) ? (int)$_POST['registrations'] : 0;

    // === VALIDATIONS ===
    if (!filter_var($gym_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid gym email format.";
    }
    if (!preg_match('/^\d{6}$/', $gym_zip)) {
        $errors[] = "PIN code must be exactly 6 digits.";
    }
    if (!preg_match('/^\d{10}$/', $phone) || !preg_match('/^\d{10}$/', $gym_phone)) {
        $errors[] = "Phone numbers must be 10 digits.";
    }

    if (empty($errors)) {
        // === INSERT GYM ===
        $stmt = $conn->prepare("INSERT INTO gyms 
            (owner_id, gym_name, location, phone, timings, facilities, gym_address, gym_state, gym_zip, 
            gym_phone, gym_email, gym_description, capacity, num_trainers, experience_years, registrations, 
            status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

        $location = "$area, $city, $gym_state";

        $stmt->bind_param("isssssssssssiiii",
            $owner_id, $gym_name, $location, $phone, $timings, $facilities, $gym_address,
            $gymHAS_state, $gym_zip, $gym_phone, $gym_email, $gym_description, $capacity, $num_trainers,
            $experience_years, $registrations);

        if ($stmt->execute()) {
            $gym_id = $stmt->insert_id;
            $stmt->close();

            // === GEOCODING: Address → Lat/Lng ===
            $fullAddress = trim("$gym_address, $area, $city, $gym_state $gym_zip, India");
            $fullAddress = urlencode($fullAddress);

            $geoUrl = "https://nominatim.openstreetmap.org/search?format=json&q={$fullAddress}&limit=1";
            $context = stream_context_create([
                'http' => [
                    'header' => "User-Agent: RawFit/1.0\r\n",
                    'timeout' => 10
                ]
            ]);
            $geoData = @file_get_contents($geoUrl, false, $context);

            $lat = $lng = null;
            if ($geoData) {
                $geo = json_decode($geoData, true);
                if (!empty($geo[0]['lat']) && !empty($geo[0]['lon'])) {
                    $lat = $geo[0]['lat'];
                    $lng = $geo[0]['lon'];

                    $geoStmt = $conn->prepare("UPDATE gyms SET lat = ?, lng = ? WHERE gym_id = ?");
                    $geoStmt->bind_param("ddi", $lat, $lng, $gym_id);
                    $geoStmt->execute();
                    $geoStmt->close();
                }
            }

            // === HANDLE IMAGE UPLOADS ===
            $uploadDir = "pari/uploads/gyms/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $firstImage = null;
            $imageFilenames = [];

            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;

                    $originalName = basename($_FILES['images']['name'][$key]);
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $filename = uniqid('gym_', true) . '.' . $ext;
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $imageFilenames[] = $filename;
                        if ($key === 0) {
                            $firstImage = $filename;
                        }
                    }
                }

                // Update main gym image
                if ($firstImage) {
                    $updateStmt = $conn->prepare("UPDATE gyms SET gym_image = ? WHERE gym_id = ?");
                    $updateStmt->bind_param("si", $firstImage, $gym_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                // Save all images to gym_images table
                if (!empty($imageFilenames)) {
                    $imgStmt = $conn->prepare("INSERT INTO gym_images (gym_id, filename) VALUES (?, ?)");
                    foreach ($imageFilenames as $img) {
                        $imgStmt->bind_param("is", $gym_id, $img);
                        $imgStmt->execute();
                    }
                    $imgStmt->close();
                }
            }

            $message = "<div class='success-msg'>Gym submitted successfully! Awaiting admin approval.</div>";
        } else {
            $message = "<div class='error-msg'>Error submitting gym. Please try again.</div>";
        }
    } else {
        foreach ($errors as $err) {
            $message .= "<div class='error-msg'>$err</div>";
        }
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
    .custom-select-wrapper { position: relative; }
    .custom-select {
      @apply w-full px-4 pt-6 pb-3 bg-gray-800/80 border border-gray-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition appearance-none;
    }
    .custom-select-wrapper::after {
      content: '\f078';
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      color: #fb923c;
    }
    .custom-select option {
      background: #1a1a1a;
      color: #e5e7eb;
      padding: 8px;
    }
    .custom-select:focus option:checked {
      background: #ea580c;
      color: white;
    }
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
        <a href="owner_dashboard.php" class="text-orange-400 hover:text-orange-300 font-semibold transition flex items-center gap-2">
          <i class="fas fa-home"></i> Home
        </a>
        <a href="gym_profile.php" class="text-gray-300 hover:text-white font-medium transition flex items-center gap-2">
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
    <p class="text-gray-400 mt-3 text-lg">Your gym will be live after admin approval</p>
  </div>

  <!-- Glass Card -->
  <div class="w-full max-w-5xl bg-white/5 backdrop-blur-xl rounded-3xl p-8 md:p-12 shadow-2xl border border-white/10 hover:border-orange-500/30 transition-all duration-500">

    <?= $message ?>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6" id="gymForm">
      <!-- Left Column -->
      <div class="space-y-6">
        <div class="relative">
          <input type="text" name="gym_name" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Gym Name
          </label>
        </div>

        <!-- State Dropdown -->
        <div class="relative custom-select-wrapper">
          <select name="gym_state" id="gym_state" required onchange="updateCities()" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
            <option value="" disabled selected></option>
            <?php
            $states = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Delhi", "Puducherry", "Jammu and Kashmir", "Ladakh"];
            foreach ($states as $state) {
                echo "<option value='$state'>$state</option>";
            }
            ?>
          </select>
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">State *</label>
        </div>
        

        <!-- City Dropdown -->
        <div class="relative custom-select-wrapper">
          <select name="city" id="city" required class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
            <option value="" disabled selected></option>
          </select>
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none">City *</label>
        </div>

        <!-- Area -->
        <div class="relative">
          <input type="text" name="area" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Area / Locality
          </label>
        </div>

        <!-- Full Address -->
        <div class="relative">
          <input type="text" name="gym_address" required placeholder=" " class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Full Address
          </label>
        </div>

        <!-- ZIP Code -->
        <div class="relative">
          <input type="text" name="gym_zip" required placeholder=" " pattern="\d{6}" maxlength="6" inputmode="numeric" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            PIN Code (6 digits)
          </label>
        </div>

        <!-- Capacity & Registrations -->
        <div class="relative">
          <input type="number" name="capacity" required placeholder=" " min="0" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Max Capacity
          </label>
        </div>

        <div class="relative">
          <input type="number" name="registrations" placeholder=" " min="0" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Current Registrations
          </label>
        </div>
      </div>

      <!-- Right Column -->
      <div class="space-y-6">
        <div class="relative">
          <input type="text" name="phone" required placeholder=" " pattern="\d{10}" maxlength="10" inputmode="numeric" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Owner Phone
          </label>
        </div>

        <div class="relative">
          <input type="text" name="gym_phone" required placeholder=" " pattern="\d{10}" maxlength="10" inputmode="numeric" class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 transition">
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

      <!-- Description -->
      <div class="md:col-span-2">
        <div class="relative">
          <textarea name="gym_description" required placeholder=" " rows="4" 
            class="peer w-full px-4 pt-6 pb-3 bg-gray-800/60 border border-gray-600 rounded-xl 
            text-white placeholder-transparent focus:outline-none focus:border-orange-500 
            focus:ring-2 focus:ring-orange-500/30 transition"></textarea>
          <label class="absolute left-4 top-3 text-gray-400 text-sm pointer-events-none 
            transition-all peer-placeholder-shown:top-5 peer-placeholder-shown:text-base 
            peer-focus:top-3 peer-focus:text-xs peer-focus:text-orange-400">
            Gym Description
          </label>
        </div>
      </div>

      <!-- Submit -->
      <div class="md:col-span-2 flex justify-center">
        <button type="submit"
                class="w-full md:w-auto px-12 py-4 bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 rounded-xl font-bold text-white shadow-lg hover:shadow-orange-600/40 transition-all duration-300 flex items-center justify-center gap-2 text-lg">
          <i class="fas fa-paper-plane"></i> Submit for Approval
        </button>
      </div>
    </form>
  </div>

  <!-- Back Link -->
  <a href="owner_dashboard.php" class="mt-12 text-orange-400 hover:text-orange-300 font-medium flex items-center gap-2 transition">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
  </a>

  <!-- Styles -->
  <style>
    .success-msg {
      @apply bg-gradient-to-r from-green-900/30 to-green-800/20 border border-green-600/40 text-green-300 py-3 px-6 rounded-xl text-center font-semibold animate-pulse;
    }
    .error-msg {
      @apply bg-gradient-to-r from-red-900/30 to-red-800/20 border border-red-600/40 text-red-300 py-3 px-6 rounded-xl text-center font-semibold animate-pulse mb-2;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:none; } }
    .animate-fadeIn { animation: fadeIn .5s ease-out; }
  </style>

  <script>
    const citiesData = {
      "Kerala": [
        "Thiruvananthapuram", "Kochi", "Kozhikode", "Thrissur", "Kollam", "Alappuzha", "Palakkad", "Kannur", "Kottayam", "Pathanamthitta",
        "Idukki", "Malappuram", "Kasaragod", "Wayanad", "Ernakulam", "Thalassery", "Kanhangad", "Payyanur", "Manjeri", "Ponnani",
        "Parappanangadi", "Tirur", "Malappuram", "Kondotty", "Manjeri", "Perinthalmanna", "Nilambur", "Tanur", "Ponnani", "Kottakkal",
        "Valanchery", "Kuttippuram", "Edappal", "Chelari", "Vengara", "Areacode", "Edavanna", "Wandoor", "Pandikkad", "Melattur",
        "Perinthalmanna", "Mankada", "Angadipuram", "Cherpulassery", "Shornur", "Ottapalam", "Pattambi", "Guruvayur", "Chavakkad",
        "Kunnamkulam", "Wadakkanchery", "Chelakkara", "Alathur", "Vadakkencherry", "Chittur", "Palakkad Town", "Mannarkkad", "Kozhinjampara"
      ],
      "Maharashtra": ["Mumbai", "Pune", "Nagpur", "Nashik", "Aurangabad", "Thane", "Solapur", "Kolhapur"],
      "Delhi": ["New Delhi", "Dwarka", "Rohini", "South Delhi", "East Delhi"],
      "Karnataka": ["Bengaluru", "Mysuru", "Hubli", "Mangaluru", "Belagavi"],
      "Tamil Nadu": ["Chennai", "Coimbatore", "Madurai", "Tiruchirappalli", "Salem"],
      "Gujarat": ["Ahmedabad", "Surat", "Vadodara", "Rajkot", "Gandhinagar"],
      "Uttar Pradesh": ["Lucknow", "Kanpur", "Agra", "Varanasi", "Meerut", "Noida"],
      "West Bengal": ["Kolkata", "Howrah", "Durgapur", "Siliguri"],
      "Rajasthan": ["Jaipur", "Jodhpur", "Udaipur", "Kota", "Ajmer"],
      "Punjab": ["Ludhiana", "Amritsar", "Jalandhar", "Patiala", "Chandigarh"],
      "Telangana": ["Hyderabad", "Warangal", "Nizamabad"],
      "Andhra Pradesh": ["Visakhapatnam", "Vijayawada", "Guntur", "Nellore"],
      "Madhya Pradesh": ["Bhopal", "Indore", "Jabalpur", "Gwalior"],
      "Bihar": ["Patna", "Gaya", "Bhagalpur", "Muzaffarpur"],
      "Odisha": ["Bhubaneswar", "Cuttack", "Rourkela", "Berhampur"],
      "Haryana": ["Gurugram", "Faridabad", "Panipat", "Ambala"],
      "Jharkhand": ["Ranchi", "Jamshedpur", "Dhanbad"],
      "Chhattisgarh": ["Raipur", "Bhilai", "Bilaspur"],
      "Goa": ["Panaji", "Margao", "Vasco da Gama"],
      "Assam": ["Guwahati", "Dibrugarh", "Silchar"],
      "Uttarakhand": ["Dehradun", "Haridwar", "Roorkee"],
      "Himachal Pradesh": ["Shimla", "Manali", "Dharamshala"],
      "Jammu and Kashmir": ["Srinagar", "Jammu", "Anantnag"],
      "Puducherry": ["Puducherry", "Karaikal"],
      "Ladakh": ["Leh", "Kargil"],
      "Sikkim": ["Gangtok"],
      "Tripura": ["Agartala"],
      "Manipur": ["Imphal"],
      "Meghalaya": ["Shillong"],
      "Mizoram": ["Aizawl"],
      "Nagaland": ["Kohima", "Dimapur"],
      "Arunachal Pradesh": ["Itanagar"]
    };

    function updateCities() {
      const state = document.getElementById('gym_state').value;
      const citySelect = document.getElementById('city');
      citySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';

      setTimeout(() => {
        citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
        if (citiesData[state]) {
          citiesData[state].forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
          });
        }
      }, 100);
    }

    function previewImages(input) {
      const preview = document.getElementById('preview');
      preview.innerHTML = '';
      Array.from(input.files).forEach(file => {
        if (file.type.match("image.*")) {
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