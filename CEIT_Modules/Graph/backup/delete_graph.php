<?php
require_once('../../db.php');
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $graphId = $_POST['graph_id'];
    
    // Get tab state parameters
    $mainTab = isset($_POST['mainTab']) ? $_POST['mainTab'] : 'upload';
    $currentTab = isset($_POST['currentTab']) ? $_POST['currentTab'] : 'upload-graphs';
    
    try {
        // First get the file path to delete the file from Bulletin database
        $stmt = $conn->prepare("SELECT file_path FROM graphs WHERE id = ?");
        $stmt->bind_param('i', $graphId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $fileName = $row['file_path'];
            
            // Construct the full file path
            $uploadDir = __DIR__ . '/uploads';
            $filePath = $uploadDir . '/' . $fileName;
            
            // Debug: Log the file path
            error_log("Attempting to delete file: " . $filePath);
            
            // Delete the file if it exists
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    error_log("File deleted successfully: " . $filePath);
                } else {
                    error_log("Failed to delete file: " . $filePath);
                }
            } else {
                error_log("File does not exist: " . $filePath);
            }
            
            // Delete the database record from Bulletin database
            $stmt = $conn->prepare("DELETE FROM graphs WHERE id = ?");
            $stmt->bind_param('i', $graphId);
            $stmt->execute();
            
            // Redirect back to the graphs page with tab state
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        } else {
            // Redirect back to the graphs page with tab state
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        }
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Delete graph error: " . $e->getMessage());
        
        // Redirect back to the graphs page with tab state
        echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
    }
}
?>