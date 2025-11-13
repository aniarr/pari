<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = ""; // for success messages
$mode = isset($_GET['action']) && $_GET['action'] === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($mode === 'login') {

        $email = trim($_POST['trainer_email']);
        $password = trim($_POST['trainer_password']);

        $stmt = $conn->prepare("SELECT trainer_id, trainer_name, trainer_email, trainer_password FROM trainerlog WHERE trainer_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['trainer_password'])) {

                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['role'] = 'trainer';
                $_SESSION['trainer_id'] = $row['trainer_id'];
                $_SESSION['trainer_email'] = $row['trainer_email'];
                $_SESSION['trainer_name'] = $row['trainer_name'];

                header("Location: trainerman.php");
                exit;

            } else {
                $error = "❌ Invalid password.";
            }
        } else {
            $error = "❌ No account found with that email.";
        }

    } else {

        // Registration
        $name     = trim($_POST['trainer_name']);
        $email    = trim($_POST['trainer_email']);
        $phone    = trim($_POST['trainer_phone']);
        $password = trim($_POST['trainer_password']);
        $hashed   = password_hash($password, PASSWORD_BCRYPT);

        // ✅ phone validation
        // require 10 digits and first digit 6-9 (India-style mobile numbers)
        if (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
            $error = "❌ Phone must be 10 digits and start with 6,7,8 or 9.";
        }

        if (!$error) {
            // check email exists
            $check = $conn->prepare("SELECT trainer_id FROM trainerlog WHERE trainer_email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "❌ Email already registered.";
            }
        }

        // ✅ certificate upload
        $certificate_file = NULL;

        if (!$error && !empty($_FILES['trainer_certificate']['name'])) {

            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['trainer_certificate']['name'];
            $tmpname  = $_FILES['trainer_certificate']['tmp_name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "❌ Only PDF, JPG, JPEG, PNG allowed.";
            } else {
                $new_name = time() . "_" . rand(1000, 9999) . "." . $ext;

                if (!file_exists("uploads/certificates")) {
                    mkdir("uploads/certificates", 0777, true);
                }

                move_uploaded_file($tmpname, "uploads/certificates/" . $new_name);
                $certificate_file = $new_name;
            }
        }

        if (!$error) {

            $stmt = $conn->prepare("INSERT INTO trainerlog (trainer_name, trainer_email, trainer_phone, trainer_password, certificate_file) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $hashed, $certificate_file);

            if ($stmt->execute()) {

                session_regenerate_id(true);
                $_SESSION['loggedin']      = true;
                $_SESSION['role']          = 'trainer';
                $_SESSION['trainer_id']    = $stmt->insert_id;
                $_SESSION['trainer_email'] = $email;
                $_SESSION['trainer_name']  = $name;

                $success = "✅ Registration successful! Welcome, $name.";
                // header("Location: trainerman.php");
                // exit;

            } else {
                $error = "❌ Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $mode === 'login' ? 'Trainer Login' : 'Trainer Register'; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- small toast styles -->
  <style>
    .toast {
      min-width: 220px;
      max-width: 320px;
      padding: 12px 14px;
      border-radius: 10px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.45);
      display: flex;
      gap: 10px;
      align-items: center;
      color: #fff;
      transform: translateY(-8px);
      opacity: 0;
      transition: transform .28s ease, opacity .28s ease;
    }
    .toast.show { transform: translateY(0); opacity: 1; }
    .toast.success { background: linear-gradient(90deg,#10B981,#059669); }
    .toast.error { background: linear-gradient(90deg,#EF4444,#DC2626); }
    .toast .icon { font-size: 18px; width: 22px; text-align: center; opacity: .95;}
    .toast-container { position: fixed; right: 16px; top: 18px; z-index: 60; display:flex; flex-direction:column; gap:10px; align-items:flex-end; }
  </style>
</head>

<body class="bg-gray-900 text-white flex items-center justify-center h-screen">

<div class="bg-gray-800 p-8 rounded-xl w-full max-w-md shadow-lg">
  <h2 class="text-4xl px-4 py-2 md:text-5xl font-extrabold bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent animate-pulse shadow-lg mb-6 text-center">
    <?= $mode === 'login' ? 'Trainer Login' : 'Trainer Register'; ?>
  </h2>

  <?php if ($error): ?>
    <p class="mb-4 text-red-400 hidden" id="serverError"><?= htmlspecialchars($error); ?></p>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <p class="mb-4 text-green-400 hidden" id="serverSuccess"><?= htmlspecialchars($success); ?></p>
  <?php endif; ?>

  <?php if ($mode === 'login'): ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="trainer_email" required class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Password</label>
        <div class="relative">
          <input type="password" name="trainer_password" id="login_password" required 
                 class="w-full px-4 py-2 pr-10 rounded-lg bg-gray-700 border border-gray-600 text-white">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer toggle-password" data-target="login_password">👁</span>
        </div>
      </div>

      <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white px-4 py-2 rounded-lg hover:from-orange-600 hover:to-red-600 transition-all">
        Login
      </button>
    </form>

    <p class="mt-4 text-gray-400 text-sm text-center">
      Don’t have an account? <a href="?action=register" class="text-orange-400 hover:underline">Register</a>
    </p>

  <?php else: ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4" id="registerForm">

      <div>
        <label class="block text-sm font-medium mb-1">Full Name</label>
        <input type="text" name="trainer_name" required class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="trainer_email" required class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Phone (10 digits)</label>
        <!-- pattern enforces first digit 6-9 and total 10 digits; inputmode helps mobile keyboards -->
        <input type="text" name="trainer_phone" maxlength="10" required
               pattern="[6-9][0-9]{9}" title="Enter 10 digits, starting with 6,7,8 or 9"
               inputmode="numeric"
               class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Password</label>
        <div class="relative">
          <input type="password" name="trainer_password" id="register_password" required 
                 class="w-full px-4 py-2 pr-10 rounded-lg bg-gray-700 border border-gray-600 text-white">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer toggle-password" data-target="register_password">👁</span>
        </div>
      </div>

      <!-- ✅ Certificate Upload -->
      <div>
        <label class="block text-sm font-medium mb-1">Certificate (PDF/JPG/PNG)</label>
        <input type="file" name="trainer_certificate" accept=".pdf,.jpg,.jpeg,.png"
               class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white">
      </div>

      <button type="submit" id="registerBtn" class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white px-4 py-2 rounded-lg hover:from-orange-600 hover:to-red-600 transition-all">
        Register
      </button>

    </form>

    <p class="mt-4 text-gray-400 text-sm text-center">
      Already have an account? <a href="?action=login" class="text-orange-400 hover:underline">Login</a>
    </p>

  <?php endif; ?>

</div>

<!-- Toast container -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<script>
document.querySelectorAll('.toggle-password').forEach(function(el) {
  el.addEventListener('click', function() {
    const input = document.getElementById(el.getAttribute('data-target'));
    if (input.type === "password") {
      input.type = "text";
      el.textContent = "";
    } else {
      input.type = "password";
      el.textContent = "👁";
    }
  });
});

// Toast helper
function showToast(type, message, timeout = 4500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'toast ' + (type === 'success' ? 'success' : 'error') + ' shadow-lg';
  toast.innerHTML = `<div class="icon">${type === 'success' ? '✓' : '⚠'}</div>
                     <div class="flex-1 text-sm leading-snug">${message}</div>
                     <button aria-label="Close" style="margin-left:8px;opacity:.9;background:transparent;border:none;color:rgba(255,255,255,.95);font-weight:700;cursor:pointer">×</button>`;

  const btn = toast.querySelector('button');
  btn.addEventListener('click', () => {
    container.removeChild(toast);
  });

  container.appendChild(toast);
  // force reflow then show
  requestAnimationFrame(() => toast.classList.add('show'));

  // hide after timeout
  setTimeout(() => {
    if (toast.parentNode) {
      toast.classList.remove('show');
      setTimeout(() => { if (toast.parentNode) container.removeChild(toast); }, 300);
    }
  }, timeout);
}

// show server-side message if present
document.addEventListener('DOMContentLoaded', () => {
  const srvErr = document.getElementById('serverError');
  const srvSuc = document.getElementById('serverSuccess');
  if (srvErr && srvErr.textContent.trim()) {
    showToast('error', srvErr.textContent.trim());
    // also make visible for accessibility if needed
    srvErr.classList.remove('hidden');
  } else if (srvSuc && srvSuc.textContent.trim()) {
    showToast('success', srvSuc.textContent.trim());
    srvSuc.classList.remove('hidden');
  }
});

// Client-side registration phone validation (extra UX)
(function(){
  const form = document.getElementById('registerForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    const phoneInput = form.querySelector('input[name="trainer_phone"]');
    const pattern = /^[6-9][0-9]{9}$/;
    if (!pattern.test(phoneInput.value.trim())) {
      e.preventDefault();
      showToast('error', "❌ Phone must be 10 digits and start with 6,7,8 or 9.");
      phoneInput.focus();
      return false;
    }
    return true;
  });
})();
</script>

</body>
</html>