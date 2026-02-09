<?php
// Manage_Modules/Graphs/Graphs.php
// Include DB first
include "../../db.php";
session_start();

// Handle AJAX requests for approval/rejection IMMEDIATELY
// This prevents PHP warnings from HTML logic below from corrupting JSON response.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $graphId = $_POST['id'] ?? null;

    if (!$graphId) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    try {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE graph SET status = 'Approved' WHERE id = ?");
            $stmt->bind_param("i", $graphId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Graph approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve graph']);
            }
        } elseif ($action === 'reject') {
            $rejectionReason = $_POST['reason'] ?? 'No reason provided';

            // Get current data to preserve it and add rejection reason
            $getDataQuery = "SELECT data FROM graph WHERE id = ?";
            $getStmt = $conn->prepare($getDataQuery);
            $getStmt->bind_param("i", $graphId);
            $getStmt->execute();
            $result = $getStmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $currentData = json_decode($row['data'], true) ?: [];
                
                // Add rejection reason to the data
                $currentData['rejection_reason'] = $rejectionReason;
                $updatedData = json_encode($currentData);
                
                $stmt = $conn->prepare("UPDATE graph SET status = 'Not Approved', data = ? WHERE id = ?");
                $stmt->bind_param("si", $updatedData, $graphId);
            } else {
                // Fallback if graph not found
                $stmt = $conn->prepare("UPDATE graph SET status = 'Not Approved' WHERE id = ?");
                $stmt->bind_param("i", $graphId);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Graph rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject graph']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit; // STOP EXECUTION HERE. Do not load HTML for AJAX requests.
}

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

// Get module ID for Graph
$moduleQuery = "SELECT id FROM modules WHERE name = 'Graph' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Get all departments for organization
$deptQuery = "SELECT dept_id, dept_name, acronym FROM departments ORDER BY dept_name";
$deptResult = $conn->query($deptQuery);
$departments = [];
while ($row = $deptResult->fetch_assoc()) {
    $departments[$row['dept_id']] = $row;
}

// Get pending graphs grouped by department
$pendingByDept = [];
foreach ($departments as $deptId => $dept) {
    $query = "SELECT g.*, u.name as user_name 
              FROM graph g 
              LEFT JOIN users u ON g.user_id = u.id 
              WHERE g.module = ? AND g.dept_id = ? AND g.status = 'Pending' 
              ORDER BY g.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $moduleId, $deptId);
    $stmt->execute();
    $result = $stmt->get_result();

    $graphs = [];
    while ($row = $result->fetch_assoc()) {
        $graphData = json_decode($row['data'], true) ?: [];
        
        // Extract rejection reason if it exists
        $rejectionReason = '';
        if (isset($graphData['rejection_reason'])) {
            $rejectionReason = $graphData['rejection_reason'];
            // Remove rejection reason from graph data to keep it clean for rendering
            unset($graphData['rejection_reason']);
        }

        $graphs[] = [
            'id' => $row['id'],
            'description' => $row['description'],
            'graph_type' => $row['graph_type'],
            'data' => $graphData,
            'rejection_reason' => $rejectionReason,
            'posted_on' => $row['created_at'],
            'user_name' => $row['user_name'] ?? 'Unknown'
        ];
    }

    if (!empty($graphs)) {
        $pendingByDept[$deptId] = [
            'dept_info' => $dept,
            'graphs' => $graphs
        ];
    }
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
        /* CEIT Graph Management Specific Styles */
        .ceit-graph-preview {
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

        .ceit-graph-modal {
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

        .ceit-graph-modal-content {
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

        .ceit-graph-modal-header {
            padding: 15px 20px;
            background-color: #3b82f6;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ceit-graph-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .ceit-graph-modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .ceit-graph-modal-close:hover {
            transform: scale(1.2);
        }

        .ceit-graph-modal-body {
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

        .ceit-graph-modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ceit-graph-modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* Department sections */
        .ceit-graph-dept-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
            background-color: #dbeafe;
            border: 1px solid #93c5fd;
        }

        .ceit-graph-dept-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }

        /* Notification styles */
        .ceit-graph-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            animation: ceit-graph-slideIn 0.3s ease-out;
        }

        .ceit-graph-notification.success {
            background-color: #3b82f6;
        }

        .ceit-graph-notification.error {
            background-color: #ef4444;
        }

        .ceit-graph-notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes ceit-graph-slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Group graph icon styles */
        .group-graph-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            color: #6b7280;
            font-size: 3rem;
        }

        .group-graph-icon i {
            margin-bottom: 10px;
        }

        .group-graph-icon span {
            font-size: 0.8rem;
            text-align: center;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .ceit-graph-modal-content {
                width: 95%;
                margin: 5% auto;
                min-height: 500px;
            }

            .ceit-graph-modal-header {
                padding: 10px 15px;
            }

            .ceit-graph-modal-title {
                font-size: 1.2rem;
            }

            .ceit-graph-modal-body {
                padding: 15px;
                min-height: 400px;
            }

            .ceit-graph-modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .ceit-graph-dept-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .ceit-graph-dept-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-blue-600 mb-4 md:mb-0">
                <i class="fas fa-chart-pie mr-3 w-5"></i> CEIT Graph Management
            </h1>
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>
                Approve or reject pending graphs from all departments
            </div>
        </div>

        <?php if (empty($pendingByDept)): ?>
            <div class="text-center py-16 bg-white rounded-lg shadow-md">
                <i class="fas fa-check-circle fa-4x text-green-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">All Caught Up!</h2>
                <p class="text-gray-600">No pending graphs require your attention at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingByDept as $deptId => $deptData): ?>
                <div class="ceit-graph-dept-section">
                    <h2 class="ceit-graph-dept-title">
                        <i class="fas fa-building mr-2"></i>
                        <?= htmlspecialchars($deptData['dept_info']['dept_name']) ?>
                        (<?= htmlspecialchars($deptData['dept_info']['acronym']) ?>)
                        <span class="text-sm font-normal ml-2">
                            - <?= count($deptData['graphs']) ?> pending graph(s)
                        </span>
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach ($deptData['graphs'] as $index => $graph): ?>
                            <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105">
                                <div class="mb-3 border border-gray-300 rounded">
                                    <div class="ceit-graph-preview">
                                        <canvas id="ceit-graph-preview-<?= $deptId ?>-<?= $index ?>"></canvas>
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
                                    <?php if (!empty($graph['rejection_reason'])): ?>
                                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            <strong>Reason:</strong> <?= htmlspecialchars($graph['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-between mt-4 space-x-2 text-xs">
                                    <button id="ceit-graph-view-full-<?= $deptId ?>-<?= $index ?>"
                                        class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110"
                                        title="View Full Graph"
                                        data-dept-id="<?= $deptId ?>"
                                        data-index="<?= $index ?>">
                                        <i class="fas fa-eye fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 ceit-graph-approve-btn"
                                        data-id="<?= $graph['id'] ?>"
                                        data-description="<?= htmlspecialchars($graph['description']) ?>"
                                        title="Approve">
                                        <i class="fas fa-check fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 ceit-graph-reject-btn"
                                        data-id="<?= $graph['id'] ?>"
                                        data-description="<?= htmlspecialchars($graph['description']) ?>"
                                        title="Reject">
                                        <i class="fas fa-times fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Graph View Modals -->
    <?php foreach ($pendingByDept as $deptId => $deptData): ?>
        <?php foreach ($deptData['graphs'] as $index => $graph): ?>
            <div id="ceit-graph-modal-<?= $deptId ?>-<?= $index ?>" class="ceit-graph-modal">
                <div class="ceit-graph-modal-content">
                    <div class="ceit-graph-modal-header">
                        <h3 class="ceit-graph-modal-title"><?= htmlspecialchars($graph['description']) ?></h3>
                        <span class="ceit-graph-modal-close" onclick="window.closeCeitGraphModal('<?= $deptId ?>', <?= $index ?>)">&times;</span>
                    </div>
                    <div class="ceit-graph-modal-body">
                        <div id="ceit-graph-container-<?= $deptId ?>-<?= $index ?>" style="width: 100%; height: 100%; position: relative;">
                            <canvas id="ceit-graph-full-<?= $deptId ?>-<?= $index ?>"></canvas>
                        </div>
                    </div>
                    <div class="ceit-graph-modal-footer">
                        <div class="ceit-graph-modal-meta">
                            Posted by: <?= htmlspecialchars($graph['user_name']) ?> on <?= date('F j, Y', strtotime($graph['posted_on'])) ?> | Type: <?= ucfirst($graph['graph_type']) ?> Graph
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <!-- Approval Confirmation Modal -->
    <div id="ceit-graph-approve-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-green-600">Approve Graph</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to approve this graph?</p>
                <p class="font-semibold mt-2" id="ceit-graph-approve-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="ceit-graph-cancel-approve-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="ceit-graph-confirm-approve-btn">
                    <i class="fas fa-check mr-2"></i> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="ceit-graph-reject-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Reject Graph</h3>
            </div>
            <div class="mb-4">
                <p class="mb-3">Please provide a reason for rejecting this graph:</p>
                <p class="font-semibold mb-3" id="ceit-graph-reject-title"></p>
                <textarea id="ceit-graph-reject-reason"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                    rows="4"
                    placeholder="Enter rejection reason..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="ceit-graph-cancel-reject-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="ceit-graph-confirm-reject-btn">
                    <i class="fas fa-times mr-2"></i> Reject
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables for graph handling
        window.ceitGraphCharts = {};
        window.ceitGraphData = <?php echo json_encode($pendingByDept); ?>;
        let currentCeitGraphId = null;

        // Helper function to create charts with optional DataLabels plugin
        function createCeitGraphWithDataLabels(ctx, config) {
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
        function formatCeitGraphValue(value, isPercentage = false) {
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

        // Initialize module - RENAMED to match Main_dashboard expectation
        function initializeGraphsModule() {
            console.log('Initializing CEIT Graphs module for Manage_Modules...');

            initializeCeitGraphViewButtons();
            initializeCeitGraphApprovalRejectionButtons();
            initializeCeitGraphPreviews();

            console.log('CEIT Graphs module initialized for Manage_Modules');
        }

        // Initialize view buttons
        function initializeCeitGraphViewButtons() {
            console.log('Initializing view buttons for Manage Graphs...');
            document.querySelectorAll('[id^="ceit-graph-view-full-"]').forEach(button => {
                console.log('Found view button:', button.id);
                button.addEventListener('click', handleCeitGraphViewButtonClick);
            });
        }

        // Handle view button click
        function handleCeitGraphViewButtonClick(event) {
            console.log('Graph view button clicked:', this.id);
            const button = this;
            const deptId = button.dataset.deptId;
            const index = button.dataset.index;

            console.log('DeptId:', deptId, 'Index:', index);

            const modalId = `ceit-graph-modal-${deptId}-${index}`;
            const modal = document.getElementById(modalId);

            if (!modal) {
                console.error('Modal not found:', modalId);
                return;
            }

            console.log('Showing graph modal...');
            modal.style.display = "block";
            displayCeitGraphFullView(deptId, index);
        }

        // Display full graph view
        function displayCeitGraphFullView(deptId, index) {
            console.log('Displaying full graph view:', deptId, index);
            
            const graph = window.ceitGraphData[deptId].graphs[index];
            const canvasId = `ceit-graph-full-${deptId}-${index}`;
            const canvas = document.getElementById(canvasId);

            if (!canvas) {
                console.error('Canvas not found:', canvasId);
                return;
            }

            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            const chartKey = `full-${deptId}-${index}`;
            if (window.ceitGraphCharts[chartKey]) {
                window.ceitGraphCharts[chartKey].destroy();
            }

            // Create new chart based on type
            if (graph.graph_type === 'pie') {
                createCeitPieChart(ctx, graph, chartKey);
            } else if (graph.graph_type === 'bar') {
                createCeitBarChart(ctx, graph, chartKey);
            } else if (graph.graph_type === 'group') {
                createCeitGroupChart(deptId, index, graph);
            }
        }

        // Create pie chart
        function createCeitPieChart(ctx, graph, chartKey) {
            const isPercentage = shouldTreatAsPercentage(graph.data);
            window.ceitGraphCharts[chartKey] = createCeitGraphWithDataLabels(ctx, {
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
                                padding: 20,
                                font: {
                                    size: 14
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
                                                displayText = `${label}: ${value} (${slicePercentage}%)`;
                                            } else {
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
                                size: 14
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
                }
            });
        }

        // Create bar chart
        function createCeitBarChart(ctx, graph, chartKey) {
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
            window.ceitGraphCharts[chartKey] = createCeitGraphWithDataLabels(ctx, {
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
                            display: datasets.length > 1
                        },
                        datalabels: {
                            display: true,
                            color: 'white',
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            formatter: (value) => {
                                return formatCeitGraphValue(value, isPercentage);
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
                                    return formatCeitGraphValue(value, isPercentage);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Create group chart
        function createCeitGroupChart(deptId, index, graph) {
            console.log('Creating group chart:', deptId, index, graph);
            
            const container = document.getElementById(`ceit-graph-container-${deptId}-${index}`);
            const canvas = document.getElementById(`ceit-graph-full-${deptId}-${index}`);
            
            // Hide canvas and show group charts
            canvas.style.display = 'none';
            
            // Clear container
            container.innerHTML = '';
            
            // Validate group data structure
            if (!graph.data || !graph.data.graphs || !Array.isArray(graph.data.graphs)) {
                container.innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-lg text-gray-700 mb-2">Invalid Group Data</p>
                        <p class="text-gray-600">Group chart data is malformed or missing.</p>
                    </div>
                `;
                return;
            }
            
            // Create group chart layout
            const groupContainer = document.createElement('div');
            groupContainer.style.cssText = `
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                width: 100%;
                height: 100%;
                padding: 20px;
                overflow-y: auto;
            `;
            
            graph.data.graphs.forEach((childGraph, childIndex) => {
                // Validate child graph data
                if (!childGraph || !childGraph.data) {
                    console.warn(`Child graph ${childIndex} has invalid data:`, childGraph);
                    return;
                }
                
                const chartContainer = document.createElement('div');
                chartContainer.style.cssText = `
                    background: white;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 15px;
                    min-height: 300px;
                    display: flex;
                    flex-direction: column;
                `;
                
                const title = document.createElement('h4');
                title.textContent = childGraph.title || `Graph ${childIndex + 1}`;
                title.style.cssText = `
                    margin: 0 0 15px 0;
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #374151;
                    text-align: center;
                `;
                
                const canvasContainer = document.createElement('div');
                canvasContainer.style.cssText = `
                    flex: 1;
                    position: relative;
                    min-height: 250px;
                `;
                
                const childCanvas = document.createElement('canvas');
                childCanvas.id = `ceit-group-chart-${deptId}-${index}-${childIndex}`;
                
                chartContainer.appendChild(title);
                canvasContainer.appendChild(childCanvas);
                chartContainer.appendChild(canvasContainer);
                groupContainer.appendChild(chartContainer);
                
                // Create the child chart
                const childCtx = childCanvas.getContext('2d');
                const childChartKey = `group-${deptId}-${index}-${childIndex}`;
                
                try {
                    if (childGraph.type === 'pie') {
                        const isPercentage = shouldTreatAsPercentage(childGraph.data);
                        window.ceitGraphCharts[childChartKey] = createCeitGraphWithDataLabels(childCtx, {
                            type: 'pie',
                            data: {
                                labels: childGraph.data.labels || [],
                                datasets: [{
                                    data: childGraph.data.values || [],
                                    backgroundColor: childGraph.data.colors || ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6']
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'bottom',
                                        labels: {
                                            padding: 15,
                                            font: {
                                                size: 12
                                            },
                                            usePointStyle: true,
                                            pointStyle: 'circle'
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
                                        }
                                    }
                                }
                            }
                        });
                    } else if (childGraph.type === 'bar') {
                        // Handle both old and new data structures like the department module
                        let labels, datasets;
                        
                        if (childGraph.data.categories) {
                            // New data structure
                            labels = childGraph.data.categories;
                            datasets = (childGraph.data.seriesLabels || []).map((label, idx) => ({
                                label: label,
                                data: (childGraph.data.values || []).map(v => v[idx] || 0),
                                backgroundColor: childGraph.data.seriesColors ? childGraph.data.seriesColors[idx] : '#3b82f6'
                            }));
                        } else {
                            // Old data structure (for backward compatibility)
                            labels = childGraph.data.labels || [];
                            datasets = [{
                                label: childGraph.title || 'Data',
                                data: childGraph.data.values || [],
                                backgroundColor: childGraph.data.colors || ['#3b82f6']
                            }];
                        }
                        
                        const isPercentage = shouldTreatAsPercentage(childGraph.data);
                        window.ceitGraphCharts[childChartKey] = createCeitGraphWithDataLabels(childCtx, {
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
                                        display: datasets.length > 1
                                    },
                                    datalabels: {
                                        display: true,
                                        color: 'white',
                                        font: {
                                            weight: 'bold',
                                            size: 10
                                        },
                                        formatter: (value) => {
                                            return formatCeitGraphValue(value, isPercentage);
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return formatCeitGraphValue(value, isPercentage);
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        // Unsupported chart type
                        canvasContainer.innerHTML = `
                            <div class="text-center p-4">
                                <i class="fas fa-question-circle text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-600">Unsupported chart type: ${childGraph.type}</p>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error(`Error creating child chart ${childIndex}:`, error);
                    canvasContainer.innerHTML = `
                        <div class="text-center p-4">
                            <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-2"></i>
                            <p class="text-red-600">Error rendering chart</p>
                            <p class="text-gray-600 text-sm">${error.message}</p>
                        </div>
                    `;
                }
            });
            
            container.appendChild(groupContainer);
        }

        // Initialize graph previews
        function initializeCeitGraphPreviews() {
            console.log('Initializing graph previews...');
            
            Object.keys(window.ceitGraphData).forEach(deptId => {
                const deptData = window.ceitGraphData[deptId];
                deptData.graphs.forEach((graph, index) => {
                    createCeitPreviewGraph(deptId, index, graph);
                });
            });

            console.log('Graph previews initialized');
        }

        // Create preview graph
        function createCeitPreviewGraph(deptId, index, graph) {
            const canvasId = `ceit-graph-preview-${deptId}-${index}`;
            const canvas = document.getElementById(canvasId);

            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            const chartKey = `preview-${deptId}-${index}`;
            if (window.ceitGraphCharts[chartKey]) {
                window.ceitGraphCharts[chartKey].destroy();
            }

            // Create new chart based on type
            if (graph.graph_type === 'pie') {
                const isPercentage = shouldTreatAsPercentage(graph.data);
                window.ceitGraphCharts[chartKey] = createCeitGraphWithDataLabels(ctx, {
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
                                    pointStyle: 'circle'
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
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${percentage}%`;
                                }
                            }
                        }
                    }
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
                window.ceitGraphCharts[chartKey] = createCeitGraphWithDataLabels(ctx, {
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
                                    return formatCeitGraphValue(value, isPercentage);
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCeitGraphValue(value, isPercentage);
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
                    }
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
                    
                    // Validate group data and show appropriate information
                    let chartCount = 0;
                    let chartInfo = 'No data';
                    
                    if (graph.data && graph.data.graphs && Array.isArray(graph.data.graphs)) {
                        chartCount = graph.data.graphs.length;
                        chartInfo = `${chartCount} chart${chartCount !== 1 ? 's' : ''}`;
                    } else {
                        chartInfo = 'Invalid data';
                    }
                    
                    iconContainer.innerHTML = `
                        <i class="fas fa-layer-group"></i>
                        <span>Group Chart<br>${chartInfo}</span>
                    `;
                    container.appendChild(iconContainer);
                }
            }
        }

        // Initialize approval/rejection buttons
        function initializeCeitGraphApprovalRejectionButtons() {
            // Approve buttons
            document.querySelectorAll('.ceit-graph-approve-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    currentCeitGraphId = id;
                    document.getElementById('ceit-graph-approve-title').textContent = description;
                    document.getElementById('ceit-graph-approve-modal').classList.remove('hidden');
                });
            });

            // Reject buttons
            document.querySelectorAll('.ceit-graph-reject-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    currentCeitGraphId = id;
                    document.getElementById('ceit-graph-reject-title').textContent = description;
                    document.getElementById('ceit-graph-reject-reason').value = '';
                    document.getElementById('ceit-graph-reject-modal').classList.remove('hidden');
                });
            });

            // Modal event listeners
            document.getElementById('ceit-graph-cancel-approve-btn').addEventListener('click', function() {
                document.getElementById('ceit-graph-approve-modal').classList.add('hidden');
                currentCeitGraphId = null;
            });

            document.getElementById('ceit-graph-cancel-reject-btn').addEventListener('click', function() {
                document.getElementById('ceit-graph-reject-modal').classList.add('hidden');
                currentCeitGraphId = null;
            });

            // Confirm approve
            document.getElementById('ceit-graph-confirm-approve-btn').addEventListener('click', function() {
                if (!currentCeitGraphId) return;

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Approving...';

                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('id', currentCeitGraphId);

                // Use absolute path to directly target Graphs.php
                const graphFileUrl = '/Testing/Manage_Modules/Graphs/Graphs.php';

                fetch(graphFileUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showCeitGraphNotification(data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showCeitGraphNotification(data.message, 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-check mr-2"></i> Approve';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showCeitGraphNotification('An error occurred while approving graph', 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-check mr-2"></i> Approve';
                    });
            });

            // Confirm reject
            document.getElementById('ceit-graph-confirm-reject-btn').addEventListener('click', function() {
                if (!currentCeitGraphId) return;

                const reason = document.getElementById('ceit-graph-reject-reason').value.trim();
                if (!reason) {
                    showCeitGraphNotification('Please provide a reason for rejection', 'error');
                    return;
                }

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Rejecting...';

                const formData = new FormData();
                formData.append('action', 'reject');
                formData.append('id', currentCeitGraphId);
                formData.append('reason', reason);

                // Use absolute path to directly target Graphs.php
                const graphFileUrl = '/Testing/Manage_Modules/Graphs/Graphs.php';

                fetch(graphFileUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showCeitGraphNotification(data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showCeitGraphNotification(data.message, 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-times mr-2"></i> Reject';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showCeitGraphNotification('An error occurred while rejecting graph', 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-times mr-2"></i> Reject';
                    });
            });

            // Close modals when clicking outside
            document.getElementById('ceit-graph-approve-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    currentCeitGraphId = null;
                }
            });

            document.getElementById('ceit-graph-reject-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    currentCeitGraphId = null;
                }
            });
        }

        // Show notification
        function showCeitGraphNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `ceit-graph-notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Close graph modal
        function closeCeitGraphModal(deptId, index) {
            const modal = document.getElementById(`ceit-graph-modal-${deptId}-${index}`);
            if (modal) {
                modal.style.display = "none";
                
                // Clean up any group charts
                const chartKeys = Object.keys(window.ceitGraphCharts).filter(key => 
                    key.startsWith(`group-${deptId}-${index}-`) || key === `full-${deptId}-${index}`
                );
                chartKeys.forEach(key => {
                    if (window.ceitGraphCharts[key]) {
                        window.ceitGraphCharts[key].destroy();
                        delete window.ceitGraphCharts[key];
                    }
                });
            }
        }

        // Initialize when DOM is ready
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initializeGraphsModule, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeGraphsModule, 100);
            });
        }

        // Make functions globally available
        window.closeCeitGraphModal = closeCeitGraphModal;
    </script>
</body>

</html>