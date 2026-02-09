<?php
// CEIT_Modules/Graph/update_graph.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log that the file was accessed
error_log("update_graph.php accessed at " . date('Y-m-d H:i:s'));
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("Raw input: " . file_get_contents('php://input'));

session_start();
include "../../db.php";

// Check database connection
if (!$conn) {
    error_log("Database connection failed in update_graph.php");
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
$dept_id = $userDeptId ?: 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    error_log("Attempting to update graph ID: $id for department: $dept_id");
    
    // Validate basic input
    if (!is_numeric($id) || $id < 0) {
        error_log("Invalid ID provided: " . var_export($id, true));
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid ID is required']);
        exit;
    }

    // First check if the graph exists and belongs to the user's department
    $checkQuery = "SELECT id, description, graph_type FROM main_graph WHERE id = ? AND dept_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        error_log("Failed to prepare check query: " . $conn->error);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $checkStmt->bind_param("ii", $id, $dept_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        error_log("Graph not found or access denied - ID: $id, Dept: $dept_id");
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Graph not found or access denied']);
        exit;
    }
    
    $graphInfo = $checkResult->fetch_assoc();
    error_log("Found graph: " . print_r($graphInfo, true));
    $checkStmt->close();
    
    // Check if this is a file upload update
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        error_log("Processing file upload update for graph ID: $id");
        
        // Include the upload processing functions
        include_once 'UploadGraph.php';
        
        try {
            $file = $_FILES['file'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            
            // Validate file size (max 10MB)
            if ($fileSize > 10 * 1024 * 1024) {
                throw new Exception('File size too large. Maximum size is 10MB.');
            }
            
            // Get file extension
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Support CSV and Excel files
            $allowedExtensions = ['csv', 'xlsx', 'xls'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Only CSV (.csv) and Excel (.xlsx, .xls) files are supported.');
            }
            
            // Read file data based on extension
            if ($fileExtension === 'csv') {
                $fileData = readCSVFileEnhanced($fileTmpName);
            } elseif ($fileExtension === 'xls') {
                throw new Exception('Legacy Excel format (.xls) is not supported. Please save your file as .xlsx format or convert to CSV.');
            } else {
                $fileData = readExcelFile($fileTmpName, $fileExtension);
            }
            
            if (empty($fileData['data'])) {
                throw new Exception('No data found in the file or file format is invalid.');
            }
            
            // Validate that we have at least 2 columns
            if (count($fileData['headers']) < 2) {
                throw new Exception('File must contain at least 2 columns (labels and values).');
            }
            
            // Validate that we have at least 2 rows of data
            if (count($fileData['data']) < 2) {
                throw new Exception('File must contain at least 2 rows of data.');
            }
            
            // Get the graph type and title from POST data
            $graphType = $_POST['graphType'] ?? 'pie';
            $title = $_POST['title'] ?? $fileName;
            
            // Process the file data into graph format
            $graphData = [];
            
            if ($graphType === 'pie') {
                // For pie charts, use first column as labels and second column as values
                $labels = [];
                $values = [];
                $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'];
                
                foreach ($fileData['data'] as $row) {
                    $rowValues = array_values($row);
                    if (count($rowValues) >= 2 && !empty($rowValues[0]) && is_numeric($rowValues[1])) {
                        $labels[] = $rowValues[0];
                        $values[] = floatval($rowValues[1]);
                    }
                }
                
                // Assign colors cyclically
                $graphColors = [];
                for ($i = 0; $i < count($labels); $i++) {
                    $graphColors[] = $colors[$i % count($colors)];
                }
                
                $graphData = [
                    'labels' => $labels,
                    'values' => $values,
                    'colors' => $graphColors
                ];
                
            } elseif ($graphType === 'bar') {
                // For bar charts, use first column as categories and remaining columns as series
                $categories = [];
                $seriesLabels = array_slice($fileData['headers'], 1); // Skip first column (categories)
                $seriesColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
                $values = [];
                
                foreach ($fileData['data'] as $row) {
                    $rowValues = array_values($row);
                    if (count($rowValues) >= 2 && !empty($rowValues[0])) {
                        $categories[] = $rowValues[0];
                        $categoryValues = [];
                        
                        // Get values for each series
                        for ($i = 1; $i < count($rowValues); $i++) {
                            $categoryValues[] = is_numeric($rowValues[$i]) ? floatval($rowValues[$i]) : 0;
                        }
                        $values[] = $categoryValues;
                    }
                }
                
                $graphData = [
                    'categories' => $categories,
                    'seriesLabels' => $seriesLabels,
                    'seriesColors' => array_slice($seriesColors, 0, count($seriesLabels)),
                    'values' => $values
                ];
            }
            
            // Update the record in the database with file-based data
            $query = "UPDATE main_graph SET description = ?, graph_type = ?, data = ? WHERE id = ? AND dept_id = ?";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                error_log("Failed to prepare file update query: " . $conn->error);
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            
            $dataJson = json_encode($graphData);
            $stmt->bind_param("sssii", $title, $graphType, $dataJson, $id, $dept_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    error_log("Graph updated successfully with file data - ID: $id");
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Graph updated successfully with new file data']);
                } else {
                    error_log("No rows affected during file update - ID: $id, Dept: $dept_id");
                    header('Content-Type: application/json');
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Graph not found or no changes made']);
                }
            } else {
                error_log("File update query execution failed: " . $stmt->error);
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            exit;
            
        } catch (Exception $e) {
            error_log("File upload update error: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    // Check if this is the new comprehensive update or the old simple update
    if (isset($_POST['title']) && isset($_POST['type']) && isset($_POST['data'])) {
        // New comprehensive update
        $title = $_POST['title'] ?? '';
        $graphType = $_POST['type'] ?? '';
        $dataJson = $_POST['data'] ?? '';
        
        error_log("Comprehensive update - Title: $title, Type: $graphType");
        
        // Validate inputs
        if (empty($title) || empty($graphType)) {
            error_log("Missing title or graph type");
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Title and graph type are required']);
            exit;
        }
        
        if (empty($dataJson)) {
            error_log("Missing graph data");
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Graph data is required']);
            exit;
        }
        
        // Decode the JSON data
        $graphData = json_decode($dataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON data: " . json_last_error_msg());
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid graph data format: ' . json_last_error_msg()]);
            exit;
        }
        
        // Additional validation based on graph type
        if ($graphType === 'pie') {
            if (!isset($graphData['labels']) || !isset($graphData['values']) || 
                count($graphData['labels']) < 2 || count($graphData['values']) < 2) {
                error_log("Insufficient pie chart data");
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please add at least 2 data points for the pie chart']);
                exit;
            }
        } elseif ($graphType === 'bar') {
            if (!isset($graphData['categories']) || !isset($graphData['values']) || 
                count($graphData['categories']) < 2 || count($graphData['values']) < 2) {
                error_log("Insufficient bar chart data");
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please add at least 2 categories for the bar chart']);
                exit;
            }
        } elseif ($graphType === 'group') {
            if (!isset($graphData['graphs']) || count($graphData['graphs']) < 2) {
                error_log("Insufficient group graph data");
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please add at least 2 graphs to the group']);
                exit;
            }
        }
        
        // Update the record in the database with full data
        $query = "UPDATE main_graph SET description = ?, graph_type = ?, data = ? WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Failed to prepare comprehensive update query: " . $conn->error);
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("sssii", $title, $graphType, $dataJson, $id, $dept_id);
        
    } else {
        // Old simple update (for backward compatibility)
        $description = $_POST['description'] ?? '';
        $graphType = $_POST['graphType'] ?? 'pie';
        
        error_log("Simple update - Description: $description, Type: $graphType");
        
        // Validate inputs
        if (empty($description)) {
            error_log("Missing description");
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Description is required']);
            exit;
        }
        
        // Update the record in the database
        $query = "UPDATE main_graph SET description = ?, graph_type = ? WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Failed to prepare simple update query: " . $conn->error);
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("ssii", $description, $graphType, $id, $dept_id);
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            error_log("Graph updated successfully - ID: $id");
            
            // If status is not-approved, reset to pending for resubmission
            if ($status === 'not-approved') {
                $resetQuery = "UPDATE main_graph SET status = 'Pending' WHERE id = ?";
                $resetStmt = $conn->prepare($resetQuery);
                $resetStmt->bind_param("i", $id);
                $resetStmt->execute();
                error_log("Reset status to Pending for graph ID: $id");
            }

            // Return success response
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Graph updated successfully']);
        } else {
            error_log("No rows affected during update - ID: $id, Dept: $dept_id");
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Graph not found or no changes made']);
        }
        exit;
    } else {
        error_log("Update query execution failed: " . $stmt->error);
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
