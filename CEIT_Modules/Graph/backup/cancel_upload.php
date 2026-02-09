<?php
session_start();
require_once('../../db.php');

// Get tab state parameters
$mainTab = isset($_GET['main']) ? $_GET['main'] : 'upload';
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'upload-graphs';

// Clean up uploaded file if exists in session
if (isset($_SESSION['uploaded_file'])) {
    $fileInfo = $_SESSION['uploaded_file'];
    
    // Delete the uploaded file
    $uploadDir = __DIR__ . '/uploads';
    $filePath = $uploadDir . '/' . $fileInfo['filename'];
    
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Clear session
    unset($_SESSION['uploaded_file']);
}

// Redirect back to graphs page
header("Location: CEIT.php?main=$mainTab&tab=$currentTab");
exit;
?>