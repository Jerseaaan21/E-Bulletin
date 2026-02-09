<?php
// Modules/Memo/restore_memo.php
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

    // Get the memo details before restoring
    $query = "SELECT * FROM department_post WHERE id = ? AND dept_id = ? AND status = 'Archived'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $deptId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Check if file_path starts with 'archive/'
        if (strpos($row['file_path'], 'archive/') === 0) {
            // Extract the actual filename without the 'archive/' prefix
            $fileName = substr($row['file_path'], 8); // 8 is the length of 'archive/'

            // Copy file from archive directory back to main directory
            $sourceFile = "../../uploads/{$dept_acronym}/Memo/archive/" . $fileName;
            $destFile = "../../uploads/{$dept_acronym}/Memo/" . $fileName;

            if (file_exists($sourceFile)) {
                if (!copy($sourceFile, $destFile)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to restore file from archive']);
                    exit;
                }

                // Delete the archived file after successful copy
                if (!unlink($sourceFile)) {
                    // Log error but continue with database update
                    error_log("Failed to delete archived file: " . $sourceFile);
                }

                // Update the status to 'Approved' and remove 'archive/' prefix from the file_path
                $query = "UPDATE department_post SET status = 'Approved', file_path = ? WHERE id = ? AND dept_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sii", $fileName, $id, $deptId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Memo restored successfully to Approved section']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error restoring memo: ' . $conn->error]);
                }
            } else {
                // If archived file doesn't exist, just update the database
                $query = "UPDATE department_post SET status = 'Approved', file_path = ? WHERE id = ? AND dept_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sii", $fileName, $id, $deptId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Memo restored successfully to Approved section (file not found in archive)']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error restoring memo: ' . $conn->error]);
                }
            }
        } else {
            // If file_path doesn't start with 'archive/', just update the status
            $query = "UPDATE department_post SET status = 'Approved' WHERE id = ? AND dept_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $deptId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Memo restored successfully to Approved section']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error restoring memo: ' . $conn->error]);
            }
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Memo not found or not archived']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
