<?php
session_start();
include "../../db.php";

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get department acronym from session
 $dept_acronym = $_SESSION['dept_acronym'] ?? 'CEIT';

// Create archive directory if it doesn't exist
 $archiveDir = "../../uploads/{$dept_acronym}/Institutional_Development/archive/";
if (!file_exists($archiveDir)) {
    if (!mkdir($archiveDir, 0755, true)) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create archive directory']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    
    if (empty($id)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        exit;
    }
    
    // Get the file path before updating the status
    $query = "SELECT file_path FROM main_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $fileName = $row['file_path'];
    
    // Construct source and destination paths
    $sourcePath = "../../uploads/{$dept_acronym}/Institutional_Development/" . $fileName;
    $destPath = $archiveDir . $fileName;
    
    // Move the file to the archive directory
    if (file_exists($sourcePath)) {
        if (!rename($sourcePath, $destPath)) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error moving file to archive']);
            exit;
        }
    }
    
    // Update status to 'archived'
    $query = "UPDATE main_post SET status = 'archived' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Document archived successfully']);
        exit;
    } else {
        // If database update fails, try to move the file back
        if (file_exists($destPath)) {
            rename($destPath, $sourcePath);
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error archiving document: ' . $conn->error]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>