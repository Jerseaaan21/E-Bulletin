<?php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document ID is required']);
        exit;
    }
    
    $id = $_POST['id'];

    // Get document details
    $query = "SELECT * FROM department_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filename = $row['file_path'];
        
        // Define paths
        $baseDir = "../../uploads/{$dept_acronym}/Institutional_Development/";
        $archiveDir = $baseDir . "archive/";
        $sourceFile = $archiveDir . $filename;
        $destinationFile = $baseDir . $filename;

        // Check if file exists in archive
        if (file_exists($sourceFile)) {
            // Create destination directory if needed
            $destinationDir = dirname($destinationFile);
            if (!file_exists($destinationDir)) {
                if (!mkdir($destinationDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to create destination directory']);
                    exit;
                }
            }

            // Move file back from archive to main directory
            if (!rename($sourceFile, $destinationFile)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to restore file from archive']);
                exit;
            }
        } else {
            // Check if file is already in main directory (in case of failed previous operation)
            if (!file_exists($destinationFile)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File not found in archive or main directory']);
                exit;
            }
            // File is already in main directory, just update the database
        }

        // Update status to 'Approved'
        $query = "UPDATE department_post SET status = 'Approved' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Document restored successfully']);
        } else {
            // If database update fails, try to move file back to archive
            if (file_exists($destinationFile) && !file_exists($sourceFile)) {
                rename($destinationFile, $sourceFile);
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error restoring document: ' . $conn->error]);
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