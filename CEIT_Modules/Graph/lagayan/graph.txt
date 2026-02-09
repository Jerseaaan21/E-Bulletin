<?php
// Include database connection (one level up from CEIT_Modules)
require_once '../../db.php';

// Handle form submissions
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Set JSON header for AJAX requests
if ($action) {
    header('Content-Type: application/json');
    // Disable error display for JSON responses
    ini_set('display_errors', 0);
}

// Debug logging
if ($action) {
    error_log("Graphs.php - Action: $action");
    error_log("Graphs.php - POST data: " . print_r($_POST, true));
}

try {
    if ($action) {
        switch ($action) {
            case 'get':
                handleGet($conn);
                exit;
            case 'add':
                handleAdd($conn);
                exit;
            case 'add_group':
                handleAddGroup($conn);
                exit;
            case 'update':
                handleUpdate($conn);
                exit;
            case 'delete':
                handleDelete($conn);
                exit;
            case 'archive':
                handleArchive($conn);
                exit;
            default:
                // Invalid action for AJAX request
                error_log("Graphs.php - Invalid action: $action");
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
    } else {
        // No action, display the graphs page
        displayGraphs($conn);
    }
} catch (Exception $e) {
    // For AJAX requests, output JSON error
    if ($action) {
        error_log("Graphs.php - Exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    } else {
        // For non-AJAX requests, show HTML error
        showError($e->getMessage());
    }
}

// Handle get action
function handleGet($conn)
{
    $graphId = $_GET['id'] ?? 0;

    if ($graphId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    // Get graph details
    $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $graph = $result->fetch_assoc();
        $graph['data'] = json_decode($graph['data'], true);
        echo json_encode(['success' => true, 'graph' => $graph]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Graph not found']);
    }
    exit;
}

// Helper functions
function getChartColor($index)
{
    $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8A2BE2', '#20B2AA', '#FF69B4', '#7B68EE'];
    return $colors[$index % count($colors)];
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

// Handle add action
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

        $graphId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Graph uploaded successfully', 'graph_id' => $graphId]);
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

    $graphId = $conn->insert_id;
    echo json_encode(['success' => true, 'message' => 'Graph created successfully', 'graph_id' => $graphId]);
    exit;
}

// Handle add group action
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

// Handle update action
function handleUpdate($conn)
{
    error_log("handleUpdate function called");

    $graphId = $_POST['id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? 'pie';

    error_log("Update request - ID: $graphId, Title: $title, Type: $type");
    error_log("POST data: " . print_r($_POST, true));

    if ($graphId <= 0) {
        error_log("Invalid graph ID: $graphId");
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    if (empty($title)) {
        error_log("Empty title provided");
        echo json_encode(['success' => false, 'message' => 'Please provide a title']);
        exit;
    }

    // Get existing graph data
    $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Graph not found with ID: $graphId");
        echo json_encode(['success' => false, 'message' => 'Graph not found']);
        exit;
    }

    $existingGraph = $result->fetch_assoc();
    error_log("Found graph: " . json_encode($existingGraph));

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
        error_log("No valid data provided");
        echo json_encode(['success' => false, 'message' => 'No valid data provided']);
        exit;
    }

    // Update graph in database
    $stmt = $conn->prepare("UPDATE graphs SET title = ?, type = ?, data = ? WHERE id = ?");
    $jsonData = json_encode($graphData);
    $stmt->bind_param('sssi', $title, $type, $jsonData, $graphId);

    if (!$stmt->execute()) {
        error_log("Database error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        exit;
    }

    error_log("Graph updated successfully");
    // Get the updated graph data to return to the frontend
    $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedGraph = $result->fetch_assoc();
    $updatedGraph['data'] = json_decode($updatedGraph['data'], true);

    echo json_encode(['success' => true, 'message' => 'Graph updated successfully', 'graph_id' => $graphId, 'graph_data' => $updatedGraph]);
    exit;
}

// Handle delete action
function handleDelete($conn)
{
    $graphId = $_POST['id'] ?? 0;

    if ($graphId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    // Get file path
    $stmt = $conn->prepare("SELECT file_path FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fileName = $row['file_path'];

        // Delete file if it exists (files are in the same directory)
        if ($fileName && file_exists(__DIR__ . '/' . $fileName)) {
            unlink(__DIR__ . '/' . $fileName);
        }
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Graph deleted successfully']);
    exit;
}

// Handle archive action
function handleArchive($conn)
{
    $graphId = $_POST['id'] ?? 0;

    if ($graphId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    // Get graph details
    $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // For archive, we'll just delete the file and record
        $fileName = $row['file_path'];

        // Delete file if it exists
        if ($fileName && file_exists(__DIR__ . '/' . $fileName)) {
            unlink(__DIR__ . '/' . $fileName);
        }

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM graphs WHERE id = ?");
        $stmt->bind_param('i', $graphId);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Graph archived successfully']);
    exit;
}

function displayGraphs($conn)
{
    // Get all graphs for CEIT department
    $query = "SELECT * FROM graphs WHERE department_id = 1 ORDER BY created_at DESC";
    $result = $conn->query($query);

    $graphs = [];
    while ($row = $result->fetch_assoc()) {
        $graphs[] = $row;
    }

    // Group graphs by group_title
    $groupedGraphs = [];
    $individualGraphs = [];

    foreach ($graphs as $graph) {
        if ($graph['group_title']) {
            if (!isset($groupedGraphs[$graph['group_title']])) {
                $groupedGraphs[$graph['group_title']] = [];
            }
            $groupedGraphs[$graph['group_title']][] = $graph;
        } else {
            $individualGraphs[] = $graph;
        }
    }
?>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-orange-600 flex items-center">
                <i class="fas fa-chart-pie mr-3 text-orange-600"></i>
                Graph Management
            </h2>
            <div class="flex space-x-3">
                <button onclick="showUploadModal()" class="px-4 py-2 bg-blue-500 text-white hover:bg-blue-600 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-upload mr-2"></i> Upload File
                </button>
                <button onclick="showAddModal()" class="px-4 py-2 bg-orange-500 text-white hover:bg-orange-600 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New Graph
                </button>
                <button onclick="showGroupAddModal()" class="px-4 py-2 bg-purple-500 text-white hover:bg-purple-600 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-layer-group mr-2"></i> Add Graph Group
                </button>
            </div>
        </div>

        <!-- Display grouped graphs -->
        <?php foreach ($groupedGraphs as $groupTitle => $graphs): ?>
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center p-3 bg-purple-50 rounded-lg">
                    <i class="fas fa-layer-group mr-2 text-purple-500"></i>
                    <?php echo htmlspecialchars($groupTitle); ?>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($graphs as $graph): ?>
                        <?php renderGraphCard($graph); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Display individual graphs -->
        <?php if (!empty($individualGraphs)): ?>
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center p-3 bg-blue-50 rounded-lg">
                    <i class="fas fa-chart-bar mr-2 text-blue-500"></i>
                    Individual Graphs
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($individualGraphs as $graph): ?>
                        <?php renderGraphCard($graph); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- No graphs message -->
        <?php if (empty($groupedGraphs) && empty($individualGraphs)): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-6 rounded-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-base text-blue-700 font-medium">No graphs found</p>
                        <p class="text-sm text-blue-600 mt-1">Add graphs to display them here.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Upload Graph</h3>
                <button onclick="closeModal('uploadModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="uploadForm" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Title</label>
                    <input type="text" name="title" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Type</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="pie" class="form-radio" checked>
                            <span class="ml-2">Pie Chart</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="bar" class="form-radio">
                            <span class="ml-2">Bar Chart</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Upload File</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-600 mb-2">Drag and drop your file here or</p>
                        <label class="cursor-pointer inline-block px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            Browse Files
                        </label>
                        <input type="file" name="file" class="hidden" accept=".csv,.xlsx,.xls" required onchange="updateFileName(this)">
                        <div id="fileName" class="mt-2 text-sm text-gray-500">No file selected</div>
                        <p class="text-xs text-gray-400 mt-2">Supported formats: CSV, XLSX, XLS (Max 10MB)</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('uploadModal')" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg">
                        Upload Graph
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Add New Graph</h3>
                <button onclick="closeModal('addModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="addForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Title</label>
                    <input type="text" name="title" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Type</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="pie" class="form-radio" checked onchange="switchGraphType('pie')">
                            <span class="ml-2">Pie Chart</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="bar" class="form-radio" onchange="switchGraphType('bar')">
                            <span class="ml-2">Bar Chart</span>
                        </label>
                    </div>
                </div>

                <!-- Pie Chart Form -->
                <div id="pieForm">
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-gray-700 text-sm font-bold">Data Points</label>
                            <button type="button" onclick="addPieRow()" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
                                <i class="fas fa-plus mr-1"></i> Add Row
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Label</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Value</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Color</th>
                                        <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pieTableBody">
                                    <tr class="border-b border-gray-200">
                                        <td class="px-4 py-2">
                                            <input type="text" name="label[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Label" required>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="text" name="value[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Value" required>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="color" name="color[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="#FF6384">
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button type="button" onclick="removePieRow(this)" class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Bar Chart Form -->
                <div id="barForm" class="hidden">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Number of Series</label>
                        <select id="seriesCount" name="seriesCount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" onchange="updateSeriesInputs()">
                            <option value="1">1</option>
                            <option value="2" selected>2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Series Labels</label>
                        <div id="seriesLabelsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Series inputs will be dynamically added here -->
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-gray-700 text-sm font-bold">Data Points</label>
                            <button type="button" onclick="addBarRow()" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
                                <i class="fas fa-plus mr-1"></i> Add Row
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr id="barTableHeader">
                                        <!-- Table headers will be dynamically added here -->
                                    </tr>
                                </thead>
                                <tbody id="barTableBody">
                                    <!-- Table rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg">
                        Create Graph
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Group Add Modal -->
    <div id="groupAddModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-6xl max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Create Graph Group</h3>
                <button onclick="closeModal('groupAddModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="groupAddForm">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Group Title</label>
                    <input type="text" name="group_title" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Number of Graphs</label>
                    <select id="graphCount" name="graph_count" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" onchange="updateGraphForms()">
                        <option value="2">2 Graphs</option>
                        <option value="3">3 Graphs</option>
                        <option value="4">4 Graphs</option>
                        <option value="5">5 Graphs</option>
                    </select>
                </div>

                <div id="graphFormsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Graph forms will be dynamically added here -->
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('groupAddModal')" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg">
                        Create Graph Group
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Modal -->
    <div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Update Graph</h3>
                <button onclick="closeModal('updateModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="updateForm">
                <input type="hidden" name="id" id="updateGraphId">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Title</label>
                    <input type="text" name="title" id="updateTitle" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Type</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="pie" id="updateTypePie" class="form-radio" onchange="switchGraphType('pie', 'update')">
                            <span class="ml-2">Pie Chart</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="bar" id="updateTypeBar" class="form-radio" onchange="switchGraphType('bar', 'update')">
                            <span class="ml-2">Bar Chart</span>
                        </label>
                    </div>
                </div>

                <!-- Pie Chart Form -->
                <div id="pieFormUpdate">
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-gray-700 text-sm font-bold">Data Points</label>
                            <button type="button" onclick="addPieRow('update')" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
                                <i class="fas fa-plus mr-1"></i> Add Row
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Label</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Value</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Color</th>
                                        <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pieTableBodyUpdate">
                                    <!-- Table rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Bar Chart Form -->
                <div id="barFormUpdate" class="hidden">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Number of Series</label>
                        <select id="seriesCountUpdate" name="seriesCount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" onchange="updateSeriesInputs('update')">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Series Labels</label>
                        <div id="seriesLabelsContainerUpdate" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Series inputs will be dynamically added here -->
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-gray-700 text-sm font-bold">Data Points</label>
                            <button type="button" onclick="addBarRow('update')" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
                                <i class="fas fa-plus mr-1"></i> Add Row
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr id="barTableHeaderUpdate">
                                        <!-- Table headers will be dynamically added here -->
                                    </tr>
                                </thead>
                                <tbody id="barTableBodyUpdate">
                                    <!-- Table rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('updateModal')" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg">
                        Update Graph
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete/Archive Modal -->
    <div id="actionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Confirm Action</h3>
                <button onclick="closeModal('actionModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-6">
                <p class="text-gray-600 mb-4">What would you like to do with this graph?</p>
                <div class="flex flex-col space-y-3">
                    <button onclick="archiveGraph()" class="px-4 py-3 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-archive mr-2"></i> Archive
                    </button>
                    <button onclick="deleteGraph()" class="px-4 py-3 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-trash mr-2"></i> Delete
                    </button>
                </div>
            </div>
            <div class="flex justify-end">
                <button onclick="closeModal('actionModal')" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variable to store chart instances
        window.chartInstances = {};

        let currentGraphId = null;
        const moduleUrl = 'CEIT_Modules/Graphs/Graphs.php'; // Explicit module URL

        // Modal functions
        function showUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
        }

        function showAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            switchGraphType('pie');
        }

        function showGroupAddModal() {
            document.getElementById('groupAddModal').classList.remove('hidden');
            // Initialize with 2 graphs
            updateGraphForms();
        }

        function showUpdateModal(graphId) {
            // Fetch graph data and populate the update form
            fetch('CEIT_Modules/Graphs/Graphs.php?action=get&id=' + graphId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        populateUpdateForm(data.graph);
                        document.getElementById('updateModal').classList.remove('hidden');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching graph data: ' + error.message);
                });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function showActionModal(graphId) {
            currentGraphId = graphId;
            document.getElementById('actionModal').classList.remove('hidden');
        }

        // File upload functions
        function updateFileName(input) {
            const fileNameDiv = document.getElementById('fileName');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = 'Selected: ' + input.files[0].name;
                fileNameDiv.className = 'mt-2 text-sm text-green-600';
            } else {
                fileNameDiv.textContent = 'No file selected';
                fileNameDiv.className = 'mt-2 text-sm text-gray-500';
            }
        }

        // Graph type switching
        function switchGraphType(type, graphNum = '') {
            const pieForm = document.getElementById('pieForm' + graphNum);
            const barForm = document.getElementById('barForm' + graphNum);

            if (type === 'pie') {
                if (pieForm) pieForm.classList.remove('hidden');
                if (barForm) barForm.classList.add('hidden');
            } else {
                if (pieForm) pieForm.classList.add('hidden');
                if (barForm) barForm.classList.remove('hidden');
                updateSeriesInputs(graphNum);
            }
        }

        // Update graph forms based on selected count
        function updateGraphForms() {
            const graphCount = document.getElementById('graphCount').value;
            const container = document.getElementById('graphFormsContainer');
            container.innerHTML = '';

            for (let i = 1; i <= graphCount; i++) {
                const graphForm = createGraphForm(i);
                container.appendChild(graphForm);
            }
        }

        // Create a graph form element
        function createGraphForm(graphNum) {
            const div = document.createElement('div');
            div.className = 'border border-gray-200 rounded-lg p-4';
            div.innerHTML = `
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Graph ${graphNum}</h4>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Title</label>
                    <input type="text" name="title${graphNum}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Graph Type</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="type${graphNum}" value="pie" class="form-radio" checked onchange="switchGraphType('pie', ${graphNum})">
                            <span class="ml-2">Pie Chart</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="type${graphNum}" value="bar" class="form-radio" onchange="switchGraphType('bar', ${graphNum})">
                            <span class="ml-2">Bar Chart</span>
                        </label>
                    </div>
                </div>

                <!-- Pie Chart Form -->
                <div id="pieForm${graphNum}">
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-gray-700 text-sm font-bold">Data Points</label>
                            <button type="button" onclick="addPieRow(${graphNum})" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
                                <i class="fas fa-plus mr-1"></i> Add Row
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Label</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Value</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Color</th>
                                        <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pieTableBody${graphNum}">
                                    <tr class="border-b border-gray-200">
                                        <td class="px-4 py-2">
                                            <input type="text" name="label${graphNum}[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Label" required>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="text" name="value${graphNum}[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Value" required>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="color" name="color${graphNum}[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${getChartColor(graphNum - 1)}">
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button type="button" onclick="removePieRow(this, ${graphNum})" class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Bar Chart Form -->
                <div id="barForm${graphNum}" class="hidden">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Number of Series</label>
                        <select id="seriesCount${graphNum}" name="seriesCount${graphNum}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" onchange="updateSeriesInputs(${graphNum})">
                            <option value="1">1</option>
                            <option value="2" selected>2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Series Labels</label>
                        <div id="seriesLabelsContainer${graphNum}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Series inputs will be dynamically added here -->
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-gray-700 text-sm font-bold">Data Points</label>
                            <button type="button" onclick="addBarRow(${graphNum})" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
                                <i class="fas fa-plus mr-1"></i> Add Row
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr id="barTableHeader${graphNum}">
                                        <!-- Table headers will be dynamically added here -->
                                    </tr>
                                </thead>
                                <tbody id="barTableBody${graphNum}">
                                    <!-- Table rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            return div;
        }

        // Populate update form with existing data
        function populateUpdateForm(graph) {
            try {
                document.getElementById('updateGraphId').value = graph.id;
                document.getElementById('updateTitle').value = graph.title;

                if (graph.type === 'pie') {
                    document.getElementById('updateTypePie').checked = true;
                    switchGraphType('pie', 'update');
                    populatePieUpdateForm(graph.data);
                } else {
                    document.getElementById('updateTypeBar').checked = true;
                    switchGraphType('bar', 'update');
                    populateBarUpdateForm(graph.data);
                }
            } catch (error) {
                console.error('Error populating update form:', error);
                alert('Error populating form: ' + error.message);
            }
        }

        // Populate pie chart update form
        function populatePieUpdateForm(data) {
            try {
                const tableBody = document.getElementById('pieTableBodyUpdate');
                tableBody.innerHTML = '';

                if (!Array.isArray(data)) {
                    console.error('Invalid data format for pie chart');
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.className = 'border-b border-gray-200';
                    row.innerHTML = `
                <td class="px-4 py-2">
                    <input type="text" name="label[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Label" value="${item.label || ''}" required>
                </td>
                <td class="px-4 py-2">
                    <input type="text" name="value[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Value" value="${item.value || 0}" required>
                </td>
                <td class="px-4 py-2">
                    <input type="color" name="color[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${item.color || '#FF6384'}">
                </td>
                <td class="px-4 py-2 text-center">
                    <button type="button" onclick="removePieRow(this, 'update')" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
                    tableBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error populating pie update form:', error);
            }
        }

        // Populate bar chart update form
        function populateBarUpdateForm(data) {
            try {
                // Determine number of series
                if (!Array.isArray(data) || data.length === 0) {
                    console.error('Invalid data format for bar chart');
                    return;
                }

                const firstItem = data[0];
                const seriesCount = Object.keys(firstItem).filter(key => key.startsWith('series')).length;

                document.getElementById('seriesCountUpdate').value = seriesCount;
                updateSeriesInputs('update');

                const tableBody = document.getElementById('barTableBodyUpdate');
                tableBody.innerHTML = '';

                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.className = 'border-b border-gray-200';

                    let rowHtml = `
                <td class="px-4 py-2">
                    <input type="text" name="bar_category[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Category" value="${item.category || ''}" required>
                </td>
            `;

                    for (let i = 1; i <= seriesCount; i++) {
                        const seriesKey = `series${i}`;
                        rowHtml += `
                    <td class="px-4 py-2">
                        <input type="text" name="bar_series${i}[]" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Value" value="${item[seriesKey] || 0}" required>
                    </td>
                `;
                    }

                    rowHtml += `
                <td class="px-4 py-2 text-center">
                    <button type="button" onclick="removeBarRow(this, 'update')" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

                    row.innerHTML = rowHtml;
                    tableBody.appendChild(row);
                });

                // Populate series labels and colors
                for (let i = 1; i <= seriesCount; i++) {
                    const labelKey = `series${i}_label`;
                    const colorKey = `series${i}_color`;
                    const labelInput = document.querySelector(`input[name="series${i}Label"]`);
                    const colorInput = document.querySelector(`input[name="series${i}Color"]`);

                    if (labelInput) labelInput.value = firstItem[labelKey] || `Series ${i}`;
                    if (colorInput) colorInput.value = firstItem[colorKey] || getChartColor(i - 1);
                }
            } catch (error) {
                console.error('Error populating bar update form:', error);
            }
        }

        // Pie chart functions
        function addPieRow(graphNum = '') {
            // Determine the correct table body ID based on graphNum
            let tableBodyId = 'pieTableBody';
            if (graphNum === 'update') {
                tableBodyId = 'pieTableBodyUpdate';
            } else if (graphNum) {
                tableBodyId = 'pieTableBody' + graphNum;
            }

            const tableBody = document.getElementById(tableBodyId);
            if (!tableBody) {
                console.error('Table body not found:', tableBodyId);
                return;
            }

            const newRow = document.createElement('tr');
            newRow.className = 'border-b border-gray-200';

            // Use different default colors for each graph
            let colorIndex = 0;
            if (graphNum === 'update') {
                colorIndex = 0; // Default color for update modal
            } else if (graphNum) {
                colorIndex = parseInt(graphNum) - 1;
            }

            // Ensure colorIndex is a valid number
            if (isNaN(colorIndex) || colorIndex < 0) {
                colorIndex = 0;
            }

            const defaultColor = getChartColor(colorIndex);

            newRow.innerHTML = `
                    <td class="px-4 py-2">
                        <input type="text" name="label${graphNum ? graphNum + '[]' : '[]'}" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Label" required>
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="value${graphNum ? graphNum + '[]' : '[]'}" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Value" required>
                    </td>
                    <td class="px-4 py-2">
                        <input type="color" name="color${graphNum ? graphNum + '[]' : '[]'}" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${defaultColor}">
                    </td>
                    <td class="px-4 py-2 text-center">
                        <button type="button" onclick="removePieRow(this, '${graphNum}')" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
            tableBody.appendChild(newRow);
        }

        function removePieRow(button, graphNum = '') {
            const row = button.closest('tr');

            // Determine the correct table body ID based on graphNum
            let tableBodyId = 'pieTableBody';
            if (graphNum === 'update') {
                tableBodyId = 'pieTableBodyUpdate';
            } else if (graphNum) {
                tableBodyId = 'pieTableBody' + graphNum;
            }

            const tableBody = document.getElementById(tableBodyId);

            if (tableBody && tableBody.children.length > 1) {
                row.remove();
            }
        }

        // Bar chart functions
        function updateSeriesInputs(graphNum = '') {
            const seriesCount = document.getElementById('seriesCount' + graphNum).value;
            const container = document.getElementById('seriesLabelsContainer' + graphNum);
            const tableHeader = document.getElementById('barTableHeader' + graphNum);

            container.innerHTML = '';
            tableHeader.innerHTML = '<th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Category</th>';

            for (let i = 0; i < seriesCount; i++) {
                const seriesDiv = document.createElement('div');
                // Use different default colors for each graph
                const defaultColor = getChartColor((parseInt(graphNum) - 1) * 5 + i);

                seriesDiv.innerHTML = `
                    <input type="text" name="series${i + 1}Label${graphNum}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" 
                           placeholder="Series ${i + 1} Label">
                    <div class="mt-2 flex items-center">
                        <label class="text-sm text-gray-600 mr-2">Color:</label>
                        <input type="color" name="series${i + 1}Color${graphNum}" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${defaultColor}">
                    </div>
                `;
                container.appendChild(seriesDiv);

                tableHeader.innerHTML += `<th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Series ${i + 1}</th>`;
            }

            tableHeader.innerHTML += '<th class="px-4 py-2 text-center text-sm font-semibold text-gray-700">Actions</th>';

            // Reset table body
            const tableBody = document.getElementById('barTableBody' + graphNum);
            tableBody.innerHTML = '';
            addBarRow(graphNum);
        }

        function addBarRow(graphNum = '') {
            // Determine the correct table body ID based on graphNum
            let tableBodyId = 'barTableBody';
            if (graphNum === 'update') {
                tableBodyId = 'barTableBodyUpdate';
            } else if (graphNum) {
                tableBodyId = 'barTableBody' + graphNum;
            }

            const tableBody = document.getElementById(tableBodyId);
            if (!tableBody) {
                console.error('Table body not found:', tableBodyId);
                return;
            }

            // Determine the correct series count ID based on graphNum
            let seriesCountId = 'seriesCount';
            if (graphNum === 'update') {
                seriesCountId = 'seriesCountUpdate';
            } else if (graphNum) {
                seriesCountId = 'seriesCount' + graphNum;
            }

            const seriesCountElement = document.getElementById(seriesCountId);
            if (!seriesCountElement) {
                console.error('Series count element not found:', seriesCountId);
                return;
            }

            const seriesCount = seriesCountElement.value;

            const newRow = document.createElement('tr');
            newRow.className = 'border-b border-gray-200';

            let rowHtml = `
                    <td class="px-4 py-2">
                        <input type="text" name="bar_category${graphNum ? graphNum + '[]' : '[]'}" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Category" required>
                    </td>
                `;

            for (let i = 0; i < seriesCount; i++) {
                rowHtml += `
                        <td class="px-4 py-2">
                            <input type="text" name="bar_series${i + 1}${graphNum ? graphNum + '[]' : '[]'}" class="w-full px-2 py-1 border border-gray-300 rounded" placeholder="Value" required>
                        </td>
                    `;
            }

            rowHtml += `
                    <td class="px-4 py-2 text-center">
                        <button type="button" onclick="removeBarRow(this, '${graphNum}')" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;

            newRow.innerHTML = rowHtml;
            tableBody.appendChild(newRow);
        }

        function removeBarRow(button, graphNum = '') {
            const row = button.closest('tr');

            // Determine the correct table body ID based on graphNum
            let tableBodyId = 'barTableBody';
            if (graphNum === 'update') {
                tableBodyId = 'barTableBodyUpdate';
            } else if (graphNum) {
                tableBodyId = 'barTableBody' + graphNum;
            }

            const tableBody = document.getElementById(tableBodyId);

            if (tableBody && tableBody.children.length > 1) {
                row.remove();
            }
        }

        function getChartColor(index) {
            const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8A2BE2', '#20B2AA', '#FF69B4', '#7B68EE'];
            return colors[index % colors.length];
        }

        // Action functions
        function editGraph(graphId) {
            showUpdateModal(graphId);
        }

        function deleteGraph() {
            if (currentGraphId) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', currentGraphId);

                fetch(moduleUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeModal('actionModal');
                            if (window.reloadModule) {
                                window.reloadModule();
                            }
                            if (window.showNotification) {
                                window.showNotification(data.message, 'success');
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error deleting graph: ' + error.message);
                    });
            }
        }

        function archiveGraph() {
            if (currentGraphId) {
                const formData = new FormData();
                formData.append('action', 'archive');
                formData.append('id', currentGraphId);

                fetch(moduleUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeModal('actionModal');
                            if (window.reloadModule) {
                                window.reloadModule();
                            }
                            if (window.showNotification) {
                                window.showNotification(data.message, 'success');
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error archiving graph: ' + error.message);
                    });
            }
        }

        // Form submissions
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            fetch(moduleUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('uploadModal');
                        if (window.reloadModule) {
                            window.reloadModule(data.graph_id);
                        }
                        if (window.showNotification) {
                            window.showNotification(data.message, 'success');
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error uploading graph: ' + error.message);
                });
        });

        document.getElementById('addForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            fetch(moduleUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('addModal');
                        if (window.reloadModule) {
                            window.reloadModule(data.graph_id);
                        }
                        if (window.showNotification) {
                            window.showNotification(data.message, 'success');
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error creating graph: ' + error.message);
                });
        });

        document.getElementById('groupAddForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_group');

            fetch(moduleUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('groupAddModal');
                        if (window.reloadModule) {
                            window.reloadModule();
                        }
                        if (window.showNotification) {
                            window.showNotification(data.message, 'success');
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error creating graph group: ' + error.message);
                });
        });

        document.getElementById('updateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update');

            console.log('Submitting update form with data:');
            console.log('Action: update');
            console.log('Graph ID:', formData.get('id'));
            console.log('Title:', formData.get('title'));
            console.log('Type:', formData.get('type'));

            fetch(moduleUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text(); // Get as text first to debug
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            closeModal('updateModal');
                            if (window.reloadModule) {
                                // Pass the graph ID and updated data to the reload function
                                window.reloadModule(data.graph_id, data.graph_data);
                            }
                            if (window.showNotification) {
                                window.showNotification(data.message, 'success');
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        console.error('JSON parsing error:', e);
                        console.error('Response text:', text);
                        alert('Error parsing response. Check console for details.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating graph: ' + error.message);
                });
        });

        // Function to destroy a specific chart instance
        function destroyChartInstance(canvasId) {
            if (window.chartInstances && window.chartInstances[canvasId]) {
                try {
                    window.chartInstances[canvasId].destroy();
                    delete window.chartInstances[canvasId];
                    console.log(`Destroyed chart for canvas ${canvasId}`);
                } catch (e) {
                    console.error(`Error destroying chart for canvas ${canvasId}:`, e);
                }
            }
        }

        // Function to initialize a specific chart
        function initializeChart(canvas, graphData = null) {
            try {
                const canvasId = canvas.id;
                const graphId = canvasId.replace('graph', '');
                const graphType = canvas.getAttribute('data-type');

                // Use provided graph data if available, otherwise get from canvas attribute
                const graphDataAttr = graphData || canvas.getAttribute('data-graph');

                // Destroy existing chart instance if it exists
                destroyChartInstance(canvasId);

                if (!graphDataAttr) {
                    console.error(`No data attribute found for canvas ${canvasId}`);
                    return;
                }

                const data = typeof graphDataAttr === 'string' ? JSON.parse(graphDataAttr) : graphDataAttr;

                if (!data || data.length === 0) {
                    console.error(`Invalid or empty data for canvas ${canvasId}`);
                    return;
                }

                console.log(`Initializing ${graphType} chart for canvas ${canvasId}`);

                let chartInstance;
                if (graphType === 'pie') {
                    chartInstance = createPieChart(canvas, data);
                } else {
                    chartInstance = createBarChart(canvas, data);
                }

                // Store the chart instance
                if (chartInstance) {
                    if (!window.chartInstances) {
                        window.chartInstances = {};
                    }
                    window.chartInstances[canvasId] = chartInstance;
                }

                // Mark as initialized
                canvas.setAttribute('data-initialized', 'true');
            } catch (error) {
                console.error(`Error initializing chart for canvas ${canvas.id}:`, error);
            }
        }

        // Function to initialize all charts or a specific chart
        function initializeCharts(targetGraphId = null, graphData = null) {
            console.log('Attempting to initialize charts...');

            // Try multiple times with increasing delays to catch dynamic content
            const attempts = [0, 100, 300, 500, 1000];

            attempts.forEach(delay => {
                setTimeout(() => {
                    let selector = 'canvas[id^="graph"]:not([data-initialized])';

                    // If a specific graph ID is provided, only target that canvas
                    if (targetGraphId) {
                        selector = `#graph${targetGraphId}`;
                    }

                    const canvases = document.querySelectorAll(selector);

                    if (canvases.length > 0) {
                        console.log(`Found ${canvases.length} canvas elements at ${delay}ms delay`);

                        canvases.forEach(canvas => {
                            // If this is the target graph and we have new data, use it
                            const canvasId = canvas.id;
                            const canvasGraphId = canvasId.replace('graph', '');
                            const useGraphData = (targetGraphId && canvasGraphId == targetGraphId) ? graphData : null;

                            initializeChart(canvas, useGraphData);
                        });
                    }
                }, delay);
            });
        }

        function createPieChart(canvas, data) {
            try {
                // Ensure all colors are valid and properly formatted
                const backgroundColors = data.map(item => {
                    if (item.color && /^#[0-9A-F]{6}$/i.test(item.color)) {
                        return item.color;
                    } else {
                        return getChartColor(Math.floor(Math.random() * 10));
                    }
                });

                const chartInstance = new Chart(canvas, {
                    type: 'pie',
                    data: {
                        labels: data.map(item => item.label),
                        datasets: [{
                            data: data.map(item => item.value),
                            backgroundColor: backgroundColors,
                            borderWidth: 1,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
                console.log(`Pie chart created successfully for canvas ${canvas.id}`);
                return chartInstance;
            } catch (error) {
                console.error(`Error creating pie chart for canvas ${canvas.id}:`, error);
                return null;
            }
        }

        function createBarChart(canvas, data) {
            try {
                // Determine number of series
                const firstItem = data[0];
                const seriesCount = Object.keys(firstItem).filter(key => key.startsWith('series')).length;

                // Create datasets
                const datasets = [];
                for (let i = 1; i <= seriesCount; i++) {
                    const seriesKey = `series${i}`;
                    const labelKey = `series${i}_label`;
                    const colorKey = `series${i}_color`;

                    let color = firstItem[colorKey];
                    if (!color || !/^#[0-9A-F]{6}$/i.test(color)) {
                        color = getChartColor(i - 1);
                    }

                    datasets.push({
                        label: firstItem[labelKey] || `Series ${i}`,
                        data: data.map(item => item[seriesKey]),
                        backgroundColor: color,
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    });
                }

                const chartInstance = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.map(item => item.category),
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
                console.log(`Bar chart created successfully for canvas ${canvas.id}`);
                return chartInstance;
            } catch (error) {
                console.error(`Error creating bar chart for canvas ${canvas.id}:`, error);
                return null;
            }
        }

        // Initialize charts in different scenarios
        // 1. If DOM is already loaded (for dynamic content)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            console.log('DOM already loaded, initializing charts with delay');
            setTimeout(initializeCharts, 100);
        }

        // 2. When DOM is loaded (for direct access)
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded event fired, initializing charts');
            initializeCharts();
        });

        // 3. For dynamic content that might load after scripts
        window.addEventListener('load', function() {
            console.log('Window load event fired, initializing charts');
            setTimeout(initializeCharts, 200);
        });

        // 4. Initialize immediately in case everything is ready
        setTimeout(initializeCharts, 50);

        // Override the reloadModule function to handle specific graph refresh
        window.reloadModule = function(graphId, graphData = null) {
            console.log('Reloading module' + (graphId ? ' for graph ' + graphId : ''));

            // If a specific graph ID is provided, only refresh that chart
            if (graphId) {
                const canvas = document.getElementById('graph' + graphId);
                if (canvas) {
                    // Remove the initialized flag to force reinitialization
                    canvas.removeAttribute('data-initialized');

                    // Update the canvas data attribute if new data is provided
                    if (graphData) {
                        canvas.setAttribute('data-graph', JSON.stringify(graphData.data));
                    }

                    // Initialize just this chart with the new data
                    initializeCharts(graphId, graphData ? graphData.data : null);
                }
            } else {
                // Otherwise, refresh all charts
                // Destroy all existing chart instances
                if (window.chartInstances) {
                    Object.keys(window.chartInstances).forEach(canvasId => {
                        destroyChartInstance(canvasId);
                    });
                }

                // Remove initialized flags from all canvases
                document.querySelectorAll('canvas[id^="graph"]').forEach(canvas => {
                    canvas.removeAttribute('data-initialized');
                });

                // Reinitialize all charts
                initializeCharts();
            }
        };
    </script>
<?php
}

function renderGraphCard($graph)
{
    $data = json_decode($graph['data'], true);

    // Debug: Check if data is properly decoded
    if (!$data) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">';
        echo '<strong class="font-bold">Error!</strong> ';
        echo '<span>Invalid graph data for graph ID: ' . $graph['id'] . '</span>';
        echo '</div>';
        return;
    }
?>

    <div class="bg-white border border-gray-200 rounded-lg shadow-md p-5 hover:shadow-lg transition-shadow duration-200">
        <div class="flex justify-between items-start mb-4">
            <h5 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($graph['title']); ?></h5>
            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                <?php echo ucfirst($graph['type']); ?>
            </span>
        </div>

        <div class="h-64 mb-4">
            <canvas id="graph<?php echo $graph['id']; ?>"
                data-type="<?php echo $graph['type']; ?>"
                data-graph='<?php echo json_encode($data); ?>'
                style="width: 100%; height: 100%;"></canvas>
        </div>

        <!-- Data Table for Pie Charts -->
        <?php if ($graph['type'] === 'pie'): ?>
            <div class="mt-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php
                            $total = array_sum(array_column($data, 'value'));
                            foreach ($data as $item):
                                $percentage = $total > 0 ? round(($item['value'] / $total) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($item['label']); ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($item['value']); ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-900"><?php echo $percentage; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">Total</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900"><?php echo $total; ?></td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center pt-3 border-t border-gray-100">
            <div class="text-xs text-gray-500">
                Created: <?php echo date('M j, Y', strtotime($graph['created_at'])); ?>
            </div>

            <div class="flex space-x-2">
                <button onclick="editGraph(<?php echo $graph['id']; ?>)" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-xs rounded-lg">
                    <i class="fas fa-edit mr-1"></i> Edit
                </button>
                <button onclick="showActionModal(<?php echo $graph['id']; ?>)" class="px-3 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white text-xs rounded-lg">
                    <i class="fas fa-trash mr-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
<?php
}

function showError($message)
{
    // For AJAX requests, output JSON error
    if (isset($_POST['action']) || isset($_GET['action'])) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    } else {
        // Display as HTML for non-AJAX requests
        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">';
        echo '<div class="flex">';
        echo '<div class="flex-shrink-0">';
        echo '<i class="fas fa-exclamation-triangle text-red-500"></i>';
        echo '</div>';
        echo '<div class="ml-3">';
        echo '<p class="text-sm text-red-700">' . htmlspecialchars($message) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        displayGraphs($GLOBALS['conn']);
    }
}
?>