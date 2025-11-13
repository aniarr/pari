<?php
session_start();

// Check if trainer is logged in
if (!isset($_SESSION['trainer_id'])) {
    header("Location: login.php");
    exit();
}

// Database configuration
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

// Initialize variables
$course = null;
$is_edit = false;
$errors = [];
$message = '';

// Default form values (will be filled from DB or POST)
$form = [
    'title' => '',
    'description' => '',
    'category' => '',
    'duration' => '',
    'status' => 'active',
    'start_date' => '',
    'end_date' => '',
    'image_path' => '',
    'doc_path' => ''
];

// Check if editing an existing course
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit = true;
    $course_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM trainer_courses WHERE id = ? AND trainer_id = ?");
    $stmt->execute([$course_id, $_SESSION['trainer_id']]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        $message = "Course not found or you don't have permission to edit it.";
        $is_edit = false;
    } else {
        // Populate form with existing data
        foreach ($form as $key => $value) {
            $form[$key] = $course[$key] ?? $form[$key];
        }
    }
}

// Handle delete request
if (isset($_GET['delete']) && $_GET['delete'] === 'true' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $course_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM trainer_courses WHERE id = ? AND trainer_id = ?");
    if ($stmt->execute([$course_id, $_SESSION['trainer_id']])) {
        $message = "Course deleted successfully!";
        header("Location: manage-courses.php");
        exit();
    } else {
        $message = "Failed to delete course.";
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Capture and sanitize inputs
    $form['title']       = trim($_POST['title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['category']    = $_POST['category'] ?? '';
    $form['duration']    = trim($_POST['duration'] ?? '');
    $form['status']      = $_POST['status'] ?? 'active';
    $form['start_date']  = $_POST['start_date'] ?? '';
    $form['end_date']    = $_POST['end_date'] ?? '';

    $image   = $_FILES['image'] ?? null;
    $document = $_FILES['document'] ?? null;

    // === VALIDATION ===
    if (empty($form['title'])) {
        $errors['title'] = "Course title is required.";
    } elseif (strlen($form['title']) > 255) {
        $errors['title'] = "Title must be less than 255 characters.";
    }

    if (empty($form['description'])) {
        $errors['description'] = "Description is required.";
    } elseif (strlen($form['description']) > 2000) {
        $errors['description'] = "Description must be less than 2000 characters.";
    }

    if (empty($form['category'])) {
        $errors['category'] = "Please select a category.";
    } elseif (!in_array($form['category'], ['strength', 'cardio', 'yoga', 'boxing', 'crossfit', 'nutrition'])) {
        $errors['category'] = "Invalid category selected.";
    }

    if (empty($form['duration'])) {
        $errors['duration'] = "Duration is required.";
    } elseif (!is_numeric($form['duration']) || $form['duration'] < 1 || $form['duration'] > 52) {
        $errors['duration'] = "Duration must be between 1 and 52 weeks.";
    } else {
        $form['duration'] = (int)$form['duration'];
    }

    if (empty($form['start_date'])) {
        $errors['start_date'] = "Start date is required.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $form['start_date'])) {
        $errors['start_date'] = "Invalid start date format.";
    }

    if (!empty($form['end_date']) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $form['end_date'])) {
        $errors['end_date'] = "Invalid end date format.";
    }

    // Validate end_date >= start_date
    if (empty($errors['start_date']) && empty($errors['end_date']) && !empty($form['end_date'])) {
        if (strtotime($form['end_date']) < strtotime($form['start_date'])) {
            $errors['end_date'] = "End date cannot be before start date.";
        }
    }

    // === IMAGE VALIDATION ===
    $image_name = $is_edit ? $course['image_path'] : null;

    if (!$is_edit && (!isset($image) || $image['error'] == UPLOAD_ERR_NO_FILE)) {
        $errors['image'] = "Course image is required for new courses.";
    } elseif (isset($image) && $image['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($image['type'], $allowed_types)) {
            $errors['image'] = "Only JPEG, PNG, and GIF images are allowed.";
        } elseif ($image['size'] > $max_size) {
            $errors['image'] = "Image size must be less than 5MB.";
        } else {
            // Generate unique name
            $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
            $image_name = uniqid('img_') . '.' . strtolower($ext);
            $target_file = "uploads/" . $image_name;

            if (!move_uploaded_file($image['tmp_name'], $target_file)) {
                $errors['image'] = "Failed to upload image.";
            }
        }
    } elseif (isset($image) && $image['error'] !== UPLOAD_ERR_NO_FILE && $image['error'] !== UPLOAD_ERR_OK) {
        $errors['image'] = "Image upload error.";
    }

    // === DOCUMENT VALIDATION ===
    $doc_name = $is_edit ? $course['doc_path'] : null;

    if (isset($document) && $document['error'] == UPLOAD_ERR_OK) {
        $allowed_doc_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        $max_size = 5 * 1024 * 1024;

        if (!in_array($document['type'], $allowed_doc_types)) {
            $errors['document'] = "Only PDF, DOC, DOCX, TXT, PPT, PPTX are allowed.";
        } elseif ($document['size'] > $max_size) {
            $errors['document'] = "Document size must be less than 5MB.";
        } else {
            $ext = pathinfo($document['name'], PATHINFO_EXTENSION);
            $doc_name = uniqid('doc_') . '.' . strtolower($ext);
            $doc_target = "uploads/" . $doc_name;

            if (!move_uploaded_file($document['tmp_name'], $doc_target)) {
                $errors['document'] = "Failed to upload document.";
            }
        }
    } elseif (isset($document) && $document['error'] !== UPLOAD_ERR_NO_FILE && $document['error'] !== UPLOAD_ERR_OK) {
        $errors['document'] = "Document upload error.";
    }

    // === FINAL SAVE IF NO ERRORS ===
    if (empty($errors)) {
        $trainer_id = $_SESSION['trainer_id'];
        $created_at = date('Y-m-d H:i:s');

        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("UPDATE trainer_courses SET 
                    title = ?, description = ?, category = ?, duration = ?, status = ?, 
                    start_date = ?, end_date = ?, image_path = ?, doc_path = ? 
                    WHERE id = ? AND trainer_id = ?");

                $stmt->execute([
                    $form['title'], $form['description'], $form['category'], $form['duration'],
                    $form['status'], $form['start_date'], $form['end_date'] ?: null,
                    $image_name, $doc_name, $course_id, $trainer_id
                ]);
                $message = "Course updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO trainer_courses 
                    (trainer_id, title, description, category, duration, status, start_date, end_date, image_path, doc_path, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $trainer_id, $form['title'], $form['description'], $form['category'],
                    $form['duration'], $form['status'], $form['start_date'],
                    $form['end_date'] ?: null, $image_name, $doc_name, $created_at
                ]);
                $message = "Course added successfully!";
            }

            // Optional: redirect to avoid re-post
            // header("Location: manage-courses.php?success=1");
            // exit();
        } catch (Exception $e) {
            $errors['general'] = "Database error: " . $e->getMessage();
        }
    } else {
        $message = "Please fix the errors below.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Course' : 'Add New Course'; ?> - Rawfit</title>
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
                    <a href="trainerman.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                        <span>Home</span>
                    </a>
                    <a href="manage-courses.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                        <span>Courses</span>
                    </a>
                </div>
                <a href="logout.php" class="text-sm text-gray-300 hover:text-white hover:bg-gray-800 px-3 py-2 rounded-lg transition-colors">Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-8">
        <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6">
            <h1 class="text-2xl font-bold text-white mb-6"><?php echo $is_edit ? 'Edit Course' : 'Add New Course'; ?></h1>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg <?php echo !empty($errors) ? 'bg-red-500/20 text-red-300' : 'bg-green-500/20 text-green-300'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-300">Course Title</label>
                    <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($form['title']); ?>"
                           class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['title']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    <?php if (isset($errors['title'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['title']; ?></p><?php endif; ?>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-300">Description</label>
                    <textarea name="description" id="description" rows="3"
                              class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['description']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white focus:outline-none focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($form['description']); ?></textarea>
                    <?php if (isset($errors['description'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['description']; ?></p><?php endif; ?>
                </div>

                <!-- Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-300">Category</label>
                    <select name="category" id="category" class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['category']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-gray-300 focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">Select Category</option>
                        <option value="strength" <?php echo $form['category'] === 'strength' ? 'selected' : ''; ?>>Strength Training</option>
                        <option value="cardio" <?php echo $form['category'] === 'cardio' ? 'selected' : ''; ?>>Cardio & HIIT</option>
                        <option value="yoga" <?php echo $form['category'] === 'yoga' ? 'selected' : ''; ?>>Yoga & Flexibility</option>
                        <option value="boxing" <?php echo $form['category'] === 'boxing' ? 'selected' : ''; ?>>Boxing</option>
                        <option value="crossfit" <?php echo $form['category'] === 'crossfit' ? 'selected' : ''; ?>>CrossFit</option>
                        <option value="nutrition" <?php echo $form['category'] === 'nutrition' ? 'selected' : ''; ?>>Nutrition</option>
                    </select>
                    <?php if (isset($errors['category'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['category']; ?></p><?php endif; ?>
                </div>

                <!-- Duration -->
                <div>
                    <label for="duration" class="block text-sm font-medium text-gray-300">Duration (weeks)</label>
                    <input type="number" name="duration" id="duration" min="1" max="52" value="<?php echo htmlspecialchars($form['duration']); ?>"
                           class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['duration']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    <?php if (isset($errors['duration'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['duration']; ?></p><?php endif; ?>
                </div>

                <!-- Start Date -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-300">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($form['start_date']); ?>"
                           class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['start_date']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    <?php if (isset($errors['start_date'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['start_date']; ?></p><?php endif; ?>
                </div>

                <!-- End Date -->
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-300">End Date (Optional)</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($form['end_date']); ?>"
                           class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['end_date']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    <?php if (isset($errors['end_date'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['end_date']; ?></p><?php endif; ?>
                </div>

                <!-- Image -->
                <div>
                    <label for="image" class="block text-sm font-medium text-gray-300">Course Image <?php echo !$is_edit ? '<span class="text-red-400">*</span>' : ''; ?></label>
                    <?php if ($is_edit && !empty($form['image_path'])): ?>
                        <p class="text-sm text-gray-400 mb-2">Current: 
                            <img src="uploads/<?php echo htmlspecialchars($form['image_path']); ?>" alt="Current" class="h-16 inline-block rounded">
                        </p>
                    <?php endif; ?>
                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif"
                           class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['image']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                    <?php if (isset($errors['image'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['image']; ?></p><?php endif; ?>
                </div>

                <!-- Document -->
                <div>
                    <label for="document" class="block text-sm font-medium text-gray-300">Document (PDF, DOCX, etc.)</label>
                    <?php if ($is_edit && !empty($form['doc_path'])): ?>
                        <p class="text-sm text-gray-400 mb-2">Current: 
                            <a href="uploads/<?php echo htmlspecialchars($form['doc_path']); ?>" target="_blank" class="text-primary underline">
                                <?php echo htmlspecialchars(basename($form['doc_path'])); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <input type="file" name="document" id="document"
                           class="mt-1 block w-full bg-gray-700 border <?php echo isset($errors['document']) ? 'border-red-500' : 'border-gray-600'; ?> rounded-lg p-2 text-white file:bg-orange-500 file:text-white file:rounded-lg file:px-4 file:py-2 file:border-0 focus:outline-none focus:ring-2 focus:ring-primary"
                           accept=".pdf,.doc,.docx,.txt,.ppt,.pptx">
                    <?php if (isset($errors['document'])): ?><p class="text-red-400 text-xs mt-1"><?php echo $errors['document']; ?></p><?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1">Upload to replace (max 5MB).</p>
                </div>

                <!-- Status (Hidden or Dropdown) -->
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($form['status']); ?>">

                <!-- Submit Buttons -->
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 bg-primary text-white py-2 px-4 rounded-lg hover:bg-orange-600 text-center">
                        <?php echo $is_edit ? 'Update Course' : 'Save Course'; ?>
                    </button>
                    <?php if ($is_edit): ?>
                        <a href="edit_course.php?delete=true&id=<?php echo $course['id']; ?>"
                           class="flex-1 bg-secondary text-white py-2 px-4 rounded-lg hover:bg-red-600 text-center"
                           onclick="return confirm('Are you sure you want to delete this course? This cannot be undone.');">
                            Delete Course
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <p class="text-xs text-gray-500 mt-6">Page generated at: <?php echo date('h:i A T, F j, Y'); ?></p>
        </div>
    </div>
</body>
</html>