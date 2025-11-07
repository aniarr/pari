

<?php
session_start();
if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainer_login.php");
    exit();
}

// --- NEW: AJAX endpoints scoped to trainer session (fetch_messages, send_message, check_unread) ---
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax']) {
    header('Content-Type: application/json; charset=utf-8');
    $trainer_id = intval($_SESSION['trainer_id']);
    $db = new mysqli("localhost", "root", "", "rawfit");
    if ($db->connect_error) {
        echo json_encode(['success' => false, 'error' => 'DB connection error']);
        exit();
    }
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'fetch_messages') {
        $course_id = intval($_GET['course_id'] ?? 0);
        $user_id = intval($_GET['user_id'] ?? 0);
        if (!$course_id || !$user_id) {
            echo json_encode(['success' => false, 'error' => 'Missing params']);
            $db->close(); exit();
        }
        // mark user's messages as read (user -> trainer)
        $u = $db->prepare("UPDATE trainer_messages SET is_read = 1 WHERE trainer_id = ? AND course_id = ? AND user_id = ? AND sender_type = 'user' AND is_read = 0");
        $u->bind_param('iii', $trainer_id, $course_id, $user_id);
        $u->execute();
        $u->close();

        $q = $db->prepare("SELECT id, user_id, trainer_id, course_id, message, sender_type, created_at, is_read FROM trainer_messages WHERE trainer_id = ? AND course_id = ? AND user_id = ? ORDER BY created_at ASC");
        $q->bind_param('iii', $trainer_id, $course_id, $user_id);
        $q->execute();
        $res = $q->get_result();
        $msgs = $res->fetch_all(MYSQLI_ASSOC);
        $q->close();
        echo json_encode(['success' => true, 'messages' => $msgs]);
        $db->close();
        exit();
    }

    if ($action === 'send_message') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = trim($payload['message'] ?? '');
        $course_id = intval($payload['course_id'] ?? 0);
        $user_id = intval($payload['user_id'] ?? 0);
        if (!$message || !$course_id || !$user_id) {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            $db->close(); exit();
        }
        $ins = $db->prepare("INSERT INTO trainer_messages (user_id, trainer_id, course_id, message, sender_type, is_read, created_at) VALUES (?, ?, ?, ?, 'trainer', 0, NOW())");
        $ins->bind_param('iiis', $user_id, $trainer_id, $course_id, $message);
        $ok = $ins->execute();
        $ins->close();
        echo json_encode(['success' => (bool)$ok]);
        $db->close();
        exit();
    }

    if ($action === 'check_unread') {
        $sql = "SELECT tm.user_id, r.name AS user_name, tm.course_id, tc.title AS course_title,
                       MAX(tm.created_at) AS last_at,
                       SUM(CASE WHEN tm.is_read = 0 AND tm.sender_type = 'user' THEN 1 ELSE 0 END) AS unread_count
                FROM trainer_messages tm
                LEFT JOIN register r ON r.id = tm.user_id
                LEFT JOIN trainer_courses tc ON tc.id = tm.course_id
                WHERE tm.trainer_id = ?
                GROUP BY tm.user_id, tm.course_id
                ORDER BY last_at DESC
                LIMIT 200";
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

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    $db->close();
    exit();
}

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$trainer_id = intval($_SESSION['trainer_id']);

// Fetch all courses for this trainer
$stmt = $conn->prepare("SELECT DISTINCT tm.course_id, tc.title, r.name as user_name, r.id as user_id 
                       FROM trainer_messages tm 
                       JOIN trainer_courses tc ON tm.course_id = tc.id 
                       JOIN register r ON tm.user_id = r.id
                       WHERE tm.trainer_id = ?
                       GROUP BY tm.course_id, tm.user_id");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- NEW: enrich conversations with last message and unread count ---
foreach ($conversations as &$conv) {
    // last message for this pair
    $cstmt = $conn->prepare("SELECT message, sender_type, created_at FROM trainer_messages WHERE trainer_id = ? AND course_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
    $cstmt->bind_param('iii', $trainer_id, $conv['course_id'], $conv['user_id']);
    $cstmt->execute();
    $cstmt->bind_result($last_msg, $last_sender, $last_at);
    $cstmt->fetch();
    $cstmt->close();

    $conv['last_message'] = $last_msg ?? '';
    $conv['last_sender'] = $last_sender ?? '';
    $conv['last_at'] = $last_at ?? '';

    // unread count (user -> trainer)
    $uc = $conn->prepare("SELECT COUNT(*) FROM trainer_messages WHERE trainer_id = ? AND course_id = ? AND user_id = ? AND sender_type = 'user' AND is_read = 0");
    $uc->bind_param('iii', $trainer_id, $conv['course_id'], $conv['user_id']);
    $uc->execute();
    $uc->bind_result($unread);
    $uc->fetch();
    $uc->close();

    $conv['unread_count'] = intval($unread);
}
unset($conv); // break reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Messages - RawFit</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <div class="flex h-screen">

        
        <!-- Conversations Sidebar: modern card-style -->
        <aside class="w-96 bg-gradient-to-b from-gray-900/80 to-gray-800 border-r border-gray-700 overflow-y-auto p-4 space-y-4">
            <div class="sticky top-0 bg-transparent pt-2 pb-3 z-20">
                <div class="flex items-center justify-between mb-3">
                     <h1 class="text-3xl md:text-4xl font-extrabold p-3 pl-3 bg-gradient-to-r from-orange-400 via-orange-500 to-orange-600 bg-clip-text text-transparent drop-shadow-md">
                        Messages
                        </h1>

                    <button id="refreshConv" class="p-2 rounded-md text-gray-300 hover:bg-gray-800/60" title="Refresh">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5"/><path stroke-linecap="round" stroke-linejoin="round" d="M20 20v-5h-5"/></svg>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <input id="convSearch" type="search" placeholder="Search users or courses" class="flex-1 bg-gray-800/60 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
            </div>

            <div id="convList" class="space-y-3 mt-3">
                <?php foreach ($conversations as $conv):
                    $snippet = htmlspecialchars(mb_strimwidth($conv['last_message'] ?? '', 0, 80, '...'));
                    $time = !empty($conv['last_at']) ? date('M j H:i', strtotime($conv['last_at'])) : '';
                    $unread = intval($conv['unread_count']);
                ?>
                <div class="conv-row group bg-gray-800/30 hover:bg-gray-800/50 transition rounded-2xl p-3 cursor-pointer flex items-start gap-3"
                     data-course="<?php echo intval($conv['course_id']); ?>"
                     data-user="<?php echo intval($conv['user_id']); ?>"
                     data-name="<?php echo htmlspecialchars($conv['user_name'], ENT_QUOTES); ?>"
                     data-course-title="<?php echo htmlspecialchars($conv['title'], ENT_QUOTES); ?>"
                     onclick="loadChat('<?php echo intval($conv['course_id']); ?>','<?php echo intval($conv['user_id']); ?>','<?php echo htmlspecialchars(addslashes($conv['user_name'])); ?>','<?php echo htmlspecialchars(addslashes($conv['title'])); ?>')">

                    <div class="relative">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-gradient-to-br from-orange-400 to-red-500 text-white font-bold text-lg">
                            <?= strtoupper(substr($conv['user_name'],0,1)) ?>
                        </div>
                        <div class="absolute -top-1 -right-1 conv-unread">
                            <?php if ($unread>0): ?>
                                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-white bg-red-600 rounded-full"><?php echo $unread; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
`                       <a href="trainerman.php" 
                            class="fixed bottom-6 left-6 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white p-4 rounded-full shadow-lg transition transform hover:scale-105 z-50"
                            title="Back to Home">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5.25a.75.75 0 01-.75-.75V15a.75.75 0 00-.75-.75H9.75A.75.75 0 009 15v6.25a.75.75 0 01-.75.75H3a1 1 0 01-1-1V9.75z" />
                            </svg>
                            </a>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold conv-name truncate"><?php echo htmlspecialchars($conv['user_name']); ?></div>
                                <div class="text-xs text-orange-300 conv-course truncate"><?php echo htmlspecialchars($conv['title']); ?></div>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo $time; ?></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-300 conv-snippet truncate"><?php echo $snippet; ?></p>
                        <div class="mt-3 flex gap-2 text-xs">
                            <span class="px-2 py-1 bg-gray-700/40 rounded-md text-gray-200">Last message</span>
                            <?php if (!empty($conv['last_sender'])): ?>
                                <span class="px-2 py-1 bg-gray-700/20 rounded-md text-gray-400"><?php echo htmlspecialchars($conv['last_sender']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>
 
       <!-- Chat Area: centered larger panel to match message.php UI -->
<div class="flex-1 flex items-stretch h-full">
  <div class=" w-full h-full max-w-5xl">
    <div id="chat-panel" class="bg-gray-900/95 border border-gray-700 rounded-xl shadow-xl h-[100vh] flex flex-col overflow-hidden relative">
      
      <!-- Chat Header -->
      <div id="chatHeader" class="p-4 border-b border-gray-700 flex items-center justify-between bg-gray-900/80 backdrop-blur-sm">
        <div>
          <div id="chatWithLabel" class="text-sm text-gray-400">No conversation selected</div>
          <div id="chatWithName" class="text-lg font-semibold text-white mt-1">Messages</div>
        </div>
      </div>
      
      <!-- Messages Section (scrollable) -->
      <div id="messagesContainer" class="p-6 flex-1 overflow-y-auto space-y-4 bg-transparent scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-transparent">
        <div class="text-center text-gray-500">Select a conversation on the left to begin</div>
      </div>
      
      <!-- Message Input (fixed bottom) -->
      <div class="p-4 border-t border-gray-700 bg-gray-800/40">
        <form id="messageForm" class="flex items-end gap-4">
          <input type="hidden" id="currentCourseId">
          <input type="hidden" id="currentUserId">
          
          <textarea id="messageContent" rows="2"
            class="flex-1 bg-gray-800 border border-gray-700 rounded-xl p-4 text-white placeholder-gray-400 resize-none focus:ring-2 focus:ring-orange-500"
            placeholder="Write a reply..."></textarea>
          
          <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-3 rounded-xl font-semibold shadow transition">
            Send
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

     </div>
 
     <script>
     let currentChatInfo = {};
 
     async function loadChat(courseId, userId, userName = '', courseTitle = '') {
        console.log('Loading chat:', { courseId, userId, userName, courseTitle });
       currentChatInfo = { courseId: parseInt(courseId,10), userId: parseInt(userId,10) };
       document.getElementById('currentCourseId').value = currentChatInfo.courseId;
       document.getElementById('currentUserId').value = currentChatInfo.userId;
       // update header
       document.getElementById('chatHeader').innerHTML = `<div class="flex items-center justify-between"><div><div class="text-sm text-gray-400">Chat with</div><div class="text-lg font-semibold text-white">${escapeHtml(userName || 'User')}</div><div class="text-sm text-gray-400 mt-1">${escapeHtml(courseTitle || '')}</div></div></div>`;
       const container = document.getElementById('messagesContainer');
       container.innerHTML = '<div class="text-gray-400">Loading...</div>';
       await loadMessages();
       // focus reply editor
       setTimeout(()=>{ try { document.getElementById('messageContent').focus(); } catch(e){} }, 150);

       // mark selected conversation in the sidebar (uses .conv-row and data-course/data-user)
       markActiveConversation(currentChatInfo.courseId, currentChatInfo.userId);
     }
        
        async function loadMessages(silent = false) {
            if (!currentChatInfo.courseId || !currentChatInfo.userId) return;
            const container = document.getElementById('messagesContainer');
            if (!silent) container.innerHTML = '<div class="text-gray-400">Loading...</div>';
        try {
            // use local AJAX endpoint to ensure scoping to trainer session
            const res = await fetch(`trainer_messages.php?ajax=1&action=fetch_messages&course_id=${currentChatInfo.courseId}&user_id=${currentChatInfo.userId}`);
            const data = await res.json();
            if (!data.success) {
                container.innerHTML = '<div class="text-gray-400">No messages</div>';
                return;
            }
            const messages = data.messages || [];
            container.innerHTML = messages.map(msg => {
                const side = (msg.sender_type === 'trainer') ? 'justify-end' : 'justify-start';
                const bubbleBg = (msg.sender_type === 'trainer') ? 'bg-orange-500/20' : 'bg-gray-700/50';
                const who = (msg.sender_type === 'trainer') ? 'You' : (msg.user_name || 'User');
                return `<div class="flex ${side}"><div class="max-w-[90%] ${bubbleBg} rounded-lg p-4"><div class="text-sm font-semibold ${(msg.sender_type === 'trainer') ? 'text-orange-400' : 'text-blue-400'}">${escapeHtml(who)}</div><div class="text-white mt-1">${escapeHtml(msg.message)}</div><div class="text-xs text-gray-400 mt-1">${escapeHtml(new Date(msg.created_at).toLocaleString())}</div></div></div>`;
            }).join('');
 
            container.scrollTop = container.scrollHeight;
            // refresh unread numbers after loading (safer)
            updateUnreadCounts();
        } catch (err) {
            console.error('loadMessages err', err);
            container.innerHTML = '<div class="text-gray-400">Failed to load messages</div>';
        }
        const messages = data.messages || [];
const newHtml = messages.map(msg => {
    const side = (msg.sender_type === 'trainer') ? 'justify-end' : 'justify-start';
    const bubbleBg = (msg.sender_type === 'trainer') ? 'bg-orange-500/20' : 'bg-gray-700/50';
    const who = (msg.sender_type === 'trainer') ? 'You' : (msg.user_name || 'User');
    return `<div class="flex ${side}"><div class="max-w-[90%] ${bubbleBg} rounded-lg p-4"><div class="text-sm font-semibold ${(msg.sender_type === 'trainer') ? 'text-orange-400' : 'text-blue-400'}">${escapeHtml(who)}</div><div class="text-white mt-1">${escapeHtml(msg.message)}</div><div class="text-xs text-gray-400 mt-1">${escapeHtml(new Date(msg.created_at).toLocaleString())}</div></div></div>`;
}).join('');

if (container.innerHTML !== newHtml) {
    container.innerHTML = newHtml;
    container.scrollTop = container.scrollHeight;
}

     }
 
     // send reply - use local AJAX
     document.getElementById('messageForm').addEventListener('submit', async (e) => {
         e.preventDefault();
         const message = document.getElementById('messageContent').value.trim();
         if (!message || !currentChatInfo.courseId || !currentChatInfo.userId) return;
         try {
             const res = await fetch('trainer_messages.php?ajax=1&action=send_message', {
                 method: 'POST',
                 headers: {'Content-Type':'application/json'},
                 body: JSON.stringify({ message, course_id: currentChatInfo.courseId, user_id: currentChatInfo.userId })
             });
             const data = await res.json();
             if (data.success) {
                 document.getElementById('messageContent').value = '';
                 await loadMessages();
             } else {
                 console.error('send failed', data);
             }
         } catch (err) {
             console.error('send err', err);
         }
     });
 
     // new: update unread badges in sidebar and total
     async function updateUnreadCounts() {
         try {
             const res = await fetch('trainer_messages.php?ajax=1&action=check_unread');
             const data = await res.json();
             if (!data.success) return;
             const conv = data.conversations || [];
             // reset all badges
             document.querySelectorAll('.conv-unread').forEach(el => { el.innerHTML = ''; });
             // update per-row badges
             conv.forEach(c => {
                 const selector = `.conv-row[data-course="${c.course_id}"][data-user="${c.user_id}"] .conv-unread`;
                 const el = document.querySelector(selector);
                 if (el) {
                     if (c.unread_count && c.unread_count > 0) {
                         el.innerHTML = `<span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold text-white bg-red-600 rounded-full">${c.unread_count}</span>`;
                     } else {
                         el.innerHTML = '';
                     }
                 }
             });
             // optionally update nav unread if present
             const totalUnread = conv.reduce((s,i)=> s + parseInt(i.unread_count || 0), 0);
             const navBadge = document.getElementById('nav-unread-count');
             if (navBadge) {
                 if (totalUnread>0) { navBadge.textContent = totalUnread; navBadge.classList.remove('hidden'); }
                 else navBadge.classList.add('hidden');
             }
         } catch (err) {
             console.error('updateUnreadCounts err', err);
         }
     }
 
     // call updateUnreadCounts periodically
     updateUnreadCounts();
     setInterval(updateUnreadCounts, 5000);
 
    function escapeHtml(str){
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    // --- NEW: conversation list helpers ---
    (function(){
        const convSearch = document.getElementById('convSearch');
        const convList = document.getElementById('convList');
        const refreshBtn = document.getElementById('refreshConv');
    
        if (convSearch) {
            convSearch.addEventListener('input', function() {
                const q = this.value.trim().toLowerCase();
                Array.from(convList.querySelectorAll('.conv-row')).forEach(row => {
                    const name = (row.dataset.name || '').toLowerCase();
                    const ctitle = (row.dataset.courseTitle || '').toLowerCase();
                    const snippet = (row.querySelector('.conv-snippet')?.textContent || '').toLowerCase();
                    const show = !q || name.includes(q) || ctitle.includes(q) || snippet.includes(q);
                    row.style.display = show ? '' : 'none';
                });
            });
        }
    
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                // simple page reload to refresh conversations (keeps code simple)
                window.location.reload();
            });
        }
     })();
 
     // highlight active conversation when opened
     function markActiveConversation(courseId, userId) {
         const prev = document.querySelector('.conv-row.bg-gray-700');
         if (prev) prev.classList.remove('bg-gray-700');
         const sel = document.querySelector(`.conv-row[data-course="${courseId}"][data-user="${userId}"]`);
         if (sel) sel.classList.add('bg-gray-700');
     }
     </script>
 </body>
 </html>
