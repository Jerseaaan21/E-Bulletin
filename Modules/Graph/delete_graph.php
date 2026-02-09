<?php
// Modules/Graph/delete_graph.php
session_start();
include "../../db.php";

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get department ID from session
 $dept_id = $_SESSION['dept_id'] ?? 0;
 $user_id = $_SESSION['user_info']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;

    // Validate input
    if (empty($id)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    // Delete the record from the database
    $query = "DELETE FROM graph WHERE id = ? AND dept_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $dept_id);

    if ($stmt->execute()) {
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Graph deleted successfully']);
        exit;
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>