<?php
session_start();
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$action = $_GET['action'] ?? 'login';  // default = login
$success = $error = "";

// --------------------------
//  PROCESS FORM SUBMISSION
// --------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'register') {
        // ---------- REGISTER ----------
        $name = trim($_POST['owner_name'] ?? '');
        $email = trim($_POST['owner_email'] ?? '');
        $password = $_POST['owner_password'] ?? '';
        $confirm = $_POST['owner_confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
            $error = "All fields are required.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            // Check duplicate email
            $check = $conn->prepare("SELECT owner_id FROM ownerlog WHERE owner_email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "This email is already registered.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO ownerlog (owner_name, owner_email, owner_password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $hashed);
                if ($stmt->execute()) {
                    $success = "Registration successful! <a href='?action=login' class='text-orange-400 underline'>Login now</a>";
                } else {
                    $error = "Registration failed. Try again.";
                }
                $stmt->close();
            }
            $check->close();
        }

    } elseif ($action === 'login') {
        // ---------- LOGIN ----------
        $email = trim($_POST['owner_email'] ?? '');
        $password = $_POST['owner_password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            $stmt = $conn->prepare("SELECT owner_id, owner_name, owner_email, owner_password FROM ownerlog WHERE owner_email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['owner_password'])) {
                    session_regenerate_id(true);
                    $_SESSION['loggedin'] = true;
                    $_SESSION['role'] = 'owner';
                    $_SESSION['owner_id'] = $row['owner_id'];
                    $_SESSION['owner_name'] = $row['owner_name'];
                    $_SESSION['owner_email'] = $row['owner_email'];

                    header("Location: owner_dashboard.php");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= $action === 'register' ? 'Register' : 'Login' ?> - Gym Owner</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">

  <div class="w-full max-w-md bg-gray-800 rounded-xl p-8 shadow-lg">
    <h1 class="text-center text-3xl font-extrabold mb-6 bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">
      <?= $action === 'register' ? 'Register as Gym Owner' : 'Owner Login' ?>
    </h1>

    <!-- Error / Success Messages -->
    <?php if ($error): ?>
      <div class="mb-4 p-3 bg-red-900 border border-red-700 text-red-200 rounded-lg text-center">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-4 p-3 bg-green-900 border border-green-700 text-green-200 rounded-lg text-center">
        <?= $success ?>
      </div>
    <?php endif; ?>

    <!-- ========== FORM ========== -->
    <form method="POST" novalidate>
      <?php if ($action === 'register'): ?>
        <!-- REGISTER FIELDS -->
        <div class="mb-4">
          <label for="owner_name" class="block text-sm font-medium mb-1">Full Name</label>
          <input
            id="owner_name"
            name="owner_name"
            type="text"
            required
            class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white focus:outline-none focus:ring-2 focus:ring-orange-400"
            placeholder="John Doe"
            value="<?= isset($_POST['owner_name']) ? htmlspecialchars($_POST['owner_name']) : '' ?>"
          />
        </div>
      <?php endif; ?>

      <div class="mb-4">
        <label for="owner_email" class="block text-sm font-medium mb-1">Email</label>
        <input
          id="owner_email"
          name="owner_email"
          type="email"
          required
          class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white focus:outline-none focus:ring-2 focus:ring-orange-400"
          placeholder="owner@gym.com"
          value="<?= isset($_POST['owner_email']) ? htmlspecialchars($_POST['owner_email']) : '' ?>"
        />
      </div>

      <div class="mb-4">
        <label for="owner_password" class="block text-sm font-medium mb-1">Password</label>
        <div class="relative">
          <input
            id="owner_password"
            name="owner_password"
            type="password"
            required
            <?= $action === 'register' ? 'minlength="6"' : '' ?>
            class="w-full pr-12 px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white focus:outline-none focus:ring-2 focus:ring-orange-400"
            placeholder="••••••••"
          />
          <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center px-3">
            <svg class="eye w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <svg class="eye-off w-6 h-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.223-3.375M6.1 6.1A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.95 9.95 0 01-1.077 2.22M3 3l18 18" />
            </svg>
          </button>
        </div>
      </div>

      <?php if ($action === 'register'): ?>
        <div class="mb-6">
          <label for="owner_confirm_password" class="block text-sm font-medium mb-1">Confirm Password</label>
          <input
            id="owner_confirm_password"
            name="owner_confirm_password"
            type="password"
            required
            minlength="6"
            class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white focus:outline-none focus:ring-2 focus:ring-orange-400"
            placeholder="••••••••"
          />
        </div>
      <?php endif; ?>

      <button
        type="submit"
        class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white px-4 py-2 rounded-lg hover:from-orange-600 hover:to-red-600 transition-all font-semibold"
      >
        <?= $action === 'register' ? 'Create Account' : 'Login' ?>
      </button>
    </form>

    <!-- Toggle Link -->
    <p class="mt-6 text-center text-sm">
      <?php if ($action === 'register'): ?>
        Already have an account? <a href="?action=login" class="text-orange-400 hover:underline font-medium">Login here</a>
      <?php else: ?>
        Don't have an account? <a href="?action=register" class="text-orange-400 hover:underline font-medium">Register here</a>
      <?php endif; ?>
    </p>
  </div>

  <!-- Password Toggle Script -->
  <script>
    document.getElementById('togglePassword')?.addEventListener('click', function () {
      const pwd = document.getElementById('owner_password');
      const eye = this.querySelector('.eye');
      const eyeOff = this.querySelector('.eye-off');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        eye.classList.add('hidden');
        eyeOff.classList.remove('hidden');
      } else {
        pwd.type = 'password';
        eye.classList.remove('hidden');
        eyeOff.classList.add('hidden');
      }
      pwd.focus();
    });
  </script>
</body>
</html>