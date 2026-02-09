<?php
// Include the database connection file
require_once('../../db.php');
require_once '../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    // Check if this is a group submission
    $isGroup = isset($_POST['isGroup']) && $_POST['isGroup'] == '1';
    
    // Get tab state parameters
    $mainTab = isset($_POST['mainTab']) ? $_POST['mainTab'] : 'upload';
    $currentTab = isset($_POST['currentTab']) ? $_POST['currentTab'] : 'upload-graphs';
    
    if ($isGroup) {
        // Handle group submission
        $groupTitle = $_POST['groupTitle'];
        $graphCount = $_POST['graphCount'];
        
        if (empty($groupTitle) || empty($graphCount)) {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Process each graph in the group
        for ($i = 0; $i < $graphCount; $i++) {
            $graphTitle = $_POST['graphTitle'][$i];
            $graphType = $_POST['graphType'][$i];
            
            if (empty($graphTitle) || empty($graphType)) {
                continue; // Skip incomplete graphs
            }
            
            // Check if this graph has file upload
            $fileUploadKey = "file_{$i}";
            if (isset($_FILES[$fileUploadKey]) && $_FILES[$fileUploadKey]['error'] === UPLOAD_ERR_OK) {
                // Process uploaded file for this graph
                processUploadedFileForGroupGraph($conn, $graphTitle, $graphType, $groupTitle, $i, $fileUploadKey);
            } else {
                // Process manual form data for this graph
                processManualFormDataForGroupGraph($conn, $graphTitle, $graphType, $groupTitle, $i);
            }
        }
        
        // Redirect back to the graphs page with tab state
        echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
        
    } else {
        // Handle single graph submission
        $title = $_POST['graphTitle'];
        $graphType = $_POST['graphType'];
        
        if (empty($title) || empty($graphType)) {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Prepare graph data based on graph type
        if ($graphType === 'pie') {
            $labels = $_POST['label'];
            $values = $_POST['value'];
            $colors = $_POST['color'];
            
            if (empty($labels) || empty($values)) {
                echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
                exit;
            }
            
            // Prepare graph data
            $graphData = [];
            for ($i = 0; $i < count($labels); $i++) {
                if (!empty($labels[$i]) && isset($values[$i])) {
                    // Parse value - handle percentage, decimal, and integer formats
                    $parsedValue = parseValue($values[$i]);
                    if ($parsedValue !== false) {
                        // Get color for this label, default to a color from palette if not provided
                        $color = isset($colors[$i]) ? $colors[$i] : getDefaultColor($i);
                        
                        $graphData[] = [
                            'label' => $labels[$i],
                            'value' => $parsedValue['value'],
                            'format' => $parsedValue['format'],
                            'color' => $color,
                            'original_value' => $values[$i]
                        ];
                    }
                }
            }
        } else {
            // Get the number of series for this graph
            $seriesCount = isset($_POST['seriesCount']) ? intval($_POST['seriesCount']) : 2;
            
            $categories = $_POST['bar_category'];
            
            if (empty($categories)) {
                echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
                exit;
            }
            
            // Prepare graph data
            $graphData = [];
            for ($i = 0; $i < count($categories); $i++) {
                if (!empty($categories[$i])) {
                    $dataPoint = ['category' => $categories[$i]];
                    
                    // Add each series to the data point
                    for ($k = 1; $k <= $seriesCount; $k++) {
                        $seriesKey = "bar_series{$k}";
                        $labelKey = "series{$k}Label";
                        $colorKey = "series{$k}Color";
                        
                        if (isset($_POST[$seriesKey][$i])) {
                            $seriesValue = $_POST[$seriesKey][$i];
                            $parsedValue = parseValue($seriesValue);
                            if ($parsedValue !== false) {
                                $dataPoint["series{$k}"] = $parsedValue['value'];
                                $dataPoint["series{$k}_format"] = $parsedValue['format'];
                                $dataPoint["series{$k}_original_value"] = $seriesValue;
                                
                                // Add label and color from the form inputs
                                if ($i === 0) {
                                    $dataPoint["series{$k}_label"] = isset($_POST[$labelKey]) ? $_POST[$labelKey] : "Series {$k}";
                                    $dataPoint["series{$k}_color"] = isset($_POST[$colorKey]) ? $_POST[$colorKey] : getDefaultColor($k - 1);
                                }
                            }
                        }
                    }
                    
                    $graphData[] = $dataPoint;
                }
            }
        }
        
        if (empty($graphData)) {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Check for duplicate graph in Bulletin database
        $checkQuery = "SELECT id FROM graphs WHERE title = ? AND type = ? AND group_title IS NULL";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('ss', $title, $graphType);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Graph already exists, redirect back
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate new filename
        date_default_timezone_set('Asia/Manila');
        $newFileName = 'graph_' . date('d_m_Y_H_i_s') . '.xlsx';
        $uploadPath = $uploadDir . '/' . $newFileName;
        
        // Create a simple CSV file
        $csvContent = "";
        if ($graphType === 'pie') {
            $csvContent = "Label,Value,Color\n";
            foreach ($graphData as $item) {
                $csvContent .= "{$item['label']},{$item['value']},{$item['color']}\n";
            }
        } else {
            // Create header with all series
            $csvContent = "Category";
            for ($k = 1; $k <= $seriesCount; $k++) {
                $label = isset($graphData[0]["series{$k}_label"]) ? $graphData[0]["series{$k}_label"] : "Series {$k}";
                $csvContent .= ",{$label}";
            }
            $csvContent .= "\n";
            
            // Add data rows
            foreach ($graphData as $item) {
                $csvContent .= $item['category'];
                for ($k = 1; $k <= $seriesCount; $k++) {
                    $value = isset($item["series{$k}"]) ? $item["series{$k}"] : "";
                    $csvContent .= ",{$value}";
                }
                $csvContent .= "\n";
            }
        }
        
        // Save CSV file
        if (file_put_contents($uploadPath, $csvContent) === false) {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Prepare data for database with proper JSON encoding
        $jsonData = json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
        
        // Insert into Bulletin database
        try {
            // Use CEIT department_id (assuming department_id = 1 for CEIT)
            $departmentId = 1; // CEIT department
            $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, file_path, department_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssi', $title, $graphType, $jsonData, $newFileName, $departmentId);
            $stmt->execute();
            
            // Redirect back to the graphs page with tab state
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        } catch (Exception $e) {
            // Delete the uploaded file if database insertion fails
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        }
    }
}

/**
 * Process uploaded file for a group graph
 */
function processUploadedFileForGroupGraph($conn, $graphTitle, $graphType, $groupTitle, $index, $fileKey) {
    $file = $_FILES[$fileKey];
    $fileTmpName = $file['tmp_name'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowedExtensions = ['csv', 'xlsx', 'xls'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        return false;
    }
    
    try {
        // Parse the file
        $data = [];
        
        if ($fileExtension === 'csv') {
            if (($handle = fopen($fileTmpName, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                
                if ($graphType === 'pie') {
                    // Pie chart: Expecting Label,Value format
                    if (count($headers) >= 2) {
                        while (($row = fgetcsv($handle)) !== FALSE) {
                            if (count($row) >= 2) {
                                $data[] = [
                                    'label' => $row[0],
                                    'value' => $row[1]
                                ];
                            }
                        }
                    }
                } else {
                    // Bar chart: Expecting Category,Series1,Series2,... format
                    if (count($headers) >= 2) {
                        $seriesCount = count($headers) - 1;
                        
                        while (($row = fgetcsv($handle)) !== FALSE) {
                            if (count($row) >= 2) {
                                $item = ['category' => $row[0]];
                                
                                for ($i = 1; $i <= $seriesCount && $i < count($row); $i++) {
                                    $item["series{$i}"] = $row[$i];
                                }
                                
                                $data[] = $item;
                            }
                        }
                    }
                }
                
                fclose($handle);
            }
        } else {
            // Parse Excel file
            $spreadsheet = IOFactory::load($fileTmpName);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (!empty($rows) && count($rows) >= 2) {
                $headers = $rows[0];
                
                if ($graphType === 'pie') {
                    if (count($headers) >= 2) {
                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            if (count($row) >= 2 && !empty($row[0])) {
                                $data[] = [
                                    'label' => $row[0],
                                    'value' => $row[1]
                                ];
                            }
                        }
                    }
                } else {
                    if (count($headers) >= 2) {
                        $seriesCount = count($headers) - 1;
                        
                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            if (count($row) >= 2 && !empty($row[0])) {
                                $item = ['category' => $row[0]];
                                
                                for ($j = 1; $j <= $seriesCount && $j < count($row); $j++) {
                                    $item["series{$j}"] = $row[$j];
                                }
                                
                                $data[] = $item;
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($data)) {
            return false;
        }
        
        // Apply default colors
        $colorPalettes = [
            ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
            ['#8AC926', '#1982C4', '#6A4C93', '#F15BB5', '#00BBF9', '#00F5D4'],
            ['#FB5607', '#FF006E', '#8338EC', '#3A86FF', '#06FFA5', '#FFBE0B'],
            ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557', '#F77F00'],
            ['#2A9D8F', '#E9C46A', '#F4A261', '#E76F51', '#264653', '#E9D8A6']
        ];
        
        $paletteIndex = $index % count($colorPalettes);
        $palette = $colorPalettes[$paletteIndex];
        
        // Prepare graph data
        $graphData = [];
        
        if ($graphType === 'pie') {
            foreach ($data as $idx => $item) {
                if (!empty($item['label']) && isset($item['value'])) {
                    $parsedValue = parseValue($item['value']);
                    if ($parsedValue !== false) {
                        $graphData[] = [
                            'label' => $item['label'],
                            'value' => $parsedValue['value'],
                            'format' => $parsedValue['format'],
                            'color' => $palette[$idx % count($palette)],
                            'original_value' => $item['value']
                        ];
                    }
                }
            }
        } else {
            // Bar chart
            if (!empty($data)) {
                $firstItem = $data[0];
                $seriesCount = 0;
                
                // Count series
                foreach ($firstItem as $key => $value) {
                    if (strpos($key, 'series') === 0 && !strpos($key, '_')) {
                        $seriesCount++;
                    }
                }
                
                foreach ($data as $item) {
                    if (!empty($item['category'])) {
                        $dataPoint = ['category' => $item['category']];
                        
                        for ($k = 1; $k <= $seriesCount; $k++) {
                            if (isset($item["series{$k}"])) {
                                $parsedValue = parseValue($item["series{$k}"]);
                                if ($parsedValue !== false) {
                                    $dataPoint["series{$k}"] = $parsedValue['value'];
                                    $dataPoint["series{$k}_format"] = $parsedValue['format'];
                                    $dataPoint["series{$k}_original_value"] = $item["series{$k}"];
                                    $dataPoint["series{$k}_label"] = "Series {$k}";
                                    $dataPoint["series{$k}_color"] = $palette[($k-1) % count($palette)];
                                }
                            }
                        }
                        
                        $graphData[] = $dataPoint;
                    }
                }
            }
        }
        
        if (empty($graphData)) {
            return false;
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate filename
        date_default_timezone_set('Asia/Manila');
        $newFileName = 'graph_group_' . date('Ymd_His') . '_' . $index . '.xlsx';
        $uploadPath = $uploadDir . '/' . $newFileName;
        
        // Save CSV file
        $csvContent = "";
        if ($graphType === 'pie') {
            $csvContent = "Label,Value,Color\n";
            foreach ($graphData as $item) {
                $csvContent .= "{$item['label']},{$item['value']},{$item['color']}\n";
            }
        } else {
            // Create header
            $csvContent = "Category";
            if (!empty($graphData)) {
                $firstItem = $graphData[0];
                $seriesCount = 0;
                foreach ($firstItem as $key => $value) {
                    if (strpos($key, 'series') === 0 && !strpos($key, '_')) {
                        $seriesCount++;
                        $label = isset($firstItem["series{$seriesCount}_label"]) ? $firstItem["series{$seriesCount}_label"] : "Series {$seriesCount}";
                        $csvContent .= ",{$label}";
                    }
                }
                $csvContent .= "\n";
                
                // Add data rows
                foreach ($graphData as $item) {
                    $csvContent .= $item['category'];
                    for ($k = 1; $k <= $seriesCount; $k++) {
                        $value = isset($item["series{$k}"]) ? $item["series{$k}"] : "";
                        $csvContent .= ",{$value}";
                    }
                    $csvContent .= "\n";
                }
            }
        }
        
        if (file_put_contents($uploadPath, $csvContent) === false) {
            return false;
        }
        
        // Prepare data for database
        $jsonData = json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
        
        // Check for duplicate in Bulletin database
        $checkQuery = "SELECT id FROM graphs WHERE title = ? AND type = ? AND group_title = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('sss', $graphTitle, $graphType, $groupTitle);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            unlink($uploadPath);
            return false;
        }
        
        // Insert into Bulletin database with department_id
        $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, file_path, group_title, department_id) VALUES (?, ?, ?, ?, ?, ?)");
        $departmentId = 1; // CEIT department
        $stmt->bind_param('sssssi', $graphTitle, $graphType, $jsonData, $newFileName, $groupTitle, $departmentId);
        
        if (!$stmt->execute()) {
            unlink($uploadPath);
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Group file upload error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process manual form data for a group graph
 */
function processManualFormDataForGroupGraph($conn, $graphTitle, $graphType, $groupTitle, $index) {
    // For pie charts
    if ($graphType === 'pie') {
        $labels = $_POST['label'][$index] ?? [];
        $values = $_POST['value'][$index] ?? [];
        $colors = $_POST['color'][$index] ?? [];
        
        if (empty($labels) || empty($values)) {
            return false;
        }
        
        $graphData = [];
        for ($j = 0; $j < count($labels); $j++) {
            if (!empty($labels[$j]) && isset($values[$j])) {
                $parsedValue = parseValue($values[$j]);
                if ($parsedValue !== false) {
                    $color = isset($colors[$j]) ? $colors[$j] : getDefaultColor($j);
                    
                    $graphData[] = [
                        'label' => $labels[$j],
                        'value' => $parsedValue['value'],
                        'format' => $parsedValue['format'],
                        'color' => $color,
                        'original_value' => $values[$j]
                    ];
                }
            }
        }
    } else {
        // Bar chart
        $seriesCount = isset($_POST['seriesCount'][$index]) ? intval($_POST['seriesCount'][$index]) : 2;
        $categories = $_POST['bar_category'][$index] ?? [];
        
        if (empty($categories)) {
            return false;
        }
        
        $graphData = [];
        for ($j = 0; $j < count($categories); $j++) {
            if (!empty($categories[$j])) {
                $dataPoint = ['category' => $categories[$j]];
                
                for ($k = 1; $k <= $seriesCount; $k++) {
                    $seriesKey = "bar_series{$k}";
                    $labelKey = "series{$k}Label";
                    $colorKey = "series{$k}Color";
                    
                    if (isset($_POST[$seriesKey][$index][$j])) {
                        $seriesValue = $_POST[$seriesKey][$index][$j];
                        $parsedValue = parseValue($seriesValue);
                        if ($parsedValue !== false) {
                            $dataPoint["series{$k}"] = $parsedValue['value'];
                            $dataPoint["series{$k}_format"] = $parsedValue['format'];
                            $dataPoint["series{$k}_original_value"] = $seriesValue;
                            
                            if ($j === 0) {
                                $dataPoint["series{$k}_label"] = isset($_POST[$labelKey][$index]) ? $_POST[$labelKey][$index] : "Series {$k}";
                                $dataPoint["series{$k}_color"] = isset($_POST[$colorKey][$index]) ? $_POST[$colorKey][$index] : getDefaultColor($k - 1);
                            }
                        }
                    }
                }
                
                $graphData[] = $dataPoint;
            }
        }
    }
    
    if (empty($graphData)) {
        return false;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate filename
    date_default_timezone_set('Asia/Manila');
    $newFileName = 'graph_group_' . date('Ymd_His') . '_' . $index . '.xlsx';
    $uploadPath = $uploadDir . '/' . $newFileName;
    
    // Save CSV file
    $csvContent = "";
    if ($graphType === 'pie') {
        $csvContent = "Label,Value,Color\n";
        foreach ($graphData as $item) {
            $csvContent .= "{$item['label']},{$item['value']},{$item['color']}\n";
        }
    } else {
        // Create header
        $csvContent = "Category";
        if (!empty($graphData)) {
            $firstItem = $graphData[0];
            $seriesCount = 0;
            foreach ($firstItem as $key => $value) {
                if (strpos($key, 'series') === 0 && !strpos($key, '_')) {
                    $seriesCount++;
                    $label = isset($firstItem["series{$seriesCount}_label"]) ? $firstItem["series{$seriesCount}_label"] : "Series {$seriesCount}";
                    $csvContent .= ",{$label}";
                }
            }
            $csvContent .= "\n";
            
            // Add data rows
            foreach ($graphData as $item) {
                $csvContent .= $item['category'];
                for ($k = 1; $k <= $seriesCount; $k++) {
                    $value = isset($item["series{$k}"]) ? $item["series{$k}"] : "";
                    $csvContent .= ",{$value}";
                }
                $csvContent .= "\n";
            }
        }
    }
    
    if (file_put_contents($uploadPath, $csvContent) === false) {
        return false;
    }
    
    // Prepare data for database
    $jsonData = json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
    
    // Check for duplicate in Bulletin database
    $checkQuery = "SELECT id FROM graphs WHERE title = ? AND type = ? AND group_title = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('sss', $graphTitle, $graphType, $groupTitle);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        unlink($uploadPath);
        return false;
    }
    
    // Insert into Bulletin database with department_id
    $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, file_path, group_title, department_id) VALUES (?, ?, ?, ?, ?, ?)");
    $departmentId = 1; // CEIT department
    $stmt->bind_param('sssssi', $graphTitle, $graphType, $jsonData, $newFileName, $groupTitle, $departmentId);
    
    if (!$stmt->execute()) {
        unlink($uploadPath);
        return false;
    }
    
    return true;
}

// Function to parse value that can be in different formats
function parseValue($value) {
    // Trim whitespace
    $value = trim($value);
    
    // Check if it's a percentage
    if (strpos($value, '%') !== false) {
        // Remove % sign and convert to decimal
        $numericValue = str_replace('%', '', $value);
        if (is_numeric($numericValue)) {
            return [
                'value' => floatval($numericValue),
                'format' => 'percentage'
            ];
        }
    }
    
    // Check if it's a regular number
    if (is_numeric($value)) {
        if (strpos($value, '.') === false || (floatval($value) == intval($value))) {
            return [
                'value' => floatval($value),
                'format' => 'integer'
            ];
        } else {
            return [
                'value' => floatval($value),
                'format' => 'decimal'
            ];
        }
    }
    
    return false;
}

// Function to get a default color from the color palette
function getDefaultColor($index) {
    $colorPalettes = [
        ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
        ['#8AC926', '#1982C4', '#6A4C93', '#F15BB5', '#00BBF9', '#00F5D4'],
        ['#FB5607', '#FF006E', '#8338EC', '#3A86FF', '#06FFA5', '#FFBE0B'],
        ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557', '#F77F00'],
        ['#2A9D8F', '#E9C46A', '#F4A261', '#E76F51', '#264653', '#E9D8A6']
    ];
    
    $paletteIndex = 0;
    $palette = $colorPalettes[$paletteIndex % count($colorPalettes)];
    return $palette[$index % count($palette)];
}
?>