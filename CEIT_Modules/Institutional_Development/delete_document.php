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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    
    if (empty($id)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        exit;
    }
    
    // First, get the file path and status to delete the physical file
    $query = "SELECT file_path, status FROM main_post WHERE id = ?";
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
    $status = $row['status'];
    
    // Get department acronym from session
    $dept_acronym = $_SESSION['dept_acronym'] ?? 'CEIT';
    
    // Construct full file path based on status
    if ($status === 'archived') {
        $fullFilePath = "../../uploads/{$dept_acronym}/Institutional_Development/archive/" . $fileName;
    } else {
        $fullFilePath = "../../uploads/{$dept_acronym}/Institutional_Development/" . $fileName;
    }
    
    // Delete the record from database
    $query = "DELETE FROM main_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Try to delete the physical file
        if (file_exists($fullFilePath)) {
            unlink($fullFilePath);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
        exit;
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting document: ' . $conn->error]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>