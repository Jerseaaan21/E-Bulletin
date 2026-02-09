<?php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';

// Create archive directory if it doesn't exist
$archiveDir = "../../uploads/{$dept_acronym}/Announcement/archive/";
if (!file_exists($archiveDir)) {
    if (!mkdir($archiveDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create archive directory']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Get the announcement details before archiving
    $query = "SELECT * FROM department_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Copy file to archive directory if it exists
        $sourceFile = "../../uploads/{$dept_acronym}/Announcement/" . $row['file_path'];
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

        // Update the status to 'Archived' and prepend 'archive/' to the file_path in the database
        $query = "UPDATE department_post SET status = 'Archived', file_path = CONCAT('archive/', file_path) WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Announcement archived successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error archiving announcement: ' . $conn->error]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
