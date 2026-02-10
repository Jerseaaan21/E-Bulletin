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

            // Special handling for CEIT department creation
            $isCEIT = strtoupper($acronym) === 'CEIT';

            // Get programs data if provided
            $programs = $_POST['programs'] ?? [];

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Insert new department
                $stmt = $conn->prepare("INSERT INTO departments (dept_name, acronym) VALUES (?, ?)");
                $stmt->bind_param("ss", $deptName, $acronym);
                $stmt->execute();
                $deptId = $conn->insert_id;

                // Auto-assign ALL modules from modules table (only for non-CEIT departments)
                if (!$isCEIT) {
                    $modulesQuery = "SELECT id FROM modules";
                    $modulesResult = $conn->query($modulesQuery);
                    
                    if ($modulesResult->num_rows > 0) {
                        $insertModuleStmt = $conn->prepare("INSERT INTO department_modules (department_id, module_id) VALUES (?, ?)");
                        while ($module = $modulesResult->fetch_assoc()) {
                            $insertModuleStmt->bind_param("ii", $deptId, $module['id']);
                            $insertModuleStmt->execute();
                        }
                    }
                }

                // Insert programs into programs_status table
                if (!empty($programs)) {
                    $insertProgramStmt = $conn->prepare("INSERT INTO programs_status (dept_id, program_name, program_code) VALUES (?, ?, ?)");
                    foreach ($programs as $program) {
                        if (!empty($program['name']) && !empty($program['code'])) {
                            $insertProgramStmt->bind_param("iss", $deptId, $program['name'], $program['code']);
                            $insertProgramStmt->execute();
                        }
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

            // Get programs data if provided
            $programs = $_POST['programs'] ?? [];

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

                // Update programs - delete existing and insert new ones
                $deleteProgramsStmt = $conn->prepare("DELETE FROM programs_status WHERE dept_id = ?");
                $deleteProgramsStmt->bind_param("i", $deptId);
                $deleteProgramsStmt->execute();

                if (!empty($programs)) {
                    $insertProgramStmt = $conn->prepare("INSERT INTO programs_status (dept_id, program_name, program_code) VALUES (?, ?, ?)");
                    foreach ($programs as $program) {
                        if (!empty($program['name']) && !empty($program['code'])) {
                            $insertProgramStmt->bind_param("iss", $deptId, $program['name'], $program['code']);
                            $insertProgramStmt->execute();
                        }
                    }
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

            // Check if this is CEIT department - prevent deletion
            $deptCheckStmt = $conn->prepare("SELECT acronym FROM departments WHERE dept_id = ?");
            $deptCheckStmt->bind_param("i", $deptId);
            $deptCheckStmt->execute();
            $currentDept = $deptCheckStmt->get_result()->fetch_assoc();
            
            if ($currentDept && strtoupper($currentDept['acronym']) === 'CEIT') {
                echo json_encode(['success' => false, 'message' => 'CEIT department cannot be deleted as it is the main department']);
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

            // Check if this is CEIT department
            $isCEIT = strtoupper($department['acronym']) === 'CEIT';

            // Get programs for this department
            $programsQuery = "SELECT id, program_name, program_code, accreditation_level, accreditation_date FROM programs_status WHERE dept_id = ? ORDER BY program_name";
            $programsStmt = $conn->prepare($programsQuery);
            $programsStmt->bind_param("i", $deptId);
            $programsStmt->execute();
            $programsResult = $programsStmt->get_result();
            $programs = [];
            while ($row = $programsResult->fetch_assoc()) {
                $programs[] = $row;
            }

            // Get all available modules
            $allModulesQuery = "SELECT id, name, description FROM modules ORDER BY name";
            $allModulesResult = $conn->query($allModulesQuery);
            $allModules = [];
            while ($row = $allModulesResult->fetch_assoc()) {
                $allModules[] = $row;
            }

            if ($isCEIT) {
                // CEIT has access to all modules automatically
                echo json_encode([
                    'success' => true,
                    'department' => $department,
                    'programs' => $programs,
                    'is_ceit' => true,
                    'missing_modules' => [], // No missing modules for CEIT
                    'total_modules' => count($allModules),
                    'assigned_modules' => count($allModules) // All modules are considered assigned
                ]);
            } else {
                // For other departments, check assigned modules
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
                    'programs' => $programs,
                    'is_ceit' => false,
                    'missing_modules' => $missingModules,
                    'total_modules' => count($allModules),
                    'assigned_modules' => count($assignedModuleIds)
                ]);
            }

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
        CASE 
            WHEN UPPER(d.acronym) = 'CEIT' THEN (
                SELECT COUNT(*) FROM modules
            ) + (
                SELECT COUNT(*) FROM ceit_modules
            )
            ELSE COUNT(dm.module_id)
        END as module_count
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
    // Fetch programs for this department
    $programsQuery = "SELECT program_name, program_code FROM programs_status WHERE dept_id = ? ORDER BY program_name";
    $programsStmt = $conn->prepare($programsQuery);
    $programsStmt->bind_param("i", $row['dept_id']);
    $programsStmt->execute();
    $programsResult = $programsStmt->get_result();
    
    $programs = [];
    while ($program = $programsResult->fetch_assoc()) {
        $programs[] = $program;
    }
    
    $row['programs'] = $programs;
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
                        College (<?= count($ceitDepartments) ?>)
                    </span>
                    <span class="text-sm font-normal opacity-75">
                        <i class="fas fa-info-circle mr-1"></i>
                        College
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
                                    <div class="flex items-center text-sm text-gray-600 mb-2">
                                        <i class="fas fa-puzzle-piece mr-2"></i>
                                        <span><?= $dept['module_count'] ?> modules available</span>
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full ml-2">All Access</span>
                                    </div>
                                    <?php if (!empty($dept['programs'])): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="flex items-center text-sm text-gray-700 font-medium mb-2">
                                                <i class="fas fa-graduation-cap mr-2 text-orange-500"></i>
                                                <span>Programs (<?= count($dept['programs']) ?>)</span>
                                            </div>
                                            <div class="space-y-1 max-h-24 overflow-y-auto">
                                                <?php foreach ($dept['programs'] as $program): ?>
                                                    <div class="text-xs bg-gray-50 rounded px-2 py-1 flex items-center">
                                                        <span class="font-medium text-orange-600 mr-2"><?= htmlspecialchars($program['program_code']) ?></span>
                                                        <span class="text-gray-600 truncate"><?= htmlspecialchars($program['program_name']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                    Departments (<?= count($otherDepartments) ?>)
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
                                    <div class="flex items-center text-sm text-gray-600 mb-2">
                                        <i class="fas fa-puzzle-piece mr-2"></i>
                                        <span><?= $dept['module_count'] ?> modules assigned</span>
                                    </div>
                                    <?php if (!empty($dept['programs'])): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="flex items-center text-sm text-gray-700 font-medium mb-2">
                                                <i class="fas fa-graduation-cap mr-2 text-orange-500"></i>
                                                <span>Programs (<?= count($dept['programs']) ?>)</span>
                                            </div>
                                            <div class="space-y-1 max-h-24 overflow-y-auto">
                                                <?php foreach ($dept['programs'] as $program): ?>
                                                    <div class="text-xs bg-gray-50 rounded px-2 py-1 flex items-center">
                                                        <span class="font-medium text-orange-600 mr-2"><?= htmlspecialchars($program['program_code']) ?></span>
                                                        <span class="text-gray-600 truncate"><?= htmlspecialchars($program['program_name']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold text-orange-600 mb-6" id="modal-title">Create Department</h2>
            <form id="dept-form" class="space-y-6">
                <input type="hidden" id="dept-id" name="dept_id">
                
                <!-- Basic Info Section - 3 columns -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="dept-name" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-building text-orange-500 mr-1"></i> Department Name
                        </label>
                        <input type="text" id="dept-name" name="dept_name" required
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label for="dept-acronym" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag text-orange-500 mr-1"></i> Acronym
                        </label>
                        <input type="text" id="dept-acronym" name="acronym" required
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label for="dept-officer" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-user-tie text-orange-500 mr-1"></i> Assign Officer
                        </label>
                        <select id="dept-officer" name="officer_id"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
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
                </div>

                <!-- Programs Section -->
                <div id="programs-section" class="border-t pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-graduation-cap text-orange-500 mr-2"></i> Programs
                        </h3>
                        <button type="button" id="add-program-btn" 
                            class="px-3 py-1 bg-orange-500 text-white rounded-md hover:bg-orange-600 transition duration-200 text-sm">
                            <i class="fas fa-plus mr-1"></i> Add Program
                        </button>
                    </div>
                    
                    <div id="programs-container" class="space-y-3">
                        <!-- Programs will be added here dynamically -->
                        <div class="text-center py-4 text-gray-400 text-sm" id="no-programs-message">
                            <i class="fas fa-info-circle mr-1"></i> No programs added yet. Click "Add Program" to get started.
                        </div>
                    </div>
                </div>
                
                <!-- Module Status Section (only shown during edit) -->
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
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 max-h-60 overflow-y-auto">
                            <?php foreach ($allModules as $module): ?>
                                <div class="flex items-start p-2 bg-white rounded border">
                                    <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-gray-800 block"><?= htmlspecialchars(str_replace('_', ' ', $module['name'])) ?></span>
                                        <?php if (!empty($module['description'])): ?>
                                            <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($module['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-blue-700">No modules available</p>
                    <?php endif; ?>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mt-3">
                        <div class="flex items-center">
                            <i class="fas fa-star text-yellow-600 mr-2"></i>
                            <span class="text-sm text-yellow-700">
                                <strong>Note:</strong> If you create a department with acronym "CEIT", it will be treated as the main department with automatic access to all modules.
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        <span class="text-sm text-blue-700" id="modal-info-text">All available modules will be automatically assigned to this department.</span>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" id="dept-cancel-btn"
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
    <div id="dept-delete-modal" class="fixed inset-0 modal-overlay flex items-center justify-center hidden z-50">
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
                <button id="dept-cancel-delete-btn"
                    class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                    Cancel
                </button>
                <button id="dept-confirm-delete-btn"
                    class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentDeptId = null;
        let isEditingDept = false;

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

            // Add program button
            const addProgramBtn = document.getElementById('add-program-btn');
            if (addProgramBtn) {
                addProgramBtn.addEventListener('click', function() {
                    addProgramRow();
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
            const deleteModal = document.getElementById('dept-delete-modal');
            const deptForm = document.getElementById('dept-form');

            // Cancel buttons
            const cancelBtn = document.getElementById('dept-cancel-btn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeDeptModal);
            }
            const cancelDeleteBtn = document.getElementById('dept-cancel-delete-btn');
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', closeDeleteModal);
            }

            // Close modals when clicking outside
            if (deptModal) {
                deptModal.addEventListener('click', function(e) {
                    if (e.target === deptModal) closeDeptModal();
                });
            }
            
            if (deleteModal) {
                deleteModal.addEventListener('click', function(e) {
                    if (e.target === deleteModal) closeDeleteModal();
                });
            }

            // Form submission
            if (deptForm) {
                deptForm.addEventListener('submit', handleFormSubmit);
            }

            // Delete confirmation
            const confirmDeleteBtn = document.getElementById('dept-confirm-delete-btn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', handleDelete);
            }
        }

        // Open department modal for create/edit
        async function openDeptModal(id = null, name = '', acronym = '', officerId = '') {
            isEditingDept = !!id;
            currentDeptId = id;
            
            document.getElementById('modal-title').textContent = isEditingDept ? 'Edit Department' : 'Create Department';
            document.getElementById('submit-text').textContent = isEditingDept ? 'Update Department' : 'Create Department';
            
            document.getElementById('dept-id').value = id || '';
            document.getElementById('dept-name').value = name;
            document.getElementById('dept-acronym').value = acronym;
            
            // Check if this is CEIT (college level)
            const isCEIT = acronym && acronym.toUpperCase() === 'CEIT';
            
            // Show/hide Programs section based on whether it's CEIT
            const programsSection = document.getElementById('programs-section');
            if (programsSection) {
                if (isCEIT) {
                    programsSection.classList.add('hidden');
                } else {
                    programsSection.classList.remove('hidden');
                }
            }
            
            // Show/hide appropriate sections
            const createSection = document.getElementById('create-modules-section');
            const missingSection = document.getElementById('missing-modules-section');
            const infoText = document.getElementById('modal-info-text');
            
            if (isEditingDept) {
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

                        // Populate existing programs
                        if (data.programs && data.programs.length > 0) {
                            clearAllPrograms();
                            data.programs.forEach(program => {
                                addProgramRow(program.program_name, program.program_code);
                            });
                        }
                        
                        // Handle CEIT department special case
                        const missingCount = document.getElementById('missing-count');
                        const missingList = document.getElementById('missing-modules-list');
                        
                        if (data.is_ceit) {
                            // CEIT department - show all modules available message
                            missingCount.textContent = '0';
                            missingList.innerHTML = `
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center justify-center">
                                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                                        <span class="text-sm font-medium text-green-700">All modules are automatically assigned to CEIT department</span>
                                    </div>
                                    <p class="text-xs text-green-600 text-center mt-2">
                                        As the main department, CEIT has automatic access to all ${data.total_modules} available modules
                                    </p>
                                </div>
                            `;
                            
                            // Update info text for CEIT
                            infoText.textContent = 'CEIT is the main department with automatic access to all modules. Only name, acronym, and officer assignment can be modified.';
                            
                            // Update missing modules section title and styling for CEIT
                            const missingTitle = missingSection.querySelector('h3');
                            const missingContainer = missingSection.querySelector('.bg-yellow-50');
                            if (missingTitle) {
                                missingTitle.innerHTML = `
                                    <i class="fas fa-star mr-2"></i>
                                    CEIT Department - Main Department (All Modules Available)
                                `;
                                missingTitle.className = 'text-sm font-medium text-green-800 mb-3 flex items-center';
                            }
                            if (missingContainer) {
                                missingContainer.className = 'bg-green-50 border border-green-200 rounded-lg p-4';
                            }
                            
                            // Hide the info box at the bottom for CEIT
                            const infoBox = missingSection.querySelector('.bg-yellow-100');
                            if (infoBox) {
                                infoBox.style.display = 'none';
                            }
                        } else {
                            // Regular department - show missing modules
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
            
            // Clear all programs
            clearAllPrograms();
            
            // Show programs section again (in case it was hidden for CEIT)
            const programsSection = document.getElementById('programs-section');
            if (programsSection) {
                programsSection.classList.remove('hidden');
            }
            
            // Clean up dynamically added officer options
            const officerSelect = document.getElementById('dept-officer');
            const options = officerSelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.textContent.includes('- Current')) {
                    option.remove();
                }
            });
            
            // Reset modal styling to default (for regular departments)
            const missingSection = document.getElementById('missing-modules-section');
            const missingTitle = missingSection.querySelector('h3');
            const missingContainer = missingSection.querySelector('div');
            const infoBox = missingSection.querySelector('.bg-yellow-100, .bg-green-100');
            
            if (missingTitle) {
                missingTitle.innerHTML = `
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Missing Modules (<span id="missing-count">0</span>)
                `;
                missingTitle.className = 'text-sm font-medium text-yellow-800 mb-3 flex items-center';
            }
            if (missingContainer && missingContainer.classList.contains('bg-green-50')) {
                missingContainer.className = 'bg-yellow-50 border border-yellow-200 rounded-lg p-4';
            }
            if (infoBox) {
                infoBox.style.display = 'block';
            }
            
            currentDeptId = null;
            isEditingDept = false;
        }

        // Open delete modal
        function openDeleteModal(id, name) {
            currentDeptId = id;
            document.getElementById('delete-dept-name').textContent = name;
            document.getElementById('dept-delete-modal').classList.remove('hidden');
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('dept-delete-modal').classList.add('hidden');
            currentDeptId = null;
        }

        // Handle form submission
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', isEditingDept ? 'update_department' : 'create_department');
            
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
            
            const deleteBtn = document.getElementById('dept-confirm-delete-btn');
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

        // Program management functions
        let programCounter = 0;

        function addProgramRow(programName = '', programCode = '') {
            programCounter++;
            const container = document.getElementById('programs-container');
            const noMessage = document.getElementById('no-programs-message');
            
            if (noMessage) {
                noMessage.remove();
            }

            const programRow = document.createElement('div');
            programRow.className = 'program-row bg-gray-50 border border-gray-200 rounded-lg p-4';
            programRow.setAttribute('data-program-id', programCounter);
            
            programRow.innerHTML = `
                <div class="flex items-start justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-700">
                        <i class="fas fa-graduation-cap text-orange-500 mr-1"></i> Program #${programCounter}
                    </h4>
                    <button type="button" class="remove-program-btn text-red-500 hover:text-red-700 transition duration-200" data-program-id="${programCounter}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Program Name</label>
                        <input type="text" name="programs[${programCounter}][name]" required
                            value="${programName}"
                            placeholder="e.g., Bachelor of Science in Information Technology"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Program Code</label>
                        <input type="text" name="programs[${programCounter}][code]" required
                            value="${programCode}"
                            placeholder="e.g., BSIT"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                    </div>
                </div>
            `;
            
            container.appendChild(programRow);
            
            // Add event listener to remove button
            programRow.querySelector('.remove-program-btn').addEventListener('click', function() {
                removeProgramRow(this.getAttribute('data-program-id'));
            });
        }

        function removeProgramRow(programId) {
            const programRow = document.querySelector(`.program-row[data-program-id="${programId}"]`);
            if (programRow) {
                programRow.remove();
            }
            
            // Check if there are no programs left
            const container = document.getElementById('programs-container');
            if (container.children.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-gray-400 text-sm" id="no-programs-message">
                        <i class="fas fa-info-circle mr-1"></i> No programs added yet. Click "Add Program" to get started.
                    </div>
                `;
            }
        }

        function clearAllPrograms() {
            const container = document.getElementById('programs-container');
            container.innerHTML = `
                <div class="text-center py-4 text-gray-400 text-sm" id="no-programs-message">
                    <i class="fas fa-info-circle mr-1"></i> No programs added yet. Click "Add Program" to get started.
                </div>
            `;
            programCounter = 0;
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