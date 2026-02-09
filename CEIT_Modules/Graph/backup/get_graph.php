<?php
require_once('../../db.php');

if (isset($_GET['id'])) {
    $graphId = $_GET['id'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
        $stmt->bind_param('i', $graphId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            $response = [
                'id' => $row['id'],
                'title' => $row['title'],
                'type' => $row['type'],
                'data' => json_decode($row['data'], true)
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Graph not found']);
        }
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Missing graph ID']);
}
?>