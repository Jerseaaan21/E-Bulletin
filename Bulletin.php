<?php
// Start session to get department information
session_start();

// Check access - allow both authenticated users and guests
if (!isset($_SESSION['user_info']) && !isset($_SESSION['guest'])) {
    header("Location: login.php");
    exit();
}

// Get department ID - from session for authenticated users, from URL for guests
if (isset($_SESSION['dept_id'])) {
    // Authenticated user - use session dept_id
    $dept_id = $_SESSION['dept_id'];
} elseif (isset($_SESSION['guest']) && isset($_GET['dept_id'])) {
    // Guest user - use URL parameter
    $dept_id = (int)$_GET['dept_id'];
    // Set in session for consistency
    $_SESSION['dept_id'] = $dept_id;
} else {
    // No valid dept_id found
    header("Location: login.php");
    exit();
}

// Get department information
include "db.php";

// Validate that the department exists
$dept_query = "SELECT acronym, dept_name FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($dept_query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$dept_result = $stmt->get_result();

if ($dept_result->num_rows === 0) {
    // Invalid department ID
    header("Location: login.php");
    exit();
}

$dept = $dept_result->fetch_assoc();
$dept_acronym = $dept['acronym'];
$dept_name = $dept['dept_name'];

// Get module IDs for different types
$modules_query = "SELECT id, name FROM modules";
$modules_result = $conn->query($modules_query);
$modules = [];
while ($row = $modules_result->fetch_assoc()) {
    $modules[$row['name']] = $row['id'];
}

// Fetch announcements data for JavaScript
$announcements = [];
if (isset($modules['Announcements'])) {
    // Get user_id from session if available
    $user_id = isset($_SESSION['user_info']['id']) ? $_SESSION['user_info']['id'] : null;
    
    if ($user_id) {
        // Fetch from main_post table filtered by user_id
        $query = "SELECT * FROM main_post WHERE module = ? AND user_id = ? AND status = 'active' ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $modules['Announcements'], $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $file_path = "uploads/{$dept_acronym}/Announcement/" . $row['file_path'];
            $announcements[] = [
                'file_path' => $file_path,
                'description' => $row['description'],
                'posted_on' => date("F j, Y", strtotime($row['created_at'])),
                'id' => $row['id']
            ];
        }
    }
}

// Fetch memos data for JavaScript
$memos = [];
if (isset($modules['Memos'])) {
    $query = "SELECT * FROM department_post WHERE module = ? AND dept_id = ? AND status = 'Approved' ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $modules['Memos'], $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $file_path = "uploads/{$dept_acronym}/Memo/" . $row['file_path'];
        $memos[] = [
            'file_path' => $file_path,
            'description' => $row['description'],
            'posted_on' => date("F j, Y", strtotime($row['created_at'])),
            'id' => $row['id']
        ];
    }
}

// Fetch GAD data for JavaScript
$gads = [];
if (isset($modules['GAD'])) {
    $query = "SELECT * FROM department_post WHERE module = ? AND dept_id = ? AND status = 'Approved' ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $modules['GAD'], $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $file_path = "uploads/{$dept_acronym}/GAD/" . $row['file_path'];
        $gads[] = [
            'file_path' => $file_path,
            'description' => $row['description'],
            'posted_on' => date("F j, Y", strtotime($row['created_at'])),
            'id' => $row['id']
        ];
    }
}

// Fetch Student Development data for JavaScript
$studentDevs = [];
if (isset($modules['Student Development'])) {
    $query = "SELECT * FROM department_post WHERE module = ? AND dept_id = ? AND status = 'Approved' ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $modules['Student Development'], $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $file_path = "uploads/{$dept_acronym}/Student Development/" . $row['file_path'];
        $studentDevs[] = [
            'file_path' => $file_path,
            'description' => $row['description'],
            'posted_on' => date("F j, Y", strtotime($row['created_at'])),
            'id' => $row['id']
        ];
    }
}

// Fetch About posts data for dynamic About section
$aboutPosts_bulletin = [];
if (isset($modules['About'])) {
    $query = "SELECT * FROM main_post WHERE status = 'active' AND module = ? ORDER BY order_position ASC, id ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $modules['About']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $aboutPosts_bulletin[] = [
            'id' => $row['id'],
            'description' => $row['description'],
            'content' => $row['content'],
            'order_position' => $row['order_position']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($dept_acronym); ?> Bulletin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --base-font-size: 16px;
    }
    html,
    body {
      height: 100%;
      margin: 0;
      overflow: hidden;
      font-size: var(--base-font-size);
    }
    /* Enhanced Responsive font sizing */
    @media (max-width: 1280px) {
      :root {
        --base-font-size: 15px;
      }
    }
    @media (max-width: 1024px) {
      :root {
        --base-font-size: 14px;
      }
    }
    @media (max-width: 768px) {
      :root {
        --base-font-size: 13px;
      }
    }
    @media (max-width: 640px) {
      :root {
        --base-font-size: 12px;
      }
    }
    @media (max-width: 480px) {
      :root {
        --base-font-size: 11px;
      }
    }
    .carousel {
      position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    .carousel-item {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      transition: all 0.5s ease-in-out;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .carousel-item:not(.active) {
      transform: translateY(20px) scale(0.95);
      opacity: 0;
      z-index: 0;
    }
    .carousel-item.active {
      transform: translateY(0) scale(1);
      opacity: 1;
      z-index: 1;
    }
    .content-area {
      height: calc(100% - 2rem);
      overflow: auto;
    }
    .page {
      display: none;
      height: 100%;
      flex-direction: column;
    }
    .page.active {
      display: flex;
    }
    /* Enhanced Marquee */
    .marquee-wrapper {
      display: inline-block;
      white-space: nowrap;
      animation: marquee 25s linear infinite;
    }
    .marquee-content {
      display: inline-block;
      background: linear-gradient(90deg, #ea580c, #f97316, #ea580c);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      font-weight: 800;
      letter-spacing: 1px;
    }
    @keyframes marquee {
      0% {
        transform: translateX(0);
      }
      100% {
        transform: translateX(-50%);
      }
    }
    /* Responsive adjustments for different aspect ratios */
    @media (max-aspect-ratio: 4/3) {
      .main-grid {
        grid-template-columns: 1fr !important;
      }
      .page2-grid {
        grid-template-columns: 1fr !important;
      }
      .page3-grid {
        grid-template-columns: 1fr !important;
      }
    }
    /* Make background images responsive */
    .responsive-bg {
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }
    /* Responsive text sizes */
    .header-title {
      font-size: clamp(0.9rem, 2.5vw, 1.5rem);
    }
    .header-subtitle {
      font-size: clamp(0.7rem, 2vw, 1.2rem);
    }
    .date-time {
      font-size: clamp(0.8rem, 2.2vw, 1.3rem);
    }
    .tenets-text {
      font-size: clamp(0.7rem, 1.8vw, 1.2rem);
    }
    .card-header {
      font-size: clamp(0.8rem, 2vw, 1.1rem);
    }
    .carousel-text {
      font-size: clamp(0.5rem, 1.5vw, 0.8rem);
    }
    /* Tab Styles */
    .tab-container {
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .tab-buttons {
      display: flex;
      background-color: #f3f4f6;
      border-radius: 8px 8px 0 0;
      overflow: hidden;
      flex-wrap: wrap;
    }
    .tab-btn {
      flex: 1;
      min-width: 50px;
      max-height: 50px;
      padding: 5px;
      border: none;
      background: none;
      cursor: pointer;
      font-weight: 300;
      color: #6b7280;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 2px;
      position: relative;
      overflow: hidden;
    }
    .tab-btn:hover {
      background-color: #e5e7eb;
      color: #4b5563;
    }
    .tab-btn.active {
      background-color: white;
      color: #f97316;
      border-bottom: 3px solid #f97316;
      font-weight: bold;
    }
    .tab-btn i {
      font-size: 0.9rem;
    }
    .tab-content {
      flex: 1;
      background-color: white;
      border-radius: 0 0 8px 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .tab-pane {
      display: none;
      height: 100%;
      flex-direction: column;
    }
    .tab-pane.active {
      display: flex;
    }
    /* Announcement Modal Styles */
    .announcement-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      z-index: 1000;
      backdrop-filter: blur(5px);
    }
    .modal-content {
      position: relative;
      background-color: white;
      margin: 2% auto;
      padding: 0;
      width: 95%;
      max-width: 1200px;
      max-height: 90vh;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .modal-header {
      padding: 12px 15px;
      background-color: #f97316;
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-title {
      font-size: 1.2rem;
      font-weight: 600;
    }
    .modal-close {
      font-size: 1.8rem;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .modal-close:hover {
      transform: scale(1.2);
    }
    .modal-body {
      padding: 15px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow: auto;
    }
    .pdf-container {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      max-height: 70vh;
    }
    .pdf-page {
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      max-width: 100%;
      max-height: 100%;
    }
    .modal-footer {
      padding: 12px 15px;
      background-color: #f3f4f6;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }
    .modal-meta {
      font-size: 0.8rem;
      color: #6b7280;
      text-align: center;
    }
    .page-navigation {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .page-nav-btn {
      background-color: #f97316;
      color: white;
      border: none;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background-color 0.2s, transform 0.2s;
    }
    .page-nav-btn:hover {
      background-color: #ea580c;
      transform: scale(1.1);
    }
    .page-nav-btn:disabled {
      background-color: #d1d5db;
      cursor: not-allowed;
      transform: scale(1);
    }
    .page-indicator {
      font-weight: 600;
      color: #4b5563;
      min-width: 80px;
      text-align: center;
      font-size: 0.9rem;
    }
    .loading-spinner {
      border: 4px solid rgba(0, 0, 0, 0.1);
      border-radius: 50%;
      border-top: 4px solid #f97316;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }
      100% {
        transform: rotate(360deg);
      }
    }
    /* Announcement section styling */
    .announcement-section {
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    /* Modal styles for different file types */
    .image-container {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
    }
    .office-viewer {
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      overflow: hidden;
      width: 100%;
      height: 70vh;
    }
    .carousel-container {
      width: 100%;
      height: 100%;
      max-height: 480px;
      overflow: hidden;
    }
    .pdf-preview-area {
      width: 100%;
      height: 380px;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      cursor: pointer;
    }
    canvas {
      max-width: 100%;
      max-height: 100%;
    }
    .view-hint {
      position: absolute;
      bottom: 10px;
      right: 10px;
      background-color: rgba(249, 115, 22, 0.9);
      color: white;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 500;
      opacity: 0;
      transition: opacity 0.2s;
      pointer-events: none;
    }
    .pdf-preview-area:hover .view-hint {
      opacity: 1;
    }
    .loading-indicator {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
    }
    .spinner {
      border: 3px solid rgba(0, 0, 0, 0.1);
      border-radius: 50%;
      border-top: 3px solid #f97316;
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
      margin: 0 auto 10px;
    }
    /* Mobile-specific adjustments */
    @media (max-width: 768px) {
      .pdf-preview-area {
        height: 250px;
      }
      
      .content-area {
        height: calc(100% - 1rem);
      }
      
      .tab-btn {
        max-width: 100px;
        padding: 8px 10px;
        font-size: 0.85rem;
      }
      
      .tab-btn i {
        font-size: 0.8rem;
      }
    }
    
    /* Pie Chart Layout Styles - UPDATED */
    .pie-chart-layout {
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100%;
      gap: 0;
    }

    @media (min-width: 1024px) {
      .pie-chart-layout {
        flex-direction: row;
      }
    }

    .pie-data-table {
      background: #f8fafc;
      border-radius: 8px;
      overflow: visible;
      display: flex;
      flex-direction: column;
    }

    .pie-data-table .table-container {
      flex: 1;
      overflow-y: visible;
      max-height: none !important;
    }

    .pie-data-table table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 0.75rem;
    }

    .pie-data-table th {
      background-color: #f1f5f9;
      position: sticky;
      top: 0;
      z-index: 10;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 0.75rem;
    }

    .pie-data-table td, .pie-data-table th {
      padding: 4px 6px;
      border-bottom: 1px solid #e2e8f0;
      vertical-align: middle;
      font-size: 0.75rem;
    }

    .pie-data-table tr:hover {
      background-color: #f7fafc;
    }

    /* Column widths for better distribution */
    .pie-data-table th:nth-child(1),
    .pie-data-table td:nth-child(1) {
      width: 45%;
      text-align: left;
      padding-left: 12px;
    }

    .pie-data-table th:nth-child(2),
    .pie-data-table td:nth-child(2) {
      width: 20%;
      text-align: center;
    }

    .pie-data-table th:nth-child(3),
    .pie-data-table td:nth-child(3) {
      width: 35%;
      text-align: center;
    }

    .pie-chart-display {
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
      border-radius: 8px;
      overflow: visible;
      flex: 1;
      position: relative;
    }

    .pie-chart-display .chart-container {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: visible;
    }

    /* Main Graph Carousel Styles */
    .graphs-carousel-container {
      height: 100%;
      display: flex;
      flex-direction: column;
      overflow: visible;
    }
    
    .main-graph-carousel {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: visible;
    }
    
    .graph-carousel-container {
      flex: 1;
      position: relative;
      overflow: visible;
    }
    
    .main-carousel-item {
      width: 100%;
      height: 100%;
      position: absolute;
      top: 0;
      left: 0;
    }
    
    .main-carousel-item.active {
      display: flex !important;
    }
    
    .main-carousel-nav {
      flex-shrink: 0;
      padding: 8px 0;
      margin-top: 15px;
      position: relative;
      z-index: 100;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 8px;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 20px;
    }
    
    @media (max-width: 640px) {
      .modal-content {
        width: 98%;
        margin: 5% auto;
      }
      
      .modal-header {
        padding: 10px;
      }
      
      .modal-title {
        font-size: 1rem;
      }
      
      .modal-close {
        font-size: 1.5rem;
      }
      
      .modal-body {
        padding: 10px;
      }
      
      .modal-footer {
        padding: 10px;
      }
      
      .office-viewer {
        height: 60vh;
      }
      
      .pdf-container {
        max-height: 60vh;
      }
      
      .tab-btn {
        min-width: 80px;
        padding: 6px 8px;
        font-size: 0.8rem;
        gap: 2px;
      }

      /* Responsive table adjustments */
      .pie-data-table table {
        font-size: 0.7rem;
      }

      .pie-data-table td, .pie-data-table th {
        padding: 4px 2px;
      }

      .pie-data-table th:nth-child(1),
      .pie-data-table td:nth-child(1) {
        width: 40%;
        padding-left: 8px;
      }

      .pie-data-table th:nth-child(2),
      .pie-data-table td:nth-child(2) {
        width: 25%;
      }

      .pie-data-table th:nth-child(3),
      .pie-data-table td:nth-child(3) {
        width: 35%;
      }
    }
    
    @media (max-width: 480px) {
      .header-title {
        font-size: 0.9rem;
      }
      
      .header-subtitle {
        font-size: 0.7rem;
      }
      
      .date-time {
        font-size: 0.8rem;
      }
      
      .tenets-text {
        font-size: 0.7rem;
      }
      
      .card-header {
        font-size: 0.8rem;
      }
      
      .carousel-text {
        font-size: 0.6rem;
      }
      
      .tab-btn {
        min-width: 50px;
        padding: 2px;
        font-size: 0.7rem;
      }
      
      .tab-btn span {
        display: none;
      }
      
      .tab-btn i {
        font-size: 0.9rem;
      }

      /* Further table adjustments for very small screens */
      .pie-data-table table {
        font-size: 0.65rem;
      }

      .pie-data-table td, .pie-data-table th {
        padding: 3px 1px;
      }

      .pie-chart-layout {
        flex-direction: column;
        height: auto;
      }

      .pie-data-table, .pie-chart-display {
        width: 100%;
        height: 300px;
      }
    }
    
    @media (max-width: 360px) {
      .tab-btn {
        min-width: 60px;
      }
      
      .tab-btn i {
        font-size: 0.8rem;
      }

      .pie-data-table table {
        font-size: 0.6rem;
      }
    }

    /* Graph container adjustments */
    .graph-container, .group-graph-container {
      height: 100%;
      display: flex;
      flex-direction: column;
      overflow: visible;
    }

    /* Ensure nested carousel nav is visible */
    .nested-carousel-nav {
      position: relative;
      z-index: 100;
      margin-top: 10px;
      padding: 8px;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 8px;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 15px;
    }

    /* Progress bar styling */
    .pie-data-table .progress-bar-container {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .pie-data-table .progress-bar {
      flex-grow: 1;
      max-width: 120px;
      background-color: #e5e7eb;
      border-radius: 9999px;
      height: 8px;
      overflow: hidden;
    }

    .pie-data-table .progress-bar-fill {
      height: 100%;
      background-color: #f97316;
      border-radius: 9999px;
    }
  </style>
</head>
<body class="bg-orange-500 p-2 font-sans h-full flex flex-col">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row justify-between items-center bg-gradient-to-r from-orange-700 to-orange-800 p-3 rounded-xl mb-2 text-white shadow-xl fade-in">
    <div class="text-left flex items-center mb-2 sm:mb-0">
      <div class="bg-white/20 p-2 sm:p-3 rounded-full mr-2 sm:mr-4 pulse">
        <i class="fas fa-university text-lg sm:text-2xl"></i>
      </div>
      <div>
        <div class="header-title font-bold">Cavite State University</div>
        <div class="header-subtitle text-orange-200"><?php echo htmlspecialchars($dept_name); ?></div>
      </div>
    </div>
    <div class="text-right">
      <div id="date" class="date-time font-semibold"></div>
      <div id="time" class="date-time font-semibold"></div>
    </div>
  </div>
  
  <!-- Tenets -->
  <div class="bg-white/90 backdrop-blur-sm text-center font-bold text-black py-2 sm:py-3 mb-2 rounded-xl overflow-hidden relative h-12 sm:h-16 flex items-center shadow-lg slide-up">
    <div class="marquee-wrapper whitespace-nowrap">
      <div class="marquee-content inline-block tenets-text">
        TRUTH • EXCELLENCE • SERVICE &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        ENGINEERING INNOVATION • DIGITAL TRANSFORMATION • TECH EXCELLENCE &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
      </div>
      <div class="marquee-content inline-block tenets-text" aria-hidden="true">
        TRUTH • EXCELLENCE • SERVICE &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        ENGINEERING INNOVATION • DIGITAL TRANSFORMATION • TECH EXCELLENCE &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
      </div>
    </div>
  </div>
  
  <!-- Page 1 -->
  <div id="page1" class="page active">
    <main class="grid grid-cols-1 lg:grid-cols-3 gap-2 p-1 font-bold text-center flex-grow main-grid">
      <!-- Mandates and Accreditation -->
      <div class="flex flex-col space-y-2 h-full">
        <!-- About CvSU - Dynamic Content -->
        <div class="bg-white rounded-lg shadow-md p-2 flex-1 overflow-auto transition duration-500 transform hover:scale-[1.02]">
          <div class="h-full flex flex-col">
            <div class="card-header mb-2 text-orange-600 flex items-center p-2 border-b">
              <i class="fas fa-landmark mr-2"></i> About CvSU
            </div>
            <div class="carousel mandates-carousel flex-grow relative">
              <?php if (count($aboutPosts_bulletin) > 0): ?>
                <?php foreach ($aboutPosts_bulletin as $index => $post): ?>
                  <div class="carousel-item <?= $index === 0 ? 'active' : '' ?> h-full">
                    <div class="h-full flex items-center justify-center bg-gray-100 responsive-bg" style="background-image: url('images/bg-small.png');">
                      <div class="carousel-text p-2 sm:p-3" style="margin: 5px 10px; background-color: rgba(240, 240, 240, 0.8); border-radius: 6px; line-height: 1.5; max-width: 90%; max-height: 90%; overflow-y: auto;">
                        <strong><?= htmlspecialchars($post['description']) ?></strong><br><br>
                        <?= nl2br(htmlspecialchars($post['content'])) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- Fallback to static content if no dynamic posts available -->
                <div class="carousel-item active h-full">
                  <div class="h-full flex items-center justify-center bg-gray-100 responsive-bg" style="background-image: url('images/bg-small.png');">
                    <div class="carousel-text p-2 sm:p-3" style="margin: 5px 10px; background-color: rgba(240, 240, 240, 0.8); border-radius: 6px; line-height: 1.5;">
                      MISSION <br><br>
                      The Cavite State University shall provide excellent, equitable and relevant educational opportunities in the arts, sciences and technology through quality instruction and relevant research and development activities.
                    </div>
                  </div>
                </div>
                <div class="carousel-item h-full">
                  <div class="h-full flex items-center justify-center bg-gray-100 responsive-bg" style="background-image: url('images/bg-small.png');">
                    <div class="carousel-text p-2 sm:p-3" style="margin: 5px 10px; background-color: rgba(240, 240, 240, 0.8); border-radius: 6px; line-height: 1.5;">
                      VISION <br><br>
                      The premier university in historic Cavite recognized for excellence in the development of morally upright and globally competitive individuals.
                    </div>
                  </div>
                </div>
                <div class="carousel-item h-full">
                  <div class="h-full flex items-center justify-center bg-gray-100 responsive-bg" style="background-image: url('images/bg-small.png');">
                    <div class="carousel-text p-2 sm:p-3" style="margin: 5px 10px; background-color: rgba(240, 240, 240, 0.8); border-radius: 6px; line-height: 1.5;">
                      CORE VALUES <br><br>
                      Excellence, Integrity, Patriotism, Service, Social Responsibility, and Commitment
                    </div>
                  </div>
                </div>
                <div class="carousel-item h-full">
                  <div class="h-full flex items-center justify-center bg-gray-100 responsive-bg" style="background-image: url('images/bg-small.png');">
                    <div class="carousel-text p-2 sm:p-3" style="margin: 5px 10px; background-color: rgba(240, 240, 240, 0.8); border-radius: 6px; line-height: 1.5;">
                      QUALITY POLICY <br><br>
                      Cavite State University is committed to provide quality education and related services through continuous improvement of its programs and services to satisfy stakeholder requirements and applicable regulatory requirements.
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <div class="flex justify-center space-x-2 mt-2">
              <button class="mandates-prev-btn bg-orange-500 text-white p-1 sm:p-2 rounded-full hover:bg-orange-600 transition duration-200 transform hover:scale-110">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button class="mandates-next-btn bg-orange-500 text-white p-1 sm:p-2 rounded-full hover:bg-orange-600 transition duration-200 transform hover:scale-110">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
        
        <!-- Accreditation & Strategic Plan Tabs -->
        <div class="bg-white rounded-lg shadow-md p-2 flex-1 overflow-hidden transition duration-200 transform hover:scale-[1.02]">
          <div class="tab-container">
            <!-- Tab Buttons -->
            <div class="tab-buttons text-xs font-fold">
              <button class="tab-btn active" data-tab="accreditation">
                <i class="fas fa-award"></i>
                <span>Accreditation</span>
              </button>
              <button class="tab-btn" data-tab="strategic-plan">
                <i class="fas fa-clipboard-list"></i>
                <span>Strategic Plan</span>
              </button>
            </div>
            
            <!-- Tab Content -->
            <div class="tab-content">
              <!-- Accreditation Tab -->
              <div class="tab-pane active" id="accreditation-tab">
                <iframe src="CEIT_Modules/Accreditation_Status/ViewStatus.php" 
                        style="width: 100%; height: 100%; border: none;" 
                        frameborder="0">
                </iframe>
              </div>
              
              <!-- Strategic Plan Tab -->
              <div class="tab-pane" id="strategic-plan-tab">
                <iframe src="CEIT_Modules/Institutional_Development/InstitutionalDevelopmentBulletin.php?category=strategic" 
                        style="width: 100%; height: 100%; border: none;" 
                        frameborder="0">
                </iframe>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Announcement & Memos Tabs -->
      <div class="bg-white rounded-lg shadow-md p-2 sm:p-4 overflow-hidden transition duration-500 transform hover:scale-[1.02]">
        <div class="tab-container">
          <!-- Tab Buttons -->
          <div class="tab-buttons text-xs font-fold">
            <button class="tab-btn active" data-tab="announcements">
              <i class="fas fa-bullhorn"></i>
              <span>Announcements</span>
            </button>
            <button class="tab-btn" data-tab="memos">
              <i class="fas fa-file-alt"></i>
              <span>Memos</span>
            </button>
          </div>
          
          <!-- Tab Content -->
          <div class="tab-content">
            <!-- Announcements Tab -->
            <div class="tab-pane active" id="announcements-tab">
              <iframe src="CEIT_Modules/Announcements/AnnouncementBulletin.php" 
                      style="width: 100%; height: 100%; border: none;" 
                      frameborder="0">
              </iframe>
            </div>
            
            <!-- Memos Tab -->
            <div class="tab-pane" id="memos-tab">
              <iframe src="CEIT_Modules/Memo/MemoBulletin.php" 
                      style="width: 100%; height: 100%; border: none;" 
                      frameborder="0">
              </iframe>
            </div>
          </div>
        </div>
      </div>
      
      <!-- GAD & Student Development Tabs -->
      <div class="bg-white rounded-lg shadow-md p-2 sm:p-4 overflow-hidden transition duration-500 transform hover:scale-[1.02]">
        <div class="tab-container">
          <!-- Tab Buttons -->
          <div class="tab-buttons text-xs font-fold">
            <button class="tab-btn active" data-tab="gad">
              <i class="fas fa-users"></i>
              <span> Gender and Development</span>
            </button>
            <button class="tab-btn" data-tab="student-dev">
              <i class="fas fa-graduation-cap"></i>
              <span>Student Development</span>
            </button>
          </div>
          
          <!-- Tab Content -->
          <div class="tab-content">
            <!-- GAD Tab -->
            <div class="tab-pane active" id="gad-tab">
              <iframe src="CEIT_Modules/Institutional_Development/InstitutionalDevelopmentBulletin.php?category=gender" 
                      style="width: 100%; height: 100%; border: none;" 
                      frameborder="0">
              </iframe>
            </div>
            
            <!-- Student Development Tab -->
            <div class="tab-pane" id="student-dev-tab">
              <iframe src="CEIT_Modules/Institutional_Development/InstitutionalDevelopmentBulletin.php?category=student" 
                      style="width: 100%; height: 100%; border: none;" 
                      frameborder="0">
              </iframe>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Page 2 -->
  <div id="page2" class="page">
    <main class="grid grid-cols-1 lg:grid-cols-2 gap-2 p-1 font-bold text-center flex-grow page2-grid">
      <!-- Calendar -->
      <div class="bg-white rounded-lg shadow-md p-2 sm:p-4 overflow-auto transition duration-500 transform hover:scale-[1.02]">
        <div class="card-header mb-2 text-orange-600 flex items-center border-b pb-2">
          <i class="fas fa-calendar-alt mr-2"></i> Academic Calendar
        </div>
        <div class="overflow-x-auto">
          <?php include '../../Modules/Calendar/CalendarView.php'; ?>
        </div>
      </div>

      <!-- Organizational Structure -->
      <div class="rounded-lg shadow-md sm:p-4 transition duration-500 transform hover:scale-[1.02] relative overflow-hidden bg-white">
        <div class="relative z-10">
          <div class="card-header mb-1 text-orange-600 flex items-center border-b pb-2">
            <i class="fas fa-sitemap mr-2"></i> <?php echo htmlspecialchars($dept_name); ?> Organizational Structure
          </div>
          <div class="content-area">
            <!-- Org Chart from ViewChart.php -->
            <div class="bg-white rounded-lg h-full overflow-auto">
              <?php include 'CEIT_Modules/Organizational_Chart/ViewChart.php'; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Page 3 -->
  <div id="page3" class="page">
    <main class="grid grid-cols-1 gap-2 p-1 font-bold text-center flex-grow page3-grid">
      <!-- Graphs -->
      <div class="bg-white rounded-lg shadow-md p-2 sm:p-4 transition duration-500 transform hover:scale-[1.02]">
        <div class="card-header mb-2 text-orange-600 flex items-center border-b pb-2">
          <i class="fas fa-chart-pie mr-2"></i> <?php echo htmlspecialchars($dept_name); ?> Performance Metrics
        </div>
        <div class="content-area">
          <?php
          // Include department-specific graph bulletin
          $graph_bulletin_path = null;
          
          // Determine which graph bulletin to include based on department
          if (strtoupper($dept_acronym) === 'CEIT') {
              $graph_bulletin_path = 'CEIT_Modules/Graph/GraphBulletin.php';
          } elseif (file_exists("Modules/Graph/GraphBulletin.php")) {
              // For other departments, use the general modules path
              $graph_bulletin_path = 'Modules/Graph/GraphBulletin.php';
          }
          
          if ($graph_bulletin_path && file_exists($graph_bulletin_path)) {
              include $graph_bulletin_path;
              
              // Get the department-specific graphs variable
              $dept_graphs = isset($ceit_bulletin_graphs_var) ? $ceit_bulletin_graphs_var : 
                           (isset($general_bulletin_graphs_var) ? $general_bulletin_graphs_var : 
                           ['individual' => [], 'group' => []]);
              
              $total_graphs = count($dept_graphs['individual']) + count($dept_graphs['group']);
              
              // Debug output
              echo "<!-- Debug: Department: $dept_acronym, Total graphs: $total_graphs -->";
              echo "<!-- Debug: Individual: " . count($dept_graphs['individual']) . ", Group: " . count($dept_graphs['group']) . " -->";
              echo "<!-- Debug: Functions available - renderBulletinGraph: " . (function_exists('renderBulletinGraph') ? 'YES' : 'NO') . " -->";
              echo "<!-- Debug: Functions available - renderBulletinGroupGraph: " . (function_exists('renderBulletinGroupGraph') ? 'YES' : 'NO') . " -->";
              
              if ($total_graphs > 0): ?>
                <!-- JavaScript Test for Chart.js -->
                <script>
                console.log('=== BULLETIN CHART.JS TEST ===');
                console.log('Chart.js available:', typeof Chart !== 'undefined');
                console.log('Total graphs to render:', <?php echo $total_graphs; ?>);
                console.log('Individual graphs:', <?php echo count($dept_graphs['individual']); ?>);
                console.log('Group graphs:', <?php echo count($dept_graphs['group']); ?>);
                </script>
                
                <div class="graphs-carousel-container h-full">
                  <!-- Main Graph Carousel -->
                  <div class="main-graph-carousel relative h-full">
                    <div class="graph-carousel-container h-full">
                      <?php 
                      $graph_index = 0;
                      
                      // Display individual graphs
                      foreach ($dept_graphs['individual'] as $graph): 
                        $isActive = $graph_index === 0 ? 'active' : '';
                        $containerId = 'main_graph_' . $graph_index;
                        $graphId = 'bulletin_graph_' . $graph['id'] . '_' . uniqid();
                        
                        echo "<!-- Debug: Rendering individual graph {$graph_index}: {$graph['description']} -->";
                      ?>
                        <div class="main-carousel-item <?php echo $isActive; ?>" data-index="<?php echo $graph_index; ?>" style="display: <?php echo $isActive ? 'flex' : 'none'; ?>; flex-direction: column;">
                          <div class='graph-container' id='<?php echo $containerId; ?>'>
                            <div class='graph-header text-center mb-2'>
                              <h4 class='text-lg font-semibold text-gray-800'><?php echo htmlspecialchars($graph['description']); ?></h4>
                            </div>
                            
                            <?php if ($graph['graph_type'] === 'pie'): ?>
                              <!-- Pie Chart Layout - Table on Left, Chart on Right -->
                              <div class='pie-chart-layout flex flex-col lg:flex-row' style='min-height: 400px;'>
                                <!-- Left side: Data Table -->
                                <div class='pie-data-table w-full lg:w-2/5 p-3 border-r border-gray-200'>
                                  <div class='table-container bg-white rounded-lg shadow-inner p-3'>
                                    <h5 class='text-sm font-semibold text-gray-700 mb-2 border-b pb-2 text-center'>Data Details</h5>
                                    
                                    <?php 
                                    $labels = $graph['data']['labels'] ?? [];
                                    $values = $graph['data']['values'] ?? [];
                                    $colors = $graph['data']['colors'] ?? ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
                                    
                                    if (count($labels) > 0 && count($values) > 0):
                                      // Calculate totals for percentages
                                      $total = array_sum($values);
                                    ?>
                                      <table class='w-full text-xs'>
                                        <thead class='bg-gray-50'>
                                          <tr>
                                            <th class='py-1 px-2 text-left font-medium text-gray-700 text-xs'>Category</th>
                                            <th class='py-1 px-2 text-center font-medium text-gray-700 text-xs'>Value</th>
                                            <th class='py-1 px-2 text-center font-medium text-gray-700 text-xs'>Percentage</th>
                                          </tr>
                                        </thead>
                                        <tbody class='divide-y divide-gray-100'>
                                          <?php for ($i = 0; $i < count($labels); $i++): 
                                            $value = $values[$i];
                                            $percentage = $total > 0 ? round(($value / $total) * 100, 2) : 0;
                                            $color = $colors[$i % count($colors)];
                                          ?>
                                            <tr class='hover:bg-gray-50'>
                                              <td class='py-1 px-2 text-xs'>
                                                <div class='flex items-center'>
                                                  <div class='w-2 h-2 rounded-full mr-1 flex-shrink-0' style='background-color: <?php echo $color; ?>'></div>
                                                  <span class='text-xs'><?php echo htmlspecialchars($labels[$i]); ?></span>
                                                </div>
                                              </td>
                                              <td class='py-1 px-2 font-medium text-center text-xs'><?php echo number_format($value, 2); ?></td>
                                              <td class='py-1 px-2 text-center text-xs'>
                                                <div class='flex items-center justify-center'>
                                                  <span class='mr-1 text-xs'><?php echo $percentage; ?>%</span>
                                                  <div class='w-12 bg-gray-200 rounded-full h-1.5'>
                                                    <div class='bg-orange-500 h-1.5 rounded-full' style='width: <?php echo $percentage; ?>%'></div>
                                                  </div>
                                                </div>
                                              </td>
                                            </tr>
                                          <?php endfor; ?>
                                          
                                          <!-- Total row -->
                                          <tr class='bg-gray-50 font-semibold'>
                                            <td class='py-1 px-2 text-xs'>Total</td>
                                            <td class='py-1 px-2 text-center text-xs'><?php echo number_format($total, 2); ?></td>
                                            <td class='py-1 px-2 text-center text-xs'>100%</td>
                                          </tr>
                                        </tbody>
                                      </table>
                                    <?php else: ?>
                                      <div class='text-center p-8 text-gray-500'>
                                        <i class='fas fa-chart-pie text-3xl mb-3'></i>
                                        <p>No data available for this pie chart</p>
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                </div>
                                
                                <!-- Right side: Pie Chart -->
                                <div class='pie-chart-display w-full lg:w-3/5 p-3'>
                                  <div class='chart-container bg-white rounded-lg shadow-inner p-2'>
                                    <div style='width: 100%; height: 400px; position: relative;'>
                                      <canvas id='<?php echo $graphId; ?>' style='width: 100% !important; height: 100% !important;'></canvas>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            <?php else: ?>
                              <!-- For non-pie charts (bar charts) -->
                              <div class='graph-canvas-container' style='height: 400px; position: relative; display: flex; align-items: stretch; justify-content: center; padding: 10px; overflow: visible;'>
                                <canvas id='<?php echo $graphId; ?>' style='width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%; display: block;'></canvas>
                              </div>
                            <?php endif; ?>
                          </div>
                          
                          <script>
                          document.addEventListener('DOMContentLoaded', function() {
                            console.log('=== RENDERING INDIVIDUAL GRAPH <?php echo $graph_index; ?> ===');
                            console.log('Graph ID: <?php echo $graph['id']; ?>, Type: <?php echo $graph['graph_type']; ?>');
                            console.log('Canvas ID: <?php echo $graphId; ?>');
                            
                            const canvas = document.getElementById('<?php echo $graphId; ?>');
                            if (!canvas) {
                              console.error('Canvas not found: <?php echo $graphId; ?>');
                              return;
                            }
                            
                            const ctx = canvas.getContext('2d');
                            const graphData = <?php echo json_encode($graph['data']); ?>;
                            console.log('Graph data:', graphData);
                            
                            let chartInstance; // Store chart instance
                            
                            <?php if ($graph['graph_type'] === 'pie'): ?>
                              const labels = graphData.labels || [];
                              const values = graphData.values || [];
                              const colors = graphData.colors || ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
                              
                              console.log('Pie chart - Labels:', labels, 'Values:', values);
                              
                              if (labels.length > 0 && values.length > 0) {
                                chartInstance = new Chart(ctx, {
                                  type: 'pie',
                                  data: {
                                    labels: labels,
                                    datasets: [{
                                      data: values,
                                      backgroundColor: colors.slice(0, values.length),
                                      borderWidth: 2,
                                      borderColor: '#fff',
                                      hoverOffset: 15,
                                      hoverBorderWidth: 3
                                    }]
                                  },
                                  options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    animation: {
                                      animateRotate: true,
                                      animateScale: true,
                                      duration: 1200
                                    },
                                    layout: {
                                      padding: {
                                        top: 10,
                                        bottom: 10,
                                        left: 10,
                                        right: 3
                                      }
                                    },
                                    plugins: {
                                      legend: { 
                                        position: 'right',
                                        align: 'center',
                                        maxWidth: 250,
                                        labels: { 
                                          boxWidth: 10,
                                          font: { size: 9 },
                                          padding: 4,
                                          usePointStyle: true
                                        }
                                      },
                                      tooltip: {
                                        callbacks: {
                                          label: function(context) {
                                            let label = context.label || '';
                                            if (label) {
                                              label += ': ';
                                            }
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const value = context.raw;
                                            const percentage = Math.round((value / total) * 100);
                                            label += value + ' (' + percentage + '%)';
                                            return label;
                                          }
                                        }
                                      }
                                    }
                                  }
                                });
                                console.log('Pie chart created successfully');
                              } else {
                                console.warn('Empty pie chart data');
                                canvas.parentElement.innerHTML = '<div class="text-center p-4"><p class="text-gray-500">No pie data available</p></div>';
                              }
                            <?php elseif ($graph['graph_type'] === 'bar'): ?>
                              let categories = graphData.categories || [];
                              let values = graphData.values || [];
                              let seriesLabels = graphData.seriesLabels || ['Data'];
                              let seriesColors = graphData.seriesColors || ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'];
                              
                              console.log('Bar chart parsed - Categories:', categories, 'Values:', values, 'Labels:', seriesLabels);
                              
                              if (categories.length > 0 && values.length > 0) {
                                let datasets = [];
                                
                                // Check if we have multiple series (2D array)
                                if (Array.isArray(values) && values.length > 0 && Array.isArray(values[0])) {
                                  console.log('Multiple series detected');
                                  const numSeries = values[0].length;
                                  for (let seriesIndex = 0; seriesIndex < numSeries; seriesIndex++) {
                                    const seriesData = values.map(row => parseFloat(row[seriesIndex]) || 0);
                                    datasets.push({
                                      label: seriesLabels[seriesIndex] || 'Series ' + (seriesIndex + 1),
                                      data: seriesData,
                                      backgroundColor: seriesColors[seriesIndex % seriesColors.length],
                                      borderColor: seriesColors[seriesIndex % seriesColors.length],
                                      borderWidth: 1
                                    });
                                  }
                                } else {
                                  console.log('Single series detected');
                                  const seriesData = Array.isArray(values) ? values.map(v => parseFloat(v) || 0) : [];
                                  datasets.push({
                                    label: seriesLabels[0] || 'Data',
                                    data: seriesData,
                                    backgroundColor: seriesColors[0] || '#36A2EB',
                                    borderColor: seriesColors[0] || '#36A2EB',
                                    borderWidth: 1
                                  });
                                }
                                
                                console.log('Final datasets:', datasets);
                                
                                new Chart(ctx, {
                                  type: 'bar',
                                  data: {
                                    labels: categories,
                                    datasets: datasets
                                  },
                                  options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    animation: {
                                      duration: 1200
                                    },
                                    plugins: {
                                      legend: { 
                                        display: datasets.length > 1,
                                        position: 'top',
                                        labels: { boxWidth: 12, font: { size: 10 } }
                                      }
                                    },
                                    scales: {
                                      y: { 
                                        beginAtZero: true,
                                        ticks: { font: { size: 10 } }
                                      },
                                      x: {
                                        ticks: { font: { size: 9 }, maxRotation: 0, minRotation: 0 }
                                      }
                                    }
                                  }
                                });
                                console.log('Bar chart created successfully');
                                
                                // Store chart instance globally
                                window['chart_<?php echo $graphId; ?>'] = chartInstance;
                              } else {
                                console.warn('Empty bar chart data');
                                canvas.parentElement.innerHTML = '<div class="text-center p-4"><p class="text-gray-500">No bar data available</p></div>';
                              }
                            <?php endif; ?>
                            
                            console.log('=== END INDIVIDUAL GRAPH <?php echo $graph_index; ?> ===');
                          });
                          </script>
                        </div>
                      <?php 
                        $graph_index++;
                      endforeach; 
                      
                      // Display group graphs
                      foreach ($dept_graphs['group'] as $groupGraph): 
                        $isActive = $graph_index === 0 ? 'active' : '';
                        $containerId = 'main_group_graph_' . $graph_index;
                        $groupId = 'group_' . $groupGraph['id'];
                        $graphs = $groupGraph['data']['graphs'] ?? [];
                        
                        echo "<!-- Debug: Rendering group graph {$graph_index}: {$groupGraph['description']} -->";
                      ?>
                        <div class="main-carousel-item <?php echo $isActive; ?>" data-index="<?php echo $graph_index; ?>" style="display: <?php echo $isActive ? 'flex' : 'none'; ?>; flex-direction: column;">
                          <div class='group-graph-container' id='<?php echo $containerId; ?>' data-group-id='<?php echo $groupId; ?>'>
                            <div class='graph-header text-center mb-2'>
                              <h4 class='text-lg font-semibold text-gray-800'><?php echo htmlspecialchars($groupGraph['description']); ?></h4>
                            </div>
                            
                            <?php if (!empty($graphs)): ?>
                              <div class='nested-carousel relative' data-group-id='<?php echo $groupId; ?>'>
                                <div class='nested-carousel-container overflow-hidden' style='height: 420px;'>
                                  <?php foreach ($graphs as $index => $graph): 
                                    $isNestedActive = $index === 0 ? 'active' : '';
                                    $nestedGraphId = $groupId . '_graph_' . $index;
                                    $graphType = $graph['type'] ?? 'pie';
                                  ?>
                                    <div class='nested-carousel-item <?php echo $isNestedActive; ?>' data-index='<?php echo $index; ?>' style='display: <?php echo $isNestedActive ? 'flex' : 'none'; ?>; position: absolute; top: 0; left: 0; width: 100%; height: 100%; flex-direction: column;'>
                                      <div class='nested-graph-header text-center mb-2'>
                                        <h5 class='text-md font-medium text-gray-700'><?php echo htmlspecialchars($graph['title'] ?? 'Graph ' . ($index + 1)); ?></h5>
                                      </div>
                                      
                                      <?php if ($graphType === 'pie'): ?>
                                        <!-- Nested Pie Chart Layout -->
                                        <div class='pie-chart-layout flex flex-col md:flex-row' style='height: calc(100% - 40px);'>
                                          <!-- Left side: Data Table -->
                                          <div class='pie-data-table w-full md:w-1/2 p-2 border-r border-gray-200'>
                                            <div class='table-container bg-white rounded-lg shadow-inner p-2'>
                                              <h6 class='text-xs font-semibold text-gray-700 mb-1 border-b pb-1 text-center'>Data Details</h6>
                                              
                                              <?php 
                                              // Get data
                                              $labels = $graph['labels'] ?? $graph['pieLabels'] ?? $graph['categories'] ?? [];
                                              $values = $graph['values'] ?? $graph['pieValues'] ?? $graph['data'] ?? [];
                                              $colors = $graph['colors'] ?? $graph['pieColors'] ?? $graph['backgroundColor'] ?? ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
                                              
                                              // Try nested data structure
                                              if (empty($labels) && isset($graph['data']) && is_array($graph['data'])) {
                                                $nestedData = $graph['data'];
                                                $labels = $nestedData['labels'] ?? $nestedData['categories'] ?? [];
                                                $values = $nestedData['values'] ?? $nestedData['data'] ?? [];
                                                $colors = $nestedData['colors'] ?? $colors;
                                              }
                                              
                                              if (count($labels) > 0 && count($values) > 0):
                                                $total = array_sum($values);
                                              ?>
                                                <table class='w-full' style='font-size: 0.6rem;'>
                                                  <thead class='bg-gray-50'>
                                                    <tr>
                                                      <th class='text-left font-medium text-gray-700' style='font-size: 0.6rem; padding: 2px 4px;'>Category</th>
                                                      <th class='text-center font-medium text-gray-700' style='font-size: 0.6rem; padding: 2px 4px;'>Value</th>
                                                      <th class='text-center font-medium text-gray-700' style='font-size: 0.6rem; padding: 2px 4px;'>Percentage</th>
                                                    </tr>
                                                  </thead>
                                                  <tbody class='divide-y divide-gray-100'>
                                                    <?php for ($i = 0; $i < count($labels); $i++): 
                                                      $value = $values[$i] ?? 0;
                                                      $percentage = $total > 0 ? round(($value / $total) * 100, 1) : 0;
                                                      $color = $colors[$i % count($colors)];
                                                    ?>
                                                      <tr class='hover:bg-gray-50'>
                                                        <td style='font-size: 0.6rem; padding: 2px 4px; line-height: 1.1;'>
                                                          <div class='flex items-center'>
                                                            <div class='w-1.5 h-1.5 rounded-full flex-shrink-0' style='background-color: <?php echo $color; ?>; margin-right: 3px;'></div>
                                                            <span style='font-size: 0.6rem;'><?php echo htmlspecialchars($labels[$i]); ?></span>
                                                          </div>
                                                        </td>
                                                        <td class='font-medium text-center' style='font-size: 0.6rem; padding: 2px 4px;'><?php echo number_format($value, 1); ?></td>
                                                        <td class='text-center' style='font-size: 0.6rem; padding: 2px 4px;'>
                                                          <div class='flex items-center justify-center'>
                                                            <span style='margin-right: 3px; font-size: 0.6rem;'><?php echo $percentage; ?>%</span>
                                                            <div style='width: 40px; background-color: #e5e7eb; border-radius: 9999px; height: 6px; overflow: hidden;'>
                                                              <div style='height: 100%; background-color: #f97316; border-radius: 9999px; width: <?php echo $percentage; ?>%'></div>
                                                            </div>
                                                          </div>
                                                        </td>
                                                      </tr>
                                                    <?php endfor; ?>
                                                    
                                                    <!-- Total row -->
                                                    <tr class='bg-gray-50 font-semibold'>
                                                      <td style='font-size: 0.6rem; padding: 2px 4px;'>Total</td>
                                                      <td class='text-center' style='font-size: 0.6rem; padding: 2px 4px;'><?php echo number_format($total, 1); ?></td>
                                                      <td class='text-center' style='font-size: 0.6rem; padding: 2px 4px;'>100%</td>
                                                    </tr>
                                                  </tbody>
                                                </table>
                                              <?php else: ?>
                                                <div class='text-center p-4 text-gray-400 text-sm'>
                                                  <i class='fas fa-chart-pie text-lg mb-2'></i>
                                                  <p>No data available</p>
                                                </div>
                                              <?php endif; ?>
                                            </div>
                                          </div>
                                          
                                          <!-- Right side: Pie Chart -->
                                          <div class='pie-chart-display w-full md:w-1/2 p-2'>
                                            <div class='chart-container bg-white rounded-lg shadow-inner p-2 flex items-center justify-center'>
                                              <div style='width: 100%; height: 330px; position: relative;'>
                                                <canvas id='<?php echo $nestedGraphId; ?>' style='width: 100% !important; height: 100% !important;'></canvas>
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                      <?php else: ?>
                                        <!-- For non-pie charts -->
                                        <div class='nested-graph-canvas' style='height: 380px; position: relative; display: flex; align-items: center; justify-content: center; overflow: visible;'>
                                          <canvas id='<?php echo $nestedGraphId; ?>' style='width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%;'></canvas>
                                        </div>
                                      <?php endif; ?>
                                    </div>
                                  <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($graphs) > 1): ?>
                                  <div class='nested-carousel-nav flex justify-center items-center mt-2 space-x-2'>
                                    <button class='nested-prev-btn bg-orange-500 text-white p-1 rounded-full hover:bg-orange-600 transition-colors' onclick='prevNestedGraph("<?php echo $groupId; ?>")'>
                                      <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 19l-7-7 7-7'></path>
                                      </svg>
                                    </button>
                                    <span class='nested-indicator text-sm text-gray-600'>1 of <?php echo count($graphs); ?></span>
                                    <button class='nested-next-btn bg-orange-500 text-white p-1 rounded-full hover:bg-orange-600 transition-colors' onclick='nextNestedGraph("<?php echo $groupId; ?>")'>
                                      <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 5l7 7-7 7'></path>
                                      </svg>
                                    </button>
                                  </div>
                                <?php endif; ?>
                              </div>
                              
                              <script>
                              document.addEventListener('DOMContentLoaded', function() {
                                console.log('=== RENDERING GROUP GRAPH <?php echo $graph_index; ?> ===');
                                console.log('Group ID: <?php echo $groupGraph['id']; ?>, Nested graphs: <?php echo count($graphs); ?>');
                                
                                <?php foreach ($graphs as $index => $graph): 
                                  $nestedGraphId = $groupId . '_graph_' . $index;
                                  $graphType = $graph['type'] ?? 'pie';
                                ?>
                                  console.log('--- Processing nested graph <?php echo $index; ?> ---');
                                  console.log('Canvas ID: <?php echo $nestedGraphId; ?>, Type: <?php echo $graphType; ?>');
                                  
                                  const canvas_<?php echo $index; ?> = document.getElementById('<?php echo $nestedGraphId; ?>');
                                  if (!canvas_<?php echo $index; ?>) {
                                    console.error('Canvas not found: <?php echo $nestedGraphId; ?>');
                                    return;
                                  }
                                  const ctx_<?php echo $index; ?> = canvas_<?php echo $index; ?>.getContext('2d');
                                  
                                  const graphData_<?php echo $index; ?> = <?php echo json_encode($graph); ?>;
                                  console.log('Graph data <?php echo $index; ?>:', graphData_<?php echo $index; ?>);
                                  
                                  <?php if ($graphType === 'pie'): ?>
                                    // Try multiple data key combinations for pie charts
                                    let labels_<?php echo $index; ?> = graphData_<?php echo $index; ?>.labels || graphData_<?php echo $index; ?>.pieLabels || graphData_<?php echo $index; ?>.categories || [];
                                    let values_<?php echo $index; ?> = graphData_<?php echo $index; ?>.values || graphData_<?php echo $index; ?>.pieValues || graphData_<?php echo $index; ?>.data || [];
                                    let colors_<?php echo $index; ?> = graphData_<?php echo $index; ?>.colors || graphData_<?php echo $index; ?>.pieColors || graphData_<?php echo $index; ?>.backgroundColor || ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
                                    
                                    // If still empty, try nested data structure
                                    if (labels_<?php echo $index; ?>.length === 0 && graphData_<?php echo $index; ?>.data && typeof graphData_<?php echo $index; ?>.data === 'object') {
                                      console.log('Trying nested data structure for pie chart <?php echo $index; ?>');
                                      const nestedData = graphData_<?php echo $index; ?>.data;
                                      labels_<?php echo $index; ?> = nestedData.labels || nestedData.categories || [];
                                      values_<?php echo $index; ?> = nestedData.values || [];
                                      colors_<?php echo $index; ?> = nestedData.colors || colors_<?php echo $index; ?>;
                                    }
                                    
                                    console.log('Pie data <?php echo $index; ?> - Labels:', labels_<?php echo $index; ?>, 'Values:', values_<?php echo $index; ?>);
                                    
                                    if (labels_<?php echo $index; ?>.length > 0 && values_<?php echo $index; ?>.length > 0) {
                                      const chart_<?php echo $index; ?> = new Chart(ctx_<?php echo $index; ?>, {
                                        type: 'pie',
                                        data: {
                                          labels: labels_<?php echo $index; ?>,
                                          datasets: [{
                                            data: values_<?php echo $index; ?>.map(v => parseFloat(v) || 0),
                                            backgroundColor: colors_<?php echo $index; ?>.slice(0, values_<?php echo $index; ?>.length),
                                            borderWidth: 1.5,
                                            borderColor: '#fff',
                                            hoverOffset: 10
                                          }]
                                        },
                                        options: {
                                          responsive: true,
                                          maintainAspectRatio: false,
                                          animation: {
                                            animateRotate: true,
                                            animateScale: true,
                                            duration: 1200
                                          },
                                          layout: {
                                            padding: {
                                              top: 5,
                                              bottom: 5,
                                              left: 5,
                                              right: 2
                                            }
                                          },
                                          plugins: {
                                            legend: { 
                                              position: 'right',
                                              align: 'center',
                                              labels: { 
                                                boxWidth: 8,
                                                font: { size: 8 },
                                                padding: 3,
                                                usePointStyle: true
                                              }
                                            }
                                          }
                                        }
                                      });
                                      window['chart_<?php echo $nestedGraphId; ?>'] = chart_<?php echo $index; ?>;
                                      console.log('Nested pie chart <?php echo $index; ?> created successfully');
                                    } else {
                                      console.warn('Empty pie chart data for nested graph <?php echo $index; ?>');
                                      canvas_<?php echo $index; ?>.parentElement.innerHTML = '<div class="text-center p-2"><p class="text-xs text-gray-500">No pie data</p></div>';
                                    }
                                  <?php elseif ($graphType === 'bar'): ?>
                                    // Try multiple data key combinations for bar charts
                                    let categories_<?php echo $index; ?> = graphData_<?php echo $index; ?>.categories || graphData_<?php echo $index; ?>.barCategories || graphData_<?php echo $index; ?>.labels || [];
                                    let values_<?php echo $index; ?> = graphData_<?php echo $index; ?>.values || graphData_<?php echo $index; ?>.barValues || graphData_<?php echo $index; ?>.data || [];
                                    let seriesLabels_<?php echo $index; ?> = graphData_<?php echo $index; ?>.seriesLabels || graphData_<?php echo $index; ?>.barLabels || ['Data'];
                                    let seriesColors_<?php echo $index; ?> = graphData_<?php echo $index; ?>.seriesColors || graphData_<?php echo $index; ?>.barColors || ['#36A2EB'];
                                    
                                    // If still empty, try nested data structure
                                    if (categories_<?php echo $index; ?>.length === 0 && graphData_<?php echo $index; ?>.data && typeof graphData_<?php echo $index; ?>.data === 'object') {
                                      console.log('Trying nested data structure for bar chart <?php echo $index; ?>');
                                      const nestedData = graphData_<?php echo $index; ?>.data;
                                      categories_<?php echo $index; ?> = nestedData.categories || nestedData.labels || [];
                                      values_<?php echo $index; ?> = nestedData.values || [];
                                      seriesLabels_<?php echo $index; ?> = nestedData.seriesLabels || seriesLabels_<?php echo $index; ?>;
                                      seriesColors_<?php echo $index; ?> = nestedData.seriesColors || seriesColors_<?php echo $index; ?>;
                                    }
                                    
                                    console.log('Bar data <?php echo $index; ?> - Categories:', categories_<?php echo $index; ?>, 'Values:', values_<?php echo $index; ?>);
                                    
                                    if (categories_<?php echo $index; ?>.length > 0 && values_<?php echo $index; ?>.length > 0) {
                                      let datasets_<?php echo $index; ?> = [];
                                      if (Array.isArray(values_<?php echo $index; ?>) && values_<?php echo $index; ?>.length > 0 && Array.isArray(values_<?php echo $index; ?>[0])) {
                                        const numSeries = values_<?php echo $index; ?>[0].length;
                                        for (let seriesIndex = 0; seriesIndex < numSeries; seriesIndex++) {
                                          const seriesData = values_<?php echo $index; ?>.map(row => parseFloat(row[seriesIndex]) || 0);
                                          datasets_<?php echo $index; ?>.push({
                                            label: seriesLabels_<?php echo $index; ?>[seriesIndex] || 'Series ' + (seriesIndex + 1),
                                            data: seriesData,
                                            backgroundColor: seriesColors_<?php echo $index; ?>[seriesIndex % seriesColors_<?php echo $index; ?>.length],
                                            borderWidth: 1
                                          });
                                        }
                                      } else {
                                        const seriesData = Array.isArray(values_<?php echo $index; ?>) ? values_<?php echo $index; ?>.map(v => parseFloat(v) || 0) : [];
                                        datasets_<?php echo $index; ?>.push({
                                          label: seriesLabels_<?php echo $index; ?>[0] || 'Data',
                                          data: seriesData,
                                          backgroundColor: seriesColors_<?php echo $index; ?>[0] || '#36A2EB',
                                          borderWidth: 1
                                        });
                                      }
                                      
                                      const chart_bar_<?php echo $index; ?> = new Chart(ctx_<?php echo $index; ?>, {
                                        type: 'bar',
                                        data: {
                                          labels: categories_<?php echo $index; ?>,
                                          datasets: datasets_<?php echo $index; ?>
                                        },
                                        options: {
                                          responsive: true,
                                          maintainAspectRatio: false,
                                          animation: {
                                            duration: 1200
                                          },
                                          plugins: { 
                                            legend: { 
                                              display: datasets_<?php echo $index; ?>.length > 1,
                                              position: 'top',
                                              labels: { boxWidth: 8, font: { size: 8 } }
                                            }
                                          },
                                          scales: { 
                                            y: { beginAtZero: true, ticks: { font: { size: 8 } } },
                                            x: { ticks: { font: { size: 7 }, maxRotation: 45 } }
                                          }
                                        }
                                      });
                                      window['chart_<?php echo $nestedGraphId; ?>'] = chart_bar_<?php echo $index; ?>;
                                      console.log('Nested bar chart <?php echo $index; ?> created successfully');
                                    } else {
                                      console.warn('Empty bar chart data for nested graph <?php echo $index; ?>');
                                      canvas_<?php echo $index; ?>.parentElement.innerHTML = '<div class="text-center p-2"><p class="text-xs text-gray-500">No bar data</p></div>';
                                    }
                                  <?php endif; ?>
                                <?php endforeach; ?>
                                
                                console.log('=== END GROUP GRAPH <?php echo $graph_index; ?> ===');
                              });
                              </script>
                            <?php else: ?>
                              <div class='text-center p-4'>
                                <p class='text-gray-500'>No graphs in this group</p>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php 
                        $graph_index++;
                      endforeach; ?>
                    </div>
                    
                    <!-- Main Carousel Navigation -->
                    <?php if ($total_graphs > 1): ?>
                    <div class="main-carousel-nav flex justify-center items-center mt-4 space-x-4" style="padding: 10px; margin-top: 15px; background: rgba(255,255,255,0.9); border-radius: 8px; position: relative; z-index: 20;">
                      <button class="main-prev-btn bg-orange-500 text-white p-2 rounded-full hover:bg-orange-600 transition-colors duration-200 shadow-md" onclick="prevMainGraph()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                      </button>
                      
                      <span class="main-indicator text-sm font-medium text-gray-700">1 of <?php echo $total_graphs; ?></span>
                      
                      <button class="main-next-btn bg-orange-500 text-white p-2 rounded-full hover:bg-orange-600 transition-colors duration-200 shadow-md" onclick="nextMainGraph()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                      </button>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                
                <script>
                // Main graph carousel functionality
                let currentMainGraphIndex = 0;
                const totalMainGraphs = <?php echo $total_graphs; ?>;
                
                function nextMainGraph() {
                    const items = document.querySelectorAll('.main-carousel-item');
                    const indicator = document.querySelector('.main-indicator');
                    
                    // Hide current item
                    items[currentMainGraphIndex].style.display = 'none';
                    
                    // Move to next item
                    currentMainGraphIndex = (currentMainGraphIndex + 1) % totalMainGraphs;
                    
                    // Show next item
                    items[currentMainGraphIndex].style.display = 'flex';
                    
                    // Update indicator
                    indicator.textContent = `${currentMainGraphIndex + 1} of ${totalMainGraphs}`;
                    
                    console.log('Moved to main slide', currentMainGraphIndex + 1);
                    
                    // Trigger chart animation for the new slide
                    const currentItem = items[currentMainGraphIndex];
                    const canvas = currentItem.querySelector('canvas');
                    if (canvas) {
                        const chartId = canvas.id;
                        const chart = window['chart_' + chartId];
                        if (chart) {
                            console.log('Re-animating chart:', chartId);
                            chart.update('active');
                        }
                    }
                }
                
                function prevMainGraph() {
                    const items = document.querySelectorAll('.main-carousel-item');
                    const indicator = document.querySelector('.main-indicator');
                    
                    // Hide current item
                    items[currentMainGraphIndex].style.display = 'none';
                    
                    // Move to previous item
                    currentMainGraphIndex = (currentMainGraphIndex - 1 + totalMainGraphs) % totalMainGraphs;
                    
                    // Show previous item
                    items[currentMainGraphIndex].style.display = 'flex';
                    
                    // Update indicator
                    indicator.textContent = `${currentMainGraphIndex + 1} of ${totalMainGraphs}`;
                }
                
                // Nested carousel navigation functions
                function nextNestedGraph(groupId) {
                    try {
                        console.log('nextNestedGraph called with groupId:', groupId);
                        
                        const carousel = document.querySelector(`[data-group-id="${groupId}"] .nested-carousel-container`);
                        if (!carousel) {
                            console.warn('Nested carousel container not found for groupId:', groupId);
                            return;
                        }
                        
                        const items = carousel.querySelectorAll('.nested-carousel-item');
                        const indicator = carousel.parentElement.querySelector('.nested-indicator');
                        
                        if (items.length === 0) {
                            console.warn('No nested carousel items found');
                            return;
                        }
                        
                        let currentIndex = 0;
                        items.forEach((item, index) => {
                            const isVisible = item.style.display === 'flex' || 
                                            item.classList.contains('active') ||
                                            (!item.style.display && index === 0);
                            if (isVisible) {
                                currentIndex = index;
                            }
                        });
                        
                        console.log(`Current active item: ${currentIndex + 1} of ${items.length}`);
                        
                        // Hide all items
                        items.forEach(item => {
                            item.style.display = 'none';
                            item.classList.remove('active');
                        });
                        
                        const nextIndex = (currentIndex + 1) % items.length;
                        
                        // Show next item with explicit styling
                        items[nextIndex].style.display = 'flex';
                        items[nextIndex].style.visibility = 'visible';
                        items[nextIndex].style.opacity = '1';
                        items[nextIndex].classList.add('active');
                        
                        // Force reflow to ensure display update
                        void items[nextIndex].offsetHeight;
                        
                        if (indicator) {
                            indicator.textContent = `${nextIndex + 1} of ${items.length}`;
                        }
                        
                        console.log(`Successfully switched to graph ${nextIndex + 1} of ${items.length}`);
                        console.log('Next item display:', items[nextIndex].style.display);
                    } catch (error) {
                        console.error('Error in nextNestedGraph:', error);
                    }
                }

                function prevNestedGraph(groupId) {
                    try {
                        console.log('prevNestedGraph called with groupId:', groupId);
                        
                        const carousel = document.querySelector(`[data-group-id="${groupId}"] .nested-carousel-container`);
                        if (!carousel) {
                            console.warn('Nested carousel container not found for groupId:', groupId);
                            return;
                        }
                        
                        const items = carousel.querySelectorAll('.nested-carousel-item');
                        const indicator = carousel.parentElement.querySelector('.nested-indicator');
                        
                        if (items.length === 0) {
                            console.warn('No nested carousel items found');
                            return;
                        }
                        
                        let currentIndex = 0;
                        items.forEach((item, index) => {
                            const isVisible = item.style.display === 'flex' || 
                                            item.classList.contains('active') ||
                                            (!item.style.display && index === 0);
                            if (isVisible) {
                                currentIndex = index;
                            }
                        });
                        
                        console.log(`Current active item: ${currentIndex + 1}`);
                        
                        items.forEach(item => {
                            item.style.display = 'none';
                            item.classList.remove('active');
                        });
                        
                        const prevIndex = (currentIndex - 1 + items.length) % items.length;
                        
                        items[prevIndex].style.display = 'flex';
                        items[prevIndex].classList.add('active');
                        
                        if (indicator) {
                            indicator.textContent = `${prevIndex + 1} of ${items.length}`;
                        }
                        
                        console.log(`Switched from graph ${currentIndex + 1} to graph ${prevIndex + 1} of ${items.length}`);
                    } catch (error) {
                        console.error('Error in prevNestedGraph:', error);
                    }
                }
                
                // Auto-rotate main carousel with dynamic timing
                <?php if ($total_graphs > 1): ?>
                let mainCarouselTimer;
                let nestedCarouselTimer;
                let currentNestedIndex = 0;
                
                // Initialize all nested carousels to show first slide on page load
                function initializeNestedCarousels() {
                    console.log('Initializing all nested carousels to first slide');
                    const allNestedCarousels = document.querySelectorAll('.nested-carousel[data-group-id]');
                    
                    allNestedCarousels.forEach(carousel => {
                        const groupId = carousel.getAttribute('data-group-id');
                        const items = carousel.querySelectorAll('.nested-carousel-item');
                        const indicator = carousel.parentElement ? carousel.parentElement.querySelector('.nested-indicator') : null;
                        
                        console.log('Initializing nested carousel', groupId, 'with', items.length, 'items');
                        
                        items.forEach((item, idx) => {
                            if (idx === 0) {
                                item.style.display = 'flex';
                                item.style.visibility = 'visible';
                                item.classList.add('active');
                            } else {
                                item.style.display = 'none';
                                item.style.visibility = 'hidden';
                                item.classList.remove('active');
                            }
                        });
                        
                        if (indicator && items.length > 0) {
                            indicator.textContent = `1 of ${items.length}`;
                        }
                    });
                }
                
                // Call initialization immediately
                initializeNestedCarousels();
                
                function showNestedSlide(groupId, slideIndex) {
                    const carousel = document.querySelector(`[data-group-id="${groupId}"] .nested-carousel-container`);
                    if (!carousel) {
                        console.error('Carousel not found for groupId:', groupId);
                        return false;
                    }
                    
                    const items = carousel.querySelectorAll('.nested-carousel-item');
                    const indicator = carousel.parentElement.querySelector('.nested-indicator');
                    
                    if (items.length === 0) {
                        console.error('No nested items found');
                        return false;
                    }
                    
                    console.log(`Showing nested slide ${slideIndex + 1} of ${items.length} for group ${groupId}`);
                    
                    // Hide all items
                    items.forEach(item => {
                        item.style.display = 'none';
                        item.style.visibility = 'hidden';
                        item.classList.remove('active');
                    });
                    
                    // Show the requested slide
                    if (items[slideIndex]) {
                        items[slideIndex].style.display = 'flex';
                        items[slideIndex].style.visibility = 'visible';
                        items[slideIndex].classList.add('active');
                        
                        if (indicator) {
                            indicator.textContent = `${slideIndex + 1} of ${items.length}`;
                        }
                        
                        // Trigger chart animation for nested chart
                        const canvas = items[slideIndex].querySelector('canvas');
                        if (canvas) {
                            const chartId = canvas.id;
                            const chart = window['chart_' + chartId];
                            if (chart) {
                                console.log('Re-animating nested chart:', chartId);
                                chart.update('active');
                            }
                        }
                        
                        return true;
                    }
                    
                    return false;
                }
                
                function stopNestedCarousel() {
                    if (nestedCarouselTimer) {
                        clearInterval(nestedCarouselTimer);
                        nestedCarouselTimer = null;
                    }
                    currentNestedIndex = 0;
                }
                
                function startNestedCarousel(groupId, itemCount) {
                    // Stop any existing nested carousel
                    stopNestedCarousel();
                    
                    console.log('Starting nested carousel for group', groupId, 'with', itemCount, 'items');
                    
                    // Show first slide immediately
                    currentNestedIndex = 0;
                    showNestedSlide(groupId, currentNestedIndex);
                    
                    if (itemCount > 1) {
                        // Start interval to cycle through slides every 5 seconds
                        nestedCarouselTimer = setInterval(() => {
                            currentNestedIndex++;
                            
                            // Loop back to first slide after showing all
                            if (currentNestedIndex >= itemCount) {
                                currentNestedIndex = 0;
                            }
                            
                            showNestedSlide(groupId, currentNestedIndex);
                            console.log('Nested carousel advanced to slide', currentNestedIndex + 1);
                        }, 5000);
                    }
                }
                
                function scheduleNextMainSlide() {
                    // Clear any existing main timer
                    if (mainCarouselTimer) {
                        clearTimeout(mainCarouselTimer);
                    }
                    
                    const items = document.querySelectorAll('.main-carousel-item');
                    const currentItem = items[currentMainGraphIndex];
                    const nestedCarousel = currentItem.querySelector('.nested-carousel[data-group-id]');
                    
                    console.log('scheduleNextMainSlide: main index =', currentMainGraphIndex);
                    
                    if (nestedCarousel) {
                        // Group graph - start nested carousel cycling
                        const groupId = nestedCarousel.getAttribute('data-group-id');
                        const nestedItems = nestedCarousel.querySelectorAll('.nested-carousel-item');
                        
                        console.log('Group graph detected with', nestedItems.length, 'nested items');
                        
                        // Start nested carousel (will cycle every 5 seconds)
                        startNestedCarousel(groupId, nestedItems.length);
                        
                        // Calculate total time: (number of slides × 5 seconds)
                        const totalTime = nestedItems.length * 5000;
                        console.log('Group graph will show for', totalTime, 'ms before moving to next main slide');
                        
                        // Move to next main slide after all nested slides have been shown once
                        mainCarouselTimer = setTimeout(() => {
                            stopNestedCarousel();
                            nextMainGraph();
                            scheduleNextMainSlide();
                        }, totalTime);
                    } else {
                        // Individual graph - stop any nested carousel and show for 10 seconds
                        console.log('Individual graph detected. Showing for 10 seconds');
                        stopNestedCarousel();
                        
                        mainCarouselTimer = setTimeout(() => {
                            nextMainGraph();
                            scheduleNextMainSlide();
                        }, 10000);
                    }
                }
                
                // Start the main carousel timer after a short delay to ensure DOM is ready
                console.log('Initializing carousel. Starting at main index:', currentMainGraphIndex);
                setTimeout(() => {
                    scheduleNextMainSlide();
                }, 100);
                <?php endif; ?>
                
                // Remove the DOMContentLoaded wrapper since we're starting nested carousels in scheduleNextMainSlide
                
                </script>
                
              <?php else: ?>
                <!-- No graphs available -->
                <div class="no-graphs-message flex flex-col justify-center items-center h-full p-4 text-center">
                  <i class="fas fa-chart-pie text-gray-400 text-5xl mb-4"></i>
                  <p class="text-gray-600 text-lg">No performance metrics available</p>
                  <p class="text-gray-500 text-sm mt-2">Graphs will appear here when added by department administrators</p>
                </div>
              <?php endif;
          } else { ?>
            <!-- Graph bulletin not available -->
            <div class="no-graph-system-message flex flex-col justify-center items-center h-full p-4 text-center">
              <i class="fas fa-chart-pie text-gray-400 text-5xl mb-4"></i>
              <p class="text-gray-600 text-lg">Graph system not configured</p>
              <p class="text-gray-500 text-sm mt-2">Performance metrics will be available when the graph module is set up</p>
            </div>
          <?php } ?>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Page Navigation -->
  <div class="flex flex-col sm:flex-row justify-between items-center mt-2 sm:mt-4 gap-2">
    <div class="flex space-x-3 w-full sm:w-auto">
      <button id="refreshBtn" class="btn btn-secondary px-3 py-1 sm:px-4 sm:py-2 rounded-xl bg-white hover:bg-orange-800 hover:text-white transition-colors duration-200 flex items-center shadow-md w-full sm:w-auto justify-center">
        <i class="fas fa-sync-alt mr-1 sm:mr-2"></i> <span class="text-sm sm:text-base">Refresh</span>
      </button>
    </div>
    <div class="flex space-x-3 w-full sm:w-auto">
      <button id="togglePageBtn" class="btn btn-primary px-3 py-1 sm:px-4 sm:py-2 rounded-xl bg-white hover:bg-orange-800 hover:text-white transition-colors duration-200 flex items-center shadow-md w-full sm:w-auto justify-center">
        <i class="fas fa-arrow-right mr-1 sm:mr-2"></i> <span class="text-sm sm:text-base">Go to Page 2</span>
      </button>
    </div>
  </div>
  
  <!-- Announcement Modal -->
  <div id="announcementModal" class="announcement-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="modalTitle">Announcement</h3>
      </div>
      <div class="modal-body">
        <div id="pdfContainer" class="pdf-container">
          <div class="loading-spinner"></div>
          <p class="text-center text-gray-600">Loading announcement...</p>
        </div>
      </div>
      <div class="modal-footer">
        <div class="modal-meta" id="modalMeta"></div>
        <div class="page-navigation">
          <button id="prevPageBtn" class="page-nav-btn" disabled>
            <i class="fas fa-chevron-left"></i>
          </button>
          <div id="pageIndicator" class="page-indicator">Page 1 of 1</div>
          <button id="nextPageBtn" class="page-nav-btn" disabled>
            <i class="fas fa-chevron-right"></i>
          </button>
          <span class="modal-close" onclick="closeAnnouncementModal()">&times;</span>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg flex flex-col items-center">
      <i class="fas fa-spinner fa-spin text-2xl sm:text-3xl text-orange-600 mb-3"></i>
      <p class="text-base sm:text-lg font-semibold">Refreshing...</p>
    </div>
  </div>
  
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
<script src="bulletin.js"></script>

<!-- Pass data from PHP to JavaScript -->
<script>
  // Initialize the bulletin with data from PHP
  document.addEventListener('DOMContentLoaded', function() {
    initializeBulletin(
      <?= json_encode($announcements); ?>,
      <?= json_encode($memos); ?>,
      <?= json_encode($gads); ?>,
      <?= json_encode($studentDevs); ?>
    );
  });
</script>

<!-- PDF Viewer Modal -->
<div id="pdf-viewer-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 10000;">
  <div style="position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; padding: 20px;">
    
    <!-- Header with Title and Close Button -->
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 20px; position: relative;">
      <div style="background: linear-gradient(90deg, #ea580c 0%, #f97316 50%, #ea580c 100%); color: white; padding: 12px 40px; border-radius: 25px; font-size: 1.1rem; font-weight: 600; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-bullhorn"></i>
        <span id="pdf-modal-title">Announcement: Bulletin Announcement Feb 3</span>
      </div>
    </div>
    
    <!-- PDF Content Area with White Background -->
    <div style="flex: 1; border-radius: 20px; padding: 30px; display: flex; justify-content: center; align-items: center; gap: 15px; overflow: auto; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);" id="pdf-pages-container">
      <canvas id="pdf-page-1" style="box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); border-radius: 8px; max-width: 500px; height: auto;"></canvas>
      <canvas id="pdf-page-2" style="box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); border-radius: 8px; max-width: 500px; height: auto;"></canvas>
    </div>
    
    <!-- Footer with Navigation -->
    <div style="background: rgba(0, 0, 0, 0.7); margin-top: 20px; padding: 15px 30px; border-radius: 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);">
      <div style="color: white; font-size: 0.9rem; font-weight: 500;">
        Posted on <span style="font-weight: 600;" id="pdf-posted-date">Loading...</span> | File: <span style="font-style: italic;" id="pdf-file-name">Loading...</span>
      </div>
      <div style="display: flex; align-items: center; gap: 15px;">
        <button onclick="goToPreviousPages()" id="pdf-prev-btn" style="background: #ea580c; color: white; border: none; border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); font-size: 1.1rem;">
          <i class="fas fa-chevron-left"></i>
        </button>
        <div style="background: #ea580c; color: white; padding: 8px 20px; border-radius: 20px; font-weight: 600; min-width: 120px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
          <span id="pdf-page-indicator">Pages 1-2 of 4</span>
        </div>
        <button onclick="goToNextPages()" id="pdf-next-btn" style="background: #ea580c; color: white; border: none; border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); font-size: 1.1rem;">
          <i class="fas fa-chevron-right"></i>
        </button>
        <button onclick="closePDFModal()" style="background: #dc2626; border: none; color: white; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); transition: all 0.2s;">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  // Configure PDF.js worker
  if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';
  }

  // PDF Modal Variables
  let currentPDFDoc = null;
  let currentPDFPage = 1;
  let totalPDFPages = 0;

  // Listen for messages from iframe
  window.addEventListener('message', function(event) {
    if (event.data.type === 'openPDF') {
      openPDFModal(event.data.filePath, event.data.title, event.data.postedDate, event.data.fileName, event.data.fileType);
    }
  });

  function openPDFModal(filePath, title, postedDate, fileName, fileType) {
    document.getElementById('pdf-viewer-modal').style.display = 'block';
    document.getElementById('pdf-modal-title').textContent = title || 'Document';
    
    // Update the footer with dynamic data
    const fileNameToDisplay = fileName || filePath.split('/').pop();
    const dateToDisplay = postedDate || 'N/A';
    
    document.getElementById('pdf-posted-date').textContent = dateToDisplay;
    document.getElementById('pdf-file-name').textContent = fileNameToDisplay;
    
    console.log('Opening file with path:', filePath, 'type:', fileType);
    
    // Detect file type if not provided
    if (!fileType) {
      const extension = filePath.split('.').pop().toLowerCase();
      fileType = extension;
    }
    
    // Handle different file types
    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType)) {
      loadImageInModal(filePath);
    } else if (fileType === 'pdf') {
      loadPDFInModal(filePath);
    } else {
      loadPDFInModal(filePath); // Default to PDF loader
    }
  }

  function loadImageInModal(filePath) {
    const container = document.getElementById('pdf-pages-container');
    container.innerHTML = `
      <img src="${filePath}" 
           alt="Document" 
           style="max-width: 100%; max-height: 100%; object-fit: contain; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); border-radius: 8px;">
    `;
    
    // Hide navigation buttons for images
    document.getElementById('pdf-prev-btn').style.display = 'none';
    document.getElementById('pdf-next-btn').style.display = 'none';
    document.querySelector('#pdf-page-indicator').parentElement.style.display = 'none';
  }

  function closePDFModal() {
    document.getElementById('pdf-viewer-modal').style.display = 'none';
    currentPDFDoc = null;
    currentPDFPage = 1;
    totalPDFPages = 0;
    
    // Reset navigation buttons visibility
    document.getElementById('pdf-prev-btn').style.display = 'flex';
    document.getElementById('pdf-next-btn').style.display = 'flex';
    document.querySelector('#pdf-page-indicator').parentElement.style.display = 'flex';
  }

  function loadPDFInModal(filePath) {
    // Show navigation buttons for PDFs
    document.getElementById('pdf-prev-btn').style.display = 'flex';
    document.getElementById('pdf-next-btn').style.display = 'flex';
    document.querySelector('#pdf-page-indicator').parentElement.style.display = 'flex';
    
    // Reset the container and recreate canvas elements
    const container = document.getElementById('pdf-pages-container');
    container.innerHTML = `
      <canvas id="pdf-page-1" style="box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); border-radius: 8px; max-width: 500px; height: auto;"></canvas>
      <canvas id="pdf-page-2" style="box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); border-radius: 8px; max-width: 500px; height: auto;"></canvas>
    `;
    
    const loadingTask = pdfjsLib.getDocument(filePath);
    loadingTask.promise.then(pdf => {
      currentPDFDoc = pdf;
      totalPDFPages = pdf.numPages;
      currentPDFPage = 1;
      renderPDFPages();
    }).catch(error => {
      console.error('Error loading PDF:', error);
      alert('Error loading PDF document');
      closePDFModal();
    });
  }
  function renderPDFPages() {
    const canvas1 = document.getElementById('pdf-page-1');
    const canvas2 = document.getElementById('pdf-page-2');
    const container = document.getElementById('pdf-pages-container');
    
    console.log('renderPDFPages called:', { currentPDFPage, totalPDFPages });
    
    // Clear canvases completely
    canvas1.style.display = 'none';
    canvas2.style.display = 'none';
    canvas1.width = 0;
    canvas1.height = 0;
    canvas2.width = 0;
    canvas2.height = 0;

    // A4 aspect ratio: 210mm x 297mm (1:1.414)
    // Standard A4 width in pixels at 72 DPI: ~595px
    const targetWidth = 500;

    // Check if this is the last page and it's odd (only show single page for odd total)
    const isLastPageOdd = (currentPDFPage === totalPDFPages && totalPDFPages % 2 === 1);
    
    console.log('isLastPageOdd:', isLastPageOdd, 'currentPDFPage:', currentPDFPage, 'totalPDFPages:', totalPDFPages);
    
    // Render first page
    if (currentPDFPage <= totalPDFPages) {
      console.log('Rendering page:', currentPDFPage);
      currentPDFDoc.getPage(currentPDFPage).then(page => {
        const viewport = page.getViewport({ scale: 1.0 });
        const scale = targetWidth / viewport.width;
        const scaledViewport = page.getViewport({ scale: scale });
        
        canvas1.height = scaledViewport.height;
        canvas1.width = scaledViewport.width;
        canvas1.style.display = 'block';
        canvas1.style.maxWidth = targetWidth + 'px';
        canvas1.style.width = '100%';
        canvas1.style.height = 'auto';

        const renderContext = {
          canvasContext: canvas1.getContext('2d'),
          viewport: scaledViewport
        };
        page.render(renderContext);
      });
    }

    // Render second page - always render if available, unless it's the last odd page
    if (!isLastPageOdd && currentPDFPage + 1 <= totalPDFPages) {
      console.log('Rendering second page:', currentPDFPage + 1);
      currentPDFDoc.getPage(currentPDFPage + 1).then(page => {
        const viewport = page.getViewport({ scale: 1.0 });
        const scale = targetWidth / viewport.width;
        const scaledViewport = page.getViewport({ scale: scale });
        
        canvas2.height = scaledViewport.height;
        canvas2.width = scaledViewport.width;
        canvas2.style.display = 'block';
        canvas2.style.maxWidth = targetWidth + 'px';
        canvas2.style.width = '100%';
        canvas2.style.height = 'auto';

        const renderContext = {
          canvasContext: canvas2.getContext('2d'),
          viewport: scaledViewport
        };
        page.render(renderContext);
      });
    } else {
      console.log('NOT rendering second page. isLastPageOdd:', isLastPageOdd, 'nextPage would be:', currentPDFPage + 1);
    }

    // Update page indicator
    if (isLastPageOdd) {
      document.getElementById('pdf-page-indicator').textContent = `Page ${currentPDFPage} of ${totalPDFPages}`;
    } else {
      const endPage = Math.min(currentPDFPage + 1, totalPDFPages);
      document.getElementById('pdf-page-indicator').textContent = `Pages ${currentPDFPage}-${endPage} of ${totalPDFPages}`;
    }

    // Update button states
    document.getElementById('pdf-prev-btn').disabled = currentPDFPage <= 1;
    // For even pages, disable next when we're at the last pair (totalPages - 1)
    // For odd pages, disable next when we're at the last page
    if (totalPDFPages % 2 === 0) {
      document.getElementById('pdf-next-btn').disabled = currentPDFPage >= totalPDFPages - 1;
    } else {
      document.getElementById('pdf-next-btn').disabled = currentPDFPage >= totalPDFPages;
    }
  }

  function changePDFPage(delta) {
    let newPage = currentPDFPage + delta;
    
    console.log('changePDFPage called:', { currentPDFPage, delta, newPage, totalPDFPages });
    
    // Handle forward navigation
    if (delta > 0) {
      // If total pages is odd and we're approaching or past the last page
      if (totalPDFPages % 2 === 1 && newPage >= totalPDFPages) {
        // Jump directly to the last page
        newPage = totalPDFPages;
        console.log('Jumping to last odd page:', newPage);
      }
      // If total pages is even, don't go past the second-to-last page
      else if (totalPDFPages % 2 === 0 && newPage >= totalPDFPages) {
        newPage = totalPDFPages - 1;
        console.log('Adjusted to last even page pair:', newPage);
      }
    }
    
    // Handle backward navigation
    if (delta < 0) {
      // If we're currently on the last odd page
      if (totalPDFPages % 2 === 1 && currentPDFPage === totalPDFPages) {
        // Jump back to show the previous pair (e.g., from page 5 to pages 3-4)
        newPage = totalPDFPages - 2;
        console.log('Jumping back from last odd page to:', newPage);
      }
    }
    
    // Ensure newPage is within valid range
    if (newPage >= 1 && newPage <= totalPDFPages) {
      console.log('Setting currentPDFPage to:', newPage);
      currentPDFPage = newPage;
      renderPDFPages();
    } else {
      console.log('Invalid page number:', newPage);
    }
  }

  // Smart navigation functions
  function goToNextPages() {
    console.log('goToNextPages called. Current:', currentPDFPage, 'Total:', totalPDFPages);
    
    // Calculate the maximum page we can navigate to
    const maxPage = totalPDFPages % 2 === 0 ? totalPDFPages - 1 : totalPDFPages;
    
    // If we're already at or past the max page, do nothing
    if (currentPDFPage >= maxPage) {
      console.log('Already at last page/pair');
      return;
    }
    
    // Calculate next page position (normally jump by 2)
    let nextPage = currentPDFPage + 2;
    console.log('Next page would be:', nextPage);
    
    // Special handling for odd total pages
    if (totalPDFPages % 2 === 1) {
      console.log('Total pages is odd');
      // If next position would show the last page as part of a pair, jump to last page alone
      if (nextPage === totalPDFPages || nextPage > totalPDFPages) {
        nextPage = totalPDFPages;
        console.log('Adjusted to last odd page:', nextPage);
      }
    } else {
      // For even pages, make sure we don't go past the second-to-last page
      if (nextPage >= totalPDFPages) {
        nextPage = totalPDFPages - 1;
        console.log('Adjusted to last even page pair:', nextPage);
      }
    }
    
    // Make sure we don't go past the max
    currentPDFPage = Math.min(nextPage, maxPage);
    console.log('New currentPDFPage:', currentPDFPage);
    
    renderPDFPages();
  }

  function goToPreviousPages() {
    console.log('goToPreviousPages called. Current:', currentPDFPage, 'Total:', totalPDFPages);
    
    // If we're already at page 1, do nothing
    if (currentPDFPage <= 1) {
      console.log('Already at first page');
      return;
    }
    
    // Always jump back by 2 pages
    currentPDFPage = Math.max(1, currentPDFPage - 2);
    console.log('New currentPDFPage:', currentPDFPage);
    
    renderPDFPages();
  }

  // Close modal on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closePDFModal();
    }
  });

  // Tab functionality for all tab containers
  document.addEventListener('DOMContentLoaded', function() {
    // Get all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    tabButtons.forEach(button => {
      button.addEventListener('click', function() {
        const targetTab = this.getAttribute('data-tab');
        
        // Find the parent tab container
        const tabContainer = this.closest('.tab-container');
        if (!tabContainer) return;
        
        // Remove active class from all buttons in this container
        const containerButtons = tabContainer.querySelectorAll('.tab-btn');
        containerButtons.forEach(btn => btn.classList.remove('active'));
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Hide all tab panes in this container
        const tabPanes = tabContainer.querySelectorAll('.tab-pane');
        tabPanes.forEach(pane => pane.classList.remove('active'));
        
        // Show the target tab pane
        const targetPane = tabContainer.querySelector(`#${targetTab}-tab`);
        if (targetPane) {
          targetPane.classList.add('active');
        }
      });
    });
    
    console.log('Tab functionality initialized for', tabButtons.length, 'buttons');
  });

  // Carousel functionality for About CvSU section
  document.addEventListener('DOMContentLoaded', function() {
    const mandatesCarousel = document.querySelector('.mandates-carousel');
    if (!mandatesCarousel) return;
    
    const items = mandatesCarousel.querySelectorAll('.carousel-item');
    const prevBtn = document.querySelector('.mandates-prev-btn');
    const nextBtn = document.querySelector('.mandates-next-btn');
    
    if (items.length === 0) return;
    
    let currentIndex = 0;
    
    function showSlide(index) {
      items.forEach((item, i) => {
        item.classList.remove('active');
        if (i === index) {
          item.classList.add('active');
        }
      });
    }
    
    if (prevBtn) {
      prevBtn.addEventListener('click', function() {
        currentIndex = (currentIndex - 1 + items.length) % items.length;
        showSlide(currentIndex);
      });
    }
    
    if (nextBtn) {
      nextBtn.addEventListener('click', function() {
        currentIndex = (currentIndex + 1) % items.length;
        showSlide(currentIndex);
      });
    }
    
    // Auto-rotate carousel every 10 seconds
    setInterval(function() {
      currentIndex = (currentIndex + 1) % items.length;
      showSlide(currentIndex);
    }, 10000);
  });
</script>

</body>
</html>