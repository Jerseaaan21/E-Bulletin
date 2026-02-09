<?php
// Modules/Graph/UploadGraph.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try different paths for db.php
$db_paths = [
    "../../db.php",
    "../../../db.php", 
    "db.php",
    "./db.php"
];

$db_included = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        $db_included = true;
        error_log("Database included from: " . $path);
        break;
    }
}

if (!$db_included) {
    error_log("Could not find db.php file. Tried paths: " . implode(", ", $db_paths));
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}

session_start();

// Log the request for debugging
error_log("UploadGraph.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Current working directory: " . getcwd());
error_log("Script filename: " . $_SERVER['SCRIPT_FILENAME']);

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    error_log("User not logged in - Session: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Function to read CSV file
function readCSVFile($filePath) {
    $data = [];
    $headers = [];
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        // Read headers from first row
        $headers = fgetcsv($handle, 1000, ",");
        if (!$headers) {
            fclose($handle);
            throw new Exception('Unable to read CSV headers');
        }
        
        // Read data rows
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) >= 2) { // At least 2 columns required
                // Combine headers with row data, handling cases where row has fewer columns than headers
                $rowData = [];
                for ($i = 0; $i < count($headers); $i++) {
                    $rowData[$headers[$i]] = isset($row[$i]) ? $row[$i] : '';
                }
                $data[] = $rowData;
            }
        }
        fclose($handle);
    } else {
        throw new Exception('Unable to open CSV file');
    }
    
    return ['headers' => $headers, 'data' => $data];
}

// Function to read Excel file (simple implementation for .xlsx files)
function readExcelFile($filePath, $fileExtension = null) {
    // If extension not provided, detect it from the file path
    if ($fileExtension === null) {
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    }
    
    if ($fileExtension === 'xlsx') {
        try {
            return readXLSXFile($filePath);
        } catch (Exception $e) {
            // If Excel reading fails, suggest CSV conversion
            throw new Exception('Excel file could not be processed: ' . $e->getMessage() . ' Please try saving your Excel file as CSV format instead.');
        }
    } elseif ($fileExtension === 'xls') {
        throw new Exception('Legacy Excel format (.xls) is not supported. Please save your file as .xlsx format or convert to CSV.');
    }
    
    throw new Exception('Unsupported file format. Please use .xlsx (Excel) or .csv format.');
}

// Simple XLSX reader (without external dependencies)
function readXLSXFile($filePath) {
    // Check if file exists and is readable
    if (!file_exists($filePath) || !is_readable($filePath)) {
        throw new Exception('Excel file is not readable.');
    }
    
    // Check if ZipArchive class is available
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive extension is not available. Please enable php_zip extension or use CSV format instead.');
    }
    
    // Create a temporary directory for extraction
    $tempDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
    
    try {
        // Extract the XLSX file (it's a ZIP archive)
        $zip = new ZipArchive();
        $result = $zip->open($filePath);
        
        if ($result !== TRUE) {
            throw new Exception('Unable to open Excel file. Error code: ' . $result);
        }
        
        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            throw new Exception('Unable to extract Excel file contents.');
        }
        $zip->close();
        
        // Read the shared strings (if exists)
        $sharedStrings = [];
        $sharedStringsPath = $tempDir . '/xl/sharedStrings.xml';
        if (file_exists($sharedStringsPath)) {
            $sharedStringsXml = simplexml_load_file($sharedStringsPath);
            if ($sharedStringsXml) {
                foreach ($sharedStringsXml->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }
        }
        
        // Read the worksheet data
        $worksheetPath = $tempDir . '/xl/worksheets/sheet1.xml';
        if (!file_exists($worksheetPath)) {
            throw new Exception('Unable to find worksheet data in Excel file.');
        }
        
        $worksheetXml = simplexml_load_file($worksheetPath);
        if (!$worksheetXml) {
            throw new Exception('Unable to parse worksheet data.');
        }
        
        $data = [];
        $headers = [];
        $rowIndex = 0;
        
        foreach ($worksheetXml->sheetData->row as $row) {
            $rowData = [];
            $maxCol = 0;
            
            // First pass: determine the maximum column index and collect cell data
            $cellData = [];
            foreach ($row->c as $cell) {
                $cellRef = (string)$cell['r'];
                // Extract column index from cell reference (e.g., "A1" -> 0, "B1" -> 1, "C1" -> 2)
                preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
                if (isset($matches[1])) {
                    $colLetter = $matches[1];
                    $colIndex = 0;
                    for ($i = 0; $i < strlen($colLetter); $i++) {
                        $colIndex = $colIndex * 26 + (ord($colLetter[$i]) - ord('A') + 1);
                    }
                    $colIndex--; // Convert to 0-based index
                    
                    $maxCol = max($maxCol, $colIndex);
                    
                    $cellValue = '';
                    
                    // Get cell value
                    if (isset($cell->v)) {
                        $value = (string)$cell->v;
                        
                        // Check if it's a shared string
                        if (isset($cell['t']) && (string)$cell['t'] === 's') {
                            $cellValue = isset($sharedStrings[$value]) ? $sharedStrings[$value] : '';
                        } else {
                            $cellValue = $value;
                            
                            // Check if this looks like a percentage value and convert to percentage display
                            if (is_numeric($cellValue)) {
                                $numValue = floatval($cellValue);
                                // If value is between 0 and 1 (inclusive), it might be a percentage stored as decimal
                                // Excel often stores 100% as 1.0, 50% as 0.5, etc.
                                if ($numValue >= 0 && $numValue <= 1) {
                                    // Convert to percentage format
                                    $percentageValue = $numValue * 100;
                                    if ($percentageValue == intval($percentageValue)) {
                                        $cellValue = intval($percentageValue) . '%';
                                    } else {
                                        $cellValue = number_format($percentageValue, 2) . '%';
                                    }
                                }
                            }
                        }
                    }
                    
                    $cellData[$colIndex] = $cellValue;
                }
            }
            
            // Second pass: create row data array with proper column order
            for ($i = 0; $i <= $maxCol; $i++) {
                $rowData[] = isset($cellData[$i]) ? $cellData[$i] : '';
            }
            
            // Skip empty rows
            if (!empty(array_filter($rowData))) {
                if ($rowIndex === 0) {
                    $headers = $rowData;
                } else {
                    // Ensure row has same number of columns as headers
                    while (count($rowData) < count($headers)) {
                        $rowData[] = '';
                    }
                    
                    // Create associative array
                    $associativeRow = [];
                    for ($i = 0; $i < count($headers); $i++) {
                        $associativeRow[$headers[$i]] = isset($rowData[$i]) ? $rowData[$i] : '';
                    }
                    $data[] = $associativeRow;
                }
                $rowIndex++;
            }
        }
        
        // Clean up temporary directory
        deleteDirectory($tempDir);
        
        if (empty($headers) || count($headers) < 2) {
            throw new Exception('Excel file must have at least 2 columns.');
        }
        
        if (empty($data)) {
            throw new Exception('No data found in Excel file.');
        }
        
        return ['headers' => $headers, 'data' => $data];
        
    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($tempDir)) {
            deleteDirectory($tempDir);
        }
        throw $e;
    }
}

// Helper function to delete directory recursively
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Function to detect if data contains percentages
function detectPercentageData($data, $headers) {
    if (empty($data) || empty($headers)) {
        return false;
    }
    
    $percentageCount = 0;
    $totalNumericValues = 0;
    
    // Check all numeric columns (skip the first column which is usually labels)
    for ($colIndex = 1; $colIndex < count($headers); $colIndex++) {
        $columnName = $headers[$colIndex];
        
        foreach ($data as $row) {
            if (isset($row[$columnName])) {
                $value = trim($row[$columnName]);
                
                // Skip empty values
                if (empty($value)) {
                    continue;
                }
                
                // Check if value contains % symbol
                if (strpos($value, '%') !== false) {
                    $percentageCount++;
                    $totalNumericValues++;
                } else {
                    // Check if it's a numeric value
                    $numericValue = str_replace(',', '', $value); // Remove commas
                    if (is_numeric($numericValue)) {
                        $totalNumericValues++;
                    }
                }
            }
        }
    }
    
    // If more than 50% of values contain % symbol, consider it percentage data
    return $totalNumericValues > 0 && ($percentageCount / $totalNumericValues) > 0.5;
}

// Function to detect CSV delimiter
function detectCSVDelimiter($filePath, $checkLines = 2) {
    $delimiters = [',', ';', '\t', '|'];
    $results = [];
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $lines = [];
        for ($i = 0; $i < $checkLines && ($line = fgets($handle)) !== FALSE; $i++) {
            $lines[] = $line;
        }
        fclose($handle);
        
        foreach ($delimiters as $delimiter) {
            $counts = [];
            foreach ($lines as $line) {
                $counts[] = count(str_getcsv($line, $delimiter));
            }
            // Check if all lines have the same number of columns and more than 1
            if (count(array_unique($counts)) === 1 && $counts[0] > 1) {
                $results[$delimiter] = $counts[0];
            }
        }
        
        // Return delimiter with most columns
        if (!empty($results)) {
            return array_search(max($results), $results);
        }
    }
    
    return ','; // Default to comma
}

// Enhanced CSV reading with delimiter detection
function readCSVFileEnhanced($filePath) {
    $delimiter = detectCSVDelimiter($filePath);
    $data = [];
    $headers = [];
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        // Read headers from first row
        $headers = str_getcsv(fgets($handle), $delimiter);
        if (!$headers || count($headers) < 2) {
            fclose($handle);
            throw new Exception('CSV file must have at least 2 columns');
        }
        
        // Clean headers (remove BOM if present)
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        
        // Read data rows
        while (($line = fgets($handle)) !== FALSE) {
            $row = str_getcsv($line, $delimiter);
            if (count($row) >= 2 && !empty(array_filter($row))) { // At least 2 columns and not empty
                // Combine headers with row data
                $rowData = [];
                for ($i = 0; $i < count($headers); $i++) {
                    $rowData[$headers[$i]] = isset($row[$i]) ? trim($row[$i]) : '';
                }
                $data[] = $rowData;
            }
        }
        fclose($handle);
    } else {
        throw new Exception('Unable to open CSV file');
    }
    
    return ['headers' => $headers, 'data' => $data];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("Processing POST request for file upload");
        
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            error_log("File upload error - FILES: " . print_r($_FILES, true));
            throw new Exception('No file uploaded or upload error occurred');
        }
        
        $file = $_FILES['file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
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
            // Try to read as Excel file, passing the detected extension
            try {
                $fileData = readExcelFile($fileTmpName, $fileExtension);
            } catch (Exception $e) {
                error_log("Excel reading failed: " . $e->getMessage());
                throw new Exception('Excel file could not be processed. Error: ' . $e->getMessage());
            }
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
        
        // Detect if values are percentages
        $isPercentageData = detectPercentageData($fileData['data'], $fileData['headers']);
        
        // Process the data for graph creation
        $processedData = [
            'headers' => $fileData['headers'],
            'rows' => $fileData['data'],
            'fileName' => $fileName,
            'rowCount' => count($fileData['data']),
            'columnCount' => count($fileData['headers']),
            'isPercentageData' => $isPercentageData
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => 'File uploaded and processed successfully',
            'data' => $processedData
        ]);
        
    } catch (Exception $e) {
        error_log("Upload processing error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>