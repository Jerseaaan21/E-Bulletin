<?php
// Include database connection (one level up from CEIT_Modules)
require_once '../../db.php';

// Disable error display for JSON responses
ini_set('display_errors', 0);
header('Content-Type: application/json');

$action = $_POST['action'] ?? null;

try {
    if ($action === 'add') {
        handleAdd($conn);
    } elseif ($action === 'add_group') {
        handleAddGroup($conn);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function handleAdd($conn)
{
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? 'pie';
    $file = $_FILES['file'] ?? null;

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a title']);
        exit;
    }

    // Handle file upload if present
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        // Validate file
        $allowedExtensions = ['csv', 'xlsx', 'xls'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileSize > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
            exit;
        }

        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(['success' => false, 'message' => 'Only CSV, XLSX, and XLS files are allowed']);
            exit;
        }

        // Files will be stored directly in CEIT_Modules/Graphs/
        $uploadDir = __DIR__; // Current directory is CEIT_Modules/Graphs/

        // Generate unique filename
        $uniqueFileName = 'graph_' . date('Ymd_His') . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . '/' . $uniqueFileName;

        // Move uploaded file
        if (!move_uploaded_file($fileTmpName, $uploadPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            exit;
        }

        // Parse file
        $graphData = parseFile($uploadPath, $fileExtension, $type);

        if (empty($graphData)) {
            unlink($uploadPath);
            echo json_encode(['success' => false, 'message' => 'No valid data found in the uploaded file']);
            exit;
        }

        // Save to database - store just the filename since files are in the same directory
        $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, file_path, department_id) VALUES (?, ?, ?, ?, ?)");
        $departmentId = 1; // CEIT department
        $jsonData = json_encode($graphData);
        $filePath = $uniqueFileName; // Just store the filename

        $stmt->bind_param('ssssi', $title, $type, $jsonData, $filePath, $departmentId);

        if (!$stmt->execute()) {
            unlink($uploadPath);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Graph uploaded successfully']);
        exit;
    }

    // Handle manual data entry
    // Prepare graph data
    if ($type === 'pie') {
        $labels = $_POST['label'] ?? [];
        $values = $_POST['value'] ?? [];
        $colors = $_POST['color'] ?? [];

        $graphData = [];
        for ($i = 0; $i < count($labels); $i++) {
            if (!empty($labels[$i]) && isset($values[$i])) {
                // Validate color format
                $color = $colors[$i] ?? '#FF6384';
                if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
                    $color = '#FF6384'; // Default color if invalid
                }

                $graphData[] = [
                    'label' => $labels[$i],
                    'value' => is_numeric($values[$i]) ? floatval($values[$i]) : 0,
                    'color' => $color
                ];
            }
        }
    } else {
        $seriesCount = intval($_POST['seriesCount'] ?? 2);
        $categories = $_POST['bar_category'] ?? [];

        $graphData = [];
        for ($i = 0; $i < count($categories); $i++) {
            if (!empty($categories[$i])) {
                $dataPoint = ['category' => $categories[$i]];

                for ($k = 1; $k <= $seriesCount; $k++) {
                    $seriesKey = "bar_series{$k}";
                    $labelKey = "series{$k}Label";
                    $colorKey = "series{$k}Color";

                    if (isset($_POST[$seriesKey][$i])) {
                        $dataPoint["series{$k}"] = is_numeric($_POST[$seriesKey][$i]) ? floatval($_POST[$seriesKey][$i]) : 0;
                        $dataPoint["series{$k}_label"] = $_POST[$labelKey] ?? "Series {$k}";

                        // Validate color format
                        $color = $_POST[$colorKey] ?? getChartColor($k - 1);
                        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
                            $color = getChartColor($k - 1); // Default color if invalid
                        }
                        $dataPoint["series{$k}_color"] = $color;
                    }
                }

                $graphData[] = $dataPoint;
            }
        }
    }

    if (empty($graphData)) {
        echo json_encode(['success' => false, 'message' => 'No valid data provided']);
        exit;
    }

    // Save to database - no file path for manually created graphs
    $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, department_id) VALUES (?, ?, ?, ?)");
    $departmentId = 1; // CEIT department
    $jsonData = json_encode($graphData);

    $stmt->bind_param('sssi', $title, $type, $jsonData, $departmentId);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Graph created successfully']);
    exit;
}

function handleAddGroup($conn)
{
    $groupTitle = $_POST['group_title'] ?? '';
    $graphCount = intval($_POST['graph_count'] ?? 2);

    if (empty($groupTitle)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a group title']);
        exit;
    }

    if ($graphCount < 2 || $graphCount > 5) {
        echo json_encode(['success' => false, 'message' => 'Number of graphs must be between 2 and 5']);
        exit;
    }

    $graphIds = [];
    $departmentId = 1; // CEIT department

    // Process each graph
    for ($i = 1; $i <= $graphCount; $i++) {
        $title = $_POST['title' . $i] ?? '';
        $type = $_POST['type' . $i] ?? 'pie';

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => "Please provide a title for Graph $i"]);
            exit;
        }

        // Prepare graph data
        if ($type === 'pie') {
            $labels = $_POST['label' . $i] ?? [];
            $values = $_POST['value' . $i] ?? [];
            $colors = $_POST['color' . $i] ?? [];

            $graphData = [];
            for ($j = 0; $j < count($labels); $j++) {
                if (!empty($labels[$j]) && isset($values[$j])) {
                    // Validate color format
                    $color = $colors[$j] ?? getChartColor($i - 1);
                    if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
                        $color = getChartColor($i - 1); // Default color if invalid
                    }

                    $graphData[] = [
                        'label' => $labels[$j],
                        'value' => is_numeric($values[$j]) ? floatval($values[$j]) : 0,
                        'color' => $color
                    ];
                }
            }
        } else {
            $seriesCount = intval($_POST['seriesCount' . $i] ?? 2);
            $categories = $_POST['bar_category' . $i] ?? [];

            $graphData = [];
            for ($j = 0; $j < count($categories); $j++) {
                if (!empty($categories[$j])) {
                    $dataPoint = ['category' => $categories[$j]];

                    for ($k = 1; $k <= $seriesCount; $k++) {
                        $seriesKey = "bar_series{$k}{$i}";
                        $labelKey = "series{$k}Label{$i}";
                        $colorKey = "series{$k}Color{$i}";

                        if (isset($_POST[$seriesKey][$j])) {
                            $dataPoint["series{$k}"] = is_numeric($_POST[$seriesKey][$j]) ? floatval($_POST[$seriesKey][$j]) : 0;
                            $dataPoint["series{$k}_label"] = $_POST[$labelKey] ?? "Series {$k}";

                            // Validate color format
                            $color = $_POST[$colorKey] ?? getChartColor(($i - 1) * 5 + $k - 1);
                            if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
                                $color = getChartColor(($i - 1) * 5 + $k - 1); // Default color if invalid
                            }
                            $dataPoint["series{$k}_color"] = $color;
                        }
                    }

                    $graphData[] = $dataPoint;
                }
            }
        }

        if (empty($graphData)) {
            echo json_encode(['success' => false, 'message' => "No valid data provided for Graph $i"]);
            exit;
        }

        // Save graph to database
        $stmt = $conn->prepare("INSERT INTO graphs (title, type, data, group_title, department_id) VALUES (?, ?, ?, ?, ?)");
        $jsonData = json_encode($graphData);
        $stmt->bind_param('ssssi', $title, $type, $jsonData, $groupTitle, $departmentId);

        if (!$stmt->execute()) {
            // If any graph fails, delete all previously created graphs in this group
            if (!empty($graphIds)) {
                $idsStr = implode(',', $graphIds);
                $conn->query("DELETE FROM graphs WHERE id IN ($idsStr)");
            }
            echo json_encode(['success' => false, 'message' => "Database error for Graph $i: " . $stmt->error]);
            exit;
        }

        $graphIds[] = $conn->insert_id;
    }

    echo json_encode(['success' => true, 'message' => 'Graph group created successfully', 'graph_ids' => $graphIds]);
    exit;
}

function parseFile($filePath, $fileExtension, $chartType)
{
    $data = [];

    if ($fileExtension === 'csv') {
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle);

            if ($chartType === 'pie' && count($headers) >= 2) {
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 2 && !empty(trim($row[0]))) {
                        $data[] = [
                            'label' => trim($row[0]),
                            'value' => is_numeric($row[1]) ? floatval($row[1]) : 0,
                            'color' => getChartColor(count($data))
                        ];
                    }
                }
            } elseif ($chartType === 'bar' && count($headers) >= 2) {
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 2 && !empty(trim($row[0]))) {
                        $item = ['category' => trim($row[0])];

                        for ($i = 1; $i < count($headers) && $i < count($row); $i++) {
                            $item["series{$i}"] = is_numeric($row[$i]) ? floatval($row[$i]) : 0;
                            $item["series{$i}_label"] = $headers[$i] ?? "Series {$i}";
                            $item["series{$i}_color"] = getChartColor($i - 1);
                        }

                        $data[] = $item;
                    }
                }
            }

            fclose($handle);
        }
    }

    return $data;
}

function getChartColor($index)
{
    $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8A2BE2', '#20B2AA', '#FF69B4', '#7B68EE'];
    return $colors[$index % count($colors)];
}
