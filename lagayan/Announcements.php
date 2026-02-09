<?php
// CEIT_Modules/Announcements/Announcements.php
include "../../db.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

// Get module ID for Announcements
$moduleQuery = "SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Get all departments for organization
$deptQuery = "SELECT dept_id, dept_name, acronym FROM departments ORDER BY dept_name";
$deptResult = $conn->query($deptQuery);
$departments = [];
while ($row = $deptResult->fetch_assoc()) {
    $departments[$row['dept_id']] = $row;
}

// Get pending announcements grouped by department
$pendingByDept = [];
foreach ($departments as $deptId => $dept) {
    $query = "SELECT dp.*, u.name as user_name 
              FROM department_post dp 
              LEFT JOIN users u ON dp.user_id = u.id 
              WHERE dp.module = ? AND dp.dept_id = ? AND dp.status = 'Pending' 
              ORDER BY dp.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $moduleId, $deptId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
        $relativeFilePath = "uploads/{$dept['acronym']}/Announcement/" . $row['file_path'];
        
        $announcements[] = [
            'id' => $row['id'],
            'file_path' => $relativeFilePath,
            'description' => $row['description'],
            'posted_on' => $row['created_at'],
            'file_type' => strtolower($fileExtension),
            'user_name' => $row['user_name'] ?? 'Unknown'
        ];
    }
    
    if (!empty($announcements)) {
        $pendingByDept[$deptId] = [
            'dept_info' => $dept,
            'announcements' => $announcements
        ];
    }
}

// Handle AJAX requests for approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $announcementId = $_POST['id'] ?? null;
    
    if (!$announcementId) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit;
    }
    
    try {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE department_post SET status = 'Approved' WHERE id = ?");
            $stmt->bind_param("i", $announcementId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Announcement approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve announcement']);
            }
        } elseif ($action === 'reject') {
            $rejectionReason = $_POST['reason'] ?? 'No reason provided';
            
            $stmt = $conn->prepare("UPDATE department_post SET status = 'Not Approved', content = ? WHERE id = ?");
            $stmt->bind_param("si", $rejectionReason, $announcementId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Announcement rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject announcement']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEIT Announcement Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Announcement-specific styles */
        .announcement-file-preview {
            width: 100%;
            height: 200px;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9fafb;
        }

        .announcement-file-modal {
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

        .announcement-modal-content {
            position: relative;
            background-color: white;
            margin: 2% auto;
            padding: 0;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .announcement-modal-header {
            padding: 15px 20px;
            background-color: #ea580c;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .announcement-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .announcement-modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .announcement-modal-close:hover {
            transform: scale(1.2);
        }

        .announcement-modal-body {
            padding: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            height: calc(100% - 140px);
        }

        .announcement-pdf-container {
            width: 100%;
            height: 100%;
            min-height: 82vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .announcement-pdf-page {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            max-width: 100%;
            max-height: 100%;
        }

        .announcement-modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .announcement-modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .announcement-page-navigation {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .announcement-page-nav-btn {
            background-color: #ea580c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.2s;
        }

        .announcement-page-nav-btn:hover {
            background-color: #c2410c;
            transform: scale(1.1);
        }

        .announcement-page-nav-btn:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
            transform: scale(1);
        }

        .announcement-page-indicator {
            font-weight: 600;
            color: #4b5563;
            min-width: 80px;
            text-align: center;
        }

        .announcement-loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #ea580c;
            width: 40px;
            height: 40px;
            animation: announcement-spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes announcement-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .announcement-file-icon {
            font-size: 4rem;
        }

        .announcement-file-icon.pdf {
            color: #dc2626;
        }

        .announcement-file-icon.doc,
        .announcement-file-icon.docx,
        .announcement-file-icon.wps {
            color: #2563eb;
        }

        .announcement-file-icon.xls,
        .announcement-file-icon.xlsx {
            color: #16a34a;
        }

        .announcement-file-icon.ppt,
        .announcement-file-icon.pptx {
            color: #ea580c;
        }

        .announcement-file-icon.jpg,
        .announcement-file-icon.jpeg,
        .announcement-file-icon.png,
        .announcement-file-icon.gif {
            color: #ea580c;
        }

        .announcement-file-icon.default {
            color: #6b7280;
        }

        .announcement-image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #000;
        }

        .announcement-image-viewer {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .announcement-office-viewer {
            border: none;
            border-radius: 0;
            overflow: hidden;
            width: 100%;
            height: 100%;
            background-color: #fff;
        }

        /* Department sections */
        .department-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
        }

        .department-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #d97706;
            color: #d97706;
        }

        /* Notification styles */
        .announcement-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            animation: announcement-slideIn 0.3s ease-out;
        }

        .announcement-notification.success {
            background-color: #ea580c;
        }

        .announcement-notification.error {
            background-color: #ef4444;
        }

        .announcement-notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes announcement-slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .announcement-modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .announcement-modal-header {
                padding: 10px 15px;
            }

            .announcement-modal-title {
                font-size: 1.2rem;
            }

            .announcement-modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .announcement-page-navigation {
                width: 100%;
                justify-content: center;
            }

            .announcement-modal-meta {
                text-align: center;
                width: 100%;
            }

            .department-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .department-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0">
                <i class="fas fa-tasks mr-3 w-5"></i> CEIT Announcement Management
            </h1>
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>
                Approve or reject pending announcements from all departments
            </div>
        </div>

        <?php if (empty($pendingByDept)): ?>
            <div class="text-center py-16 bg-white rounded-lg shadow-md">
                <i class="fas fa-check-circle fa-4x text-green-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">All Caught Up!</h2>
                <p class="text-gray-600">No pending announcements require your attention at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingByDept as $deptId => $deptData): ?>
                <div class="department-section">
                    <h2 class="department-title">
                        <i class="fas fa-building mr-2"></i>
                        <?= htmlspecialchars($deptData['dept_info']['dept_name']) ?> 
                        (<?= htmlspecialchars($deptData['dept_info']['acronym']) ?>)
                        <span class="text-sm font-normal ml-2">
                            - <?= count($deptData['announcements']) ?> pending announcement(s)
                        </span>
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach ($deptData['announcements'] as $index => $announcement): ?>
                            <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105">
                                <div class="mb-3 border border-gray-300 rounded">
                                    <div id="announcement-file-preview-<?= $deptId ?>-<?= $index ?>" class="announcement-file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                        <?php if ($announcement['file_type'] === 'pdf'): ?>
                                            <div class="announcement-loading-spinner"></div>
                                        <?php elseif (in_array($announcement['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?= $announcement['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                                onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image announcement-file-icon jpg text-4xl\'></i>'">
                                        <?php else: ?>
                                            <i class="fas fa-file announcement-file-icon <?= $announcement['file_type'] ?> text-4xl"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body flex-grow">
                                    <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                        <?= htmlspecialchars($announcement['description']) ?>
                                    </div>
                                    <p class="card-text text-gray-600 text-sm truncate">
                                        <?= basename($announcement['file_path']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Posted by: <?= htmlspecialchars($announcement['user_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?>
                                    </p>
                                </div>
                                <div class="flex justify-between mt-4 space-x-2 text-xs">
                                    <button id="announcement-view-full-<?= $deptId ?>-<?= $index ?>" 
                                            class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" 
                                            title="View Full Document" 
                                            data-file-type="<?= $announcement['file_type'] ?>" 
                                            data-file-path="<?= $announcement['file_path'] ?>">
                                        <i class="fas fa-eye fa-sm"></i>
                                        View
                                    </button>
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-approve-btn" 
                                            data-id="<?= $announcement['id'] ?>" 
                                            data-description="<?= htmlspecialchars($announcement['description']) ?>" 
                                            title="Approve">
                                        <i class="fas fa-check fa-sm"></i>
                                        Approve
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-reject-btn" 
                                            data-id="<?= $announcement['id'] ?>" 
                                            data-description="<?= htmlspecialchars($announcement['description']) ?>" 
                                            title="Reject">
                                        <i class="fas fa-times fa-sm"></i>
                                        Reject
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- File View Modals -->
    <?php foreach ($pendingByDept as $deptId => $deptData): ?>
        <?php foreach ($deptData['announcements'] as $index => $announcement): ?>
            <div id="announcement-file-modal-<?= $deptId ?>-<?= $index ?>" class="announcement-file-modal">
                <div class="announcement-modal-content">
                    <div class="announcement-modal-header">
                        <h3 class="announcement-modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                        <span class="announcement-modal-close" onclick="closeAnnouncementFileModal('<?= $deptId ?>', <?= $index ?>)">&times;</span>
                    </div>
                    <div class="announcement-modal-body">
                        <div id="announcement-pdf-container-<?= $deptId ?>-<?= $index ?>" class="announcement-pdf-container">
                            <div class="announcement-loading-spinner"></div>
                            <p class="text-center text-gray-600">Loading announcement...</p>
                        </div>
                    </div>
                    <div class="announcement-modal-footer">
                        <div class="announcement-modal-meta">
                            Posted by: <?= htmlspecialchars($announcement['user_name']) ?> on <?= date('F j, Y', strtotime($announcement['posted_on'])) ?> | File: <?= basename($announcement['file_path']) ?>
                        </div>
                        <?php if ($announcement['file_type'] === 'pdf'): ?>
                            <div class="announcement-page-navigation">
                                <button id="announcement-prev-page-btn-<?= $deptId ?>-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div id="announcement-page-indicator-<?= $deptId ?>-<?= $index ?>" class="announcement-page-indicator">Page 1 of 1</div>
                                <button id="announcement-next-page-btn-<?= $deptId ?>-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <!-- Approval Confirmation Modal -->
    <div id="announcement-approve-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-green-600">Approve Announcement</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to approve this announcement?</p>
                <p class="font-semibold mt-2" id="announcement-approve-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="announcement-cancel-approve-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="announcement-confirm-approve-btn">
                    <i class="fas fa-check mr-2"></i> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="announcement-reject-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Reject Announcement</h3>
            </div>
            <div class="mb-4">
                <p class="mb-3">Please provide a reason for rejecting this announcement:</p>
                <p class="font-semibold mb-3" id="announcement-reject-title"></p>
                <textarea id="announcement-reject-reason" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" 
                          rows="4" 
                          placeholder="Enter rejection reason..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="announcement-cancel-reject-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="announcement-confirm-reject-btn">
                    <i class="fas fa-times mr-2"></i> Reject
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        // Set PDF.js worker source
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        // Global variables for PDF handling
        window.announcementPdfDocs = {};
        window.announcementCurrentPageNum = {};
        window.announcementTotalPages = {};
        window.announcementIsRendering = {};

        // Global variables for approval/rejection
        let currentAnnouncementId = null;

        // Initialize the module
        function initializeCEITAnnouncementsModule() {
            console.log('Initializing CEIT Announcements module...');
            
            initializeAnnouncementViewButtons();
            initializeAnnouncementPageNavigation();
            initializeApprovalRejectionButtons();
            initializeAnnouncementPDFPreviews();
            
            console.log('CEIT Announcements module initialized');
        }

        // Initialize view buttons
        function initializeAnnouncementViewButtons() {
            document.querySelectorAll('[id^="announcement-view-full-"]').forEach(button => {
                button.addEventListener('click', handleAnnouncementViewButtonClick);
            });
        }

        // Handle view button click
        function handleAnnouncementViewButtonClick(event) {
            const button = this;
            const idParts = button.id.split('-');
            const deptId = idParts[3];
            const index = idParts[4];
            
            const modalId = `announcement-file-modal-${deptId}-${index}`;
            const containerId = `announcement-pdf-container-${deptId}-${index}`;
            const fileType = button.dataset.fileType;
            const filePath = button.dataset.filePath;

            const modal = document.getElementById(modalId);
            const container = document.getElementById(containerId);

            if (!modal || !container) return;

            modal.style.display = "block";
            displayAnnouncementFileContent(fileType, filePath, deptId, index, container);
        }

        // Display file content
        function displayAnnouncementFileContent(fileType, filePath, deptId, index, container) {
            const fileExtension = filePath.split('.').pop().toLowerCase();

            container.innerHTML = `
                <div class="announcement-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading file...</p>
            `;

            const baseUrl = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const testingIndex = pathParts.indexOf('Testing');

            let fullUrl;
            if (testingIndex !== -1) {
                const basePath = pathParts.slice(0, testingIndex + 1).join('/');
                fullUrl = `${baseUrl}${basePath}/${filePath}`;
            } else {
                fullUrl = `${baseUrl}/Testing/${filePath}`;
            }

            if (fileExtension === 'pdf') {
                loadAnnouncementPDFFile(fullUrl, deptId, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                container.innerHTML = `
                    <div class="announcement-image-container">
                        <img src="${fullUrl}" alt="Full view" class="announcement-image-viewer" 
                             onerror="this.onerror=null; this.style.display='none'; 
                             container.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-lg text-gray-700\\'>Failed to load image</p></div>'">
                    </div>
                `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                const encodedUrl = encodeURIComponent(fullUrl);
                container.innerHTML = `
                    <div class="announcement-office-viewer">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=${encodedUrl}" 
                            style="width: 100%; height: 100%; border: none;"
                            frameborder="0"
                            allowfullscreen>
                        </iframe>
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-file text-gray-400 text-6xl mb-4"></i>
                        <p class="text-lg text-gray-700 mb-2">Preview not available</p>
                        <p class="text-gray-600 mb-4">This file type cannot be previewed in the browser.</p>
                        <p class="text-gray-600">File: ${filePath.split('/').pop()}</p>
                    </div>
                `;
            }
        }

        // Load PDF file
        function loadAnnouncementPDFFile(filePath, deptId, index, container) {
            const key = `${deptId}-${index}`;

            container.innerHTML = `
                <div class="announcement-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading PDF document...</p>
            `;

            pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                window.announcementPdfDocs[key] = pdfDoc;
                window.announcementTotalPages[key] = pdfDoc.numPages;
                window.announcementCurrentPageNum[key] = 1;
                window.announcementIsRendering[key] = false;

                const pageIndicator = document.getElementById(`announcement-page-indicator-${deptId}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdfDoc.numPages}`;
                }

                const prevBtn = document.getElementById(`announcement-prev-page-btn-${deptId}-${index}`);
                const nextBtn = document.getElementById(`announcement-next-page-btn-${deptId}-${index}`);

                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdfDoc.numPages <= 1;

                setTimeout(() => {
                    renderAnnouncementPDFPage(deptId, index, 1);
                }, 100);

            }).catch(error => {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-lg text-gray-700 mb-2">Failed to load PDF</p>
                        <p class="text-gray-600">${error.message}</p>
                    </div>
                `;
            });
        }

        // Render PDF page
        function renderAnnouncementPDFPage(deptId, index, pageNum) {
            return new Promise((resolve, reject) => {
                const key = `${deptId}-${index}`;
                if (!window.announcementPdfDocs[key]) {
                    reject(new Error('PDF document not found'));
                    return;
                }

                const container = document.getElementById(`announcement-pdf-container-${deptId}-${index}`);

                container.innerHTML = `
                    <div class="announcement-loading-spinner"></div>
                    <p class="text-center text-gray-600">Rendering page ${pageNum}...</p>
                `;

                window.announcementPdfDocs[key].getPage(pageNum).then(page => {
                    const modalBody = document.querySelector(`#announcement-file-modal-${deptId}-${index} .announcement-modal-body`);

                    const availableWidth = modalBody.clientWidth - 20;
                    const availableHeight = modalBody.clientHeight - 20;

                    const viewport = page.getViewport({ scale: 1 });
                    const scale = Math.min(
                        availableWidth / viewport.width,
                        availableHeight / viewport.height
                    );

                    const scaledViewport = page.getViewport({ scale });

                    const canvas = document.createElement("canvas");
                    const ctx = canvas.getContext("2d");

                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    canvas.style.maxWidth = "100%";
                    canvas.style.maxHeight = "100%";

                    container.innerHTML = "";
                    container.appendChild(canvas);

                    const renderTask = page.render({
                        canvasContext: ctx,
                        viewport: scaledViewport
                    });

                    renderTask.promise.then(() => {
                        resolve();
                    }).catch(reject);
                }).catch(reject);
            });
        }

        // Initialize page navigation
        function initializeAnnouncementPageNavigation() {
            // This will be called for each PDF modal dynamically
        }

        // Initialize PDF previews
        function initializeAnnouncementPDFPreviews() {
            document.querySelectorAll('[id^="announcement-file-preview-"]').forEach(preview => {
                const filePath = preview.dataset.filePath;
                if (filePath && filePath.toLowerCase().endsWith('.pdf')) {
                    loadPDFPreview(preview, filePath);
                }
            });
        }

        // Load PDF preview
        function loadPDFPreview(previewElement, filePath) {
            const baseUrl = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const testingIndex = pathParts.indexOf('Testing');

            let fullUrl;
            if (testingIndex !== -1) {
                const basePath = pathParts.slice(0, testingIndex + 1).join('/');
                fullUrl = `${baseUrl}${basePath}/${filePath}`;
            } else {
                fullUrl = `${baseUrl}/Testing/${filePath}`;
            }

            pdfjsLib.getDocument(fullUrl).promise.then(pdfDoc => {
                return pdfDoc.getPage(1);
            }).then(page => {
                const scale = 0.5;
                const viewport = page.getViewport({ scale });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.style.maxWidth = '100%';
                canvas.style.maxHeight = '100%';

                previewElement.innerHTML = '';
                previewElement.appendChild(canvas);

                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                return page.render(renderContext).promise;
            }).catch(error => {
                previewElement.innerHTML = '<i class="fas fa-file-pdf announcement-file-icon pdf text-4xl"></i>';
            });
        }

        // Initialize approval/rejection buttons
        function initializeApprovalRejectionButtons() {
            // Approve buttons
            document.querySelectorAll('.announcement-approve-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    currentAnnouncementId = id;
                    document.getElementById('announcement-approve-title').textContent = description;
                    document.getElementById('announcement-approve-modal').classList.remove('hidden');
                });
            });

            // Reject buttons
            document.querySelectorAll('.announcement-reject-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    currentAnnouncementId = id;
                    document.getElementById('announcement-reject-title').textContent = description;
                    document.getElementById('announcement-reject-reason').value = '';
                    document.getElementById('announcement-reject-modal').classList.remove('hidden');
                });
            });

            // Modal event listeners
            document.getElementById('announcement-cancel-approve-btn').addEventListener('click', function() {
                document.getElementById('announcement-approve-modal').classList.add('hidden');
                currentAnnouncementId = null;
            });

            document.getElementById('announcement-cancel-reject-btn').addEventListener('click', function() {
                document.getElementById('announcement-reject-modal').classList.add('hidden');
                currentAnnouncementId = null;
            });

            // Confirm approve
            document.getElementById('announcement-confirm-approve-btn').addEventListener('click', function() {
                if (!currentAnnouncementId) return;

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Approving...';

                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('id', currentAnnouncementId);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAnnouncementNotification(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAnnouncementNotification(data.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-check mr-2"></i> Approve';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAnnouncementNotification('An error occurred while approving the announcement', 'error');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-check mr-2"></i> Approve';
                });
            });

            // Confirm reject
            document.getElementById('announcement-confirm-reject-btn').addEventListener('click', function() {
                if (!currentAnnouncementId) return;

                const reason = document.getElementById('announcement-reject-reason').value.trim();
                if (!reason) {
                    showAnnouncementNotification('Please provide a reason for rejection', 'error');
                    return;
                }

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Rejecting...';

                const formData = new FormData();
                formData.append('action', 'reject');
                formData.append('id', currentAnnouncementId);
                formData.append('reason', reason);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAnnouncementNotification(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAnnouncementNotification(data.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-times mr-2"></i> Reject';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAnnouncementNotification('An error occurred while rejecting the announcement', 'error');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-times mr-2"></i> Reject';
                });
            });

            // Close modals when clicking outside
            document.getElementById('announcement-approve-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    currentAnnouncementId = null;
                }
            });

            document.getElementById('announcement-reject-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    currentAnnouncementId = null;
                }
            });
        }

        // Show notification
        function showAnnouncementNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `announcement-notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Close file modal
        function closeAnnouncementFileModal(deptId, index) {
            const modal = document.getElementById(`announcement-file-modal-${deptId}-${index}`);
            if (modal) {
                modal.style.display = "none";
            }
        }

        // Navigation functions
        function goToAnnouncementPrevPage(deptId, index) {
            const key = `${deptId}-${index}`;
            if (!window.announcementPdfDocs || !window.announcementPdfDocs[key]) return;

            if (window.announcementIsRendering[key]) return;

            if (window.announcementCurrentPageNum[key] > 1) {
                window.announcementIsRendering[key] = true;

                const prevBtn = document.getElementById(`announcement-prev-page-btn-${deptId}-${index}`);
                const nextBtn = document.getElementById(`announcement-next-page-btn-${deptId}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.announcementCurrentPageNum[key]--;

                const pageIndicator = document.getElementById(`announcement-page-indicator-${deptId}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.announcementCurrentPageNum[key]} of ${window.announcementTotalPages[key]}`;
                }

                renderAnnouncementPDFPage(deptId, index, window.announcementCurrentPageNum[key]).then(() => {
                    if (prevBtn) prevBtn.disabled = window.announcementCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.announcementCurrentPageNum[key] === window.announcementTotalPages[key];
                    window.announcementIsRendering[key] = false;
                });
            }
        }

        function goToAnnouncementNextPage(deptId, index) {
            const key = `${deptId}-${index}`;
            if (!window.announcementPdfDocs || !window.announcementPdfDocs[key]) return;

            if (window.announcementIsRendering[key]) return;

            if (window.announcementCurrentPageNum[key] < window.announcementTotalPages[key]) {
                window.announcementIsRendering[key] = true;

                const prevBtn = document.getElementById(`announcement-prev-page-btn-${deptId}-${index}`);
                const nextBtn = document.getElementById(`announcement-next-page-btn-${deptId}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.announcementCurrentPageNum[key]++;

                const pageIndicator = document.getElementById(`announcement-page-indicator-${deptId}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.announcementCurrentPageNum[key]} of ${window.announcementTotalPages[key]}`;
                }

                renderAnnouncementPDFPage(deptId, index, window.announcementCurrentPageNum[key]).then(() => {
                    if (prevBtn) prevBtn.disabled = window.announcementCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.announcementCurrentPageNum[key] === window.announcementTotalPages[key];
                    window.announcementIsRendering[key] = false;
                });
            }
        }

        // Initialize when DOM is ready
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initializeCEITAnnouncementsModule, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeCEITAnnouncementsModule, 100);
            });
        }

        // Make functions globally available
        window.closeAnnouncementFileModal = closeAnnouncementFileModal;
        window.goToAnnouncementPrevPage = goToAnnouncementPrevPage;
        window.goToAnnouncementNextPage = goToAnnouncementNextPage;
    </script>
</body>

</html>