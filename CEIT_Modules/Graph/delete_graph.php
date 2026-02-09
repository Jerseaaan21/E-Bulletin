<?php
// CEIT_Modules/Graphs/delete_graph.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("delete_graph.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Raw input: " . file_get_contents('php://input'));

session_start();
include "../../db.php";

// Check database connection
if (!$conn) {
    error_log("Database connection failed in delete_graph.php");
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    error_log("User not logged in - Session: " . print_r($_SESSION, true));
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user info
$userId = $_SESSION['user_info']['id'];
$userRole = $_SESSION['user_info']['role'] ?? '';
$userDeptId = $_SESSION['user_info']['dept_id'] ?? 1; // Default to CEIT

error_log("User info - ID: $userId, Role: $userRole, Dept: $userDeptId");

// For CEIT modules, prefer LEAD_MIS but allow other roles for testing
// You can uncomment the strict check below when ready
/*
if ($userRole !== 'LEAD_MIS' || $userDeptId != 1) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. LEAD_MIS role for CEIT department required.']);
    exit;
}
*/

// Use the user's department ID (default to CEIT if not set)
$deptId = $userDeptId ?: 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    
    error_log("Attempting to delete graph ID: $id for department: $deptId");

    // Validate input
    if (!is_numeric($id) || $id < 0) {
        error_log("Invalid ID provided: " . var_export($id, true));
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid ID is required']);
        exit;
    }

    // First check if the graph exists and belongs to the user's department
    $checkQuery = "SELECT id, description FROM main_graph WHERE id = ? AND dept_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        error_log("Failed to prepare check query: " . $conn->error);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $checkStmt->bind_param("ii", $id, $deptId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        error_log("Graph not found or access denied - ID: $id, Dept: $deptId");
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Graph not found or access denied']);
        exit;
    }
    
    $graphInfo = $checkResult->fetch_assoc();
    error_log("Found graph: " . print_r($graphInfo, true));
    $checkStmt->close();

    // Delete the record from the main_graph table
    $query = "DELETE FROM main_graph WHERE id = ? AND dept_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Failed to prepare delete query: " . $conn->error);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("ii", $id, $deptId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            error_log("Graph deleted successfully - ID: $id");
            // Return success response
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Graph deleted successfully']);
        } else {
            error_log("No rows affected during delete - ID: $id, Dept: $deptId");
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Graph not found or already deleted']);
        }
        exit;
    } else {
        error_log("Delete query execution failed: " . $stmt->error);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        exit;
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>