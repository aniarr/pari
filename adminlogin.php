<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $form_type = $_POST['form_type'] ?? "";

        if ($form_type === "login") {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = "All fields are required.";
            } else {
                $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($admin_id, $username_db, $hashed_password);
                    $stmt->fetch();

                    if (password_verify($password, $hashed_password)) {
                        session_regenerate_id(true);
                        $_SESSION['loggedin'] = true;
                        $_SESSION['role'] = 'admin';
                        $_SESSION['username'] = $username_db;

                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        header("Location: admin.php");
                        exit;
                    } else {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
                $stmt->close();
            }
        } elseif ($form_type === "register") {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $secret_code = trim($_POST['secret_code'] ?? '');

            if (empty($username) || empty($password) || empty($secret_code)) {
                $error = "All fields are required.";
            } elseif ($secret_code !== "RAWFITADMIN") {
                $error = "Invalid secret code.";
            } else {
                $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $error = "Username already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->close();

                    $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                    $stmt->bind_param("ss", $username, $hashed_password);
                    if ($stmt->execute()) {
                        $success = "Registration successful. Please login.";
                    } else {
                        $error = "Error: Could not register.";
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Access - RawFit</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 flex items-center justify-center min-h-screen font-[Inter]">

<!-- Glassmorphic Container -->
<div class="bg-gray-900/70 backdrop-blur-lg border border-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6 transition-all duration-300">

  <!-- Header -->
  <div class="text-center">
    <h1 id="formTitle" class="text-3xl font-bold bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent">Admin Login</h1>
    <p class="text-gray-400 text-sm mt-1">Access RawFit control panel securely</p>
  </div>

  <!-- Feedback -->
  <?php if (!empty($error)): ?>
    <div class="text-red-400 text-center text-sm bg-red-500/10 py-2 rounded-lg"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="text-green-400 text-center text-sm bg-green-500/10 py-2 rounded-lg"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Login Form -->
  <form method="POST" id="loginForm" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="form_type" value="login">

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
      <input type="text" name="username" placeholder="Enter admin username" required
             class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
      <div class="relative">
        <input type="password" name="password" id="loginPassword" placeholder="Enter password" required
               class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
        <button type="button" onclick="togglePassword('loginPassword')" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-orange-400">
          👁️
        </button>
      </div>
    </div>

    <button type="submit"
            class="w-full flex justify-center items-center gap-2 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600
                   text-white font-semibold rounded-lg shadow-lg shadow-orange-500/20 transform hover:scale-[1.02] transition-all">
      🔐 Login Securely
    </button>
  </form>

  <!-- Register Form -->
  <form method="POST" id="registerForm" class="space-y-5 hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="form_type" value="register">

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">New Username</label>
      <input type="text" name="username" placeholder="Create admin username" required
             class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
      <div class="relative">
        <input type="password" name="password" id="registerPassword" placeholder="Create password" required
               class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
        <button type="button" onclick="togglePassword('registerPassword')" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-orange-400">
          👁️
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Admin Secret Code</label>
      <input type="text" name="secret_code" placeholder="Enter Admin Code" required
             class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
    </div>

    <button type="submit"
            class="w-full flex justify-center items-center gap-2 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600
                   text-white font-semibold rounded-lg shadow-lg shadow-orange-500/20 transform hover:scale-[1.02] transition-all">
      🚀 Register Admin
    </button>
  </form>

  <!-- Toggle -->
  <div class="text-center">
    <button onclick="toggleForms()" class="text-sm text-orange-400 hover:text-orange-300 transition">
      Don’t have an account? Register
    </button>
  </div>

  <p class="text-center text-gray-500 text-xs mt-4">© <?= date('Y'); ?> RawFit. All rights reserved.</p>
</div>

<script>
function toggleForms() {
  const loginForm = document.getElementById("loginForm");
  const registerForm = document.getElementById("registerForm");
  const title = document.getElementById("formTitle");
  const toggleBtn = document.querySelector("button[onclick='toggleForms()']");

  if (loginForm.classList.contains("hidden")) {
    loginForm.classList.remove("hidden");
    registerForm.classList.add("hidden");
    title.innerText = "Admin Login";
    toggleBtn.innerText = "Don’t have an account? Register";
  } else {
    loginForm.classList.add("hidden");
    registerForm.classList.remove("hidden");
    title.innerText = "Admin Register";
    toggleBtn.innerText = "Already have an account? Login";
  }
}

function togglePassword(id) {
  const input = document.getElementById(id);
  input.type = input.type === "password" ? "text" : "password";
}
</script>
</body>
</html>
