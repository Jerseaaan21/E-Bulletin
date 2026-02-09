<?php
// Manage_Modules/Users/Users.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

// Start output buffering to catch any unexpected output
ob_start();

include "../../db.php";
session_start();

// Handle AJAX requests for user management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clear any output buffer and start fresh
    ob_clean();
    
    header('Content-Type: application/json');

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    $action = $_POST['action'];

    try {
        if ($action === 'create_user') {
            $email = $_POST['email'] ?? '';
            $name = $_POST['name'] ?? '';
            $role = $_POST['role'] ?? '';
            $dept_id = $_POST['dept_id'] ?? null;

            // Convert empty string to null for dept_id
            if (empty($dept_id)) {
                $dept_id = null;
            }

            // Validate required fields
            if (empty($email) || empty($name) || empty($role)) {
                echo json_encode(['success' => false, 'message' => 'Email, name, and role are required']);
                exit;
            }

            // Check if trying to create LEAD_MIS when one already exists
            if ($role === 'LEAD_MIS') {
                $checkLeadStmt = $conn->prepare("SELECT id FROM users WHERE role = 'LEAD_MIS' LIMIT 1");
                $checkLeadStmt->execute();
                if ($checkLeadStmt->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Only one Lead MIS Officer is allowed. Please change the existing Lead MIS Officer role first.']);
                    exit;
                }
            }

            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }

            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (email, name, role, dept_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $email, $name, $role, $dept_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create user']);
            }

        } elseif ($action === 'update_user') {
            $id = $_POST['id'] ?? '';
            $email = $_POST['email'] ?? '';
            $name = $_POST['name'] ?? '';
            $role = $_POST['role'] ?? '';
            $dept_id = $_POST['dept_id'] ?? null;

            // Convert empty string to null for dept_id
            if (empty($dept_id)) {
                $dept_id = null;
            }

            if (empty($id) || empty($email) || empty($name) || empty($role)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }

            // Check if trying to change to LEAD_MIS when one already exists (and it's not the current user)
            if ($role === 'LEAD_MIS') {
                $checkLeadStmt = $conn->prepare("SELECT id FROM users WHERE role = 'LEAD_MIS' AND id != ? LIMIT 1");
                $checkLeadStmt->bind_param("i", $id);
                $checkLeadStmt->execute();
                if ($checkLeadStmt->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Only one Lead MIS Officer is allowed. Please change the existing Lead MIS Officer role first.']);
                    exit;
                }
            }

            // Check if email already exists for other users
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->bind_param("si", $email, $id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET email = ?, name = ?, role = ?, dept_id = ? WHERE id = ?");
            $stmt->bind_param("sssii", $email, $name, $role, $dept_id, $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            }

        } elseif ($action === 'delete_user') {
            $id = $_POST['id'] ?? '';

            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
            }

        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        // Clear any output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Get all departments
$deptQuery = "SELECT dept_id, dept_name, acronym FROM departments ORDER BY dept_name";
$deptResult = $conn->query($deptQuery);
$departments = [];
while ($row = $deptResult->fetch_assoc()) {
    $departments[] = $row;
}

// Get departments that already have users assigned
$assignedDeptQuery = "SELECT DISTINCT dept_id FROM users WHERE dept_id IS NOT NULL";
$assignedDeptResult = $conn->query($assignedDeptQuery);
$assignedDepartments = [];
while ($row = $assignedDeptResult->fetch_assoc()) {
    $assignedDepartments[] = $row['dept_id'];
}

// Check if there's already a LEAD_MIS user
$leadMisQuery = "SELECT id, name FROM users WHERE role = 'LEAD_MIS' LIMIT 1";
$leadMisResult = $conn->query($leadMisQuery);
$existingLeadMis = $leadMisResult->fetch_assoc();

// Get users with their department information, sorted by role and department assignment
$userQuery = "
    SELECT u.*, d.dept_name, d.acronym 
    FROM users u 
    LEFT JOIN departments d ON u.dept_id = d.dept_id 
    ORDER BY 
        CASE WHEN u.role = 'LEAD_MIS' THEN 1 ELSE 2 END,
        CASE WHEN u.dept_id IS NULL THEN 1 ELSE 0 END,
        d.dept_name ASC,
        u.name ASC
";
$userResult = $conn->query($userQuery);
$users = [];
while ($row = $userResult->fetch_assoc()) {
    $users[] = $row;
}

// Group users by categories
$leadMisUsers = [];
$assignedMisUsers = [];
$unassignedUsers = [];

foreach ($users as $user) {
    if ($user['role'] === 'LEAD_MIS') {
        $leadMisUsers[] = $user;
    } elseif ($user['role'] === 'MIS' && $user['dept_id']) {
        $assignedMisUsers[] = $user;
    } else {
        $unassignedUsers[] = $user;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* User Management specific styles */
        .user-card {
            transition: all 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .role-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }

        .role-badge.lead-mis {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
        }

        .role-badge.mis {
            background-color: #dbeafe;
            color: #2563eb;
            border: 1px solid #93c5fd;
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

        /* Notification styles with unique class name */
        .users-notification {
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

        .users-notification.success {
            background-color: #ea580c;
        }

        .users-notification.error {
            background-color: #ef4444;
        }

        .users-notification i {
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

        /* Disabled option styling */
        select option:disabled {
            color: #9ca3af !important;
            background-color: #f9fafb !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .user-card {
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
                <i class="fas fa-users mr-3 w-5"></i> User Management
            </h1>
            <button id="create-user-btn"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-user-plus mr-2"></i> Create User
            </button>
        </div>

        <!-- Lead MIS Users Section -->
        <div class="mb-8">
            <div class="section-header">
                <h2 class="text-xl font-bold flex items-center justify-between">
                    <span class="flex items-center">
                        <i class="fas fa-crown mr-3"></i>
                        Lead MIS Officers (<?= count($leadMisUsers) ?>)
                    </span>
                    <span class="text-sm font-normal opacity-75">
                        <i class="fas fa-info-circle mr-1"></i>
                        Maximum: 1
                    </span>
                </h2>
            </div>
            <div class="section-content p-6">
                <?php if (count($leadMisUsers) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($leadMisUsers as $user): ?>
                            <div class="user-card bg-white border border-yellow-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-crown text-yellow-600 text-lg"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                                        </div>
                                    </div>
                                    <span class="role-badge lead-mis">LEAD MIS</span>
                                </div>
                                <div class="mb-3">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-building mr-2"></i>
                                        <?php if ($user['dept_name']): ?>
                                            <span><?= htmlspecialchars($user['dept_name']) ?> (<?= htmlspecialchars($user['acronym']) ?>)</span>
                                            <span class="status-badge assigned ml-2">Assigned</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No department assigned</span>
                                            <span class="status-badge unassigned ml-2">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 edit-user-btn" 
                                        data-id="<?= $user['id'] ?>" 
                                        data-email="<?= htmlspecialchars($user['email']) ?>" 
                                        data-name="<?= htmlspecialchars($user['name']) ?>" 
                                        data-role="<?= $user['role'] ?>" 
                                        data-dept-id="<?= $user['dept_id'] ?>" 
                                        title="Edit User">
                                        <i class="fas fa-edit fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 delete-user-btn" 
                                        data-id="<?= $user['id'] ?>" 
                                        data-name="<?= htmlspecialchars($user['name']) ?>" 
                                        title="Delete User">
                                        <i class="fas fa-trash fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-crown fa-3x mb-4 text-gray-300"></i>
                        <p class="text-lg">No Lead MIS Officer assigned</p>
                        <p class="text-sm text-gray-400 mt-2">Create a user and assign them the Lead MIS role</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned MIS Users Section -->
        <div class="mb-8">
            <div class="section-header">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fas fa-user-check mr-3"></i>
                    Assigned MIS Officers (<?= count($assignedMisUsers) ?>)
                </h2>
            </div>
            <div class="section-content p-6">
                <?php if (count($assignedMisUsers) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($assignedMisUsers as $user): ?>
                            <div class="user-card bg-white border border-green-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600 text-lg"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                                        </div>
                                    </div>
                                    <span class="role-badge mis">MIS</span>
                                </div>
                                <div class="mb-3">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-building mr-2"></i>
                                        <span><?= htmlspecialchars($user['dept_name']) ?> (<?= htmlspecialchars($user['acronym']) ?>)</span>
                                        <span class="status-badge assigned ml-2">Assigned</span>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 edit-user-btn" 
                                        data-id="<?= $user['id'] ?>" 
                                        data-email="<?= htmlspecialchars($user['email']) ?>" 
                                        data-name="<?= htmlspecialchars($user['name']) ?>" 
                                        data-role="<?= $user['role'] ?>" 
                                        data-dept-id="<?= $user['dept_id'] ?>" 
                                        title="Edit User">
                                        <i class="fas fa-edit fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 delete-user-btn" 
                                        data-id="<?= $user['id'] ?>" 
                                        data-name="<?= htmlspecialchars($user['name']) ?>" 
                                        title="Delete User">
                                        <i class="fas fa-trash fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-user-check fa-3x mb-4 text-gray-300"></i>
                        <p class="text-lg">No assigned MIS Officers found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Unassigned Users Section -->
        <div class="mb-8">
            <div class="section-header">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fas fa-user-clock mr-3"></i>
                    Unassigned Users (<?= count($unassignedUsers) ?>)
                </h2>
            </div>
            <div class="section-content p-6">
                <?php if (count($unassignedUsers) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($unassignedUsers as $user): ?>
                            <div class="user-card bg-white border border-red-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600 text-lg"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                                        </div>
                                    </div>
                                    <span class="role-badge <?= $user['role'] === 'LEAD_MIS' ? 'lead-mis' : 'mis' ?>"><?= $user['role'] ?></span>
                                </div>
                                <div class="mb-3">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-building mr-2"></i>
                                        <span class="text-gray-400">No department assigned</span>
                                        <span class="status-badge unassigned ml-2">Unassigned</span>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 edit-user-btn" 
                                        data-id="<?= $user['id'] ?>" 
                                        data-email="<?= htmlspecialchars($user['email']) ?>" 
                                        data-name="<?= htmlspecialchars($user['name']) ?>" 
                                        data-role="<?= $user['role'] ?>" 
                                        data-dept-id="<?= $user['dept_id'] ?>" 
                                        title="Edit User">
                                        <i class="fas fa-edit fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 delete-user-btn" 
                                        data-id="<?= $user['id'] ?>" 
                                        data-name="<?= htmlspecialchars($user['name']) ?>" 
                                        title="Delete User">
                                        <i class="fas fa-trash fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-user-clock fa-3x mb-4 text-gray-300"></i>
                        <p class="text-lg">No unassigned users found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit User Modal -->
    <div id="users-modal" class="fixed inset-0 modal-overlay flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h2 class="text-xl font-bold mb-4" id="users-modal-title">Create User</h2>
            <form id="users-form" class="space-y-4">
                <input type="hidden" id="users-id" name="id">
                <div>
                    <label for="users-email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="users-email" name="email" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="users-name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="users-name" name="name" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="users-role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="users-role" name="role" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                        <option value="">Select Role</option>
                        <option value="MIS">MIS Officer</option>
                        <option value="LEAD_MIS" id="users-lead-mis-option">Lead MIS Officer</option>
                    </select>
                </div>
                <div>
                    <label for="users-dept" class="block text-sm font-medium text-gray-700">Department</label>
                    <select id="users-dept" name="dept_id"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                        <option value="">No Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <?php 
                            $isAssigned = in_array($dept['dept_id'], $assignedDepartments);
                            $disabledAttr = $isAssigned ? 'disabled' : '';
                            $disabledClass = $isAssigned ? 'text-gray-400' : '';
                            ?>
                            <option value="<?= $dept['dept_id'] ?>" <?= $disabledAttr ?> class="<?= $disabledClass ?>">
                                <?= htmlspecialchars($dept['dept_name']) ?> (<?= htmlspecialchars($dept['acronym']) ?>)
                                <?= $isAssigned ? ' - Already Assigned' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Each department can only have one assigned user. Departments with existing users are disabled.
                    </p>
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" id="users-cancel-btn"
                        class="px-4 py-2 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white transition duration-200">
                        <span id="users-submit-text">Create User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="users-delete-modal" class="fixed inset-0 modal-overlay flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Delete User</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to delete this user?</p>
                <p class="font-semibold mt-2" id="users-delete-name"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button id="users-cancel-delete-btn"
                    class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                    Cancel
                </button>
                <button id="users-confirm-delete-btn"
                    class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables with unique names
        let usersCurrentUserId = null;
        let usersIsEditingUser = false;
        
        // LEAD_MIS restriction data
        const usersExistingLeadMis = <?= json_encode($existingLeadMis) ?>;
        
        // Assigned departments data
        const usersAssignedDepartments = <?= json_encode($assignedDepartments) ?>;
        const usersAllDepartments = <?= json_encode($departments) ?>;
        
        console.log('Users - Existing Lead MIS:', usersExistingLeadMis);
        console.log('Users - Assigned Departments:', usersAssignedDepartments);
        console.log('Users - All Departments:', usersAllDepartments);

        // Initialize the module with unique function name
        function initializeUsersManagementModule() {
            console.log('Initializing Users Management module...');
            
            // Prevent multiple initializations
            if (window.usersManagementModuleInitialized) {
                console.log('Users Management module already initialized');
                return;
            }
            
            window.usersManagementModuleInitialized = true;
            
            initializeUsersEventListeners();
            console.log('Users Management module initialized');
        }

        // Initialize event listeners with unique function name
        function initializeUsersEventListeners() {
            // Create user button
            const createBtn = document.getElementById('create-user-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    openUsersModal();
                });
            }

            // Edit user buttons
            document.querySelectorAll('.edit-user-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const email = this.getAttribute('data-email');
                    const name = this.getAttribute('data-name');
                    const role = this.getAttribute('data-role');
                    const deptId = this.getAttribute('data-dept-id');
                    
                    openUsersModal(id, email, name, role, deptId);
                });
            });

            // Delete user buttons
            document.querySelectorAll('.delete-user-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    openUsersDeleteModal(id, name);
                });
            });

            // Modal event listeners
            const usersModal = document.getElementById('users-modal');
            const usersDeleteModal = document.getElementById('users-delete-modal');
            const usersForm = document.getElementById('users-form');

            // Cancel buttons
            const cancelBtn = document.getElementById('users-cancel-btn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeUsersModal);
            }
            
            const cancelDeleteBtn = document.getElementById('users-cancel-delete-btn');
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', closeUsersDeleteModal);
            }

            // Close modals when clicking outside
            if (usersModal) {
                usersModal.addEventListener('click', function(e) {
                    if (e.target === usersModal) closeUsersModal();
                });
            }
            
            if (usersDeleteModal) {
                usersDeleteModal.addEventListener('click', function(e) {
                    if (e.target === usersDeleteModal) closeUsersDeleteModal();
                });
            }

            // Form submission
            if (usersForm) {
                usersForm.addEventListener('submit', handleUsersFormSubmit);
            }

            // Delete confirmation
            const confirmDeleteBtn = document.getElementById('users-confirm-delete-btn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', handleUsersDelete);
            }
        }

        // Update department dropdown based on create/edit mode with unique function name
        function updateUsersDepartmentDropdown(currentUserDeptId = null) {
            const deptSelect = document.getElementById('users-dept');
            if (!deptSelect) return;
            
            // Clear existing options except "No Department"
            while (deptSelect.children.length > 1) {
                deptSelect.removeChild(deptSelect.lastChild);
            }
            
            // Add department options
            usersAllDepartments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.dept_id;
                
                const isAssigned = usersAssignedDepartments.includes(dept.dept_id.toString());
                const isCurrentUserDept = currentUserDeptId && dept.dept_id.toString() === currentUserDeptId.toString();
                
                // Enable if it's not assigned OR if it's the current user's department (for edit mode)
                const shouldDisable = isAssigned && !isCurrentUserDept;
                
                option.disabled = shouldDisable;
                option.className = shouldDisable ? 'text-gray-400' : '';
                
                let optionText = `${dept.dept_name} (${dept.acronym})`;
                if (isAssigned && !isCurrentUserDept) {
                    optionText += ' - Already Assigned';
                } else if (isCurrentUserDept) {
                    optionText += ' - Current';
                }
                
                option.textContent = optionText;
                deptSelect.appendChild(option);
            });
        }

        // Open user modal for create/edit with unique function name
        function openUsersModal(id = null, email = '', name = '', role = '', deptId = '') {
            usersIsEditingUser = !!id;
            usersCurrentUserId = id;
            
            document.getElementById('users-modal-title').textContent = usersIsEditingUser ? 'Edit User' : 'Create User';
            document.getElementById('users-submit-text').textContent = usersIsEditingUser ? 'Update User' : 'Create User';
            
            document.getElementById('users-id').value = id || '';
            document.getElementById('users-email').value = email;
            document.getElementById('users-name').value = name;
            document.getElementById('users-role').value = role;
            
            // Update department dropdown based on mode
            updateUsersDepartmentDropdown(usersIsEditingUser ? deptId : null);
            
            // Set the department value after updating the dropdown
            document.getElementById('users-dept').value = deptId || '';
            
            // Handle LEAD_MIS restriction
            const leadMisOption = document.getElementById('users-lead-mis-option');
            
            if (usersExistingLeadMis && usersExistingLeadMis.id) {
                // There's already a LEAD_MIS user
                if (usersIsEditingUser && id == usersExistingLeadMis.id) {
                    // Editing the current LEAD_MIS user - allow them to keep or change their role
                    leadMisOption.disabled = false;
                    leadMisOption.style.color = '';
                } else {
                    // Creating new user or editing someone else - disable LEAD_MIS option
                    leadMisOption.disabled = true;
                    leadMisOption.style.color = '#9ca3af'; // gray-400
                }
            } else {
                // No existing LEAD_MIS user - allow selection
                leadMisOption.disabled = false;
                leadMisOption.style.color = '';
            }
            
            document.getElementById('users-modal').classList.remove('hidden');
        }

        // Close user modal with unique function name
        function closeUsersModal() {
            document.getElementById('users-modal').classList.add('hidden');
            document.getElementById('users-form').reset();
            usersCurrentUserId = null;
            usersIsEditingUser = false;
        }

        // Open delete modal with unique function name
        function openUsersDeleteModal(id, name) {
            usersCurrentUserId = id;
            document.getElementById('users-delete-name').textContent = name;
            document.getElementById('users-delete-modal').classList.remove('hidden');
        }

        // Close delete modal with unique function name
        function closeUsersDeleteModal() {
            document.getElementById('users-delete-modal').classList.add('hidden');
            usersCurrentUserId = null;
        }

        // Handle form submission with unique function name
        async function handleUsersFormSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', usersIsEditingUser ? 'update_user' : 'create_user');
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            submitBtn.disabled = true;
            
            let responseText = '';
            
            try {
                const response = await fetch('Manage_Modules/Users/Users.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first to debug
                responseText = await response.text();
                console.log('Users - Response text:', responseText);
                
                // Try to parse as JSON
                const data = JSON.parse(responseText);
                
                if (data.success) {
                    showUsersNotification(data.message, 'success');
                    closeUsersModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showUsersNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Users - Error:', error);
                console.error('Users - Response text was:', responseText || 'No response text');
                showUsersNotification('An error occurred while processing the request: ' + error.message, 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Handle delete with unique function name
        async function handleUsersDelete() {
            if (!usersCurrentUserId) return;
            
            const deleteBtn = document.getElementById('users-confirm-delete-btn');
            const originalText = deleteBtn.innerHTML;
            
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
            deleteBtn.disabled = true;
            
            let responseText = '';
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('id', usersCurrentUserId);
                
                const response = await fetch('Manage_Modules/Users/Users.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first to debug
                responseText = await response.text();
                console.log('Users - Delete response text:', responseText);
                
                // Try to parse as JSON
                const data = JSON.parse(responseText);
                
                if (data.success) {
                    showUsersNotification(data.message, 'success');
                    closeUsersDeleteModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showUsersNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Users - Error:', error);
                console.error('Users - Delete response text was:', responseText || 'No response text');
                showUsersNotification('An error occurred while deleting the user: ' + error.message, 'error');
            } finally {
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        }

        // Show notification with unique function name
        function showUsersNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `users-notification ${type}`;
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
            setTimeout(initializeUsersManagementModule, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeUsersManagementModule, 100);
            });
        }
    </script>
</body>

</html>