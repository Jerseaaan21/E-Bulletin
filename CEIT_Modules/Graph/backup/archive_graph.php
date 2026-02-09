<?php
// Include the database connection file
require_once('../../db.php');

// Create archive directory if it doesn't exist
$archiveDir = __DIR__ . '/uploads/archive';
if (!file_exists($archiveDir)) {
    mkdir($archiveDir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $graphId = $_POST['graph_id'];
    
    // Get tab state parameters
    $mainTab = isset($_POST['mainTab']) ? $_POST['mainTab'] : 'upload';
    $currentTab = isset($_POST['currentTab']) ? $_POST['currentTab'] : 'upload-graphs';
    
    try {
        // Get the graph details before archiving
        $query = "SELECT * FROM graphs WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $graphId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Get the graph data
            $graphData = json_decode($row['data'], true);
            
            // Ensure colors are preserved for both pie and bar charts
            if ($row['type'] === 'pie') {
                // For pie charts, ensure each data point has its color preserved
                foreach ($graphData as &$dataPoint) {
                    if (!isset($dataPoint['color']) && isset($dataPoint['color_text'])) {
                        $dataPoint['color'] = $dataPoint['color_text'];
                    }
                    if (isset($dataPoint['value']) && !isset($dataPoint['original_value'])) {
                        $dataPoint['original_value'] = $dataPoint['value'];
                    }
                    if (isset($dataPoint['value']) && !isset($dataPoint['format']) && is_string($dataPoint['value']) && strpos($dataPoint['value'], '%') !== false) {
                        $dataPoint['format'] = 'percentage';
                    }
                }
            } else if ($row['type'] === 'bar') {
                // For bar charts, ensure series colors are preserved
                $seriesCount = 0;
                // Determine the number of series
                if (!empty($graphData)) {
                    $firstItem = $graphData[0];
                    for ($i = 1; $i <= 5; $i++) {
                        if (isset($firstItem["series{$i}"])) {
                            $seriesCount = $i;
                        }
                    }
                }
                
                // Ensure each series has its color and label preserved
                foreach ($graphData as &$dataPoint) {
                    for ($i = 1; $i <= $seriesCount; $i++) {
                        $seriesKey = "series{$i}";
                        $labelKey = "series{$i}_label";
                        $colorKey = "series{$i}_color";
                        $formatKey = "series{$i}_format";
                        
                        // Make sure series colors are preserved
                        if (!isset($dataPoint[$colorKey]) && isset($dataPoint["series{$i}ColorText"])) {
                            $dataPoint[$colorKey] = $dataPoint["series{$i}ColorText"];
                        }
                        
                        // Make sure series labels are preserved
                        if (!isset($dataPoint[$labelKey]) && isset($dataPoint["series{$i}Label"])) {
                            $dataPoint[$labelKey] = $dataPoint["series{$i}Label"];
                        }
                        
                        // Preserve original value and format
                        if (isset($dataPoint[$seriesKey]) && !isset($dataPoint["{$seriesKey}_original_value"])) {
                            $dataPoint["{$seriesKey}_original_value"] = $dataPoint[$seriesKey];
                        }
                        
                        if (isset($dataPoint[$seriesKey]) && !isset($dataPoint[$formatKey]) && is_string($dataPoint[$seriesKey]) && strpos($dataPoint[$seriesKey], '%') !== false) {
                            $dataPoint[$formatKey] = 'percentage';
                        }
                    }
                }
            }
            
            // Create archive data structure that preserves the original data and type
            $archiveData = [
                'type' => $row['type'],
                'title' => $row['title'],
                'group_title' => $row['group_title'],
                'department_id' => $row['department_id'],
                'created_at' => $row['created_at'],
                'data' => $graphData
            ];
            
            // Re-encode the data with JSON_UNESCAPED_UNICODE to handle special characters
            $formattedData = json_encode($archiveData, JSON_UNESCAPED_UNICODE);
            
            // Handle file path - only if file_path is not empty
            $file_path = null;
            if (!empty($row['file_path'])) {
                // Copy file to archive directory if it exists
                $sourceFile = __DIR__ . '/uploads/' . $row['file_path'];
                $archiveFile = $archiveDir . '/' . $row['file_path'];
                
                if (file_exists($sourceFile)) {
                    if (!copy($sourceFile, $archiveFile)) {
                        error_log("Failed to copy file from $sourceFile to $archiveFile");
                    } else {
                        $file_path = 'archive/' . $row['file_path'];
                        unlink($sourceFile);
                    }
                }
            }
            
            // Insert into Archive table in Bulletin database
            $archiveQuery = "INSERT INTO Archive (type, title, content, file_path, data, archived_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $archiveStmt = $conn->prepare($archiveQuery);
            
            $type = 'faculty_graph';
            $title = $row['title'];
            $content = null;
            $data = $formattedData;

            $archiveStmt->bind_param("sssss", $type, $title, $content, $file_path, $data);
            
            if ($archiveStmt->execute()) {
                // Now delete from graphs table in Bulletin database
                $deleteQuery = "DELETE FROM graphs WHERE id = ?";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->bind_param("i", $graphId);
                
                if ($deleteStmt->execute()) {
                    // Success message
                    echo "<script>
                        alert('Graph archived successfully!');
                        window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';
                    </script>";
                } else {
                    echo "<script>
                        alert('Error: Failed to delete the original graph after archiving.');
                        window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';
                    </script>";
                }
            } else {
                echo "<script>
                    alert('Error: Failed to archive the graph. " . addslashes($conn->error) . "');
                    window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';
                </script>";
            }
        } else {
            echo "<script>
                alert('Error: Graph not found.');
                window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            alert('Error: " . addslashes($e->getMessage()) . "');
            window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';
        </script>";
    }
} else {
    echo "<script>
        alert('Error: Invalid request method.');
        window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';
    </script>";
}
?>