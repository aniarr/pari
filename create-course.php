<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainer_login.php");
    exit();
}

$host = 'localhost';
$dbname = 'rawfit';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch trainer details
$trainer_id = $_SESSION['trainer_id'];
$stmt = $pdo->prepare("SELECT name, trainer_image FROM trainer_details WHERE id = ?");
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);

$trainer_name = 'Trainer';
$trainer_image = 'default-avatar.png';

if ($trainer) {
    $trainer_name = $trainer['name'] ?? 'Trainer';
    $trainer_image = !empty($trainer['trainer_image']) ? $trainer['trainer_image'] : 'default-avatar.png';
}

// Handle form submission
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $status = 'pending'; // FORCE PENDING - admin must approve
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $image = $_FILES['image'] ?? null;
    $document = $_FILES['course_document'] ?? null;

    $errors = [];

    // Validation
    if (empty($title)) $errors[] = "Course title is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if (empty($duration) || !is_numeric($duration) || $duration < 1 || $duration > 52) {
        $errors[] = "Duration must be between 1 and 52 weeks.";
    }
    if (empty($start_date)) $errors[] = "Start date is required.";
    if (empty($end_date)) $errors[] = "End date is required.";
    if (strtotime($end_date) <= strtotime($start_date)) {
        $errors[] = "End date must be after start date.";
    }

    // Image validation
    if (!$image || $image['error'] == UPLOAD_ERR_NO_FILE) {
        $errors[] = "Course image is required.";
    } elseif ($image['error'] == UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($image['type'], $allowed)) {
            $errors[] = "Only JPEG, PNG, and GIF images are allowed.";
        }
        if ($image['size'] > 5 * 1024 * 1024) {
            $errors[] = "Image must be less than 5MB.";
        }
    }

    // Document (optional)
    $doc_name = null;
    if ($document && $document['error'] == UPLOAD_ERR_OK) {
        $allowed_doc = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        if (!in_array($document['type'], $allowed_doc)) {
            $errors[] = "Invalid document type. Use PDF, DOC, DOCX, TXT, PPT, PPTX.";
        }
        if ($document['size'] > 5 * 1024 * 1024) {
            $errors[] = "Document must be less than 5MB.";
        }
    }

    // Process upload if no errors
    if (empty($errors)) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Upload image
        $image_ext = pathinfo($image['name'], PATHINFO_EXTENSION);
        $image_name = uniqid('img_') . '.' . $image_ext;
        $image_path = $upload_dir . $image_name;

        // Upload document if provided
        if ($document && $document['error'] == UPLOAD_ERR_OK) {
            $doc_ext = pathinfo($document['name'], PATHINFO_EXTENSION);
            $doc_name = uniqid('doc_') . '.' . $doc_ext;
            $doc_path = $upload_dir . $doc_name;
            if (!move_uploaded_file($document['tmp_name'], $doc_path)) {
                $errors[] = "Failed to upload document.";
            }
        }

        if (move_uploaded_file($image['tmp_name'], $image_path)) {
            try {
                $created_at = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("
                    INSERT INTO trainer_courses 
                    (trainer_id, title, description, category, duration, status, start_date, end_date, image_path, doc_path, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $trainer_id, $title, $description, $category, $duration,
                    $status, $start_date, $end_date, $image_name, $doc_name, $created_at
                ]);

                $message = "Course '<strong>$title</strong>' added successfully! It is now <strong>pending admin approval</strong>.";
                if ($doc_name) $message .= " Document: <strong>$doc_name</strong>.";
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        } else {
            $errors[] = "Failed to upload image.";
        }
    }

    if (!empty($errors)) {
        $message = "<ul class='list-disc list-inside text-red-300'>" . implode('', array_map(fn($e) => "<li>$e</li>", $errors)) . "</ul>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Course - Rawfit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'inter': ['Inter', 'sans-serif'] },
                    colors: { primary: '#F97316', secondary: '#EF4444' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 font-inter text-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-lg flex items-center justify-center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <span class="text-white font-bold text-xl">Rawfit</span>
                </div>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="trainerman.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                        <span>Home</span>
                    </a>
                    <a href="manage-courses.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                        <span>My Courses</span>
                    </a>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profile-menu-button" class="flex items-center space-x-3 text-sm rounded-lg px-3 py-2 text-gray-300 hover:text-white hover:bg-gray-800 transition">
                        <img class="h-8 w-8 rounded-full object-cover border border-gray-600"
                             src="uploads/<?php echo htmlspecialchars($trainer_image); ?>"
                             alt="<?php echo htmlspecialchars($trainer_name); ?>">
                        <span class="hidden md:block font-medium"><?php echo htmlspecialchars($trainer_name); ?></span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-gray-800/95 backdrop-blur-sm rounded-xl border border-gray-700 shadow-lg py-1 z-50 transition-all duration-200 opacity-0 scale-95">
                        <a href="trainer_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">
                            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            View Profile
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-red-600/20 hover:text-red-400 transition">
                            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-8">
        <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6">
            <h1 class="text-2xl font-bold text-white mb-6">Create New Course</h1>
               <p class="text-xs text-gray-500 mt-8 text-left">“While entering the code, make sure you have submitted your Trainer Certificate. Otherwise, your request will be rejected by the admin.”</p>
       
            <br><br>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg 
                    <?php echo str_contains($message, 'pending') ? 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/50' : 
                          (str_contains($message, 'successfully') ? 'bg-green-500/20 text-green-300 border border-green-500/50' : 
                          'bg-red-500/20 text-red-300 border border-red-500/50'); ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-300">Course Title</label>
                    <input type="text" name="title" id="title" required
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                           class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:outline-none focus:ring-2 focus:ring-primary transition">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-300">Description</label>
                    <textarea name="description" id="description" rows="4" required
                              class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:outline-none focus:ring-2 focus:ring-primary transition"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-300">Category</label>
                        <select name="category" id="category" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                            <option value="strength" <?php echo ($_POST['category'] ?? '') === 'strength' ? 'selected' : ''; ?>>Strength Training</option>
                            <option value="cardio" <?php echo ($_POST['category'] ?? '') === 'cardio' ? 'selected' : ''; ?>>Cardio & HIIT</option>
                            <option value="yoga" <?php echo ($_POST['category'] ?? '') === 'yoga' ? 'selected' : ''; ?>>Yoga & Flexibility</option>
                            <option value="boxing" <?php echo ($_POST['category'] ?? '') === 'boxing' ? 'selected' : ''; ?>>Boxing</option>
                            <option value="crossfit" <?php echo ($_POST['category'] ?? '') === 'crossfit' ? 'selected' : ''; ?>>CrossFit</option>
                            <option value="nutrition" <?php echo ($_POST['category'] ?? '') === 'nutrition' ? 'selected' : ''; ?>>Nutrition</option>
                        </select>
                    </div>

                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-300">Duration (weeks)</label>
                        <input type="number" name="duration" id="duration" min="1" max="52" required
                               value="<?php echo isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''; ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-300">Start Date</label>
                        <input type="date" name="start_date" id="start_date" required
                               value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-300">End Date</label>
                        <input type="date" name="end_date" id="end_date" required
                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div>
                    <label for="image" class="block text-sm font-medium text-gray-300 mb-2">Course Image (Required)</label>
                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif" required
                           class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-orange-600 cursor-pointer">
                </div>

                <div>
                    <label for="course_document" class="block text-sm font-medium text-gray-300 mb-2">Attach Document (Optional)</label>
                    <input type="file" id="course_document" name="course_document"
                           accept=".pdf,.doc,.docx,.txt,.ppt,.pptx"
                           class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-600 file:text-white hover:file:bg-gray-700 cursor-pointer">
                    <p class="text-xs text-gray-500 mt-1">Max 5MB: PDF, DOCX, TXT, PPT, PPTX</p>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary transition transform hover:scale-[1.01]">
                    Submit Course
                </button>
            </form>

            <p class="text-xs text-gray-500 mt-8 text-center">
                Page generated at: <?php echo date('h:i A T, F d, Y'); ?> (India Time)
            </p>
        </div>
    </div>

    <!-- Dropdown Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const btn = document.getElementById("profile-menu-button");
            const dropdown = document.getElementById("profile-dropdown");

            btn.addEventListener("click", (e) => {
                e.stopPropagation();
                dropdown.classList.toggle("hidden");
                dropdown.classList.toggle("opacity-0");
                dropdown.classList.toggle("scale-95");
                dropdown.classList.toggle("opacity-100");
                dropdown.classList.toggle("scale-100");
            });

            document.addEventListener("click", (e) => {
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add("hidden", "opacity-0", "scale-95");
                    dropdown.classList.remove("opacity-100", "scale-100");
                }
            });
        });
    </script>
</body>
</html>