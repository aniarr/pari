<?php
session_start();

// --- NEW: AJAX handler to scope messages by logged-in user (prevents cross-account leakage) ---
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax']) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }

    $db = new mysqli("localhost", "root", "", "rawfit");
    if ($db->connect_error) {
        echo json_encode(['success' => false, 'error' => 'DB connection error']);
        exit();
    }

    $user_id = intval($_SESSION['user_id']);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $trainer_id = intval($_GET['trainer_id'] ?? 0);
        $course_id = intval($_GET['course_id'] ?? 0);

        if (!$trainer_id || !$course_id) {
            echo json_encode(['success' => false, 'messages' => []]);
            $db->close();
            exit();
        }

        $q = $db->prepare("SELECT id, user_id, trainer_id, course_id, message, sender_type, created_at FROM trainer_messages WHERE trainer_id = ? AND course_id = ? AND user_id = ? ORDER BY created_at ASC");
        $q->bind_param('iii', $trainer_id, $course_id, $user_id);
        $q->execute();
        $res = $q->get_result();
        $msgs = $res->fetch_all(MYSQLI_ASSOC);
        $q->close();

        echo json_encode(['success' => true, 'messages' => $msgs]);
        $db->close();
        exit();
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = trim($payload['message'] ?? '');
        $trainer_id = intval($payload['trainer_id'] ?? 0);
        $course_id = intval($payload['course_id'] ?? 0);
        $sender_type = ($payload['sender_type'] ?? 'user') === 'trainer' ? 'trainer' : 'user';

        if (!$message || !$trainer_id || !$course_id) {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            $db->close();
            exit();
        }

        $ins = $db->prepare("INSERT INTO trainer_messages (user_id, trainer_id, course_id, message, sender_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $ins->bind_param('iiiss', $user_id, $trainer_id, $course_id, $message, $sender_type);
        $ok = $ins->execute();
        $ins->close();

        echo json_encode(['success' => (bool)$ok]);
        $db->close();
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Unsupported method']);
    $db->close();
    exit();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get and validate parameters
$trainer_id = isset($_GET['trainer_id']) ? intval($_GET['trainer_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$trainer_id || !$course_id) {
    header("Location: home.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify trainer and course exist
$stmt = $conn->prepare("SELECT t.*, c.title as course_title 
                       FROM trainer_details t 
                       JOIN trainer_courses c ON c.trainer_id = t.id 
                       WHERE t.id = ? AND c.id = ? LIMIT 1");
$stmt->bind_param("ii", $trainer_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();
$details = $result->fetch_assoc();

if (!$details) {
    header("Location: user_booking.php?course_id=" . $course_id);
    exit();
}

// --- NEW: current logged-in user id for scoping messages ---
$user_id = intval($_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Trainer - RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-inter">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gray-800 border-b border-gray-700 p-4">
            <div class="max-w-4xl mx-auto flex items-center justify-between">
                <a href="user_booking.php?course_id=<?php echo $course_id; ?>" 
                   class="flex items-center text-gray-400 hover:text-white">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    <span class="ml-2">Back to Course</span>
                </a>
                <div class="text-center">
                    <h1 class="text-xl font-bold"><?php echo htmlspecialchars($details['name']); ?></h1>
                    <p class="text-sm text-gray-400"><?php echo htmlspecialchars($details['course_title']); ?></p>
                </div>
                <div class="w-24"></div>
            </div>
        </header>

        <!-- Chat Area -->
        <div class="flex-1 max-w-4xl mx-auto w-full p-4">
            <div id="messages" class="space-y-4 mb-4 h-[calc(100vh-250px)] overflow-y-auto p-4 bg-gray-800/50 rounded-lg"></div>
            
            <form id="messageForm" class="bg-gray-800/50 rounded-lg p-4">
                <div class="flex gap-4">
                    <textarea id="messageContent" rows="3" 
                        class="flex-1 bg-gray-700 rounded-lg p-3 text-white border border-gray-600 focus:border-orange-500"
                        placeholder="Type your message..."></textarea>
                    <button type="submit" 
                        class="bg-gradient-to-r from-orange-500 to-red-500 text-white px-6 rounded-lg hover:from-orange-600 hover:to-red-600">
                        Send
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // expose current user id to JS so requests are scoped per-user
    const CURRENT_USER_ID = <?php echo json_encode($user_id); ?>;
    const TRAINER_ID = <?php echo json_encode($trainer_id); ?>;
    const COURSE_ID = <?php echo json_encode($course_id); ?>;

    function escapeHtml(str){
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    // Chat functionality
    document.getElementById('messageForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const messageContent = document.getElementById('messageContent');
        const message = messageContent.value.trim();
        if (!message) return;

        try {
            // POST to this same file's AJAX endpoint so server enforces user scoping
            const response = await fetch(`messages.php?ajax=1`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message,
                    trainer_id: TRAINER_ID,
                    course_id: COURSE_ID,
                    sender_type: 'user'
                })
            });

            const result = await response.json();
            if (result.success) {
                messageContent.value = '';
                loadMessages();
            } else {
                console.error('Send error', result);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });

    async function loadMessages() {
        try {
            // GET from this file's AJAX endpoint including session-scoped user
            const response = await fetch(`messages.php?ajax=1&course_id=${COURSE_ID}&trainer_id=${TRAINER_ID}`);
            const payload = await response.json();
            const container = document.getElementById('messages');

            let msgs = [];
            if (payload && payload.success && Array.isArray(payload.messages)) msgs = payload.messages;

            container.innerHTML = msgs.map(msg => `
                <div class="flex ${msg.sender_type === 'user' ? 'justify-end' : 'justify-start'}">
                    <div class="max-w-[80%] ${msg.sender_type === 'user' ? 'bg-orange-500/20' : 'bg-gray-700'} rounded-lg p-4">
                        <p class="text-sm font-semibold ${msg.sender_type === 'user' ? 'text-orange-400' : 'text-blue-400'}">
                            ${msg.sender_type === 'user' ? 'You' : 'Trainer'}
                        </p>
                        <p class="text-white mt-1">${escapeHtml(msg.message)}</p>
                        <p class="text-xs text-gray-400 mt-1">${escapeHtml(new Date(msg.created_at).toLocaleString())}</p>
                    </div>
                </div>
            `).join('');
            
            container.scrollTop = container.scrollHeight;
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Initial load and refresh
    loadMessages();
    setInterval(loadMessages, 3000);
    </script>
</body>
</html>
