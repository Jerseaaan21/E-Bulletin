<?php
// CEIT_Modules/Memo/archive_memo.php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Check if user is Lead MIS Officer
if (!isset($_SESSION['user_info']) || $_SESSION['user_info']['role'] !== 'LEAD_MIS' || $_SESSION['user_info']['dept_id'] != 1) {
    http_response_code(403);
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

// Create archive directory if it doesn't exist
$archiveDir = "../../uploads/{$dept_acronym}/Memo/archive/";
if (!file_exists($archiveDir)) {
    if (!mkdir($archiveDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create archive directory']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Get the memo details before archiving
    $query = "SELECT * FROM main_post WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Copy file to archive directory if it exists
        $sourceFile = "../../uploads/{$dept_acronym}/Memo/" . $row['file_path'];
        $archiveFile = $archiveDir . $row['file_path'];

        if (file_exists($sourceFile)) {
            if (!copy($sourceFile, $archiveFile)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to copy file to archive']);
                exit;
            }

            // Delete the original file after successful copy
            if (!unlink($sourceFile)) {
                // Log error but continue with database update
                error_log("Failed to delete original file: " . $sourceFile);
            }
        }

        // Update the status to 'archived' and prepend 'archive/' to the file_path in the database
        $query = "UPDATE main_post SET status = 'archived', file_path = CONCAT('archive/', file_path) WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $userId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Memo archived successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error archiving memo: ' . $conn->error]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Memo not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
