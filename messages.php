<?php
session_start();

/* ---------- AJAX HANDLER ---------- */
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
    $method  = $_SERVER['REQUEST_METHOD'];

    /* ---------- GET : fetch messages ---------- */
    if ($method === 'GET') {
        $trainer_id = intval($_GET['trainer_id'] ?? 0);
        $course_id  = intval($_GET['course_id'] ?? 0);

        if (!$trainer_id || !$course_id) {
            echo json_encode(['success' => false, 'messages' => []]);
            $db->close();
            exit();
        }

        $q = $db->prepare("SELECT id, user_id, trainer_id, course_id, message, sender_type, created_at 
                           FROM trainer_messages 
                           WHERE trainer_id = ? AND course_id = ? AND user_id = ? 
                           ORDER BY created_at ASC");
        $q->bind_param('iii', $trainer_id, $course_id, $user_id);
        $q->execute();
        $res = $q->get_result();
        $msgs = $res->fetch_all(MYSQLI_ASSOC);
        $q->close();

        echo json_encode(['success' => true, 'messages' => $msgs]);
        $db->close();
        exit();
    }

    /* ---------- POST : insert new message ---------- */
    if ($method === 'POST') {
        $payload     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message     = trim($payload['message'] ?? '');
        $trainer_id  = intval($payload['trainer_id'] ?? 0);
        $course_id   = intval($payload['course_id'] ?? 0);
        $sender_type = ($payload['sender_type'] ?? 'user') === 'trainer' ? 'trainer' : 'user';

        if (!$message || !$trainer_id || !$course_id) {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            $db->close();
            exit();
        }

        $ins = $db->prepare("INSERT INTO trainer_messages 
                (user_id, trainer_id, course_id, message, sender_type, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $ins->bind_param('iiiss', $user_id, $trainer_id, $course_id, $message, $sender_type);
        $ok = $ins->execute();
        $new_id = $ins->insert_id;
        $ins->close();

        if ($ok && $new_id) {
            // Return the freshly inserted row
            $q = $db->prepare("SELECT id, user_id, trainer_id, course_id, message, sender_type, created_at 
                               FROM trainer_messages WHERE id = ?");
            $q->bind_param('i', $new_id);
            $q->execute();
            $res = $q->get_result();
            $new_msg = $res->fetch_assoc();
            $q->close();

            echo json_encode(['success' => true, 'message' => $new_msg]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Insert failed']);
        }
        $db->close();
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Unsupported method']);
    $db->close();
    exit();
}

/* ---------- MAIN PAGE ---------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$trainer_id = isset($_GET['trainer_id']) ? intval($_GET['trainer_id']) : 0;
$course_id  = isset($_GET['course_id'])  ? intval($_GET['course_id'])  : 0;

if (!$trainer_id || !$course_id) {
    header("Location: home.php");
    exit();
}

/* ---- DB connection & data fetch ---- */
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* Verify trainer + course */
$stmt = $conn->prepare("SELECT t.*, c.title AS course_title 
                        FROM trainer_details t 
                        JOIN trainer_courses c ON c.trainer_id = t.id 
                        WHERE t.id = ? AND c.id = ? LIMIT 1");
$stmt->bind_param("ii", $trainer_id, $course_id);
$stmt->execute();
$res = $stmt->get_result();
$details = $res->fetch_assoc();
$stmt->close();

if (!$details) {
    header("Location: user_booking.php?course_id=" . $course_id);
    exit();
}

$user_id = intval($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?=htmlspecialchars($details['name'])?> - RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar {width:6px}
        ::-webkit-scrollbar-track {background:#1a1a1a}
        ::-webkit-scrollbar-thumb {background:#f97316;border-radius:3px}
        ::-webkit-scrollbar-thumb:hover {background:#ea580c}

        .message-bubble{animation:fadeIn .3s ease-out}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

        .typing span{display:inline-block;width:8px;height:8px;margin:0 2px;background:#9ca3af;border-radius:50%;animation:typing 1.4s infinite}
        .typing span:nth-child(1){animation-delay:0s}
        .typing span:nth-child(2){animation-delay:.2s}
        .typing span:nth-child(3){animation-delay:.4s}
        @keyframes typing{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-10px)}}
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-black to-gray-900 text-white font-inter min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-gray-800/80 backdrop-blur-md border-b border-orange-500/20 shadow-xl">
        <div class="max-w-4xl mx-auto flex items-center justify-between p-4">
            <a href="user_booking.php?course_id=<?=$course_id?>" class="flex items-center text-orange-400 hover:text-orange-300 transition">
                <i class="fas fa-arrow-left mr-2"></i><span class="font-medium">Back</span>
            </a>
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white font-bold text-lg shadow-lg">
                    <?=strtoupper(substr($details['name'],0,1))?>
                </div>
                <div>
                    <h1 class="text-lg font-bold"><?=htmlspecialchars($details['name'])?></h1>
                    <p class="text-xs text-green-400 flex items-center">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>Online
                    </p>
                </div>
            </div>
            <div class="w-20"></div>
        </div>
    </header>

    <!-- Chat Area -->
    <div class="flex-1 max-w-4xl mx-auto w-full p-4 flex flex-col">
        <div id="messages" class="flex-1 overflow-y-auto p-5 space-y-4 bg-gray-900/50 backdrop-blur-sm rounded-2xl border border-gray-700/50 shadow-2xl"
             style="max-height:calc(100vh - 220px);"></div>

        <form id="messageForm" class="mt-4 bg-gray-800/70 backdrop-blur-md rounded-2xl p-4 border border-gray-700/50 shadow-xl">
            <div class="flex items-end gap-3">
                <textarea id="messageContent" rows="1"
                    class="flex-1 bg-gray-700/70 text-white placeholder-gray-400 rounded-xl px-4 py-3 border border-gray-600 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/30 outline-none transition-all resize-none"
                    placeholder="Type your message..."></textarea>
                <button type="submit"
                    class="bg-gradient-to-r from-orange-500 to-red-600 text-white p-3 rounded-xl hover:from-orange-600 hover:to-red-700 transition-all transform hover:scale-105 shadow-lg">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo json_encode($user_id); ?>;
        const TRAINER_ID      = <?php echo json_encode($trainer_id); ?>;
        const COURSE_ID       = <?php echo json_encode($course_id); ?>;
        let lastMessageId = 0;
        let isSending = false;

        const escapeHtml = str => {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        };

        const createBubble = msg => {
            const isUser = msg.sender_type === 'user';
            const time   = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            return `
                <div class="flex ${isUser?'justify-end':'justify-start'} message-bubble">
                    <div class="${isUser
                        ?'bg-gradient-to-br from-orange-500/30 to-red-600/30 border border-orange-500/30 text-white'
                        :'bg-gray-700/80 border border-gray-600 text-gray-100'} 
                        rounded-2xl px-4 py-3 max-w-xs md:max-w-md shadow-md backdrop-blur-sm">
                        <p class="text-sm font-medium ${isUser?'text-orange-300':'text-blue-300'}">
                            ${isUser?'You':'Trainer'}
                        </p>
                        <p class="mt-1 leading-relaxed">${escapeHtml(msg.message)}</p>
                        <p class="text-xs ${isUser?'text-orange-200/70':'text-gray-400'} mt-1 text-right">${time}</p>
                    </div>
                </div>`;
        };

        const loadMessages = async () => {
            try {
                const resp = await fetch(`messages.php?ajax=1&course_id=${COURSE_ID}&trainer_id=${TRAINER_ID}`);
                const data = await resp.json();
                const container = document.getElementById('messages');
                const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

                if (data.success && Array.isArray(data.messages)) {
                    const msgs = data.messages;

                    if (msgs.length === 0) {
                        container.innerHTML = `<div class="text-center text-gray-500 py-10">
                            <i class="fas fa-comment-slash text-4xl mb-3"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>`;
                        lastMessageId = 0;
                        return;
                    }

                    const newer = msgs.filter(m => m.id > lastMessageId);
                    if (newer.length) {
                        lastMessageId = Math.max(...newer.map(m => m.id), lastMessageId);
                        const frag = document.createDocumentFragment();
                        newer.forEach(m => {
                            const div = document.createElement('div');
                            div.innerHTML = createBubble(m);
                            frag.appendChild(div);
                        });
                        container.appendChild(frag);
                        if (atBottom) container.scrollTop = container.scrollHeight;
                    }
                }
            } catch (e) { console.error(e); }
        };

        document.getElementById('messageForm').addEventListener('submit', async e => {
            e.preventDefault();
            if (isSending) return;
            isSending = true;

            const textarea = document.getElementById('messageContent');
            const text = textarea.value.trim();
            if (!text) { isSending = false; return; }

            // ---- optimistic UI ----
            const temp = {id: Date.now(), sender_type: 'user', message: text, created_at: new Date().toISOString()};
            const container = document.getElementById('messages');
            const optimisticDiv = document.createElement('div');
            optimisticDiv.innerHTML = createBubble(temp);
            container.appendChild(optimisticDiv);
            container.scrollTop = container.scrollHeight;
            textarea.value = '';

            try {
                const r = await fetch('messages.php?ajax=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: text,
                        trainer_id: TRAINER_ID,
                        course_id: COURSE_ID,
                        sender_type: 'user'
                    })
                });
                const res = await r.json();

                // ---- remove optimistic bubble ----
                optimisticDiv.remove();

                if (!res.success) {
                    alert('Failed to send. Try again.');
                } else {
                    // ---- insert real message returned by server ----
                    const realMsg = res.message;
                    lastMessageId = Math.max(lastMessageId, realMsg.id);

                    const realDiv = document.createElement('div');
                    realDiv.innerHTML = createBubble(realMsg);
                    container.appendChild(realDiv);
                    container.scrollTop = container.scrollHeight;
                }
            } catch (err) {
                console.error(err);
                alert('Network error.');
            } finally {
                isSending = false;
            }
        });

        // auto-resize textarea
        const ta = document.getElementById('messageContent');
        ta.addEventListener('input', () => {
            ta.style.height = 'auto';
            ta.style.height = ta.scrollHeight + 'px';
        });

        // ---- initial load + start polling ----
        loadMessages().then(() => setInterval(loadMessages, 2500));
    </script>
</body>
</html>