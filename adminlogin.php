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
            $confirm_password = $_POST['confirm_password'] ?? '';
            $secret_code = trim($_POST['secret_code'] ?? '');

            if (empty($username) || empty($password) || empty($secret_code)) {
                $error = "All fields are required.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                $error = "Password must be at least 8 characters and include an uppercase letter, a number and a special character.";
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
 <a href="index.php" 
                            class="fixed bottom-6 left-6 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white p-4 rounded-full shadow-lg transition transform hover:scale-105 z-50"
                            title="Back to Home">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5.25a.75.75 0 01-.75-.75V15a.75.75 0 00-.75-.75H9.75A.75.75 0 009 15v6.25a.75.75 0 01-.75.75H3a1 1 0 01-1-1V9.75z" />
                            </svg>
                            </a>
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
       Login Securely
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
        <!-- pattern: min 8, 1 uppercase, 1 digit, 1 special char -->
        <input type="password" name="password" id="registerPassword" placeholder="Create password" required
               pattern="(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}"
               title="At least 8 characters, include 1 uppercase letter, 1 number and 1 special character"
               aria-describedby="pwdHelp"
               class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
        <button type="button" onclick="togglePassword('registerPassword')" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-orange-400">
          👁️
        </button>
      </div>
      <div id="pwdHelp" class="mt-2 text-xs text-gray-400">
        <ul id="pwdReqs" class="space-y-1">
          <li id="reqLength" class="text-red-400">• At least 8 characters</li>
          <li id="reqUpper" class="text-red-400">• At least one uppercase letter (A–Z)</li>
          <li id="reqDigit" class="text-red-400">• At least one number (0–9)</li>
          <li id="reqSpecial" class="text-red-400">• At least one special character (!@#$%^&*)</li>
        </ul>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
      <div class="relative">
        <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm password" required
               class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
        <button type="button" onclick="togglePassword('confirmPassword')" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-orange-400">
          👁️
        </button>
      </div>
      <div id="pwdMatch" class="mt-2 text-xs text-gray-400"></div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Admin Secret Code</label>
      <input type="text" name="secret_code" placeholder="Enter Admin Code" required
             class="w-full px-4 py-3 rounded-lg bg-gray-800/70 border border-gray-700 text-white placeholder-gray-500 focus:ring-2 focus:ring-orange-500 outline-none transition">
    </div>

    <button type="submit"
            class="w-full flex justify-center items-center gap-2 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600
                   text-white font-semibold rounded-lg shadow-lg shadow-orange-500/20 transform hover:scale-[1.02] transition-all">
       Register Admin
    </button>
  </form>

  <!-- Toggle -->
  <div class="text-center">
    <button onclick="toggleForms()" class="text-sm text-orange-400 hover:text-orange-300 transition">
      Don’t have an account? Register
    </button>
  </div>

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

// Client-side confirm-password check for better UX
document.addEventListener('DOMContentLoaded', function () {
  const regForm = document.getElementById('registerForm');
  if (!regForm) return;

  const pwd = document.getElementById('registerPassword');
  const cp = document.getElementById('confirmPassword');
  const reqLength = document.getElementById('reqLength');
  const reqUpper  = document.getElementById('reqUpper');
  const reqDigit  = document.getElementById('reqDigit');
  const reqSpecial= document.getElementById('reqSpecial');
  const pwdMatch  = document.getElementById('pwdMatch');

  function validatePasswordLive() {
    const v = pwd.value || '';
    // checks
    const okLength = v.length >= 8;
    const okUpper  = /[A-Z]/.test(v);
    const okDigit  = /\d/.test(v);
    const okSpecial= /[\W_]/.test(v);

    reqLength.classList.toggle('text-green-400', okLength);
    reqLength.classList.toggle('text-red-400', !okLength);
    reqUpper.classList.toggle('text-green-400', okUpper);
    reqUpper.classList.toggle('text-red-400', !okUpper);
    reqDigit.classList.toggle('text-green-400', okDigit);
    reqDigit.classList.toggle('text-red-400', !okDigit);
    reqSpecial.classList.toggle('text-green-400', okSpecial);
    reqSpecial.classList.toggle('text-red-400', !okSpecial);
    return okLength && okUpper && okDigit && okSpecial;
  }

  function validateMatch() {
    if (!cp) return false;
    const match = pwd.value === cp.value && cp.value.length > 0;
    pwdMatch.textContent = match ? 'Passwords match' : (cp.value ? 'Passwords do not match' : '');
    pwdMatch.classList.toggle('text-green-400', match);
    pwdMatch.classList.toggle('text-red-400', !match && cp.value.length > 0);
    return match;
  }

  if (pwd) pwd.addEventListener('input', () => { validatePasswordLive(); validateMatch(); });
  if (cp)  cp.addEventListener('input', validateMatch);

  regForm.addEventListener('submit', function (e) {
    // enforce client-side policy before submit
    const okPolicy = validatePasswordLive();
    const okMatch = validateMatch();
    if (!okPolicy || !okMatch) {
      e.preventDefault();
      alert('Password does not meet requirements or passwords do not match. Please correct and try again.');
      return false;
    }
    return true;
  });
});
</script>
</body>
</html>
