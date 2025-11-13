<?php
// Start session early so AJAX endpoints can access $_SESSION
session_start();

// --- NEW: AJAX endpoints for notifications & chat (check_unread, fetch_conversation, send_message) ---
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax']) {
    header('Content-Type: application/json; charset=utf-8');

    // Ensure trainer is authenticated for AJAX calls
    if (!isset($_SESSION['trainer_id']) || empty($_SESSION['trainer_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }

    $trainer_id = intval($_SESSION['trainer_id']);

    $db = new mysqli("localhost", "root", "", "rawfit");
    if ($db->connect_error) {
        echo json_encode(['success' => false, 'error' => 'DB connection error']);
        exit();
    }

    $action = $_REQUEST['action'] ?? '';

    if ($action === 'check_unread') {
        // return list of conversations with unread counts and last message time
        $sql = "SELECT tm.user_id, r.name AS user_name, tm.course_id, tc.title AS course_title,
                       MAX(tm.created_at) AS last_at,
                       SUM(CASE WHEN tm.is_read = 0 AND tm.sender_type = 'user' THEN 1 ELSE 0 END) AS unread_count
                FROM trainer_messages tm
                LEFT JOIN register r ON r.id = tm.user_id
                LEFT JOIN trainer_courses tc ON tc.id = tm.course_id
                WHERE tm.trainer_id = ?
                GROUP BY tm.user_id, tm.course_id
                ORDER BY last_at DESC
                LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $trainer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'conversations' => $rows]);
        $stmt->close();
        $db->close();
        exit();
    }

    if ($action === 'fetch_conversation') {
        $course_id = intval($_REQUEST['course_id'] ?? 0);
        $user_id = intval($_REQUEST['user_id'] ?? 0);
        if (!$course_id || !$user_id) {
            echo json_encode(['success' => false, 'error' => 'Missing params']);
            $db->close();
            exit();
        }
        // Mark user's messages as read
        $u = $db->prepare("UPDATE trainer_messages SET is_read = 1 WHERE trainer_id = ? AND course_id = ? AND user_id = ? AND sender_type = 'user' AND is_read = 0");
        $u->bind_param('iii', $trainer_id, $course_id, $user_id);
        $u->execute();
        $u->close();

        $q = $db->prepare("SELECT id, user_id, trainer_id, course_id, message, sender_type, created_at, is_read FROM trainer_messages WHERE trainer_id = ? AND course_id = ? AND user_id = ? ORDER BY created_at ASC");
        $q->bind_param('iii', $trainer_id, $course_id, $user_id);
        $q->execute();
        $r = $q->get_result();
        $msgs = $r->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'messages' => $msgs]);
        $q->close();
        $db->close();
        exit();
    }

    if ($action === 'send_message') {
        // trainer sends reply to user
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = trim($payload['message'] ?? '');
        $course_id = intval($payload['course_id'] ?? 0);
        $user_id = intval($payload['user_id'] ?? 0);

        if (!$message || !$course_id || !$user_id) {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            $db->close();
            exit();
        }

        $ins = $db->prepare("INSERT INTO trainer_messages (user_id, trainer_id, course_id, message, sender_type, is_read) VALUES (?, ?, ?, ?, 'trainer', 0)");
        $ins->bind_param('iiis', $user_id, $trainer_id, $course_id, $message);
        $ok = $ins->execute();
        $ins->close();

        echo json_encode(['success' => $ok]);
        $db->close();
        exit();
    }

    // default
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    $db->close();
    exit();
}

if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainerlogin.php"); // Redirect to trainer login page
    exit();
}

// Fetch trainer details from the database
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$trainer_id = $_SESSION['trainer_id'];

// Fetch trainer name/email from trainerlog
$sql = "SELECT trainer_id, trainer_name, trainer_email FROM trainerlog WHERE trainer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$result = $stmt->get_result();

$trainer = [
    'id' => '',
    'name' => '',
    'email' => '',
    'avatar' => '',
    'title' => 'Cardio Instructor'
];

if ($row = $result->fetch_assoc()) {
    $trainer['id'] = $row['trainer_id'];
    $trainer['name'] = $row['trainer_name'];
    $trainer['email'] = $row['trainer_email'];
}

// Fetch trainer image from trainer_details
$sql_img = "SELECT trainer_image FROM trainer_details WHERE id = ?";
$stmt_img = $conn->prepare($sql_img);
$stmt_img->bind_param("i", $trainer_id);
$stmt_img->execute();
$stmt_img->bind_result($trainer_image);
$stmt_img->fetch();
$stmt_img->close();

if (!empty($trainer_image)) {
    $trainer['avatar'] = "uploads/" . $trainer_image;
} else {
    $trainer['avatar'] = "https://images.pexels.com/photos/2379004/pexels-photo-2379004.jpeg?auto=compress&w=400&h=400&fit=crop";
}

// Fetch total courses for this trainer
$sql_courses = "SELECT COUNT(*) FROM trainer_courses WHERE trainer_id = ?";
$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->bind_param("i", $trainer_id);
$stmt_courses->execute();
$stmt_courses->bind_result($total_courses);
$stmt_courses->fetch();
$stmt_courses->close();

// Quick stats data
$quickStats = [
    'total_courses' => $total_courses,
    'active_students' => 0,
    'average_rating' => 4.8
];

$conn->close();

// --- NEW: support opening a specific conversation via GET params (open_user & open_course) ---
$open_user = intval($_GET['open_user'] ?? 0);
$open_course = intval($_GET['open_course'] ?? 0);
$open_user_name = '';
$open_course_title = '';
if ($open_user && $open_course) {
	$_db = new mysqli("localhost", "root", "", "rawfit");
	if (!$_db->connect_error) {
		// fetch user name
		$uq = $_db->prepare("SELECT name FROM register WHERE id = ?");
		$uq->bind_param('i', $open_user);
		$uq->execute();
		$uq->bind_result($uname);
		$uq->fetch();
		$uq->close();
		$open_user_name = $uname ?? '';

		// fetch course title (ensure it belongs to this trainer optionally)
		$cq = $_db->prepare("SELECT title FROM trainer_courses WHERE id = ?");
		$cq->bind_param('i', $open_course);
		$cq->execute();
		$cq->bind_result($ctitle);
		$cq->fetch();
		$cq->close();
		$open_course_title = $ctitle ?? '';

		$_db->close();
	}
}

// NEW: chat-mode flag when visiting trainerman.php?chat=1
$open_chat = (isset($_GET['chat']) && $_GET['chat'] == '1') ? 1 : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Dashboard - Rawfit</title>
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
                    },
                    colors: {
                        primary: '#F97316', // Orange-500
                        secondary: '#EF4444', // Red-500
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 font-inter text-gray-100 min-h-screen">
    <!-- Navigation Header -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-700">
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
                
                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="trainerman.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9,22 9,12 15,12 15,22"/>
                        </svg>
                        <span>Home</span>
                    </a>
                    <a href="manage-courses.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Course</span>
                    </a>
                </div>

              <!-- Profile Menu -->
<div class="relative">
    <!-- Button -->
    <button id="profile-menu-button"
        class="flex items-center space-x-3 text-sm rounded-lg px-3 py-2 text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
        <img class="h-8 w-8 rounded-full object-cover"
             src="<?php echo $trainer['avatar']; ?>"
             alt="<?php echo htmlspecialchars($trainer['name']); ?>">
        <span class="hidden md:block font-medium"><?php echo htmlspecialchars($trainer['name']); ?></span>
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Dropdown -->
    <div id="profile-dropdown"
        class="hidden absolute right-0 mt-2 w-48 bg-gray-800/95 backdrop-blur-sm rounded-xl border border-gray-700 shadow-lg py-1 z-50 transition-all duration-200 ease-out transform opacity-0 scale-95">
        <a href="trainer_profile.php"
            class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            View Profile
        </a>
        <a href="logout.php"
            class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-red-600/20 hover:text-red-400 transition-colors">
            <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            Sign Out
        </a>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("profile-menu-button");
    const dropdown = document.getElementById("profile-dropdown");

    btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const isHidden = dropdown.classList.contains("hidden");

        // Close any open dropdown first
        document.querySelectorAll(".profile-dropdown-open").forEach(el => {
            el.classList.add("hidden", "opacity-0", "scale-95");
            el.classList.remove("profile-dropdown-open", "opacity-100", "scale-100");
        });

        if (isHidden) {
            dropdown.classList.remove("hidden", "opacity-0", "scale-95");
            dropdown.classList.add("opacity-100", "scale-100", "profile-dropdown-open");
        } else {
            dropdown.classList.add("opacity-0", "scale-95");
            setTimeout(() => dropdown.classList.add("hidden"), 150);
            dropdown.classList.remove("profile-dropdown-open");
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add("opacity-0", "scale-95");
            setTimeout(() => dropdown.classList.add("hidden"), 150);
            dropdown.classList.remove("profile-dropdown-open");
        }
    });
});
</script>

            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-400">Welcome <?php echo htmlspecialchars($trainer['name']); ?></p>
        </div>
        
        <!-- Content Grid -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Add Course Sections -->
        <section class="lg:col-span-1 space-y-6">
           <!-- Add Course Section 1 -->
            <div class="h-[205px] bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700 p-6 flex flex-col items-center justify-center">
                <h1 class="text-lg font-semibold text-white mb-4">Add Course</h1>
                <p class="text-sm text-gray-400 text-center mb-4">Create a new course to share your expertise with our fitness community.</p>
              
                <a href="create-course.php" 
                class="w-full max-w-md bg-primary text-white py-3 px-4 rounded-lg font-medium 
                        hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-primary transition-all text-center block">
                    + Add New Course
                </a>
                <p class="text-xs text-gray-500 mt-2">Last updated: September 12, 2025, 10:45 AM IST</p>
            </div>
            <!-- Quick Actions -->
            <div class="h-[250px] bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="manage-courses.php" 
                       class="block w-full text-left px-4 py-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition-all">
                        <div class="font-medium text-white">Manage Courses</div>
                        <div class="text-sm text-gray-400">Edit or delete existing courses</div>
                    </a>
                    <a href="view-students.php" 
                       class="block w-full text-left px-4 py-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition-all">
                        <div class="font-medium text-white">View Students</div>
                        <div class="text-sm text-gray-400">See enrolled students and progress</div>
                    </a>
                  
                </div>
            </div>

            <!-- Open Messages button (moved from nav) -->
            <div class="mt-6 flex justify-center">
                <a href="trainer_messages.php" id="open-messages-left" class="flex items-center gap-3 px-5 py-3 rounded-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold shadow-lg transform transition hover:scale-105 focus:outline-none">
                    <span class="flex items-center justify-center w-27 h-9 bg-white/10 rounded-full">
                        <svg class="w-10 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span class="text-sm">Messages</span>
                    <span id="open-messages-left-badge" class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold text-white bg-red-600 rounded-full hidden">0</span>
                </a>
            </div>
        </section>

        <!-- Sidebar -->
        <aside class="lg:col-span-1 space-y-6">
            <!-- Trainer Profile Card -->
            <div class="h-[205px] bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6">
                <div class="flex items-center space-x-4">
                    <img class="h-16 w-16 rounded-full object-cover border border-gray-600" 
                         src="<?php echo $trainer['avatar']; ?>" 
                         alt="<?php echo htmlspecialchars($trainer['name']); ?>'s avatar">
                    <div>
                        <h3 class="text-lg font-semibold text-white">
                            <?php echo htmlspecialchars($trainer['name']); ?>
                        </h3>
                        <p class="text-sm text-gray-400">
                            <?php echo htmlspecialchars($trainer['title']); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo htmlspecialchars($trainer['email']); ?>
                        </p>
                    </div>
                </div>
                <div class="mt-6">
                    <a href="trainer_profile.php" 
                       class="block w-full bg-transparent border border-primary text-primary py-2 px-4 rounded-lg font-medium 
                              hover:bg-primary/10 hover:text-orange-400 transition-all text-center">
                        View Full Profile
                    </a>
                </div>
            </div>
                
            <!-- Quick Stats -->
            <div class="h-[120px] bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Quick Stats</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-400">Total Courses</span>
                        <span class="font-semibold text-primary">
                            <?php echo $quickStats['total_courses']; ?>
                        </span>
                    </div>
                
                   
                </div>
            </div>

            <!-- Recent Downloads -->
            <div class="mt-4 bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Downloads</h3>
                <div class="space-y-3 text-sm text-gray-300">
                    <?php
                    // Re-open DB connection to fetch downloads
                    $dconn = new mysqli("localhost", "root", "", "rawfit");
                    if ($dconn->connect_error) {
                        echo '<div class="text-gray-400">Could not load downloads</div>';
                    } else {
                        // Get recent downloads for courses owned by this trainer
                        $sql = "SELECT cd.downloaded_at, r.name as user_name, tc.title as course_title
                                FROM course_downloads cd
                                JOIN trainer_courses tc ON tc.id = cd.course_id
                                JOIN register r ON r.id = cd.user_id
                                WHERE tc.trainer_id = ?
                                ORDER BY cd.downloaded_at DESC
                                LIMIT 2";
                        if ($s = $dconn->prepare($sql)) {
                            $s->bind_param('i', $trainer_id);
                            $s->execute();
                            $res = $s->get_result();
                            if ($res && $res->num_rows > 0) {
                                while ($dl = $res->fetch_assoc()) {
                                    $time = date('M j, Y H:i', strtotime($dl['downloaded_at']));
                                    echo "<div class=\"p-3 bg-gray-700/30 rounded-lg\"><div class=\"font-medium text-white\">" . htmlspecialchars($dl['user_name']) . "</div><div class=\"text-gray-400 text-xs\">Downloaded: " . htmlspecialchars($dl['course_title']) . " — $time</div></div>";
                                }
                            } else {
                                echo '<div class="text-gray-400">No downloads yet</div>';
                            }
                            $s->close();
                        } else {
                            echo '<div class="text-gray-400">Downloads not available (table may be missing)</div>';
                        }
                        $dconn->close();
                    }
                    ?>
                </div>
            </div>

            
        </aside>
    </div>
    </div>

    </div>
<!-- --- JS: polling unread conversations, show dropdown, open chat, send message --- -->
<script>
(async function(){
    const trainerId = <?php echo intval($_SESSION['trainer_id']); ?>;
    const chatPanel = document.getElementById('trainer-chat-panel');
    const chatMessages = document.getElementById('chat-messages');
    const chatUserTitle = document.getElementById('chat-user-title');
    const chatCourseTitle = document.getElementById('chat-course-title');
    const chatInput = document.getElementById('chat-input');
    const chatCourseIdInput = document.getElementById('chat-course-id');
    const chatUserIdInput = document.getElementById('chat-user-id');
    const closeChat = document.getElementById('close-chat');

    let pollingTimer = null;

    console.log('trainer chat script initialized');

    closeChat?.addEventListener('click', () => {
        chatPanel.classList.add('hidden');
    });

    // ensure the "Open Messages" button still works
    document.getElementById('open-all-messages')?.addEventListener('click', () => {
        fetchUnread().then(items => {
            if (items && items.length) {
                // open first conversation
                openConversation(items[0].course_id, items[0].user_id, items[0].user_name, items[0].course_title);
            }
        });
    });

    async function fetchUnread(){
        try {
            const res = await fetch(window.location.pathname + '?ajax=1&action=check_unread');
            const data = await res.json();
            if (!data.success) return [];
            const conv = data.conversations || [];
            // update unread count (sum)
            const totalUnread = conv.reduce((s,i)=> s + parseInt(i.unread_count || 0), 0);
            // populate dropdown list (only if msg-list exists)
            if (!msgList) return conv;
            msgList.innerHTML = conv.map(c => {
                const last = new Date(c.last_at || Date.now()).toLocaleString();
                const unreadBadge = (c.unread_count && c.unread_count > 0) ? `<span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold text-white bg-red-600 rounded-full">${c.unread_count}</span>` : '';
                return `<div class="w-full p-3 hover:bg-gray-800/60 flex justify-between items-start list-row" data-course="${c.course_id}" data-user="${c.user_id}>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white truncate user-name">${escapeHtml(c.user_name || 'User')}</div>
                                <div class="text-xs text-gray-400 truncate course-title">${escapeHtml(c.course_title || 'Course')}</div>
                                <div class="text-xs text-gray-400 mt-1">Last: ${last}</div>
                            </div>
                            <div class="ml-3 flex flex-col items-end space-y-2">
                                ${unreadBadge}
                                <button type="button" class="open-chat-btn text-xs text-orange-400 px-2 py-1 border border-orange-400 rounded hover:bg-orange-400/10" data-course="${c.course_id}" data-user="${c.user_id}" aria-label="Open chat with ${escapeHtml(c.user_name || 'User')}">Open Chat</button>
                            </div>
                        </div>`;
            }).join('');
            // attach delegated click handler once (prevents duplicates)
            if (msgList && !msgList.dataset.delegated) {
                msgList.addEventListener('click', function(e) {
                    // find a button with data-course or the row with data-course
                    const btn = e.target.closest('.open-chat-btn');
                    const row = e.target.closest('.list-row') || (btn && btn.closest('.list-row'));
                    const target = btn || row;
                    if (!target) return;
                    e.stopPropagation();
                    const courseId = target.getAttribute('data-course') || target.dataset.course;
                    const userId = target.getAttribute('data-user') || target.dataset.user;
                    const userName = row?.querySelector('.user-name')?.textContent?.trim() ?? 'User';
                    const courseTitle = row?.querySelector('.course-title')?.textContent?.trim() ?? 'Course';
                    console.log('msgList click open', { courseId, userId, userName, courseTitle });
                    openConversation(courseId, userId, userName, courseTitle);
                }, false);
                msgList.dataset.delegated = '1';
            }
 
             return conv;
         } catch (err) {
             console.error('fetchUnread err', err);
             return [];
         }
     }

    async function openConversation(courseId, userId, userName, courseTitle) {
        console.log('openConversation called', { courseId, userId, userName, courseTitle });
        chatPanel.classList.remove('hidden');
        chatUserTitle.textContent = userName || 'Conversation';
        chatCourseTitle.textContent = courseTitle || '';
        chatCourseIdInput.value = courseId;
        chatUserIdInput.value = userId;
        chatMessages.innerHTML = '<div class="text-xs text-gray-400">Loading...</div>';
        await loadConversation(courseId, userId);
        // start short polling for currently opened conversation
        if (pollingTimer) clearInterval(pollingTimer);
        pollingTimer = setInterval(()=> loadConversation(courseId, userId, true), 3000);
        // focus on input for quick replies
        setTimeout(()=> {
            try { chatInput.focus(); } catch(e){/*ignore*/;}
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }, 200);
    }

    async function loadConversation(courseId, userId, keepScroll) {
        try {
            const res = await fetch(window.location.pathname + `?ajax=1&action=fetch_conversation&course_id=${courseId}&user_id=${userId}`);
            const data = await res.json();
            if (!data.success) {
                chatMessages.innerHTML = '<div class="text-xs text-gray-400">No messages</div>';
                return;
            }
            const msgs = data.messages || [];
            chatMessages.innerHTML = msgs.map(m => {
                const cls = m.sender_type === 'trainer' ? 'justify-end' : 'justify-start';
                const bubbleBg = m.sender_type === 'trainer' ? 'bg-orange-500/20' : 'bg-gray-700';
                const who = m.sender_type === 'trainer' ? 'You' : 'User';
                return `<div class="flex ${cls}"><div class="max-w-[85%] ${bubbleBg} rounded-lg p-2"><div class="text-xs text-gray-300 font-semibold">${who}</div><div class="text-sm text-white mt-1">${escapeHtml(m.message)}</div><div class="text-xs text-gray-400 mt-1">${new Date(m.created_at).toLocaleString()}</div></div></div>`;
            }).join('');
            if (!keepScroll) chatMessages.scrollTop = chatMessages.scrollHeight;
            else chatMessages.scrollTop = chatMessages.scrollHeight;
        } catch (err) {
            console.error('loadConversation err', err);
        }
    }

    // send reply
    document.getElementById('chat-send-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = chatInput.value.trim();
        const courseId = chatCourseIdInput.value;
        const userId = chatUserIdInput.value;
        if (!message || !courseId || !userId) return;
        try {
            const res = await fetch(window.location.pathname + '?ajax=1&action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message, course_id: courseId, user_id: userId })
            });
            const data = await res.json();
            if (data.success) {
                chatInput.value = '';
                // refresh messages
                loadConversation(courseId, userId);
                // also refetch unread counts/conversations
                fetchUnread();
            } else {
                console.error('Send message failed', data.error);
            }
        } catch (err) {
            console.error('send_message err', err);
        }
    });

    // expose these functions so external inline scripts (and links from other pages) can open a chat
    window.openConversation = openConversation;
    window.fetchUnread = fetchUnread;

    // --- NEW: auto-open conversation if open_user & open_course are set ---
    if (<?php echo json_encode($open_user && $open_course); ?>) {
        openConversation(<?php echo json_encode($open_course); ?>, <?php echo json_encode($open_user); ?>, <?php echo json_encode($open_user_name); ?>, <?php echo json_encode($open_course_title); ?>);
    }

    // --- NEW: if ?chat=1 then fetch unread and open first conversation if any, else show dropdown ---
    if (<?php echo json_encode($open_chat); ?>) {
        try {
            const conv = await fetchUnread();
            if (conv && conv.length > 0) {
                const first = conv[0];
                openConversation(first.course_id, first.user_id, first.user_name, first.course_title);
            } else {
                // show dropdown (if exists) so trainer sees list area even when empty
                if (dropdown) dropdown.classList.remove('hidden');
            }
         } catch (e) {
             console.error('chat-mode open failed', e);
         }
     }
 
 })();
</script>

<!-- NEW: If trainerman.php was opened with ?open_user=..&open_course=.. call openConversation -->
<?php if ($open_user && $open_course): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ensure functions are available on window (IIFE executed). Retry a few times if necessary.
    const courseId = <?= json_encode($open_course); ?>;
    const userId = <?= json_encode($open_user); ?>;
    const userName = <?= json_encode($open_user_name ?: 'User'); ?>;
    const courseTitle = <?= json_encode($open_course_title ?: 'Course'); ?>;

    let tries = 0;
    function tryOpen() {
        if (window.openConversation) {
            window.openConversation(courseId, userId, userName, courseTitle);
        } else if (tries < 10) {
            tries++;
            setTimeout(tryOpen, 150);
        } else {
            console.warn('openConversation not available');
        }
    }
    tryOpen();
});
</script>
<?php endif; ?>

<!-- small helper to update the small "Open Messages" badge -->
<script>
(function(){
    const badge = document.getElementById('open-messages-left-badge');
    const btn = document.getElementById('open-messages-left');

    async function refreshLeftBadge(){
        try {
            const res = await fetch(window.location.pathname + '?ajax=1&action=check_unread');
            const data = await res.json();
            if (!data || !data.success) {
                if (badge) badge.classList.add('hidden');
                return;
            }
            const conv = data.conversations || [];
            const total = conv.reduce((s,i)=> s + parseInt(i.unread_count || 0), 0);
            if (badge) {
                if (total > 0) {
                    badge.textContent = total;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        } catch (e) {
            console.warn('Failed to refresh messages badge', e);
        }
    }

    // refresh on load and periodically
    document.addEventListener('DOMContentLoaded', refreshLeftBadge);
    // also refresh when clicking the button (just before navigation)
    if (btn) {
        btn.addEventListener('click', function(){
            // try one last refresh (non-blocking)
            refreshLeftBadge().catch(()=>{});
        });
    }
    setInterval(refreshLeftBadge, 10000); // update every 10s
})();
</script>
</body>
</html>