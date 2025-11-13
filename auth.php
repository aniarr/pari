<?php 
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Determine action
$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

// Show brief message after redirect from registration
if ($action === 'login' && isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Registration successful. Please log in.';
}

// Handle form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token mismatch.";
    } else {
        if ($action === 'register') {
            // Registration
            $name = trim($_POST['name']);
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $phone = trim($_POST['phone']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirmpassword'];

            // Validations
            if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
                $error = "All fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
                $error = "Phone must be 10 digits and start with 6,7,8 or 9.";
            } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/', $password)) {
                $error = "Password must be at least 8 characters and contain at least one uppercase letter, one number, and one special character.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                $stmt = $conn->prepare("SELECT email FROM register WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $error = "An account with this email already exists.";
                } else {
                    // Ensure phone number is unique (one account per phone)
                    $phoneCheck = $conn->prepare("SELECT id FROM register WHERE phone = ?");
                    $phoneCheck->bind_param("s", $phone);
                    $phoneCheck->execute();
                    $phoneCheck->store_result();
                    if ($phoneCheck->num_rows > 0) {
                        $error = "An account with this phone number already exists.";
                        $phoneCheck->close();
                    } else {
                        $phoneCheck->close();
                         $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                         $stmt_insert = $conn->prepare("INSERT INTO register (name, email, phone, password) VALUES (?, ?, ?, ?)");
                         $stmt_insert->bind_param("ssss", $name, $email, $phone, $hashed_password);
 
                         if ($stmt_insert->execute()) {
                             $stmt_insert->close();
                             // Redirect immediately to login page with a flag so the login form can show a message if desired
                             header("Location: auth.php?action=login&registered=1");
                             exit;
                         } else {
                             $error = "Something went wrong. Please try again.";
                         }
                    }
                 }
                 $stmt->close();
             }
        } elseif ($action === 'login') {
            // Login
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'];

            if (empty($email) || empty($password)) {
                $error = "All fields are required.";
            } else {
                $stmt = $conn->prepare("SELECT id, name, password FROM register WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($id, $name, $hashed_password);
                    $stmt->fetch();

                    if (password_verify($password, $hashed_password)) {
                        // Set session
                        session_regenerate_id(true);
                        $_SESSION['loggedin'] = true;
                        $_SESSION['user_id'] = $id;
                        $_SESSION['email'] = $email;
                        $_SESSION['name'] = $name;

                        // Refresh CSRF
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // Redirect all users to home
                        header("Location: home.php");
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
}
$conn->close();
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RawFit - <?php echo ucfirst($action); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=M+PLUS+Rounded+1c:wght@700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .animate-gradient {
            background: linear-gradient(45deg, #1A1F2E, #2D3748, #1A1F2E, #4A5568);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
        }
    </style>
</head>
<body class="animate-gradient min-h-screen flex items-center justify-center p-10 font-['Inter']">
    <section id="auth" class="w-full max-w-md">
        <div class="bg-gray-800 rounded-2xl p-10 shadow-2xl">
            <div class="font-['M_PLUS_Rounded_1c'] text-3xl text-center mb-2 p-2 font-bold text-orange-500"><?php echo strtoupper($action); ?></div>
            <div class="text-sm text-center mb-8 text-gray-500">Please enter your details</div>
             <a href="index.php" 
                            class="fixed bottom-6 left-6 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white p-4 rounded-full shadow-lg transition transform hover:scale-105 z-50"
                            title="Back to Home">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5.25a.75.75 0 01-.75-.75V15a.75.75 0 00-.75-.75H9.75A.75.75 0 009 15v6.25a.75.75 0 01-.75.75H3a1 1 0 01-1-1V9.75z" />
                            </svg>
                            </a>

            <?php if ($error): ?>
                <p class="text-red-400 text-center bg-red-500/10 p-2 rounded-lg mb-5"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="text-green-400 text-center bg-green-500/10 p-2 rounded-lg mb-5"><?php echo $success; ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <?php if ($action === 'register'): ?>
                    <label for="name" class="block mb-1 text-sm text-white">Name</label>
                    <input type="text" name="name" placeholder="John Doe" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="email" class="block mb-1 text-sm text-white">Email</label>
                    <input type="email" name="email" placeholder="example@gmail.com" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="phone" class="block mb-1 text-sm text-white">Phone</label>
                    <!-- pattern enforces first digit 6-9 and total 10 digits; title explains rule to user -->
                    <input type="tel" name="phone" placeholder="6XXXXXXXXX" pattern="[6-9][0-9]{9}" maxlength="10" title="Enter 10 digits, starting with 6,7,8 or 9" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="password" class="block mb-1 text-sm text-white">Password</label>
                    <div class="relative w-full">
                        <input type="password" id="password" name="password" placeholder="********" required class="w-full p-2 pr-10 mb-5 rounded-lg bg-gray-700 text-white border-none">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-400 text-lg hover:text-white select-none" onclick="togglePassword('password')">👁</span>
                    </div>

                    <label for="confirmpassword" class="block mb-1 text-sm text-white">Confirm Password</label>
                    <div class="relative w-full">
                        <input type="password" id="confirmpassword" name="confirmpassword" placeholder="********" required class="w-full p-2 pr-10 mb-5 rounded-lg bg-gray-700 text-white border-none">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-400 text-lg hover:text-white select-none" onclick="togglePassword('confirmpassword')">👁</span>
                    </div>

                <?php else: ?>
                    <label for="email" class="block mb-1 text-sm text-white">Email</label>
                    <input type="email" name="email" placeholder="example@gmail.com" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="password" class="block mb-1 text-sm text-white">Password</label>
                    <div class="relative w-full">
                        <input type="password" id="password" name="password" placeholder="********" required class="w-full p-2 pr-10 mb-5 rounded-lg bg-gray-700 text-white border-none">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-400 text-lg hover:text-white select-none" onclick="togglePassword('password')">👁</span>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full bg-orange-500 text-white font-bold p-3 rounded-lg hover:bg-orange-400 transition duration-300"><?php echo ucfirst($action); ?></button>
            </form>

            <div class="text-center mt-5 text-sm text-gray-400">
                <?php if ($action === 'login'): ?>
                    Don't have an account? <a href="auth.php?action=register" class="text-orange-500 font-bold no-underline">Sign up</a>
                <?php else: ?>
                    Already have an account? <a href="auth.php?action=login" class="text-orange-500 font-bold no-underline">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === "password" ? "text" : "password";
        }
    </script>
</body>
</html>