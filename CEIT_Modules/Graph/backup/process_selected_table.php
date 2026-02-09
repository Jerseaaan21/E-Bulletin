<?php
session_start();
require_once('../../db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $selectedTable = isset($_POST['selectedTable']) ? intval($_POST['selectedTable']) : 0;
    $chartType = $_POST['chartType'] ?? 'pie';
    
    // Get tab state parameters
    $mainTab = isset($_POST['mainTab']) ? $_POST['mainTab'] : 'upload';
    $currentTab = isset($_POST['currentTab']) ? $_POST['currentTab'] : 'upload-graphs';
    
    // Check if uploaded file info exists in session
    if (!isset($_SESSION['uploaded_file']) || empty($_SESSION['uploaded_file']['tables'])) {
        header("Location: CEIT.php?main=$mainTab&tab=$currentTab");
        exit;
    }
    
    $fileInfo = $_SESSION['uploaded_file'];
    
    // Validate table index
    if ($selectedTable < 0 || $selectedTable >= count($fileInfo['tables'])) {
        $selectedTable = 0; // Default to first table
    }
    
    // Store selected table in session
    $_SESSION['uploaded_file']['selected_table'] = $selectedTable;
    $_SESSION['uploaded_file']['chartType'] = $chartType;
    
    // Redirect back to upload_graph.php to process the selected table
    header("Location: upload_graph.php?main=$mainTab&tab=$currentTab");
    exit;
} else {
    // Redirect to graphs page if accessed directly
    header("Location: CEIT.php?main=upload&tab=upload-graphs");
    exit;
}
?>