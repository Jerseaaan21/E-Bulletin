<?php
// Modules/Graph/update_graph.php
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
    $status = $_POST['status'] ?? '';
    
    // Check if this is the new comprehensive update or the old simple update
    if (isset($_POST['title']) && isset($_POST['type']) && isset($_POST['data'])) {
        // New comprehensive update
        $title = $_POST['title'] ?? '';
        $graphType = $_POST['type'] ?? '';
        $dataJson = $_POST['data'] ?? '';
        
        // Validate inputs
        if (empty($id) || empty($title) || empty($graphType)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID, title, and graph type are required']);
            exit;
        }
        
        if (empty($dataJson)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Graph data is required']);
            exit;
        }
        
        // Decode the JSON data
        $graphData = json_decode($dataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid graph data format']);
            exit;
        }
        
        // Additional validation based on graph type
        if ($graphType === 'pie') {
            if (!isset($graphData['labels']) || !isset($graphData['values']) || 
                count($graphData['labels']) < 2 || count($graphData['values']) < 2) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please add at least 2 data points for the pie chart']);
                exit;
            }
        } elseif ($graphType === 'bar') {
            if (!isset($graphData['categories']) || !isset($graphData['values']) || 
                count($graphData['categories']) < 2 || count($graphData['values']) < 2) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please add at least 2 categories for the bar chart']);
                exit;
            }
        } elseif ($graphType === 'group') {
            if (!isset($graphData['graphs']) || count($graphData['graphs']) < 2) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please add at least 2 graphs to the group']);
                exit;
            }
        }
        
        // Update the record in the database with full data
        $query = "UPDATE graph SET description = ?, graph_type = ?, data = ? WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssii", $title, $graphType, $dataJson, $id, $dept_id);
        
    } else {
        // Old simple update (for backward compatibility)
        $description = $_POST['description'] ?? '';
        $graphType = $_POST['graphType'] ?? 'pie';
        
        // Validate inputs
        if (empty($id) || empty($description)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID and description are required']);
            exit;
        }
        
        // Update the record in the database
        $query = "UPDATE graph SET description = ?, graph_type = ? WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssii", $description, $graphType, $id, $dept_id);
    }

    if ($stmt->execute()) {
        // If status is not-approved, reset to pending for resubmission
        if ($status === 'not-approved') {
            $resetQuery = "UPDATE graph SET status = 'Pending' WHERE id = ?";
            $resetStmt = $conn->prepare($resetQuery);
            $resetStmt->bind_param("i", $id);
            $resetStmt->execute();
        }

        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Graph updated successfully']);
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