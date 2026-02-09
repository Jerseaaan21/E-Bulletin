<?php
// Include database connection (one level up from CEIT_Modules)
require_once '../../db.php';

// Disable error display for JSON responses
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    $graphId = $_GET['id'] ?? 0;

    if ($graphId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    // Get graph details
    $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $graph = $result->fetch_assoc();
        $graph['data'] = json_decode($graph['data'], true);
        echo json_encode(['success' => true, 'graph' => $graph]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Graph not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
