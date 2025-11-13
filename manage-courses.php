<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainer_login.php");
    exit();
}

// Database connection (PDO)
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

$trainer_id = $_SESSION['trainer_id'];

// Fetch trainer details
$stmt = $pdo->prepare("SELECT name, trainer_image FROM trainer_details WHERE id = ?");
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);
$trainer_name = $trainer['name'] ?? 'Trainer';
$trainer_image = !empty($trainer['trainer_image']) ? $trainer['trainer_image'] : 'default-avatar.png';

// Handle Edit Submission
$message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_course'])) {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $duration = (int)$_POST['duration'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?: null;
    $image = $_FILES['image'] ?? null;

    // Validation
    $errors = [];
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if (!in_array($category, ['strength','cardio','yoga','boxing','crossfit','nutrition'])) $errors[] = "Invalid category.";
    if ($duration < 1 || $duration > 52) $errors[] = "Duration must be 1–52 weeks.";
    if (empty($start_date)) $errors[] = "Start date is required.";

    $image_path = null;
    if ($image && $image['error'] == UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif'];
        if (!in_array($image['type'], $allowed)) {
            $errors[] = "Only JPG, PNG, GIF allowed.";
        } elseif ($image['size'] > 5*1024*1024) {
            $errors[] = "Image too large (max 5MB).";
        } else {
            $upload_dir = "uploads/";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $image_path = uniqid('img_') . '.' . pathinfo($image['name'], PATHINFO_EXTENSION);
            if (!move_uploaded_file($image['tmp_name'], $upload_dir . $image_path)) {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        // Fetch current image to preserve if no new upload
        $stmt = $pdo->prepare("SELECT image_path FROM trainer_courses WHERE id = ? AND trainer_id = ?");
        $stmt->execute([$id, $trainer_id]);
        $current = $stmt->fetchColumn();
        $final_image = $image_path ?: $current;

        $stmt = $pdo->prepare("UPDATE trainer_courses SET title=?, description=?, category=?, duration=?, start_date=?, end_date=?, image_path=? WHERE id=? AND trainer_id=?");
        $stmt->execute([$title, $description, $category, $duration, $start_date, $end_date, $final_image, $id, $trainer_id]);
        $message = "Course updated successfully!";
        header("Location: all_courses.php?success=1");
        exit();
    } else {
        $message = "Errors: " . implode(" ", $errors);
    }
}

// Handle Delete
if (isset($_GET['delete'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM trainer_courses WHERE id = ? AND trainer_id = ?");
    $stmt->execute([$id, $trainer_id]);
    header("Location: all_courses.php?deleted=1");
    exit();
}

// Fetch all courses
$stmt = $pdo->prepare("SELECT * FROM trainer_courses WHERE trainer_id = ? ORDER BY created_at DESC");
$stmt->execute([$trainer_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit mode
$edit_course = null;
if (isset($_GET['edit'], $_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    foreach ($courses as $c) {
        if ($c['id'] == $edit_id) {
            $edit_course = $c;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit - All Courses</title>
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
                    <a href="trainerman.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9,22 9,12 15,12 15,22"/>
                        </svg>
                        <span>Home</span>
                    </a>
                    <a href="manage-courses.php" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Courses</span>
                    </a>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profile-menu-button" class="flex items-center space-x-3 text-sm rounded-lg px-3 py-2 text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <img class="h-8 w-8 rounded-full object-cover"
                             src="uploads/<?php echo htmlspecialchars($trainer_image); ?>"
                             alt="<?php echo htmlspecialchars($trainer_name); ?>">
                        <span class="hidden md:block font-medium"><?php echo htmlspecialchars($trainer_name); ?></span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-sm rounded-xl border border-gray-700 py-1 z-50 shadow-xl">
                        <a href="trainer_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            View Profile
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-red-600/20 hover:text-red-400 transition-colors">
                            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
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
            <h1 class="text-2xl font-bold text-white mb-6">All Courses</h1>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-4 p-3 bg-green-500/20 text-green-300 rounded-lg">Course updated successfully!</div>
            <?php elseif (isset($_GET['deleted'])): ?>
                <div class="mb-4 p-3 bg-red-500/20 text-red-300 rounded-lg">Course deleted.</div>
            <?php elseif ($message): ?>
                <div class="mb-4 p-3 bg-red-500/20 text-red-300 rounded-lg"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($edit_course): ?>
                <!-- Edit Form -->
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="id" value="<?php echo $edit_course['id']; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Title</label>
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($edit_course['title']); ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Description</label>
                        <textarea name="description" rows="3" required
                                  class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($edit_course['description']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Category</label>
                        <select name="category" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                            <?php
                            $cats = ['strength'=>'Strength Training','cardio'=>'Cardio & HIIT','yoga'=>'Yoga & Flexibility','boxing'=>'Boxing','crossfit'=>'CrossFit','nutrition'=>'Nutrition'];
                            foreach ($cats as $val => $label):
                            ?>
                                <option value="<?php echo $val; ?>" <?php echo $edit_course['category'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Duration (weeks)</label>
                        <input type="number" name="duration" min="1" max="52" required value="<?php echo $edit_course['duration']; ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Start Date</label>
                        <input type="date" name="start_date" required value="<?php echo $edit_course['start_date']; ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">End Date (optional)</label>
                        <input type="date" name="end_date" value="<?php echo $edit_course['end_date']; ?>"
                               class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Image (optional)</label>
                        <?php if ($edit_course['image_path']): ?>
                            <img src="uploads/<?php echo htmlspecialchars($edit_course['image_path']); ?>" alt="Current" class="h-32 mt-2 rounded">
                        <?php endif; ?>
                        <input type="file" name="image" accept="image/*" class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white">
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" name="edit_course" class="flex-1 bg-primary text-white py-2 rounded-lg hover:bg-orange-600">
                            Save Changes
                        </button>
                        <a href="all_courses.php" class="flex-1 bg-gray-600 text-white py-2 rounded-lg text-center hover:bg-gray-700">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <!-- Course Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="bg-gray-700/50 rounded-lg overflow-hidden border border-gray-600">
                            <img src="<?php echo $course['image_path'] ? 'uploads/' . htmlspecialchars($course['image_path']) : 'https://via.placeholder.com/300x200/333/fff?text=No+Image'; ?>"
                                 alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-48 object-cover">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="text-sm text-gray-400">Category: <?php echo ucfirst(htmlspecialchars($course['category'])); ?></p>
                                <p class="text-sm text-gray-400">Duration: <?php echo $course['duration']; ?> weeks</p>
                                <p class="text-sm text-gray-400">Start: <?php echo date('M j, Y', strtotime($course['start_date'])); ?></p>
                                <?php if ($course['end_date']): ?>
                                    <p class="text-sm text-gray-400">End: <?php echo date('M j, Y', strtotime($course['end_date'])); ?></p>
                                <?php endif; ?>
                                <div class="mt-4 flex space-x-2">
                                    <a href="all_courses.php?edit=1&id=<?php echo $course['id']; ?>"
                                       class="flex-1 bg-primary text-white text-center py-2 rounded hover:bg-orange-600 text-sm">
                                        Edit
                                    </a>
                                    <a href="all_courses.php?delete=1&id=<?php echo $course['id']; ?>"
                                       class="flex-1 bg-secondary text-white text-center py-2 rounded hover:bg-red-600 text-sm"
                                       onclick="return confirm('Delete this course?');">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($courses)): ?>
                    <p class="text-center text-gray-400 py-10">No courses yet. <a href="edit_course.php" class="text-primary underline">Add one</a>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dropdown Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const button = document.getElementById('profile-menu-button');
            const dropdown = document.getElementById('profile-dropdown');

            button.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>