<?php
// Modules/Memo/delete_memo.php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID and department ID from session
$userId = $_SESSION['user_info']['id'];
$deptId = $_SESSION['dept_id'] ?? 0;

// Get department acronym from database
$dept_acronym = 'default'; // fallback

// Query the departments table to get the acronym
$query = "SELECT acronym FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dept_acronym = $row['acronym'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Get the memo details before deleting
    $query = "SELECT * FROM department_post WHERE id = ? AND dept_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $deptId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filePath = $row['file_path'];

        // Check if file_path starts with 'archive/'
        if (strpos($filePath, 'archive/') === 0) {
            // Extract the actual filename without the 'archive/' prefix
            $fileName = substr($filePath, 8); // 8 is the length of 'archive/'

            // Delete the file from archive directory
            $fileToDelete = "../../uploads/{$dept_acronym}/Memo/archive/" . $fileName;

            if (file_exists($fileToDelete)) {
                if (!unlink($fileToDelete)) {
                    // Log error but continue with database deletion
                    error_log("Failed to delete archived file: " . $fileToDelete);
                }
            }
        } else {
            // Delete the file from main directory
            $fileToDelete = "../../uploads/{$dept_acronym}/Memo/" . $filePath;

            if (file_exists($fileToDelete)) {
                if (!unlink($fileToDelete)) {
                    // Log error but continue with database deletion
                    error_log("Failed to delete file: " . $fileToDelete);
                }
            }
        }

        // Delete the record from the database
        $query = "DELETE FROM department_post WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $deptId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Memo deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Memo not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
