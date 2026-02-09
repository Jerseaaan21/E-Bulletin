<?php
// CEIT_Modules/Announcements/archive_announcement.php
session_start();
include "../../db.php";

// Check if user is Lead MIS Officer
if (!isset($_SESSION['user_info']) || $_SESSION['user_info']['role'] !== 'LEAD_MIS' || $_SESSION['user_info']['dept_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Update the record in the database
    $query = "UPDATE main_post SET status = 'archived' WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Announcement archived successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error archiving announcement: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
