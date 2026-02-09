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
        echo json_encode(['success' => false, 'message' => 'Memo ID is required']);
        exit;
    }

    $id = $_POST['id'];

    // Log the ID for debugging
    error_log("Attempting to delete memo with ID: " . $id);

    // Get the file path to delete it
    $query = "SELECT file_path, status FROM department_post WHERE id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $dbFilePath = $row['file_path'];

        // Build the full file path with dynamic department folder
        $filePath = "../../uploads/{$dept_acronym}/Memo/" . $dbFilePath;

        // Check if the file exists before trying to delete
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                // Log error but continue with database deletion
                error_log("Failed to delete file: " . $filePath);
            }
        }

        // Delete the record from the database
        $query = "DELETE FROM department_post WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Check if a row was actually deleted
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Memo deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Memo not found or already deleted']);
            }
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
