<?php
session_start();
require_once('../../db.php');

// Try multiple paths for autoloader
$autoloadPaths = [
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    '../../../../vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    error_log("PhpSpreadsheet autoloader not found. Using fallback CSV parser.");
}

// Check if PhpOffice\PhpSpreadsheet is available
$phpspreadsheetAvailable = class_exists('PhpOffice\PhpSpreadsheet\IOFactory');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get tab state parameters
    $mainTab = isset($_POST['mainTab']) ? $_POST['mainTab'] : 'upload';
    $currentTab = isset($_POST['currentTab']) ? $_POST['currentTab'] : 'upload-graphs';
    
    // Get form data
    $groupTitle = $_POST['groupTitle'] ?? '';
    $graphCount = isset($_POST['graphCount']) ? intval($_POST['graphCount']) : 2;
    $isGroup = isset($_POST['isGroup']) ? $_POST['isGroup'] : '1';
    
    // Get graph titles and types
    $graphTitles = $_POST['graphTitle'] ?? [];
    $graphTypes = $_POST['graphType'] ?? [];
    
    // Validate required fields
    if (empty($groupTitle)) {
        echo "<script>alert('Please provide a group title'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // Validate graph titles
    if (count($graphTitles) !== $graphCount) {
        echo "<script>alert('Graph count mismatch'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['graphFiles']) || empty($_FILES['graphFiles']['name'][0])) {
        echo "<script>alert('Please upload files for all graphs'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    $graphFiles = $_FILES['graphFiles'];
    $successCount = 0;
    $errors = [];
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Process each graph
    for ($i = 0; $i < $graphCount; $i++) {
        $graphTitle = $graphTitles[$i] ?? "Graph " . ($i + 1) . " - " . $groupTitle;
        $graphType = $graphTypes[$i] ?? 'pie';
        
        // Check if file was uploaded for this graph
        if (empty($graphFiles['name'][$i])) {
            $errors[] = "No file uploaded for Graph " . ($i + 1) . " ({$graphTitle})";
            continue;
        }
        
        // File upload validation
        $allowedExtensions = ['csv', 'xlsx', 'xls'];
        $fileName = $graphFiles['name'][$i];
        $fileTmpName = $graphFiles['tmp_name'][$i];
        $fileSize = $graphFiles['size'][$i];
        $fileError = $graphFiles['error'][$i];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            ];
            $errorMessage = $uploadErrors[$fileError] ?? "Unknown upload error ($fileError)";
            $errors[] = "File upload error for Graph " . ($i + 1) . " ({$graphTitle}): $errorMessage";
            continue;
        }
        
        // Check file size (10MB max)
        $maxFileSize = 10 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            $errors[] = "File for Graph " . ($i + 1) . " ({$graphTitle}) exceeds 10MB limit";
            continue;
        }
        
        // Validate file extension
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "File for Graph " . ($i + 1) . " ({$graphTitle}) has invalid extension. Only CSV, XLSX, and XLS files are allowed.";
            continue;
        }
        
        try {
            // Generate unique filename
            date_default_timezone_set('Asia/Manila');
            $uniqueFileName = 'graph_group_' . date('Ymd_His') . '_' . $i . '.' . $fileExtension;
            $uploadPath = $uploadDir . '/' . $uniqueFileName;
            
            // Move uploaded file
            if (!move_uploaded_file($fileTmpName, $uploadPath)) {
                throw new Exception("Failed to move uploaded file");
            }
            
            // Parse the uploaded file
            $data = [];
            $seriesNames = [];
            
            if ($fileExtension === 'csv') {
                // Parse CSV file
                $data = parseCSVFile($uploadPath, $graphType, $seriesNames);
            } else if ($fileExtension === 'xlsx' || $fileExtension === 'xls') {
                // Parse Excel file only if PhpSpreadsheet is available
                if ($phpspreadsheetAvailable) {
                    $data = parseExcelFile($uploadPath, $graphType, $seriesNames);
                } else {
                    throw new Exception("Excel file parsing requires PhpSpreadsheet library. Please install it or use CSV files.");
                }
            }
            
            if (empty($data)) {
                // Clean up uploaded file
                unlink($uploadPath);
                throw new Exception("No valid data found in the uploaded file.");
            }
            
            // Apply color palette
            $colorPalettes = [
                ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
                ['#8AC926', '#1982C4', '#6A4C93', '#F15BB5', '#00BBF9', '#00F5D4'],
                ['#FB5607', '#FF006E', '#8338EC', '#3A86FF', '#06FFA5', '#FFBE0B'],
                ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557', '#F77F00'],
                ['#2A9D8F', '#E9C46A', '#F4A261', '#E76F51', '#264653', '#E9D8A6']
            ];
            
            $paletteIndex = $i % count($colorPalettes);
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
                    $seriesCount = count($seriesNames);
                    
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
                                        // Use series name from header if available
                                        $dataPoint["series{$k}_label"] = isset($seriesNames[$k-1]) ? $seriesNames[$k-1] : "Series {$k}";
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
                // Clean up uploaded file
                unlink($uploadPath);
                throw new Exception("Failed to prepare graph data from file.");
            }
            
            // Check for duplicate graph
            $checkQuery = "SELECT id FROM graphs WHERE title = ? AND type = ? AND group_title = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('sss', $graphTitle, $graphType, $groupTitle);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Delete uploaded file
                unlink($uploadPath);
                $errors[] = "A graph with title '{$graphTitle}' already exists in group '{$groupTitle}'";
                continue;
            }
            
            // Prepare data for database with proper JSON encoding
            $jsonData = json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
            
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, file_path, group_title, department_id) VALUES (?, ?, ?, ?, ?, ?)");
            $departmentId = 1;
            $stmt->bind_param('sssssi', $graphTitle, $graphType, $jsonData, $uniqueFileName, $groupTitle, $departmentId);
            
            if ($stmt->execute()) {
                $successCount++;
            } else {
                unlink($uploadPath);
                $errors[] = "Database error for Graph " . ($i + 1) . " ({$graphTitle}): " . $stmt->error;
            }
            
        } catch (Exception $e) {
            // Clean up uploaded file if it exists
            if (isset($uploadPath) && file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            $errors[] = "Error processing Graph " . ($i + 1) . " ({$graphTitle}): " . $e->getMessage();
        }
    }
    
    // Prepare response message
    $message = "";
    if ($successCount > 0) {
        $message = "Successfully created $successCount graph(s) in group \"$groupTitle\"!";
    }
    
    if (!empty($errors)) {
        if (!empty($message)) {
            $message .= "\\n\\nHowever, there were some errors:";
        } else {
            $message = "There were errors processing your files:";
        }
        $message .= "\\n• " . implode("\\n• ", array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $message .= "\\n• ... and " . (count($errors) - 5) . " more errors";
        }
    }
    
    if ($successCount === 0 && empty($errors)) {
        $message = "No graphs were created. Please check your file formats and try again.";
    }
    
    // Show appropriate alert based on results
    $alertType = ($successCount > 0 && empty($errors)) ? 'alert' : 'alert';
    echo "<script>$alertType('$message'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
    
} else {
    // If accessed directly, redirect to graphs page
    header("Location: CEIT.php?main=upload&tab=upload-graphs");
    exit;
}

/**
 * Parse CSV file
 */
function parseCSVFile($filePath, $chartType, &$seriesNames = []) {
    $data = [];
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        
        if ($headers === false) {
            fclose($handle);
            return $data;
        }
        
        if ($chartType === 'pie') {
            // Pie chart: Expecting Label,Value format
            if (count($headers) >= 2) {
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 2 && !empty(trim($row[0]))) {
                        $data[] = [
                            'label' => trim($row[0]),
                            'value' => trim($row[1])
                        ];
                    }
                }
            }
        } else {
            // Bar chart: Expecting Category,Series1,Series2,... format
            if (count($headers) >= 2) {
                // Store series names from headers (skip first column which is category)
                $seriesNames = array_slice($headers, 1);
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 2 && !empty(trim($row[0]))) {
                        $item = ['category' => trim($row[0])];
                        
                        for ($i = 1; $i < count($headers) && $i < count($row); $i++) {
                            $item["series{$i}"] = trim($row[$i]);
                        }
                        
                        $data[] = $item;
                    }
                }
            }
        }
        
        fclose($handle);
    }
    
    return $data;
}

/**
 * Parse Excel file
 */
function parseExcelFile($filePath, $chartType, &$seriesNames = []) {
    $data = [];
    
    try {
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception("PhpSpreadsheet library not available");
        }
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        if (empty($rows) || count($rows) < 2) {
            return $data;
        }
        
        $headers = $rows[0];
        
        if ($chartType === 'pie') {
            if (count($headers) >= 2) {
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (count($row) >= 2 && !empty(trim($row[0]))) {
                        $data[] = [
                            'label' => trim($row[0]),
                            'value' => trim($row[1])
                        ];
                    }
                }
            }
        } else {
            if (count($headers) >= 2) {
                // Store series names from headers (skip first column which is category)
                $seriesNames = array_slice($headers, 1);
                
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (count($row) >= 2 && !empty(trim($row[0]))) {
                        $item = ['category' => trim($row[0])];
                        
                        for ($j = 1; $j < count($headers) && $j < count($row); $j++) {
                            $item["series{$j}"] = trim($row[$j]);
                        }
                        
                        $data[] = $item;
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Excel parsing error: " . $e->getMessage());
        throw new Exception("Failed to parse Excel file: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Parse value - handle percentage, decimal, and integer formats
 */
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
    
    // Check if it's a regular number (integer or decimal)
    if (is_numeric($value)) {
        // Check if it's an integer (no decimal point or decimal point followed by zeros)
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
    
    // If we get here, the value is not in a valid format
    return false;
}
?>