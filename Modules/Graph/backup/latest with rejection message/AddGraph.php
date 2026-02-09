<?php
// Modules/Graph/AddGraph.php
include_once "../../db.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user ID and department ID
 $userId = $_SESSION['user_info']['id'];
 $deptId = $_SESSION['dept_id'] ?? 0;

// Get module ID for Graph
 $moduleQuery = "SELECT id FROM modules WHERE name = 'Graph' LIMIT 1";
 $moduleResult = $conn->query($moduleQuery);
 $moduleRow = $moduleResult->fetch_assoc();
 $moduleId = $moduleRow['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the title from the form
    $title = $_POST['title'] ?? '';
    
    // Get the graph type
    $graphType = $_POST['type'] ?? '';
    
    // Get the graph data
    $dataJson = $_POST['data'] ?? '';
    
    // Validate required fields
    if (empty($title) || empty($graphType)) {
        echo json_encode(['success' => false, 'message' => 'Title and graph type are required']);
        exit;
    }
    
    if (empty($dataJson)) {
        echo json_encode(['success' => false, 'message' => 'Graph data is required']);
        exit;
    }
    
    // Decode the JSON data
    $graphData = json_decode($dataJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph data format']);
        exit;
    }
    
    // Additional validation based on graph type
    if ($graphType === 'pie') {
        if (!isset($graphData['labels']) || !isset($graphData['values']) || 
            count($graphData['labels']) < 2 || count($graphData['values']) < 2) {
            echo json_encode(['success' => false, 'message' => 'Please add at least 2 data points for the pie chart']);
            exit;
        }
    } elseif ($graphType === 'bar') {
        if (!isset($graphData['categories']) || !isset($graphData['values']) || 
            count($graphData['categories']) < 2 || count($graphData['values']) < 2) {
            echo json_encode(['success' => false, 'message' => 'Please add at least 2 categories for the bar chart']);
            exit;
        }
    } elseif ($graphType === 'group') {
        if (!isset($graphData['graphs']) || count($graphData['graphs']) < 2) {
            echo json_encode(['success' => false, 'message' => 'Please add at least 2 graphs to the group']);
            exit;
        }
    }
    
    // Insert the graph into the database
    try {
        // First, ensure the auto-increment is working properly
        $conn->query("ALTER TABLE graph AUTO_INCREMENT = 1");
        
        $stmt = $conn->prepare("INSERT INTO graph (module, dept_id, user_id, description, graph_type, data, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iiisss", $moduleId, $deptId, $userId, $title, $graphType, $dataJson);
        
        if ($stmt->execute()) {
            $graphId = $conn->insert_id;
            echo json_encode(['success' => true, 'message' => 'Graph added successfully', 'graph_id' => $graphId]);
        } else {
            // If we get a duplicate key error, try to fix it
            if (strpos($stmt->error, 'Duplicate entry') !== false && strpos($stmt->error, 'PRIMARY') !== false) {
                // Get the maximum ID and reset auto-increment
                $maxIdResult = $conn->query("SELECT MAX(id) as max_id FROM graph");
                $maxIdRow = $maxIdResult->fetch_assoc();
                $nextId = ($maxIdRow['max_id'] ?? 0) + 1;
                
                $conn->query("ALTER TABLE graph AUTO_INCREMENT = $nextId");
                
                // Try the insert again
                if ($stmt->execute()) {
                    $graphId = $conn->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Graph added successfully', 'graph_id' => $graphId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>