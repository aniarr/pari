<?php
session_start();

// Verify user is logged in (gym owner or admin)
if (!isset($_SESSION['owner_id']) && !isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image_id = intval($_POST['image_id'] ?? 0);
    $gym_id = intval($_POST['gym_id'] ?? 0);
    
    if (!$image_id || !$gym_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }

    // Verify ownership (owner or admin)
    $isOwner = false;
    if (isset($_SESSION['owner_id'])) {
        $stmt = $conn->prepare("SELECT owner_id FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $isOwner = ($row['owner_id'] == $_SESSION['owner_id']);
        }
        $stmt->close();
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $isOwner = true; // Admin can delete any image
    }

    if (!$isOwner) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    // Get image filename
    $stmt = $conn->prepare("SELECT filename FROM gym_images WHERE id = ? AND gym_id = ?");
    $stmt->bind_param("ii", $image_id, $gym_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!($row = $result->fetch_assoc())) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Image not found']);
        $stmt->close();
        exit;
    }

    $filename = $row['filename'];
    $stmt->close();

    // Delete file from disk
    $filepath = __DIR__ . '/uploads/gyms/' . $filename;
    if (file_exists($filepath)) {
        @unlink($filepath);
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM gym_images WHERE id = ? AND gym_id = ?");
    $stmt->bind_param("ii", $image_id, $gym_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete from database']);
    }
    
    $stmt->close();
}

$conn->close();
exit;
?>
