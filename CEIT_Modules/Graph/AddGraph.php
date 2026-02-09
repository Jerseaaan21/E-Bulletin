<?php
// CEIT_Modules/Graph/AddGraph.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log that the file was accessed
error_log("AddGraph.php accessed at " . date('Y-m-d H:i:s'));

include_once "../../db.php";
session_start();

// Check database connection
if (!$conn) {
    error_log("Database connection failed");
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if main_graph table exists
$tableCheckQuery = "SHOW TABLES LIKE 'main_graph'";
$tableCheckResult = $conn->query($tableCheckQuery);

if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
    error_log("main_graph table does not exist");
    echo json_encode(['success' => false, 'message' => 'Database table not found']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user info
$userId = $_SESSION['user_info']['id'];
$userRole = $_SESSION['user_info']['role'] ?? '';
$userDeptId = $_SESSION['user_info']['dept_id'] ?? 1; // Default to CEIT

// For CEIT modules, prefer LEAD_MIS but allow other roles for testing
// You can uncomment the strict check below when ready
/*
if ($userRole !== 'LEAD_MIS' || $userDeptId != 1) {
    echo json_encode(['success' => false, 'message' => 'Access denied. LEAD_MIS role for CEIT department required.']);
    exit;
}
*/

// Use the user's department ID (default to CEIT if not set)
$deptId = $userDeptId ?: 1;

// Get module ID for Graphs
$moduleQuery = "SELECT id FROM modules WHERE name = 'Graph' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);

if (!$moduleResult) {
    error_log("Module query failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error: Could not fetch module information']);
    exit;
}

$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? null;

// If no Graph module found, try to create it or use a default
if (!$moduleId) {
    error_log("Graph module not found in modules table");
    
    // Try to insert the Graph module
    $insertModuleQuery = "INSERT INTO modules (name, description) VALUES ('Graph', 'Graph Management Module') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $insertResult = $conn->query($insertModuleQuery);
    
    if ($insertResult) {
        $moduleId = $conn->insert_id;
        if (!$moduleId) {
            // If insert_id is 0, it means the record already existed, try to get it again
            $moduleResult = $conn->query($moduleQuery);
            if ($moduleResult) {
                $moduleRow = $moduleResult->fetch_assoc();
                $moduleId = $moduleRow['id'] ?? 1; // Default to 1 if still not found
            }
        }
    } else {
        error_log("Failed to create Graph module: " . $conn->error);
        $moduleId = 1; // Default fallback
    }
}

error_log("Using module ID: " . $moduleId . " for department: " . $deptId . " user: " . $userId);

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
    
    // Log the values being inserted for debugging
    error_log("Inserting graph - Module: $moduleId, Dept: $deptId, User: $userId, Title: $title, Type: $graphType");
    error_log("Data JSON length: " . strlen($dataJson));
    
    // Check if we have valid IDs
    if ($moduleId <= 0) {
        error_log("Invalid module ID: $moduleId");
        echo json_encode(['success' => false, 'message' => 'Invalid module configuration. Please run the database repair script.']);
        exit;
    }
    
    if ($deptId <= 0) {
        error_log("Invalid department ID: $deptId");
        echo json_encode(['success' => false, 'message' => 'Invalid department configuration']);
        exit;
    }
    
    if ($userId <= 0) {
        error_log("Invalid user ID: $userId");
        echo json_encode(['success' => false, 'message' => 'Invalid user session']);
        exit;
    }
    
    // Insert the graph into the main_graph table
    try {
        // SAFEGUARD: Ensure auto-increment is working before insertion
        $statusQuery = "SHOW TABLE STATUS LIKE 'main_graph'";
        $statusResult = $conn->query($statusQuery);
        
        if ($statusResult) {
            $row = $statusResult->fetch_assoc();
            $currentAutoIncrement = $row['Auto_increment'] ?? 0;
            
            if ($currentAutoIncrement <= 0) {
                error_log("Auto-increment issue detected: $currentAutoIncrement");
                
                // Fix auto-increment before proceeding
                $maxIdQuery = "SELECT MAX(id) as max_id FROM main_graph";
                $maxIdResult = $conn->query($maxIdQuery);
                $nextId = 1;
                
                if ($maxIdResult) {
                    $row = $maxIdResult->fetch_assoc();
                    $nextId = ($row['max_id'] ?? 0) + 1;
                }
                
                $resetQuery = "ALTER TABLE main_graph AUTO_INCREMENT = $nextId";
                $conn->query($resetQuery);
                error_log("Auto-increment reset to: $nextId");
            }
        }
        
        // Check for potential duplicates (same user, same title, same type within last minute)
        $duplicateCheckQuery = "SELECT id FROM main_graph WHERE user_id = ? AND description = ? AND graph_type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        $duplicateStmt = $conn->prepare($duplicateCheckQuery);
        
        if ($duplicateStmt) {
            $duplicateStmt->bind_param("iss", $userId, $title, $graphType);
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();
            
            if ($duplicateResult->num_rows > 0) {
                $duplicateRow = $duplicateResult->fetch_assoc();
                error_log("Potential duplicate detected, returning existing ID: " . $duplicateRow['id']);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Graph already exists (duplicate prevented)',
                    'id' => $duplicateRow['id']
                ]);
                exit;
            }
            $duplicateStmt->close();
        }
        
        // SAFEGUARD: Use explicit column list and let auto-increment handle ID
        $stmt = $conn->prepare("INSERT INTO main_graph (module, dept_id, user_id, description, graph_type, data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
        
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("iiisss", $moduleId, $deptId, $userId, $title, $graphType, $dataJson);
        
        if ($stmt->execute()) {
            $insertedId = $conn->insert_id;
            
            // SAFEGUARD: If insert_id is 0, there's still an auto-increment issue
            if ($insertedId <= 0) {
                error_log("CRITICAL: Auto-increment returned 0 after safeguards, attempting emergency fix...");
                
                // Emergency fix: Find the actual inserted record
                $findQuery = "SELECT id FROM main_graph WHERE user_id = ? AND description = ? AND graph_type = ? ORDER BY created_at DESC LIMIT 1";
                $findStmt = $conn->prepare($findQuery);
                if ($findStmt) {
                    $findStmt->bind_param("iss", $userId, $title, $graphType);
                    $findStmt->execute();
                    $findResult = $findStmt->get_result();
                    if ($findResult && $findResult->num_rows > 0) {
                        $findRow = $findResult->fetch_assoc();
                        $insertedId = $findRow['id'];
                        error_log("Emergency: Found actual inserted ID: $insertedId");
                        
                        // If it's still 0, we have a serious problem
                        if ($insertedId == 0) {
                            error_log("EMERGENCY: Record inserted with ID 0, fixing immediately...");
                            
                            // Get next safe ID
                            $maxIdQuery = "SELECT MAX(id) as max_id FROM main_graph WHERE id > 0";
                            $maxIdResult = $conn->query($maxIdQuery);
                            $safeId = 1;
                            
                            if ($maxIdResult) {
                                $row = $maxIdResult->fetch_assoc();
                                $safeId = ($row['max_id'] ?? 0) + 1;
                            }
                            
                            // Update the record with ID 0 to have a safe ID
                            $updateQuery = "UPDATE main_graph SET id = ? WHERE id = 0 AND user_id = ? AND description = ? ORDER BY created_at DESC LIMIT 1";
                            $updateStmt = $conn->prepare($updateQuery);
                            $updateStmt->bind_param("iis", $safeId, $userId, $title);
                            $updateStmt->execute();
                            
                            $insertedId = $safeId;
                            error_log("Emergency fix: Updated ID 0 to ID $safeId");
                            
                            // Reset auto-increment for future insertions
                            $nextAutoIncrement = $safeId + 1;
                            $conn->query("ALTER TABLE main_graph AUTO_INCREMENT = $nextAutoIncrement");
                        }
                    }
                    $findStmt->close();
                }
            }
            
            if ($insertedId > 0) {
                error_log("Graph inserted successfully with ID: " . $insertedId);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Graph added successfully',
                    'id' => $insertedId
                ]);
            } else {
                error_log("CRITICAL ERROR: Could not determine inserted ID");
                echo json_encode(['success' => false, 'message' => 'Graph creation failed - database error']);
            }
        } else {
            error_log("Database execute error: " . $stmt->error);
            
            // Check if it's a duplicate key error
            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                error_log("Duplicate entry error detected: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'Database error: A graph with similar data already exists. Please run the database repair tool or try with a different title.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Database exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>