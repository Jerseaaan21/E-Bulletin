<?php
// CEIT_Modules/Graph/Graph.php
include "../../db.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
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
    header("Location: ../../logout.php");
    exit;
}
*/

// Use the user's department ID (default to CEIT if not set)
$dept_id = $userDeptId ?: 1;
$dept_acronym = 'default'; // fallback

// Query the departments table to get the acronym
$query = "SELECT acronym FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dept_acronym = $row['acronym'];
}

// Get module ID for Graph
$moduleQuery = "SELECT id FROM modules WHERE name = 'Graph' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Get active graphs from CEIT department
$query = "SELECT dg.*, u.name as user_name 
        FROM main_graph dg 
        LEFT JOIN users u ON dg.user_id = u.id 
        WHERE dg.dept_id = ? AND dg.status = 'active' AND dg.module = ? 
        ORDER BY dg.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $dept_id, $moduleId);
$stmt->execute();
$result = $stmt->get_result();

$activeGraphs = [];
while ($row = $result->fetch_assoc()) {
    $graphData = json_decode($row['data'], true) ?: [];
    $activeGraphs[] = [
        'id' => $row['id'],
        'description' => $row['description'],
        'graph_type' => $row['graph_type'],
        'data' => $graphData,
        'posted_on' => $row['created_at'],
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Get archived graphs from CEIT department
$query = "SELECT dg.*, u.name as user_name 
        FROM main_graph dg 
        LEFT JOIN users u ON dg.user_id = u.id 
        WHERE dg.dept_id = ? AND dg.status = 'archived' AND dg.module = ? 
        ORDER BY dg.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $dept_id, $moduleId);
$stmt->execute();
$result = $stmt->get_result();

$archivedGraphs = [];
while ($row = $result->fetch_assoc()) {
    $graphData = json_decode($row['data'], true) ?: [];
    $archivedGraphs[] = [
        'id' => $row['id'],
        'description' => $row['description'],
        'graph_type' => $row['graph_type'],
        'data' => $graphData,
        'posted_on' => $row['created_at'],
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEIT Graph Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        /* Graph-specific styles */
        .graph-preview {
            width: 100%;
            height: 200px;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9fafb;
        }

        .graph-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        /* Ensure canvas elements in modals are properly sized */
        .graph-modal canvas {
            max-width: 100% !important;
            max-height: 100% !important;
        }

        .graph-modal .graph-modal-body canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .graph-modal-content {
            position: relative;
            background-color: white;
            margin: 2% auto;
            padding: 0;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            min-height: 600px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .graph-modal-header {
            padding: 15px 20px;
            background-color: #f97316;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .graph-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .graph-modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .graph-modal-close:hover {
            transform: scale(1.2);
        }

        .graph-modal-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            min-height: 500px;
            height: calc(90vh - 140px);
        }

        .graph-modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .graph-modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* Status sections */
        .graph-status-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
        }

        .graph-status-section.active {
            background-color: transparent;
            border: none;
        }

        .graph-status-section.archived {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .graph-status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }

        .graph-status-section.active .graph-status-title {
            color: #ea580c;
            border-color: #ea580c;
        }

        .graph-status-section.archived .graph-status-title {
            color: #2563eb;
            border-color: #2563eb;
        }

        /* Notification styles */
        .graph-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            animation: graph-slideIn 0.3s ease-out;
        }

        .graph-notification.success {
            background-color: #22C55E;
        }

        .graph-notification.error {
            background-color: #ef4444;
        }

        .graph-notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes graph-slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .graph-modal-content {
                width: 95%;
                margin: 5% auto;
                min-height: 500px;
            }

            .graph-modal-header {
                padding: 10px 15px;
            }

            .graph-modal-title {
                font-size: 1.2rem;
            }

            .graph-modal-body {
                padding: 15px;
                min-height: 400px;
            }

            .graph-modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .graph-status-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .graph-status-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }
        }

        /* Chart type selector styles */
        .chart-type-option {
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chart-type-option:hover {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
            border-width: 2px !important;
        }

        .chart-type-option.selected {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
            border-width: 2px !important;
        }

        /* More specific selector to override Tailwind classes */
        .chart-type-selector .chart-type-option.selected {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
            border-width: 2px !important;
        }

        /* Upload graph type selector styles */
        .upload-graph-type-option.selected {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
        }
        
        /* Group graph type selector styles */
        .group-graph-type-option.selected {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
        }

        /* Form field styles */
        .form-fieldset {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .form-fieldset legend {
            font-weight: 600;
            color: #374151;
            padding: 0 0.5rem;
        }

        /* Data point row styles */
        .data-point-row {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            align-items: center;
        }

        .data-point-row input {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
        }

        .data-point-row input[type="text"] {
            flex: 1;
        }

        .data-point-row input[type="number"] {
            width: 80px;
        }

        .data-point-row input[type="color"] {
            width: 40px;
            height: 36px;
        }

        /* Grouped graph styles */
        .grouped-graph-container {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
        }

        .grouped-graph-item {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
            position: relative;
        }

        .grouped-graph-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .grouped-graph-item-title {
            font-weight: 600;
            color: #374151;
        }

        .grouped-graph-item-remove {
            color: #ef4444;
            cursor: pointer;
            font-size: 1.2rem;
            transition: transform 0.2s;
        }

        .grouped-graph-item-remove:hover {
            transform: scale(1.2);
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0">
                <i class="fas fa-chart-pie mr-3 w-5"></i> CEIT Graph Management
            </h1>
            <div class="flex space-x-3">
                <button id="graph-upload-btn"
                    class="border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                    <i class="fas fa-upload mr-2"></i> Upload File
                </button>
                <button id="graph-add-btn"
                    class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                    <i class="fas fa-plus mr-2"></i> Add Graph
                </button>
            </div>
        </div>

        <!-- Active Graphs -->
        <div class="graph-status-section active">
            <h2 class="graph-status-title">Active Graphs</h2>
            <?php if (count($activeGraphs) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($activeGraphs as $index => $graph): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-orange-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div class="graph-preview">
                                    <canvas id="graph-preview-active-<?= $index ?>"></canvas>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                    <?= htmlspecialchars($graph['description']) ?>
                                </div>
                                <p class="card-text text-gray-600 text-sm truncate">
                                    <?= ucfirst($graph['graph_type']) ?> Graph
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted by: <?= htmlspecialchars($graph['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Posted on: <?= date('F j, Y', strtotime($graph['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="graph-view-full-active-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Graph" data-index="<?= $index ?>" data-status="active">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 graph-edit-btn" data-index="<?= $index ?>" data-id="<?= $graph['id'] ?>" data-description="<?= htmlspecialchars($graph['description']) ?>" data-graph-type="<?= $graph['graph_type'] ?>" data-status="active" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 graph-download-btn" data-index="<?= $index ?>" data-status="active" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 graph-archive-btn" data-index="<?= $index ?>" data-id="<?= $graph['id'] ?>" data-description="<?= htmlspecialchars($graph['description']) ?>" data-status="active" title="Archive">
                                    <i class="fas fa-archive fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-chart-pie fa-3x mb-4"></i>
                    <p class="text-lg">No active graphs yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Graphs -->
        <div class="graph-status-section archived">
            <h2 class="graph-status-title">Archived Graphs</h2>
            <?php if (count($archivedGraphs) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archivedGraphs as $index => $graph): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div class="graph-preview">
                                    <canvas id="graph-preview-archived-<?= $index ?>"></canvas>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                    <?= htmlspecialchars($graph['description']) ?>
                                </div>
                                <p class="card-text text-gray-600 text-sm truncate">
                                    <?= ucfirst($graph['graph_type']) ?> Graph
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted by: <?= htmlspecialchars($graph['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Posted on: <?= date('F j, Y', strtotime($graph['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="graph-view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Graph" data-index="<?= $index ?>" data-status="archived">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 graph-download-btn" data-index="<?= $index ?>" data-status="archived" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 graph-restore-btn" data-index="<?= $index ?>" data-id="<?= $graph['id'] ?>" data-description="<?= htmlspecialchars($graph['description']) ?>" title="Restore">
                                    <i class="fas fa-undo fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 graph-delete-btn" data-index="<?= $index ?>" data-id="<?= $graph['id'] ?>" data-description="<?= htmlspecialchars($graph['description']) ?>" data-status="archived" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-archive fa-3x mb-4"></i>
                    <p class="text-lg">No archived graphs</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Graph View Modals -->
    <?php foreach ($activeGraphs as $index => $graph): ?>
        <div id="graph-modal-active-<?= $index ?>" class="graph-modal">
            <div class="graph-modal-content">
                <div class="graph-modal-header">
                    <h3 class="graph-modal-title"><?= htmlspecialchars($graph['description']) ?></h3>
                    <span class="graph-modal-close" onclick="closeGraphModal('active', <?= $index ?>)">&times;</span>
                </div>
                <div class="graph-modal-body">
                    <div style="width: 100%; height: 100%; min-height: 400px; position: relative;">
                        <canvas id="graph-full-active-<?= $index ?>" style="width: 100% !important; height: 100% !important;"></canvas>
                    </div>
                </div>
                <div class="graph-modal-footer">
                    <div class="graph-modal-meta">
                        Posted by: <?= htmlspecialchars($graph['user_name']) ?> on <?= date('F j, Y', strtotime($graph['posted_on'])) ?> | Type: <?= ucfirst($graph['graph_type']) ?> Graph
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archivedGraphs as $index => $graph): ?>
        <div id="graph-modal-archived-<?= $index ?>" class="graph-modal">
            <div class="graph-modal-content">
                <div class="graph-modal-header">
                    <h3 class="graph-modal-title"><?= htmlspecialchars($graph['description']) ?></h3>
                    <span class="graph-modal-close" onclick="closeGraphModal('archived', <?= $index ?>)">&times;</span>
                </div>
                <div class="graph-modal-body">
                    <div style="width: 100%; height: 100%; min-height: 400px; position: relative;">
                        <canvas id="graph-full-archived-<?= $index ?>" style="width: 100% !important; height: 100% !important;"></canvas>
                    </div>
                </div>
                <div class="graph-modal-footer">
                    <div class="graph-modal-meta">
                        Posted by: <?= htmlspecialchars($graph['user_name']) ?> on <?= date('F j, Y', strtotime($graph['posted_on'])) ?> | Type: <?= ucfirst($graph['graph_type']) ?> Graph
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Upload File Modal -->
    <div id="graph-upload-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">Upload File for Graph</h2>
            
            <!-- Upload Type Tabs -->
            <div class="mb-6">
                <div class="flex border-b border-gray-200">
                    <button type="button" id="individual-upload-tab" class="px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50 rounded-t-lg">
                        Individual Graph
                    </button>
                    <button type="button" id="group-upload-tab" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-t-lg ml-2">
                        Group Graph
                    </button>
                </div>
            </div>
            
            <!-- Individual Graph Upload -->
            <div id="individual-upload-content">
                <!-- Step 1: File Upload -->
                <div id="upload-step-1" class="upload-step">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <input type="file" id="graph-file-input" accept=".csv,.xlsx,.xls" class="hidden">
                            <div id="file-drop-zone" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                                <p class="text-gray-600">Click to select or drag and drop your CSV or Excel file</p>
                                <p class="text-sm text-gray-500 mt-1">Supported formats: CSV, Excel (.xlsx) - Max: 10MB</p>
                            </div>
                            <div id="file-selected" class="hidden">
                                <i class="fas fa-file text-4xl text-green-500 mb-2"></i>
                                <p id="selected-file-name" class="text-gray-800 font-medium"></p>
                                <button type="button" id="change-file-btn" class="text-blue-500 hover:text-blue-700 text-sm mt-2">
                                    Change file
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" id="upload-cancel-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="button" id="upload-process-btn" class="px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded-lg transition duration-200" disabled>
                            <i class="fas fa-arrow-right mr-2"></i> Process File
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Group Graph Upload -->
            <div id="group-upload-content" class="hidden">
                <!-- Step 1: Group Configuration -->
                <div id="group-upload-step-1" class="upload-step">
                    <div class="mb-4">
                        <label for="group-title" class="block text-sm font-medium text-gray-700 mb-2">Group Title</label>
                        <input type="text" id="group-title" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="Enter group title">
                    </div>
                    
                    <div class="mb-4">
                        <label for="number-of-graphs" class="block text-sm font-medium text-gray-700 mb-2">Number of Graphs</label>
                        <select id="number-of-graphs" class="w-full p-3 border border-gray-300 rounded-lg" onchange="generateGroupGraphInputs()">
                            <option value="">Select number of graphs</option>
                            <option value="2">2 Graphs</option>
                            <option value="3">3 Graphs</option>
                            <option value="4">4 Graphs</option>
                            <option value="5">5 Graphs</option>
                        </select>
                    </div>
                    
                    <div id="group-graphs-inputs" class="mb-4">
                        <!-- Dynamic graph inputs will be generated here -->
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" id="group-upload-cancel-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="button" id="group-process-files-btn" class="px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded-lg transition duration-200" disabled>
                            <i class="fas fa-arrow-right mr-2"></i> Process Files
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Graph Type Selection -->
                <div id="group-upload-step-2" class="upload-step hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-3">Choose Graph Type for All Graphs</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="group-graph-type-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer text-center transition-all hover:border-blue-500" data-type="pie">
                                <i class="fas fa-chart-pie text-3xl text-blue-500 mb-2"></i>
                                <h4 class="font-medium">Pie Chart</h4>
                                <p class="text-sm text-gray-600">Perfect for showing parts of a whole</p>
                            </div>
                            <div class="group-graph-type-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer text-center transition-all hover:border-blue-500" data-type="bar">
                                <i class="fas fa-chart-bar text-3xl text-green-500 mb-2"></i>
                                <h4 class="font-medium">Bar Chart</h4>
                                <p class="text-sm text-gray-600">Great for comparing different categories</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="group-upload-back-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <div class="space-x-3">
                            <button type="button" id="group-upload-cancel-step2-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                                Cancel
                            </button>
                            <button type="button" id="group-upload-continue-btn" class="px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded-lg transition duration-200" disabled>
                                <i class="fas fa-arrow-right mr-2"></i> Continue
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Data Configuration -->
                <div id="group-upload-step-3" class="upload-step hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-3">Configure Your Graphs</h3>
                        <p class="text-sm text-gray-600 mb-4">Review and edit the data for each graph in your group.</p>
                        
                        <div id="group-graphs-configuration" class="space-y-6">
                            <!-- Dynamic configuration for each graph will be generated here -->
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="group-upload-back-step3-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <div class="space-x-3">
                            <button type="button" id="group-upload-cancel-step3-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                                Cancel
                            </button>
                            <button type="button" id="group-create-graphs-btn" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i> Create Group Graph
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Individual Graph Steps (Step 2 and 3) -->
            <div id="individual-upload-steps">
                <!-- Step 2: Graph Type Selection -->
                <div id="upload-step-2" class="upload-step hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-3">Choose Graph Type</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="upload-graph-type-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer text-center transition-all hover:border-blue-500" data-type="pie">
                                <i class="fas fa-chart-pie text-3xl text-blue-500 mb-2"></i>
                                <h4 class="font-medium">Pie Chart</h4>
                                <p class="text-sm text-gray-600">Perfect for showing parts of a whole</p>
                            </div>
                            <div class="upload-graph-type-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer text-center transition-all hover:border-blue-500" data-type="bar">
                                <i class="fas fa-chart-bar text-3xl text-green-500 mb-2"></i>
                                <h4 class="font-medium">Bar Chart</h4>
                                <p class="text-sm text-gray-600">Great for comparing different categories</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="upload-back-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <div class="space-x-3">
                            <button type="button" id="upload-cancel-step2-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                                Cancel
                            </button>
                            <button type="button" id="upload-continue-btn" class="px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded-lg transition duration-200" disabled>
                                <i class="fas fa-arrow-right mr-2"></i> Continue
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Data Mapping and Editing -->
                <div id="upload-step-3" class="upload-step hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-3">Configure Your Graph</h3>
                        
                        <!-- Graph Title -->
                        <div class="mb-4">
                            <label for="upload-graph-title-select" class="block text-sm font-medium text-gray-700 mb-2">Graph Title</label>
                            <select id="upload-graph-title-select" class="w-full p-2 border border-gray-300 rounded-md" onchange="handleUploadTitleSelection()">
                                <option value="">Select a title</option>
                                <option value="Faculty Profile">Faculty Profile</option>
                                <option value="Enrollment Trends">Enrollment Trends</option>
                                <option value="Performance Licensure Examination">Performance Licensure Examination</option>
                                <option value="custom">Custom Title</option>
                            </select>
                            <input type="text" id="upload-graph-title" class="w-full p-2 border border-gray-300 rounded-md mt-2 hidden" placeholder="Enter custom graph title">
                        </div>
                        
                        <!-- Column Mapping -->
                        <div id="upload-column-mapping" class="mb-4">
                            <!-- Will be populated dynamically -->
                        </div>
                        
                        <!-- Value Type Selection (for bar charts) -->
                        <div id="upload-value-type-section" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Value Input Type</label>
                            <div class="flex gap-4">
                                <label class="flex items-center">
                                    <input type="radio" name="uploadValueType" value="values" checked class="mr-2" onchange="handleUploadValueTypeChange()">
                                    <span>Whole Values</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="uploadValueType" value="percentages" class="mr-2" onchange="handleUploadValueTypeChange()">
                                    <span>Percentages (%)</span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Choose whether your data contains whole values or percentages</p>
                            <div id="upload-percentage-warning" class="hidden mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                    <span class="text-sm text-yellow-700">
                                        <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Data Preview and Editing -->
                        <div id="upload-data-preview" class="mb-4">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="upload-back-step3-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <div class="space-x-3">
                            <button type="button" id="upload-cancel-step3-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                                Cancel
                            </button>
                            <button type="button" id="upload-create-graph-btn" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i> Create Graph
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Graph Modal -->
    <div id="graph-add-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">Add Graph</h2>
            
            <!-- Chart Type Selector -->
            <div class="chart-type-selector flex gap-3 mb-6">
                <div class="chart-type-option flex-1 p-3 border-2 border-gray-200 rounded-lg cursor-pointer text-center transition-all" onclick="selectChartType('pie')" data-type="pie">
                    <i class="fas fa-chart-pie w-5"></i>
                    <span>Pie Graph</span>
                </div>
                <div class="chart-type-option flex-1 p-3 border-2 border-gray-200 rounded-lg cursor-pointer text-center transition-all" onclick="selectChartType('bar')" data-type="bar">
                    <i class="fas fa-chart-bar w-5"></i>
                    <span>Bar Graph</span>
                </div>
                <div class="chart-type-option flex-1 p-3 border-2 border-gray-200 rounded-lg cursor-pointer text-center transition-all" onclick="selectChartType('group')" data-type="group">
                    <i class="fas fa-layer-group w-5"></i>
                    <span>Grouped Graph</span>
                </div>
            </div>

            <!-- Common Graph Fields -->
            <div id="common-graph-fields" class="mb-6">
                <div class="form-fieldset">
                    <legend>Graph Information</legend>
                    <div class="mb-4">
                        <label for="graph-title-select" class="block text-sm font-medium text-gray-700 mb-2">Graph Title</label>
                        <select id="graph-title-select" name="graphTitleSelect" class="w-full p-2 border border-gray-300 rounded-md" onchange="handleTitleSelection()">
                            <option value="">Select a title</option>
                            <option value="Faculty Profile">Faculty Profile</option>
                            <option value="Enrollment Trends">Enrollment Trends</option>
                            <option value="Performance Licensure Examination">Performance Licensure Examination</option>
                            <option value="custom">Custom Title</option>
                        </select>
                        <input type="text" id="graph-title" name="graphTitle" class="w-full p-2 border border-gray-300 rounded-md mt-2 hidden" placeholder="Enter custom graph title">
                    </div>
                </div>
            </div>

            <!-- Pie Chart Form -->
            <div id="pie-chart-form" class="hidden">
                <div class="form-fieldset">
                    <legend>Pie Graph Data</legend>
                    <div id="pie-data-points" class="mb-4">
                        <div class="data-point-row">
                            <input type="text" placeholder="Label" name="pieLabel[]" class="flex-1 p-2 border border-gray-300 rounded">
                            <input type="number" placeholder="Value" name="pieValue[]" class="w-20 p-2 border border-gray-300 rounded" step="any" min="0">
                            <input type="color" name="pieColor[]" value="#3b82f6" class="w-10 h-10 border border-gray-300 rounded">
                            <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                <i class="fas fa-times" title="Remove Data Point"></i>
                            </button>
                        </div>
                        <div class="data-point-row">
                            <input type="text" placeholder="Label" name="pieLabel[]" class="flex-1 p-2 border border-gray-300 rounded">
                            <input type="number" placeholder="Value" name="pieValue[]" class="w-20 p-2 border border-gray-300 rounded" step="any" min="0">
                            <input type="color" name="pieColor[]" value="#ef4444" class="w-10 h-10 border border-gray-300 rounded">
                            <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                <i class="fas fa-times" title="Remove Data Point"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" id="add-pie-data-btn" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110">
                        <i class="fas fa-plus mr-2"></i> Add Data Point
                    </button>
                </div>
            </div>

            <!-- Bar Chart Form -->
            <div id="bar-chart-form" class="hidden">
                <div class="form-fieldset">
                    <legend>Bar Graph Configuration</legend>
                    <div class="mb-4">
                        <label for="number-of-series" class="block text-sm font-medium text-gray-700 mb-2">Number of Series</label>
                        <input type="number" id="number-of-series" name="numberOfSeries" min="1" max="5" value="1" class="w-full p-2 border border-gray-300 rounded-md" onchange="updateSeriesInputs()">
                    </div>
                </div>

                <div class="form-fieldset">
                    <legend>Series Labels</legend>
                    <div id="series-inputs" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="flex items-center gap-2">
                            <input type="text" placeholder="Series 1 Label" name="seriesLabel[]" value="Series 1" class="flex-1 p-2 border border-gray-300 rounded">
                            <input type="color" name="seriesColor[]" value="#3b82f6" class="w-10 h-10 border border-gray-300 rounded">
                        </div>
                    </div>
                </div>

                <div class="form-fieldset">
                    <legend>Bar Graph Data</legend>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Value Input Type</label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="barValueType" value="values" checked class="mr-2" onchange="handleBarValueTypeChange()">
                                <span>Whole Values</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="barValueType" value="percentages" class="mr-2" onchange="handleBarValueTypeChange()">
                                <span>Percentages (%)</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Choose whether to enter whole values (with or without decimals) or percentages</p>
                    </div>
                    <div id="bar-data-points" class="mb-4">
                        <div class="data-point-row">
                            <input type="text" placeholder="Category" name="barCategory[]" class="flex-1 p-2 border border-gray-300 rounded">
                            <input type="number" placeholder="Series 1" name="barValue[]" class="w-20 p-2 border border-gray-300 rounded" step="any" min="0">
                            <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                <i class="fas fa-times" title="Remove Data Point"></i>
                            </button>
                        </div>
                        <div class="data-point-row">
                            <input type="text" placeholder="Category" name="barCategory[]" class="flex-1 p-2 border border-gray-300 rounded">
                            <input type="number" placeholder="Series 1" name="barValue[]" class="w-20 p-2 border border-gray-300 rounded" step="any" min="0">
                            <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                <i class="fas fa-times" title="Remove Data Point"></i>
                            </button>
                        </div>
                    </div>
                    <div id="bar-percentage-warning" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                            <span class="text-sm text-yellow-700">
                                <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                            </span>
                        </div>
                    </div>
                    <button type="button" id="add-bar-data-btn" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110">
                        <i class="fas fa-plus mr-2"></i> Add Category
                    </button>
                </div>
            </div>

            <!-- Grouped Graph Form -->
            <div id="grouped-graph-form" class="hidden">
                <div class="form-fieldset">
                    <legend>Graphs in Group</legend>
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm font-medium text-gray-700">Individual Graphs</span>
                        <button type="button" id="add-graph-to-group-btn" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110">
                            <i class="fas fa-plus mr-2"></i> Add Graph
                        </button>
                    </div>
                    <div id="grouped-graphs-container" class="grouped-graph-container">
                        <!-- Initial graph will be added here -->
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" id="graph-cancel-add-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
                    Cancel
                </button>
                <button type="submit" id="graph-save-btn" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
                    <i class="fas fa-save fa-sm"></i> Save Graph
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Graph Modal -->
    <div id="graph-edit-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">Edit Graph</h2>
            
            <!-- Edit Mode Tabs -->
            <div class="mb-6">
                <div class="flex border-b border-gray-200">
                    <button type="button" id="edit-manual-tab" class="px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50 rounded-t-lg">
                        Manual Edit
                    </button>
                    <button type="button" id="edit-upload-tab" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-t-lg ml-2">
                        Upload New File
                    </button>
                </div>
            </div>
            
            <!-- Manual Edit Form -->
            <div id="edit-manual-content">
                <form id="graph-edit-form">
                    <input type="hidden" id="graph-edit-id" name="id">
                    <input type="hidden" id="graph-edit-status" name="status">
                    <input type="hidden" id="graph-edit-original-type" name="originalType">
                    
                    <!-- Chart Type Display (Read-only) -->
                    <div class="chart-type-display mb-6">
                        <div id="edit-current-chart-type" class="flex justify-center">
                            <!-- Current chart type will be displayed here -->
                        </div>
                    </div>

                    <!-- Common Graph Fields -->
                    <div id="edit-common-graph-fields" class="mb-6">
                        <div class="form-fieldset">
                            <legend>Graph Information</legend>
                            <div class="mb-4">
                                <label for="graph-edit-title-select" class="block text-sm font-medium text-gray-700 mb-2">Graph Title</label>
                                <select id="graph-edit-title-select" name="graphTitleSelect" class="w-full p-2 border border-gray-300 rounded-md" onchange="handleEditTitleSelection()">
                                    <option value="">Select a title</option>
                                    <option value="Faculty Profile">Faculty Profile</option>
                                    <option value="Enrollment Trends">Enrollment Trends</option>
                                    <option value="Performance Licensure Examination">Performance Licensure Examination</option>
                                    <option value="custom">Custom Title</option>
                                </select>
                                <input type="text" id="graph-edit-title" name="graphTitle" class="w-full p-2 border border-gray-300 rounded-md mt-2 hidden" placeholder="Enter custom graph title">
                            </div>
                        </div>
                    </div>

                    <!-- Pie Chart Form -->
                    <div id="edit-pie-chart-form" class="hidden">
                        <div class="form-fieldset">
                            <legend>Pie Graph Data</legend>
                            <div id="edit-pie-data-points" class="mb-4">
                                <!-- Data points will be populated here -->
                            </div>
                            <button type="button" id="edit-add-pie-data-btn" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110">
                                <i class="fas fa-plus mr-2"></i> Add Data Point
                            </button>
                        </div>
                    </div>

                    <!-- Bar Chart Form -->
                    <div id="edit-bar-chart-form" class="hidden">
                        <div class="form-fieldset">
                            <legend>Bar Graph Configuration</legend>
                            <div class="mb-4">
                                <label for="edit-number-of-series" class="block text-sm font-medium text-gray-700 mb-2">Number of Series</label>
                                <input type="number" id="edit-number-of-series" name="numberOfSeries" min="1" max="5" value="1" class="w-full p-2 border border-gray-300 rounded-md" onchange="updateEditSeriesInputs()">
                            </div>
                        </div>

                        <div class="form-fieldset">
                            <legend>Series Labels</legend>
                            <div id="edit-series-inputs" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <!-- Series inputs will be populated here -->
                            </div>
                        </div>

                        <div class="form-fieldset">
                            <legend>Bar Graph Data</legend>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Value Input Type</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="editBarValueType" value="values" checked class="mr-2" onchange="handleEditBarValueTypeChange()">
                                        <span>Whole Values</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="editBarValueType" value="percentages" class="mr-2" onchange="handleEditBarValueTypeChange()">
                                        <span>Percentages (%)</span>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Choose whether to enter whole values (with or without decimals) or percentages</p>
                            </div>
                            <div id="edit-bar-data-points" class="mb-4">
                                <!-- Data points will be populated here -->
                            </div>
                            <div id="edit-bar-percentage-warning" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                    <span class="text-sm text-yellow-700">
                                        <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                                    </span>
                                </div>
                            </div>
                            <button type="button" id="edit-add-bar-data-btn" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110">
                                <i class="fas fa-plus mr-2"></i> Add Category
                            </button>
                        </div>
                    </div>

                    <!-- Grouped Graph Form -->
                    <div id="edit-grouped-graph-form" class="hidden">
                        <div class="form-fieldset">
                            <legend>Graphs in Group</legend>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-medium text-gray-700">Individual Graphs</span>
                                <button type="button" id="edit-add-graph-to-group-btn" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110">
                                    <i class="fas fa-plus mr-2"></i> Add Graph
                                </button>
                            </div>
                            <div id="edit-grouped-graphs-container" class="grouped-graph-container">
                                <!-- Grouped graphs will be populated here -->
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" id="graph-cancel-edit-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
                            Cancel
                        </button>
                        <button type="submit" id="graph-save-edit-btn" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
                            <i class="fas fa-save fa-sm"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Upload New File Content -->
            <div id="edit-upload-content" class="hidden">
                <form id="graph-edit-upload-form" enctype="multipart/form-data">
                    <input type="hidden" id="graph-edit-upload-id" name="id">
                    <input type="hidden" id="graph-edit-upload-status" name="status">
                    
                    <!-- Graph Title -->
                    <div class="mb-4">
                        <label for="edit-upload-graph-title-select" class="block text-sm font-medium text-gray-700 mb-2">Graph Title</label>
                        <select id="edit-upload-graph-title-select" name="title" class="w-full p-2 border border-gray-300 rounded-md" onchange="handleEditUploadTitleSelection()">
                            <option value="">Select a title</option>
                            <option value="Faculty Profile">Faculty Profile</option>
                            <option value="Enrollment Trends">Enrollment Trends</option>
                            <option value="Performance Licensure Examination">Performance Licensure Examination</option>
                            <option value="custom">Custom Title</option>
                        </select>
                        <input type="text" id="edit-upload-graph-title" name="customTitle" class="w-full p-2 border border-gray-300 rounded-md mt-2 hidden" placeholder="Enter custom graph title">
                    </div>
                    
                    <!-- Graph Type Selection -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Graph Type</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="edit-upload-graph-type-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer text-center transition-all hover:border-blue-500" data-type="pie">
                                <i class="fas fa-chart-pie text-3xl text-blue-500 mb-2"></i>
                                <h4 class="font-medium">Pie Chart</h4>
                                <p class="text-sm text-gray-600">Perfect for showing parts of a whole</p>
                            </div>
                            <div class="edit-upload-graph-type-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer text-center transition-all hover:border-blue-500" data-type="bar">
                                <i class="fas fa-chart-bar text-3xl text-green-500 mb-2"></i>
                                <h4 class="font-medium">Bar Chart</h4>
                                <p class="text-sm text-gray-600">Great for comparing different categories</p>
                            </div>
                        </div>
                        <input type="hidden" id="edit-upload-graph-type" name="graphType" value="">
                    </div>
                    
                    <!-- File Upload -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select New File</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <input type="file" id="edit-graph-file-input" name="file" accept=".csv,.xlsx,.xls" class="hidden">
                            <div id="edit-file-drop-zone" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                                <p class="text-gray-600">Click to select or drag and drop your CSV or Excel file</p>
                                <p class="text-sm text-gray-500 mt-1">Supported formats: CSV, Excel (.xlsx) - Max: 10MB</p>
                            </div>
                            <div id="edit-file-selected" class="hidden">
                                <i class="fas fa-file text-4xl text-green-500 mb-2"></i>
                                <p id="edit-selected-file-name" class="text-gray-800 font-medium"></p>
                                <button type="button" id="edit-change-file-btn" class="text-blue-500 hover:text-blue-700 text-sm mt-2">
                                    Change file
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" id="graph-cancel-edit-upload-btn" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
                            Cancel
                        </button>
                        <button type="submit" id="graph-save-edit-upload-btn" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" disabled>
                            <i class="fas fa-save fa-sm"></i> Update with New File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal for Pending Graphs -->
    <div id="graph-delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Delete Graph</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to delete this graph?</p>
                <p class="font-semibold mt-2" id="graph-delete-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="cancel-graph-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="confirm-graph-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Active Graph Archive/Delete Modal -->
    <div id="active-graph-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-yellow-600">Archive or Delete Graph</h3>
            </div>
            <div class="mb-4">
                <p>What actions do you want?</p>
                <p class="font-semibold mt-2" id="active-graph-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="cancel-active-graph-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="archive-active-graph-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="delete-active-graph-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Archived Graph Restore Modal -->
    <div id="archived-graph-restore-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-green-600">Restore Graph</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to restore this graph?</p>
                <p class="font-semibold mt-2" id="archived-graph-restore-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="cancel-archived-graph-restore-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="confirm-archived-graph-restore-btn">
                    <i class="fas fa-undo mr-2"></i> Restore
                </button>
            </div>
        </div>
    </div>

    <!-- Archived Graph Delete Modal -->
    <div id="archived-graph-delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Delete Graph</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to delete this graph?</p>
                <p class="font-semibold mt-2" id="archived-graph-delete-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="cancel-archived-graph-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="confirm-archived-graph-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables for graph handling
        window.graphCharts = {};
        let selectedChartType = '';
        let selectedEditChartType = '';
        let graphInGroupCount = 0;
        let editGraphInGroupCount = 0;
        
        // Upload-specific variables
        let uploadedFileData = null;
        let selectedUploadGraphType = '';
        let uploadStep = 1;
        
        // Group upload variables
        let selectedGroupGraphType = '';
        let groupUploadStep = 1;
        let groupGraphsData = [];
        let numberOfGroupGraphs = 0;
        
        // Global function to get the correct path for CEIT_Modules/Graph files
        function getCEITGraphPath(filename) {
            // Check if we're being loaded directly or through Main_dashboard.php
            const currentPath = window.location.pathname;
            let path;
            
            console.log('Current path:', currentPath);
            
            if (currentPath.includes('Main_dashboard.php') || currentPath.includes('dept_dashboard.php')) {
                // We're being loaded through a dashboard, use full path
                path = 'CEIT_Modules/Graph/' + filename;
            } else if (currentPath.includes('/Testing/')) {
                // We're in the Testing directory, need to include the full path
                path = 'Testing/CEIT_Modules/Graph/' + filename;
            } else {
                // Try to detect the base path from the current URL
                const pathParts = currentPath.split('/');
                const graphIndex = pathParts.indexOf('Graph');
                
                if (graphIndex > 0) {
                    // Build path up to the Graph directory
                    const basePath = pathParts.slice(0, graphIndex).join('/');
                    path = basePath + '/Graph/' + filename;
                    // Remove leading slash if present
                    path = path.replace(/^\/+/, '');
                } else {
                    // Fallback: assume we're in the project root
                    path = 'CEIT_Modules/Graph/' + filename;
                }
            }
            
            console.log('getCEITGraphPath for', filename, ':', path);
            return path;
        }

        // Global variable to track active requests
        const graphActiveRequests = {
            archive: false,
            delete: false,
            restore: false
        };

        // Global variable to track graph being acted upon
        let graphToAction = null;
        let graphToActionData = null; // Store additional data like status

        // Store graph data for each status
        const graphData = {
            active: <?php echo json_encode($activeGraphs); ?>,
            archived: <?php echo json_encode($archivedGraphs); ?>
        };

        // Helper function to create charts with optional DataLabels plugin
        function createChartWithDataLabels(ctx, config) {
            // Check if ChartDataLabels is available
            if (typeof ChartDataLabels !== 'undefined') {
                // Add the plugin to the config if it's not already there
                if (!config.plugins) {
                    config.plugins = [ChartDataLabels];
                } else if (!config.plugins.includes(ChartDataLabels)) {
                    config.plugins.push(ChartDataLabels);
                }
            } else {
                // Remove datalabels configuration if plugin is not available
                if (config.options && config.options.plugins && config.options.plugins.datalabels) {
                    delete config.options.plugins.datalabels;
                }
                // Remove plugins array if it only contained ChartDataLabels
                if (config.plugins && config.plugins.length === 1 && config.plugins[0] === ChartDataLabels) {
                    delete config.plugins;
                }
            }
            
            return new Chart(ctx, config);
        }

        // Helper function to format values based on data type
        function formatChartValue(value, isPercentage = false) {
            if (isPercentage) {
                return value + '%';
            }
            return value;
        }

        // Helper function to determine if data should be treated as percentages
        function shouldTreatAsPercentage(graphData) {
            // Check if the graph data has a valueType property
            if (graphData.valueType === 'percentages') {
                return true;
            }
            
            // Fallback: check if values look like percentages
            let values = [];
            if (graphData.values) {
                values = Array.isArray(graphData.values[0]) ? graphData.values.flat() : graphData.values;
            }
            
            if (values.length === 0) return false;
            
            // If most values are between 0-100 and we have decimals, likely percentages
            const percentageLikeCount = values.filter(val => {
                const num = parseFloat(val);
                return !isNaN(num) && num >= 0 && num <= 100;
            }).length;
            
            return (percentageLikeCount / values.length) > 0.7;
        }

        // Function to initialize the module
        function initializeGraphModule() {
            console.log('Initializing Graph module...');

            // Check if ChartDataLabels plugin is available
            if (typeof ChartDataLabels === 'undefined') {
                console.warn('ChartDataLabels plugin not available, loading from CDN...');
                // Try to load the plugin dynamically if not available
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2';
                script.onload = function() {
                    console.log('ChartDataLabels plugin loaded successfully');
                    continueInitialization();
                };
                script.onerror = function() {
                    console.error('Failed to load ChartDataLabels plugin');
                    continueInitialization();
                };
                document.head.appendChild(script);
            } else {
                continueInitialization();
            }
        }

        function continueInitialization() {
            // Prevent multiple initializations
            if (window.graphModuleInitialized) {
                console.log('Graph module already initialized, reinitializing...');
                // Force reinitialize graph previews
                initializeGraphPreviews();
                return;
            }

            window.graphModuleInitialized = true;

            // Initialize modal event listeners
            initializeGraphModalEventListeners();

            // Initialize graph previews
            initializeGraphPreviews();

            console.log('Graph module initialized');
        }

        // For direct access (not through dashboard)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(() => {
                // Only initialize if not already initialized by dashboard
                if (!window.graphModuleInitialized) {
                    initializeGraphModule();
                    window.graphModuleInitialized = true;
                }
            }, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => {
                    // Only initialize if not already initialized by dashboard
                    if (!window.graphModuleInitialized) {
                        initializeGraphModule();
                        window.graphModuleInitialized = true;
                    }
                }, 100);
            });
        }

        // Initialize modal event listeners
        function initializeGraphModalEventListeners() {
            console.log('Initializing graph modal event listeners...');
            
            // Remove any existing delegated listeners first to prevent duplicates
            document.removeEventListener('click', handleDelegatedGraphActions);
            
            // Add single delegated event listener for all graph actions
            document.addEventListener('click', handleDelegatedGraphActions);
            
            // Initialize modal-specific listeners only once
            if (!window.graphModalListenersInitialized) {
                initializeModalListeners();
                window.graphModalListenersInitialized = true;
            }
            
            // Reinitialize all graph action buttons
            initializeGraphActionButtons();
        }
        
        // Initialize modal listeners (called only once)
        function initializeModalListeners() {
            console.log('Initializing modal listeners...');
            
            // Tab switching functionality
            const individualTab = document.getElementById('individual-upload-tab');
            const groupTab = document.getElementById('group-upload-tab');
            const individualContent = document.getElementById('individual-upload-content');
            const groupContent = document.getElementById('group-upload-content');
            const individualSteps = document.getElementById('individual-upload-steps');
            
            if (individualTab) {
                individualTab.addEventListener('click', function() {
                    // Switch to individual tab
                    individualTab.classList.add('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    individualTab.classList.remove('text-gray-500');
                    groupTab.classList.remove('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    groupTab.classList.add('text-gray-500');
                    
                    // Show individual content
                    individualContent.classList.remove('hidden');
                    individualSteps.classList.remove('hidden');
                    groupContent.classList.add('hidden');
                    
                    // Reset upload modal to step 1
                    resetUploadModal();
                });
            }
            
            if (groupTab) {
                groupTab.addEventListener('click', function() {
                    // Switch to group tab
                    groupTab.classList.add('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    groupTab.classList.remove('text-gray-500');
                    individualTab.classList.remove('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    individualTab.classList.add('text-gray-500');
                    
                    // Show group content
                    groupContent.classList.remove('hidden');
                    individualContent.classList.add('hidden');
                    individualSteps.classList.add('hidden');
                    
                    // Reset group upload
                    resetGroupUpload();
                });
            }
            
            // File input and drop zone
            const fileInput = document.getElementById('graph-file-input');
            const dropZone = document.getElementById('file-drop-zone');
            const fileSelected = document.getElementById('file-selected');
            const selectedFileName = document.getElementById('selected-file-name');
            const changeFileBtn = document.getElementById('change-file-btn');
            const processBtn = document.getElementById('upload-process-btn');
            
            // File input change
            if (fileInput) {
                fileInput.addEventListener('change', handleFileSelection);
            }
            
            // Drop zone click
            if (dropZone) {
                dropZone.addEventListener('click', () => fileInput.click());
                
                // Drag and drop functionality
                dropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropZone.classList.add('border-blue-500', 'bg-blue-50');
                });
                
                dropZone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('border-blue-500', 'bg-blue-50');
                });
                
                dropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('border-blue-500', 'bg-blue-50');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        handleFileSelection();
                    }
                });
            }
            
            // Change file button
            if (changeFileBtn) {
                changeFileBtn.addEventListener('click', () => {
                    fileInput.click();
                });
            }
            
            // Process file button
            if (processBtn) {
                processBtn.addEventListener('click', processUploadedFile);
            }
            
            // Cancel buttons for individual upload
            const cancelBtns = ['upload-cancel-btn', 'upload-cancel-step2-btn', 'upload-cancel-step3-btn'];
            cancelBtns.forEach(btnId => {
                const btn = document.getElementById(btnId);
                if (btn) {
                    btn.addEventListener('click', () => {
                        const modal = document.getElementById('graph-upload-modal');
                        if (modal) {
                            modal.classList.add('hidden');
                            modal.style.display = 'none';
                        }
                        // Small delay to ensure modal is hidden before reset
                        setTimeout(() => {
                            resetUploadModal();
                        }, 50);
                    });
                }
            });
            
            // Cancel buttons for group upload
            const groupCancelBtns = ['group-upload-cancel-btn', 'group-upload-cancel-step2-btn', 'group-upload-cancel-step3-btn'];
            groupCancelBtns.forEach(btnId => {
                const btn = document.getElementById(btnId);
                if (btn) {
                    btn.addEventListener('click', () => {
                        const modal = document.getElementById('graph-upload-modal');
                        if (modal) {
                            modal.classList.add('hidden');
                            modal.style.display = 'none';
                        }
                        // Small delay to ensure modal is hidden before reset
                        setTimeout(() => {
                            resetUploadModal();
                        }, 50);
                    });
                }
            });
            
            // Back buttons for individual upload
            const backBtn = document.getElementById('upload-back-btn');
            if (backBtn) {
                backBtn.addEventListener('click', () => showUploadStep(1));
            }
            
            const backStep3Btn = document.getElementById('upload-back-step3-btn');
            if (backStep3Btn) {
                backStep3Btn.addEventListener('click', () => showUploadStep(2));
            }
            
            // Back buttons for group upload
            const groupBackBtn = document.getElementById('group-upload-back-btn');
            if (groupBackBtn) {
                groupBackBtn.addEventListener('click', () => showGroupUploadStep(1));
            }
            
            const groupBackStep3Btn = document.getElementById('group-upload-back-step3-btn');
            if (groupBackStep3Btn) {
                groupBackStep3Btn.addEventListener('click', () => showGroupUploadStep(2));
            }
            
            // Continue button for individual upload
            const continueBtn = document.getElementById('upload-continue-btn');
            if (continueBtn) {
                continueBtn.addEventListener('click', () => showUploadStep(3));
            }
            
            // Continue button for group upload
            const groupContinueBtn = document.getElementById('group-upload-continue-btn');
            if (groupContinueBtn) {
                groupContinueBtn.addEventListener('click', () => showGroupUploadStep(3));
            }
            
            // Process files button for group upload
            const groupProcessBtn = document.getElementById('group-process-files-btn');
            if (groupProcessBtn) {
                groupProcessBtn.addEventListener('click', processGroupFiles);
            }
            
            // Graph type selection for individual upload
            document.querySelectorAll('.upload-graph-type-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.upload-graph-type-option').forEach(opt => {
                        opt.classList.remove('border-blue-500', 'bg-blue-50');
                    });
                    this.classList.add('border-blue-500', 'bg-blue-50');
                    selectedUploadGraphType = this.getAttribute('data-type');
                    document.getElementById('upload-continue-btn').disabled = false;
                });
            });
            
            // Graph type selection for group upload
            document.querySelectorAll('.group-graph-type-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.group-graph-type-option').forEach(opt => {
                        opt.classList.remove('border-blue-500', 'bg-blue-50');
                    });
                    this.classList.add('border-blue-500', 'bg-blue-50');
                    selectedGroupGraphType = this.getAttribute('data-type');
                    document.getElementById('group-upload-continue-btn').disabled = false;
                });
            });
            
            // Create graph button for individual upload
            const createGraphBtn = document.getElementById('upload-create-graph-btn');
            if (createGraphBtn) {
                createGraphBtn.addEventListener('click', createGraphFromUpload);
            }
            
            // Create group graph button
            const createGroupGraphBtn = document.getElementById('group-create-graphs-btn');
            if (createGroupGraphBtn) {
                createGroupGraphBtn.addEventListener('click', createGroupGraphFromUpload);
            }
        }
        
        // Function to initialize graph action buttons (edit, archive, delete, restore, download)
        function initializeGraphActionButtons() {
            console.log('Initializing graph action buttons...');
            
            // Add graph button - show modal (simple fix for multiple clicks)
            const addBtn = document.getElementById('graph-add-btn');
            if (addBtn) {
                // Remove any existing click listeners by cloning the button
                const newAddBtn = addBtn.cloneNode(true);
                addBtn.parentNode.replaceChild(newAddBtn, addBtn);
                
                // Add single click listener
                newAddBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Add graph button clicked');
                    const modal = document.getElementById('graph-add-modal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.style.display = 'flex';
                        // Set default selection to pie chart
                        setTimeout(() => {
                            selectChartType('pie');
                        }, 50);
                    }
                });
            }
            
            // Upload button - show upload modal (simple fix for multiple clicks)
            const uploadBtn = document.getElementById('graph-upload-btn');
            if (uploadBtn) {
                // Remove any existing click listeners by cloning the button
                const newUploadBtn = uploadBtn.cloneNode(true);
                uploadBtn.parentNode.replaceChild(newUploadBtn, uploadBtn);
                
                // Add single click listener
                newUploadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Upload graph button clicked');
                    resetUploadModal();
                    const modal = document.getElementById('graph-upload-modal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.style.display = 'flex';
                        // Ensure step 1 is shown when modal opens
                        showUploadStep(1);
                    }
                });
            }

            // Cancel add button - hide modal
            const cancelAddBtn = document.getElementById('graph-cancel-add-btn');
            if (cancelAddBtn) {
                const newCancelAddBtn = cancelAddBtn.cloneNode(true);
                cancelAddBtn.parentNode.replaceChild(newCancelAddBtn, cancelAddBtn);
                
                newCancelAddBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Cancel add button clicked');
                    const modal = document.getElementById('graph-add-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.style.display = 'none';
                        resetAddForm();
                    }
                });
            }

            // Close add modal when clicking outside (only add once)
            const addModal = document.getElementById('graph-add-modal');
            if (addModal && !addModal.hasAttribute('data-outside-click-added')) {
                addModal.addEventListener('click', function(e) {
                    // Only close if clicking directly on the modal backdrop, not on child elements
                    if (e.target === addModal) {
                        console.log('Clicked outside add modal - closing');
                        addModal.classList.add('hidden');
                        addModal.style.display = 'none';
                        resetAddForm();
                    }
                });
                addModal.setAttribute('data-outside-click-added', 'true');
            }

            // Close upload modal when clicking outside (only add once)
            const uploadModal = document.getElementById('graph-upload-modal');
            if (uploadModal && !uploadModal.hasAttribute('data-outside-click-added')) {
                uploadModal.addEventListener('click', function(e) {
                    // Only close if clicking directly on the modal backdrop, not on child elements
                    if (e.target === uploadModal) {
                        console.log('Clicked outside upload modal - closing');
                        uploadModal.classList.add('hidden');
                        uploadModal.style.display = 'none';
                        resetUploadModal();
                    }
                });
                uploadModal.setAttribute('data-outside-click-added', 'true');
            }

            // Save button
            const saveBtn = document.getElementById('graph-save-btn');
            if (saveBtn) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', handleSaveGraph);
            }

            // Edit button functionality - handled by event delegation
            // document.querySelectorAll('.graph-edit-btn').forEach(button => {
            //     button.addEventListener('click', handleEditClick);
            // });

            // Archive button functionality - handled by event delegation
            // document.querySelectorAll('.graph-archive-btn').forEach(button => {
            //     button.addEventListener('click', handleArchiveClick);
            // });

            // Delete button functionality for pending graphs - handled by event delegation
            // document.querySelectorAll('.graph-delete-btn[data-status="pending"]').forEach(button => {
            //     button.addEventListener('click', handlePendingDeleteClick);
            // });

            // Delete button functionality for other graphs - handled by event delegation
            // document.querySelectorAll('.graph-delete-btn:not([data-status="pending"])').forEach(button => {
            //     button.addEventListener('click', handleDeleteClick);
            // });

            // Restore button functionality - handled by event delegation
            // document.querySelectorAll('.graph-restore-btn').forEach(button => {
            //     button.addEventListener('click', handleRestoreClick);
            // });

            // View button functionality - handled by event delegation
            // document.querySelectorAll('[id^="graph-view-full-"]').forEach(button => {
            //     button.addEventListener('click', handleViewClick);
            // });

            // Download button functionality - handled by event delegation
            // document.querySelectorAll('.graph-download-btn').forEach(button => {
            //     button.addEventListener('click', handleDownloadClick);
            // });

            // Edit modal functionality
            const editModal = document.getElementById('graph-edit-modal');
            const editForm = document.getElementById('graph-edit-form');

            // Edit modal tab functionality
            const editManualTab = document.getElementById('edit-manual-tab');
            const editUploadTab = document.getElementById('edit-upload-tab');
            const editManualContent = document.getElementById('edit-manual-content');
            const editUploadContent = document.getElementById('edit-upload-content');
            
            if (editManualTab && editUploadTab) {
                editManualTab.addEventListener('click', function() {
                    // Switch to manual tab
                    editManualTab.classList.add('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    editManualTab.classList.remove('text-gray-500');
                    editUploadTab.classList.remove('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    editUploadTab.classList.add('text-gray-500');
                    
                    // Show manual content
                    editManualContent.classList.remove('hidden');
                    editUploadContent.classList.add('hidden');
                });
                
                editUploadTab.addEventListener('click', function() {
                    // Switch to upload tab
                    editUploadTab.classList.add('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    editUploadTab.classList.remove('text-gray-500');
                    editManualTab.classList.remove('text-blue-600', 'border-blue-600', 'bg-blue-50');
                    editManualTab.classList.add('text-gray-500');
                    
                    // Show upload content
                    editUploadContent.classList.remove('hidden');
                    editManualContent.classList.add('hidden');
                    
                    // Reset upload form
                    resetEditUploadForm();
                });
            }
            
            // Edit upload form functionality
            const editFileInput = document.getElementById('edit-graph-file-input');
            const editDropZone = document.getElementById('edit-file-drop-zone');
            const editFileSelected = document.getElementById('edit-file-selected');
            const editSelectedFileName = document.getElementById('edit-selected-file-name');
            const editChangeFileBtn = document.getElementById('edit-change-file-btn');
            const editUploadForm = document.getElementById('graph-edit-upload-form');
            
            if (editFileInput) {
                editFileInput.addEventListener('change', handleEditFileSelection);
            }
            
            if (editDropZone) {
                editDropZone.addEventListener('click', () => editFileInput.click());
                
                // Drag and drop functionality
                editDropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    editDropZone.classList.add('border-blue-500', 'bg-blue-50');
                });
                
                editDropZone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    editDropZone.classList.remove('border-blue-500', 'bg-blue-50');
                });
                
                editDropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    editDropZone.classList.remove('border-blue-500', 'bg-blue-50');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        editFileInput.files = files;
                        handleEditFileSelection();
                    }
                });
            }
            
            if (editChangeFileBtn) {
                editChangeFileBtn.addEventListener('click', () => editFileInput.click());
            }
            
            // Edit upload graph type selection
            document.querySelectorAll('.edit-upload-graph-type-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.edit-upload-graph-type-option').forEach(opt => {
                        opt.classList.remove('border-blue-500', 'bg-blue-50');
                    });
                    this.classList.add('border-blue-500', 'bg-blue-50');
                    document.getElementById('edit-upload-graph-type').value = this.getAttribute('data-type');
                    updateEditUploadSubmitButton();
                });
            });
            
            // Edit upload form submission
            if (editUploadForm) {
                editUploadForm.addEventListener('submit', handleEditUploadFormSubmit);
            }
            
            // Cancel edit upload button
            const cancelEditUploadBtn = document.getElementById('graph-cancel-edit-upload-btn');
            if (cancelEditUploadBtn) {
                cancelEditUploadBtn.addEventListener('click', function() {
                    editModal.style.display = 'none';
                    editModal.classList.add('hidden');
                    resetEditForm();
                    resetEditUploadForm();
                });
            }

            // Cancel edit button
            const cancelEditBtn = document.getElementById('graph-cancel-edit-btn');
            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', function() {
                    console.log('Cancel edit button clicked');
                    const editModal = document.getElementById('graph-edit-modal');
                    if (editModal) {
                        editModal.style.display = 'none';
                        editModal.classList.add('hidden');
                        resetEditForm();
                        resetEditUploadForm();
                        console.log('Edit modal closed and form reset');
                    }
                });
            }

            // Close edit modal when clicking outside
            if (editModal) {
                editModal.addEventListener('click', function(e) {
                    if (e.target === editModal) {
                        console.log('Clicked outside edit modal - closing');
                        editModal.style.display = 'none';
                        editModal.classList.add('hidden');
                        resetEditForm();
                        resetEditUploadForm();
                    }
                });
            }

            // Edit form submission
            if (editForm) {
                editForm.addEventListener('submit', handleEditFormSubmit);
            }

            // Delete confirmation modal for pending graphs
            const cancelDeleteBtn = document.getElementById('cancel-graph-delete-btn');
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', function() {
                    document.getElementById('graph-delete-modal').style.display = 'none';
                });
            }

            const confirmDeleteBtn = document.getElementById('confirm-graph-delete-btn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', handleConfirmDelete);
            }

            // Active graph modal buttons
            const cancelActiveBtn = document.getElementById('cancel-active-graph-btn');
            if (cancelActiveBtn) {
                cancelActiveBtn.addEventListener('click', function() {
                    document.getElementById('active-graph-modal').style.display = 'none';
                });
            }

            const archiveActiveBtn = document.getElementById('archive-active-graph-btn');
            if (archiveActiveBtn) {
                archiveActiveBtn.addEventListener('click', handleArchiveActive);
            }

            const deleteActiveBtn = document.getElementById('delete-active-graph-btn');
            if (deleteActiveBtn) {
                deleteActiveBtn.addEventListener('click', handleDeleteActive);
            }

            // Archived modals
            setupArchivedModals();

            // Add pie data button
            const addPieDataBtn = document.getElementById('add-pie-data-btn');
            if (addPieDataBtn) {
                addPieDataBtn.addEventListener('click', addPieDataRow);
            }

            // Add bar data button
            const addBarDataBtn = document.getElementById('add-bar-data-btn');
            if (addBarDataBtn) {
                addBarDataBtn.addEventListener('click', addBarDataRow);
            }

            // Add graph to group button
            const addGraphToGroupBtn = document.getElementById('add-graph-to-group-btn');
            if (addGraphToGroupBtn) {
                addGraphToGroupBtn.addEventListener('click', addGraphToGroup);
            }

            // Edit form button event listeners
            const editAddPieDataBtn = document.getElementById('edit-add-pie-data-btn');
            if (editAddPieDataBtn) {
                editAddPieDataBtn.addEventListener('click', addEditPieDataRow);
            }

            const editAddBarDataBtn = document.getElementById('edit-add-bar-data-btn');
            if (editAddBarDataBtn) {
                editAddBarDataBtn.addEventListener('click', addEditBarDataRow);
            }

            const editAddGraphToGroupBtn = document.getElementById('edit-add-graph-to-group-btn');
            if (editAddGraphToGroupBtn) {
                editAddGraphToGroupBtn.addEventListener('click', addEditGraphToGroup);
            }
            
            // Upload modal event listeners are already initialized in initializeModalListeners()
        }

        // Reset add form
        function resetAddForm() {
            selectedChartType = '';
            document.querySelectorAll('#graph-add-modal .chart-type-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.getElementById('common-graph-fields').style.display = 'block';
            document.getElementById('pie-chart-form').classList.add('hidden');
            document.getElementById('bar-chart-form').classList.add('hidden');
            document.getElementById('grouped-graph-form').classList.add('hidden');
            document.getElementById('graph-title-select').value = '';
            document.getElementById('graph-title').classList.add('hidden');
            document.getElementById('graph-title').value = '';
            resetPieDataPoints();
            resetBarDataPoints();
            resetGroupedGraphContainer();
        }

        // Select chart type
        function selectChartType(type) {
            selectedChartType = type;
            
            // Update UI - Remove selected class from all options in add modal
            document.querySelectorAll('#graph-add-modal .chart-type-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to the clicked option in add modal
            const selectedOption = document.querySelector(`#graph-add-modal [data-type="${type}"]`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }
            
            // Show/hide appropriate form sections
            document.getElementById('pie-chart-form').classList.add('hidden');
            document.getElementById('bar-chart-form').classList.add('hidden');
            document.getElementById('grouped-graph-form').classList.add('hidden');
            
            if (type === 'pie') {
                document.getElementById('pie-chart-form').classList.remove('hidden');
                if (document.querySelectorAll('#pie-data-points .data-point-row').length === 0) {
                    // Add initial data points if none exist
                    addPieDataRow();
                    addPieDataRow();
                }
            } else if (type === 'bar') {
                document.getElementById('bar-chart-form').classList.remove('hidden');
                if (document.querySelectorAll('#bar-data-points .data-point-row').length === 0) {
                    // Add initial data points if none exist
                    addBarDataRow();
                    addBarDataRow();
                }
                updateSeriesInputs();
            } else if (type === 'group') {
                document.getElementById('grouped-graph-form').classList.remove('hidden');
                if (document.querySelectorAll('#grouped-graphs-container .grouped-graph-item').length === 0) {
                    // Add initial graph if none exist
                    addGraphToGroup();
                }
            }
        }

        // Handle title selection
        function handleTitleSelection() {
            const select = document.getElementById('graph-title-select');
            const customInput = document.getElementById('graph-title');
            
            if (select.value === 'custom') {
                customInput.classList.remove('hidden');
                customInput.focus();
                customInput.value = '';
            } else {
                customInput.classList.add('hidden');
                customInput.value = select.value;
            }
        }

        // Add pie data row
        function addPieDataRow() {
            const container = document.getElementById('pie-data-points');
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Label" name="pieLabel[]" class="flex-1 p-2 border border-gray-300 rounded">
                <input type="number" placeholder="Value" name="pieValue[]" class="w-20 p-2 border border-gray-300 rounded" step="any" min="0">
                <input type="color" name="pieColor[]" value="${randomColor}" class="w-10 h-10 border border-gray-300 rounded">
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Reset pie data points
        function resetPieDataPoints() {
            const container = document.getElementById('pie-data-points');
            container.innerHTML = '';
        }

        // Handle bar value type change
        function handleBarValueTypeChange() {
            const valueType = document.querySelector('input[name="barValueType"]:checked').value;
            const valueInputs = document.querySelectorAll('#bar-data-points input[name="barValue[]"]');
            const warningDiv = document.getElementById('bar-percentage-warning');
            
            if (valueType === 'percentages') {
                valueInputs.forEach(input => {
                    const currentPlaceholder = input.placeholder;
                    if (!currentPlaceholder.includes('(%)')) {
                        input.placeholder = currentPlaceholder + ' (%)';
                    }
                    input.max = '100';
                });
                warningDiv.classList.remove('hidden');
            } else {
                valueInputs.forEach(input => {
                    input.placeholder = input.placeholder.replace(/ \(%\)/, '');
                    input.removeAttribute('max');
                });
                warningDiv.classList.add('hidden');
            }
        }

        // Handle edit bar value type change
        function handleEditBarValueTypeChange() {
            const valueType = document.querySelector('input[name="editBarValueType"]:checked').value;
            const valueInputs = document.querySelectorAll('#edit-bar-data-points input[name="barValue[]"]');
            const warningDiv = document.getElementById('edit-bar-percentage-warning');
            
            if (valueType === 'percentages') {
                valueInputs.forEach(input => {
                    const currentPlaceholder = input.placeholder;
                    if (!currentPlaceholder.includes('(%)')) {
                        input.placeholder = currentPlaceholder + ' (%)';
                    }
                    input.max = '100';
                });
                warningDiv.classList.remove('hidden');
            } else {
                valueInputs.forEach(input => {
                    input.placeholder = input.placeholder.replace(/ \(%\)/, '');
                    input.removeAttribute('max');
                });
                warningDiv.classList.add('hidden');
            }
        }

        // Add bar data row
        function addBarDataRow() {
            const container = document.getElementById('bar-data-points');
            const numberOfSeries = parseInt(document.getElementById('number-of-series').value) || 1;
            
            // Check current value type
            const valueType = document.querySelector('input[name="barValueType"]:checked').value;
            const suffix = valueType === 'percentages' ? ' (%)' : '';
            const maxAttr = valueType === 'percentages' ? 'max="100"' : '';
            
            let valueInputs = '';
            for (let i = 1; i <= numberOfSeries; i++) {
                valueInputs += `<input type="number" placeholder="S${i}${suffix}" name="barValue[]" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0" ${maxAttr}>`;
            }
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Category" name="barCategory[]" class="flex-1 p-2 border border-gray-300 rounded">
                ${valueInputs}
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Reset bar data points
        function resetBarDataPoints() {
            const container = document.getElementById('bar-data-points');
            container.innerHTML = '';
        }

        // Handle grouped bar value type change
        function handleGroupedBarValueTypeChange(graphIndex) {
            const valueType = document.querySelector(`input[name="groupedBarValueType[${graphIndex}]"]:checked`).value;
            const graphItem = document.querySelector(`.grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const valueInputs = graphItem.querySelectorAll(`input[name="barValue[${graphIndex}][]"]`);
            const warningDiv = document.getElementById(`grouped-bar-percentage-warning-${graphIndex}`);
            
            if (valueType === 'percentages') {
                valueInputs.forEach(input => {
                    const currentPlaceholder = input.placeholder;
                    if (!currentPlaceholder.includes('(%)')) {
                        input.placeholder = currentPlaceholder + ' (%)';
                    }
                    input.max = '100';
                });
                if (warningDiv) warningDiv.classList.remove('hidden');
            } else {
                valueInputs.forEach(input => {
                    input.placeholder = input.placeholder.replace(/ \(%\)/, '');
                    input.removeAttribute('max');
                });
                if (warningDiv) warningDiv.classList.add('hidden');
            }
        }

        // Handle edit grouped bar value type change
        function handleEditGroupedBarValueTypeChange(graphIndex) {
            const valueType = document.querySelector(`input[name="editGroupedBarValueType[${graphIndex}]"]:checked`).value;
            const graphItem = document.querySelector(`#edit-grouped-graphs-container .grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const valueInputs = graphItem.querySelectorAll(`input[name="barValue[${graphIndex}][]"]`);
            const warningDiv = document.getElementById(`edit-grouped-bar-percentage-warning-${graphIndex}`);
            
            if (valueType === 'percentages') {
                valueInputs.forEach(input => {
                    const currentPlaceholder = input.placeholder;
                    if (!currentPlaceholder.includes('(%)')) {
                        input.placeholder = currentPlaceholder + ' (%)';
                    }
                    input.max = '100';
                });
                if (warningDiv) warningDiv.classList.remove('hidden');
            } else {
                valueInputs.forEach(input => {
                    input.placeholder = input.placeholder.replace(/ \(%\)/, '');
                    input.removeAttribute('max');
                });
                if (warningDiv) warningDiv.classList.add('hidden');
            }
        }

        // Update series inputs
        function updateSeriesInputs() {
            const count = parseInt(document.getElementById('number-of-series').value) || 1;
            const container = document.getElementById('series-inputs');
            
            // Preserve existing series labels and colors
            const existingLabels = [];
            const existingColors = [];
            const existingInputs = container.querySelectorAll('input[name="seriesLabel[]"]');
            const existingColorInputs = container.querySelectorAll('input[name="seriesColor[]"]');
            
            existingInputs.forEach(input => {
                existingLabels.push(input.value || '');
            });
            existingColorInputs.forEach(input => {
                existingColors.push(input.value || '');
            });
            
            container.innerHTML = '';
            
            const defaultColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            
            for (let i = 1; i <= count; i++) {
                const existingLabel = existingLabels[i - 1] || `Series ${i}`;
                const existingColor = existingColors[i - 1] || defaultColors[i - 1] || '#' + Math.floor(Math.random()*16777215).toString(16);
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';
                div.innerHTML = `
                    <input type="text" placeholder="Series ${i} Label" name="seriesLabel[]" value="${existingLabel}" class="flex-1 p-2 border border-gray-300 rounded">
                    <input type="color" name="seriesColor[]" value="${existingColor}" class="w-10 h-10 border border-gray-300 rounded">
                `;
                container.appendChild(div);
            }
            
            // Update existing bar data rows
            updateBarDataRowsForSeries(count);
        }

        // Update bar data rows for series count
        function updateBarDataRowsForSeries(seriesCount) {
            const barRows = document.querySelectorAll('#bar-data-points .data-point-row');
            barRows.forEach(row => {
                const categoryInput = row.querySelector('input[name="barCategory[]"]');
                const existingValueInputs = row.querySelectorAll('input[name="barValue[]"]');
                const deleteButton = row.querySelector('button');
                const categoryValue = categoryInput ? categoryInput.value : '';
                
                // Preserve existing values
                const existingValues = [];
                existingValueInputs.forEach(input => {
                    existingValues.push(input.value || '');
                });
                
                let valueInputs = '';
                for (let i = 1; i <= seriesCount; i++) {
                    const existingValue = existingValues[i - 1] || '';
                    valueInputs += `<input type="number" placeholder="S${i}" name="barValue[]" value="${existingValue}" class="w-16 p-1 border border-gray-300 rounded text-sm" step="any" min="0">`;
                }
                
                row.innerHTML = `
                    <input type="text" placeholder="Category" name="barCategory[]" value="${categoryValue}" class="flex-1 p-2 border border-gray-300 rounded">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
            });
        }

        // Add graph to group
        function addGraphToGroup() {
            const container = document.getElementById('grouped-graphs-container');
            const graphItem = document.createElement('div');
            graphItem.className = 'grouped-graph-item';
            graphItem.setAttribute('data-graph-index', graphInGroupCount);
            
            graphItem.innerHTML = `
                <div class="grouped-graph-item-header">
                    <div class="grouped-graph-item-title">Graph ${graphInGroupCount + 1}</div>
                    <i class="fas fa-times grouped-graph-item-remove" onclick="removeGraphFromGroup(this)"></i>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Graph Type</label>
                    <select name="graphType[]" class="w-full p-2 border border-gray-300 rounded" onchange="updateGroupedGraphType(this, ${graphInGroupCount})">
                        <option value="">Select Type</option>
                        <option value="pie">Pie Graph</option>
                        <option value="bar">Bar Graph</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Graph Title</label>
                    <select name="graphTitleSelect[]" class="w-full p-2 border border-gray-300 rounded" onchange="handleGroupedTitleSelection(this, ${graphInGroupCount})">
                        <option value="">Select a title</option>
                        <option value="Faculty Profile">Faculty Profile</option>
                        <option value="Enrollment Trends">Enrollment Trends</option>
                        <option value="Performance Licensure Examination">Performance Licensure Examination</option>
                        <option value="custom">Custom Title</option>
                    </select>
                    <input type="text" name="graphTitle[]" class="w-full p-2 border border-gray-300 rounded mt-2 hidden" placeholder="Enter custom graph title">
                </div>
                <div class="grouped-graph-data"></div>
            `;
            
            container.appendChild(graphItem);
            graphInGroupCount++;
        }

        // Handle grouped title selection
        function handleGroupedTitleSelection(selectElement, graphIndex) {
            const graphItem = selectElement.closest('.grouped-graph-item');
            if (!graphItem) return;
            
            const customInput = graphItem.querySelector('input[name="graphTitle[]"]');
            
            if (selectElement.value === 'custom') {
                customInput.classList.remove('hidden');
                customInput.focus();
                customInput.value = '';
            } else {
                customInput.classList.add('hidden');
                customInput.value = selectElement.value;
            }
        }

        // Remove graph from group
        function removeGraphFromGroup(button) {
            const graphItem = button.closest('.grouped-graph-item');
            if (graphItem) {
                graphItem.remove();
                
                const graphItems = document.querySelectorAll('.grouped-graph-item');
                graphItems.forEach((item, index) => {
                    item.querySelector('.grouped-graph-item-title').textContent = `Graph ${index + 1}`;
                    item.setAttribute('data-graph-index', index);
                    
                    const select = item.querySelector('select[name="graphType[]"]');
                    if (select) {
                        select.setAttribute('onchange', `updateGroupedGraphType(this, ${index})`);
                    }
                });
                
                graphInGroupCount = graphItems.length;
            }
        }

        // Update grouped graph type
        function updateGroupedGraphType(selectElement, graphIndex) {
            const graphItem = selectElement.closest('.grouped-graph-item');
            if (!graphItem) return;
            
            const graphType = selectElement.value;
            const dataContainer = graphItem.querySelector('.grouped-graph-data');
            
            dataContainer.innerHTML = '';
            
            if (graphType === 'pie') {
                dataContainer.innerHTML = `
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Points</label>
                        <div class="grouped-pie-data-points mb-3">
                            <div class="data-point-row">
                                <input type="text" placeholder="Label" name="pieLabel[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                                <input type="number" placeholder="Value" name="pieValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded">
                                <input type="color" name="pieColor[${graphIndex}][]" value="#3b82f6" class="w-10 h-10 border border-gray-300 rounded">
                                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                    <i class="fas fa-times" title="Remove Data Point"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="px-3 py-1 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded text-sm transition duration-200 transform hover:scale-110" onclick="addGroupedPieDataRow(${graphIndex})">
                            <i class="fas fa-plus mr-2"></i> Add Data Point
                        </button>
                    </div>
                `;
            } else if (graphType === 'bar') {
                dataContainer.innerHTML = `
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Number of Series</label>
                        <input type="number" class="grouped-series-count w-full p-2 border border-gray-300 rounded" min="1" max="5" value="1" onchange="updateGroupedSeriesInputs(${graphIndex}, this.value)">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Series Labels</label>
                        <div class="grouped-series-inputs mb-3">
                            <div class="flex items-center gap-2">
                                <input type="text" placeholder="Series 1 Label" name="seriesLabel[${graphIndex}][]" value="Series 1" class="flex-1 p-2 border border-gray-300 rounded">
                                <input type="color" name="seriesColor[${graphIndex}][]" value="#3b82f6" class="w-10 h-10 border border-gray-300 rounded">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Value Input Type</label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="groupedBarValueType[${graphIndex}]" value="values" checked class="mr-2" onchange="handleGroupedBarValueTypeChange(${graphIndex})">
                                <span>Whole Values</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="groupedBarValueType[${graphIndex}]" value="percentages" class="mr-2" onchange="handleGroupedBarValueTypeChange(${graphIndex})">
                                <span>Percentages (%)</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Choose whether to enter whole values (with or without decimals) or percentages</p>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categories</label>
                        <div class="grouped-bar-data-points mb-3">
                            <div class="data-point-row">
                                <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                                <input type="number" placeholder="Series 1" name="barValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded">
                                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                    <i class="fas fa-times" title="Remove Data Point"></i>
                                </button>
                            </div>
                        </div>
                        <div id="grouped-bar-percentage-warning-${graphIndex}" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                <span class="text-sm text-yellow-700">
                                    <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                                </span>
                            </div>
                        </div>
                        <button type="button" class="px-3 py-1 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded text-sm transition duration-200 transform hover:scale-110" onclick="addGroupedBarDataRow(${graphIndex})">
                            <i class="fas fa-plus mr-2"></i> Add Category
                        </button>
                    </div>
                `;
            }
        }

        // Add grouped pie data row
        function addGroupedPieDataRow(graphIndex) {
            const graphItem = document.querySelector(`.grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const container = graphItem.querySelector('.grouped-pie-data-points');
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Label" name="pieLabel[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                <input type="number" placeholder="Value" name="pieValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded">
                <input type="color" name="pieColor[${graphIndex}][]" value="${randomColor}" class="w-10 h-10 border border-gray-300 rounded">
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Add grouped bar data row
        function addGroupedBarDataRow(graphIndex) {
            const graphItem = document.querySelector(`.grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const container = graphItem.querySelector('.grouped-bar-data-points');
            const seriesCount = parseInt(graphItem.querySelector('.grouped-series-count').value) || 1;
            
            // Check current value type
            const valueTypeRadio = graphItem.querySelector(`input[name="groupedBarValueType[${graphIndex}]"]:checked`);
            const valueType = valueTypeRadio ? valueTypeRadio.value : 'values';
            const suffix = valueType === 'percentages' ? ' (%)' : '';
            const maxAttr = valueType === 'percentages' ? 'max="100"' : '';
            
            let valueInputs = '';
            for (let i = 1; i <= seriesCount; i++) {
                valueInputs += `<input type="number" placeholder="S${i}${suffix}" name="barValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0" ${maxAttr}>`;
            }
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                ${valueInputs}
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Update grouped series inputs
        function updateGroupedSeriesInputs(graphIndex, count) {
            const graphItem = document.querySelector(`.grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const container = graphItem.querySelector('.grouped-series-inputs');
            
            // Preserve existing series labels and colors
            const existingLabels = [];
            const existingColors = [];
            const existingInputs = container.querySelectorAll(`input[name="seriesLabel[${graphIndex}][]"]`);
            const existingColorInputs = container.querySelectorAll(`input[name="seriesColor[${graphIndex}][]"]`);
            
            existingInputs.forEach(input => {
                existingLabels.push(input.value || '');
            });
            existingColorInputs.forEach(input => {
                existingColors.push(input.value || '');
            });
            
            container.innerHTML = '';
            
            const defaultColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            
            for (let i = 1; i <= count; i++) {
                const existingLabel = existingLabels[i - 1] || `Series ${i}`;
                const existingColor = existingColors[i - 1] || defaultColors[i - 1] || '#' + Math.floor(Math.random()*16777215).toString(16);
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';
                div.innerHTML = `
                    <input type="text" placeholder="Series ${i} Label" name="seriesLabel[${graphIndex}][]" value="${existingLabel}" class="flex-1 p-2 border border-gray-300 rounded">
                    <input type="color" name="seriesColor[${graphIndex}][]" value="${existingColor}" class="w-10 h-10 border border-gray-300 rounded">
                `;
                container.appendChild(div);
            }
            
            // Update bar data rows
            const barRows = graphItem.querySelectorAll('.grouped-bar-data-points .data-point-row');
            barRows.forEach(row => {
                const categoryInput = row.querySelector(`input[name="barCategory[${graphIndex}][]"]`);
                const existingValueInputs = row.querySelectorAll(`input[name="barValue[${graphIndex}][]"]`);
                const categoryValue = categoryInput ? categoryInput.value : '';
                
                // Preserve existing values
                const existingValues = [];
                existingValueInputs.forEach(input => {
                    existingValues.push(input.value || '');
                });
                
                let valueInputs = '';
                for (let i = 1; i <= count; i++) {
                    const existingValue = existingValues[i - 1] || '';
                    valueInputs += `<input type="number" placeholder="S${i}" name="barValue[${graphIndex}][]" value="${existingValue}" class="w-16 p-1 border border-gray-300 rounded text-sm" step="any" min="0">`;
                }
                
                row.innerHTML = `
                    <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" value="${categoryValue}" class="flex-1 p-2 border border-gray-300 rounded">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
            });
        }

        // Reset grouped graph container
        function resetGroupedGraphContainer() {
            graphInGroupCount = 0;
            const container = document.getElementById('grouped-graphs-container');
            if (container) container.innerHTML = '';
        }

        // Remove data row
        function removeDataRow(button) {
            const row = button.closest('.data-point-row');
            if (row) {
                row.remove();
            }
        }

        // Handle save graph
        function handleSaveGraph() {
            if (!selectedChartType) {
                showGraphNotification('Please select a chart type', 'error');
                return;
            }

            const titleSelect = document.getElementById('graph-title-select');
            const titleInput = document.getElementById('graph-title');
            const title = titleSelect.value === 'custom' ? titleInput.value : titleSelect.value;

            if (!title) {
                showGraphNotification('Please enter a graph title', 'error');
                return;
            }

            let graphData = {};

            if (selectedChartType === 'pie') {
                const labels = Array.from(document.querySelectorAll('input[name="pieLabel[]"]'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const values = Array.from(document.querySelectorAll('input[name="pieValue[]"]'))
                    .map(input => parseFloat(input.value))
                    .filter(val => !isNaN(val) && val !== '');
                
                const colors = Array.from(document.querySelectorAll('input[name="pieColor[]"]'))
                    .map(input => input.value);

                if (labels.length < 2 || values.length < 2) {
                    showGraphNotification('Please add at least 2 data points', 'error');
                    return;
                }

                // Ensure we have the same number of labels, values, and colors
                const minLength = Math.min(labels.length, values.length, colors.length);
                
                graphData = {
                    labels: labels.slice(0, minLength),
                    values: values.slice(0, minLength),
                    colors: colors.slice(0, minLength)
                };
                
            } else if (selectedChartType === 'bar') {
                const categories = Array.from(document.querySelectorAll('input[name="barCategory[]"]'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesLabels = Array.from(document.querySelectorAll('input[name="seriesLabel[]"]'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesColors = Array.from(document.querySelectorAll('input[name="seriesColor[]"]'))
                    .map(input => input.value);
                
                const values = Array.from(document.querySelectorAll('input[name="barValue[]"]'))
                    .map(input => parseFloat(input.value) || 0);

                if (categories.length < 2) {
                    showGraphNotification('Please add at least 2 categories', 'error');
                    return;
                }

                // Check if user selected percentage input type
                const valueType = document.querySelector('input[name="barValueType"]:checked').value;
                if (valueType === 'percentages') {
                    // Validate percentage values are between 0 and 100
                    const invalidValues = values.filter(val => val < 0 || val > 100);
                    if (invalidValues.length > 0) {
                        showGraphNotification('Percentage values must be between 0% and 100%', 'error');
                        return;
                    }
                }

                const seriesCount = seriesLabels.length;
                const filteredValues = [];
                
                // Process values in groups of seriesCount
                for (let i = 0; i < values.length; i += seriesCount) {
                    const categoryValues = values.slice(i, i + seriesCount);
                    if (categoryValues.length === seriesCount) {
                        filteredValues.push(categoryValues);
                    }
                }

                graphData = {
                    categories: categories.slice(0, filteredValues.length),
                    seriesLabels: seriesLabels,
                    seriesColors: seriesColors,
                    values: filteredValues
                };
                
            } else if (selectedChartType === 'group') {
                const graphs = [];
                const graphItems = document.querySelectorAll('.grouped-graph-item');

                for (let i = 0; i < graphItems.length; i++) {
                    const graphItem = graphItems[i];
                    const graphType = graphItem.querySelector('select[name="graphType[]"]').value;
                    
                    if (!graphType) {
                        showGraphNotification(`Please select a type for Graph ${i + 1}`, 'error');
                        return;
                    }

                    const titleSelect = graphItem.querySelector(`select[name="graphTitleSelect[]"]`);
                    const titleInput = graphItem.querySelector(`input[name="graphTitle[]"]`);
                    const graphTitle = titleSelect.value === 'custom' ? titleInput.value.trim() : titleSelect.value.trim();

                    if (!graphTitle) {
                        showGraphNotification(`Please enter a title for Graph ${i + 1}`, 'error');
                        return;
                    }

                    let childGraphData = {};

                    if (graphType === 'pie') {
                        const labels = Array.from(graphItem.querySelectorAll(`input[name="pieLabel[${i}][]"]`))
                            .map(input => input.value.trim())
                            .filter(val => val !== '');
                        
                        const values = Array.from(graphItem.querySelectorAll(`input[name="pieValue[${i}][]"]`))
                            .map(input => parseFloat(input.value))
                            .filter(val => !isNaN(val) && val !== '');
                        
                        const colors = Array.from(graphItem.querySelectorAll(`input[name="pieColor[${i}][]"]`))
                            .map(input => input.value);

                        if (labels.length < 2 || values.length < 2) {
                            showGraphNotification(`Please add at least 2 data points for Graph ${i + 1}`, 'error');
                            return;
                        }

                        const minLength = Math.min(labels.length, values.length, colors.length);
                        
                        childGraphData = {
                            labels: labels.slice(0, minLength),
                            values: values.slice(0, minLength),
                            colors: colors.slice(0, minLength)
                        };
                        
                    } else if (graphType === 'bar') {
                        const categories = Array.from(graphItem.querySelectorAll(`input[name="barCategory[${i}][]"]`))
                            .map(input => input.value.trim())
                            .filter(val => val !== '');
                        
                        const seriesLabels = Array.from(graphItem.querySelectorAll(`input[name="seriesLabel[${i}][]"]`))
                            .map(input => input.value.trim())
                            .filter(val => val !== '');
                        
                        const seriesColors = Array.from(graphItem.querySelectorAll(`input[name="seriesColor[${i}][]"]`))
                            .map(input => input.value);
                        
                        const values = Array.from(graphItem.querySelectorAll(`input[name="barValue[${i}][]"]`))
                            .map(input => parseFloat(input.value) || 0);

                        if (categories.length < 2) {
                            showGraphNotification(`Please add at least 2 categories for Graph ${i + 1}`, 'error');
                            return;
                        }

                        const seriesCount = seriesLabels.length;
                        const filteredValues = [];
                        
                        for (let j = 0; j < values.length; j += seriesCount) {
                            const categoryValues = values.slice(j, j + seriesCount);
                            if (categoryValues.length === seriesCount) {
                                filteredValues.push(categoryValues);
                            }
                        }

                        childGraphData = {
                            categories: categories.slice(0, filteredValues.length),
                            seriesLabels: seriesLabels,
                            seriesColors: seriesColors,
                            values: filteredValues
                        };
                    }

                    graphs.push({
                        title: graphTitle,
                        type: graphType,
                        data: childGraphData
                    });
                }

                if (graphs.length < 2) {
                    showGraphNotification('Please add at least 2 graphs to the group', 'error');
                    return;
                }

                graphData = {
                    graphs: graphs
                };
            }

            // Create form data
            const formData = new FormData();
            formData.append('title', title);
            formData.append('type', selectedChartType);
            formData.append('data', JSON.stringify(graphData));

            // Show loading state
            const saveBtn = document.getElementById('graph-save-btn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            // Use the correct path to AddGraph.php for CEIT_Modules
            // Use absolute path approach to avoid relative path issues
            const addGraphPath = getCEITGraphPath('AddGraph.php');
            
            console.log('Absolute addGraphPath:', addGraphPath);

            fetch(addGraphPath, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Fetch URL:', addGraphPath);
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset form state
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;

                    if (data.success) {
                        showGraphNotification(data.message || 'Graph added successfully!', 'success');
                        setTimeout(() => {
                            document.getElementById('graph-add-modal').classList.add('hidden');
                            location.reload();
                        }, 1500);
                    } else {
                        showGraphNotification(data.message || 'Error adding graph', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                    showGraphNotification('An error occurred while adding the graph: ' + error.message, 'error');
                });
        }

        // Delegated event handler for all graph actions
        function handleDelegatedGraphActions(e) {
            const target = e.target.closest('button');
            if (!target) return;
            
            if (target.classList.contains('graph-edit-btn')) {
                handleEditClick.call(target, e);
            } else if (target.classList.contains('graph-archive-btn')) {
                handleArchiveClick.call(target, e);
            } else if (target.classList.contains('graph-delete-btn')) {
                handleDeleteClick.call(target, e);
            } else if (target.classList.contains('graph-restore-btn')) {
                handleRestoreClick.call(target, e);
            } else if (target.classList.contains('graph-download-btn')) {
                handleDownloadClick.call(target, e);
            } else if (target.id && target.id.startsWith('graph-view-full-')) {
                handleViewClick.call(target, e);
            }
        }

        // Handle edit button click
        function handleEditClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');
            const graphType = this.getAttribute('data-graph-type');
            const status = this.getAttribute('data-status');
            const index = this.getAttribute('data-index');

            console.log('Edit button clicked:', { id, description, graphType, status, index });
            console.log('Button element:', this);

            // First, ensure the modal is hidden and reset
            const editModal = document.getElementById('graph-edit-modal');
            if (editModal) {
                editModal.style.display = 'none';
                editModal.classList.add('hidden');
                resetEditForm();
                resetEditUploadForm();
            }

            // Small delay to ensure reset is complete
            setTimeout(() => {
                // Check if graphData exists and has the required structure
                if (typeof graphData === 'undefined') {
                    console.error('graphData is not defined');
                    showGraphNotification('System error: Graph data not available', 'error');
                    return;
                }

                if (!graphData[status]) {
                    console.error(`Status '${status}' not found in graphData:`, Object.keys(graphData));
                    showGraphNotification('System error: Invalid graph status', 'error');
                    return;
                }

                if (!graphData[status][index]) {
                    console.error(`Graph at index ${index} not found in ${status} graphs:`, graphData[status]);
                    
                    // Try to find the graph by ID instead of index
                    console.log('Attempting to find graph by ID...');
                    const graphById = graphData[status].find(g => g.id == id);
                    if (graphById) {
                        console.log('Found graph by ID:', graphById);
                        const correctIndex = graphData[status].findIndex(g => g.id == id);
                        console.log('Correct index should be:', correctIndex);
                        showGraphNotification(`Graph data found by ID but index mismatch. Expected index: ${correctIndex}, provided: ${index}. Please refresh the page.`, 'error');
                    } else {
                        console.log('Graph not found by ID either');
                        showGraphNotification('Graph data not found. This may be due to a data synchronization issue. Please refresh the page.', 'error');
                    }
                    return;
                }

                // Get the graph data from the global graphData object
                const graph = graphData[status][index];
                console.log('Found graph data:', graph);

                try {
                    // Populate basic fields for manual edit form
                    document.getElementById('graph-edit-id').value = id;
                    document.getElementById('graph-edit-status').value = status;
                    document.getElementById('graph-edit-original-type').value = graphType;

                    // Populate basic fields for upload form
                    document.getElementById('graph-edit-upload-id').value = id;
                    document.getElementById('graph-edit-upload-status').value = status;

                    // Set title for manual edit
                    const titleSelect = document.getElementById('graph-edit-title-select');
                    const titleInput = document.getElementById('graph-edit-title');
                    
                    if (!titleSelect || !titleInput) {
                        console.error('Title elements not found');
                        showGraphNotification('System error: Form elements missing', 'error');
                        return;
                    }
                    
                    const predefinedTitles = ['Faculty Profile', 'Enrollment Trends', 'Performance Licensure Examination'];
                    if (predefinedTitles.includes(description)) {
                        titleSelect.value = description;
                        titleInput.classList.add('hidden');
                        titleInput.value = description;
                    } else {
                        titleSelect.value = 'custom';
                        titleInput.classList.remove('hidden');
                        titleInput.value = description;
                    }
                    
                    // Set title for upload form
                    const uploadTitleSelect = document.getElementById('edit-upload-graph-title-select');
                    const uploadTitleInput = document.getElementById('edit-upload-graph-title');
                    
                    if (uploadTitleSelect && uploadTitleInput) {
                        if (predefinedTitles.includes(description)) {
                            uploadTitleSelect.value = description;
                            uploadTitleInput.classList.add('hidden');
                            uploadTitleInput.value = description;
                        } else {
                            uploadTitleSelect.value = 'custom';
                            uploadTitleInput.classList.remove('hidden');
                            uploadTitleInput.value = description;
                        }
                    }

                    // Display chart type and populate data
                    displayEditChartType(graphType);
                    populateEditFormData(graph);
                    
                    // Show edit modal
                    if (editModal) {
                        // Ensure modal is properly visible
                        editModal.style.display = 'flex';
                        editModal.classList.remove('hidden');
                        
                        // Force a reflow to ensure the modal appears
                        editModal.offsetHeight;
                        
                        console.log('Edit modal opened successfully');
                        console.log('Modal display style:', editModal.style.display);
                        console.log('Modal classes:', editModal.className);
                    } else {
                        console.error('Edit modal element not found');
                        showGraphNotification('System error: Edit modal not available', 'error');
                    }
                } catch (error) {
                    console.error('Error in edit process:', error);
                    showGraphNotification('Error opening edit form: ' + error.message, 'error');
                }
            }, 100); // Small delay to ensure proper reset
        }

        // Handle archive button click
        function handleArchiveClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');
            const status = this.getAttribute('data-status');

            console.log('Archive button clicked:', { id, description, status });

            graphToAction = id;
            graphToActionData = { status };
            document.getElementById('active-graph-title').textContent = description;

            // Show active graph modal
            document.getElementById('active-graph-modal').style.display = 'flex';
        }

        // Handle delete button click for pending graphs
        function handlePendingDeleteClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');

            console.log('Delete button clicked for pending graph:', { id, description });

            graphToAction = id;
            document.getElementById('graph-delete-title').textContent = description;

            // Show delete confirmation modal
            document.getElementById('graph-delete-modal').style.display = 'flex';
        }

        // Handle delete button click for other graphs
        function handleDeleteClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');
            const status = this.getAttribute('data-status');

            console.log('Delete button clicked:', { id, description, status });

            // Show appropriate modal based on status
            if (status === 'archived') {
                graphToAction = id;
                document.getElementById('archived-graph-delete-title').textContent = description;
                document.getElementById('archived-graph-delete-modal').style.display = 'flex';
            } else if (status === 'not-approved') {
                // For rejected graphs, show the simple delete modal
                graphToAction = id;
                document.getElementById('graph-delete-title').textContent = description;
                document.getElementById('graph-delete-modal').style.display = 'flex';
            } else {
                // For other statuses, show the archive/delete options modal
                graphToAction = id;
                graphToActionData = { status };
                document.getElementById('active-graph-title').textContent = description;
                document.getElementById('active-graph-modal').style.display = 'flex';
            }
        }

        // Handle restore button click
        function handleRestoreClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');

            console.log('Restore button clicked:', { id, description });

            graphToAction = id;
            document.getElementById('archived-graph-restore-title').textContent = description;
            document.getElementById('archived-graph-restore-modal').style.display = 'flex';
        }

        // Handle view button click
        function handleViewClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const button = this;
            const index = button.getAttribute('data-index');
            const status = button.getAttribute('data-status');

            console.log('View button clicked:', { index, status });

            const modalId = `graph-modal-${status}-${index}`;
            const modal = document.getElementById(modalId);

            if (modal) {
                modal.style.display = 'block';
                
                // Create or update the chart in the modal
                setTimeout(() => {
                    createFullGraph(status, index);
                }, 100);
            }
        }

        // Handle download button click
        function handleDownloadClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const button = this;
            const index = button.getAttribute('data-index');
            const status = button.getAttribute('data-status');

            console.log('Download button clicked:', { index, status });

            // Get the graph data
            const graph = graphData[status][index];
            
            // Create a canvas element
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // Set canvas size
            canvas.width = 800;
            canvas.height = 600;
            
            // Create a temporary chart to render
            let chart;
            
            if (graph.graph_type === 'pie') {
                const isPercentage = shouldTreatAsPercentage(graph.data);
                chart = createChartWithDataLabels(ctx, {
                    type: 'pie',
                    data: {
                        labels: graph.data.labels,
                        datasets: [{
                            data: graph.data.values,
                            backgroundColor: graph.data.colors
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: {
                            title: {
                                display: true,
                                text: graph.description
                            },
                            datalabels: {
                                display: true,
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    size: 14
                                },
                                formatter: (value, context) => {
                                    // For pie charts, always show the slice percentage
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    if (isPercentage) {
                                        return `${percentage}%`;
                                    } else {
                                        return `${value}\n(${percentage}%)`;
                                    }
                                },
                                textAlign: 'center'
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            } else if (graph.graph_type === 'bar') {
                const isPercentage = shouldTreatAsPercentage(graph.data);
                chart = createChartWithDataLabels(ctx, {
                    type: 'bar',
                    data: {
                        labels: graph.data.labels,
                        datasets: [{
                            label: graph.description,
                            data: graph.data.values,
                            backgroundColor: graph.data.colors
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: {
                            title: {
                                display: true,
                                text: graph.description
                            },
                            datalabels: {
                                display: true,
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    size: 8
                                },
                                formatter: (value) => {
                                    return formatChartValue(value, isPercentage);
                                },
                                anchor: 'center',
                                align: 'center'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatChartValue(value, isPercentage);
                                    }
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            } else if (graph.graph_type === 'group') {
                chart = createChartWithDataLabels(ctx, {
                    type: 'bar',
                    data: {
                        labels: graph.data.labels,
                        datasets: [{
                            label: graph.description,
                            data: graph.data.values,
                            backgroundColor: graph.data.colors
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: {
                            title: {
                                display: true,
                                text: graph.description
                            },
                            datalabels: {
                                display: true,
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    size: 8
                                },
                                formatter: (value) => {
                                    const isPercentage = shouldTreatAsPercentage(graph.data);
                                    return formatChartValue(value, isPercentage);
                                },
                                anchor: 'center',
                                align: 'center'
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }
            
            // Wait for the chart to render
            setTimeout(() => {
                // Convert canvas to blob and download
                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${graph.description}.png`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    // Show notification
                    showGraphNotification(`Downloading ${graph.description}...`, 'success');
                });
            }, 1000);
        }

        // Reset edit form
        function resetEditForm() {
            console.log('Resetting edit form...');
            selectedEditChartType = '';
            
            // Clear the chart type display
            const chartTypeDisplay = document.getElementById('edit-current-chart-type');
            if (chartTypeDisplay) {
                chartTypeDisplay.innerHTML = '';
            }
            
            // Reset form visibility
            const commonFields = document.getElementById('edit-common-graph-fields');
            const pieForm = document.getElementById('edit-pie-chart-form');
            const barForm = document.getElementById('edit-bar-chart-form');
            const groupForm = document.getElementById('edit-grouped-graph-form');
            
            if (commonFields) commonFields.style.display = 'block';
            if (pieForm) pieForm.classList.add('hidden');
            if (barForm) barForm.classList.add('hidden');
            if (groupForm) groupForm.classList.add('hidden');
            
            // Reset form fields
            const titleSelect = document.getElementById('graph-edit-title-select');
            const titleInput = document.getElementById('graph-edit-title');
            const idInput = document.getElementById('graph-edit-id');
            const statusInput = document.getElementById('graph-edit-status');
            const originalTypeInput = document.getElementById('graph-edit-original-type');
            
            if (titleSelect) titleSelect.value = '';
            if (titleInput) {
                titleInput.classList.add('hidden');
                titleInput.value = '';
            }
            if (idInput) idInput.value = '';
            if (statusInput) statusInput.value = '';
            if (originalTypeInput) originalTypeInput.value = '';
            
            // Reset data points
            resetEditPieDataPoints();
            resetEditBarDataPoints();
            resetEditGroupedGraphContainer();
            
            // Reset tabs to manual edit
            const editManualTab = document.getElementById('edit-manual-tab');
            const editUploadTab = document.getElementById('edit-upload-tab');
            const editManualContent = document.getElementById('edit-manual-content');
            const editUploadContent = document.getElementById('edit-upload-content');
            
            if (editManualTab && editUploadTab) {
                editManualTab.classList.add('text-blue-600', 'border-blue-600', 'bg-blue-50');
                editManualTab.classList.remove('text-gray-500');
                editUploadTab.classList.remove('text-blue-600', 'border-blue-600', 'bg-blue-50');
                editUploadTab.classList.add('text-gray-500');
                
                editManualContent.classList.remove('hidden');
                editUploadContent.classList.add('hidden');
            }
            
            console.log('Edit form reset completed');
        }
        
        // Reset edit upload form
        function resetEditUploadForm() {
            console.log('Resetting edit upload form...');
            
            // Reset file input
            const fileInput = document.getElementById('edit-graph-file-input');
            const dropZone = document.getElementById('edit-file-drop-zone');
            const fileSelected = document.getElementById('edit-file-selected');
            
            if (fileInput) fileInput.value = '';
            if (dropZone) dropZone.classList.remove('hidden');
            if (fileSelected) fileSelected.classList.add('hidden');
            
            // Reset title selection
            const titleSelect = document.getElementById('edit-upload-graph-title-select');
            const titleInput = document.getElementById('edit-upload-graph-title');
            
            if (titleSelect) titleSelect.value = '';
            if (titleInput) {
                titleInput.classList.add('hidden');
                titleInput.value = '';
            }
            
            // Reset graph type selection
            document.querySelectorAll('.edit-upload-graph-type-option').forEach(opt => {
                opt.classList.remove('border-blue-500', 'bg-blue-50');
            });
            
            const graphTypeInput = document.getElementById('edit-upload-graph-type');
            if (graphTypeInput) graphTypeInput.value = '';
            
            // Reset hidden inputs
            const idInput = document.getElementById('graph-edit-upload-id');
            const statusInput = document.getElementById('graph-edit-upload-status');
            
            if (idInput) idInput.value = '';
            if (statusInput) statusInput.value = '';
            
            // Disable submit button
            const submitBtn = document.getElementById('graph-save-edit-upload-btn');
            if (submitBtn) submitBtn.disabled = true;
            
            console.log('Edit upload form reset completed');
        }
        
        // Handle edit file selection
        function handleEditFileSelection() {
            const fileInput = document.getElementById('edit-graph-file-input');
            const dropZone = document.getElementById('edit-file-drop-zone');
            const fileSelected = document.getElementById('edit-file-selected');
            const selectedFileName = document.getElementById('edit-selected-file-name');
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                selectedFileName.textContent = file.name;
                dropZone.classList.add('hidden');
                fileSelected.classList.remove('hidden');
                updateEditUploadSubmitButton();
            }
        }
        
        // Handle edit upload title selection
        function handleEditUploadTitleSelection() {
            const titleSelect = document.getElementById('edit-upload-graph-title-select');
            const titleInput = document.getElementById('edit-upload-graph-title');
            
            if (titleSelect.value === 'custom') {
                titleInput.classList.remove('hidden');
                titleInput.focus();
            } else {
                titleInput.classList.add('hidden');
                titleInput.value = titleSelect.value;
            }
            updateEditUploadSubmitButton();
        }
        
        // Update edit upload submit button state
        function updateEditUploadSubmitButton() {
            const fileInput = document.getElementById('edit-graph-file-input');
            const titleSelect = document.getElementById('edit-upload-graph-title-select');
            const titleInput = document.getElementById('edit-upload-graph-title');
            const graphTypeInput = document.getElementById('edit-upload-graph-type');
            const submitBtn = document.getElementById('graph-save-edit-upload-btn');
            
            if (!submitBtn) return;
            
            const hasFile = fileInput && fileInput.files.length > 0;
            const hasTitle = titleSelect && (
                (titleSelect.value !== 'custom' && titleSelect.value !== '') ||
                (titleSelect.value === 'custom' && titleInput && titleInput.value.trim() !== '')
            );
            const hasGraphType = graphTypeInput && graphTypeInput.value !== '';
            
            submitBtn.disabled = !(hasFile && hasTitle && hasGraphType);
        }
        
        // Handle edit upload form submission
        function handleEditUploadFormSubmit(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('edit-graph-file-input');
            const titleSelect = document.getElementById('edit-upload-graph-title-select');
            const titleInput = document.getElementById('edit-upload-graph-title');
            const graphTypeInput = document.getElementById('edit-upload-graph-type');
            const idInput = document.getElementById('graph-edit-upload-id');
            const statusInput = document.getElementById('graph-edit-upload-status');
            
            if (!fileInput.files.length) {
                showGraphNotification('Please select a file', 'error');
                return;
            }
            
            const title = titleSelect.value === 'custom' ? titleInput.value.trim() : titleSelect.value;
            if (!title) {
                showGraphNotification('Please enter a graph title', 'error');
                return;
            }
            
            if (!graphTypeInput.value) {
                showGraphNotification('Please select a graph type', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('title', title);
            formData.append('graphType', graphTypeInput.value);
            formData.append('id', idInput.value);
            formData.append('status', statusInput.value);
            
            const submitBtn = document.getElementById('graph-save-edit-upload-btn');
            const originalHTML = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin fa-sm mr-1"></i> Updating...';
            submitBtn.disabled = true;
            
            const updateGraphPath = getCEITGraphPath('update_graph.php');
            
            fetch(updateGraphPath, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showGraphNotification(data.message || 'Graph updated successfully with new file!', 'success');
                    setTimeout(() => {
                        document.getElementById('graph-edit-modal').classList.add('hidden');
                        resetEditForm();
                        resetEditUploadForm();
                        location.reload();
                    }, 1500);
                } else {
                    showGraphNotification(data.message || 'Error updating graph', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
                showGraphNotification('An error occurred while updating the graph: ' + error.message, 'error');
            });
        }

        // Display current chart type for edit (read-only)
        function displayEditChartType(type) {
            selectedEditChartType = type;
            
            // Clear the current chart type display
            const chartTypeDisplay = document.getElementById('edit-current-chart-type');
            chartTypeDisplay.innerHTML = '';
            
            // Create the display element for the current chart type
            let icon, label;
            if (type === 'pie') {
                icon = 'fas fa-chart-pie';
                label = 'Pie Graph';
            } else if (type === 'bar') {
                icon = 'fas fa-chart-bar';
                label = 'Bar Graph';
            } else if (type === 'group') {
                icon = 'fas fa-layer-group';
                label = 'Grouped Graph';
            }
            
            const displayElement = document.createElement('div');
            displayElement.className = 'p-4 border-2 border-blue-500 bg-blue-50 rounded-lg text-center';
            displayElement.innerHTML = `
                <i class="${icon} text-2xl text-blue-600 mb-2"></i>
                <div class="font-semibold text-blue-800">${label}</div>
                <div class="text-sm text-blue-600 mt-1">Currently editing this graph type</div>
            `;
            chartTypeDisplay.appendChild(displayElement);
            
            // Show/hide appropriate form sections
            document.getElementById('edit-pie-chart-form').classList.add('hidden');
            document.getElementById('edit-bar-chart-form').classList.add('hidden');
            document.getElementById('edit-grouped-graph-form').classList.add('hidden');
            
            if (type === 'pie') {
                document.getElementById('edit-pie-chart-form').classList.remove('hidden');
            } else if (type === 'bar') {
                document.getElementById('edit-bar-chart-form').classList.remove('hidden');
            } else if (type === 'group') {
                document.getElementById('edit-grouped-graph-form').classList.remove('hidden');
            }
        }

        // Handle title selection for edit
        function handleEditTitleSelection() {
            const select = document.getElementById('graph-edit-title-select');
            const customInput = document.getElementById('graph-edit-title');
            
            if (select.value === 'custom') {
                customInput.classList.remove('hidden');
                customInput.focus();
                customInput.value = '';
            } else {
                customInput.classList.add('hidden');
                customInput.value = select.value;
            }
        }

        // Populate edit form data
        function populateEditFormData(graph) {
            if (!graph || !graph.data) return;

            if (graph.graph_type === 'pie') {
                populateEditPieData(graph.data);
            } else if (graph.graph_type === 'bar') {
                populateEditBarData(graph.data);
            } else if (graph.graph_type === 'group') {
                populateEditGroupData(graph.data);
            }
        }

        // Populate pie chart data for edit
        function populateEditPieData(data) {
            const container = document.getElementById('edit-pie-data-points');
            container.innerHTML = '';

            if (data.labels && data.values && data.colors) {
                for (let i = 0; i < data.labels.length; i++) {
                    const row = document.createElement('div');
                    row.className = 'data-point-row';
                    row.innerHTML = `
                        <input type="text" placeholder="Label" name="pieLabel[]" value="${data.labels[i] || ''}" class="flex-1 p-2 border border-gray-300 rounded">
                        <input type="number" placeholder="Value" name="pieValue[]" value="${data.values[i] || ''}" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0">
                        <input type="color" name="pieColor[]" value="${data.colors[i] || '#3b82f6'}" class="w-10 h-10 border border-gray-300 rounded">
                        <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                            <i class="fas fa-times" title="Remove Data Point"></i>
                        </button>
                    `;
                    container.appendChild(row);
                }
            }
        }

        // Populate bar chart data for edit
        function populateEditBarData(data) {
            if (!data.categories || !data.values) return;

            // Set number of series
            const seriesCount = data.seriesLabels ? data.seriesLabels.length : 1;
            document.getElementById('edit-number-of-series').value = seriesCount;
            
            // Update series inputs
            updateEditSeriesInputs();
            
            // Populate series labels and colors
            if (data.seriesLabels && data.seriesColors) {
                const seriesInputs = document.querySelectorAll('#edit-series-inputs input[name="seriesLabel[]"]');
                const colorInputs = document.querySelectorAll('#edit-series-inputs input[name="seriesColor[]"]');
                
                for (let i = 0; i < data.seriesLabels.length; i++) {
                    if (seriesInputs[i]) seriesInputs[i].value = data.seriesLabels[i];
                    if (colorInputs[i]) colorInputs[i].value = data.seriesColors[i];
                }
            }

            // Detect value type (check if all values are <= 100 and might be percentages)
            const allValues = data.values.flat();
            const maxValue = Math.max(...allValues);
            const isLikelyPercentage = maxValue <= 100 && allValues.every(val => val >= 0 && val <= 100);
            
            // Set value type radio buttons (default to whole values unless clearly percentages)
            const valueTypeRadios = document.querySelectorAll('input[name="editBarValueType"]');
            valueTypeRadios.forEach(radio => {
                if (radio.value === 'values') {
                    radio.checked = true;
                } else {
                    radio.checked = false;
                }
            });

            // Populate categories and values
            const container = document.getElementById('edit-bar-data-points');
            container.innerHTML = '';

            for (let i = 0; i < data.categories.length; i++) {
                let valueInputs = '';
                const categoryValues = data.values[i] || [];
                
                for (let j = 0; j < seriesCount; j++) {
                    valueInputs += `<input type="number" placeholder="Series ${j + 1}" name="barValue[]" value="${categoryValues[j] || ''}" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0">`;
                }
                
                const row = document.createElement('div');
                row.className = 'data-point-row';
                row.innerHTML = `
                    <input type="text" placeholder="Category" name="barCategory[]" value="${data.categories[i] || ''}" class="flex-1 p-2 border border-gray-300 rounded">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
                container.appendChild(row);
            }
        }

        // Populate grouped graph data for edit
        function populateEditGroupData(data) {
            console.log('Populating edit group data:', data);
            
            if (!data || !data.graphs) {
                console.error('Invalid group data structure:', data);
                showGraphNotification('Invalid group graph data structure', 'error');
                return;
            }

            const container = document.getElementById('edit-grouped-graphs-container');
            if (!container) {
                console.error('Edit grouped graphs container not found');
                showGraphNotification('System error: Group container missing', 'error');
                return;
            }
            
            container.innerHTML = '';
            editGraphInGroupCount = 0;

            try {
                data.graphs.forEach((graph, index) => {
                    console.log(`Processing group graph ${index}:`, graph);
                    addEditGraphToGroup();
                    const graphItem = container.children[index];
                    
                    if (!graphItem) {
                        console.error(`Failed to create graph item ${index}`);
                        return;
                    }
                    
                    // Replace graph type selector with display-only element
                    const typeSelectContainer = graphItem.querySelector('select[name="graphType[]"]')?.parentElement;
                    if (typeSelectContainer) {
                        let icon, label;
                        if (graph.type === 'pie') {
                            icon = 'fas fa-chart-pie';
                            label = 'Pie Graph';
                        } else if (graph.type === 'bar') {
                            icon = 'fas fa-chart-bar';
                            label = 'Bar Graph';
                        } else {
                            icon = 'fas fa-question';
                            label = 'Unknown Type';
                        }
                        
                        typeSelectContainer.innerHTML = `
                            <label class="block text-sm font-medium text-gray-700 mb-1">Graph Type</label>
                            <div class="p-2 border-2 border-blue-500 bg-blue-50 rounded text-center">
                                <i class="${icon} text-blue-600 mr-2"></i>
                                <span class="font-semibold text-blue-800">${label}</span>
                                <div class="text-xs text-blue-600 mt-1">Editing existing graph</div>
                            </div>
                            <input type="hidden" name="graphType[]" value="${graph.type}">
                        `;
                    }
                    
                    // Set title
                    const titleSelect = graphItem.querySelector('select[name="graphTitleSelect[]"]');
                    const titleInput = graphItem.querySelector('input[name="graphTitle[]"]');
                    
                    if (titleSelect && titleInput) {
                        const predefinedTitles = ['Faculty Profile', 'Enrollment Trends', 'Performance Licensure Examination'];
                        if (predefinedTitles.includes(graph.title)) {
                            titleSelect.value = graph.title;
                            titleInput.classList.add('hidden');
                            titleInput.value = graph.title;
                        } else {
                            titleSelect.value = 'custom';
                            titleInput.classList.remove('hidden');
                            titleInput.value = graph.title;
                        }
                    }
                    
                    // Trigger the graph type update to show appropriate form fields
                    // Since we replaced the select with a display element, we need to manually show the appropriate form
                    const graphDataContainer = graphItem.querySelector('.grouped-graph-data');
                    if (graphDataContainer && graph.type) {
                        // Clear existing content
                        graphDataContainer.innerHTML = '';
                        
                        if (graph.type === 'pie') {
                            graphDataContainer.innerHTML = `
                                <div class="form-fieldset">
                                    <legend>Pie Graph Data</legend>
                                    <div class="grouped-pie-data-points mb-4">
                                        <!-- Pie data points will be populated here -->
                                    </div>
                                    <button type="button" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="addGroupedPieDataPoint(this, ${index})">
                                        <i class="fas fa-plus mr-2"></i> Add Data Point
                                    </button>
                                </div>
                            `;
                        } else if (graph.type === 'bar') {
                            graphDataContainer.innerHTML = `
                                <div class="form-fieldset">
                                    <legend>Bar Graph Configuration</legend>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Number of Series</label>
                                        <input type="number" class="grouped-series-count w-full p-2 border border-gray-300 rounded-md" min="1" max="5" value="1" onchange="updateEditGroupedSeriesInputs(${index}, this.value)">
                                    </div>
                                    <div class="form-fieldset">
                                        <legend>Series Labels</legend>
                                        <div class="grouped-series-inputs grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <!-- Series inputs will be populated here -->
                                        </div>
                                    </div>
                                    <div class="form-fieldset">
                                        <legend>Bar Graph Data</legend>
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Value Input Type</label>
                                            <div class="flex gap-4">
                                                <label class="flex items-center">
                                                    <input type="radio" name="editGroupedBarValueType[${index}]" value="values" checked class="mr-2" onchange="handleEditGroupedBarValueTypeChange(${index})">
                                                    <span>Whole Values</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="radio" name="editGroupedBarValueType[${index}]" value="percentages" class="mr-2" onchange="handleEditGroupedBarValueTypeChange(${index})">
                                                    <span>Percentages (%)</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="grouped-bar-data-points mb-4">
                                            <!-- Bar data points will be populated here -->
                                        </div>
                                        <div id="edit-grouped-bar-percentage-warning-${index}" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <div class="flex items-center">
                                                <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                                <span class="text-sm text-yellow-700">
                                                    <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                                                </span>
                                            </div>
                                        </div>
                                        <button type="button" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="addGroupedBarDataPoint(this, ${index})">
                                            <i class="fas fa-plus mr-2"></i> Add Category
                                        </button>
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    // Populate data based on type
                    if (graph.type === 'pie' && graph.data) {
                        populateEditGroupedPieData(graphItem, graph.data, index);
                    } else if (graph.type === 'bar' && graph.data) {
                        populateEditGroupedBarData(graphItem, graph.data, index);
                    }
                });
                
                console.log('Group data populated successfully');
            } catch (error) {
                console.error('Error populating group data:', error);
                showGraphNotification('Error loading group graph data: ' + error.message, 'error');
            }
        }

        // Populate grouped pie data
        function populateEditGroupedPieData(graphItem, data, graphIndex) {
            const container = graphItem.querySelector('.grouped-pie-data-points');
            if (!container || !data.labels || !data.values || !data.colors) return;

            container.innerHTML = '';
            for (let i = 0; i < data.labels.length; i++) {
                const row = document.createElement('div');
                row.className = 'data-point-row';
                row.innerHTML = `
                    <input type="text" placeholder="Label" name="pieLabel[${graphIndex}][]" value="${data.labels[i] || ''}" class="flex-1 p-2 border border-gray-300 rounded">
                    <input type="number" placeholder="Value" name="pieValue[${graphIndex}][]" value="${data.values[i] || ''}" class="w-16 p-2 border border-gray-300 rounded">
                    <input type="color" name="pieColor[${graphIndex}][]" value="${data.colors[i] || '#3b82f6'}" class="w-10 h-10 border border-gray-300 rounded">
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
                container.appendChild(row);
            }
        }

        // Populate grouped bar data
        function populateEditGroupedBarData(graphItem, data, graphIndex) {
            if (!data.categories || !data.values) return;

            // Set series count
            const seriesCount = data.seriesLabels ? data.seriesLabels.length : 1;
            const seriesCountInput = graphItem.querySelector('.grouped-series-count');
            if (seriesCountInput) {
                seriesCountInput.value = seriesCount;
                updateEditGroupedSeriesInputs(graphIndex, seriesCount);
            }

            // Populate series labels and colors
            if (data.seriesLabels && data.seriesColors) {
                const seriesInputs = graphItem.querySelectorAll(`input[name="seriesLabel[${graphIndex}][]"]`);
                const colorInputs = graphItem.querySelectorAll(`input[name="seriesColor[${graphIndex}][]"]`);
                
                for (let i = 0; i < data.seriesLabels.length; i++) {
                    if (seriesInputs[i]) seriesInputs[i].value = data.seriesLabels[i];
                    if (colorInputs[i]) colorInputs[i].value = data.seriesColors[i];
                }
            }

            // Populate categories and values
            const container = graphItem.querySelector('.grouped-bar-data-points');
            if (!container) return;

            container.innerHTML = '';
            for (let i = 0; i < data.categories.length; i++) {
                let valueInputs = '';
                const categoryValues = data.values[i] || [];
                
                for (let j = 0; j < seriesCount; j++) {
                    valueInputs += `<input type="number" placeholder="Series ${j + 1}" name="barValue[${graphIndex}][]" value="${categoryValues[j] || ''}" class="w-16 p-2 border border-gray-300 rounded">`;
                }
                
                const row = document.createElement('div');
                row.className = 'data-point-row';
                row.innerHTML = `
                    <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" value="${data.categories[i] || ''}" class="flex-1 p-2 border border-gray-300 rounded">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
                container.appendChild(row);
            }
        }

        // Add pie data row for edit
        function addEditPieDataRow() {
            const container = document.getElementById('edit-pie-data-points');
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Label" name="pieLabel[]" class="flex-1 p-2 border border-gray-300 rounded">
                <input type="number" placeholder="Value" name="pieValue[]" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0">
                <input type="color" name="pieColor[]" value="${randomColor}" class="w-10 h-10 border border-gray-300 rounded">
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Reset edit pie data points
        function resetEditPieDataPoints() {
            const container = document.getElementById('edit-pie-data-points');
            if (container) container.innerHTML = '';
        }

        // Add bar data row for edit
        function addEditBarDataRow() {
            const container = document.getElementById('edit-bar-data-points');
            const numberOfSeries = parseInt(document.getElementById('edit-number-of-series').value) || 1;
            
            // Check current value type
            const valueType = document.querySelector('input[name="editBarValueType"]:checked').value;
            const suffix = valueType === 'percentages' ? ' (%)' : '';
            const maxAttr = valueType === 'percentages' ? 'max="100"' : '';
            
            let valueInputs = '';
            for (let i = 1; i <= numberOfSeries; i++) {
                valueInputs += `<input type="number" placeholder="S${i}${suffix}" name="barValue[]" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0" ${maxAttr}>`;
            }
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Category" name="barCategory[]" class="flex-1 p-2 border border-gray-300 rounded">
                ${valueInputs}
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Reset edit bar data points
        function resetEditBarDataPoints() {
            const container = document.getElementById('edit-bar-data-points');
            if (container) container.innerHTML = '';
        }

        // Update series inputs for edit
        function updateEditSeriesInputs() {
            const count = parseInt(document.getElementById('edit-number-of-series').value) || 1;
            const container = document.getElementById('edit-series-inputs');
            
            // Preserve existing series labels and colors
            const existingLabels = [];
            const existingColors = [];
            const existingInputs = container.querySelectorAll('input[name="seriesLabel[]"]');
            const existingColorInputs = container.querySelectorAll('input[name="seriesColor[]"]');
            
            existingInputs.forEach(input => {
                existingLabels.push(input.value || '');
            });
            existingColorInputs.forEach(input => {
                existingColors.push(input.value || '');
            });
            
            container.innerHTML = '';
            
            const defaultColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            
            for (let i = 1; i <= count; i++) {
                const existingLabel = existingLabels[i - 1] || `Series ${i}`;
                const existingColor = existingColors[i - 1] || defaultColors[i - 1] || '#' + Math.floor(Math.random()*16777215).toString(16);
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';
                div.innerHTML = `
                    <input type="text" placeholder="Series ${i} Label" name="seriesLabel[]" value="${existingLabel}" class="flex-1 p-2 border border-gray-300 rounded">
                    <input type="color" name="seriesColor[]" value="${existingColor}" class="w-10 h-10 border border-gray-300 rounded">
                `;
                container.appendChild(div);
            }
            
            // Update existing bar data rows
            updateEditBarDataRowsForSeries(count);
        }

        // Update bar data rows for series count in edit
        function updateEditBarDataRowsForSeries(seriesCount) {
            const barRows = document.querySelectorAll('#edit-bar-data-points .data-point-row');
            barRows.forEach(row => {
                const categoryInput = row.querySelector('input[name="barCategory[]"]');
                const existingValueInputs = row.querySelectorAll('input[name="barValue[]"]');
                const categoryValue = categoryInput ? categoryInput.value : '';
                
                // Preserve existing values
                const existingValues = [];
                existingValueInputs.forEach(input => {
                    existingValues.push(input.value || '');
                });
                
                let valueInputs = '';
                for (let i = 1; i <= seriesCount; i++) {
                    const existingValue = existingValues[i - 1] || '';
                    valueInputs += `<input type="number" placeholder="S${i}" name="barValue[]" value="${existingValue}" class="w-16 p-1 border border-gray-300 rounded text-sm" step="any" min="0">`;
                }
                
                row.innerHTML = `
                    <input type="text" placeholder="Category" name="barCategory[]" value="${categoryValue}" class="flex-1 p-2 border border-gray-300 rounded">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
            });
        }

        // Add graph to group for edit
        function addEditGraphToGroup() {
            const container = document.getElementById('edit-grouped-graphs-container');
            const graphItem = document.createElement('div');
            graphItem.className = 'grouped-graph-item';
            graphItem.setAttribute('data-graph-index', editGraphInGroupCount);
            
            graphItem.innerHTML = `
                <div class="grouped-graph-item-header">
                    <div class="grouped-graph-item-title">Graph ${editGraphInGroupCount + 1}</div>
                    <i class="fas fa-times grouped-graph-item-remove" onclick="removeEditGraphFromGroup(this)"></i>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Graph Type</label>
                    <select name="graphType[]" class="w-full p-2 border border-gray-300 rounded" onchange="updateEditGroupedGraphType(this, ${editGraphInGroupCount})">
                        <option value="">Select Type</option>
                        <option value="pie">Pie Graph</option>
                        <option value="bar">Bar Graph</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Graph Title</label>
                    <select name="graphTitleSelect[]" class="w-full p-2 border border-gray-300 rounded" onchange="handleEditGroupedTitleSelection(this, ${editGraphInGroupCount})">
                        <option value="">Select a title</option>
                        <option value="Faculty Profile">Faculty Profile</option>
                        <option value="Enrollment Trends">Enrollment Trends</option>
                        <option value="Performance Licensure Examination">Performance Licensure Examination</option>
                        <option value="custom">Custom Title</option>
                    </select>
                    <input type="text" name="graphTitle[]" class="w-full p-2 border border-gray-300 rounded mt-2 hidden" placeholder="Enter custom graph title">
                </div>
                <div class="grouped-graph-data"></div>
            `;
            
            container.appendChild(graphItem);
            editGraphInGroupCount++;
        }

        // Handle grouped title selection for edit
        function handleEditGroupedTitleSelection(selectElement, graphIndex) {
            const graphItem = selectElement.closest('.grouped-graph-item');
            if (!graphItem) return;
            
            const customInput = graphItem.querySelector('input[name="graphTitle[]"]');
            
            if (selectElement.value === 'custom') {
                customInput.classList.remove('hidden');
                customInput.focus();
                customInput.value = '';
            } else {
                customInput.classList.add('hidden');
                customInput.value = selectElement.value;
            }
        }

        // Remove graph from group for edit
        function removeEditGraphFromGroup(button) {
            const graphItem = button.closest('.grouped-graph-item');
            if (graphItem) {
                graphItem.remove();
                
                const graphItems = document.querySelectorAll('#edit-grouped-graphs-container .grouped-graph-item');
                graphItems.forEach((item, index) => {
                    item.querySelector('.grouped-graph-item-title').textContent = `Graph ${index + 1}`;
                    item.setAttribute('data-graph-index', index);
                    
                    const select = item.querySelector('select[name="graphType[]"]');
                    if (select) {
                        select.setAttribute('onchange', `updateEditGroupedGraphType(this, ${index})`);
                    }
                });
                
                editGraphInGroupCount = graphItems.length;
            }
        }

        // Update grouped graph type for edit
        function updateEditGroupedGraphType(selectElement, graphIndex) {
            const graphItem = selectElement.closest('.grouped-graph-item');
            if (!graphItem) return;
            
            const graphType = selectElement.value;
            const dataContainer = graphItem.querySelector('.grouped-graph-data');
            
            dataContainer.innerHTML = '';
            
            if (graphType === 'pie') {
                dataContainer.innerHTML = `
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Points</label>
                        <div class="grouped-pie-data-points mb-3">
                            <div class="data-point-row">
                                <input type="text" placeholder="Label" name="pieLabel[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                                <input type="number" placeholder="Value" name="pieValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded">
                                <input type="color" name="pieColor[${graphIndex}][]" value="#3b82f6" class="w-10 h-10 border border-gray-300 rounded">
                                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                    <i class="fas fa-times" title="Remove Data Point"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="px-3 py-1 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded text-sm transition duration-200 transform hover:scale-110" onclick="addEditGroupedPieDataRow(${graphIndex})">
                            <i class="fas fa-plus mr-2"></i> Add Data Point
                        </button>
                    </div>
                `;
            } else if (graphType === 'bar') {
                dataContainer.innerHTML = `
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Number of Series</label>
                        <input type="number" class="grouped-series-count w-full p-2 border border-gray-300 rounded" min="1" max="5" value="1" onchange="updateEditGroupedSeriesInputs(${graphIndex}, this.value)">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Series Labels</label>
                        <div class="grouped-series-inputs mb-3">
                            <div class="flex items-center gap-2">
                                <input type="text" placeholder="Series 1 Label" name="seriesLabel[${graphIndex}][]" value="Series 1" class="flex-1 p-2 border border-gray-300 rounded">
                                <input type="color" name="seriesColor[${graphIndex}][]" value="#3b82f6" class="w-10 h-10 border border-gray-300 rounded">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Value Input Type</label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="editGroupedBarValueType[${graphIndex}]" value="values" checked class="mr-2" onchange="handleEditGroupedBarValueTypeChange(${graphIndex})">
                                <span>Whole Values</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="editGroupedBarValueType[${graphIndex}]" value="percentages" class="mr-2" onchange="handleEditGroupedBarValueTypeChange(${graphIndex})">
                                <span>Percentages (%)</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Choose whether to enter whole values (with or without decimals) or percentages</p>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categories</label>
                        <div class="grouped-bar-data-points mb-3">
                            <div class="data-point-row">
                                <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                                <input type="number" placeholder="Series 1" name="barValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded">
                                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                                    <i class="fas fa-times" title="Remove Data Point"></i>
                                </button>
                            </div>
                        </div>
                        <div id="edit-grouped-bar-percentage-warning-${graphIndex}" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                <span class="text-sm text-yellow-700">
                                    <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                                </span>
                            </div>
                        </div>
                        <button type="button" class="px-3 py-1 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded text-sm transition duration-200 transform hover:scale-110" onclick="addEditGroupedBarDataRow(${graphIndex})">
                            <i class="fas fa-plus mr-2"></i> Add Category
                        </button>
                    </div>
                `;
            }
        }

        // Add grouped pie data row for edit
        function addEditGroupedPieDataRow(graphIndex) {
            const graphItem = document.querySelector(`#edit-grouped-graphs-container .grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const container = graphItem.querySelector('.grouped-pie-data-points');
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Label" name="pieLabel[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                <input type="number" placeholder="Value" name="pieValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded">
                <input type="color" name="pieColor[${graphIndex}][]" value="${randomColor}" class="w-10 h-10 border border-gray-300 rounded">
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Add grouped bar data row for edit
        function addEditGroupedBarDataRow(graphIndex) {
            const graphItem = document.querySelector(`#edit-grouped-graphs-container .grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const container = graphItem.querySelector('.grouped-bar-data-points');
            const seriesCount = parseInt(graphItem.querySelector('.grouped-series-count').value) || 1;
            
            // Check current value type
            const valueTypeRadio = graphItem.querySelector(`input[name="editGroupedBarValueType[${graphIndex}]"]:checked`);
            const valueType = valueTypeRadio ? valueTypeRadio.value : 'values';
            const suffix = valueType === 'percentages' ? ' (%)' : '';
            const maxAttr = valueType === 'percentages' ? 'max="100"' : '';
            
            let valueInputs = '';
            for (let i = 1; i <= seriesCount; i++) {
                valueInputs += `<input type="number" placeholder="S${i}${suffix}" name="barValue[${graphIndex}][]" class="w-16 p-2 border border-gray-300 rounded" step="any" min="0" ${maxAttr}>`;
            }
            
            const row = document.createElement('div');
            row.className = 'data-point-row';
            row.innerHTML = `
                <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" class="flex-1 p-2 border border-gray-300 rounded">
                ${valueInputs}
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                    <i class="fas fa-times" title="Remove Data Point"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Update grouped series inputs for edit
        function updateEditGroupedSeriesInputs(graphIndex, count) {
            const graphItem = document.querySelector(`#edit-grouped-graphs-container .grouped-graph-item[data-graph-index="${graphIndex}"]`);
            if (!graphItem) return;
            
            const container = graphItem.querySelector('.grouped-series-inputs');
            
            // Preserve existing series labels and colors
            const existingLabels = [];
            const existingColors = [];
            const existingInputs = container.querySelectorAll(`input[name="seriesLabel[${graphIndex}][]"]`);
            const existingColorInputs = container.querySelectorAll(`input[name="seriesColor[${graphIndex}][]"]`);
            
            existingInputs.forEach(input => {
                existingLabels.push(input.value || '');
            });
            existingColorInputs.forEach(input => {
                existingColors.push(input.value || '');
            });
            
            container.innerHTML = '';
            
            const defaultColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            
            for (let i = 1; i <= count; i++) {
                const existingLabel = existingLabels[i - 1] || `Series ${i}`;
                const existingColor = existingColors[i - 1] || defaultColors[i - 1] || '#' + Math.floor(Math.random()*16777215).toString(16);
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';
                div.innerHTML = `
                    <input type="text" placeholder="Series ${i} Label" name="seriesLabel[${graphIndex}][]" value="${existingLabel}" class="flex-1 p-2 border border-gray-300 rounded">
                    <input type="color" name="seriesColor[${graphIndex}][]" value="${existingColor}" class="w-10 h-10 border border-gray-300 rounded">
                `;
                container.appendChild(div);
            }
            
            // Update bar data rows
            const barRows = graphItem.querySelectorAll('.grouped-bar-data-points .data-point-row');
            barRows.forEach(row => {
                const categoryInput = row.querySelector(`input[name="barCategory[${graphIndex}][]"]`);
                const existingValueInputs = row.querySelectorAll(`input[name="barValue[${graphIndex}][]"]`);
                const categoryValue = categoryInput ? categoryInput.value : '';
                
                // Preserve existing values
                const existingValues = [];
                existingValueInputs.forEach(input => {
                    existingValues.push(input.value || '');
                });
                
                let valueInputs = '';
                for (let i = 1; i <= count; i++) {
                    const existingValue = existingValues[i - 1] || '';
                    valueInputs += `<input type="number" placeholder="S${i}" name="barValue[${graphIndex}][]" value="${existingValue}" class="w-16 p-1 border border-gray-300 rounded text-sm" step="any" min="0">`;
                }
                
                row.innerHTML = `
                    <input type="text" placeholder="Category" name="barCategory[${graphIndex}][]" value="${categoryValue}" class="flex-1 p-2 border border-gray-300 rounded">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200 transform hover:scale-110" onclick="removeDataRow(this)">
                        <i class="fas fa-times" title="Remove Data Point"></i>
                    </button>
                `;
            });
        }

        // Reset edit grouped graph container
        function resetEditGroupedGraphContainer() {
            editGraphInGroupCount = 0;
            const container = document.getElementById('edit-grouped-graphs-container');
            if (container) container.innerHTML = '';
        }

        // Handle edit form submission
        function handleEditFormSubmit(e) {
            e.preventDefault();

            if (!selectedEditChartType) {
                showGraphNotification('Please select a chart type', 'error');
                return;
            }

            const titleSelect = document.getElementById('graph-edit-title-select');
            const titleInput = document.getElementById('graph-edit-title');
            const title = titleSelect.value === 'custom' ? titleInput.value : titleSelect.value;

            if (!title) {
                showGraphNotification('Please enter a graph title', 'error');
                return;
            }

            const id = document.getElementById('graph-edit-id').value;
            const status = document.getElementById('graph-edit-status').value;

            let graphData = {};

            if (selectedEditChartType === 'pie') {
                const labels = Array.from(document.querySelectorAll('#edit-pie-data-points input[name="pieLabel[]"]'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                let values = Array.from(document.querySelectorAll('#edit-pie-data-points input[name="pieValue[]"]'))
                    .map(input => parseFloat(input.value))
                    .filter(val => !isNaN(val) && val !== '');
                
                const colors = Array.from(document.querySelectorAll('#edit-pie-data-points input[name="pieColor[]"]'))
                    .map(input => input.value);

                if (labels.length < 2 || values.length < 2) {
                    showGraphNotification('Please add at least 2 data points', 'error');
                    return;
                }

                // Check if user entered percentages (only if the radio buttons exist)
                const valueTypeRadio = document.querySelector('input[name="editPieValueType"]:checked');
                if (valueTypeRadio && valueTypeRadio.value === 'percentages') {
                    // Validate that percentages add up to approximately 100%
                    const total = values.reduce((a, b) => a + b, 0);
                    if (Math.abs(total - 100) > 0.1) {
                        showGraphNotification('Percentages must add up to 100%. Current total: ' + total.toFixed(1) + '%', 'error');
                        return;
                    }
                    // Convert percentages to proportional values (keep as percentages for display)
                    // The chart will handle the percentage display correctly
                }

                const minLength = Math.min(labels.length, values.length, colors.length);
                
                graphData = {
                    labels: labels.slice(0, minLength),
                    values: values.slice(0, minLength),
                    colors: colors.slice(0, minLength)
                };
                
            } else if (selectedEditChartType === 'bar') {
                const categories = Array.from(document.querySelectorAll('#edit-bar-data-points input[name="barCategory[]"]'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesLabels = Array.from(document.querySelectorAll('#edit-series-inputs input[name="seriesLabel[]"]'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesColors = Array.from(document.querySelectorAll('#edit-series-inputs input[name="seriesColor[]"]'))
                    .map(input => input.value);
                
                const values = Array.from(document.querySelectorAll('#edit-bar-data-points input[name="barValue[]"]'))
                    .map(input => parseFloat(input.value) || 0);

                if (categories.length < 2) {
                    showGraphNotification('Please add at least 2 categories', 'error');
                    return;
                }

                // Check if user selected percentage input type
                const valueType = document.querySelector('input[name="editBarValueType"]:checked').value;
                if (valueType === 'percentages') {
                    // Validate percentage values are between 0 and 100
                    const invalidValues = values.filter(val => val < 0 || val > 100);
                    if (invalidValues.length > 0) {
                        showGraphNotification('Percentage values must be between 0% and 100%', 'error');
                        return;
                    }
                }

                const seriesCount = seriesLabels.length;
                const filteredValues = [];
                
                for (let i = 0; i < values.length; i += seriesCount) {
                    const categoryValues = values.slice(i, i + seriesCount);
                    if (categoryValues.length === seriesCount) {
                        filteredValues.push(categoryValues);
                    }
                }

                graphData = {
                    categories: categories.slice(0, filteredValues.length),
                    seriesLabels: seriesLabels,
                    seriesColors: seriesColors,
                    values: filteredValues
                };
                
            } else if (selectedEditChartType === 'group') {
                const graphs = [];
                const graphItems = document.querySelectorAll('#edit-grouped-graphs-container .grouped-graph-item');
                
                for (let i = 0; i < graphItems.length; i++) {
                    const graphItem = graphItems[i];
                    const graphType = graphItem.querySelector('select[name="graphType[]"]').value;
                    
                    if (!graphType) {
                        showGraphNotification(`Please select a type for Graph ${i + 1}`, 'error');
                        return;
                    }

                    const titleSelect = graphItem.querySelector(`select[name="graphTitleSelect[]"]`);
                    const titleInput = graphItem.querySelector(`input[name="graphTitle[]"]`);
                    const graphTitle = titleSelect.value === 'custom' ? titleInput.value.trim() : titleSelect.value.trim();

                    if (!graphTitle) {
                        showGraphNotification(`Please enter a title for Graph ${i + 1}`, 'error');
                        return;
                    }

                    let childGraphData = {};

                    if (graphType === 'pie') {
                        const labels = Array.from(graphItem.querySelectorAll(`input[name="pieLabel[${i}][]"]`))
                            .map(input => input.value.trim())
                            .filter(val => val !== '');
                        
                        const values = Array.from(graphItem.querySelectorAll(`input[name="pieValue[${i}][]"]`))
                            .map(input => parseFloat(input.value))
                            .filter(val => !isNaN(val) && val !== '');
                        
                        const colors = Array.from(graphItem.querySelectorAll(`input[name="pieColor[${i}][]"]`))
                            .map(input => input.value);

                        if (labels.length < 2 || values.length < 2) {
                            showGraphNotification(`Please add at least 2 data points for Graph ${i + 1}`, 'error');
                            return;
                        }

                        const minLength = Math.min(labels.length, values.length, colors.length);
                        
                        childGraphData = {
                            labels: labels.slice(0, minLength),
                            values: values.slice(0, minLength),
                            colors: colors.slice(0, minLength)
                        };
                        
                    } else if (graphType === 'bar') {
                        const categories = Array.from(graphItem.querySelectorAll(`input[name="barCategory[${i}][]"]`))
                            .map(input => input.value.trim())
                            .filter(val => val !== '');
                        
                        const seriesLabels = Array.from(graphItem.querySelectorAll(`input[name="seriesLabel[${i}][]"]`))
                            .map(input => input.value.trim())
                            .filter(val => val !== '');
                        
                        const seriesColors = Array.from(graphItem.querySelectorAll(`input[name="seriesColor[${i}][]"]`))
                            .map(input => input.value);
                        
                        const values = Array.from(graphItem.querySelectorAll(`input[name="barValue[${i}][]"]`))
                            .map(input => parseFloat(input.value) || 0);

                        if (categories.length < 2) {
                            showGraphNotification(`Please add at least 2 categories for Graph ${i + 1}`, 'error');
                            return;
                        }

                        const seriesCount = seriesLabels.length;
                        const filteredValues = [];
                        
                        for (let j = 0; j < values.length; j += seriesCount) {
                            const categoryValues = values.slice(j, j + seriesCount);
                            if (categoryValues.length === seriesCount) {
                                filteredValues.push(categoryValues);
                            }
                        }

                        childGraphData = {
                            categories: categories.slice(0, filteredValues.length),
                            seriesLabels: seriesLabels,
                            seriesColors: seriesColors,
                            values: filteredValues
                        };
                    }

                    graphs.push({
                        title: graphTitle,
                        type: graphType,
                        data: childGraphData
                    });
                }

                if (graphs.length < 2) {
                    showGraphNotification('Please add at least 2 graphs to the group', 'error');
                    return;
                }

                graphData = {
                    graphs: graphs
                };
            }

            // Create form data
            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', status);
            formData.append('title', title);
            formData.append('type', selectedEditChartType);
            formData.append('data', JSON.stringify(graphData));

            // Debug logging
            console.log('Edit form submission data:', {
                id: id,
                status: status,
                title: title,
                type: selectedEditChartType,
                data: graphData
            });

            const submitBtn = document.getElementById('graph-save-edit-btn');
            const originalHTML = submitBtn.innerHTML;

            // Show loading state with spinner icon
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin fa-sm mr-1"></i> Updating...';
            submitBtn.disabled = true;

            // Use absolute path approach to avoid relative path issues
            const updateGraphPath = getCEITGraphPath('update_graph.php');
            
            console.log('Absolute updateGraphPath:', updateGraphPath);

            fetch(updateGraphPath, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;

                    if (data.success) {
                        showGraphNotification(data.message || 'Graph updated successfully!', 'success');
                        setTimeout(() => {
                            document.getElementById('graph-edit-modal').classList.add('hidden');
                            resetEditForm();
                            location.reload();
                        }, 1500);
                    } else {
                        showGraphNotification(data.message || 'Error updating graph', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                    showGraphNotification('An error occurred while updating the graph: ' + error.message, 'error');
                });
        }

        // Handle confirm delete for pending graphs
        function handleConfirmDelete(e) {
            e.preventDefault();
            e.stopPropagation();

            console.log('handleConfirmDelete called with graphToAction:', graphToAction);

            // Check if there's already an active delete request
            if (graphActiveRequests.delete) {
                console.log('Delete request already in progress');
                return;
            }

            if (graphToAction) {
                // Set active request flag
                graphActiveRequests.delete = true;

                const btn = e.target;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                // Use the correct path to delete_graph.php
                let deleteGraphPath = getCEITGraphPath('delete_graph.php');

                fetch(deleteGraphPath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(graphToAction)
                    })
                    .then(response => {
                        console.log('Delete response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Delete response data:', data);
                        if (data.success) {
                            showGraphNotification(data.message || 'Graph deleted successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('graph-delete-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showGraphNotification(data.message || 'Error deleting graph', 'error');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        showGraphNotification('An error occurred while deleting the graph: ' + error.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                    })
                    .finally(() => {
                        graphActiveRequests.delete = false;
                    });
            }
        }

        // Handle archive action from modal
        function handleArchiveActive(e) {
            e.preventDefault();
            e.stopPropagation();

            console.log('handleArchiveActive called with graphToAction:', graphToAction);

            // Check if there's already an active archive request
            if (graphActiveRequests.archive) {
                console.log('Archive request already in progress');
                return;
            }

            if (graphToAction) {
                // Set active request flag
                graphActiveRequests.archive = true;

                // Disable button to prevent multiple clicks
                const btn = e.target;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                // Use the correct path to archive_graph.php for CEIT_Modules
                let archiveGraphPath = getCEITGraphPath('archive_graph.php');

                // Send the archive request to the server
                fetch(archiveGraphPath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(graphToAction)
                    })
                    .then(response => {
                        console.log('Archive response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Archive response data:', data);
                        if (data.success) {
                            showGraphNotification(data.message || 'Graph archived successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('active-graph-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showGraphNotification(data.message || 'Error archiving graph', 'error');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                        }
                    })
                    .catch(error => {
                        console.error('Archive error:', error);
                        showGraphNotification('An error occurred while archiving the graph: ' + error.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                    })
                    .finally(() => {
                        graphActiveRequests.archive = false;
                    });
            }
        }

        // Handle delete action from modal
        function handleDeleteActive(e) {
            e.preventDefault();
            e.stopPropagation();

            // Check if there's already an active delete request
            if (graphActiveRequests.delete) {
                console.log('Delete request already in progress');
                return;
            }

            if (graphToAction) {
                // Set active request flag
                graphActiveRequests.delete = true;

                const btn = e.target;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                // Use the correct path to delete_graph.php
                let deleteGraphPath = getCEITGraphPath('delete_graph.php');

                fetch(deleteGraphPath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(graphToAction)
                    })
                    .then(response => {
                        console.log('Delete response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Delete response data:', data);
                        if (data.success) {
                            showGraphNotification(data.message || 'Graph deleted successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('active-graph-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showGraphNotification(data.message || 'Error deleting graph', 'error');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        showGraphNotification('An error occurred while deleting the graph: ' + error.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                    })
                    .finally(() => {
                        graphActiveRequests.delete = false;
                    });
            }
        }

        // Setup archived modals
        function setupArchivedModals() {
            // Archived graph restore modal
            const cancelArchivedRestoreBtn = document.getElementById('cancel-archived-graph-restore-btn');
            if (cancelArchivedRestoreBtn) {
                cancelArchivedRestoreBtn.addEventListener('click', function() {
                    document.getElementById('archived-graph-restore-modal').style.display = 'none';
                });
            }

            const confirmArchivedRestoreBtn = document.getElementById('confirm-archived-graph-restore-btn');
            if (confirmArchivedRestoreBtn) {
                confirmArchivedRestoreBtn.addEventListener('click', function() {
                    if (graphActiveRequests.restore) return;
                    
                    graphActiveRequests.restore = true;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';

                    // Use the correct path to restore_graph.php
                    let restoreGraphPath = getCEITGraphPath('restore_graph.php');

                    fetch(restoreGraphPath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(graphToAction)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showGraphNotification(data.message || 'Graph restored successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('archived-graph-restore-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showGraphNotification(data.message || 'Error restoring graph', 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                        }
                    })
                    .catch(error => {
                        console.error('Restore error:', error);
                        showGraphNotification('An error occurred while restoring graph: ' + error.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                    })
                    .finally(() => {
                        graphActiveRequests.restore = false;
                    });
                });
            }

            // Archived graph delete modal
            const cancelArchivedDeleteBtn = document.getElementById('cancel-archived-graph-delete-btn');
            if (cancelArchivedDeleteBtn) {
                cancelArchivedDeleteBtn.addEventListener('click', function() {
                    document.getElementById('archived-graph-delete-modal').style.display = 'none';
                });
            }

            const confirmArchivedDeleteBtn = document.getElementById('confirm-archived-graph-delete-btn');
            if (confirmArchivedDeleteBtn) {
                confirmArchivedDeleteBtn.addEventListener('click', function() {
                    if (graphActiveRequests.delete) return;
                    
                    graphActiveRequests.delete = true;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                    // Use the correct path to delete_graph.php
                    let deleteGraphPath = getCEITGraphPath('delete_graph.php');

                    fetch(deleteGraphPath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(graphToAction)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showGraphNotification(data.message || 'Graph deleted successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('archived-graph-delete-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showGraphNotification(data.message || 'Error deleting graph', 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        showGraphNotification('An error occurred while deleting the graph: ' + error.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                    })
                    .finally(() => {
                        graphActiveRequests.delete = false;
                    });
                });
            }

            // Close modals when clicking outside
            document.querySelectorAll('[id$="-modal"]').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });
        }

        // Initialize graph previews
        function initializeGraphPreviews() {
            console.log('Initializing graph previews...');

            // Clear any existing charts and group icons
            Object.keys(window.graphCharts).forEach(key => {
                if (window.graphCharts[key]) {
                    try {
                        window.graphCharts[key].destroy();
                    } catch (e) {
                        console.warn('Error destroying chart:', e);
                    }
                    delete window.graphCharts[key];
                }
            });

            // Remove any existing group graph icons
            document.querySelectorAll('.group-graph-icon').forEach(icon => {
                icon.remove();
            });

            // Show all canvases (in case they were hidden for group graphs)
            document.querySelectorAll('.graph-preview canvas').forEach(canvas => {
                canvas.style.display = 'block';
            });

            // Create preview charts for active and archived graphs only
            ['active', 'archived'].forEach(status => {
                graphData[status].forEach((graph, index) => {
                    createPreviewGraph(status, index);
                });
            });

            console.log('Graph previews initialized');
        }

        // Create preview graph
        function createPreviewGraph(status, index) {
            const graph = graphData[status][index];
            const canvasId = `graph-preview-${status}-${index}`;
            const canvas = document.getElementById(canvasId);

            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            const chartKey = `${status}-${index}`;
            if (window.graphCharts[chartKey]) {
                window.graphCharts[chartKey].destroy();
            }

            // Create new chart
            if (graph.graph_type === 'pie') {
                const isPercentage = shouldTreatAsPercentage(graph.data);
                window.graphCharts[chartKey] = createChartWithDataLabels(ctx, {
                    type: 'pie',
                    data: {
                        labels: graph.data.labels,
                        datasets: [{
                            data: graph.data.values,
                            backgroundColor: graph.data.colors
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'right',
                                align: 'center',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 10
                                    },
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        if (data.labels.length && data.datasets.length) {
                                            const dataset = data.datasets[0];
                                            const total = dataset.data.reduce((a, b) => a + b, 0);
                                            return data.labels.map((label, i) => {
                                                const value = dataset.data[i];
                                                const slicePercentage = ((value / total) * 100).toFixed(1);
                                                let displayText;
                                                if (isPercentage) {
                                                    // Show original value without % sign, but keep slice percentage
                                                    displayText = `${label}: ${value} (${slicePercentage}%)`;
                                                } else {
                                                    // For integer data, show only the value
                                                    displayText = `${label}: ${value}`;
                                                }
                                                return {
                                                    text: displayText,
                                                    fillStyle: dataset.backgroundColor[i],
                                                    strokeStyle: dataset.backgroundColor[i],
                                                    lineWidth: 0,
                                                    pointStyle: 'circle',
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            datalabels: {
                                display: true,
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    size: 10
                                },
                                formatter: (value, context) => {
                                    // For pie charts, always show the slice percentage, not the raw value
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${percentage}%`;
                                },
                                textAlign: 'center',
                                anchor: 'center',
                                align: 'center'
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            } else if (graph.graph_type === 'bar') {
                // Handle both old and new data structures
                let labels, datasets;
                
                if (graph.data.categories) {
                    // New data structure
                    labels = graph.data.categories;
                    datasets = (graph.data.seriesLabels || []).map((label, index) => ({
                        label: label,
                        data: (graph.data.values || []).map(v => v[index] || 0),
                        backgroundColor: graph.data.seriesColors ? graph.data.seriesColors[index] : '#3b82f6'
                    }));
                } else {
                    // Old data structure (for backward compatibility)
                    labels = graph.data.labels || [];
                    datasets = [{
                        label: graph.description || 'Data',
                        data: graph.data.values || [],
                        backgroundColor: graph.data.colors || ['#3b82f6']
                    }];
                }
                
                const isPercentage = shouldTreatAsPercentage(graph.data);
                window.graphCharts[chartKey] = createChartWithDataLabels(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            datalabels: {
                                display: true,
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    size: 7
                                },
                                formatter: (value) => {
                                    return formatChartValue(value, isPercentage);
                                },
                                anchor: 'center',
                                align: 'center'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatChartValue(value, isPercentage);
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            } else if (graph.graph_type === 'group') {
                // For grouped graphs, show an icon instead of a chart
                const container = canvas.parentElement;
                
                // Hide the canvas and show an icon instead
                canvas.style.display = 'none';
                
                // Check if icon already exists
                let iconContainer = container.querySelector('.group-graph-icon');
                if (!iconContainer) {
                    iconContainer = document.createElement('div');
                    iconContainer.className = 'group-graph-icon';
                    iconContainer.style.cssText = `
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        height: 100%;
                        width: 100%;
                        color: #3b82f6;
                    `;
                    
                    iconContainer.innerHTML = `
                        <i class="fas fa-layer-group" style="font-size: 48px; margin-bottom: 8px;"></i>
                        <div style="font-size: 14px; font-weight: bold; color: #374151; margin-bottom: 4px;">Group Graph</div>
                        <div style="font-size: 12px; color: #6b7280;">${graph.data.graphs ? graph.data.graphs.length : 0} graph${(graph.data.graphs && graph.data.graphs.length !== 1) ? 's' : ''}</div>
                    `;
                    
                    container.appendChild(iconContainer);
                }
            }
        }

        // Create full graph in modal
        function createFullGraph(status, index) {
            const graph = graphData[status][index];
            const canvasId = `graph-full-${status}-${index}`;
            const modal = document.getElementById(`graph-modal-${status}-${index}`);
            const modalBody = modal.querySelector('.graph-modal-body');
            
            if (!modalBody) return;

            // Clear the modal body
            modalBody.innerHTML = '';
            
            if (graph.graph_type === 'group') {
                // Create a scrollable container for all graphs
                const graphsContainer = document.createElement('div');
                graphsContainer.className = 'w-full overflow-y-auto';
                graphsContainer.style.maxHeight = 'calc(90vh - 200px)';
                
                if (graph.data.graphs && graph.data.graphs.length > 0) {
                    // Create a grid layout for the graphs
                    const gridContainer = document.createElement('div');
                    gridContainer.className = 'grid grid-cols-1 lg:grid-cols-2 gap-6';
                    
                    graph.data.graphs.forEach((childGraph, childIndex) => {
                        const graphCard = document.createElement('div');
                        graphCard.className = 'bg-white rounded-lg shadow-lg p-4';
                        
                        // Add graph title
                        const graphTitle = document.createElement('h3');
                        graphTitle.className = 'text-lg font-semibold text-gray-800 mb-3 text-center';
                        graphTitle.textContent = childGraph.title;
                        graphCard.appendChild(graphTitle);
                        
                        // Create canvas for the graph
                        const canvasContainer = document.createElement('div');
                        canvasContainer.className = 'relative';
                        canvasContainer.style.height = '400px';
                        canvasContainer.style.minHeight = '400px';
                        
                        const canvas = document.createElement('canvas');
                        canvas.id = `group-chart-${status}-${index}-${childIndex}`;
                        canvas.style.width = '100%';
                        canvas.style.height = '100%';
                        canvasContainer.appendChild(canvas);
                        graphCard.appendChild(canvasContainer);
                        
                        gridContainer.appendChild(graphCard);
                        
                        // Create the chart after adding to DOM
                        setTimeout(() => {
                            const childCtx = canvas.getContext('2d');
                            const childChartKey = `group-${status}-${index}-${childIndex}`;
                            
                            if (window.graphCharts[childChartKey]) {
                                window.graphCharts[childChartKey].destroy();
                            }
                            
                            if (childGraph.type === 'pie') {
                                const isPercentage = shouldTreatAsPercentage(childGraph.data);
                                window.graphCharts[childChartKey] = createChartWithDataLabels(childCtx, {
                                    type: 'pie',
                                    data: {
                                        labels: childGraph.data.labels,
                                        datasets: [{
                                            data: childGraph.data.values,
                                            backgroundColor: childGraph.data.colors
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right',
                                                align: 'center',
                                                labels: {
                                                    padding: 10,
                                                    font: {
                                                        size: 9
                                                    },
                                                    usePointStyle: true,
                                                    pointStyle: 'circle',
                                                    generateLabels: function(chart) {
                                                        const data = chart.data;
                                                        if (data.labels.length && data.datasets.length) {
                                                            const dataset = data.datasets[0];
                                                            const total = dataset.data.reduce((a, b) => a + b, 0);
                                                            return data.labels.map((label, i) => {
                                                                const value = dataset.data[i];
                                                                const percentage = ((value / total) * 100).toFixed(1);
                                                                let displayText;
                                                                if (isPercentage) {
                                                                    // Show original value without % sign, but keep slice percentage
                                                                    displayText = `${label}: ${value} (${percentage}%)`;
                                                                } else {
                                                                    // Show both integer value and slice percentage
                                                                    displayText = `${label}: ${value} (${percentage}%)`;
                                                                }
                                                                return {
                                                                    text: displayText,
                                                                    fillStyle: dataset.backgroundColor[i],
                                                                    strokeStyle: dataset.backgroundColor[i],
                                                                    lineWidth: 0,
                                                                    pointStyle: 'circle',
                                                                    hidden: false,
                                                                    index: i
                                                                };
                                                            });
                                                        }
                                                        return [];
                                                    }
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const label = context.label || '';
                                                        const value = context.parsed || 0;
                                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                        const percentage = ((value / total) * 100).toFixed(1);
                                                        return `${label}: ${value} (${percentage}%)`;
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                display: true,
                                                color: 'white',
                                                font: {
                                                    weight: 'bold',
                                                    size: 8
                                                },
                                                formatter: (value, context) => {
                                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                    const percentage = ((value / total) * 100).toFixed(1);
                                                    return `${percentage}%`;
                                                },
                                                textAlign: 'center',
                                                anchor: 'center',
                                                align: 'center'
                                            }
                                        }
                                    },
                                    plugins: [ChartDataLabels]
                                });
                            } else if (childGraph.type === 'bar') {
                                let labels, datasets;
                                
                                if (childGraph.data.categories) {
                                    labels = childGraph.data.categories;
                                    datasets = (childGraph.data.seriesLabels || []).map((label, idx) => ({
                                        label: label,
                                        data: (childGraph.data.values || []).map(v => v[idx] || 0),
                                        backgroundColor: childGraph.data.seriesColors ? childGraph.data.seriesColors[idx] : '#3b82f6'
                                    }));
                                } else {
                                    labels = childGraph.data.labels || [];
                                    datasets = [{
                                        label: childGraph.title || 'Data',
                                        data: childGraph.data.values || [],
                                        backgroundColor: childGraph.data.colors || ['#3b82f6']
                                    }];
                                }
                                
                                window.graphCharts[childChartKey] = createChartWithDataLabels(childCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: datasets
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                                labels: {
                                                    padding: 10,
                                                    font: {
                                                        size: 10
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                display: true,
                                                color: 'white',
                                                font: {
                                                    weight: 'bold',
                                                    size: 8
                                                },
                                                formatter: (value) => {
                                                    const isPercentage = shouldTreatAsPercentage(childGraph.data);
                                                    return formatChartValue(value, isPercentage);
                                                },
                                                anchor: 'center',
                                                align: 'center'
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    font: {
                                                        size: 9
                                                    },
                                                    callback: function(value) {
                                                        const isPercentage = shouldTreatAsPercentage(childGraph.data);
                                                        return formatChartValue(value, isPercentage);
                                                    }
                                                }
                                            },
                                            x: {
                                                ticks: {
                                                    maxRotation: 45,
                                                    minRotation: 45,
                                                    font: {
                                                        size: 9
                                                    }
                                                }
                                            }
                                        }
                                    },
                                    plugins: [ChartDataLabels]
                                });
                            }
                        }, 100);
                    });
                    
                    graphsContainer.appendChild(gridContainer);
                } else {
                    // No graphs in group
                    const emptyMessage = document.createElement('div');
                    emptyMessage.className = 'text-center py-12';
                    emptyMessage.innerHTML = `
                        <i class="fas fa-chart-pie text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No graphs in this group</p>
                    `;
                    graphsContainer.appendChild(emptyMessage);
                }
                
                modalBody.appendChild(graphsContainer);
                
                // Update the modal footer for group graphs
                const modalFooter = modal.querySelector('.graph-modal-meta');
                if (modalFooter && graph.data.graphs) {
                    const graphCount = graph.data.graphs.length;
                    const originalText = modalFooter.textContent;
                    // Replace the graph type part with group info
                    const updatedText = originalText.replace(/Type: \w+ Graph/, `Type: Group Graph (${graphCount} graph${graphCount !== 1 ? 's' : ''})`);
                    modalFooter.textContent = updatedText;
                }
            } else {
                // For non-group graphs, create the original canvas
                const canvasContainer = document.createElement('div');
                canvasContainer.style.width = '100%';
                canvasContainer.style.height = '100%';
                canvasContainer.style.minHeight = '500px';
                canvasContainer.style.position = 'relative';
                
                const canvas = document.createElement('canvas');
                canvas.id = canvasId;
                canvas.style.width = '100%';
                canvas.style.height = '100%';
                canvasContainer.appendChild(canvas);
                modalBody.appendChild(canvasContainer);
                
                // Create the chart
                const ctx = canvas.getContext('2d');
                const chartKey = `full-${status}-${index}`;
                
                if (window.graphCharts[chartKey]) {
                    window.graphCharts[chartKey].destroy();
                }
                
                if (graph.graph_type === 'pie') {
                    window.graphCharts[chartKey] = createChartWithDataLabels(ctx, {
                        type: 'pie',
                        data: {
                            labels: graph.data.labels,
                            datasets: [{
                                data: graph.data.values,
                                backgroundColor: graph.data.colors
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: graph.description,
                                    font: {
                                        size: 16
                                    }
                                },
                                legend: {
                                    position: 'right',
                                    align: 'center',
                                    labels: {
                                        padding: 20,
                                        font: {
                                            size: 12
                                        },
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        generateLabels: function(chart) {
                                            const data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                const dataset = data.datasets[0];
                                                const total = dataset.data.reduce((a, b) => a + b, 0);
                                                return data.labels.map((label, i) => {
                                                    const value = dataset.data[i];
                                                    const percentage = ((value / total) * 100).toFixed(1);
                                                    let displayText;
                                                    const isPercentage = shouldTreatAsPercentage(graph.data);
                                                    if (isPercentage) {
                                                        // Show original value without % sign, but keep slice percentage
                                                        displayText = `${label}: ${value} (${percentage}%)`;
                                                    } else {
                                                        displayText = `${label}: ${value} (${percentage}%)`;
                                                    }
                                                    return {
                                                        text: displayText,
                                                        fillStyle: dataset.backgroundColor[i],
                                                        strokeStyle: dataset.backgroundColor[i],
                                                        lineWidth: 0,
                                                        pointStyle: 'circle',
                                                        hidden: false,
                                                        index: i
                                                    };
                                                });
                                            }
                                            return [];
                                        }
                                    }
                                },
                                datalabels: {
                                    display: true,
                                    color: 'white',
                                    font: {
                                        weight: 'bold',
                                        size: 12
                                    },
                                    formatter: (value, context) => {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${percentage}%`;
                                    },
                                    textAlign: 'center',
                                    anchor: 'center',
                                    align: 'center'
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                } else if (graph.graph_type === 'bar') {
                    // Handle both old and new data structures
                    let labels, datasets;
                    
                    if (graph.data.categories) {
                        // New data structure
                        labels = graph.data.categories;
                        datasets = (graph.data.seriesLabels || []).map((label, index) => ({
                            label: label,
                            data: (graph.data.values || []).map(v => v[index] || 0),
                            backgroundColor: graph.data.seriesColors ? graph.data.seriesColors[index] : '#3b82f6'
                        }));
                    } else {
                        // Old data structure (for backward compatibility)
                        labels = graph.data.labels || [];
                        datasets = [{
                            label: graph.description || 'Data',
                            data: graph.data.values || [],
                            backgroundColor: graph.data.colors || ['#3b82f6']
                        }];
                    }
                    
                    window.graphCharts[chartKey] = createChartWithDataLabels(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: graph.description,
                                    font: {
                                        size: 16
                                    }
                                },
                                legend: {
                                    position: 'top',
                                    labels: {
                                        padding: 20,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                datalabels: {
                                    display: true,
                                    color: 'white',
                                    font: {
                                        weight: 'bold',
                                        size: 9
                                    },
                                    formatter: (value) => {
                                        const isPercentage = shouldTreatAsPercentage(graph.data);
                                        return formatChartValue(value, isPercentage);
                                    },
                                    anchor: 'center',
                                    align: 'center'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        font: {
                                            size: 12
                                        },
                                        callback: function(value) {
                                            const isPercentage = shouldTreatAsPercentage(graph.data);
                                            return formatChartValue(value, isPercentage);
                                        }
                                    }
                                },
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 45,
                                        font: {
                                            size: 12
                                        }
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                }
            }
        }

        // Update the close modal function to clean up group charts
        function closeGraphModal(status, index) {
            const modal = document.getElementById(`graph-modal-${status}-${index}`);
            if (modal) {
                modal.style.display = 'none';

                // Clean up chart resources
                const chartKey = `full-${status}-${index}`;
                if (window.graphCharts[chartKey]) {
                    window.graphCharts[chartKey].destroy();
                    delete window.graphCharts[chartKey];
                }
                
                // Clean up group charts
                if (graphData[status] && graphData[status][index] && graphData[status][index].graph_type === 'group') {
                    const graph = graphData[status][index];
                    if (graph.data.graphs) {
                        graph.data.graphs.forEach((_, childIndex) => {
                            const childChartKey = `group-${status}-${index}-${childIndex}`;
                            if (window.graphCharts[childChartKey]) {
                                window.graphCharts[childChartKey].destroy();
                                delete window.graphCharts[childChartKey];
                            }
                        });
                    }
                }
            }
        }

        // Show notification function
        function showGraphNotification(message, type = 'success') {
            // Remove any existing notifications first
            document.querySelectorAll('.graph-notification').forEach(notification => {
                notification.remove();
            });

            const notification = document.createElement('div');
            notification.className = `graph-notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // Upload Modal Functions
        function resetUploadModal() {
            uploadStep = 1;
            uploadedFileData = null;
            selectedUploadGraphType = '';
            
            // Reset to individual tab
            const individualTab = document.getElementById('individual-upload-tab');
            const groupTab = document.getElementById('group-upload-tab');
            const individualContent = document.getElementById('individual-upload-content');
            const groupContent = document.getElementById('group-upload-content');
            const individualSteps = document.getElementById('individual-upload-steps');
            
            if (individualTab && groupTab) {
                individualTab.classList.add('text-blue-600', 'border-blue-600', 'bg-blue-50');
                individualTab.classList.remove('text-gray-500');
                groupTab.classList.remove('text-blue-600', 'border-blue-600', 'bg-blue-50');
                groupTab.classList.add('text-gray-500');
            }
            
            if (individualContent && groupContent && individualSteps) {
                individualContent.classList.remove('hidden');
                individualSteps.classList.remove('hidden');
                groupContent.classList.add('hidden');
            }
            
            // Reset file input
            document.getElementById('graph-file-input').value = '';
            document.getElementById('file-drop-zone').classList.remove('hidden');
            document.getElementById('file-selected').classList.add('hidden');
            document.getElementById('upload-process-btn').disabled = true;
            
            // Reset graph type selection
            document.querySelectorAll('.upload-graph-type-option').forEach(opt => {
                opt.classList.remove('border-blue-500', 'bg-blue-50');
            });
            document.getElementById('upload-continue-btn').disabled = true;
            
            // Reset title
            document.getElementById('upload-graph-title-select').value = '';
            document.getElementById('upload-graph-title').classList.add('hidden');
            document.getElementById('upload-graph-title').value = '';
            
            // Reset group upload
            resetGroupUpload();
            
            // Show step 1 only if modal is visible
            const modal = document.getElementById('graph-upload-modal');
            if (modal && !modal.classList.contains('hidden')) {
                showUploadStep(1);
            }
        }
        
        function showUploadStep(step) {
            console.log('showUploadStep called with step:', step);
            uploadStep = step;
            
            // Hide all steps
            document.querySelectorAll('.upload-step').forEach(stepEl => {
                stepEl.classList.add('hidden');
            });
            
            // Show current step
            document.getElementById(`upload-step-${step}`).classList.remove('hidden');
            
            if (step === 3) {
                console.log('Step 3 reached, calling setupDataMappingAndPreview');
                setupDataMappingAndPreview();
            }
        }
        
        function handleFileSelection() {
            const fileInput = document.getElementById('graph-file-input');
            const file = fileInput.files[0];
            
            if (file) {
                // Validate file type - CSV and Excel supported
                const fileExtension = file.name.split('.').pop().toLowerCase();
                const allowedExtensions = ['csv', 'xlsx', 'xls'];
                
                if (!allowedExtensions.includes(fileExtension)) {
                    showGraphNotification('Only CSV (.csv) and Excel (.xlsx, .xls) files are supported.', 'error');
                    return;
                }
                
                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    showGraphNotification('File size too large. Maximum size is 10MB.', 'error');
                    return;
                }
                
                // Show selected file
                document.getElementById('file-drop-zone').classList.add('hidden');
                document.getElementById('file-selected').classList.remove('hidden');
                document.getElementById('selected-file-name').textContent = file.name;
                document.getElementById('upload-process-btn').disabled = false;
            }
        }
        
        function processUploadedFile() {
            const fileInput = document.getElementById('graph-file-input');
            const file = fileInput.files[0];
            
            if (!file) {
                showGraphNotification('Please select a file first.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', file);
            
            // Show loading state
            const processBtn = document.getElementById('upload-process-btn');
            const originalText = processBtn.textContent;
            processBtn.textContent = 'Processing...';
            processBtn.disabled = true;
            
            // Determine the correct path to UploadGraph.php with fallback mechanism
            const currentPath = window.location.pathname;
            const currentUrl = window.location.href;
            let uploadGraphPath = 'UploadGraph.php';
            
            // More comprehensive path detection
            if (currentPath.includes('CEIT_Modules/Graph/')) {
                // We're already in the CEIT_Modules/Graph directory
                uploadGraphPath = 'UploadGraph.php';
            } else {
                // We need to navigate to CEIT_Modules/Graph from the current location
                uploadGraphPath = 'CEIT_Modules/Graph/UploadGraph.php';
            }
            
            // Alternative approach: use relative path from current URL
            const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            const fullUploadUrl = currentPath.includes('CEIT_Modules/Graph/') ? 
                baseUrl + 'UploadGraph.php' : 
                window.location.origin + '/' + uploadGraphPath;
            
            console.log('Attempting to upload file to:', uploadGraphPath);
            console.log('Full upload URL:', fullUploadUrl);
            console.log('Current path:', currentPath);
            console.log('Current URL:', currentUrl);
            console.log('File details:', {
                name: file.name,
                size: file.size,
                type: file.type
            });
            
            // Try the primary path first, then fallback if needed
            const tryUpload = async (path) => {
                const response = await fetch(path, {
                    method: 'POST',
                    body: formData
                });
                
                console.log(`Upload attempt to ${path} - Status:`, response.status);
                
                if (!response.ok) {
                    const text = await response.text();
                    console.log(`Error response from ${path}:`, text);
                    throw new Error(`HTTP error! Status: ${response.status} - ${text}`);
                }
                
                return response.text();
            };
            
            // Try primary path first, then fallback
            tryUpload(uploadGraphPath)
            .catch(error => {
                console.log('Primary path failed, trying fallback...');
                // Try alternative path
                const fallbackPath = uploadGraphPath === 'UploadGraph.php' ? 
                    'CEIT_Modules/Graph/UploadGraph.php' : 
                    'UploadGraph.php';
                console.log('Trying fallback path:', fallbackPath);
                return tryUpload(fallbackPath);
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    processBtn.textContent = originalText;
                    processBtn.disabled = false;
                    
                    if (data.success) {
                        uploadedFileData = data.data;
                        console.log('Upload successful - File data received:', uploadedFileData);
                        console.log('Headers:', uploadedFileData.headers);
                        console.log('Number of headers:', uploadedFileData.headers.length);
                        console.log('Sample row:', uploadedFileData.rows[0]);
                        showUploadStep(2);
                    } else {
                        showGraphNotification(data.message || 'Error processing file', 'error');
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
            })
            .catch(error => {
                console.error('Upload error details:', error);
                console.error('Upload path used:', uploadGraphPath);
                console.error('Current window location:', window.location);
                processBtn.textContent = originalText;
                processBtn.disabled = false;
                showGraphNotification('An error occurred while processing the file: ' + error.message, 'error');
            });
        }
        
        function setupDataMappingAndPreview() {
            console.log('setupDataMappingAndPreview called');
            console.log('uploadedFileData:', uploadedFileData);
            console.log('selectedUploadGraphType:', selectedUploadGraphType);
            
            if (!uploadedFileData || !selectedUploadGraphType) return;
            
            const mappingContainer = document.getElementById('upload-column-mapping');
            const previewContainer = document.getElementById('upload-data-preview');
            
            // Hide column mapping completely for bar charts - auto-detect everything
            if (selectedUploadGraphType === 'bar') {
                mappingContainer.innerHTML = ''; // Remove column mapping completely
                
                // Show/hide value type section
                const valueTypeSection = document.getElementById('upload-value-type-section');
                valueTypeSection.classList.add('hidden'); // Hide this too, we'll include it in preview
                
                // Auto-detect and setup everything
                setupDataPreview();
            } else if (selectedUploadGraphType === 'pie') {
                // Remove column mapping completely for pie charts - auto-detect everything
                mappingContainer.innerHTML = ''; // Remove column mapping completely
                
                // Show/hide value type section
                const valueTypeSection = document.getElementById('upload-value-type-section');
                valueTypeSection.classList.add('hidden'); // Hide this too, we'll include it in preview
                
                // Auto-detect and setup everything
                setupDataPreview();
            }
        }
        
        function updatePieSeriesSelection() {
            const selectedSeriesIndex = document.querySelector('input[name="pieSeriesSelection"]:checked').value;
            const valueColumnSelect = document.getElementById('upload-value-column');
            if (valueColumnSelect) {
                valueColumnSelect.value = selectedSeriesIndex;
                // Trigger change event to update preview
                valueColumnSelect.dispatchEvent(new Event('change'));
            }
        }
        
        function setupDataPreview() {
            if (!uploadedFileData || !selectedUploadGraphType) return;
            
            const previewContainer = document.getElementById('upload-data-preview');
            let previewHTML = '';
            
            if (selectedUploadGraphType === 'pie') {
                // Auto-detect: First column = categories, rest = series (same as bar chart)
                const categoryColumnIndex = 0;
                const valueColumns = uploadedFileData.headers.slice(1); // All columns except first
                
                // Debug logging
                console.log('PIE - Headers:', uploadedFileData.headers);
                console.log('PIE - Value columns detected:', valueColumns);
                console.log('PIE - Number of series detected:', valueColumns.length);
                
                // Auto-detect percentage data
                const isPercentageData = uploadedFileData.isPercentageData || false;
                
                // Graph Title
                previewHTML += '<div class="mb-4">';
                previewHTML += '<h5 class="font-medium mb-2"><i class="fas fa-heading mr-2"></i>Graph Title:</h5>';
                previewHTML += '</div>';
                
                // Series (inline) - show all series like bar chart
                previewHTML += '<div class="mb-4" id="upload-pie-series-config">';
                previewHTML += '<h5 class="font-medium mb-2">Series Configuration</h5>';
                previewHTML += '<div class="space-y-2">';
                const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
                
                valueColumns.forEach((header, index) => {
                    const color = colors[index % colors.length];
                    previewHTML += `
                        <div class="inline-flex items-center gap-2 mr-4 mb-2 upload-pie-series-item">
                            <span class="font-medium">Series ${index + 1}:</span>
                            <input type="text" value="${header}" class="p-2 border border-gray-300 rounded upload-pie-series-label" style="width: 180px;" data-series-index="${index}">
                            <input type="color" value="${color}" class="w-8 h-8 border border-gray-300 rounded upload-pie-series-color" data-series-index="${index}">
                        </div>
                    `;
                });
                
                previewHTML += `
                    <button type="button" id="add-upload-pie-series-btn" class="inline-flex items-center px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition duration-200 ml-2">
                        <i class="fas fa-plus mr-1"></i>Add Series
                    </button>
                `;
                previewHTML += '</div>';
                previewHTML += '</div>';
                
                // Value Input Type
                previewHTML += '<div class="mb-4">';
                previewHTML += '<h5 class="font-medium mb-2"><i class="fas fa-calculator mr-2"></i>Value Input Type</h5>';
                previewHTML += '<div class="flex gap-4">';
                previewHTML += `<label class="flex items-center"><input type="radio" name="uploadPieValueType" value="values" ${!isPercentageData ? 'checked' : ''} class="mr-2" onchange="handleUploadPieValueTypeChange()"><span>Whole Values</span></label>`;
                previewHTML += `<label class="flex items-center"><input type="radio" name="uploadPieValueType" value="percentages" ${isPercentageData ? 'checked' : ''} class="mr-2" onchange="handleUploadPieValueTypeChange()"><span>Percentages (%)</span></label>`;
                previewHTML += '</div>';
                previewHTML += '</div>';
                
                // Data Preview & Editing (table format like bar chart)
                previewHTML += '<div class="mb-4">';
                previewHTML += '<h5 class="font-medium mb-3"><i class="fas fa-table mr-2"></i>Data Preview & Editing</h5>';
                
                // Create table with all series but only selected one is used
                previewHTML += '<table class="w-full border border-gray-300 rounded-lg overflow-hidden" id="upload-pie-data-table">';
                
                // Table header
                previewHTML += '<thead class="bg-gray-100">';
                previewHTML += '<tr>';
                previewHTML += '<th class="p-3 text-left font-medium text-gray-700 border-r border-gray-300">Category</th>';
                valueColumns.forEach((header, index) => {
                    previewHTML += `<th class="p-3 text-center font-medium text-gray-700 border-r border-gray-300 pie-series-header" data-series="${index}">${header}</th>`;
                });
                previewHTML += '<th class="p-3 text-center font-medium text-gray-700 w-20">Actions</th>';
                previewHTML += '</tr>';
                previewHTML += '</thead>';
                
                // Table body
                previewHTML += '<tbody id="upload-pie-data-points">';
                
                uploadedFileData.rows.forEach((row, rowIndex) => {
                    const rowValues = Object.values(row);
                    const category = rowValues[categoryColumnIndex] || '';
                    const bgColor = rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    
                    previewHTML += `<tr class="${bgColor} hover:bg-blue-50 transition duration-200">`;
                    previewHTML += `<td class="p-3 border-r border-gray-200"><input type="text" placeholder="Category" value="${category}" class="w-full p-2 border border-gray-300 rounded upload-pie-category"></td>`;
                    
                    // Create value inputs for each series column
                    valueColumns.forEach((header, colIndex) => {
                        let value = rowValues[colIndex + 1] || ''; // +1 because first column is category
                        // Clean percentage values if they contain % symbol
                        if (typeof value === 'string' && value.includes('%')) {
                            value = cleanPercentageValue(value);
                        }
                        previewHTML += `<td class="p-3 border-r border-gray-200 text-center"><input type="number" placeholder="Value" value="${value}" class="w-full p-2 border border-gray-300 rounded upload-pie-value text-center" data-series="${colIndex}" step="any" min="0"></td>`;
                    });
                    
                    previewHTML += `
                        <td class="p-3 text-center">
                            <button type="button" class="p-2 text-red-500 hover:bg-red-100 rounded transition duration-200" onclick="removeUploadDataRow(this)" title="Remove row">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    `;
                    previewHTML += '</tr>';
                });
                
                previewHTML += '</tbody>';
                previewHTML += '</table>';
                
                previewHTML += '<button type="button" id="add-upload-pie-data" class="mt-3 px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded transition duration-200"><i class="fas fa-plus mr-2"></i>Add Category</button>';
                previewHTML += '</div>';
                
            } else if (selectedUploadGraphType === 'bar') {
                // Auto-detect: First column = categories, rest = series
                const categoryColumnIndex = 0;
                const valueColumns = uploadedFileData.headers.slice(1); // All columns except first
                
                // Debug logging
                console.log('Headers:', uploadedFileData.headers);
                console.log('Value columns detected:', valueColumns);
                console.log('Number of series detected:', valueColumns.length);
                console.log('Sample row data:', uploadedFileData.rows[0]);
                console.log('Sample row values:', Object.values(uploadedFileData.rows[0]));
                
                // Auto-detect percentage data
                const isPercentageData = uploadedFileData.isPercentageData || false;
                
                // Graph Title
                previewHTML += '<div class="mb-4">';
                previewHTML += '<h5 class="font-medium mb-2"><i class="fas fa-heading mr-2"></i>Graph Title:</h5>';
                previewHTML += '</div>';
                
                // Series (inline)
                previewHTML += '<div class="mb-4">';
                const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
                
                valueColumns.forEach((header, index) => {
                    const color = colors[index % colors.length];
                    previewHTML += `
                        <div class="inline-flex items-center gap-2 mr-4 mb-2 upload-series-item">
                            <span class="font-medium">Series ${index + 1}:</span>
                            <input type="text" value="${header}" class="p-2 border border-gray-300 rounded upload-series-label" style="width: 180px;">
                            <input type="color" value="${color}" class="w-8 h-8 border border-gray-300 rounded upload-series-color">
                            ${index > 0 ? '<button type="button" class="p-1 text-red-500 hover:bg-red-100 rounded" onclick="removeUploadSeries(this)"><i class="fas fa-times text-sm"></i></button>' : ''}
                        </div>
                    `;
                });
                
                previewHTML += `
                    <button type="button" id="add-upload-series-btn" class="inline-flex items-center px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition duration-200 ml-2">
                        <i class="fas fa-plus mr-1"></i>Add Series
                    </button>
                `;
                previewHTML += '</div>';
                
                // Value Input Type
                previewHTML += '<div class="mb-4">';
                previewHTML += '<h5 class="font-medium mb-2"><i class="fas fa-calculator mr-2"></i>Value Input Type</h5>';
                previewHTML += '<div class="flex gap-4">';
                previewHTML += `<label class="flex items-center"><input type="radio" name="uploadValueType" value="values" ${!isPercentageData ? 'checked' : ''} class="mr-2" onchange="handleUploadValueTypeChange()"><span>Whole Values</span></label>`;
                previewHTML += `<label class="flex items-center"><input type="radio" name="uploadValueType" value="percentages" ${isPercentageData ? 'checked' : ''} class="mr-2" onchange="handleUploadValueTypeChange()"><span>Percentages (%)</span></label>`;
                previewHTML += '</div>';
                previewHTML += '</div>';
                
                // Data Preview & Editing
                previewHTML += '<div class="mb-4">';
                previewHTML += '<h5 class="font-medium mb-3"><i class="fas fa-table mr-2"></i>Data Preview & Editing</h5>';
                
                // Create simple table
                previewHTML += '<table class="w-full border border-gray-300 rounded-lg overflow-hidden" id="upload-data-table">';
                
                // Table header
                previewHTML += '<thead class="bg-gray-100">';
                previewHTML += '<tr>';
                previewHTML += '<th class="p-3 text-left font-medium text-gray-700 border-r border-gray-300">Category</th>';
                valueColumns.forEach((header, index) => {
                    previewHTML += `<th class="p-3 text-center font-medium text-gray-700 border-r border-gray-300 series-header" data-series="${index}">${header}</th>`;
                });
                previewHTML += '<th class="p-3 text-center font-medium text-gray-700 w-20">Actions</th>';
                previewHTML += '</tr>';
                previewHTML += '</thead>';
                
                // Table body
                previewHTML += '<tbody id="upload-bar-data-points">';
                
                uploadedFileData.rows.forEach((row, rowIndex) => {
                    const rowValues = Object.values(row);
                    const category = rowValues[categoryColumnIndex] || '';
                    const bgColor = rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    
                    // Debug logging for first row
                    if (rowIndex === 0) {
                        console.log('First row processing:');
                        console.log('Row object:', row);
                        console.log('Row values array:', rowValues);
                        console.log('Category (index 0):', category);
                        valueColumns.forEach((header, colIndex) => {
                            let value = rowValues[colIndex + 1] || '';
                            console.log(`Series ${colIndex + 1} (${header}) - accessing index ${colIndex + 1}:`, value);
                        });
                    }
                    
                    previewHTML += `<tr class="${bgColor} hover:bg-blue-50 transition duration-200">`;
                    previewHTML += `<td class="p-3 border-r border-gray-200"><input type="text" placeholder="Category" value="${category}" class="w-full p-2 border border-gray-300 rounded upload-bar-category"></td>`;
                    
                    // Create value inputs for each series column
                    valueColumns.forEach((header, colIndex) => {
                        let value = rowValues[colIndex + 1] || ''; // +1 because first column is category
                        // Clean percentage values if they contain % symbol
                        if (typeof value === 'string' && value.includes('%')) {
                            value = cleanPercentageValue(value);
                        }
                        previewHTML += `<td class="p-3 border-r border-gray-200 text-center"><input type="number" placeholder="Value" value="${value}" class="w-full p-2 border border-gray-300 rounded upload-bar-value text-center" step="any" min="0"></td>`;
                    });
                    
                    previewHTML += `
                        <td class="p-3 text-center">
                            <button type="button" class="p-2 text-red-500 hover:bg-red-100 rounded transition duration-200" onclick="removeUploadDataRow(this)" title="Remove row">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    `;
                    previewHTML += '</tr>';
                });
                
                previewHTML += '</tbody>';
                previewHTML += '</table>';
                
                previewHTML += '<button type="button" id="add-upload-bar-data" class="mt-3 px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded transition duration-200"><i class="fas fa-plus mr-2"></i>Add Category</button>';
                previewHTML += '</div>';
            }
            
            previewContainer.innerHTML = previewHTML;
            
            // Add event listeners for add buttons
            const addPieBtn = document.getElementById('add-upload-pie-data');
            if (addPieBtn) {
                addPieBtn.addEventListener('click', addUploadPieDataRow);
            }
            
            const addPieSeriesBtn = document.getElementById('add-upload-pie-series-btn');
            if (addPieSeriesBtn) {
                addPieSeriesBtn.addEventListener('click', addUploadPieSeries);
            }
            
            const addBarBtn = document.getElementById('add-upload-bar-data');
            if (addBarBtn) {
                addBarBtn.addEventListener('click', addUploadBarDataRow);
            }
            
            const addSeriesBtn = document.getElementById('add-upload-series-btn');
            if (addSeriesBtn) {
                addSeriesBtn.addEventListener('click', addUploadSeries);
            }
            
            // Add event listeners for series label changes
            const seriesLabels = document.querySelectorAll('.upload-series-label');
            seriesLabels.forEach((label, index) => {
                label.addEventListener('input', function() {
                    const header = document.querySelector(`th.series-header[data-series="${index}"]`);
                    if (header) {
                        header.textContent = this.value || `Series ${index + 1}`;
                    }
                });
            });
        }
        
        function addUploadPieDataRow() {
            const container = document.getElementById('upload-pie-data-points');
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            
            const row = document.createElement('div');
            row.className = 'data-point-row mb-2';
            row.innerHTML = `
                <input type="text" placeholder="Label" class="flex-1 p-2 border border-gray-300 rounded upload-pie-label">
                <input type="number" placeholder="Value" class="w-20 p-2 border border-gray-300 rounded upload-pie-value" step="any" min="0">
                <input type="color" value="${randomColor}" class="w-10 h-10 border border-gray-300 rounded upload-pie-color">
                <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200" onclick="removeUploadDataRow(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(row);
        }
        
        function addUploadSeries() {
            const seriesContainer = document.querySelector('.upload-series-item').parentElement;
            const currentSeries = seriesContainer.querySelectorAll('.upload-series-item').length;
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#f97316'];
            const newColor = colors[currentSeries % colors.length];
            
            const addButton = document.getElementById('add-upload-series-btn');
            
            const newSeriesHTML = `
                <div class="flex items-center gap-2 upload-series-item">
                    <span class="font-medium text-blue-800">Series ${currentSeries + 1}:</span>
                    <input type="text" value="Series ${currentSeries + 1}" class="p-2 border border-gray-300 rounded upload-series-label" placeholder="Series name" style="width: 150px;">
                    <input type="color" value="${newColor}" class="w-8 h-8 border border-gray-300 rounded upload-series-color" title="Series color">
                    <button type="button" class="p-1 text-red-500 hover:bg-red-100 rounded" onclick="removeUploadSeries(this)" title="Remove series">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            `;
            
            // Insert before the Add Series button
            addButton.insertAdjacentHTML('beforebegin', newSeriesHTML);
            
            // Update the data table to include the new series column
            updateUploadDataTable();
        }
        
        function removeUploadSeries(button) {
            const seriesItem = button.closest('.upload-series-item');
            const seriesContainer = seriesItem.parentElement;
            const seriesItems = seriesContainer.querySelectorAll('.upload-series-item');
            
            // Don't allow removing if only one series remains
            if (seriesItems.length <= 1) {
                showGraphNotification('At least one series is required', 'error');
                return;
            }
            
            seriesItem.remove();
            
            // Renumber remaining series
            const remainingSeries = seriesContainer.querySelectorAll('.upload-series-item');
            remainingSeries.forEach((item, index) => {
                const label = item.querySelector('span');
                label.textContent = `Series ${index + 1}:`;
            });
            
            // Update the data table to remove the series column
            updateUploadDataTable();
        }
        
        function updateUploadDataTable() {
            const seriesItems = document.querySelectorAll('.upload-series-item');
            const seriesCount = seriesItems.length;
            const dataTable = document.getElementById('upload-data-table');
            
            if (!dataTable) return;
            
            // Update table header
            const headerRow = dataTable.querySelector('thead tr');
            headerRow.innerHTML = '';
            headerRow.innerHTML += '<th class="p-3 text-left font-medium text-gray-700 border-r border-gray-300">Category</th>';
            
            seriesItems.forEach((item, index) => {
                const seriesLabel = item.querySelector('.upload-series-label').value;
                headerRow.innerHTML += `<th class="p-3 text-center font-medium text-gray-700 border-r border-gray-300 series-header" data-series="${index}">${seriesLabel}</th>`;
            });
            
            headerRow.innerHTML += '<th class="p-3 text-center font-medium text-gray-700 w-20">Actions</th>';
            
            // Update data rows
            const dataRows = dataTable.querySelectorAll('tbody tr');
            dataRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const categoryCell = cells[0];
                const actionCell = cells[cells.length - 1];
                
                // Get existing values
                const existingValues = [];
                for (let i = 1; i < cells.length - 1; i++) {
                    const input = cells[i].querySelector('input');
                    existingValues.push(input ? input.value : '');
                }
                
                // Rebuild the row with correct number of series columns
                row.innerHTML = '';
                row.appendChild(categoryCell);
                
                // Add series value cells
                for (let i = 0; i < seriesCount; i++) {
                    const existingValue = existingValues[i] || '';
                    const valueCell = document.createElement('td');
                    valueCell.className = 'p-3 border-r border-gray-200 text-center';
                    valueCell.innerHTML = `<input type="number" placeholder="Value" value="${existingValue}" class="w-full p-2 border border-gray-300 rounded upload-bar-value text-center" step="any" min="0">`;
                    row.appendChild(valueCell);
                }
                
                row.appendChild(actionCell);
            });
            
            // Also update series labels in the series section when they change
            seriesItems.forEach((item, index) => {
                const labelInput = item.querySelector('.upload-series-label');
                labelInput.addEventListener('input', function() {
                    const header = dataTable.querySelector(`th.series-header[data-series="${index}"]`);
                    if (header) {
                        header.textContent = this.value || `Series ${index + 1}`;
                    }
                });
            });
        }
        
        function updatePieDataPreview() {
            // This function can be called when pie series selection changes
            console.log('Pie series selection changed');
        }
        
        function handleUploadPieValueTypeChange() {
            const valueType = document.querySelector('input[name="uploadPieValueType"]:checked').value;
            console.log('Pie value type changed to:', valueType);
        }
        
        function addUploadPieSeries() {
            const seriesContainer = document.querySelector('.upload-pie-series-item').parentElement;
            const currentSeries = seriesContainer.querySelectorAll('.upload-pie-series-item').length;
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#f97316'];
            const newColor = colors[currentSeries % colors.length];
            
            const addButton = document.getElementById('add-upload-pie-series-btn');
            
            const newSeriesHTML = `
                <div class="inline-flex items-center gap-2 mr-4 mb-2 upload-pie-series-item">
                    <span class="font-medium">Series ${currentSeries + 1}:</span>
                    <input type="text" value="Series ${currentSeries + 1}" class="p-2 border border-gray-300 rounded upload-pie-series-label" style="width: 180px;" data-series-index="${currentSeries}">
                    <input type="color" value="${newColor}" class="w-8 h-8 border border-gray-300 rounded upload-pie-series-color" data-series-index="${currentSeries}">
                    <button type="button" class="p-1 text-red-500 hover:bg-red-100 rounded" onclick="removeUploadPieSeries(this)" title="Remove series">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            `;
            
            // Insert before the Add Series button
            addButton.insertAdjacentHTML('beforebegin', newSeriesHTML);
            
            // Add new column to table
            addUploadPieTableColumn(`Series ${currentSeries + 1}`);
        }
        
        function addUploadPieTableColumn(headerName) {
            const table = document.getElementById('upload-pie-data-table');
            if (table) {
                // Add header
                const headerRow = table.querySelector('thead tr');
                const newHeader = document.createElement('th');
                newHeader.className = 'p-3 text-center font-medium text-gray-700 border-r border-gray-300 pie-series-header';
                newHeader.textContent = headerName;
                headerRow.insertBefore(newHeader, headerRow.lastElementChild);
                
                // Add cells to existing rows
                const bodyRows = table.querySelectorAll('tbody tr');
                bodyRows.forEach(row => {
                    const newCell = document.createElement('td');
                    newCell.className = 'p-3 border-r border-gray-200 text-center';
                    newCell.innerHTML = '<input type="number" placeholder="Value" value="0" class="w-full p-2 border border-gray-300 rounded upload-pie-value text-center" step="any" min="0">';
                    row.insertBefore(newCell, row.lastElementChild);
                });
            }
        }
        
        function removeUploadPieSeries(button) {
            // Remove series and corresponding table column
            button.parentElement.remove();
        }
        
        function addUploadBarDataRow() {
            const tableBody = document.getElementById('upload-bar-data-points');
            const seriesCount = document.querySelectorAll('.upload-series-item').length;
            const rowCount = tableBody.querySelectorAll('tr').length;
            const bgColor = rowCount % 2 === 0 ? 'bg-white' : 'bg-gray-50';
            
            let seriesCells = '';
            for (let i = 0; i < seriesCount; i++) {
                seriesCells += '<td class="p-3 border-r border-gray-200 text-center"><input type="number" placeholder="Value" class="w-full p-2 border border-gray-300 rounded upload-bar-value text-center" step="any" min="0"></td>';
            }
            
            const newRow = document.createElement('tr');
            newRow.className = `${bgColor} hover:bg-blue-50 transition duration-200`;
            newRow.innerHTML = `
                <td class="p-3 border-r border-gray-200"><input type="text" placeholder="Category" class="w-full p-2 border border-gray-300 rounded upload-bar-category"></td>
                ${seriesCells}
                <td class="p-3 text-center">
                    <button type="button" class="p-2 text-red-500 hover:bg-red-100 rounded transition duration-200" onclick="removeUploadDataRow(this)" title="Remove row">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            
            tableBody.appendChild(newRow);
        }
        
        function removeUploadDataRow(button) {
            const row = button.closest('tr') || button.closest('.data-point-row');
            if (row) {
                row.remove();
                
                // If it's a table row, update alternating row colors
                if (button.closest('table')) {
                    const tableBody = button.closest('tbody');
                    const rows = tableBody.querySelectorAll('tr');
                    rows.forEach((row, index) => {
                        row.className = row.className.replace(/bg-white|bg-gray-50/g, '');
                        const bgColor = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                        row.className += ` ${bgColor}`;
                    });
                }
            }
        }
        
        function handleUploadTitleSelection() {
            const select = document.getElementById('upload-graph-title-select');
            const customInput = document.getElementById('upload-graph-title');
            
            if (select.value === 'custom') {
                customInput.classList.remove('hidden');
                customInput.focus();
                customInput.value = '';
            } else {
                customInput.classList.add('hidden');
                customInput.value = select.value;
            }
        }
        
        function handleUploadValueTypeChange() {
            const valueType = document.querySelector('input[name="uploadValueType"]:checked').value;
            const warningDiv = document.getElementById('upload-percentage-warning');
            const valueInputs = document.querySelectorAll('.upload-bar-value, .upload-pie-value');
            
            if (valueType === 'percentages') {
                warningDiv.classList.remove('hidden');
                valueInputs.forEach(input => {
                    input.setAttribute('max', '100');
                    if (input.placeholder && !input.placeholder.includes('(%)')) {
                        input.placeholder = input.placeholder + ' (%)';
                    }
                });
            } else {
                warningDiv.classList.add('hidden');
                valueInputs.forEach(input => {
                    input.removeAttribute('max');
                    if (input.placeholder && input.placeholder.includes(' (%)')) {
                        input.placeholder = input.placeholder.replace(' (%)', '');
                    }
                });
            }
            
            // Refresh data preview to update value formatting
            setupDataPreview();
        }
        
        function cleanPercentageValue(value) {
            if (typeof value === 'string') {
                // Remove % symbol and convert to number
                return parseFloat(value.replace('%', '')) || 0;
            }
            return parseFloat(value) || 0;
        }
        
        function createGraphFromUpload() {
            // Get title
            const titleSelect = document.getElementById('upload-graph-title-select');
            const titleInput = document.getElementById('upload-graph-title');
            const title = titleSelect.value === 'custom' ? titleInput.value : titleSelect.value;
            
            if (!title) {
                showGraphNotification('Please enter a graph title', 'error');
                return;
            }
            
            let graphData = {};
            
            if (selectedUploadGraphType === 'pie') {
                // Collect data from new table structure
                const categories = Array.from(document.querySelectorAll('#upload-pie-data-points .upload-pie-category'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                // Get all series data from series configuration section
                const seriesLabels = Array.from(document.querySelectorAll('#upload-pie-series-config .upload-pie-series-label'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesColors = Array.from(document.querySelectorAll('#upload-pie-series-config .upload-pie-series-color'))
                    .map(input => input.value);
                
                // Debug logging
                console.log('Individual Pie Debug:');
                console.log('Categories found:', categories.length, categories);
                console.log('Series labels found:', seriesLabels.length, seriesLabels);
                console.log('Series colors found:', seriesColors.length, seriesColors);
                
                // For pie charts, we'll collect all series data but use the first series for the chart
                const tableRows = document.querySelectorAll('#upload-pie-data-points tr');
                const allSeriesData = [];
                
                // Collect data for each series
                seriesLabels.forEach((seriesLabel, seriesIndex) => {
                    const seriesValues = [];
                    tableRows.forEach(row => {
                        const valueInputs = row.querySelectorAll('.upload-pie-value');
                        if (valueInputs[seriesIndex]) {
                            let value = parseFloat(valueInputs[seriesIndex].value);
                            if (isNaN(value)) value = 0;
                            seriesValues.push(value);
                        }
                    });
                    
                    allSeriesData.push({
                        label: seriesLabel,
                        values: seriesValues,
                        color: seriesColors[seriesIndex] || '#3b82f6'
                    });
                });
                
                console.log('All series data collected:', allSeriesData);
                
                if (categories.length < 2) {
                    showGraphNotification('Please have at least 2 data points', 'error');
                    return;
                }
                
                if (allSeriesData.length === 0) {
                    showGraphNotification('Please have at least 1 series', 'error');
                    return;
                }
                
                // Detect if this is percentage data
                const isPercentageData = uploadedFileData.isPercentageData || false;
                
                graphData = {
                    // For backward compatibility, use the first series as main data
                    labels: categories,
                    values: allSeriesData[0] ? allSeriesData[0].values : [],
                    colors: categories.map((_, index) => {
                        const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                        return colors[index % colors.length];
                    }),
                    valueType: isPercentageData ? 'percentages' : 'values',
                    // Include all series data for future use
                    allSeriesData: allSeriesData
                };
                
            } else if (selectedUploadGraphType === 'bar') {
                const categories = Array.from(document.querySelectorAll('.upload-bar-category'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesLabels = Array.from(document.querySelectorAll('.upload-series-label'))
                    .map(input => input.value.trim())
                    .filter(val => val !== '');
                
                const seriesColors = Array.from(document.querySelectorAll('.upload-series-color'))
                    .map(input => input.value);
                
                // Get value type
                const valueType = document.querySelector('input[name="uploadValueType"]:checked').value;
                
                if (categories.length < 2) {
                    showGraphNotification('Please have at least 2 categories', 'error');
                    return;
                }
                
                if (seriesLabels.length === 0) {
                    showGraphNotification('Please have at least 1 series', 'error');
                    return;
                }
                
                // Collect values from table structure
                const tableRows = document.querySelectorAll('#upload-bar-data-points tr');
                const values = [];
                
                tableRows.forEach(row => {
                    const valueInputs = row.querySelectorAll('.upload-bar-value');
                    const rowValues = Array.from(valueInputs).map(input => {
                        let value = parseFloat(input.value);
                        if (isNaN(value)) return 0;
                        return value;
                    });
                    
                    if (rowValues.length === seriesLabels.length) {
                        values.push(rowValues);
                    }
                });
                
                graphData = {
                    categories: categories.slice(0, values.length),
                    seriesLabels: seriesLabels,
                    seriesColors: seriesColors,
                    values: values,
                    valueType: valueType // Include value type in the data
                };
            }
            
            // Create form data and submit
            const formData = new FormData();
            formData.append('title', title);
            formData.append('type', selectedUploadGraphType);
            formData.append('data', JSON.stringify(graphData));
            
            // Show loading state
            const createBtn = document.getElementById('upload-create-graph-btn');
            const originalText = createBtn.textContent;
            createBtn.textContent = 'Creating...';
            createBtn.disabled = true;
            
            // Determine the correct path to AddGraph.php for CEIT_Modules
            let addGraphPath = getCEITGraphPath('AddGraph.php');
            
            fetch(addGraphPath, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                createBtn.textContent = originalText;
                createBtn.disabled = false;
                
                if (data.success) {
                    showGraphNotification(data.message || 'Graph created successfully!', 'success');
                    setTimeout(() => {
                        document.getElementById('graph-upload-modal').classList.add('hidden');
                        location.reload();
                    }, 1500);
                } else {
                    showGraphNotification(data.message || 'Error creating graph', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                createBtn.textContent = originalText;
                createBtn.disabled = false;
                showGraphNotification('An error occurred while creating the graph.', 'error');
            });
        }
        
        // Group Upload Functions
        function resetGroupUpload() {
            groupUploadStep = 1;
            selectedGroupGraphType = '';
            groupGraphsData = [];
            numberOfGroupGraphs = 0;
            
            // Reset form fields
            document.getElementById('group-title').value = '';
            document.getElementById('number-of-graphs').value = '';
            document.getElementById('group-graphs-inputs').innerHTML = '';
            document.getElementById('group-graphs-configuration').innerHTML = '';
            
            // Reset graph type selection
            document.querySelectorAll('.group-graph-type-option').forEach(opt => {
                opt.classList.remove('border-blue-500', 'bg-blue-50');
            });
            
            // Reset buttons
            document.getElementById('group-process-files-btn').disabled = true;
            document.getElementById('group-upload-continue-btn').disabled = true;
            
            // Show step 1
            showGroupUploadStep(1);
        }
        
        function showGroupUploadStep(step) {
            groupUploadStep = step;
            
            // Hide all group upload steps
            document.querySelectorAll('#group-upload-content .upload-step').forEach(stepEl => {
                stepEl.classList.add('hidden');
            });
            
            // Show current step
            document.getElementById(`group-upload-step-${step}`).classList.remove('hidden');
            
            // If step 3, generate configuration interface
            if (step === 3) {
                generateGroupGraphsConfiguration();
            }
        }
        
        function generateGroupGraphInputs() {
            const numberOfGraphs = parseInt(document.getElementById('number-of-graphs').value);
            const container = document.getElementById('group-graphs-inputs');
            
            if (!numberOfGraphs || numberOfGraphs < 2) {
                container.innerHTML = '';
                document.getElementById('group-process-files-btn').disabled = true;
                return;
            }
            
            numberOfGroupGraphs = numberOfGraphs;
            let inputsHTML = '<div class="space-y-4">';
            
            for (let i = 1; i <= numberOfGraphs; i++) {
                inputsHTML += `
                    <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                        <h4 class="font-medium text-gray-800 mb-3">Graph ${i}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Graph Title</label>
                                <input type="text" id="group-graph-title-${i}" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter graph ${i} title" onchange="checkGroupInputs()">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload File</label>
                                <input type="file" id="group-graph-file-${i}" accept=".csv,.xlsx,.xls" class="w-full p-2 border border-gray-300 rounded" onchange="checkGroupInputs()">
                            </div>
                        </div>
                    </div>
                `;
            }
            
            inputsHTML += '</div>';
            container.innerHTML = inputsHTML;
            
            checkGroupInputs();
        }
        
        function checkGroupInputs() {
            const groupTitle = document.getElementById('group-title').value.trim();
            let allFieldsFilled = groupTitle !== '';
            
            for (let i = 1; i <= numberOfGroupGraphs; i++) {
                const title = document.getElementById(`group-graph-title-${i}`).value.trim();
                const file = document.getElementById(`group-graph-file-${i}`).files[0];
                
                if (!title || !file) {
                    allFieldsFilled = false;
                    break;
                }
            }
            
            document.getElementById('group-process-files-btn').disabled = !allFieldsFilled;
        }
        
        function processGroupFiles() {
            const groupTitle = document.getElementById('group-title').value.trim();
            
            if (!groupTitle) {
                showGraphNotification('Please enter a group title', 'error');
                return;
            }
            
            // Show loading state
            const processBtn = document.getElementById('group-process-files-btn');
            const originalText = processBtn.textContent;
            processBtn.textContent = 'Processing...';
            processBtn.disabled = true;
            
            // Process each file
            const promises = [];
            groupGraphsData = [];
            
            for (let i = 1; i <= numberOfGroupGraphs; i++) {
                const title = document.getElementById(`group-graph-title-${i}`).value.trim();
                const file = document.getElementById(`group-graph-file-${i}`).files[0];
                
                if (title && file) {
                    promises.push(processIndividualGroupFile(file, title, i));
                }
            }
            
            Promise.all(promises)
                .then(results => {
                    processBtn.textContent = originalText;
                    processBtn.disabled = false;
                    
                    // Check if all files were processed successfully
                    const successfulResults = results.filter(result => result.success);
                    
                    if (successfulResults.length === numberOfGroupGraphs) {
                        groupGraphsData = successfulResults;
                        showGroupUploadStep(2);
                    } else {
                        showGraphNotification('Some files could not be processed. Please check your files and try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error processing files:', error);
                    processBtn.textContent = originalText;
                    processBtn.disabled = false;
                    showGraphNotification('Error processing files. Please try again.', 'error');
                });
        }
        
        function processIndividualGroupFile(file, title, index) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', file);
                
                const uploadPath = getCEITGraphPath('UploadGraph.php');
                
                fetch(uploadPath, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resolve({
                            success: true,
                            title: title,
                            index: index,
                            data: data.data
                        });
                    } else {
                        showGraphNotification(`Error processing ${title}: ${data.message}`, 'error');
                        resolve({ success: false, title: title, index: index });
                    }
                })
                .catch(error => {
                    console.error(`Error processing file ${index}:`, error);
                    showGraphNotification(`Error processing ${title}`, 'error');
                    resolve({ success: false, title: title, index: index });
                });
            });
        }
        
        function createGroupGraphFromUpload() {
            if (!selectedGroupGraphType) {
                showGraphNotification('Please select a graph type', 'error');
                return;
            }
            
            const groupTitle = document.getElementById('group-title').value.trim();
            
            if (!groupTitle) {
                showGraphNotification('Please enter a group title', 'error');
                return;
            }
            
            // Collect data from configuration interface
            const graphs = [];
            
            for (let i = 0; i < groupGraphsData.length; i++) {
                // Get updated title from configuration
                const configTitle = document.getElementById(`group-graph-config-title-${i}`).value.trim();
                
                if (!configTitle) {
                    showGraphNotification(`Please enter a title for Graph ${i + 1}`, 'error');
                    return;
                }
                
                let processedData = {};
                
                if (selectedGroupGraphType === 'pie') {
                    // Collect pie chart data from table structure (new format)
                    const categories = Array.from(document.querySelectorAll(`#group-pie-data-points-${i} .group-pie-category`))
                        .map(input => input.value.trim())
                        .filter(val => val !== '');
                    
                    // Get all series data from the series configuration section for this specific graph
                    // Find the table first, then get its parent container that contains the series configuration
                    const dataTable = document.querySelector(`#group-pie-data-table-${i}`);
                    if (!dataTable) {
                        console.error(`Group pie data table not found for graph ${i}`);
                        showGraphNotification(`Data table not found for ${configTitle}`, 'error');
                        return;
                    }
                    
                    // Try multiple strategies to find the graph configuration container
                    let graphConfigContainer = null;
                    let seriesItems = [];
                    
                    // Strategy 1: Find the container with specific border classes
                    graphConfigContainer = dataTable.closest('.border.border-gray-300');
                    if (graphConfigContainer) {
                        seriesItems = graphConfigContainer.querySelectorAll('.group-pie-series-item');
                        console.log(`Strategy 1 success: Found ${seriesItems.length} series items`);
                    }
                    
                    // Strategy 2: If strategy 1 fails, try finding any parent with border class
                    if (seriesItems.length === 0) {
                        graphConfigContainer = dataTable.closest('.border');
                        if (graphConfigContainer) {
                            seriesItems = graphConfigContainer.querySelectorAll('.group-pie-series-item');
                            console.log(`Strategy 2: Found ${seriesItems.length} series items`);
                        }
                    }
                    
                    // Strategy 3: If both fail, search within the entire group configuration container
                    if (seriesItems.length === 0) {
                        const groupConfigContainer = document.getElementById('group-graphs-configuration');
                        if (groupConfigContainer) {
                            // Find all series items and filter by proximity to our table
                            const allSeriesItems = groupConfigContainer.querySelectorAll('.group-pie-series-item');
                            const tableRect = dataTable.getBoundingClientRect();
                            
                            // Find series items that are close to our table (within the same graph config)
                            seriesItems = Array.from(allSeriesItems).filter(item => {
                                const itemRect = item.getBoundingClientRect();
                                // Check if the series item is above our table (within reasonable distance)
                                return itemRect.bottom <= tableRect.top + 200 && itemRect.top >= tableRect.top - 400;
                            });
                            console.log(`Strategy 3: Found ${seriesItems.length} series items by proximity`);
                        }
                    }
                    
                    // Strategy 4: Last resort - find series items by index pattern
                    if (seriesItems.length === 0) {
                        // Look for series items that might be associated with this graph index
                        const allSeriesItems = document.querySelectorAll('.group-pie-series-item');
                        // This is a fallback - in a real scenario, we'd need better identification
                        console.log(`Strategy 4: Found ${allSeriesItems.length} total series items globally`);
                        
                        // For now, let's try to find series items in the same parent container
                        if (dataTable.parentElement) {
                            let currentParent = dataTable.parentElement;
                            while (currentParent && currentParent !== document.body) {
                                const foundItems = currentParent.querySelectorAll('.group-pie-series-item');
                                if (foundItems.length > 0) {
                                    seriesItems = foundItems;
                                    console.log(`Strategy 4 success: Found ${seriesItems.length} series items in parent`);
                                    break;
                                }
                                currentParent = currentParent.parentElement;
                            }
                        }
                    }
                    
                    if (seriesItems.length === 0) {
                        console.error(`No series items found for graph ${i} using any strategy`);
                        console.log('Available elements:');
                        console.log('- Data table:', dataTable);
                        console.log('- All .group-pie-series-item elements:', document.querySelectorAll('.group-pie-series-item'));
                        console.log('- All .border elements:', document.querySelectorAll('.border'));
                        showGraphNotification(`No series configuration found for ${configTitle}`, 'error');
                        return;
                    }
                    
                    // Now search for series items within this specific graph's container
                    const seriesLabels = Array.from(seriesItems)
                        .map(item => {
                            const labelInput = item.querySelector('.group-pie-series-label');
                            return labelInput ? labelInput.value.trim() : '';
                        })
                        .filter(val => val !== '');
                    
                    const seriesColors = Array.from(seriesItems)
                        .map(item => {
                            const colorInput = item.querySelector('.group-pie-series-color');
                            return colorInput ? colorInput.value : '#3b82f6';
                        });
                    
                    // Debug logging
                    console.log(`Group Pie Debug - Graph ${i}:`);
                    console.log('Data table found:', dataTable);
                    console.log('Graph config container found:', graphConfigContainer);
                    console.log('Series items found:', seriesItems.length);
                    console.log('Categories found:', categories.length, categories);
                    console.log('Series labels found:', seriesLabels.length, seriesLabels);
                    console.log('Series colors found:', seriesColors.length, seriesColors);
                    console.log('Categories found:', categories.length, categories);
                    console.log('Series items found:', seriesItems.length);
                    console.log('Series labels found:', seriesLabels.length, seriesLabels);
                    console.log('Series colors found:', seriesColors.length, seriesColors);
                    
                    // For pie charts, we'll use all series data but the chart will display them appropriately
                    const tableRows = document.querySelectorAll(`#group-pie-data-points-${i} tr`);
                    const allSeriesData = [];
                    
                    // Collect data for each series
                    seriesLabels.forEach((seriesLabel, seriesIndex) => {
                        const seriesValues = [];
                        tableRows.forEach(row => {
                            const valueInputs = row.querySelectorAll('.group-pie-value');
                            if (valueInputs[seriesIndex]) {
                                let value = parseFloat(valueInputs[seriesIndex].value);
                                if (isNaN(value)) value = 0;
                                seriesValues.push(value);
                            }
                        });
                        
                        allSeriesData.push({
                            label: seriesLabel,
                            values: seriesValues,
                            color: seriesColors[seriesIndex] || '#3b82f6'
                        });
                    });
                    
                    console.log('All series data collected:', allSeriesData);
                    
                    if (categories.length < 2) {
                        showGraphNotification(`Please have at least 2 data points for ${configTitle}`, 'error');
                        return;
                    }
                    
                    if (allSeriesData.length === 0) {
                        showGraphNotification(`Please have at least 1 series for ${configTitle}`, 'error');
                        return;
                    }
                    
                    processedData = {
                        categories: categories,
                        seriesData: allSeriesData,
                        // For backward compatibility, also include the first series as labels/values
                        labels: categories,
                        values: allSeriesData[0] ? allSeriesData[0].values : [],
                        colors: categories.map((_, index) => {
                            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                            return colors[index % colors.length];
                        })
                    };
                    
                } else if (selectedGroupGraphType === 'bar') {
                    // Collect bar chart data from configuration interface
                    const categories = Array.from(document.querySelectorAll(`#group-bar-data-points-${i} .group-bar-category`))
                        .map(input => input.value.trim())
                        .filter(val => val !== '');
                    
                    const seriesLabels = Array.from(document.querySelectorAll(`#group-bar-series-config-${i} .group-bar-series-label`))
                        .map(input => input.value.trim())
                        .filter(val => val !== '');
                    
                    const seriesColors = Array.from(document.querySelectorAll(`#group-bar-series-config-${i} .group-bar-series-color`))
                        .map(input => input.value);
                    
                    // Get value type
                    const valueType = document.querySelector(`input[name="groupBarValueType${i}"]:checked`).value;
                    
                    if (categories.length < 2) {
                        showGraphNotification(`Please have at least 2 categories for ${configTitle}`, 'error');
                        return;
                    }
                    
                    if (seriesLabels.length === 0) {
                        showGraphNotification(`Please have at least 1 series for ${configTitle}`, 'error');
                        return;
                    }
                    
                    // Collect values from table structure (new format)
                    const tableRows = document.querySelectorAll(`#group-bar-data-points-${i} tr`);
                    const values = [];
                    
                    tableRows.forEach(row => {
                        const valueInputs = row.querySelectorAll('.group-bar-value');
                        const rowValues = Array.from(valueInputs).map(input => {
                            let value = parseFloat(input.value);
                            if (isNaN(value)) return 0;
                            return value;
                        });
                        
                        if (rowValues.length === seriesLabels.length) {
                            values.push(rowValues);
                        }
                    });
                    
                    if (values.length !== categories.length) {
                        console.log('Debug - Categories:', categories.length, 'Values rows:', values.length);
                        console.log('Debug - Categories:', categories);
                        console.log('Debug - Values:', values);
                        showGraphNotification(`Data mismatch for ${configTitle}. Categories: ${categories.length}, Value rows: ${values.length}`, 'error');
                        return;
                    }
                    
                    processedData = {
                        categories: categories,
                        seriesLabels: seriesLabels,
                        seriesColors: seriesColors,
                        values: values,
                        valueType: valueType
                    };
                }
                
                graphs.push({
                    title: configTitle,
                    type: selectedGroupGraphType,
                    data: processedData
                });
            }
            
            if (graphs.length < 2) {
                showGraphNotification('Please have at least 2 graphs in the group', 'error');
                return;
            }
            
            const groupGraphData = {
                graphs: graphs
            };
            
            // Create form data and submit
            const formData = new FormData();
            formData.append('title', groupTitle);
            formData.append('type', 'group');
            formData.append('data', JSON.stringify(groupGraphData));
            
            // Show loading state
            const createBtn = document.getElementById('group-create-graphs-btn');
            const originalText = createBtn.textContent;
            createBtn.textContent = 'Creating...';
            createBtn.disabled = true;
            
            const addGraphPath = getCEITGraphPath('AddGraph.php');
            
            fetch(addGraphPath, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                createBtn.textContent = originalText;
                createBtn.disabled = false;
                
                if (data.success) {
                    showGraphNotification(data.message || 'Group graph created successfully!', 'success');
                    setTimeout(() => {
                        document.getElementById('graph-upload-modal').classList.add('hidden');
                        location.reload();
                    }, 1500);
                } else {
                    showGraphNotification(data.message || 'Error creating group graph', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                createBtn.textContent = originalText;
                createBtn.disabled = false;
                showGraphNotification('An error occurred while creating the group graph.', 'error');
            });
        }
        
        function generateGroupGraphsConfiguration() {
            const container = document.getElementById('group-graphs-configuration');
            
            if (!groupGraphsData || groupGraphsData.length === 0) {
                container.innerHTML = '<p class="text-red-500">No graph data available. Please go back and process files again.</p>';
                return;
            }
            
            let configHTML = '';
            
            groupGraphsData.forEach((graphData, index) => {
                configHTML += `
                    <div class="border border-gray-300 rounded-lg p-4 bg-white shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-chart-${selectedGroupGraphType === 'pie' ? 'pie' : 'bar'} mr-2 text-blue-500"></i>
                                ${graphData.title}
                            </h4>
                            <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                ${graphData.data.fileName || 'Uploaded File'}
                            </span>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="font-medium text-gray-700 mb-2">Graph Title</h5>
                            <input type="text" id="group-graph-config-title-${index}" value="${graphData.title}" 
                                   class="w-full p-2 border border-gray-300 rounded-md" 
                                   placeholder="Enter graph title">
                        </div>
                `;
                
                if (selectedGroupGraphType === 'pie') {
                    configHTML += generateGroupPieConfiguration(graphData, index);
                } else if (selectedGroupGraphType === 'bar') {
                    configHTML += generateGroupBarConfiguration(graphData, index);
                }
                
                configHTML += '</div>';
            });
            
            container.innerHTML = configHTML;
            
            // Add event listeners for dynamic elements
            addGroupConfigurationEventListeners();
        }
        
        function generateGroupPieConfiguration(graphData, graphIndex) {
            const valueColumns = graphData.data.headers.slice(1);
            
            let configHTML = `
                <div class="mb-4">
                    <h5 class="font-medium text-gray-700 mb-2">Series Configuration</h5>
                    <div class="space-y-2">
            `;
            
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            valueColumns.forEach((header, index) => {
                const color = colors[index % colors.length];
                configHTML += `
                    <div class="inline-flex items-center gap-2 mr-4 mb-2 group-pie-series-item">
                        <span class="font-medium">Series ${index + 1}:</span>
                        <input type="text" value="${header}" class="p-2 border border-gray-300 rounded group-pie-series-label" style="width: 180px;" data-series-index="${index}">
                        <input type="color" value="${color}" class="w-8 h-8 border border-gray-300 rounded group-pie-series-color" data-series-index="${index}">
                    </div>
                `;
            });
            
            configHTML += `
                    <button type="button" class="inline-flex items-center px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition duration-200 ml-2" 
                            onclick="addGroupPieSeriesBtn(${graphIndex})">
                        <i class="fas fa-plus mr-1"></i>Add Series
                    </button>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-medium mb-2"><i class="fas fa-calculator mr-2"></i>Value Input Type</h5>
                    <div class="flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="groupPieValueType${graphIndex}" value="values" ${!graphData.data.isPercentageData ? 'checked' : ''} class="mr-2" 
                                   onchange="handleGroupPieValueTypeChange(${graphIndex})">
                            <span>Whole Values</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="groupPieValueType${graphIndex}" value="percentages" ${graphData.data.isPercentageData ? 'checked' : ''} class="mr-2" 
                                   onchange="handleGroupPieValueTypeChange(${graphIndex})">
                            <span>Percentages (%)</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-medium mb-3"><i class="fas fa-table mr-2"></i>Data Preview & Editing</h5>
                    
                    <!-- Create table like individual pie chart -->
                    <table class="w-full border border-gray-300 rounded-lg overflow-hidden" id="group-pie-data-table-${graphIndex}">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-3 text-left font-medium text-gray-700 border-r border-gray-300">Category</th>
            `;
            
            valueColumns.forEach((header, index) => {
                configHTML += `<th class="p-3 text-center font-medium text-gray-700 border-r border-gray-300 group-pie-series-header" data-series="${index}">${header}</th>`;
            });
            
            configHTML += `
                                <th class="p-3 text-center font-medium text-gray-700 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="group-pie-data-points-${graphIndex}">
            `;
            
            // Add data rows
            graphData.data.rows.forEach((row, rowIndex) => {
                const rowValues = Object.values(row);
                const category = rowValues[0] || '';
                const bgColor = rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                
                configHTML += `<tr class="${bgColor} hover:bg-blue-50 transition duration-200">`;
                configHTML += `<td class="p-3 border-r border-gray-200"><input type="text" placeholder="Category" value="${category}" class="w-full p-2 border border-gray-300 rounded group-pie-category"></td>`;
                
                // Create value inputs for each series column
                valueColumns.forEach((header, colIndex) => {
                    let value = rowValues[colIndex + 1] || ''; // +1 because first column is category
                    // Clean percentage values if they contain % symbol
                    if (typeof value === 'string' && value.includes('%')) {
                        value = parseFloat(value.replace('%', '')) || 0;
                    }
                    configHTML += `<td class="p-3 border-r border-gray-200 text-center"><input type="number" placeholder="Value" value="${value}" class="w-full p-2 border border-gray-300 rounded group-pie-value text-center" step="any" min="0"></td>`;
                });
                
                configHTML += `
                    <td class="p-3 text-center">
                        <button type="button" class="p-2 text-red-500 hover:bg-red-100 rounded transition duration-200" onclick="removeGroupPieDataRow(${graphIndex}, ${rowIndex})" title="Remove row">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                `;
                configHTML += '</tr>';
            });
            
            configHTML += `
                        </tbody>
                    </table>
                    
                    <button type="button" class="mt-3 px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded transition duration-200" 
                            onclick="addGroupPieDataPoint(${graphIndex})">
                        <i class="fas fa-plus mr-2"></i>Add Category
                    </button>
                </div>
            `;
            
            return configHTML;
        }
        
        function generateGroupBarConfiguration(graphData, graphIndex) {
            const valueColumns = graphData.data.headers.slice(1); // All columns except first (category)
            
            let configHTML = `
                <div class="mb-4">
                    <h5 class="font-medium text-gray-700 mb-2">Auto-Detected Configuration</h5>
                    <div class="p-3 bg-green-50 rounded mb-3">
                        <p class="text-sm text-green-800 font-medium mb-1">Series Detection:</p>
                        <p class="text-sm text-green-700">✅ Category Column: <strong>${graphData.data.headers[0]}</strong></p>
                        <p class="text-sm text-green-700">✅ Detected ${valueColumns.length} Series:</p>
                        <ul class="text-sm text-green-700 ml-4 mt-1">
            `;
            
            valueColumns.forEach((header, index) => {
                configHTML += `<li>• Series ${index + 1}: <strong>${header}</strong></li>`;
            });
            
            configHTML += `
                        </ul>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-medium text-gray-700 mb-2">Series Configuration</h5>
                    <div class="space-y-2" id="group-bar-series-config-${graphIndex}">
            `;
            
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            valueColumns.forEach((header, index) => {
                const color = colors[index % colors.length];
                configHTML += `
                    <div class="inline-flex items-center gap-2 mr-4 mb-2 group-bar-series-item">
                        <span class="font-medium">Series ${index + 1}:</span>
                        <input type="text" value="${header}" class="p-2 border border-gray-300 rounded group-bar-series-label" style="width: 180px;" data-series-index="${index}">
                        <input type="color" value="${color}" class="w-8 h-8 border border-gray-300 rounded group-bar-series-color" data-series-index="${index}">
                    </div>
                `;
            });
            
            configHTML += `
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-medium mb-2"><i class="fas fa-calculator mr-2"></i>Value Input Type</h5>
                    <div class="flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="groupBarValueType${graphIndex}" value="values" ${!graphData.data.isPercentageData ? 'checked' : ''} class="mr-2" 
                                   onchange="handleGroupBarValueTypeChange(${graphIndex})">
                            <span>Whole Values</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="groupBarValueType${graphIndex}" value="percentages" ${graphData.data.isPercentageData ? 'checked' : ''} class="mr-2" 
                                   onchange="handleGroupBarValueTypeChange(${graphIndex})">
                            <span>Percentages (%)</span>
                        </label>
                    </div>
                    <div id="group-bar-percentage-warning-${graphIndex}" class="hidden mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                            <span class="text-sm text-yellow-700">
                                <strong>Note:</strong> When using percentages, values should typically be between 0% and 100%.
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-medium mb-3"><i class="fas fa-table mr-2"></i>Data Preview & Editing</h5>
                    
                    <!-- Create table like individual bar chart -->
                    <table class="w-full border border-gray-300 rounded-lg overflow-hidden" id="group-bar-data-table-${graphIndex}">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-3 text-left font-medium text-gray-700 border-r border-gray-300">Category</th>
            `;
            
            valueColumns.forEach((header, index) => {
                configHTML += `<th class="p-3 text-center font-medium text-gray-700 border-r border-gray-300 group-bar-series-header" data-series="${index}">${header}</th>`;
            });
            
            configHTML += `
                                <th class="p-3 text-center font-medium text-gray-700 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="group-bar-data-points-${graphIndex}">
            `;
            
            // Add data rows
            graphData.data.rows.forEach((row, rowIndex) => {
                const rowValues = Object.values(row);
                const category = rowValues[0] || '';
                const bgColor = rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                
                configHTML += `<tr class="${bgColor} hover:bg-blue-50 transition duration-200">`;
                configHTML += `<td class="p-3 border-r border-gray-200"><input type="text" placeholder="Category" value="${category}" class="w-full p-2 border border-gray-300 rounded group-bar-category"></td>`;
                
                // Create value inputs for each series column
                valueColumns.forEach((header, colIndex) => {
                    let value = rowValues[colIndex + 1] || ''; // +1 because first column is category
                    // Clean percentage values if they contain % symbol
                    if (typeof value === 'string' && value.includes('%')) {
                        value = parseFloat(value.replace('%', '')) || 0;
                    }
                    configHTML += `<td class="p-3 border-r border-gray-200 text-center"><input type="number" placeholder="Value" value="${value}" class="w-full p-2 border border-gray-300 rounded group-bar-value text-center" step="any" min="0"></td>`;
                });
                
                configHTML += `
                    <td class="p-3 text-center">
                        <button type="button" class="p-2 text-red-500 hover:bg-red-100 rounded transition duration-200" onclick="removeGroupBarDataRow(${graphIndex}, ${rowIndex})" title="Remove row">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                `;
                configHTML += '</tr>';
            });
            
            configHTML += `
                        </tbody>
                    </table>
                    
                    <button type="button" class="mt-3 px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded transition duration-200" 
                            onclick="addGroupBarDataPoint(${graphIndex})">
                        <i class="fas fa-plus mr-2"></i>Add Category
                    </button>
                </div>
            `;
            
            return configHTML;
        }
        
        function updateGroupBarSeriesLabel(graphIndex, seriesIndex) {
            // This function can be used to update series labels dynamically
            console.log(`Updated series ${seriesIndex} label for graph ${graphIndex}`);
        }
        
        function refreshGroupBarData(graphIndex) {
            // Since the table is now pre-built in generateGroupBarConfiguration, 
            // this function can be simplified or used for dynamic updates
            console.log(`Refreshing group bar data for graph ${graphIndex}`);
        }
        
        function handleGroupBarValueTypeChange(graphIndex) {
            const valueType = document.querySelector(`input[name="groupBarValueType${graphIndex}"]:checked`).value;
            const warningDiv = document.getElementById(`group-bar-percentage-warning-${graphIndex}`);
            
            if (valueType === 'percentages') {
                warningDiv.classList.remove('hidden');
            } else {
                warningDiv.classList.add('hidden');
            }
        }
        
        function addGroupBarDataPoint(graphIndex) {
            // Add a new empty row to the data
            const graphData = groupGraphsData[graphIndex];
            if (graphData) {
                const newRow = {};
                graphData.data.headers.forEach(header => {
                    newRow[header] = '';
                });
                graphData.data.rows.push(newRow);
                refreshGroupBarData(graphIndex);
            }
        }
        
        function removeGroupBarDataRow(graphIndex, rowIndex) {
            const graphData = groupGraphsData[graphIndex];
            if (graphData && graphData.data.rows[rowIndex]) {
                graphData.data.rows.splice(rowIndex, 1);
                refreshGroupBarData(graphIndex);
            }
        }
        
        function addGroupConfigurationEventListeners() {
            // Add event listeners for column mapping changes
            groupGraphsData.forEach((_, index) => {
                if (selectedGroupGraphType === 'pie') {
                    const labelSelect = document.getElementById(`group-pie-label-column-${index}`);
                    const valueSelect = document.getElementById(`group-pie-value-column-${index}`);
                    
                    if (labelSelect) {
                        labelSelect.addEventListener('change', () => refreshGroupPieData(index));
                    }
                    if (valueSelect) {
                        valueSelect.addEventListener('change', () => refreshGroupPieData(index));
                    }
                    
                    // Initial data population
                    refreshGroupPieData(index);
                    
                } else if (selectedGroupGraphType === 'bar') {
                    // Initial data population for bar charts
                    refreshGroupBarData(index);
                }
            });
        }
        
        function updateGroupPieSeriesSelection(graphIndex) {
            const selectedSeriesIndex = document.querySelector(`input[name="groupPieSeriesSelection${graphIndex}"]:checked`).value;
            const valueColumnSelect = document.getElementById(`group-pie-value-column-${graphIndex}`);
            if (valueColumnSelect) {
                valueColumnSelect.value = selectedSeriesIndex;
                // Refresh the data preview
                refreshGroupPieData(graphIndex);
            }
        }
        
        function refreshGroupPieData(graphIndex) {
            const graphData = groupGraphsData[graphIndex];
            const labelColumnIndex = parseInt(document.getElementById(`group-pie-label-column-${graphIndex}`).value);
            const valueColumnIndex = parseInt(document.getElementById(`group-pie-value-column-${graphIndex}`).value);
            const container = document.getElementById(`group-pie-data-points-${graphIndex}`);
            
            let dataHTML = '';
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            
            graphData.data.rows.forEach((row, index) => {
                const rowValues = Object.values(row);
                const label = rowValues[labelColumnIndex] || `Item ${index + 1}`;
                let value = rowValues[valueColumnIndex] || 0;
                
                // Clean percentage values if they contain % symbol
                if (typeof value === 'string' && value.includes('%')) {
                    value = parseFloat(value.replace('%', '')) || 0;
                } else {
                    value = parseFloat(value) || 0;
                }
                
                const color = colors[index % colors.length];
                
                dataHTML += `
                    <div class="flex items-center gap-2 p-2 bg-gray-50 rounded">
                        <input type="text" value="${label}" class="flex-1 p-2 border border-gray-300 rounded group-pie-label">
                        <input type="number" value="${value}" class="w-24 p-2 border border-gray-300 rounded group-pie-value" step="any" min="0">
                        <input type="color" value="${color}" class="w-10 h-10 border border-gray-300 rounded group-pie-color">
                        <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200" 
                                onclick="removeGroupDataPoint(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
            
            container.innerHTML = dataHTML;
        }
        
        function refreshGroupBarData(graphIndex) {
            const graphData = groupGraphsData[graphIndex];
            const categoryColumnIndex = parseInt(document.getElementById(`group-bar-category-column-${graphIndex}`).value);
            const valueCheckboxes = document.querySelectorAll(`#group-bar-value-columns-${graphIndex} .group-bar-value-checkbox:checked`);
            const valueColumnIndices = Array.from(valueCheckboxes).map(cb => parseInt(cb.value));
            
            // Update series configuration
            const seriesContainer = document.getElementById(`group-bar-series-config-${graphIndex}`);
            let seriesHTML = '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">';
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            
            valueColumnIndices.forEach((colIndex, seriesIndex) => {
                const header = graphData.data.headers[colIndex];
                const color = colors[seriesIndex % colors.length];
                
                seriesHTML += `
                    <div class="flex items-center gap-2">
                        <input type="text" value="${header}" class="flex-1 p-2 border border-gray-300 rounded group-bar-series-label">
                        <input type="color" value="${color}" class="w-10 h-10 border border-gray-300 rounded group-bar-series-color">
                    </div>
                `;
            });
            
            seriesHTML += '</div>';
            seriesContainer.innerHTML = seriesHTML;
            
            // Update data points
            const dataContainer = document.getElementById(`group-bar-data-points-${graphIndex}`);
            let dataHTML = '';
            
            graphData.data.rows.forEach((row, index) => {
                const rowValues = Object.values(row);
                const category = rowValues[categoryColumnIndex] || `Category ${index + 1}`;
                
                dataHTML += '<div class="flex items-center gap-2 p-2 bg-gray-50 rounded">';
                dataHTML += `<input type="text" value="${category}" class="flex-1 p-2 border border-gray-300 rounded group-bar-category">`;
                
                valueColumnIndices.forEach(colIndex => {
                    let value = rowValues[colIndex] || 0;
                    
                    // Clean percentage values if they contain % symbol
                    if (typeof value === 'string' && value.includes('%')) {
                        value = parseFloat(value.replace('%', '')) || 0;
                    } else {
                        value = parseFloat(value) || 0;
                    }
                    
                    dataHTML += `<input type="number" value="${value}" class="w-24 p-2 border border-gray-300 rounded group-bar-value" step="any" min="0">`;
                });
                
                dataHTML += `
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200" 
                            onclick="removeGroupDataPoint(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
            });
            
            dataContainer.innerHTML = dataHTML;
        }
        
        function addGroupPieSeriesBtn(graphIndex) {
            // Add a new series to group pie chart
            const container = document.querySelector(`#group-pie-series-config-${graphIndex} .space-y-2`);
            if (container) {
                const currentSeries = container.querySelectorAll('.group-pie-series-item').length;
                const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                const newColor = colors[currentSeries % colors.length];
                
                const newSeriesHTML = `
                    <div class="inline-flex items-center gap-2 mr-4 mb-2 group-pie-series-item">
                        <span class="font-medium">Series ${currentSeries + 1}:</span>
                        <input type="text" value="Series ${currentSeries + 1}" class="p-2 border border-gray-300 rounded group-pie-series-label" style="width: 180px;" data-series-index="${currentSeries}">
                        <input type="color" value="${newColor}" class="w-8 h-8 border border-gray-300 rounded group-pie-series-color" data-series-index="${currentSeries}">
                        <button type="button" class="p-1 text-red-500 hover:bg-red-100 rounded" onclick="removeGroupPieSeries(this)" title="Remove series">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                `;
                
                const addButton = container.parentElement.querySelector('button');
                addButton.insertAdjacentHTML('beforebegin', newSeriesHTML);
                
                // Add new column to table
                addGroupPieTableColumn(graphIndex, `Series ${currentSeries + 1}`);
            }
        }
        
        function addGroupPieTableColumn(graphIndex, headerName) {
            // Add new column to pie chart table
            const table = document.getElementById(`group-pie-data-table-${graphIndex}`);
            if (table) {
                // Add header
                const headerRow = table.querySelector('thead tr');
                const newHeader = document.createElement('th');
                newHeader.className = 'p-3 text-center font-medium text-gray-700 border-r border-gray-300 group-pie-series-header';
                newHeader.textContent = headerName;
                headerRow.insertBefore(newHeader, headerRow.lastElementChild);
                
                // Add cells to existing rows
                const bodyRows = table.querySelectorAll('tbody tr');
                bodyRows.forEach(row => {
                    const newCell = document.createElement('td');
                    newCell.className = 'p-3 border-r border-gray-200 text-center';
                    newCell.innerHTML = '<input type="number" placeholder="Value" value="0" class="w-full p-2 border border-gray-300 rounded group-pie-value text-center" step="any" min="0">';
                    row.insertBefore(newCell, row.lastElementChild);
                });
            }
        }
        
        function removeGroupPieSeries(button) {
            // Remove series and corresponding table column
            button.parentElement.remove();
        }
        
        function handleGroupPieValueTypeChange(graphIndex) {
            const valueType = document.querySelector(`input[name="groupPieValueType${graphIndex}"]:checked`).value;
            console.log(`Group pie value type changed to: ${valueType} for graph ${graphIndex}`);
        }
        
        function removeGroupPieDataRow(graphIndex, rowIndex) {
            const row = document.querySelector(`#group-pie-data-points-${graphIndex} tr:nth-child(${rowIndex + 1})`);
            if (row) {
                row.remove();
            }
        }
        
        function addGroupPieDataPoint(graphIndex) {
            const container = document.getElementById(`group-pie-data-points-${graphIndex}`);
            const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            
            const dataHTML = `
                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded">
                    <input type="text" placeholder="Label" class="flex-1 p-2 border border-gray-300 rounded group-pie-label">
                    <input type="number" placeholder="Value" class="w-24 p-2 border border-gray-300 rounded group-pie-value" step="any" min="0">
                    <input type="color" value="${randomColor}" class="w-10 h-10 border border-gray-300 rounded group-pie-color">
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200" 
                            onclick="removeGroupDataPoint(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', dataHTML);
        }
        
        function addGroupBarDataPoint(graphIndex) {
            const container = document.getElementById(`group-bar-data-points-${graphIndex}`);
            const valueCheckboxes = document.querySelectorAll(`#group-bar-value-columns-${graphIndex} .group-bar-value-checkbox:checked`);
            const seriesCount = valueCheckboxes.length;
            
            let valueInputs = '';
            for (let i = 0; i < seriesCount; i++) {
                valueInputs += '<input type="number" placeholder="Value" class="w-24 p-2 border border-gray-300 rounded group-bar-value" step="any" min="0">';
            }
            
            const dataHTML = `
                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded">
                    <input type="text" placeholder="Category" class="flex-1 p-2 border border-gray-300 rounded group-bar-category">
                    ${valueInputs}
                    <button type="button" class="p-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded transition duration-200" 
                            onclick="removeGroupDataPoint(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', dataHTML);
        }
        
        function removeGroupDataPoint(button) {
            const dataPoint = button.closest('.flex');
            if (dataPoint) {
                dataPoint.remove();
            }
        }
        
        function handleGroupBarValueTypeChange(graphIndex) {
            const valueType = document.querySelector(`input[name="groupBarValueType${graphIndex}"]:checked`).value;
            const warningDiv = document.getElementById(`group-bar-percentage-warning-${graphIndex}`);
            const valueInputs = document.querySelectorAll(`#group-bar-data-points-${graphIndex} .group-bar-value`);
            
            if (valueType === 'percentages') {
                warningDiv.classList.remove('hidden');
                valueInputs.forEach(input => {
                    input.setAttribute('max', '100');
                    if (input.placeholder && !input.placeholder.includes('(%)')) {
                        input.placeholder = input.placeholder + ' (%)';
                    }
                });
            } else {
                warningDiv.classList.add('hidden');
                valueInputs.forEach(input => {
                    input.removeAttribute('max');
                    if (input.placeholder && input.placeholder.includes(' (%)')) {
                        input.placeholder = input.placeholder.replace(' (%)', '');
                    }
                });
            }
        }
    </script>
</body>
</html>
