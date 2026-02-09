<?php
// Manage_Modules/Departments/Departments.php
include "../../db.php";
session_start();

// Handle AJAX requests for department management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        if ($action === 'create_department') {
            $deptName = $_POST['dept_name'] ?? '';
            $acronym = $_POST['acronym'] ?? '';
            $officerId = $_POST['officer_id'] ?? null;

            // Convert empty string to null for officer_id
            if (empty($officerId)) {
                $officerId = null;
            }

            // Validate required fields
            if (empty($deptName) || empty($acronym)) {
                echo json_encode(['success' => false, 'message' => 'Department name and acronym are required']);
                exit;
            }

            // Check if acronym already exists
            $checkStmt = $conn->prepare("SELECT dept_id FROM departments WHERE acronym = ?");
            $checkStmt->bind_param("s", $acronym);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Acronym already exists']);
                exit;
            }

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Insert new department
                $stmt = $conn->prepare("INSERT INTO departments (dept_name, acronym) VALUES (?, ?)");
                $stmt->bind_param("ss", $deptName, $acronym);
                $stmt->execute();
                $deptId = $conn->insert_id;

                // Auto-assign ALL modules from modules table
                $modulesQuery = "SELECT id FROM modules";
                $modulesResult = $conn->query($modulesQuery);
                
                if ($modulesResult->num_rows > 0) {
                    $insertModuleStmt = $conn->prepare("INSERT INTO department_modules (department_id, module_id) VALUES (?, ?)");
                    while ($module = $modulesResult->fetch_assoc()) {
                        $insertModuleStmt->bind_param("ii", $deptId, $module['id']);
                        $insertModuleStmt->execute();
                    }
                }

                // Assign officer to department if selected
                if ($officerId) {
                    $updateOfficerStmt = $conn->prepare("UPDATE users SET dept_id = ? WHERE id = ?");
                    $updateOfficerStmt->bind_param("ii", $deptId, $officerId);
                    $updateOfficerStmt->execute();
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Department created successfully']);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }

        } elseif ($action === 'update_department') {
            $deptId = $_POST['dept_id'] ?? '';
            $deptName = $_POST['dept_name'] ?? '';
            $acronym = $_POST['acronym'] ?? '';
            $officerId = $_POST['officer_id'] ?? null;

            // Convert empty string to null for officer_id
            if (empty($officerId)) {
                $officerId = null;
            }

            if (empty($deptId) || empty($deptName) || empty($acronym)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }

            // Check if acronym already exists for other departments
            $checkStmt = $conn->prepare("SELECT dept_id FROM departments WHERE acronym = ? AND dept_id != ?");
            $checkStmt->bind_param("si", $acronym, $deptId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Acronym already exists']);
                exit;
            }

            // Check if this is CEIT department
            $deptCheckStmt = $conn->prepare("SELECT acronym FROM departments WHERE dept_id = ?");
            $deptCheckStmt->bind_param("i", $deptId);
            $deptCheckStmt->execute();
            $currentDept = $deptCheckStmt->get_result()->fetch_assoc();
            $isCEIT = $currentDept && strtoupper($currentDept['acronym']) === 'CEIT';

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Update department
                $stmt = $conn->prepare("UPDATE departments SET dept_name = ?, acronym = ? WHERE dept_id = ?");
                $stmt->bind_param("ssi", $deptName, $acronym, $deptId);
                $stmt->execute();

                // Update officer assignment - first clear existing assignment
                $clearStmt = $conn->prepare("UPDATE users SET dept_id = NULL WHERE dept_id = ?");
                $clearStmt->bind_param("i", $deptId);
                $clearStmt->execute();

                // Assign new officer if selected
                if ($officerId) {
                    $updateOfficerStmt = $conn->prepare("UPDATE users SET dept_id = ? WHERE id = ?");
                    $updateOfficerStmt->bind_param("ii", $deptId, $officerId);
                    $updateOfficerStmt->execute();
                }

                // Handle modules - CEIT department doesn't need department_modules entries
                $addedModules = 0;
                if (!$isCEIT) {
                    // For non-CEIT departments, check for missing modules and add them
                    $allModulesQuery = "SELECT id FROM modules";
                    $allModulesResult = $conn->query($allModulesQuery);
                    
                    $assignedModulesQuery = "SELECT module_id FROM department_modules WHERE department_id = ?";
                    $assignedStmt = $conn->prepare($assignedModulesQuery);
                    $assignedStmt->bind_param("i", $deptId);
                    $assignedStmt->execute();
                    $assignedResult = $assignedStmt->get_result();
                    
                    $assignedModules = [];
                    while ($row = $assignedResult->fetch_assoc()) {
                        $assignedModules[] = $row['module_id'];
                    }
                    
                    // Add missing modules
                    $insertModuleStmt = $conn->prepare("INSERT IGNORE INTO department_modules (department_id, module_id) VALUES (?, ?)");
                    
                    while ($module = $allModulesResult->fetch_assoc()) {
                        if (!in_array($module['id'], $assignedModules)) {
                            $insertModuleStmt->bind_param("ii", $deptId, $module['id']);
                            $insertModuleStmt->execute();
                            $addedModules++;
                        }
                    }
                }

                $conn->commit();
                
                $message = 'Department updated successfully';
                if (!$isCEIT && $addedModules > 0) {
                    $message .= " and {$addedModules} missing module(s) were added";
                }
                
                echo json_encode(['success' => true, 'message' => $message]);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }

        } elseif ($action === 'delete_department') {
            $deptId = $_POST['dept_id'] ?? '';

            if (empty($deptId)) {
                echo json_encode(['success' => false, 'message' => 'Department ID is required']);
                exit;
            }

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Clear user assignments
                $clearUsersStmt = $conn->prepare("UPDATE users SET dept_id = NULL WHERE dept_id = ?");
                $clearUsersStmt->bind_param("i", $deptId);
                $clearUsersStmt->execute();

                // Delete department modules
                $deleteModulesStmt = $conn->prepare("DELETE FROM department_modules WHERE department_id = ?");
                $deleteModulesStmt->bind_param("i", $deptId);
                $deleteModulesStmt->execute();

                // Delete department
                $stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
                $stmt->bind_param("i", $deptId);
                $stmt->execute();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Department deleted successfully']);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }

        } elseif ($action === 'get_department_details') {
            $deptId = $_POST['dept_id'] ?? '';

            if (empty($deptId)) {
                echo json_encode(['success' => false, 'message' => 'Department ID is required']);
                exit;
            }

            // Get department details with current officer
            $deptQuery = "
                SELECT 
                    d.dept_id, 
                    d.dept_name, 
                    d.acronym,
                    u.id as officer_id,
                    u.name as officer_name,
                    u.email as officer_email,
                    u.role as officer_role
                FROM departments d 
                LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role IN ('MIS', 'LEAD_MIS')
                WHERE d.dept_id = ?
            ";
            $stmt = $conn->prepare($deptQuery);
            $stmt->bind_param("i", $deptId);
            $stmt->execute();
            $deptResult = $stmt->get_result();
            $department = $deptResult->fetch_assoc();

            if (!$department) {
                echo json_encode(['success' => false, 'message' => 'Department not found']);
                exit;
            }

            // Get all available modules
            $allModulesQuery = "SELECT id, name, description FROM modules ORDER BY name";
            $allModulesResult = $conn->query($allModulesQuery);
            $allModules = [];
            while ($row = $allModulesResult->fetch_assoc()) {
                $allModules[] = $row;
            }

            // Get assigned modules for this department
            $assignedModulesQuery = "SELECT module_id FROM department_modules WHERE department_id = ?";
            $assignedStmt = $conn->prepare($assignedModulesQuery);
            $assignedStmt->bind_param("i", $deptId);
            $assignedStmt->execute();
            $assignedResult = $assignedStmt->get_result();
            
            $assignedModuleIds = [];
            while ($row = $assignedResult->fetch_assoc()) {
                $assignedModuleIds[] = $row['module_id'];
            }

            // Find missing modules
            $missingModules = [];
            foreach ($allModules as $module) {
                if (!in_array($module['id'], $assignedModuleIds)) {
                    $missingModules[] = $module;
                }
            }

            echo json_encode([
                'success' => true,
                'department' => $department,
                'missing_modules' => $missingModules,
                'total_modules' => count($allModules),
                'assigned_modules' => count($assignedModuleIds)
            ]);

        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Get all available officers (users with MIS or LEAD_MIS role and no department assigned)
$officersQuery = "SELECT id, name, email, role FROM users WHERE role IN ('MIS', 'LEAD_MIS') AND dept_id IS NULL ORDER BY name";
$officersResult = $conn->query($officersQuery);
$availableOfficers = [];
while ($row = $officersResult->fetch_assoc()) {
    $availableOfficers[] = $row;
}

// Get all modules for display in create modal
$modulesQuery = "SELECT id, name, description FROM modules ORDER BY name";
$modulesResult = $conn->query($modulesQuery);
$allModules = [];
while ($row = $modulesResult->fetch_assoc()) {
    $allModules[] = $row;
}

// Get departments with their assigned officers and module counts
$deptQuery = "
    SELECT 
        d.dept_id, 
        d.dept_name, 
        d.acronym,
        u.id as officer_id,
        u.name as officer_name,
        u.email as officer_email,
        u.role as officer_role,
        COUNT(dm.module_id) as module_count
    FROM departments d 
    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role IN ('MIS', 'LEAD_MIS')
    LEFT JOIN department_modules dm ON d.dept_id = dm.department_id
    GROUP BY d.dept_id, d.dept_name, d.acronym, u.id, u.name, u.email, u.role
    ORDER BY 
        CASE WHEN d.acronym = 'CEIT' THEN 1 ELSE 2 END,
        d.dept_name ASC
";
$deptResult = $conn->query($deptQuery);
$departments = [];
while ($row = $deptResult->fetch_assoc()) {
    $departments[] = $row;
}

// Group departments by categories
$ceitDepartments = [];
$otherDepartments = [];

foreach ($departments as $dept) {
    if (strtoupper($dept['acronym']) === 'CEIT') {
        $ceitDepartments[] = $dept;
    } else {
        $otherDepartments[] = $dept;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Department Management specific styles */
        .dept-card {
            transition: all 0.3s ease;
        }

        .dept-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }

        .status-badge.assigned {
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #6ee7b7;
        }

        .status-badge.unassigned {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .section-header {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem 0.75rem 0 0;
            margin-bottom: 0;
        }

        .section-content {
            background: white;
            border-radius: 0 0 0.75rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-top: none;
        }

        /* Notification styles */
        .dept-notification {
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
            animation: slideIn 0.3s ease-out;
        }

        .dept-notification.success {
            background-color: #ea580c;
        }

        .dept-notification.error {
            background-color: #ef4444;
        }

        .dept-notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Modal styles */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dept-card {
                margin-bottom: 1rem;
            }
            
            .section-header {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0">
                <i class="fas fa-building mr-3 w-5"></i> Department Management
            </h1>
            <button id="create-dept-btn"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-plus mr-2"></i> Create Department
            </button>
        </div>

        <!-- CEIT Department Section -->
        <div class="mb-8">
            <div class="section-header">
                <h2 class="text-xl font-bold flex items-center justify-between">
                    <span class="flex items-center">
                        <i class="fas fa-star mr-3"></i>
                        CEIT Department (<?= count($ceitDepartments) ?>)
                    </span>
                    <span class="text-sm font-normal opacity-75">
                        <i class="fas fa-info-circle mr-1"></i>
                        Main Department
                    </span>
                </h2>
            </div>
            <div class="section-content p-6">
                <?php if (count($ceitDepartments) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($ceitDepartments as $dept): ?>
                            <div class="dept-card bg-white border border-yellow-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-star text-yellow-600 text-lg"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($dept['dept_name']) ?></h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($dept['acronym']) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="flex items-center text-sm text-gray-600 mb-2">
                                        <i class="fas fa-user-tie mr-2"></i>
                                        <?php if ($dept['officer_name']): ?>
                                            <span><?= htmlspecialchars($dept['officer_name']) ?></span>
                                            <?php if ($dept['officer_role'] === 'LEAD_MIS'): ?>
                                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full ml-2">LEAD</span>
                                            <?php endif; ?>
                                            <span class="status-badge assigned ml-2">Assigned</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No officer assigned</span>
                                            <span class="status-badge unassigned ml-2">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-puzzle-piece mr-2"></i>
                                        <span><?= $dept['module_count'] ?> modules assigned</span>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 edit-dept-btn" 
                                        data-id="<?= $dept['dept_id'] ?>" 
                                        data-name="<?= htmlspecialchars($dept['dept_name']) ?>" 
                                        data-acronym="<?= htmlspecialchars($dept['acronym']) ?>" 
                                        data-officer-id="<?= $dept['officer_id'] ?>" 
                                        title="Edit Department">
                                        <i class="fas fa-edit fa-sm"></i>
                                    </button>
                                    <!-- No delete button for CEIT department -->
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-star fa-3x mb-4 text-gray-300"></i>
                        <p class="text-lg">No CEIT Department found</p>
                        <p class="text-sm text-gray-400 mt-2">Create a department with acronym "CEIT"</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Other Departments Section -->
        <div class="mb-8">
            <div class="section-header">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fas fa-building mr-3"></i>
                    Other Departments (<?= count($otherDepartments) ?>)
                </h2>
            </div>
            <div class="section-content p-6">
                <?php if (count($otherDepartments) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($otherDepartments as $dept): ?>
                            <div class="dept-card bg-white border border-blue-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-building text-blue-600 text-lg"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($dept['dept_name']) ?></h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($dept['acronym']) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="flex items-center text-sm text-gray-600 mb-2">
                                        <i class="fas fa-user-tie mr-2"></i>
                                        <?php if ($dept['officer_name']): ?>
                                            <span><?= htmlspecialchars($dept['officer_name']) ?></span>
                                            <?php if ($dept['officer_role'] === 'LEAD_MIS'): ?>
                                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full ml-2">LEAD</span>
                                            <?php endif; ?>
                                            <span class="status-badge assigned ml-2">Assigned</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No officer assigned</span>
                                            <span class="status-badge unassigned ml-2">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-puzzle-piece mr-2"></i>
                                        <span><?= $dept['module_count'] ?> modules assigned</span>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 edit-dept-btn" 
                                        data-id="<?= $dept['dept_id'] ?>" 
                                        data-name="<?= htmlspecialchars($dept['dept_name']) ?>" 
                                        data-acronym="<?= htmlspecialchars($dept['acronym']) ?>" 
                                        data-officer-id="<?= $dept['officer_id'] ?>" 
                                        title="Edit Department">
                                        <i class="fas fa-edit fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 delete-dept-btn" 
                                        data-id="<?= $dept['dept_id'] ?>" 
                                        data-name="<?= htmlspecialchars($dept['dept_name']) ?>" 
                                        title="Delete Department">
                                        <i class="fas fa-trash fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-building fa-3x mb-4 text-gray-300"></i>
                        <p class="text-lg">No other departments found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit Department Modal -->
    <div id="dept-modal" class="fixed inset-0 modal-overlay flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h2 class="text-xl font-bold mb-4" id="modal-title">Create Department</h2>
            <form id="dept-form" class="space-y-4">
                <input type="hidden" id="dept-id" name="dept_id">
                <div>
                    <label for="dept-name" class="block text-sm font-medium text-gray-700">Department Name</label>
                    <input type="text" id="dept-name" name="dept_name" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="dept-acronym" class="block text-sm font-medium text-gray-700">Acronym</label>
                    <input type="text" id="dept-acronym" name="acronym" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="dept-officer" class="block text-sm font-medium text-gray-700">Assign Officer</label>
                    <select id="dept-officer" name="officer_id"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                        <option value="">No Officer</option>
                        <?php foreach ($availableOfficers as $officer): ?>
                            <option value="<?= $officer['id'] ?>">
                                <?= htmlspecialchars($officer['name']) ?> (<?= htmlspecialchars($officer['email']) ?>)
                                <?php if ($officer['role'] === 'LEAD_MIS'): ?>
                                    - LEAD MIS
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Missing Modules Section (only shown during edit) -->
                <div id="missing-modules-section" class="hidden">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-yellow-800 mb-3 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Missing Modules (<span id="missing-count">0</span>)
                        </h3>
                        <div id="missing-modules-list" class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto mb-3">
                            <!-- Missing modules will be populated here -->
                        </div>
                        <div class="bg-yellow-100 p-3 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                <span class="text-sm text-yellow-700">These missing modules will be automatically added when you update the department.</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modules that will be auto-assigned (only shown during create) -->
                <div id="create-modules-section" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-blue-800 mb-3 flex items-center">
                        <i class="fas fa-puzzle-piece mr-2"></i>
                        Modules to be Auto-Assigned (<?= count($allModules) ?>)
                    </h3>
                    <?php if (count($allModules) > 0): ?>
                        <div class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto">
                            <?php foreach ($allModules as $module): ?>
                                <div class="flex items-center p-2 bg-white rounded border">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    <div>
                                        <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($module['name']) ?></span>
                                        <?php if (!empty($module['description'])): ?>
                                            <p class="text-xs text-gray-600"><?= htmlspecialchars($module['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-blue-700">No modules available</p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        <span class="text-sm text-blue-700" id="modal-info-text">All available modules will be automatically assigned to this department.</span>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" id="cancel-btn"
                        class="px-4 py-2 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white transition duration-200">
                        <span id="submit-text">Create Department</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 modal-overlay flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Delete Department</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to delete this department?</p>
                <p class="font-semibold mt-2" id="delete-dept-name"></p>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mt-3">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                This will also unassign all officers and remove module assignments.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-3">
                <button id="cancel-delete-btn"
                    class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                    Cancel
                </button>
                <button id="confirm-delete-btn"
                    class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentDeptId = null;
        let isEditing = false;

        // Initialize the module
        function initializeDepartmentsModule() {
            console.log('Initializing Departments module...');
            
            // Prevent multiple initializations
            if (window.departmentsModuleInitialized) {
                console.log('Departments module already initialized');
                return;
            }
            
            window.departmentsModuleInitialized = true;
            
            initializeEventListeners();
            console.log('Departments module initialized');
        }

        // Initialize event listeners
        function initializeEventListeners() {
            // Create department button
            const createBtn = document.getElementById('create-dept-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    openDeptModal();
                });
            }

            // Edit department buttons
            document.querySelectorAll('.edit-dept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const acronym = this.getAttribute('data-acronym');
                    const officerId = this.getAttribute('data-officer-id');
                    
                    openDeptModal(id, name, acronym, officerId);
                });
            });

            // Delete department buttons
            document.querySelectorAll('.delete-dept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    openDeleteModal(id, name);
                });
            });

            // Modal event listeners
            const deptModal = document.getElementById('dept-modal');
            const deleteModal = document.getElementById('delete-modal');
            const deptForm = document.getElementById('dept-form');

            // Cancel buttons
            document.getElementById('cancel-btn').addEventListener('click', closeDeptModal);
            document.getElementById('cancel-delete-btn').addEventListener('click', closeDeleteModal);

            // Close modals when clicking outside
            deptModal.addEventListener('click', function(e) {
                if (e.target === deptModal) closeDeptModal();
            });
            
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) closeDeleteModal();
            });

            // Form submission
            deptForm.addEventListener('submit', handleFormSubmit);

            // Delete confirmation
            document.getElementById('confirm-delete-btn').addEventListener('click', handleDelete);
        }

        // Open department modal for create/edit
        async function openDeptModal(id = null, name = '', acronym = '', officerId = '') {
            isEditing = !!id;
            currentDeptId = id;
            
            document.getElementById('modal-title').textContent = isEditing ? 'Edit Department' : 'Create Department';
            document.getElementById('submit-text').textContent = isEditing ? 'Update Department' : 'Create Department';
            
            document.getElementById('dept-id').value = id || '';
            document.getElementById('dept-name').value = name;
            document.getElementById('dept-acronym').value = acronym;
            
            // Show/hide appropriate sections
            const createSection = document.getElementById('create-modules-section');
            const missingSection = document.getElementById('missing-modules-section');
            const infoText = document.getElementById('modal-info-text');
            
            if (isEditing) {
                // Hide create section, show missing section
                createSection.classList.add('hidden');
                missingSection.classList.remove('hidden');
                infoText.textContent = 'Missing modules will be automatically added when you update the department.';
                
                // Fetch department details including missing modules
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_department_details');
                    formData.append('dept_id', id);
                    
                    const response = await fetch('Manage_Modules/Departments/Departments.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Pre-select current officer (including those already assigned)
                        const officerSelect = document.getElementById('dept-officer');
                        
                        // First, add current officer to dropdown if they're not in available officers
                        if (data.department.officer_id) {
                            let optionExists = false;
                            for (let option of officerSelect.options) {
                                if (option.value == data.department.officer_id) {
                                    optionExists = true;
                                    break;
                                }
                            }
                            
                            if (!optionExists) {
                                const currentOfficerOption = document.createElement('option');
                                currentOfficerOption.value = data.department.officer_id;
                                currentOfficerOption.textContent = `${data.department.officer_name} (${data.department.officer_email})`;
                                if (data.department.officer_role === 'LEAD_MIS') {
                                    currentOfficerOption.textContent += ' - LEAD MIS';
                                }
                                currentOfficerOption.textContent += ' - Current';
                                officerSelect.appendChild(currentOfficerOption);
                            }
                        }
                        
                        // Set the selected officer
                        officerSelect.value = data.department.officer_id || '';
                        
                        // Display missing modules
                        const missingCount = document.getElementById('missing-count');
                        const missingList = document.getElementById('missing-modules-list');
                        
                        missingCount.textContent = data.missing_modules.length;
                        missingList.innerHTML = '';
                        
                        if (data.missing_modules.length > 0) {
                            data.missing_modules.forEach(module => {
                                const moduleDiv = document.createElement('div');
                                moduleDiv.className = 'flex items-center p-2 bg-white rounded border border-yellow-200';
                                moduleDiv.innerHTML = `
                                    <i class="fas fa-plus-circle text-yellow-500 mr-2"></i>
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">${module.name}</span>
                                        ${module.description ? `<p class="text-xs text-gray-600">${module.description}</p>` : ''}
                                    </div>
                                `;
                                missingList.appendChild(moduleDiv);
                            });
                        } else {
                            missingList.innerHTML = '<p class="text-sm text-green-700 text-center py-2">All modules are already assigned!</p>';
                        }
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error fetching department details:', error);
                    showNotification('Error loading department details', 'error');
                }
            } else {
                // Show create section, hide missing section
                createSection.classList.remove('hidden');
                missingSection.classList.add('hidden');
                infoText.textContent = 'All available modules will be automatically assigned to this department.';
                
                // Reset officer selection for create
                document.getElementById('dept-officer').value = '';
            }
            
            document.getElementById('dept-modal').classList.remove('hidden');
        }

        // Close department modal
        function closeDeptModal() {
            document.getElementById('dept-modal').classList.add('hidden');
            document.getElementById('dept-form').reset();
            
            // Clean up dynamically added officer options
            const officerSelect = document.getElementById('dept-officer');
            const options = officerSelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.textContent.includes('- Current')) {
                    option.remove();
                }
            });
            
            currentDeptId = null;
            isEditing = false;
        }

        // Open delete modal
        function openDeleteModal(id, name) {
            currentDeptId = id;
            document.getElementById('delete-dept-name').textContent = name;
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.add('hidden');
            currentDeptId = null;
        }

        // Handle form submission
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', isEditing ? 'update_department' : 'create_department');
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('Manage_Modules/Departments/Departments.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeDeptModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while processing the request', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Handle delete
        async function handleDelete() {
            if (!currentDeptId) return;
            
            const deleteBtn = document.getElementById('confirm-delete-btn');
            const originalText = deleteBtn.innerHTML;
            
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
            deleteBtn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_department');
                formData.append('dept_id', currentDeptId);
                
                const response = await fetch('Manage_Modules/Departments/Departments.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeDeleteModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while deleting the department', 'error');
            } finally {
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `dept-notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Initialize when DOM is ready
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initializeDepartmentsModule, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeDepartmentsModule, 100);
            });
        }
    </script>
</body>

</html>