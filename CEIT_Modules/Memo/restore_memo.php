<?php
// CEIT_Modules/Memo/restore_memo.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    // Get the memo details before restoring
    $query = "SELECT * FROM main_post WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $userId);
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

                // Update the status to 'active' and remove 'archive/' prefix from the file_path
                $query = "UPDATE main_post SET status = 'active', file_path = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sii", $fileName, $id, $userId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Memo restored successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error restoring memo: ' . $conn->error]);
                }
            } else {
                // If archived file doesn't exist, just update the database
                $query = "UPDATE main_post SET status = 'active', file_path = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sii", $fileName, $id, $userId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Memo restored successfully (file not found in archive)']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error restoring memo: ' . $conn->error]);
                }
            }
        } else {
            // If file_path doesn't start with 'archive/', just update the status
            $query = "UPDATE main_post SET status = 'active' WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $userId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Memo restored successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error restoring memo: ' . $conn->error]);
            }
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Memo not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
