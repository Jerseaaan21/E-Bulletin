<?php
// CEIT_Modules/Announcements/delete_announcement.php
session_start();
include "../../db.php";

// Check if user is Lead MIS Officer
if (!isset($_SESSION['user_info']) || $_SESSION['user_info']['role'] !== 'LEAD_MIS' || $_SESSION['user_info']['dept_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

// Get department acronym from database
$dept_id = $_SESSION['user_info']['dept_id'];
$dept_acronym = 'default'; // fallback

// Query the departments table to get the acronym
$query = "SELECT acronym FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dept_acronym = $row['acronym'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Get the file path to delete it
    $query = "SELECT file_path FROM main_post WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $dbFilePath = $row['file_path'];

        // Build the full file path with dynamic department folder
        $filePath = "../../uploads/{$dept_acronym}/Announcement/" . $dbFilePath;

        // Delete the file if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete the record from the database
        $query = "DELETE FROM main_post WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $userId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
