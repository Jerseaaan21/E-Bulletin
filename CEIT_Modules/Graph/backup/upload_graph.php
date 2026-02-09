<?php
session_start();
require_once('../../db.php');

// Try multiple paths for autoloader
$autoloadPaths = [
    __DIR__ . '/../../../../vendor/autoload.php', // From CEIT directory
    __DIR__ . '/../../../vendor/autoload.php',    // Alternative path
    __DIR__ . '/../../vendor/autoload.php',       // Another alternative
    __DIR__ . '/vendor/autoload.php',             // Local vendor directory
    '../../../../vendor/autoload.php',           // Relative path
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
    $graphTitle = $_POST['graphTitle'] ?? '';
    $chartType = $_POST['chartType'] ?? 'pie';
    $createGroup = isset($_POST['createGroup']) ? true : false;
    $groupTitle = isset($_POST['groupTitle']) ? $_POST['groupTitle'] : null;
    
    // Validate required fields
    if (empty($graphTitle) || !isset($_FILES['file'])) {
        echo "<script>alert('Please provide a graph title and select a file'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // File upload validation
    $allowedExtensions = ['csv', 'xlsx', 'xls'];
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
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
        echo "<script>alert('File upload error: $errorMessage'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // Check file size (10MB max)
    $maxFileSize = 10 * 1024 * 1024; // 10MB in bytes
    if ($fileSize > $maxFileSize) {
        echo "<script>alert('File size exceeds 10MB limit'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // Get file extension
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($fileExtension, $allowedExtensions)) {
        echo "<script>alert('Only CSV, XLSX, and XLS files are allowed'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $uniqueFileName = 'graph_upload_' . date('Ymd_His') . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . '/' . $uniqueFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($fileTmpName, $uploadPath)) {
            throw new Exception("Failed to move uploaded file");
        }
        
        // Parse the uploaded file immediately instead of redirecting
        $data = [];
        $seriesNames = [];
        
        if ($fileExtension === 'csv') {
            // Parse CSV file
            $data = parseCSVFile($uploadPath, $chartType, $seriesNames);
        } else if ($fileExtension === 'xlsx' || $fileExtension === 'xls') {
            // Parse Excel file only if PhpSpreadsheet is available
            if ($phpspreadsheetAvailable) {
                $data = parseExcelFile($uploadPath, $chartType, $seriesNames);
            } else {
                throw new Exception("Excel file parsing requires PhpSpreadsheet library. Please install it or use CSV files.");
            }
        }
        
        if (empty($data)) {
            // Clean up uploaded file
            unlink($uploadPath);
            throw new Exception("No valid data found in the uploaded file. Please check the file format.");
        }
        
        // Apply color palette (using default palette)
        $colorPalettes = [
            ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
            ['#8AC926', '#1982C4', '#6A4C93', '#F15BB5', '#00BBF9', '#00F5D4'],
            ['#FB5607', '#FF006E', '#8338EC', '#3A86FF', '#06FFA5', '#FFBE0B'],
            ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557', '#F77F00'],
            ['#2A9D8F', '#E9C46A', '#F4A261', '#E76F51', '#264653', '#E9D8A6']
        ];
        
        $paletteIndex = 0;
        $palette = $colorPalettes[$paletteIndex];
        
        // Prepare graph data for database
        $graphData = [];
        
        if ($chartType === 'pie') {
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
                                    // Use series name from header if available, otherwise use default
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
            throw new Exception("Failed to prepare graph data from file. Please check the data format.");
        }
        
        // Check for duplicate graph
        $checkQuery = "SELECT id FROM graphs WHERE title = ? AND type = ?";
        if ($createGroup && $groupTitle) {
            $checkQuery .= " AND group_title = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('sss', $graphTitle, $chartType, $groupTitle);
        } else {
            $checkQuery .= " AND group_title IS NULL";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('ss', $graphTitle, $chartType);
        }
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Delete uploaded file
            unlink($uploadPath);
            echo "<script>alert('A graph with this title already exists'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Prepare data for database with proper JSON encoding
        $jsonData = json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, file_path, group_title, department_id) VALUES (?, ?, ?, ?, ?, ?)");
        $departmentId = 1;
        $groupTitleForDB = $createGroup ? $groupTitle : null;
        $stmt->bind_param('sssssi', $graphTitle, $chartType, $jsonData, $uniqueFileName, $groupTitleForDB, $departmentId);
        
        if ($stmt->execute()) {
            // Success - redirect back
            echo "<script>alert('Graph created successfully from uploaded file!'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        } else {
            throw new Exception("Database insertion failed: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        // Clean up uploaded file if it exists
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        error_log("Upload graph error: " . $e->getMessage());
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
    }
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