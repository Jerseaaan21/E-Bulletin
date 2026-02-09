<?php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Get department acronym from session
 $dept_acronym = $_SESSION['dept_acronym'] ?? 'CEIT';

// Create dynamic upload directory
 $uploadDir = "../../uploads/{$dept_acronym}/Institutional_Development/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document ID is required']);
        exit;
    }

    $id = $_POST['id'];
    $description = $_POST['description'];
    $category = $_POST['category'] ?? 'default';

    // Get the current document to extract existing content
    $query = "SELECT file_path, content FROM main_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    $row = $result->fetch_assoc();
    $currentFilePath = $row['file_path'];
    
    // Parse existing content or create new content array
    $contentData = [];
    if (!empty($row['content']) && json_decode($row['content'], true)) {
        $contentData = json_decode($row['content'], true);
    }
    
    // Update category in content
    $contentData['category'] = $category;
    $newContent = json_encode($contentData);

    // Check if a new file was uploaded
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $fileType = $_FILES['file']['type'];

        // Check file size (limit to 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            http_response_code(413);
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
            exit;
        }

        // Check file type
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif'
        ];

        if (!in_array($fileType, $allowedTypes)) {
            http_response_code(415);
            echo json_encode(['success' => false, 'message' => 'File type not allowed']);
            exit;
        }

        // Delete the old file if it exists
        $oldFilePath = $uploadDir . $currentFilePath;
        if (file_exists($oldFilePath)) {
            if (!unlink($oldFilePath)) {
                error_log("Failed to delete old file: " . $oldFilePath);
            }
        }

        // Generate new filename with timestamp
        $now = new DateTime();
        $day = $now->format('d');
        $month = $now->format('m');
        $year = $now->format('Y');
        $hours = $now->format('H');
        $minutes = $now->format('i');
        $seconds = $now->format('s');
        $random = mt_rand(1000, 9999);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFilename = "document_{$day}_{$month}_{$year}_{$hours}_{$minutes}_{$seconds}_{$random}.{$fileExtension}";

        // Move the file to the uploads directory
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Update the database with new file and content
            $query = "UPDATE main_post SET description = ?, file_path = ?, content = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $description, $newFilename, $newContent, $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Document updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating document: ' . $conn->error]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error uploading file: ' . error_get_last()['message']]);
            exit;
        }
    } else {
        // No new file, just update the description and content
        $query = "UPDATE main_post SET description = ?, content = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $description, $newContent, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Document updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error updating document: ' . $conn->error]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>