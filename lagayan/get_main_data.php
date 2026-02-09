<?php
// get_main_data.php
include "db.php";

header('Content-Type: application/json');

session_start();

// Get user ID from session
$user_id = $_SESSION['user_info']['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID not found in session']);
    exit;
}

// Get user information
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 1. Get Manage Departments modules (CEIT_Modules table)
$ceit_modules_query = "SELECT * FROM CEIT_Modules";
$stmt = $conn->prepare($ceit_modules_query);
$stmt->execute();
$ceit_modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Get Posting modules (Modules table)
$modules_query = "SELECT * FROM Modules";
$stmt = $conn->prepare($modules_query);
$stmt->execute();
$modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'ceit_modules' => $ceit_modules, // For "Manage Departments" (from CEIT_Modules table)
    'modules' => $modules,           // For "Posting" (from Modules table)
    'user' => $user
]);
