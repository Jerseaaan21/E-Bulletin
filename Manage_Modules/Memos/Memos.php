<?php
// Manage_Modules/Memos/Memos.php
// Include DB first
include "../../db.php";
session_start();

// Handle AJAX requests for approval/rejection IMMEDIATELY
// This prevents PHP warnings from HTML logic below from corrupting JSON response.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $memoId = $_POST['id'] ?? null;

    if (!$memoId) {
        echo json_encode(['success' => false, 'message' => 'Invalid memo ID']);
        exit;
    }

    try {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE department_post SET status = 'Approved' WHERE id = ?");
            $stmt->bind_param("i", $memoId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Memo approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve memo']);
            }
        } elseif ($action === 'reject') {
            $rejectionReason = $_POST['reason'] ?? 'No reason provided';

            $stmt = $conn->prepare("UPDATE department_post SET status = 'Not Approved', content = ? WHERE id = ?");
            $stmt->bind_param("si", $rejectionReason, $memoId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Memo rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject memo']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit; // STOP EXECUTION HERE. Do not load HTML for AJAX requests.
}

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

// Get module ID for Memo
$moduleQuery = "SELECT id FROM modules WHERE name = 'Memo' LIMIT 1";
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

// Get pending memos grouped by department
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

    $memos = [];
    while ($row = $result->fetch_assoc()) {
        $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
        $relativeFilePath = "uploads/{$dept['acronym']}/Memo/" . $row['file_path'];

        $memos[] = [
            'id' => $row['id'],
            'file_path' => $relativeFilePath,
            'description' => $row['description'],
            'posted_on' => $row['created_at'],
            'file_type' => strtolower($fileExtension),
            'user_name' => $row['user_name'] ?? 'Unknown'
        ];
    }

    if (!empty($memos)) {
        $pendingByDept[$deptId] = [
            'dept_info' => $dept,
            'memos' => $memos
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEIT Memo Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* CEIT Memos Specific Styles */
        .ceit-memo-file-preview {
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

        .ceit-memo-file-modal {
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

        .ceit-memo-modal-content {
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

        .ceit-memo-modal-header {
            padding: 15px 20px;
            background-color: #ea580c;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ceit-memo-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .ceit-memo-modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .ceit-memo-modal-close:hover {
            transform: scale(1.2);
        }

        .ceit-memo-modal-body {
            padding: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            height: calc(100% - 140px);
        }

        .ceit-memo-pdf-container {
            width: 100%;
            height: 100%;
            min-height: 82vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .ceit-memo-pdf-page {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            max-width: 100%;
            max-height: 100%;
        }

        .ceit-memo-modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ceit-memo-modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .ceit-memo-page-navigation {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ceit-memo-page-nav-btn {
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

        .ceit-memo-page-nav-btn:hover {
            background-color: #c2410c;
            transform: scale(1.1);
        }

        .ceit-memo-page-nav-btn:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
            transform: scale(1);
        }

        .ceit-memo-page-indicator {
            font-weight: 600;
            color: #4b5563;
            min-width: 80px;
            text-align: center;
        }

        .ceit-memo-loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #ea580c;
            width: 40px;
            height: 40px;
            animation: ceit-memo-spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes ceit-memo-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .ceit-memo-file-icon {
            font-size: 4rem;
        }

        .ceit-memo-file-icon.pdf {
            color: #dc2626;
        }

        .ceit-memo-file-icon.doc,
        .ceit-memo-file-icon.docx,
        .ceit-memo-file-icon.wps {
            color: #2563eb;
        }

        .ceit-memo-file-icon.xls,
        .ceit-memo-file-icon.xlsx {
            color: #16a34a;
        }

        .ceit-memo-file-icon.ppt,
        .ceit-memo-file-icon.pptx {
            color: #ea580c;
        }

        .ceit-memo-file-icon.jpg,
        .ceit-memo-file-icon.jpeg,
        .ceit-memo-file-icon.png,
        .ceit-memo-file-icon.gif {
            color: #ea580c;
        }

        .ceit-memo-file-icon.default {
            color: #6b7280;
        }

        .ceit-memo-image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #000;
        }

        .ceit-memo-image-viewer {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        /* Department sections */
        .ceit-memo-dept-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
        }

        .ceit-memo-dept-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #d97706;
            color: #d97706;
        }

        /* Notification styles */
        .ceit-memo-notification {
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
            animation: ceit-memo-slideIn 0.3s ease-out;
        }

        .ceit-memo-notification.success {
            background-color: #ea580c;
        }

        .ceit-memo-notification.error {
            background-color: #ef4444;
        }

        .ceit-memo-notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes ceit-memo-slideIn {
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
            .ceit-memo-modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .ceit-memo-modal-header {
                padding: 10px 15px;
            }

            .ceit-memo-modal-title {
                font-size: 1.2rem;
            }

            .ceit-memo-modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .ceit-memo-page-navigation {
                width: 100%;
                justify-content: center;
            }

            .ceit-memo-modal-meta {
                text-align: center;
                width: 100%;
            }

            .ceit-memo-dept-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .ceit-memo-dept-title {
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
                <i class="fas fa-tasks mr-3 w-5"></i> CEIT Memo Management
            </h1>
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>
                Approve or reject pending memos from all departments
            </div>
        </div>

        <?php if (empty($pendingByDept)): ?>
            <div class="text-center py-16 bg-white rounded-lg shadow-md">
                <i class="fas fa-check-circle fa-4x text-green-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">All Caught Up!</h2>
                <p class="text-gray-600">No pending memos require your attention at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingByDept as $deptId => $deptData): ?>
                <div class="ceit-memo-dept-section">
                    <h2 class="ceit-memo-dept-title">
                        <i class="fas fa-building mr-2"></i>
                        <?= htmlspecialchars($deptData['dept_info']['dept_name']) ?>
                        (<?= htmlspecialchars($deptData['dept_info']['acronym']) ?>)
                        <span class="text-sm font-normal ml-2">
                            - <?= count($deptData['memos']) ?> pending memo(s)
                        </span>
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach ($deptData['memos'] as $index => $memo): ?>
                            <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105">
                                <div class="mb-3 border border-gray-300 rounded">
                                    <div id="ceit-memo-file-preview-<?= $deptId ?>-<?= $index ?>" class="ceit-memo-file-preview" data-file-path="<?= $memo['file_path'] ?>">
                                        <?php if ($memo['file_type'] === 'pdf'): ?>
                                            <div class="ceit-memo-loading-spinner"></div>
                                        <?php elseif (in_array($memo['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?= $memo['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                                onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image ceit-memo-file-icon jpg text-4xl\'></i>'">
                                        <?php else: ?>
                                            <i class="fas fa-file ceit-memo-file-icon <?= $memo['file_type'] ?> text-4xl"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body flex-grow">
                                    <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                        <?= htmlspecialchars($memo['description']) ?>
                                    </div>
                                    <p class="card-text text-gray-600 text-sm truncate">
                                        <?= basename($memo['file_path']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Posted by: <?= htmlspecialchars($memo['user_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Posted on: <?= date('F j, Y', strtotime($memo['posted_on'])) ?>
                                    </p>
                                </div>
                                <div class="flex justify-between mt-4 space-x-2 text-xs">
                                    <button id="ceit-memo-view-full-<?= $deptId ?>-<?= $index ?>"
                                        class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110"
                                        title="View Full Document"
                                        data-file-type="<?= $memo['file_type'] ?>"
                                        data-file-path="<?= $memo['file_path'] ?>">
                                        <i class="fas fa-eye fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 ceit-memo-approve-btn"
                                        data-id="<?= $memo['id'] ?>"
                                        data-description="<?= htmlspecialchars($memo['description']) ?>"
                                        title="Approve">
                                        <i class="fas fa-check fa-sm"></i>
                                    </button>
                                    <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 ceit-memo-reject-btn"
                                        data-id="<?= $memo['id'] ?>"
                                        data-description="<?= htmlspecialchars($memo['description']) ?>"
                                        title="Reject">
                                        <i class="fas fa-times fa-sm"></i>
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
        <?php foreach ($deptData['memos'] as $index => $memo): ?>
            <div id="ceit-memo-file-modal-<?= $deptId ?>-<?= $index ?>" class="ceit-memo-file-modal">
                <div class="ceit-memo-modal-content">
                    <div class="ceit-memo-modal-header">
                        <h3 class="ceit-memo-modal-title"><?= htmlspecialchars($memo['description']) ?></h3>
                        <span class="ceit-memo-modal-close" onclick="window.closeCeitMemoFileModal('<?= $deptId ?>', <?= $index ?>)">&times;</span>
                    </div>
                    <div class="ceit-memo-modal-body">
                        <div id="ceit-memo-pdf-container-<?= $deptId ?>-<?= $index ?>" class="ceit-memo-pdf-container">
                            <div class="ceit-memo-loading-spinner"></div>
                            <p class="text-center text-gray-600">Loading memo...</p>
                        </div>
                    </div>
                    <div class="ceit-memo-modal-footer">
                        <div class="ceit-memo-modal-meta">
                            Posted by: <?= htmlspecialchars($memo['user_name']) ?> on <?= date('F j, Y', strtotime($memo['posted_on'])) ?> | File: <?= basename($memo['file_path']) ?>
                        </div>
                        <?php if ($memo['file_type'] === 'pdf'): ?>
                            <div class="ceit-memo-page-navigation">
                                <button id="ceit-memo-prev-page-btn-<?= $deptId ?>-<?= $index ?>"
                                    class="ceit-memo-page-nav-btn" disabled
                                    onclick="window.goToCeitMemoPrev('<?= $deptId ?>', <?= $index ?>)">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div id="ceit-memo-page-indicator-<?= $deptId ?>-<?= $index ?>" class="ceit-memo-page-indicator">Page 1 of 1</div>
                                <button id="ceit-memo-next-page-btn-<?= $deptId ?>-<?= $index ?>"
                                    class="ceit-memo-page-nav-btn" disabled
                                    onclick="window.goToCeitMemoNext('<?= $deptId ?>', <?= $index ?>)">
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
    <div id="ceit-memo-approve-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-green-600">Approve Memo</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to approve this memo?</p>
                <p class="font-semibold mt-2" id="ceit-memo-approve-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="ceit-memo-cancel-approve-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="ceit-memo-confirm-approve-btn">
                    <i class="fas fa-check mr-2"></i> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="ceit-memo-reject-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Reject Memo</h3>
            </div>
            <div class="mb-4">
                <p class="mb-3">Please provide a reason for rejecting this memo:</p>
                <p class="font-semibold mb-3" id="ceit-memo-reject-title"></p>
                <textarea id="ceit-memo-reject-reason"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                    rows="4"
                    placeholder="Enter rejection reason..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="ceit-memo-cancel-reject-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="ceit-memo-confirm-reject-btn">
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
        window.ceitMemoPdfDocs = {};
        window.ceitMemoCurrentPageNum = {};
        window.ceitMemoTotalPages = {};
        window.ceitMemoIsRendering = {};

        // Global variables for approval/rejection
        let currentCeitMemoId = null;

        // Initialize module - RENAMED to match Main_dashboard expectation
        function initializeMemosModule() {
            console.log('Initializing CEIT Memos module for Manage_Modules...');

            initializeCeitMemoViewButtons();
            initializeCeitMemoApprovalRejectionButtons();
            initializeCeitMemoPDFPreviews();

            console.log('CEIT Memos module initialized for Manage_Modules');
        }

        // Initialize view buttons
        function initializeCeitMemoViewButtons() {
            console.log('Initializing view buttons for Manage Memos...');
            document.querySelectorAll('[id^="ceit-memo-view-full-"]').forEach(button => {
                console.log('Found view button:', button.id);
                button.addEventListener('click', handleCeitMemoViewButtonClick);
            });
        }

        // Handle view button click
        function handleCeitMemoViewButtonClick(event) {
            console.log('View button clicked:', this.id);
            const button = this;
            const buttonId = button.id; // e.g., "ceit-memo-view-full-1-0"
            
            // Extract deptId and index from the button ID
            // Button ID format: ceit-memo-view-full-{deptId}-{index}
            const match = buttonId.match(/ceit-memo-view-full-(.+)-(\d+)$/);
            if (!match) {
                console.error('Could not parse button ID:', buttonId);
                return;
            }
            
            const deptId = match[1];
            const index = match[2];

            console.log('DeptId:', deptId, 'Index:', index);

            const modalId = `ceit-memo-file-modal-${deptId}-${index}`;
            const containerId = `ceit-memo-pdf-container-${deptId}-${index}`;
            const fileType = button.dataset.fileType;
            const filePath = button.dataset.filePath;

            console.log('Looking for modal:', modalId);
            console.log('Looking for container:', containerId);
            console.log('File path:', filePath);

            const modal = document.getElementById(modalId);
            const container = document.getElementById(containerId);

            if (!modal) {
                console.error('Modal not found:', modalId);
                return;
            }
            if (!container) {
                console.error('Container not found:', containerId);
                return;
            }

            console.log('Showing modal...');
            modal.style.display = "block";
            displayCeitMemoFileContent(fileType, filePath, deptId, index, container);
        }

        // Display file content
        function displayCeitMemoFileContent(fileType, filePath, deptId, index, container) {
            console.log('Displaying file content:', fileType, filePath);
            const fileExtension = filePath.split('.').pop().toLowerCase();

            container.innerHTML = `
                <div class="ceit-memo-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading file...</p>
            `;

            // Construct the absolute URL - the file path already starts with 'uploads/'
            const baseUrl = window.location.origin;
            let fullUrl = `${baseUrl}/Testing/${filePath}`;
            
            console.log('Full URL:', fullUrl);

            if (fileExtension === 'pdf') {
                console.log('Loading PDF file...');
                loadCeitMemoPDFFile(fullUrl, deptId, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                console.log('Loading image file...');
                container.innerHTML = `
                    <div class="ceit-memo-image-container">
                        <img src="${fullUrl}" alt="Full view" class="ceit-memo-image-viewer" 
                             onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<div class=\"text-center p-8\"><i class=\"fas fa-exclamation-triangle text-red-500 text-4xl mb-4\"></i><p class=\"text-lg text-gray-700\">Failed to load image</p></div>'">
                    </div>
                `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                console.log('Loading office document...');
                const encodedUrl = encodeURIComponent(fullUrl);
                container.innerHTML = `
                    <div class="ceit-memo-office-viewer" style="width: 100%; height: 100%; border: none; background: #fff;">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=${encodedUrl}" 
                            style="width: 100%; height: 100%; border: none;"
                            frameborder="0"
                            allowfullscreen>
                        </iframe>
                    </div>
                `;
            } else {
                console.log('Unsupported file type:', fileExtension);
                container.innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-file text-gray-400 text-6xl mb-4"></i>
                        <p class="text-lg text-gray-700 mb-2">Preview not available</p>
                        <p class="text-gray-600 mb-4">This file type cannot be previewed in browser.</p>
                        <p class="text-gray-600">File: ${filePath.split('/').pop()}</p>
                    </div>
                `;
            }
        }

        // Load PDF file
        function loadCeitMemoPDFFile(filePath, deptId, index, container) {
            const key = `${deptId}-${index}`;

            container.innerHTML = `
                <div class="ceit-memo-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading PDF document...</p>
            `;

            console.log('Loading PDF from:', filePath);

            pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                console.log('PDF loaded successfully, pages:', pdfDoc.numPages);
                window.ceitMemoPdfDocs[key] = pdfDoc;
                window.ceitMemoTotalPages[key] = pdfDoc.numPages;
                window.ceitMemoCurrentPageNum[key] = 1;
                window.ceitMemoIsRendering[key] = false;

                const pageIndicator = document.getElementById(`ceit-memo-page-indicator-${deptId}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdfDoc.numPages}`;
                }

                const prevBtn = document.getElementById(`ceit-memo-prev-page-btn-${deptId}-${index}`);
                const nextBtn = document.getElementById(`ceit-memo-next-page-btn-${deptId}-${index}`);

                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdfDoc.numPages <= 1;

                setTimeout(() => {
                    renderCeitMemoPDFPage(deptId, index, 1);
                }, 100);

            }).catch(error => {
                console.error('PDF load error:', error);
                container.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-lg text-gray-700 mb-2">Failed to load PDF</p>
                        <p class="text-gray-600">${error.message}</p>
                        <p class="text-sm text-gray-500 mt-4">URL: ${filePath}</p>
                    </div>
                `;
            });
        }

        // Render PDF page - IMPROVED
        function renderCeitMemoPDFPage(deptId, index, pageNum) {
            return new Promise((resolve, reject) => {
                const key = `${deptId}-${index}`;
                if (!window.ceitMemoPdfDocs[key]) {
                    console.error('PDF document not found for key:', key);
                    reject(new Error('PDF document not found'));
                    return;
                }

                const container = document.getElementById(`ceit-memo-pdf-container-${deptId}-${index}`);
                if (!container) {
                    reject(new Error('Container not found'));
                    return;
                }

                container.innerHTML = `
                    <div class="ceit-memo-loading-spinner"></div>
                    <p class="text-center text-gray-600">Rendering page ${pageNum}...</p>
                `;

                window.ceitMemoPdfDocs[key].getPage(pageNum).then(page => {
                    const modal = document.querySelector(`#ceit-memo-file-modal-${deptId}-${index}`);
                    if (!modal) {
                        reject(new Error('Modal not found'));
                        return;
                    }

                    const modalBody = modal.querySelector('.ceit-memo-modal-body');
                    if (!modalBody) {
                        reject(new Error('Modal body not found'));
                        return;
                    }

                    const availableWidth = modalBody.clientWidth - 40;
                    const availableHeight = modalBody.clientHeight - 40;

                    const viewport = page.getViewport({
                        scale: 1
                    });
                    const scale = Math.min(
                        availableWidth / viewport.width,
                        availableHeight / viewport.height,
                        1.5
                    );

                    const scaledViewport = page.getViewport({
                        scale
                    });

                    const canvas = document.createElement("canvas");
                    const ctx = canvas.getContext("2d");

                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    canvas.style.maxWidth = "100%";
                    canvas.style.maxHeight = "100%";
                    canvas.style.display = "block";
                    canvas.style.margin = "0 auto";

                    container.innerHTML = "";
                    container.appendChild(canvas);

                    const renderTask = page.render({
                        canvasContext: ctx,
                        viewport: scaledViewport
                    });

                    renderTask.promise.then(() => {
                        console.log('Page', pageNum, 'rendered successfully');
                        resolve();
                    }).catch(reject);
                }).catch(reject);
            });
        }

        // Initialize PDF previews
        function initializeCeitMemoPDFPreviews() {
            document.querySelectorAll('[id^="ceit-memo-file-preview-"]').forEach(preview => {
                const filePath = preview.dataset.filePath;
                if (filePath && filePath.toLowerCase().endsWith('.pdf')) {
                    loadCeitMemoPDFPreview(preview, filePath);
                }
            });
        }

        // Load PDF preview
        function loadCeitMemoPDFPreview(previewElement, filePath) {
            const baseUrl = window.location.origin;
            const fullUrl = `${baseUrl}/Testing/${filePath}`;

            pdfjsLib.getDocument(fullUrl).promise.then(pdfDoc => {
                return pdfDoc.getPage(1);
            }).then(page => {
                const scale = 0.5;
                const viewport = page.getViewport({
                    scale
                });

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
                console.error('PDF preview error:', error);
                previewElement.innerHTML = '<i class="fas fa-file-pdf ceit-memo-file-icon pdf text-4xl"></i>';
            });
        }

        // Initialize approval/rejection buttons
        function initializeCeitMemoApprovalRejectionButtons() {
            // Approve buttons
            document.querySelectorAll('.ceit-memo-approve-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    currentCeitMemoId = id;
                    document.getElementById('ceit-memo-approve-title').textContent = description;
                    document.getElementById('ceit-memo-approve-modal').classList.remove('hidden');
                });
            });

            // Reject buttons
            document.querySelectorAll('.ceit-memo-reject-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    currentCeitMemoId = id;
                    document.getElementById('ceit-memo-reject-title').textContent = description;
                    document.getElementById('ceit-memo-reject-reason').value = '';
                    document.getElementById('ceit-memo-reject-modal').classList.remove('hidden');
                });
            });

            // Modal event listeners
            document.getElementById('ceit-memo-cancel-approve-btn').addEventListener('click', function() {
                document.getElementById('ceit-memo-approve-modal').classList.add('hidden');
                currentCeitMemoId = null;
            });

            document.getElementById('ceit-memo-cancel-reject-btn').addEventListener('click', function() {
                document.getElementById('ceit-memo-reject-modal').classList.add('hidden');
                currentCeitMemoId = null;
            });

            // Confirm approve
            document.getElementById('ceit-memo-confirm-approve-btn').addEventListener('click', function() {
                if (!currentCeitMemoId) return;

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Approving...';

                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('id', currentCeitMemoId);

                // FIX: Use absolute path to directly target Memos.php
                const memoFileUrl = '/Testing/Manage_Modules/Memos/Memos.php';

                fetch(memoFileUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showCeitMemoNotification(data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showCeitMemoNotification(data.message, 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-check mr-2"></i> Approve';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showCeitMemoNotification('An error occurred while approving memo', 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-check mr-2"></i> Approve';
                    });
            });

            // Confirm reject
            document.getElementById('ceit-memo-confirm-reject-btn').addEventListener('click', function() {
                if (!currentCeitMemoId) return;

                const reason = document.getElementById('ceit-memo-reject-reason').value.trim();
                if (!reason) {
                    showCeitMemoNotification('Please provide a reason for rejection', 'error');
                    return;
                }

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Rejecting...';

                const formData = new FormData();
                formData.append('action', 'reject');
                formData.append('id', currentCeitMemoId);
                formData.append('reason', reason);

                // FIX: Use absolute path to directly target Memos.php
                const memoFileUrl = '/Testing/Manage_Modules/Memos/Memos.php';

                fetch(memoFileUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showCeitMemoNotification(data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showCeitMemoNotification(data.message, 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-times mr-2"></i> Reject';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showCeitMemoNotification('An error occurred while rejecting memo', 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-times mr-2"></i> Reject';
                    });
            });

            // Close modals when clicking outside
            document.getElementById('ceit-memo-approve-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    currentCeitMemoId = null;
                }
            });

            document.getElementById('ceit-memo-reject-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    currentCeitMemoId = null;
                }
            });
        }

        // Show notification
        function showCeitMemoNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `ceit-memo-notification ${type}`;
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
        function closeCeitMemoFileModal(deptId, index) {
            const modal = document.getElementById(`ceit-memo-file-modal-${deptId}-${index}`);
            if (modal) {
                modal.style.display = "none";
            }
        }

        // Navigation functions
        function goToCeitMemoPrev(deptId, index) {
            const key = `${deptId}-${index}`;
            if (!window.ceitMemoPdfDocs || !window.ceitMemoPdfDocs[key]) {
                console.warn('PDF not available for navigation, key:', key);
                return;
            }

            if (window.ceitMemoIsRendering[key]) {
                console.warn('Already rendering, please wait');
                return;
            }

            if (window.ceitMemoCurrentPageNum[key] > 1) {
                window.ceitMemoIsRendering[key] = true;

                const prevBtn = document.getElementById(`ceit-memo-prev-page-btn-${deptId}-${index}`);
                const nextBtn = document.getElementById(`ceit-memo-next-page-btn-${deptId}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.ceitMemoCurrentPageNum[key]--;

                const pageIndicator = document.getElementById(`ceit-memo-page-indicator-${deptId}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.ceitMemoCurrentPageNum[key]} of ${window.ceitMemoTotalPages[key]}`;
                }

                renderCeitMemoPDFPage(deptId, index, window.ceitMemoCurrentPageNum[key]).then(() => {
                    if (prevBtn) prevBtn.disabled = window.ceitMemoCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.ceitMemoCurrentPageNum[key] === window.ceitMemoTotalPages[key];
                    window.ceitMemoIsRendering[key] = false;
                }).catch(error => {
                    console.error('Error rendering previous page:', error);
                    window.ceitMemoIsRendering[key] = false;
                });
            }
        }

        function goToCeitMemoNext(deptId, index) {
            const key = `${deptId}-${index}`;
            if (!window.ceitMemoPdfDocs || !window.ceitMemoPdfDocs[key]) {
                console.warn('PDF not available for navigation, key:', key);
                return;
            }

            if (window.ceitMemoIsRendering[key]) {
                console.warn('Already rendering, please wait');
                return;
            }

            if (window.ceitMemoCurrentPageNum[key] < window.ceitMemoTotalPages[key]) {
                window.ceitMemoIsRendering[key] = true;

                const prevBtn = document.getElementById(`ceit-memo-prev-page-btn-${deptId}-${index}`);
                const nextBtn = document.getElementById(`ceit-memo-next-page-btn-${deptId}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.ceitMemoCurrentPageNum[key]++;

                const pageIndicator = document.getElementById(`ceit-memo-page-indicator-${deptId}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.ceitMemoCurrentPageNum[key]} of ${window.ceitMemoTotalPages[key]}`;
                }

                renderCeitMemoPDFPage(deptId, index, window.ceitMemoCurrentPageNum[key]).then(() => {
                    if (prevBtn) prevBtn.disabled = window.ceitMemoCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.ceitMemoCurrentPageNum[key] === window.ceitMemoTotalPages[key];
                    window.ceitMemoIsRendering[key] = false;
                }).catch(error => {
                    console.error('Error rendering next page:', error);
                    window.ceitMemoIsRendering[key] = false;
                });
            }
        }

        // Initialize when DOM is ready
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initializeMemosModule, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeMemosModule, 100);
            });
        }

        // Make functions globally available
        window.closeCeitMemoFileModal = closeCeitMemoFileModal;
        window.goToCeitMemoPrev = goToCeitMemoPrev;
        window.goToCeitMemoNext = goToCeitMemoNext;
    </script>
</body>

</html>