<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
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
        if (!preg_match("/^[0-9]{10}$/", $phone)) {
            $error = "❌ Phone number must be exactly 10 digits.";
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

                header("Location: trainerman.php");
                exit;

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
</head>

<body class="bg-gray-900 text-white flex items-center justify-center h-screen">

<div class="bg-gray-800 p-8 rounded-xl w-full max-w-md shadow-lg">
  <h2 class="text-4xl px-4 py-2 md:text-5xl font-extrabold bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent animate-pulse shadow-lg mb-6 text-center">
    <?= $mode === 'login' ? 'Trainer Login' : 'Trainer Register'; ?>
  </h2>

  <?php if ($error): ?>
    <p class="mb-4 text-red-400"><?= $error; ?></p>
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

    <form method="POST" enctype="multipart/form-data" class="space-y-4">

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
        <input type="text" name="trainer_phone" maxlength="10" required class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white">
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

      <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white px-4 py-2 rounded-lg hover:from-orange-600 hover:to-red-600 transition-all">
        Register
      </button>

    </form>

    <p class="mt-4 text-gray-400 text-sm text-center">
      Already have an account? <a href="?action=login" class="text-orange-400 hover:underline">Login</a>
    </p>

  <?php endif; ?>

</div>

<script>
document.querySelectorAll('.toggle-password').forEach(function(el) {
  el.addEventListener('click', function() {
    const input = document.getElementById(el.getAttribute('data-target'));
    if (input.type === "password") {
      input.type = "text";
      el.textContent = "🙈";
    } else {
      input.type = "password";
      el.textContent = "👁";
    }
  });
});
</script>

</body>
</html>
