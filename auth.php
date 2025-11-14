<?php
session_start();

// ========================================
// 1. DATABASE CONNECTION
// ========================================
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ========================================
// 2. AUTO-CREATE ALL 22 TABLES (ORDERED FOR FK SAFETY)
// ========================================
$schemas = [

    // === FIRST: Independent Tables (no FK) ===
    // 1
    "CREATE TABLE IF NOT EXISTS register (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(10) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 2
    "CREATE TABLE IF NOT EXISTS ownerlog (
        owner_id INT AUTO_INCREMENT PRIMARY KEY,
        owner_name VARCHAR(255) NOT NULL,
        owner_email VARCHAR(255) NOT NULL UNIQUE,
        owner_phone VARCHAR(20) NULL,
        owner_password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 3
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 4
    "CREATE TABLE IF NOT EXISTS trainer_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(25) NOT NULL UNIQUE,
        phone VARCHAR(10) NOT NULL,
        address VARCHAR(255) NULL,
        age INT(10) NOT NULL,
        dob DATE NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        location VARCHAR(255) NOT NULL,
        gender VARCHAR(50) NOT NULL,
        interests TEXT NULL,
        website VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        trainer_image TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 5
    "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        file_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 6
    // === SECOND: Tables with FK to above ===
    "CREATE TABLE IF NOT EXISTS gyms (
        gym_id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        gym_name VARCHAR(255) NOT NULL,
        location VARCHAR(255) NULL,
        lat DECIMAL(10,8) NULL,
        lng DECIMAL(11,8) NULL,
        phone VARCHAR(20) NULL,
        timings VARCHAR(255) NULL,
        facilities TEXT NULL,
        gym_image VARCHAR(255) NULL,
        gym_address VARCHAR(255) NULL,
        gym_city VARCHAR(100) NULL,
        gym_state VARCHAR(100) NULL,
        gym_zip VARCHAR(20) NULL,
        gym_phone VARCHAR(20) NULL,
        gym_email VARCHAR(100) NULL,
        gym_description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        year_started YEAR NULL,
        experience_years INT NULL,
        num_trainers INT NULL,
        capacity INT NULL,
        registrations INT DEFAULT 0,
        display_before_register TINYINT(4) NOT NULL,
        is_admin TINYINT(4) NULL,
        status TINYINT(1) DEFAULT 0,
        CONSTRAINT fk_gym_owner FOREIGN KEY (owner_id) REFERENCES ownerlog(owner_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 7
    "CREATE TABLE IF NOT EXISTS trainer_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trainer_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        category VARCHAR(255) NULL,
        duration INT(11) NULL,
        doc_path VARCHAR(100) NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        image_path VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_message TEXT NULL,
        approved_at DATETIME NULL,
        CONSTRAINT fk_course_trainer FOREIGN KEY (trainer_id) REFERENCES trainer_details(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 8
    "CREATE TABLE IF NOT EXISTS gym_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_gimg_gym FOREIGN KEY (gym_id) REFERENCES gyms(gym_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 9
    "CREATE TABLE IF NOT EXISTS gym_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        user_id INT NOT NULL,
        user_name VARCHAR(255) NULL,
        rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_rev_gym FOREIGN KEY (gym_id) REFERENCES gyms(gym_id) ON DELETE CASCADE,
        CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 10
    "CREATE TABLE IF NOT EXISTS course_downloads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        user_id INT NOT NULL,
        downloaded_at DATETIME NOT NULL,
        CONSTRAINT fk_dl_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        CONSTRAINT fk_dl_user FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 11
    "CREATE TABLE IF NOT EXISTS food_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        food_name VARCHAR(255) NOT NULL,
        calories INT NOT NULL,
        protein INT NOT NULL,
        carbs INT NOT NULL,
        fats INT NOT NULL,
        log_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_food_user FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 12
    "CREATE TABLE IF NOT EXISTS nutrition_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        age INT NOT NULL,
        gender VARCHAR(20) NOT NULL,
        weight DECIMAL(5,2) NOT NULL,
        height DECIMAL(5,2) NOT NULL,
        activity_level VARCHAR(50) NULL,
        goal VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_nut_user FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 13
    "CREATE TABLE IF NOT EXISTS gym_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        user_id INT NOT NULL,
        sender_type ENUM('user','owner') NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        CONSTRAINT fk_msg_gym FOREIGN KEY (gym_id) REFERENCES gyms(gym_id) ON DELETE CASCADE,
        CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 14
    "CREATE TABLE IF NOT EXISTS trainer_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        trainer_id INT NOT NULL,
        course_id INT NOT NULL,
        message TEXT NOT NULL,
        sender_type ENUM('user','trainer') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        CONSTRAINT fk_tmsg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_tmsg_trainer FOREIGN KEY (trainer_id) REFERENCES trainer_details(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 15
    "CREATE TABLE IF NOT EXISTS reel_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reel_id INT NOT NULL,
        user_id INT NOT NULL,
        type ENUM('like','dislike') DEFAULT 'like',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_reel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 16
    "CREATE TABLE IF NOT EXISTS users_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        age INT(11) NULL,
        dob DATE NULL,
        blood_group VARCHAR(10) NULL,
        location VARCHAR(255) NULL,
        gender VARCHAR(50) NULL,
        interests TEXT NULL,
        website VARCHAR(255) NULL,
        social_links TEXT NULL,
        privacy_setting ENUM('Public','Friends Only','Private') DEFAULT 'Public',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        profile_image VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 17
    "CREATE TABLE IF NOT EXISTS split_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        split_id INT NOT NULL,
        day_name ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
        day_number TINYINT(3) UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 18
    "CREATE TABLE IF NOT EXISTS trainerlog (
        trainer_id INT AUTO_INCREMENT PRIMARY KEY,
        trainer_name VARCHAR(100) NOT NULL,
        trainer_email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NULL,
        trainer_password VARCHAR(255) NOT NULL,
        certificate_file VARCHAR(255) NULL,
        trainer_phone VARCHAR(20) NULL,
        INDEX idx_email (trainer_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 19
    "CREATE TABLE IF NOT EXISTS reel_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reel_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 20
    // === NEW: admin table ===
    "CREATE TABLE IF NOT EXISTS admin (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // 21
    "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Execute all in order
foreach ($schemas as $sql) {
    if ($conn->query($sql) !== TRUE) {
        error_log("Table creation failed: " . $conn->error . " | SQL: " . substr($sql, 0, 100));
    }
}

// Insert default test user (only once)
$defaultEmail = 'test@example.com';
$stmt = $conn->prepare("SELECT id FROM register WHERE email = ?");
$stmt->bind_param("s", $defaultEmail);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $defaultName = 'Test User';
    $defaultPhone = '9876543210';
    $defaultPass = password_hash('Password@123', PASSWORD_DEFAULT);

    $insert = $conn->prepare("INSERT INTO register (name, email, phone, password) VALUES (?, ?, ?, ?)");
    $insert->bind_param("ssss", $defaultName, $defaultEmail, $defaultPhone, $defaultPass);
    $insert->execute();
    $insert->close();
}
$stmt->close();

// ========================================
// 3. CSRF TOKEN
// ========================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ========================================
// 4. ACTION & MESSAGES
// ========================================
$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

if ($action === 'login' && isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Registration successful. Please log in.';
}

// ========================================
// 5. HANDLE FORM SUBMISSION
// ========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token mismatch.";
    } else {
        if ($action === 'register') {
            $name = trim($_POST['name']);
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $phone = trim($_POST['phone']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirmpassword'];

            if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
                $error = "All fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
                $error = "Phone must be 10 digits and start with 6-9.";
            } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/', $password)) {
                $error = "Password: 8+ chars, 1 uppercase, 1 number, 1 special (!@#$%^&*).";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                $stmt = $conn->prepare("SELECT email FROM register WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error = "Email already registered.";
                } else {
                    $stmt->close();
                    $phoneCheck = $conn->prepare("SELECT id FROM register WHERE phone = ?");
                    $phoneCheck->bind_param("s", $phone);
                    $phoneCheck->execute();
                    $phoneCheck->store_result();
                    if ($phoneCheck->num_rows > 0) {
                        $error = "Phone number already registered.";
                        $phoneCheck->close();
                    } else {
                        $phoneCheck->close();
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_insert = $conn->prepare("INSERT INTO register (name, email, phone, password) VALUES (?, ?, ?, ?)");
                        $stmt_insert->bind_param("ssss", $name, $email, $phone, $hashed_password);
                        if ($stmt_insert->execute()) {
                            $stmt_insert->close();
                            header("Location: auth.php?action=login&registered=1");
                            exit;
                        } else {
                            $error = "Registration failed. Try again.";
                        }
                    }
                }
            }
        } elseif ($action === 'login') {
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
                        session_regenerate_id(true);
                        $_SESSION['loggedin'] = true;
                        $_SESSION['user_id'] = $id;
                        $_SESSION['email'] = $email;
                        $_SESSION['name'] = $name;
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
            <div class="font-['M_PLUS_Rounded_1c'] text-3xl text-center mb-2 p-2 font-bold text-orange-500">
                <?php echo strtoupper($action); ?>
            </div>
            <div class="text-sm text-center mb-8 text-gray-500">Please enter your details</div>

            <a href="index.php"
               class="fixed bottom-6 left-6 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white p-4 rounded-full shadow-lg transition transform hover:scale-105 z-50"
               title="Back to Home">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5.25a.75.75 0 01-.75-.75V15a.75.75 0 00-.75-.75H9.75A.75.75 0 009 15v6.25a.75.75 0 01-.75.75H3a1 1 0 01-1-1V9.75z" />
                </svg>
            </a>

            <?php if ($error): ?>
                <p class="text-red-400 text-center bg-red-500/10 p-2 rounded-lg mb-5"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="text-green-400 text-center bg-green-500/10 p-2 rounded-lg mb-5"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <?php if ($action === 'register'): ?>
                    <label for="name" class="block mb-1 text-sm text-white">Name</label>
                    <input type="text" name="name" placeholder="John Doe" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="email" class="block mb-1 text-sm text-white">Email</label>
                    <input type="email" name="email" placeholder="example@gmail.com" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="phone" class="block mb-1 text-sm text-white">Phone</label>
                    <input type="tel" name="phone" placeholder="6XXXXXXXXX" pattern="[6-9][0-9]{9}" maxlength="10" title="10 digits, start with 6-9" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="password" class="block mb-1 text-sm text-white">Password</label>
                    <div class="relative w-full">
                        <input type="password" id="password" name="password" placeholder="********" required class="w-full p-2 pr-10 mb-5 rounded-lg bg-gray-700 text-white border-none">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-400 text-lg hover:text-white select-none" onclick="togglePassword('password')">Eye</span>
                    </div>

                    <label for="confirmpassword" class="block mb-1 text-sm text-white">Confirm Password</label>
                    <div class="relative w-full">
                        <input type="password" id="confirmpassword" name="confirmpassword" placeholder="********" required class="w-full p-2 pr-10 mb-5 rounded-lg bg-gray-700 text-white border-none">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-400 text-lg hover:text-white select-none" onclick="togglePassword('confirmpassword')">Eye</span>
                    </div>
                <?php else: ?>
                    <label for="email" class="block mb-1 text-sm text-white">Email</label>
                    <input type="email" name="email" placeholder="example@gmail.com" required class="w-full p-2 mb-5 rounded-lg bg-gray-700 text-white border-none">

                    <label for="password" class="block mb-1 text-sm text-white">Password</label>
                    <div class="relative w-full">
                        <input type="password" id="password" name="password" placeholder="********" required class="w-full p-2 pr-10 mb-5 rounded-lg bg-gray-700 text-white border-none">
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-gray-400 text-lg hover:text-white select-none" onclick="togglePassword('password')">Eye</span>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full bg-orange-500 text-white font-bold p-3 rounded-lg hover:bg-orange-400 transition duration-300">
                    <?php echo ucfirst($action); ?>
                </button>
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