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

// Get all modules (for main dashboard, we show all modules)
$modules_query = "SELECT * FROM modules";
$stmt = $conn->prepare($modules_query);
$stmt->execute();
$modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'modules' => $modules,
    'user' => $user
]);
