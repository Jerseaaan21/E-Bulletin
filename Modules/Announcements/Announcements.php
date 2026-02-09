<?php
// Modules/Announcements/Announcements.php
include "../../db.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];
$deptId = $_SESSION['dept_id'] ?? 0;

// Get department acronym from database
$dept_acronym = 'default'; // fallback

// Query the departments table to get the acronym
$query = "SELECT acronym FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dept_acronym = $row['acronym'];
}

// Create dynamic upload path
$uploadBaseDir = "../../uploads/{$dept_acronym}/Announcement/"; // Changed from Announcements to Announcement

// Get module ID for Announcements
$moduleQuery = "SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Get pending announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = ? AND dp.dept_id = ? AND dp.status = 'Pending' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $moduleId, $deptId);
$stmt->execute();
$result = $stmt->get_result();

$pendingAnnouncements = [];
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $filePath = $uploadBaseDir . $row['file_path'];
    $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path']; // Changed from Announcements to Announcement

    $pendingAnnouncements[] = [
        'id' => $row['id'],
        'file_path' => $relativeFilePath,
        'description' => $row['description'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Get approved announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = ? AND dp.dept_id = ? AND dp.status = 'Approved' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $moduleId, $deptId);
$stmt->execute();
$result = $stmt->get_result();

$approvedAnnouncements = [];
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $filePath = $uploadBaseDir . $row['file_path'];
    $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path']; // Changed from Announcements to Announcement

    $approvedAnnouncements[] = [
        'id' => $row['id'],
        'file_path' => $relativeFilePath,
        'description' => $row['description'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Get not approved announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = ? AND dp.dept_id = ? AND dp.status = 'Not Approved' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $moduleId, $deptId);
$stmt->execute();
$result = $stmt->get_result();

$notApprovedAnnouncements = [];
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $filePath = $uploadBaseDir . $row['file_path'];
    $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path']; // Changed from Announcements to Announcement

    $notApprovedAnnouncements[] = [
        'id' => $row['id'],
        'file_path' => $relativeFilePath,
        'description' => $row['description'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'rejection_reason' => $row['content'], // Rejection reason stored in content field
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Get archived announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = ? AND dp.dept_id = ? AND dp.status = 'Archived' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $moduleId, $deptId);
$stmt->execute();
$result = $stmt->get_result();

$archivedAnnouncements = [];
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $filePath = $uploadBaseDir . $row['file_path'];
    $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path']; // Note: archived files still use "Announcements" directory

    $archivedAnnouncements[] = [
        'id' => $row['id'],
        'file_path' => $relativeFilePath,
        'description' => $row['description'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Announcement Management</title>
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
            /* Changed from violet to orange */
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
            /* Changed from violet to orange */
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
            /* Darker orange for hover */
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
            /* Changed from violet to orange */
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
            /* Changed to match orange theme */
        }

        .announcement-file-icon.jpg,
        .announcement-file-icon.jpeg,
        .announcement-file-icon.png,
        .announcement-file-icon.gif {
            color: #ea580c;
            /* Changed to match orange theme */
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

        /* Status sections */
        .announcement-status-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
        }

        .announcement-status-section.pending {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
        }

        .announcement-status-section.approved {
            background-color: #d1fae5;
            border: 1px solid #6ee7b7;
        }

        .announcement-status-section.not-approved {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
        }

        .announcement-status-section.archived {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .announcement-status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }

        .announcement-status-section.pending .announcement-status-title {
            color: #d97706;
            border-color: #d97706;
        }

        .announcement-status-section.approved .announcement-status-title {
            color: #059669;
            border-color: #059669;
        }

        .announcement-status-section.not-approved .announcement-status-title {
            color: #dc2626;
            border-color: #dc2626;
        }

        .announcement-status-section.archived .announcement-status-title {
            color: #2563eb;
            border-color: #2563eb;
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
            /* Changed from violet to orange */
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

        /* Rejection reason tooltip */
        .announcement-rejection-tooltip {
            position: relative;
            display: inline-block;
        }

        .announcement-rejection-tooltip .announcement-tooltip-text {
            visibility: hidden;
            width: 250px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -125px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }

        .announcement-rejection-tooltip:hover .announcement-tooltip-text {
            visibility: visible;
            opacity: 1;
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

            .announcement-status-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .announcement-status-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .announcement-file-preview canvas {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                display: block;
                margin: 0 auto;
            }

            .announcement-file-preview {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0"> <!-- Changed from purple to orange -->
                <i class="fas fa-bullhorn mr-3 w-5"></i> Department Announcement Management
            </h1>
            <button id="announcement-upload-btn"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110"> <!-- Changed from purple to orange -->
                <i class="fas fa-upload mr-2"></i> Upload Announcement
            </button>
        </div>

        <!-- Pending Announcements -->
        <div class="announcement-status-section pending">
            <h2 class="announcement-status-title">Pending Announcements</h2>
            <?php if (count($pendingAnnouncements) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($pendingAnnouncements as $index => $announcement): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-pending-<?= $index ?>" class="announcement-file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
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
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-pending-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $announcement['file_type'] ?>" data-file-path="<?= $announcement['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-download-btn" data-file-path="<?= $announcement['file_path'] ?>" data-file-name="<?= basename($announcement['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-edit-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-file-path="<?= $announcement['file_path'] ?>" data-status="pending" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-delete-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-status="pending" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-clock fa-3x mb-4"></i>
                    <p class="text-lg">No pending announcements</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Approved Announcements -->
        <div class="announcement-status-section approved">
            <h2 class="announcement-status-title">Approved Announcements</h2>
            <?php if (count($approvedAnnouncements) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($approvedAnnouncements as $index => $announcement): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-green-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-approved-<?= $index ?>" class="announcement-file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
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
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $announcement['file_type'] ?>" data-file-path="<?= $announcement['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-download-btn" data-file-path="<?= $announcement['file_path'] ?>" data-file-name="<?= basename($announcement['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-archive-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-status="approved" title="Archive">
                                    <i class="fas fa-archive fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-check-circle fa-3x mb-4"></i>
                    <p class="text-lg">No approved announcements</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Not Approved Announcements -->
        <div class="announcement-status-section not-approved">
            <h2 class="announcement-status-title">Not Approved Announcements</h2>
            <?php if (count($notApprovedAnnouncements) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($notApprovedAnnouncements as $index => $announcement): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-red-500 transition duration-200 transform hover:scale-105 opacity-75">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-not-approved-<?= $index ?>" class="announcement-file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
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
                                <?php if (!empty($announcement['rejection_reason'])): ?>
                                    <div class="announcement-rejection-tooltip mt-2">
                                        <button class="text-xs text-red-500 hover:text-red-700 flex items-center">
                                            <i class="fas fa-info-circle mr-1"></i> Rejection Reason
                                        </button>
                                        <span class="announcement-tooltip-text"><?= htmlspecialchars($announcement['rejection_reason']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-not-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $announcement['file_type'] ?>" data-file-path="<?= $announcement['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-download-btn" data-file-path="<?= $announcement['file_path'] ?>" data-file-name="<?= basename($announcement['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-edit-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-file-path="<?= $announcement['file_path'] ?>" data-status="not-approved" title="Edit & Resubmit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-delete-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-status="not-approved" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-times-circle fa-3x mb-4"></i>
                    <p class="text-lg">No rejected announcements</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Announcements -->
        <div class="announcement-status-section archived">
            <h2 class="announcement-status-title">Archived Announcements</h2>
            <?php if (count($archivedAnnouncements) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105 opacity-75">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-archived-<?= $index ?>" class="announcement-file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
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
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $announcement['file_type'] ?>" data-file-path="<?= $announcement['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-download-btn" data-file-path="<?= $announcement['file_path'] ?>" data-file-name="<?= basename($announcement['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-restore-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" title="Restore">
                                    <i class="fas fa-undo fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-delete-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-status="archived" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-archive fa-3x mb-4"></i>
                    <p class="text-lg">No archived announcements</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File View Modals -->
    <?php foreach ($pendingAnnouncements as $index => $announcement): ?>
        <div id="announcement-file-modal-pending-<?= $index ?>" class="announcement-file-modal">
            <div class="announcement-modal-content">
                <div class="announcement-modal-header">
                    <h3 class="announcement-modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="announcement-modal-close" onclick="closeAnnouncementFileModal('pending', <?= $index ?>)">&times;</span>
                </div>
                <div class="announcement-modal-body">
                    <div id="announcement-pdf-container-pending-<?= $index ?>" class="announcement-pdf-container">
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
                            <button id="announcement-prev-page-btn-pending-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="announcement-page-indicator-pending-<?= $index ?>" class="announcement-page-indicator">Page 1 of 1</div>
                            <button id="announcement-next-page-btn-pending-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($approvedAnnouncements as $index => $announcement): ?>
        <div id="announcement-file-modal-approved-<?= $index ?>" class="announcement-file-modal">
            <div class="announcement-modal-content">
                <div class="announcement-modal-header">
                    <h3 class="announcement-modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="announcement-modal-close" onclick="closeAnnouncementFileModal('approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="announcement-modal-body">
                    <div id="announcement-pdf-container-approved-<?= $index ?>" class="announcement-pdf-container">
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
                            <button id="announcement-prev-page-btn-approved-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="announcement-page-indicator-approved-<?= $index ?>" class="announcement-page-indicator">Page 1 of 1</div>
                            <button id="announcement-next-page-btn-approved-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($notApprovedAnnouncements as $index => $announcement): ?>
        <div id="announcement-file-modal-not-approved-<?= $index ?>" class="announcement-file-modal">
            <div class="announcement-modal-content">
                <div class="announcement-modal-header">
                    <h3 class="announcement-modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="announcement-modal-close" onclick="closeAnnouncementFileModal('not-approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="announcement-modal-body">
                    <div id="announcement-pdf-container-not-approved-<?= $index ?>" class="announcement-pdf-container">
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
                            <button id="announcement-prev-page-btn-not-approved-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="announcement-page-indicator-not-approved-<?= $index ?>" class="announcement-page-indicator">Page 1 of 1</div>
                            <button id="announcement-next-page-btn-not-approved-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
        <div id="announcement-file-modal-archived-<?= $index ?>" class="announcement-file-modal">
            <div class="announcement-modal-content">
                <div class="announcement-modal-header">
                    <h3 class="announcement-modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="announcement-modal-close" onclick="closeAnnouncementFileModal('archived', <?= $index ?>)">&times;</span>
                </div>
                <div class="announcement-modal-body">
                    <div id="announcement-pdf-container-archived-<?= $index ?>" class="announcement-pdf-container">
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
                            <button id="announcement-prev-page-btn-archived-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="announcement-page-indicator-archived-<?= $index ?>" class="announcement-page-indicator">Page 1 of 1</div>
                            <button id="announcement-next-page-btn-archived-<?= $index ?>" class="announcement-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Upload Modal -->
    <div id="announcement-upload-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Upload Announcement</h2>
            <form id="announcement-upload-form" action="Modules/Announcements/AddAnnouncement.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="announcement-description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input
                        type="text"
                        id="announcement-description"
                        name="description"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500"> <!-- Changed from purple to orange -->
                </div>
                <div>
                    <label for="announcement-file" class="block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        id="announcement-file"
                        name="pdfFile"
                        accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        required
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100"> <!-- Changed from purple to orange -->

                </div>
                <div class="flex justify-end space-x-3 text-sm">
                    <button
                        type="button"
                        id="announcement-cancel-upload-btn"
                        class="px-4 py-2 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white transition duration-200 transform hover:scale-110"> <!-- Changed from purple to orange -->
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="announcement-edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="announcement-edit-modal-title">Edit Announcement</h3>
                <form id="announcement-edit-form" class="mt-2 py-3" enctype="multipart/form-data">
                    <input type="hidden" id="announcement-edit-id" name="id">
                    <input type="hidden" id="announcement-edit-status" name="status">
                    <div class="mb-4">
                        <label for="announcement-edit-description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <input type="text" id="announcement-edit-description" name="description"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label for="announcement-edit-file" class="block text-sm font-medium text-gray-700">Replace File (optional)</label>
                        <input
                            type="file"
                            id="announcement-edit-file"
                            name="pdfFile"
                            accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100"> <!-- Changed from purple to orange -->
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="announcement-cancel-edit"
                            class="px-4 py-2 border border-gray-300 text-gray-500 hover:bg-gray-500 hover:text-white font-medium rounded-lg shadow-sm transition duration-200 transform hover:scale-110">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white font-medium rounded-lg shadow-sm transition duration-200 transform hover:scale-110">
                            <i class="fas fa-save fa-sm"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive/Delete/Restore Confirmation Modal for Announcements -->
    <div id="announcement-delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-yellow-600" id="announcement-modal-title">Archive Announcement</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to <span id="announcement-action-text">archive</span> this announcement?</p>
                <p class="font-semibold mt-2" id="announcement-delete-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="announcement-cancel-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200" id="announcement-archive-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="announcement-restore-btn" style="display: none;">
                    <i class="fas fa-undo mr-2"></i> Restore
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="announcement-confirm-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        // Set PDF.js worker source
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        // Global variables for PDF handling - announcement specific
        window.announcementPdfDocs = {};
        window.announcementCurrentPageNum = {};
        window.announcementTotalPages = {};
        window.announcementIsRendering = {};

        // Global variable to track active requests - announcement specific
        const announcementActiveRequests = {
            archive: false,
            delete: false,
            restore: false,
            edit: false
        };

        // Function to initialize the module - can be called from dashboard
        function initializeAnnouncementsModule() {
            console.log('Initializing Announcements module...');

            // Prevent multiple initializations
            if (window.announcementsModuleInitialized) {
                console.log('Announcements module already initialized, reinitializing...');
                // Force reinitialize PDF previews
                initializeAnnouncementPDFPreviews();
                return;
            }

            window.announcementsModuleInitialized = true;

            // Initialize modal event listeners
            initializeAnnouncementModalEventListeners();

            // Re-initialize all functionality
            if (typeof pdfjsLib !== 'undefined') {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';
                initializeAnnouncementPDFPreviews();
            }

            initializeAnnouncementViewButtons();
            initializeAnnouncementPageNavigation();
            initializeAnnouncementOtherFunctionality();

            console.log('Announcements module initialized');
        }

        // For direct access (not through dashboard)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(() => {
                // Only initialize if not already initialized by dashboard
                if (!window.announcementsModuleInitialized) {
                    initializeAnnouncementsModule();
                    window.announcementsModuleInitialized = true;
                }
            }, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => {
                    // Only initialize if not already initialized by dashboard
                    if (!window.announcementsModuleInitialized) {
                        initializeAnnouncementsModule();
                        window.announcementsModuleInitialized = true;
                    }
                }, 100);
            });
        }

        // Initialize modal event listeners
        function initializeAnnouncementModalEventListeners() {
            // Upload button - show modal
            document.getElementById('announcement-upload-btn').addEventListener('click', function() {
                console.log('Upload button clicked');
                document.getElementById('announcement-upload-modal').classList.remove('hidden');
            });

            // Cancel upload button - hide modal
            document.getElementById('announcement-cancel-upload-btn').addEventListener('click', function() {
                console.log('Cancel upload button clicked');
                document.getElementById('announcement-upload-modal').classList.add('hidden');
                document.getElementById('announcement-upload-form').reset();
            });

            // Close upload modal when clicking outside
            const uploadModal = document.getElementById('announcement-upload-modal');
            if (uploadModal) {
                uploadModal.addEventListener('click', function(e) {
                    if (e.target === uploadModal) {
                        console.log('Clicked outside upload modal - closing');
                        uploadModal.classList.add('hidden');
                        document.getElementById('announcement-upload-form').reset();
                    }
                });
            }

            // Upload form submission
            const uploadForm = document.getElementById('announcement-upload-form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Check if form is already submitting
                    if (this.dataset.submitting === 'true') {
                        return;
                    }

                    // Mark form as submitting
                    this.dataset.submitting = 'true';

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;

                    // Show loading state
                    submitBtn.textContent = 'Uploading...';
                    submitBtn.disabled = true;

                    fetch('Modules/Announcements/AddAnnouncement.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            // Check if response is ok (status in the range 200-299)
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Reset form state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            if (data.success) {
                                // Show success notification
                                showAnnouncementNotification(data.message || 'Announcement uploaded successfully!', 'success');

                                // Close modal and reload after a short delay
                                setTimeout(() => {
                                    document.getElementById('announcement-upload-modal').classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showAnnouncementNotification(data.message || 'Error uploading announcement', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset form state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            // Show error notification
                            showAnnouncementNotification('An error occurred while uploading the announcement: ' + error.message, 'error');
                        });
                });
            }

            // Edit modal functionality
            const editModal = document.getElementById('announcement-edit-modal');
            const editForm = document.getElementById('announcement-edit-form');

            // Cancel edit button
            document.getElementById('announcement-cancel-edit').addEventListener('click', function() {
                editModal.classList.add('hidden');
            });

            // Close edit modal when clicking outside
            if (editModal) {
                editModal.addEventListener('click', function(e) {
                    if (e.target === editModal) {
                        editModal.classList.add('hidden');
                    }
                });
            }

            // Edit form submission
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const id = document.getElementById('announcement-edit-id').value;
                    const status = document.getElementById('announcement-edit-status').value;
                    const description = document.getElementById('announcement-edit-description').value;
                    const fileInput = document.getElementById('announcement-edit-file');

                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('status', status);
                    formData.append('description', description);

                    if (fileInput.files.length > 0) {
                        const now = new Date();
                        const day = now.getDate().toString().padStart(2, '0');
                        const month = (now.getMonth() + 1).toString().padStart(2, '0');
                        const year = now.getFullYear();
                        const hours = now.getHours().toString().padStart(2, '0');
                        const minutes = now.getMinutes().toString().padStart(2, '0');
                        const seconds = now.getSeconds().toString().padStart(2, '0');
                        const random = Math.floor(Math.random() * 9000) + 1000;

                        const fileName = fileInput.files[0].name;
                        const fileExtension = fileName.split('.').pop();
                        const newFilename = `announcement_${day}_${month}_${year}_${hours}_${minutes}_${seconds}_${random}.${fileExtension}`;

                        const originalFile = fileInput.files[0];
                        const renamedFile = new File([originalFile], newFilename, {
                            type: originalFile.type
                        });

                        formData.append('pdfFile', renamedFile);
                    }

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;

                    // Show loading state
                    submitBtn.textContent = 'Updating...';
                    submitBtn.disabled = true;

                    fetch('Modules/Announcements/update_announcement.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Reset button state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;

                            if (data.success) {
                                // Show success notification
                                showAnnouncementNotification(data.message || 'Announcement updated successfully!', 'success');

                                // Hide modal and reload after a short delay
                                setTimeout(() => {
                                    editModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showAnnouncementNotification(data.message || 'Error updating announcement', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset button state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;

                            // Show error notification
                            showAnnouncementNotification('An error occurred while updating the announcement: ' + error.message, 'error');
                        });
                });
            }

            // Edit buttons
            document.querySelectorAll('.announcement-edit-btn').forEach(button => {
                // Remove any existing event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');
                    const status = this.getAttribute('data-status');

                    document.getElementById('announcement-edit-id').value = id;
                    document.getElementById('announcement-edit-description').value = description;
                    document.getElementById('announcement-edit-status').value = status;
                    document.getElementById('announcement-edit-file').value = '';

                    // Update modal title based on status
                    const modalTitle = document.getElementById('announcement-edit-modal-title');
                    if (status === 'not-approved') {
                        modalTitle.textContent = 'Edit & Resubmit Announcement';
                    } else {
                        modalTitle.textContent = 'Edit Announcement';
                    }

                    document.getElementById('announcement-edit-modal').classList.remove('hidden');
                });
            });

            // Archive/Delete buttons
            document.querySelectorAll('.announcement-archive-btn, .announcement-delete-btn').forEach(button => {
                // Remove any existing event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');
                    const status = this.getAttribute('data-status');

                    window.announcementToAction = id;
                    document.getElementById('announcement-delete-title').textContent = description;

                    // Show/hide archive button based on status
                    const archiveBtn = document.getElementById('announcement-archive-btn');
                    const restoreBtn = document.getElementById('announcement-restore-btn');
                    const modalTitle = document.getElementById('announcement-modal-title');
                    const actionText = document.getElementById('announcement-action-text');

                    if (status === 'approved') {
                        archiveBtn.style.display = 'inline-block';
                        restoreBtn.style.display = 'none';
                        modalTitle.textContent = 'Archive Announcement';
                        actionText.textContent = 'archive';
                    } else {
                        archiveBtn.style.display = 'none';
                        restoreBtn.style.display = 'none';
                        modalTitle.textContent = 'Delete Announcement';
                        actionText.textContent = 'delete';
                    }

                    document.getElementById('announcement-delete-modal').style.display = 'flex';
                });
            });

            // Restore buttons
            document.querySelectorAll('.announcement-restore-btn').forEach(button => {
                // Remove any existing event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    window.announcementToAction = id;
                    document.getElementById('announcement-delete-title').textContent = description;

                    // Show restore button and hide others
                    const archiveBtn = document.getElementById('announcement-archive-btn');
                    const restoreBtn = document.getElementById('announcement-restore-btn');
                    const modalTitle = document.getElementById('announcement-modal-title');
                    const actionText = document.getElementById('announcement-action-text');

                    archiveBtn.style.display = 'none';
                    restoreBtn.style.display = 'inline-block';
                    modalTitle.textContent = 'Restore Announcement';
                    actionText.textContent = 'restore';

                    document.getElementById('announcement-delete-modal').style.display = 'flex';
                });
            });

            // Cancel button
            const cancelBtn = document.getElementById('announcement-cancel-delete-btn');
            if (cancelBtn) {
                const newCancelBtn = cancelBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

                newCancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    document.getElementById('announcement-delete-modal').style.display = 'none';
                });
            }

            // Close modal when clicking outside
            const modal = document.getElementById('announcement-delete-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        document.getElementById('announcement-delete-modal').style.display = 'none';
                    }
                });
            }

            // Archive button
            const archiveBtn = document.getElementById('announcement-archive-btn');
            if (archiveBtn) {
                // Remove any existing event listeners first
                const newArchiveBtn = archiveBtn.cloneNode(true);
                archiveBtn.parentNode.replaceChild(newArchiveBtn, archiveBtn);

                // Add new event listener
                newArchiveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active archive request
                    if (announcementActiveRequests.archive) {
                        console.log('Archive request already in progress');
                        return;
                    }

                    if (window.announcementToAction) {
                        // Set active request flag
                        announcementActiveRequests.archive = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                        // Send the archive request to the server
                        fetch('Modules/Announcements/archive_announcement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(window.announcementToAction)
                            })
                            .then(response => {
                                console.log('Archive response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Archive response data:', data);
                                if (data.success) {
                                    // Show success notification
                                    showAnnouncementNotification(data.message || 'Announcement archived successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('announcement-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showAnnouncementNotification(data.message || 'Error archiving announcement', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                                }
                            })
                            .catch(error => {
                                console.error('Archive error:', error);
                                showAnnouncementNotification('An error occurred while archiving the announcement: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                            })
                            .finally(() => {
                                // Reset active request flag
                                announcementActiveRequests.archive = false;
                            });
                    }
                });
            }

            // Restore button
            const restoreBtn = document.getElementById('announcement-restore-btn');
            if (restoreBtn) {
                // Remove any existing event listeners first
                const newRestoreBtn = restoreBtn.cloneNode(true);
                restoreBtn.parentNode.replaceChild(newRestoreBtn, restoreBtn);

                // Add new event listener
                newRestoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active restore request
                    if (announcementActiveRequests.restore) {
                        console.log('Restore request already in progress');
                        return;
                    }

                    if (window.announcementToAction) {
                        // Set active request flag
                        announcementActiveRequests.restore = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';

                        // Send the restore request to the server
                        fetch('Modules/Announcements/restore_announcement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(window.announcementToAction)
                            })
                            .then(response => {
                                console.log('Restore response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Restore response data:', data);
                                if (data.success) {
                                    // Show success notification
                                    showAnnouncementNotification(data.message || 'Announcement restored successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('announcement-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showAnnouncementNotification(data.message || 'Error restoring announcement', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                                }
                            })
                            .catch(error => {
                                console.error('Restore error:', error);
                                showAnnouncementNotification('An error occurred while restoring the announcement: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                            })
                            .finally(() => {
                                // Reset active request flag
                                announcementActiveRequests.restore = false;
                            });
                    }
                });
            }

            // Delete confirmation
            const deleteBtn = document.getElementById('announcement-confirm-delete-btn');
            if (deleteBtn) {
                // Remove any existing event listeners first
                const newDeleteBtn = deleteBtn.cloneNode(true);
                deleteBtn.parentNode.replaceChild(newDeleteBtn, deleteBtn);

                // Add new event listener
                newDeleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active delete request
                    if (announcementActiveRequests.delete) {
                        console.log('Delete request already in progress');
                        return;
                    }

                    if (window.announcementToAction) {
                        // Log the ID for debugging
                        console.log("Attempting to delete announcement with ID:", window.announcementToAction);

                        // Set active request flag
                        announcementActiveRequests.delete = true;

                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                        fetch('Modules/Announcements/delete_announcement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(window.announcementToAction)
                            })
                            .then(response => {
                                console.log('Delete response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Delete response data:', data);
                                if (data.success) {
                                    // Show success notification
                                    showAnnouncementNotification(data.message || 'Announcement deleted successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('announcement-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showAnnouncementNotification(data.message || 'Error deleting announcement', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                                }
                            })
                            .catch(error => {
                                console.error('Delete error:', error);
                                showAnnouncementNotification('An error occurred while deleting the announcement: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                            })
                            .finally(() => {
                                // Reset active request flag
                                announcementActiveRequests.delete = false;
                            });
                    }
                });
            }
        }

        // Function to initialize view buttons
        function initializeAnnouncementViewButtons() {
            console.log('Setting up announcement view buttons...');

            // Add click event listeners to all view buttons
            document.querySelectorAll('[id^="announcement-view-full-"]').forEach(button => {
                // Remove any existing event listeners first
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', handleAnnouncementViewButtonClick);
            });

            console.log('Announcement view buttons initialized');
        }

        // Handle view button click
        function handleAnnouncementViewButtonClick(event) {
            const button = this;
            const idParts = button.id.split('-');

            // The id format is "announcement-view-full-{status}-{index}"
            // For "not-approved", we need to handle the hyphen in the status
            let status, index;

            if (idParts[3] === 'not' && idParts[4] === 'approved') {
                // This is the "not-approved" case
                status = 'not-approved';
                index = idParts[5];
            } else {
                // All other cases (pending, approved, archived)
                status = idParts[3];
                index = idParts[4];
            }

            const modalId = `announcement-file-modal-${status}-${index}`;
            const containerId = `announcement-pdf-container-${status}-${index}`;
            const fileType = button.dataset.fileType;
            const filePath = button.dataset.filePath;

            const modal = document.getElementById(modalId);
            const container = document.getElementById(containerId);

            if (!modal || !container) return;

            modal.classList.add('modal-active');
            modal.style.display = "block";

            requestAnimationFrame(() => {
                displayAnnouncementFileContent(fileType, filePath, status, index, container);
            });
        }

        // Display file content
        function displayAnnouncementFileContent(fileType, filePath, status, index, container) {
            const fileExtension = filePath.split('.').pop().toLowerCase();

            // Clear container and show loading
            container.innerHTML = `
            <div class="announcement-loading-spinner"></div>
            <p class="text-center text-gray-600">Loading file...</p>
        `;

            // Create full URL for the file - resolve relative to Testing directory
            const baseUrl = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const testingIndex = pathParts.indexOf('Testing');

            let fullUrl;
            if (testingIndex !== -1) {
                // We're in a subdirectory of Testing
                const basePath = pathParts.slice(0, testingIndex + 1).join('/');
                fullUrl = `${baseUrl}${basePath}/${filePath}`;
            } else {
                // We're at the root or in a different structure
                fullUrl = `${baseUrl}/Testing/${filePath}`;
            }

            if (fileExtension === 'pdf') {
                loadAnnouncementPDFFile(fullUrl, status, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                container.innerHTML = `
                <div class="announcement-image-container">
                    <img src="${fullUrl}" alt="Full view" class="announcement-image-viewer" 
                         onerror="this.onerror=null; this.style.display='none'; 
                         container.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-lg text-gray-700\\'>Failed to load image</p></div>'">
                </div>
            `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                // Use Microsoft Office Online viewer
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
                // For other file types
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
        function loadAnnouncementPDFFile(filePath, status, index, container) {
            const key = `${status}-${index}`;

            container.innerHTML = `
            <div class="announcement-loading-spinner"></div>
            <p class="text-center text-gray-600">Loading PDF document...</p>
        `;

            pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                window.announcementPdfDocs[key] = pdfDoc;
                window.announcementTotalPages[key] = pdfDoc.numPages;
                window.announcementCurrentPageNum[key] = 1;
                window.announcementIsRendering[key] = false; // Initialize the rendering flag

                const pageIndicator = document.getElementById(`announcement-page-indicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdfDoc.numPages}`;
                }

                const prevBtn = document.getElementById(`announcement-prev-page-btn-${status}-${index}`);
                const nextBtn = document.getElementById(`announcement-next-page-btn-${status}-${index}`);

                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdfDoc.numPages <= 1;

                // Force render after modal is visible
                setTimeout(() => {
                    renderAnnouncementPDFPage(status, index, 1);
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
        function renderAnnouncementPDFPage(status, index, pageNum) {
            return new Promise((resolve, reject) => {
                const key = `${status}-${index}`;
                if (!window.announcementPdfDocs[key]) {
                    reject(new Error('PDF document not found'));
                    return;
                }

                const container = document.getElementById(`announcement-pdf-container-${status}-${index}`);

                container.innerHTML = `
            <div class="announcement-loading-spinner"></div>
            <p class="text-center text-gray-600">Rendering page ${pageNum}...</p>
        `;

                window.announcementPdfDocs[key].getPage(pageNum).then(page => {
                    const modalBody = document.querySelector(`#announcement-file-modal-${status}-${index} .announcement-modal-body`);

                    const availableWidth = modalBody.clientWidth - 20;
                    const availableHeight = modalBody.clientHeight - 20;

                    const viewport = page.getViewport({
                        scale: 1
                    });

                    const scale = Math.min(
                        availableWidth / viewport.width,
                        availableHeight / viewport.height
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

        // Navigation functions
        function goToAnnouncementPrevPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.announcementPdfDocs || !window.announcementPdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.announcementIsRendering[key]) return;

            if (window.announcementCurrentPageNum[key] > 1) {
                // Set the rendering flag
                window.announcementIsRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`announcement-prev-page-btn-${status}-${index}`);
                const nextBtn = document.getElementById(`announcement-next-page-btn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.announcementCurrentPageNum[key]--;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`announcement-page-indicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.announcementCurrentPageNum[key]} of ${window.announcementTotalPages[key]}`;
                }

                renderAnnouncementPDFPage(status, index, window.announcementCurrentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.announcementCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.announcementCurrentPageNum[key] === window.announcementTotalPages[key];

                    // Clear the rendering flag
                    window.announcementIsRendering[key] = false;
                });
            }
        }

        function goToAnnouncementNextPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.announcementPdfDocs || !window.announcementPdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.announcementIsRendering[key]) return;

            if (window.announcementCurrentPageNum[key] < window.announcementTotalPages[key]) {
                // Set the rendering flag
                window.announcementIsRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`announcement-prev-page-btn-${status}-${index}`);
                const nextBtn = document.getElementById(`announcement-next-page-btn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.announcementCurrentPageNum[key]++;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`announcement-page-indicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.announcementCurrentPageNum[key]} of ${window.announcementTotalPages[key]}`;
                }

                renderAnnouncementPDFPage(status, index, window.announcementCurrentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.announcementCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.announcementCurrentPageNum[key] === window.announcementTotalPages[key];

                    // Clear the rendering flag
                    window.announcementIsRendering[key] = false;
                });
            }
        }

        // Initialize PDF previews with optimizations
        function initializeAnnouncementPDFPreviews() {
            console.log('Initializing announcement PDF previews...');

            // Clear any existing PDF resources first
            if (window.announcementPdfDocs) {
                Object.keys(window.announcementPdfDocs).forEach(key => {
                    if (window.announcementPdfDocs[key]) {
                        try {
                            window.announcementPdfDocs[key].destroy();
                        } catch (e) {
                            console.warn('Error destroying PDF document:', e);
                        }
                        delete window.announcementPdfDocs[key];
                    }
                });
            }

            // Reset PDF-related variables
            window.announcementCurrentPageNum = {};
            window.announcementTotalPages = {};
            window.announcementIsRendering = {};

            // Optimized PDF preview rendering function
            function renderAnnouncementPDFPreview(filePath, containerId) {
                console.log('Rendering announcement PDF preview for:', filePath, containerId);

                const container = document.getElementById(containerId);
                if (!container) {
                    console.log('Container not found:', containerId);
                    return;
                }

                // Show loading state
                container.innerHTML = '<div class="announcement-loading-spinner"></div>';

                // Create full URL for the file
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

                console.log('Loading PDF from:', fullUrl);

                // Set maximum dimensions for the preview
                const MAX_WIDTH = 300;
                const MAX_HEIGHT = 200;

                pdfjsLib.getDocument({
                    url: fullUrl,
                    disableRange: true, // Disable range requests
                    disableStream: true, // Disable streaming
                    disableAutoFetch: true, // Disable auto-fetching
                    cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/cmaps/',
                    cMapPacked: true
                }).promise.then(function(pdf) {
                    return pdf.getPage(1);
                }).then(function(page) {
                    const viewport = page.getViewport({
                        scale: 1
                    });

                    // Calculate scale to fit within MAX_WIDTH and MAX_HEIGHT
                    const scale = Math.min(
                        MAX_WIDTH / viewport.width,
                        MAX_HEIGHT / viewport.height,
                        1.0 // Don't scale up
                    );

                    const scaledViewport = page.getViewport({
                        scale
                    });

                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    canvas.style.maxWidth = "100%";
                    canvas.style.maxHeight = "100%";
                    canvas.style.objectFit = "contain";

                    container.innerHTML = '';
                    container.appendChild(canvas);

                    const renderContext = {
                        canvasContext: context,
                        viewport: scaledViewport,
                        enableWebGL: false, // Disable WebGL for faster rendering
                        renderInteractiveForms: false // Don't render interactive forms
                    };

                    return page.render(renderContext).promise.then(function() {
                        console.log('Announcement PDF preview rendered successfully for:', containerId);
                    });
                }).catch(function(error) {
                    console.error('Announcement PDF preview error for', containerId, ':', error);
                    container.innerHTML =
                        '<div class="flex flex-col items-center justify-center h-full p-2">' +
                        '<i class="fas fa-file-pdf text-red-500 text-3xl mb-1"></i>' +
                        '<p class="text-red-500 text-xs text-center">Preview unavailable</p>' +
                        '</div>';
                });
            }

            // Use Intersection Observer for lazy loading
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const container = entry.target;
                        const filePath = container.dataset.filePath;
                        const containerId = container.id;

                        if (filePath && containerId) {
                            renderAnnouncementPDFPreview(filePath, containerId);
                            observer.unobserve(container);
                        }
                    }
                });
            }, {
                rootMargin: '100px' // Start loading 100px before element is visible
            });

            // Observe all PDF preview containers
            document.querySelectorAll('.announcement-file-preview').forEach(container => {
                observer.observe(container);
            });

            // Fallback for browsers that don't support Intersection Observer
            if (!('IntersectionObserver' in window)) {
                // Render all previews immediately
                renderAllAnnouncementPreviews();
            }

            function renderAllAnnouncementPreviews() {
                <?php
                $totalDelay = 0;
                foreach ($pendingAnnouncements as $index => $announcement): ?>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderAnnouncementPDFPreview("<?= $announcement['file_path'] ?>", "announcement-file-preview-pending-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($pendingAnnouncements) * 200;
                endforeach;
                ?>

                <?php foreach ($approvedAnnouncements as $index => $announcement): ?>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderAnnouncementPDFPreview("<?= $announcement['file_path'] ?>", "announcement-file-preview-approved-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($approvedAnnouncements) * 200;
                endforeach;
                ?>

                <?php foreach ($notApprovedAnnouncements as $index => $announcement): ?>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderAnnouncementPDFPreview("<?= $announcement['file_path'] ?>", "announcement-file-preview-not-approved-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($notApprovedAnnouncements) * 200;
                endforeach;
                ?>

                <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderAnnouncementPDFPreview("<?= $announcement['file_path'] ?>", "announcement-file-preview-archived-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php endforeach; ?>
            }

            // Set a timeout to show fallback if PDF previews fail
            setTimeout(() => {
                document.querySelectorAll('.announcement-file-preview').forEach(container => {
                    // If still showing loading spinner after 5 seconds, show file icon
                    if (container.querySelector('.announcement-loading-spinner')) {
                        const fileType = 'pdf'; // Default to PDF for fallback
                        container.innerHTML = `<i class="fas fa-file-pdf announcement-file-icon pdf text-4xl"></i>`;
                    }
                });
            }, 5000);
        }

        // Initialize all other functionality
        function initializeAnnouncementOtherFunctionality() {
            console.log('Initializing announcement other functionality...');

            // Initialize page navigation
            initializeAnnouncementPageNavigation();

            // Initialize download buttons
            initializeAnnouncementDownloadButtons();

            console.log('Announcement other functionality initialized');
        }

        // Initialize download buttons
        function initializeAnnouncementDownloadButtons() {
            // Remove any existing event listeners by cloning the buttons
            document.querySelectorAll('.announcement-download-btn').forEach(button => {
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
            });

            // Add new event listeners
            document.querySelectorAll('.announcement-download-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const filePath = this.getAttribute('data-file-path');
                    const fileName = this.getAttribute('data-file-name');

                    // Get the full URL for the file
                    const fullUrl = getAnnouncementFullUrl(filePath);

                    // Create a temporary anchor element to trigger the download
                    const a = document.createElement('a');
                    a.href = fullUrl;
                    a.download = fileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    // Show a notification
                    showAnnouncementNotification(`Downloading ${fileName}...`, 'success');
                });
            });
        }

        // Helper function to get the full URL for a file
        function getAnnouncementFullUrl(filePath) {
            const baseUrl = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const testingIndex = pathParts.indexOf('Testing');

            let fullUrl;
            if (testingIndex !== -1) {
                // We're in a subdirectory of Testing
                const basePath = pathParts.slice(0, testingIndex + 1).join('/');
                fullUrl = `${baseUrl}${basePath}/${filePath}`;
            } else {
                // We're at the root or in a different structure
                fullUrl = `${baseUrl}/Testing/${filePath}`;
            }
            return fullUrl;
        }

        // Initialize page navigation buttons
        function initializeAnnouncementPageNavigation() {
            // Previous page buttons
            document.querySelectorAll('[id^="announcement-prev-page-btn-"]').forEach(button => {
                const idParts = button.id.split('-');

                // Handle the "not-approved" case for navigation buttons too
                let status, index;
                if (idParts[4] === 'not' && idParts[5] === 'approved') {
                    status = 'not-approved';
                    index = idParts[6];
                } else {
                    status = idParts[4];
                    index = idParts[5];
                }

                button.addEventListener('click', () => goToAnnouncementPrevPage(status, index));
            });

            // Next page buttons
            document.querySelectorAll('[id^="announcement-next-page-btn-"]').forEach(button => {
                const idParts = button.id.split('-');

                // Handle the "not-approved" case for navigation buttons too
                let status, index;
                if (idParts[4] === 'not' && idParts[5] === 'approved') {
                    status = 'not-approved';
                    index = idParts[6];
                } else {
                    status = idParts[4];
                    index = idParts[5];
                }

                button.addEventListener('click', () => goToAnnouncementNextPage(status, index));
            });
        }

        // Show notification function
        function showAnnouncementNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `announcement-notification ${type}`;
            notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;

            document.body.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Global functions
        function closeAnnouncementFileModal(status, index) {
            const modal = document.getElementById(`announcement-file-modal-${status}-${index}`);
            if (modal) {
                modal.classList.remove('modal-active');
                modal.style.display = "none";

                // Clean up PDF resources
                const key = `${status}-${index}`;
                if (window.announcementPdfDocs[key]) {
                    // You can add cleanup code here if needed
                }
            }
        }
    </script>
</body>

</html>