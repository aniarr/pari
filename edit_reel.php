<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check user session
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php?error=login_required");
    exit;
}
$user_id = intval($_SESSION['user_id']);

// Debug: Log session and ID
$debug_msg = "Debug: User ID = $user_id, ";
$debug_msg .= "Reel ID from URL = " . (isset($_GET['id']) ? htmlspecialchars($_GET['id']) : 'not set');
echo "<p style='color: blue;'>$debug_msg</p>";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid reel ID. Please ensure the URL contains a valid numeric ID (e.g., edit_reel.php?id=1).");
}
$reel_id = intval($_GET['id']);

// Debug: Log prepared query
echo "<p style='color: blue;'>Debug: Querying reel with ID = $reel_id and User ID = $user_id</p>";

// Fetch reel details
$sql = "SELECT caption, supplement_tag, calories_info FROM reels WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ii", $reel_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query execution failed: " . $stmt->error);
}

if ($result->num_rows === 0) {
    die("Reel not found or you don’t have permission to edit it. Check if the reel ID ($reel_id) belongs to User ID ($user_id).");
}
$row = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caption = isset($_POST['caption']) ? trim($_POST['caption']) : '';
    $supplement_tag = isset($_POST['supplement_tag']) ? trim($_POST['supplement_tag']) : '';
    $calories_info = isset($_POST['calories_info']) ? trim($_POST['calories_info']) : '';

    $sql_update = "UPDATE reels SET caption = ?, supplement_tag = ?, calories_info = ? WHERE id = ? AND user_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) {
        die("Update prepare failed: " . $conn->error);
    }
    $stmt_update->bind_param("ssssi", $caption, $supplement_tag, $calories_info, $reel_id, $user_id);
    if ($stmt_update->execute()) {
        header("Location: upload_reel.php?edit=success");
        exit;
    } else {
        echo "<p style='color: red;'>Update failed: " . $stmt_update->error . "</p>";
    }
    $stmt_update->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reel - RawFit</title>
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
<body class="bg-gray-900 font-inter min-h-screen">
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
                <div class="flex space-x-4">
                    <a href="home.php" class="text-white hover:text-orange-500 transition-colors">Back to Home</a>
                    <a href="upload_reel.php" class="text-white hover:text-orange-500 transition-colors">Upload Reel</a>
                    <a href="logout.php" class="text-white hover:text-orange-500 transition-colors">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <br><br>
    <main class="pt-20 p-4 sm:p-6 lg:p-8">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-6">Edit Your Reel</h1>

            <form method="POST" class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-gray-700">
                <div class="mb-6">
                    <label for="caption" class="block text-white font-medium mb-2">Caption</label>
                    <input type="text" name="caption" id="caption" value="<?php echo htmlspecialchars($row['caption']); ?>" class="w-full p-2 rounded-lg bg-gray-700 text-white border border-gray-600">
                </div>
                <div class="mb-6">
                    <label for="supplement_tag" class="block text-white font-medium mb-2">Supplement Tag (Promotion)</label>
                    <input type="text" name="supplement_tag" id="supplement_tag" value="<?php echo htmlspecialchars($row['supplement_tag']); ?>" class="w-full p-2 rounded-lg bg-gray-700 text-white border border-gray-600">
                </div>
                <div class="mb-6">
                    <label for="calories_info" class="block text-white font-medium mb-2">Calories Info (Promotion)</label>
                    <input type="text" name="calories_info" id="calories_info" value="<?php echo htmlspecialchars($row['calories_info']); ?>" class="w-full p-2 rounded-lg bg-gray-700 text-white border border-gray-600">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white font-bold py-2 rounded-lg hover:from-orange-600 hover:to-red-600 transition-colors">
                    Save Changes
                </button>
            </form>
        </div>
    </main>
</body>
</html>
<?php
if (isset($conn)) $conn->close();
if (isset($stmt)) $stmt->close();
?>