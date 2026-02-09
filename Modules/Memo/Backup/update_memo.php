<?php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';

// Create dynamic upload directory
$uploadDir = "../../uploads/{$dept_acronym}/Memo/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Memo ID is required']);
        exit;
    }

    $id = $_POST['id'];
    $description = $_POST['description'];

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

        // Get the current file path to delete it
        $query = "SELECT file_path FROM department_post WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $oldFilePath = $uploadDir . $row['file_path'];

            // Delete the old file if it exists
            if (file_exists($oldFilePath)) {
                if (!unlink($oldFilePath)) {
                    error_log("Failed to delete old file: " . $oldFilePath);
                }
            }
        }

        // Get the new filename (already generated in the JavaScript)
        $newFilename = $_FILES['file']['name'];

        // Move the file to the uploads directory
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Update the record in the database
            $query = "UPDATE department_post SET description = ?, file_path = ?, status = 'Pending' WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $description, $newFilename, $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Memo updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating memo: ' . $conn->error]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error uploading file: ' . error_get_last()['message']]);
        }
    } else {
        // No new file, just update the description
        $query = "UPDATE department_post SET description = ?, status = 'Pending' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $description, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Memo updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error updating memo: ' . $conn->error]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
