<?php
// get_department_data.php
include "db.php";

header('Content-Type: application/json');

session_start();

// Get department ID from session
$dept_id = $_SESSION['user_info']['dept_id'] ?? null;

if (!$dept_id) {
    echo json_encode(['success' => false, 'message' => 'Department ID not found in session']);
    exit;
}

// Get department information
$dept_query = "SELECT * FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($dept_query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$department = $stmt->get_result()->fetch_assoc();

// Get modules assigned to this department
$modules_query = "SELECT m.* FROM modules m 
                 INNER JOIN department_modules dm ON m.id = dm.module_id 
                 WHERE dm.department_id = ?";
$stmt = $conn->prepare($modules_query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user information for this department
$user_query = "SELECT * FROM users WHERE dept_id = ? AND role = 'MIS' LIMIT 1";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// If no MIS user found, try to get any user from the department
if (!$user) {
    $user_query = "SELECT * FROM users WHERE dept_id = ? LIMIT 1";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

echo json_encode([
    'success' => true,
    'department' => $department,
    'modules' => $modules,
    'user' => $user
]);
