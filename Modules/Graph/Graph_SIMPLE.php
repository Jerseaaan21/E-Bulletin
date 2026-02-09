<?php
// Simple, working Graph module - minimal approach
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
$userDeptId = $_SESSION['user_info']['dept_id'] ?? 2; // Default to DIT

// Use the user's department ID
$dept_id = $userDeptId ?: 2;
$dept_acronym = 'default';

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

// Get graphs
$query = "SELECT dg.*, u.name as user_name 
        FROM main_graph dg 
        LEFT JOIN users u ON dg.user_id = u.id 
        WHERE dg.dept_id = ? AND dg.module = ? 
        ORDER BY dg.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $dept_id, $moduleId);
$stmt->execute();
$result = $stmt->get_result();

$graphs = ['active' => [], 'pending' => [], 'archived' => []];
while ($row = $result->fetch_assoc()) {
    $graphData = json_decode($row['data'], true) ?: [];
    $graphs[$row['status']][] = [
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
    <title>Graph Management - Simple Version</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Graph Management</h1>
                <div class="space-x-4">
                    <button onclick="showAddModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>Add Graph
                    </button>
                    <button onclick="showUploadModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-upload mr-2"></i>Upload Graph
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="showTab('active')" id="tab-active" class="tab-button active py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                        Active Graphs
                    </button>
                    <button onclick="showTab('pending')" id="tab-pending" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Pending Graphs
                    </button>
                    <button onclick="showTab('archived')" id="tab-archived" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Archived Graphs
                    </button>
                </nav>
            </div>

            <!-- Graph Lists -->
            <div id="content-active" class="tab-content">
                <h2 class="text-xl font-semibold mb-4">Active Graphs</h2>
                <?php if (empty($graphs['active'])): ?>
                    <p class="text-gray-500">No active graphs found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($graphs['active'] as $graph): ?>
                            <div class="bg-white border rounded-lg p-4 shadow">
                                <h3 class="font-semibold text-lg mb-2"><?= htmlspecialchars($graph['description']) ?></h3>
                                <p class="text-sm text-gray-600 mb-2">Type: <?= ucfirst($graph['graph_type']) ?></p>
                                <p class="text-sm text-gray-600 mb-4">By: <?= htmlspecialchars($graph['user_name']) ?></p>
                                <div class="flex space-x-2">
                                    <button onclick="viewGraph(<?= $graph['id'] ?>)" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                    <button onclick="editGraph(<?= $graph['id'] ?>)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <button onclick="archiveGraph(<?= $graph['id'] ?>)" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-archive mr-1"></i>Archive
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="content-pending" class="tab-content hidden">
                <h2 class="text-xl font-semibold mb-4">Pending Graphs</h2>
                <?php if (empty($graphs['pending'])): ?>
                    <p class="text-gray-500">No pending graphs found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($graphs['pending'] as $graph): ?>
                            <div class="bg-white border rounded-lg p-4 shadow">
                                <h3 class="font-semibold text-lg mb-2"><?= htmlspecialchars($graph['description']) ?></h3>
                                <p class="text-sm text-gray-600 mb-2">Type: <?= ucfirst($graph['graph_type']) ?></p>
                                <p class="text-sm text-gray-600 mb-4">By: <?= htmlspecialchars($graph['user_name']) ?></p>
                                <div class="flex space-x-2">
                                    <button onclick="viewGraph(<?= $graph['id'] ?>)" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                    <button onclick="editGraph(<?= $graph['id'] ?>)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <button onclick="deleteGraph(<?= $graph['id'] ?>)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="content-archived" class="tab-content hidden">
                <h2 class="text-xl font-semibold mb-4">Archived Graphs</h2>
                <?php if (empty($graphs['archived'])): ?>
                    <p class="text-gray-500">No archived graphs found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($graphs['archived'] as $graph): ?>
                            <div class="bg-white border rounded-lg p-4 shadow">
                                <h3 class="font-semibold text-lg mb-2"><?= htmlspecialchars($graph['description']) ?></h3>
                                <p class="text-sm text-gray-600 mb-2">Type: <?= ucfirst($graph['graph_type']) ?></p>
                                <p class="text-sm text-gray-600 mb-4">By: <?= htmlspecialchars($graph['user_name']) ?></p>
                                <div class="flex space-x-2">
                                    <button onclick="viewGraph(<?= $graph['id'] ?>)" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                    <button onclick="restoreGraph(<?= $graph['id'] ?>)" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-undo mr-1"></i>Restore
                                    </button>
                                    <button onclick="deleteGraph(<?= $graph['id'] ?>)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Simple Add Modal -->
    <div id="add-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Add New Graph</h3>
                    <button onclick="hideAddModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-gray-600">Add graph functionality will be implemented here.</p>
                <div class="mt-4 flex justify-end space-x-2">
                    <button onclick="hideAddModal()" class="px-4 py-2 text-gray-600 border rounded hover:bg-gray-50">Cancel</button>
                    <button class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Simple Upload Modal -->
    <div id="upload-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Upload Graph</h3>
                    <button onclick="hideUploadModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-gray-600">Upload graph functionality will be implemented here.</p>
                <div class="mt-4 flex justify-end space-x-2">
                    <button onclick="hideUploadModal()" class="px-4 py-2 text-gray-600 border rounded hover:bg-gray-50">Cancel</button>
                    <button class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple, reliable JavaScript without complex event systems
        
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
        }
        
        // Modal functions
        function showAddModal() {
            document.getElementById('add-modal').classList.remove('hidden');
        }
        
        function hideAddModal() {
            document.getElementById('add-modal').classList.add('hidden');
        }
        
        function showUploadModal() {
            document.getElementById('upload-modal').classList.remove('hidden');
        }
        
        function hideUploadModal() {
            document.getElementById('upload-modal').classList.add('hidden');
        }
        
        // Graph actions
        function viewGraph(id) {
            alert('View graph ' + id + ' - functionality to be implemented');
        }
        
        function editGraph(id) {
            alert('Edit graph ' + id + ' - functionality to be implemented');
        }
        
        function archiveGraph(id) {
            if (confirm('Are you sure you want to archive this graph?')) {
                // Implementation here
                alert('Archive graph ' + id + ' - functionality to be implemented');
            }
        }
        
        function restoreGraph(id) {
            if (confirm('Are you sure you want to restore this graph?')) {
                // Implementation here
                alert('Restore graph ' + id + ' - functionality to be implemented');
            }
        }
        
        function deleteGraph(id) {
            if (confirm('Are you sure you want to delete this graph? This action cannot be undone.')) {
                // Implementation here
                alert('Delete graph ' + id + ' - functionality to be implemented');
            }
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'add-modal') {
                hideAddModal();
            }
            if (e.target.id === 'upload-modal') {
                hideUploadModal();
            }
        });
        
        console.log('Simple Graph module loaded successfully');
    </script>
</body>
</html>