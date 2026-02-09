<?php
// Include database connection (one level up from CEIT_Modules)
require_once '../../db.php';

// Disable error display for JSON responses
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    handleDelete($conn);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function handleDelete($conn)
{
    $graphId = $_POST['id'] ?? 0;

    if ($graphId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    // Get file path
    $stmt = $conn->prepare("SELECT file_path FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fileName = $row['file_path'];

        // Delete file if it exists (files are in the same directory)
        if ($fileName && file_exists(__DIR__ . '/' . $fileName)) {
            unlink(__DIR__ . '/' . $fileName);
        }
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Graph deleted successfully']);
    exit;
}
