<?php
session_start();

// Ensure owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header('Location: login_owner.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$owner_id = $_SESSION['owner_id'];

// Fetch all gyms belonging to this owner
$stmt = $conn->prepare("
    SELECT 
        g.gym_id,
        g.owner_id,
        g.gym_name,
        g.location,
        g.phone,
        g.timings,
        g.facilities,
        g.gym_image,
        g.gym_address,
        g.gym_city,
        g.gym_state,
        g.gym_zip,
        g.gym_phone,
        g.gym_email,
        g.gym_description,
        g.created_at,
        g.year_started,
        g.experience_years,
        g.num_trainers,
        g.capacity,
        g.registrations,
        COUNT(DISTINCT gi.id) as image_count,
        COUNT(DISTINCT gr.id) as review_count,
        AVG(gr.rating) as avg_rating
    FROM gyms g
    LEFT JOIN gym_images gi ON g.gym_id = gi.gym_id
    LEFT JOIN gym_reviews gr ON g.gym_id = gr.gym_id
    WHERE g.owner_id = ?
    GROUP BY g.gym_id
    ORDER BY g.created_at DESC
");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$result = $stmt->get_result();

// Set owner ID from session
$owner_id = $_SESSION['owner_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit - Gym Management</title>
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
<body class="bg-gray-900 font-inter">
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
                    <span class="text-xl font-bold text-white">RawFit</span>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-6">
                    <a href="owner_dashboard.php" class="text-gray-300 hover:text-orange-500 transition">Home</a>
                    <a href="Manage_Gyms.php" class="text-gray-300 hover:text-orange-500 transition">Add Gym</a>
                </div>

                <!-- Profile -->
                <div class="flex items-center space-x-4">
                    <a href="logout.php" class="text-gray-300 hover:text-red-500 transition">Logout</a>
                    
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-24 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">My Gyms</h1>
            <p class="mt-2 text-gray-400">Manage and monitor all your fitness centers</p>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                <h3 class="text-gray-400 text-sm font-medium">Total Gyms</h3>
                <p class="text-2xl font-bold text-white mt-2"><?php echo $result->num_rows; ?></p>
            </div>
            <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                <h3 class="text-gray-400 text-sm font-medium">Total Reviews</h3>
                <p class="text-2xl font-bold text-white mt-2">
                    <?php 
                        $total_reviews = 0;
                        $result->data_seek(0);
                        while($row = $result->fetch_assoc()) {
                            $total_reviews += $row['review_count'];
                        }
                        echo $total_reviews;
                    ?>
                </p>
            </div>
            <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                <h3 class="text-gray-400 text-sm font-medium">Average Rating</h3>
                <p class="text-2xl font-bold text-white mt-2">
                    <?php 
                        $total_rating = 0;
                        $rated_gyms = 0;
                        $result->data_seek(0);
                        while($row = $result->fetch_assoc()) {
                            if($row['avg_rating']) {
                                $total_rating += $row['avg_rating'];
                                $rated_gyms++;
                            }
                        }
                        echo $rated_gyms > 0 ? number_format($total_rating / $rated_gyms, 1) : 'N/A';
                    ?>
                    <span class="text-yellow-400">★</span>
                </p>
            </div>
        </div>

        <!-- Gyms Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php 
            $result->data_seek(0);
            while($gym = $result->fetch_assoc()): 
                // Fetch primary image for this gym
                $img_stmt = $conn->prepare("SELECT filename FROM gym_images WHERE gym_id = ? LIMIT 1");
                $img_stmt->bind_param('i', $gym['gym_id']);
                $img_stmt->execute();
                $img_result = $img_stmt->get_result();
                $img_path = $img_result->num_rows > 0 
                    ? 'uploads/gyms/' . $img_result->fetch_assoc()['filename']
                    : 'https://via.placeholder.com/400x300?text=No+Image';
            ?>
            <div class="bg-gray-800 rounded-xl overflow-hidden hover:ring-2 hover:ring-orange-500/50 transition duration-300">
                <div class="aspect-w-16 aspect-h-9 bg-gray-900">
                    <img src="<?php echo htmlspecialchars($img_path); ?>" 
                         alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                         class="w-full h-48 object-cover">
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-white mb-2">
                        <?php echo htmlspecialchars($gym['gym_name']); ?>
                    </h3>
                    
                    <!-- Location & Contact -->
                    <div class="mb-4 space-y-1">
                        <p class="text-gray-400 text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <?php echo htmlspecialchars($gym['location']); ?>
                        </p>
                        <p class="text-gray-400 text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <?php echo htmlspecialchars($gym['gym_phone']); ?>
                        </p>
                        <p class="text-gray-400 text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <?php echo htmlspecialchars($gym['timings']); ?>
                        </p>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-700/50 rounded-lg p-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-400 text-sm">Capacity</span>
                                <span class="text-orange-400 font-bold"><?php echo $gym['capacity']; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-sm">Current</span>
                                <span class="text-green-400 font-bold"><?php echo $gym['registrations']; ?></span>
                            </div>
                        </div>
                        <div class="bg-gray-700/50 rounded-lg p-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-400 text-sm">Trainers</span>
                                <span class="text-blue-400 font-bold"><?php echo $gym['num_trainers']; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-sm">Experience</span>
                                <span class="text-purple-400 font-bold"><?php echo $gym['experience_years']; ?>y</span>
                            </div>
                        </div>
                    </div>

                    <!-- Review Stats -->
                    <div class="grid grid-cols-3 gap-4 mb-4 bg-gray-700/30 rounded-lg p-3">
                        <div class="text-center">
                            <p class="text-orange-400 font-bold"><?php echo $gym['image_count']; ?></p>
                            <p class="text-gray-500 text-xs">Images</p>
                        </div>
                        <div class="text-center border-x border-gray-600">
                            <p class="text-green-400 font-bold"><?php echo $gym['review_count']; ?></p>
                            <p class="text-gray-500 text-xs">Reviews</p>
                        </div>
                        <div class="text-center">
                            <p class="text-yellow-400 font-bold flex items-center justify-center gap-1">
                                <?php echo $gym['avg_rating'] ? number_format($gym['avg_rating'], 1) : 'N/A'; ?>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </p>
                            <p class="text-gray-500 text-xs">Rating</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <a href="edit_gym.php?id=<?php echo $gym['gym_id']; ?>" 
                           class="flex-1 bg-gray-700 text-white text-center py-2 rounded-lg hover:bg-gray-600 transition">
                            Edit
                        </a>
                        <a href="delete_gym.php?id=<?php echo $gym['gym_id']; ?>" 
                        onclick="return confirm('Are you sure you want to delete this gym? This action cannot be undone.');"
                        class="flex-1 bg-red-600 text-white text-center py-2 rounded-lg hover:bg-red-700 transition">
                        Delete
                        </a>

                    </div>
                </div>
            </div>
            <?php endwhile; ?>

            <!-- Add New Gym Card -->
            <a href="Manage_Gyms.php" class="group bg-gray-800/50 rounded-xl border-2 border-dashed border-gray-700 hover:border-orange-500/50 p-6 flex flex-col items-center justify-center text-center transition duration-300">
                <div class="w-16 h-16 rounded-full bg-gray-700 flex items-center justify-center mb-4 group-hover:bg-orange-500/20 transition">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400 group-hover:text-orange-400">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Add New Gym</h3>
                <p class="text-gray-400 text-sm">Click to add a new fitness center to your portfolio</p>
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="mt-16 border-t border-gray-800 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center sm:px-6 lg:px-8">
        </div>
    </footer>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
