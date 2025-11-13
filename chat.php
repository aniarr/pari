<?php
session_start();

// === REQUIRE LOGIN ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$gym_id = (int)($_GET['gym_id'] ?? 0);

if ($gym_id <= 0) {
    die('<div class="p-6 text-center text-red-400">Invalid Gym ID</div>');
}

// === DATABASE ===
$conn = new mysqli('localhost', 'root', '', 'rawfit');
if ($conn->connect_error) {
    die('DB Error: ' . $conn->connect_error);
}

// === ENSURE TABLE + is_read COLUMN ===
$conn->query("CREATE TABLE IF NOT EXISTS gym_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gym_id INT NOT NULL,
    user_id INT NOT NULL,
    sender_type ENUM('user', 'owner') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    INDEX(gym_id), INDEX(user_id), INDEX(sender_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("ALTER TABLE gym_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");

// === FETCH GYM NAME ===
$stmt = $conn->prepare('SELECT gym_name FROM gyms WHERE gym_id = ? AND status = 1');
$stmt->bind_param('i', $gym_id);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gym) {
    die('<div class="p-6 text-center text-red-400">Gym not found or not approved.</div>');
}
$gym_name = htmlspecialchars($gym['gym_name']);

// === AJAX: SEND MESSAGE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    header('Content-Type: application/json');
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO gym_messages (gym_id, user_id, sender_type, message, is_read) VALUES (?, ?, "user", ?, 0)');
    $stmt->bind_param('iis', $gym_id, $user_id, $message);
    $success = $stmt->execute();
    $new_id = $stmt->insert_id; // ← Get inserted ID
    $stmt->close();

    echo json_encode(['success' => $success, 'id' => $new_id]);
    exit;
}

// === AJAX: GET MESSAGES ===
if (isset($_GET['get_messages'])) {
    header('Content-Type: application/json');
    $since = (int)($_GET['since'] ?? 0);

    $sql = 'SELECT id, sender_type, message, created_at 
            FROM gym_messages 
            WHERE gym_id = ? AND user_id = ?';
    $params = 'ii';
    $types = [&$gym_id, &$user_id];

    if ($since > 0) {
        $sql .= ' AND id > ?';
        $params .= 'i';
        $types[] = &$since;
    }

    $sql .= ' ORDER BY created_at ASC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$types);
    $stmt->execute();
    $res = $stmt->get_result();

    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    // Mark owner messages as read when user opens chat
    $conn->query("UPDATE gym_messages SET is_read = 1 WHERE gym_id = $gym_id AND user_id = $user_id AND sender_type = 'owner' AND is_read = 0");

    echo json_encode(['messages' => $messages]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Chat with <?= $gym_name ?> | RawFit</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
  </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">

<!-- NAVIGATION -->
<nav class="fixed top-0 inset-x-0 z-50 bg-black/95 backdrop-blur-lg border-b border-gray-800">
  <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
    <div class="flex items-center space-x-3">
      <div class="w-9 h-9 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center shadow-md">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
          <path d="M6.5 6.5h11v11h-11z"/>
          <path d="M6.5 6.5L2 2"/><path d="M17.5 6.5L22 2"/>
          <path d="M6.5 17.5L2 22"/><path d="M17.5 17.5L22 22"/>
        </svg>
      </div>
      <span class="font-bold text-xl">RawFit</span>
    </div>
    <a href="display_gym.php" class="text-sm text-gray-300 hover:text-white">← Back to Gyms</a>
  </div>
</nav>

<!-- MAIN CHAT -->
<div class="flex-1 pt-20 pb-8 max-w-4xl mx-auto px-4 w-full">
  <!-- Header -->
  <div class="bg-gradient-to-r from-orange-600/20 to-red-600/20 rounded-2xl p-6 mb-6 border border-orange-500/30">
    <div class="flex items-center space-x-4">
      <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center text-2xl font-bold text-white shadow-lg">
        <?= strtoupper(substr($gym_name, 0, 1)) ?>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-white"><?= $gym_name ?></h1>
        <p class="text-sm text-orange-300">Chat with Gym Owner</p>
      </div>
    </div>
  </div>

  <!-- Chat Container -->
  <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-xl overflow-hidden flex flex-col h-[calc(100vh-12rem)]">
    <!-- Messages -->
    <div id="chatHistory" class="flex-1 p-6 space-y-4 overflow-y-auto scrollbar-hide">
      <div class="text-center text-gray-500 text-sm italic">Chat started on <?= date('M j, Y') ?></div>
    </div>

    <!-- Input -->
    <form id="chatForm" class="p-4 border-t border-gray-700 bg-gray-900/50">
      <div class="flex gap-3">
        <textarea
          id="messageInput"
          placeholder="Type a message..."
          class="flex-1 bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent resize-none transition-all"
          rows="1"
          maxlength="1000"
          required
        ></textarea>
        <button
          type="submit"
          class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white p-3 rounded-xl shadow-lg transition transform hover:scale-105 active:scale-95"
          title="Send"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
          </svg>
        </button>
      </div>
      <p class="text-xs text-gray-500 mt-2 text-right">Press Enter to send • Max 1000 chars</p>
    </form>
  </div>
</div>

<!-- SCRIPTS -->
<script>
const chatHistory = document.getElementById('chatHistory');
const messageInput = document.getElementById('messageInput');
const chatForm = document.getElementById('chatForm');
const gymId = <?= $gym_id ?>;
const userId = <?= $user_id ?>;
let lastMsgId = 0;
let lastSentMsgId = 0; // Track the message we just sent

// Auto-resize textarea
messageInput.addEventListener('input', function () {
  this.style.height = 'auto';
  this.style.height = (this.scrollHeight) + 'px';
});

// Format time
function formatTime(date) {
  const now = new Date();
  const msg = new Date(date);
  const diff = now - msg;
  if (diff < 60000) return 'Just now';
  if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
  if (diff < 86400000) return msg.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  return msg.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Append message
function appendMessage(text, sender, time, msgId = null) {
  // Skip if this is the message we just sent optimistically
  if (msgId && msgId === lastSentMsgId) {
    return;
  }

  const isUser = sender === 'user';
  const bubble = document.createElement('div');
  bubble.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-3 animate-fadeIn`;
  bubble.innerHTML = `
    <div class="${isUser ? 'bg-gradient-to-r from-orange-500 to-red-500 text-white' : 'bg-gray-700 text-gray-200'} 
                 px-4 py-3 rounded-2xl max-w-xs md:max-w-md shadow-md">
      <p class="text-sm break-words">${text.replace(/\n/g, '<br>')}</p>
      <p class="text-xs ${isUser ? 'text-orange-100' : 'text-gray-400'} mt-1 opacity-80">${time}</p>
    </div>
  `;
  chatHistory.appendChild(bubble);
  scrollToBottom();
}

// Scroll to bottom
function scrollToBottom() {
  chatHistory.scrollTop = chatHistory.scrollHeight;
}

// Send message
chatForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = messageInput.value.trim();
  if (!msg) return;

  // Optimistically show message
  appendMessage(msg, 'user', 'Sending...');
  messageInput.value = '';
  messageInput.style.height = 'auto';

  const formData = new FormData();
  formData.append('send_message', '1');
  formData.append('message', msg);

  try {
    const res = await fetch('', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) throw new Error();

    // Update "Sending..." → "Just now"
    const lastBubble = chatHistory.lastElementChild;
    if (lastBubble) {
      lastBubble.querySelector('p:last-child').textContent = 'Just now';
    }

    // Track the real message ID
    lastSentMsgId = data.id;
    lastMsgId = Math.max(lastMsgId, data.id);

  } catch (err) {
    alert('Failed to send. Please try again.');
    chatHistory.lastElementChild?.remove();
  }
});

// Enter to send
messageInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    chatForm.dispatchEvent(new Event('submit'));
  }
});

// Fetch messages
async function fetchMessages() {
  try {
    const res = await fetch(`?get_messages=1&gym_id=${gymId}&user_id=${userId}&since=${lastMsgId}`);
    const data = await res.json();
    if (data.messages && data.messages.length > 0) {
      data.messages.forEach(msg => {
        if (msg.id > lastMsgId) {
          lastMsgId = msg.id;

          // Skip if it's our own message we already showed
          if (msg.sender_type === 'user' && msg.id === lastSentMsgId) {
            return;
          }

          appendMessage(msg.message, msg.sender_type, formatTime(msg.created_at), msg.id);
        }
      });
    }
  } catch (err) {
    console.error('Fetch error');
  }
}

// Initial load + polling
fetchMessages();
setInterval(fetchMessages, 2500);

// Scroll to bottom on load
setTimeout(scrollToBottom, 100);
</script>

</body>
</html>
<?php $conn->close(); ?>