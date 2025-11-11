<?php
session_start();
if (!isset($_SESSION['owner_id'])) {
    header('Location: login_owner.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) die('DB connect error: ' . $conn->connect_error);

// === HANDLE REPLY ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_reply') {
    $gym_id = (int)$_POST['gym_id'];
    $user_id = (int)$_POST['user_id'];
    $message = trim($_POST['message'] ?? '');

    if ($gym_id && $user_id && $message) {
        $sender = 'owner';
        $stmt = $conn->prepare('INSERT INTO gym_messages (gym_id, user_id, sender_type, message) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiss', $gym_id, $user_id, $sender, $message);
        $stmt->execute();
        $stmt->close();

        // MARK ALL USER MESSAGES IN THIS CHAT AS READ
        $mark = $conn->prepare("UPDATE gym_messages SET is_read = 1 WHERE gym_id = ? AND user_id = ? AND sender_type = 'user'");
        $mark->bind_param('ii', $gym_id, $user_id);
        $mark->execute();
        $mark->close();
    }
    // Redirect with selected user
    header("Location: owner_messages.php?gym_id=$gym_id&user_id=$user_id");
    exit;
}

// === GET ACTIVE CHAT ===
$active_gym_id = (int)($_GET['gym_id'] ?? 0);
$active_user_id = (int)($_GET['user_id'] ?? 0);

// === GET OWNER GYMS ===
$gymsStmt = $conn->prepare('SELECT gym_id, gym_name FROM gyms WHERE owner_id = ? ORDER BY gym_name');
$gymsStmt->bind_param('i', $_SESSION['owner_id']);
$gymsStmt->execute();
$gymsRes = $gymsStmt->get_result();
$ownerGyms = [];
while ($g = $gymsRes->fetch_assoc()) $ownerGyms[] = $g;
$gymsStmt->close();

// === LOAD CONVERSATIONS (for sidebar) ===
$conversations = [];
foreach ($ownerGyms as $gym) {
    $msgStmt = $conn->prepare('
        SELECT m.user_id, u.name AS user_name, m.message, m.created_at, m.sender_type, m.is_read
        FROM gym_messages m
        LEFT JOIN register u ON m.user_id = u.id
        WHERE m.gym_id = ?
        ORDER BY m.created_at DESC
    ');
    $msgStmt->bind_param('i', $gym['gym_id']);
    $msgStmt->execute();
    $res = $msgStmt->get_result();

    $users = [];
    $seen = [];
    while ($m = $res->fetch_assoc()) {
        $uid = $m['user_id'];
        if (isset($seen[$uid])) continue;
        $seen[$uid] = true;

        $unread = 0;
        if ($m['sender_type'] === 'user' && $m['is_read'] == 0) {
            $unreadStmt = $conn->prepare("SELECT COUNT(*) FROM gym_messages WHERE gym_id = ? AND user_id = ? AND sender_type = 'user' AND is_read = 0");
            $unreadStmt->bind_param('ii', $gym['gym_id'], $uid);
            $unreadStmt->execute();
            $unreadStmt->bind_result($unread);
            $unreadStmt->fetch();
            $unreadStmt->close();
        }

        $users[] = [
            'user_id' => $uid,
            'name' => $m['user_name'] ?? 'User',
            'last_message' => $m['message'],
            'last_time' => $m['created_at'],
            'unread' => $unread,
            'gym_id' => $gym['gym_id']
        ];
    }
    if ($users) $conversations[$gym['gym_id']] = ['gym' => $gym['gym_name'], 'users' => $users];
    $msgStmt->close();
}

// === LOAD ACTIVE CHAT MESSAGES ===
$active_chat = null;
if ($active_gym_id && $active_user_id) {
    $msgStmt = $conn->prepare('
        SELECT m.*, u.name AS user_name 
        FROM gym_messages m 
        LEFT JOIN register u ON m.user_id = u.id 
        WHERE m.gym_id = ? AND m.user_id = ?
        ORDER BY m.created_at ASC
    ');
    $msgStmt->bind_param('ii', $active_gym_id, $active_user_id);
    $msgStmt->execute();
    $res = $msgStmt->get_result();
    $msgs = [];
    while ($m = $res->fetch_assoc()) $msgs[] = $m;
    if ($msgs) {
        $active_chat = [
            'gym_name' => $conversations[$active_gym_id]['gym'] ?? 'Unknown Gym',
            'user_name' => $msgs[0]['user_name'] ?? 'User',
            'messages' => $msgs
        ];
    }
    $msgStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiries | RawFit Owner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { backdrop-filter: blur(12px); background: rgba(30,30,40,.6); border: 1px solid rgba(255,255,255,.1); }
        .chat-bubble { @apply px-4 py-2 rounded-lg max-w-xs text-sm; }
        .user-bubble { @apply bg-gray-700 text-gray-200; }
        .owner-bubble { @apply bg-orange-600 text-white; }
        .sidebar-user:hover { @apply bg-gray-800/70; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-gray-900 to-black text-gray-100 min-h-screen">

<!-- NAV -->
<nav class="fixed top-0 inset-x-0 bg-black/95 backdrop-blur-lg border-b border-gray-800 z-50">
    <div class="max-w-7xl mx-auto px-4 flex justify-between h-16 items-center">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center shadow-md">
                <i class="fas fa-dumbbell text-white"></i>
            </div>
            <span class="font-bold text-xl">RawFit Owner</span>
        </div>
        <div class="hidden md:flex items-center space-x-6">
            <a href="owner_dashboard.php" class="text-gray-300 hover:text-white">Dashboard</a>
            <a href="owner_gym.php" class="text-gray-300 hover:text-white">My Gym</a>
            <a href="owner_messages.php" class="text-orange-400 font-medium">Enquiries</a>
            <a href="logout_owner.php" class="text-gray-300 hover:text-white">Logout</a>
        </div>
    </div>
</nav>

<main class="pt-20 pb-12 px-4 max-w-7xl mx-auto flex gap-6 h-screen">

    <!-- LEFT SIDEBAR: USERS -->
    <div class="w-full md:w-80 glass rounded-2xl p-4 h-full overflow-y-auto">
        <h2 class="text-xl font-bold text-orange-400 mb-4">Enquiries</h2>

        <?php if (empty($conversations)): ?>
            <p class="text-gray-400 text-center py-8">No messages yet.</p>
        <?php else: ?>
            <?php foreach ($conversations as $gym_id => $data): ?>
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-400 mb-2"><?= htmlspecialchars($data['gym']) ?></h3>
                    <?php foreach ($data['users'] as $user): 
                        $isActive = ($active_gym_id == $user['gym_id'] && $active_user_id == $user['user_id']);
                        $activeClass = $isActive ? 'bg-orange-900/40 border-orange-600' : 'border-transparent';
                    ?>
                        <a href="?gym_id=<?= $user['gym_id'] ?>&user_id=<?= $user['user_id'] ?>"
                           class="flex items-center justify-between p-3 rounded-lg border <?= $activeClass ?> transition sidebar-user">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-orange-500 to-red-500 flex items-center justify-center text-white font-bold text-sm">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium text-white text-sm"><?= htmlspecialchars($user['name']) ?></div>
                                    <div class="text-xs text-gray-400 truncate w-32">
                                        <?= htmlspecialchars(substr($user['last_message'], 0, 30)) . (strlen($user['last_message']) > 30 ? '...' : '') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-400">
                                    <?= date('g:i a', strtotime($user['last_time'])) ?>
                                </div>
                                <?php if ($user['unread'] > 0): ?>
                                    <span class="inline-block bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center mt-1">
                                        <?= $user['unread'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RIGHT PANEL: CHAT -->
    <div class="flex-1 glass rounded-2xl p-6 flex flex-col h-full <?= $active_chat ? '' : 'items-center justify-center' ?>">
        <?php if (!$active_chat): ?>
            <div class="text-center text-gray-400">
                <i class="fas fa-comments text-6xl mb-4 text-gray-600"></i>
                <p class="text-lg">Select a user to start chatting</p>
            </div>
        <?php else: ?>
            <!-- Chat Header -->
            <div class="flex items-center space-x-3 mb-4 pb-4 border-b border-gray-700">
                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-orange-500 to-red-500 flex items-center justify-center text-white font-bold">
                    <?= strtoupper(substr($active_chat['user_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="font-semibold text-white"><?= htmlspecialchars($active_chat['user_name']) ?></div>
                    <div class="text-xs text-gray-400"><?= htmlspecialchars($active_chat['gym_name']) ?></div>
                </div>
            </div>

            <!-- Messages -->
            <div id="chatMessages" class="flex-1 overflow-y-auto space-y-3 pr-2 mb-4">
                <?php foreach ($active_chat['messages'] as $m): 
                    $isOwner = $m['sender_type'] === 'owner';
                    $bubble = $isOwner ? 'owner-bubble ml-auto' : 'user-bubble mr-auto';
                    $time = date('M j, g:i a', strtotime($m['created_at']));
                ?>
                    <div class="flex <?= $isOwner ? 'justify-end' : 'justify-start' ?>">
                        <div class="<?= $bubble ?> chat-bubble">
                            <p><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                            <p class="text-xs opacity-75 mt-1"><?= $time ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply Form -->
            <form method="POST" class="flex gap-2 mt-auto">
                <input type="hidden" name="action" value="send_reply">
                <input type="hidden" name="gym_id" value="<?= $active_gym_id ?>">
                <input type="hidden" name="user_id" value="<?= $active_user_id ?>">
                <textarea name="message" placeholder="Type your reply..." required
                          class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"
                          rows="2"></textarea>
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white p-3 rounded-lg transition">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
    // Auto-scroll to bottom
    const chat = document.getElementById('chatMessages');
    if (chat) {
        chat.scrollTop = chat.scrollHeight;
    }
</script>

</body>
</html>
<?php $conn->close(); ?>