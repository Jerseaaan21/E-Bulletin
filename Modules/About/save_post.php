<?php
session_start();
if (!isset($_SESSION['user_info'])) {
    header("Location: ../../logout.php");
    exit;
}

require_once '../../db.php';

// Get department ID and user ID from session
$deptId = $_SESSION['dept_id'] ?? null;
$userId = $_SESSION['user_info']['id'] ?? null;

if (!$deptId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

// Get module ID for About
$moduleQuery = "SELECT id FROM modules WHERE name = 'About' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? null;

if (!$moduleId) {
    echo json_encode(['success' => false, 'message' => 'About module not found']);
    exit;
}

// Handle JSON requests for order updates
$input = file_get_contents('php://input');
$jsonData = json_decode($input, true);

if ($jsonData && isset($jsonData['action'])) {
    $action = $jsonData['action'];
} else {
    $action = $_POST['action'] ?? '';
}

if ($action === 'add') {
    // Add new post
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';

    if (empty($description) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Description and content are required']);
        exit;
    }

    // Get the next order position
    $orderQuery = "SELECT COALESCE(MAX(order_position), 0) + 1 as next_order FROM department_post WHERE module = ? AND dept_id = ?";
    $orderStmt = $conn->prepare($orderQuery);
    $orderStmt->bind_param("ii", $moduleId, $deptId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $nextOrder = $orderResult->fetch_assoc()['next_order'];

    $query = "INSERT INTO department_post (module, description, content, dept_id, user_id, order_position) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issiii", $moduleId, $description, $content, $deptId, $userId, $nextOrder);

    if ($stmt->execute()) {
        $postId = $conn->insert_id;
        echo json_encode(['success' => true, 'post_id' => $postId, 'order_position' => $nextOrder]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding post: ' . $conn->error]);
    }
} elseif ($action === 'update') {
    // Update existing post
    $postId = $_POST['id'] ?? 0;
    $content = $_POST['content'] ?? '';

    if (empty($postId) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Post ID and content are required']);
        exit;
    }

    // Verify the post belongs to the current department
    $checkQuery = "SELECT id FROM department_post WHERE id = ? AND dept_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $postId, $deptId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Post not found or access denied']);
        exit;
    }

    $query = "UPDATE department_post SET content = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $content, $postId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating post: ' . $conn->error]);
    }
} elseif ($action === 'delete') {
    // Delete post
    $postId = $_POST['id'] ?? 0;

    if (empty($postId)) {
        echo json_encode(['success' => false, 'message' => 'Post ID is required']);
        exit;
    }

    // Verify the post belongs to the current department
    $checkQuery = "SELECT id FROM department_post WHERE id = ? AND dept_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $postId, $deptId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Post not found or access denied']);
        exit;
    }

    $query = "DELETE FROM department_post WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $postId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting post: ' . $conn->error]);
    }
} elseif ($action === 'update_order') {
    // Update order of posts
    $orderData = $jsonData['order_data'] ?? [];

    if (empty($orderData)) {
        echo json_encode(['success' => false, 'message' => 'Order data is required']);
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        $updateQuery = "UPDATE department_post SET order_position = ? WHERE id = ? AND dept_id = ? AND module = ?";
        $updateStmt = $conn->prepare($updateQuery);

        foreach ($orderData as $item) {
            $postId = $item['id'];
            $order = $item['order'];
            
            $updateStmt->bind_param("iiii", $order, $postId, $deptId, $moduleId);
            $updateStmt->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error updating order: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

?>
