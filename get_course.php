<?php
// -----------------------------
// COURSE DOWNLOAD HANDLER
// -----------------------------

// Enable output buffering
ob_start();

// Set error reporting for debugging (turn on briefly to diagnose HTTP 500)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Convert PHP errors to exceptions so we can catch them and show a useful message
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: login.php?error=" . urlencode("You must be logged in to download this file"));
    exit();
}

// DB connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    ob_end_clean();
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    header("Location: user_booking.php?course_id=$course_id&error=" . urlencode("Database connection failed: " . $conn->connect_error));
    exit();
}

// Get course_id from URL
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
if ($course_id <= 0) {
    ob_end_clean();
    header("Location: user_booking.php?error=" . urlencode("Invalid course ID"));
    $conn->close();
    exit();
}

// Fetch course file details
$sql = "SELECT id, title, doc_path FROM trainer_courses WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    ob_end_clean();
    header("Location: user_booking.php?course_id=$course_id&error=" . urlencode("Query preparation failed: " . $conn->error));
    $conn->close();
    exit();
}

$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $file_path = $row['doc_path'];
    $file_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $row['title']) . '.pdf'; // Sanitize filename

    // Log file path for debugging
    error_log("Attempting to download file: $file_path");

    // Check if file_path is not empty
    if (!empty($file_path)) {
        // Construct absolute path
        $base_dir = __DIR__ . '/uploads/';
        $file_path = $base_dir . basename($file_path); // Prevent directory traversal
        $absolute_path = realpath($file_path);
        error_log("Resolved absolute path: " . ($absolute_path !== false ? $absolute_path : "Invalid path"));

        // Check if file exists and is readable
        if ($absolute_path !== false && file_exists($absolute_path) && is_readable($absolute_path)) {
            // Attempt to log the download to the database (if the table exists). Always append to a file as a reliable audit fallback.
            if (!empty($_SESSION['user_id'])) {
                $uid = intval($_SESSION['user_id']);
                $dbLogged = false;
                try {
                    // Try to insert into course_downloads table. If the table doesn't exist or insert fails, we catch and continue.
                    $insSql = "INSERT INTO course_downloads (course_id, user_id, downloaded_at) VALUES (?, ?, NOW())";
                    if ($insStmt = $conn->prepare($insSql)) {
                        $insStmt->bind_param("ii", $course_id, $uid);
                        if ($insStmt->execute()) {
                            $dbLogged = true;
                        } else {
                            error_log("Failed to insert course_downloads: " . $insStmt->error);
                        }
                        $insStmt->close();
                    } else {
                        // Prepare failed (likely table missing) — log and continue
                        error_log("Prepare for insert into course_downloads failed: " . $conn->error);
                    }
                } catch (Throwable $e) {
                    // Any exception while attempting DB logging should not stop the download
                    error_log("Exception while logging download to DB: " . $e->getMessage());
                }

                // Always append to a local log file as an audit trail and fallback
                $logLine = date('Y-m-d H:i:s') . "\tcourse_id={$course_id}\tuser_id={$uid}\tfile={$file_name}\tdb_logged=" . ($dbLogged ? '1' : '0') . "\n";
                @file_put_contents(__DIR__ . '/downloads.log', $logLine, FILE_APPEND | LOCK_EX);
                error_log('Course downloaded: ' . trim($logLine));
            }

            // Clear output buffer
            ob_end_clean();

            // Set headers for file download
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($absolute_path));

            // Read the file
            readfile($absolute_path);
            $stmt->close();
            $conn->close();
            exit;
        } else {
            // File not found or not readable
            $error_msg = "Course file not found or inaccessible. Path: $file_path";
            if ($absolute_path === false) {
                $error_msg .= " (Invalid path)";
            } elseif (!file_exists($absolute_path)) {
                $error_msg .= " (File does not exist)";
            } elseif (!is_readable($absolute_path)) {
                $error_msg .= " (File is not readable)";
            }
            error_log($error_msg);
            ob_end_clean();
            header("Location: user_booking.php?course_id=$course_id&error=" . urlencode($error_msg));
        }
    } else {
        // File path is empty
        error_log("No file path specified for course ID: $course_id");
        ob_end_clean();
        header("Location: user_booking.php?course_id=$course_id&error=" . urlencode("No file path specified for this course"));
    }
} else {
    // Course not found in database
    error_log("No course found for course_id: $course_id");
    ob_end_clean();
    header("Location: user_booking.php?course_id=$course_id&error=" . urlencode("Course not found"));
}

// Clean up
$stmt->close();
$conn->close();
exit();
} catch (Throwable $e) {
    // Log exception and show a simple error to the browser (helpful during debugging)
    error_log('Unhandled exception in get_course.php: ' . $e->getMessage() . " in " . $e->getFile() . ':' . $e->getLine());
    // Clear any buffers and show a friendly message
    if (ob_get_length()) ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    echo '<h1>Server error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    // Restore previous handler
    restore_error_handler();
    exit();
}
?>