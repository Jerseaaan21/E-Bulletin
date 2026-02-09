<?php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';

// Create archive directory if it doesn't exist
$baseDir = "../../uploads/{$dept_acronym}/Institutional_Development/";
$archiveDir = $baseDir . "archive/";
if (!file_exists($archiveDir)) {
    if (!mkdir($archiveDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create archive directory']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document ID is required']);
        exit;
    }
    
    $id = $_POST['id'];

    // Get document details before archiving
    $query = "SELECT * FROM department_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filename = $row['file_path'];
        
        // Define source and destination paths
        $sourceFile = $baseDir . $filename;
        $archiveFile = $archiveDir . $filename;

        // Create the directory structure in archive if needed
        $archiveSubDir = dirname($archiveFile);
        if (!file_exists($archiveSubDir)) {
            if (!mkdir($archiveSubDir, 0755, true)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create archive subdirectory']);
                exit;
            }
        }

        // Check if file exists in source location
        if (file_exists($sourceFile)) {
            // Move the file to archive directory
            if (!rename($sourceFile, $archiveFile)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to move file to archive']);
                exit;
            }
        } else {
            // Check if file is already in archive (in case of failed previous operation)
            if (!file_exists($archiveFile)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File not found in source or archive directory']);
                exit;
            }
            // File is already in archive, just update the database
        }

        // Update status to 'Archived' - DO NOT modify file_path in database
        $query = "UPDATE department_post SET status = 'Archived' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Document archived successfully']);
        } else {
            // If database update fails, try to move file back
            if (file_exists($archiveFile) && !file_exists($sourceFile)) {
                rename($archiveFile, $sourceFile);
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error archiving document: ' . $conn->error]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>