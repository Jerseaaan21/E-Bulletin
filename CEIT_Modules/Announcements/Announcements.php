<?php
// CEIT_Modules/Announcements/Announcements.php
include "../../db.php";
session_start();

// Check if user is Lead MIS Officer
if (!isset($_SESSION['user_info']) || $_SESSION['user_info']['role'] !== 'LEAD_MIS' || $_SESSION['user_info']['dept_id'] != 1) {
    header("Location: ../../logout.php");
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

// Get department acronym from database
$dept_id = $_SESSION['user_info']['dept_id'];
$dept_acronym = 'default'; // fallback

// Query the departments table to get the acronym
$query = "SELECT acronym FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dept_acronym = $row['acronym'];
}

// Query the departments table to get the acronym
$query = "SELECT id FROM Modules WHERE name = 'Announcements'";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $Ann_Id = $row['id'];
}
// Create dynamic upload path
$uploadBaseDir = "../../uploads/{$dept_acronym}/Announcement/";

// Get active announcements
$query = "SELECT * FROM main_post WHERE user_id = ? AND status = 'active' AND module = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $userId, $Ann_Id);
$stmt->execute();
$result = $stmt->get_result();

$activeAnnouncements = [];
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $filePath = $uploadBaseDir . $row['file_path'];
    $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path'];

    $activeAnnouncements[] = [
        'id' => $row['id'],
        'file_path' => $relativeFilePath,
        'description' => $row['description'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension)
    ];
}

// Get archived announcements
$query = "SELECT * FROM main_post WHERE user_id = ? AND status = 'archived' AND module = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $userId, $Ann_Id);
$stmt->execute();
$result = $stmt->get_result();

$archivedAnnouncements = [];
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $filePath = $uploadBaseDir . $row['file_path'];
    $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path'];

    $archivedAnnouncements[] = [
        'id' => $row['id'],
        'file_path' => $relativeFilePath,
        'description' => $row['description'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEIT Announcements Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Same styles as provided in Modules/Announcements/Announcements.php */
        .file-preview {
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

        .file-modal {
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
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 15px 20px;
            background-color: #f97316;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .modal-close:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            height: calc(100% - 140px);
        }

        .pdf-container {
            width: 100%;
            height: 100%;
            min-height: 82vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .pdf-page {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            max-width: 100%;
            max-height: 100%;
        }

        .modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
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
            width: 40px;
            height: 40px;
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

        .file-icon {
            font-size: 4rem;
        }

        .file-icon.pdf {
            color: #dc2626;
        }

        .file-icon.doc,
        .file-icon.docx,
        .file-icon.wps {
            color: #2563eb;
        }

        .file-icon.xls,
        .file-icon.xlsx {
            color: #16a34a;
        }

        .file-icon.ppt,
        .file-icon.pptx {
            color: #ea580c;
        }

        .file-icon.jpg,
        .file-icon.jpeg,
        .file-icon.png,
        .file-icon.gif {
            color: #8b5cf6;
        }

        .file-icon.default {
            color: #6b7280;
        }

        .image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #000;
        }

        .image-viewer {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .office-viewer {
            border: none;
            border-radius: 0;
            overflow: hidden;
            width: 100%;
            height: 100%;
            background-color: #fff;
        }

        /* Status sections */
        .status-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
        }

        .active {
            background-color: transparent;
            border: none;
            /* Removed border */
        }

        .archived {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }

        .active .status-title {
            color: #ea580c;
            /* Changed to orange */
            border-color: #ea580c;
            /* Changed to orange */
        }

        .archived .status-title {
            color: #2563eb;
            border-color: #2563eb;
        }

        /* Notification styles */
        .notification {
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
            animation: slideIn 0.3s ease-out;
        }

        .notification.success {
            background-color: #f97316;
            /* Changed to orange */
        }

        .notification.error {
            background-color: #ef4444;
        }

        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes slideIn {
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
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .modal-header {
                padding: 10px 15px;
            }

            .modal-title {
                font-size: 1.2rem;
            }

            .modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .page-navigation {
                width: 100%;
                justify-content: center;
            }

            .modal-meta {
                text-align: center;
                width: 100%;
            }

            .status-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .status-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .file-preview canvas {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                display: block;
                margin: 0 auto;
            }

            .file-preview {
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
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0">
                <i class="fas fa-bullhorn mr-3 w-5"></i> CEIT Announcements Management
            </h1>
            <button id="upload-announcement-btn"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-upload mr-2"></i> Upload Announcement
            </button>
        </div>

        <!-- Active Announcements -->
        <div class="status-section active">
            <h2 class="status-title">Active Announcements</h2>
            <?php if (count($activeAnnouncements) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($activeAnnouncements as $index => $announcement): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-orange-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="file-preview-active-<?= $index ?>" class="file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($announcement['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $announcement['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $announcement['file_type'] ?> text-4xl"></i>
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
                                    Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="view-full-active-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $announcement['file_type'] ?>" data-file-path="<?= $announcement['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 download-btn" data-file-path="<?= $announcement['file_path'] ?>" data-file-name="<?= basename($announcement['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-archive-btn" data-index="<?= $index ?>" data-id="<?= $announcement['id'] ?>" data-description="<?= htmlspecialchars($announcement['description']) ?>" data-status="active" title="Archive">
                                    <i class="fas fa-archive fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox fa-3x mb-4"></i>
                    <p class="text-lg">No active announcements yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Announcements -->
        <div class="status-section archived">
            <h2 class="status-title">Archived Announcements</h2>
            <?php if (count($archivedAnnouncements) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105 opacity-75">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="file-preview-archived-<?= $index ?>" class="file-preview" data-file-path="<?= $announcement['file_path'] ?>">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($announcement['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $announcement['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $announcement['file_type'] ?> text-4xl"></i>
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
                                    Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $announcement['file_type'] ?>" data-file-path="<?= $announcement['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white transition duration-200 transform hover:scale-110 download-btn" data-file-path="<?= $announcement['file_path'] ?>" data-file-name="<?= basename($announcement['file_path']) ?>" title="Download">
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
                    <i class="fas fa-inbox fa-3x mb-4"></i>
                    <p class="text-lg">No archived announcements</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File View Modals -->
    <?php foreach ($activeAnnouncements as $index => $announcement): ?>
        <div id="file-modal-active-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="modal-close" onclick="closeFileModal('active', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="pdfContainer-active-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?> | File: <?= basename($announcement['file_path']) ?>
                    </div>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        <div class="page-navigation">
                            <button id="prevPageBtn-active-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="pageIndicator-active-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                            <button id="nextPageBtn-active-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
        <div id="file-modal-archived-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="modal-close" onclick="closeFileModal('archived', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="pdfContainer-archived-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?> | File: <?= basename($announcement['file_path']) ?>
                    </div>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        <div class="page-navigation">
                            <button id="prevPageBtn-archived-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="pageIndicator-archived-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                            <button id="nextPageBtn-archived-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Upload Modal -->
    <div id="upload-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Upload Announcement</h2>
            <form id="upload-form" action="AddAnnouncement.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input
                        type="text"
                        id="description"
                        name="description"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="pdfFile" class="block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        id="pdfFile"
                        name="pdfFile"
                        accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        required
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">

                </div>
                <div class="flex justify-end space-x-3 text-sm">
                    <button
                        type="button"
                        id="cancel-upload-btn"
                        class="px-4 py-2 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Announcement</h3>
                <form id="edit-form" class="mt-2 py-3" enctype="multipart/form-data">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="mb-4">
                        <label for="edit-description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <input type="text" id="edit-description" name="description"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="edit-file" class="block text-sm font-medium text-gray-700">Replace File (optional)</label>
                        <input
                            type="file"
                            id="edit-file"
                            name="file"
                            accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancel-edit"
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
                <h3 class="text-xl font-bold text-yellow-600" id="modal-title">Archive Announcement</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to <span id="action-text">archive</span> this announcement?</p>
                <p class="font-semibold mt-2" id="delete-announcement-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="cancel-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200" id="archive-announcement-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="restore-announcement-btn" style="display: none;">
                    <i class="fas fa-undo mr-2"></i> Restore
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="confirm-announcement-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        // Set PDF.js worker source
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        // Global variables for PDF handling
        window.pdfDocs = {};
        window.currentPageNum = {};
        window.totalPages = {};
        window.isRendering = {};

        // Global variable to track active requests
        const activeRequests = {
            archive: false,
            delete: false,
            restore: false
        };

        // Function to initialize the module - can be called from dashboard
        function initializeAnnouncementsModule() {
            console.log('Initializing Announcements module...');

            // Prevent multiple initializations
            if (window.announcementsModuleInitialized) {
                console.log('Announcements module already initialized, reinitializing...');
                // Force reinitialize PDF previews
                initializePDFPreviews();
                return;
            }

            window.announcementsModuleInitialized = true;

            // Initialize modal event listeners
            initializeModalEventListeners();

            // Re-initialize all functionality
            if (typeof pdfjsLib !== 'undefined') {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';
                initializePDFPreviews();
            }

            initializeViewButtons();
            initializePageNavigation();
            initializeOtherFunctionality();

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
        function initializeModalEventListeners() {
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

                    announcementToAction = id;
                    document.getElementById('delete-announcement-title').textContent = description;

                    // Show/hide archive button based on status
                    const archiveBtn = document.getElementById('archive-announcement-btn');
                    const restoreBtn = document.getElementById('restore-announcement-btn');
                    const modalTitle = document.getElementById('modal-title');
                    const actionText = document.getElementById('action-text');

                    if (status === 'active') {
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

                    announcementToAction = id;
                    document.getElementById('delete-announcement-title').textContent = description;

                    // Show restore button and hide others
                    const archiveBtn = document.getElementById('archive-announcement-btn');
                    const restoreBtn = document.getElementById('restore-announcement-btn');
                    const modalTitle = document.getElementById('modal-title');
                    const actionText = document.getElementById('action-text');

                    archiveBtn.style.display = 'none';
                    restoreBtn.style.display = 'inline-block';
                    modalTitle.textContent = 'Restore Announcement';
                    actionText.textContent = 'restore';

                    document.getElementById('announcement-delete-modal').style.display = 'flex';
                });
            });

            // Cancel button
            const cancelBtn = document.getElementById('cancel-delete-btn');
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
        }

        // Function to initialize view buttons
        function initializeViewButtons() {
            console.log('Setting up view buttons...');

            // Add click event listeners to all view buttons
            document.querySelectorAll('[id^="view-full-"]').forEach(button => {
                // Remove any existing event listeners first
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', handleViewButtonClick);
            });

            console.log('View buttons initialized');
        }

        // Handle view button click
        function handleViewButtonClick(event) {
            const button = this;
            const idParts = button.id.split('-');
            const status = idParts[2];
            const index = idParts[3];

            const modalId = `file-modal-${status}-${index}`;
            const containerId = `pdfContainer-${status}-${index}`;
            const fileType = button.dataset.fileType;
            const filePath = button.dataset.filePath;

            const modal = document.getElementById(modalId);
            const container = document.getElementById(containerId);

            if (!modal || !container) return;

            modal.classList.add('modal-active');
            modal.style.display = "block";

            requestAnimationFrame(() => {
                displayFileContent(fileType, filePath, status, index, container);
            });
        }

        // Display file content
        function displayFileContent(fileType, filePath, status, index, container) {
            const fileExtension = filePath.split('.').pop().toLowerCase();

            // Clear container and show loading
            container.innerHTML = `
                <div class="loading-spinner"></div>
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
                loadPDFFile(fullUrl, status, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                container.innerHTML = `
                    <div class="image-container">
                        <img src="${fullUrl}" alt="Full view" class="image-viewer" 
                             onerror="this.onerror=null; this.style.display='none'; 
                             container.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-lg text-gray-700\\'>Failed to load image</p></div>'">
                    </div>
                `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                // Use Microsoft Office Online viewer
                const encodedUrl = encodeURIComponent(fullUrl);
                container.innerHTML = `
                    <div class="office-viewer">
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
        function loadPDFFile(filePath, status, index, container) {
            const key = `${status}-${index}`;

            container.innerHTML = `
                <div class="loading-spinner"></div>
                <p class="text-center text-gray-600">Loading PDF document...</p>
            `;

            pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                window.pdfDocs[key] = pdfDoc;
                window.totalPages[key] = pdfDoc.numPages;
                window.currentPageNum[key] = 1;
                window.isRendering[key] = false; // Initialize the rendering flag

                const pageIndicator = document.getElementById(`pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdfDoc.numPages}`;
                }

                const prevBtn = document.getElementById(`prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`nextPageBtn-${status}-${index}`);

                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdfDoc.numPages <= 1;

                // Force render after modal is visible
                setTimeout(() => {
                    renderPDFPage(status, index, 1);
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
        function renderPDFPage(status, index, pageNum) {
            return new Promise((resolve, reject) => {
                const key = `${status}-${index}`;
                if (!window.pdfDocs[key]) {
                    reject(new Error('PDF document not found'));
                    return;
                }

                const container = document.getElementById(`pdfContainer-${status}-${index}`);

                container.innerHTML = `
                    <div class="loading-spinner"></div>
                    <p class="text-center text-gray-600">Rendering page ${pageNum}...</p>
                `;

                window.pdfDocs[key].getPage(pageNum).then(page => {
                    const modalBody = document.querySelector(`#file-modal-${status}-${index} .modal-body`);

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
        function goToPrevPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.pdfDocs || !window.pdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.isRendering[key]) return;

            if (window.currentPageNum[key] > 1) {
                // Set the rendering flag
                window.isRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`nextPageBtn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.currentPageNum[key]--;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.currentPageNum[key]} of ${window.totalPages[key]}`;
                }

                renderPDFPage(status, index, window.currentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.currentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.currentPageNum[key] === window.totalPages[key];

                    // Clear the rendering flag
                    window.isRendering[key] = false;
                });
            }
        }

        function goToNextPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.pdfDocs || !window.pdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.isRendering[key]) return;

            if (window.currentPageNum[key] < window.totalPages[key]) {
                // Set the rendering flag
                window.isRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`nextPageBtn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.currentPageNum[key]++;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.currentPageNum[key]} of ${window.totalPages[key]}`;
                }

                renderPDFPage(status, index, window.currentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.currentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.currentPageNum[key] === window.totalPages[key];

                    // Clear the rendering flag
                    window.isRendering[key] = false;
                });
            }
        }

        // Initialize PDF previews with optimizations
        function initializePDFPreviews() {
            console.log('Initializing PDF previews...');

            // Clear any existing PDF resources first
            if (window.pdfDocs) {
                Object.keys(window.pdfDocs).forEach(key => {
                    if (window.pdfDocs[key]) {
                        try {
                            window.pdfDocs[key].destroy();
                        } catch (e) {
                            console.warn('Error destroying PDF document:', e);
                        }
                        delete window.pdfDocs[key];
                    }
                });
            }

            // Reset PDF-related variables
            window.currentPageNum = {};
            window.totalPages = {};
            window.isRendering = {};

            // Optimized PDF preview rendering function
            function renderPDFPreview(filePath, containerId) {
                console.log('Rendering PDF preview for:', filePath, containerId);

                const container = document.getElementById(containerId);
                if (!container) {
                    console.log('Container not found:', containerId);
                    return;
                }

                // Show loading state
                container.innerHTML = '<div class="loading-spinner"></div>';

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
                        console.log('PDF preview rendered successfully for:', containerId);
                    });
                }).catch(function(error) {
                    console.error('PDF preview error for', containerId, ':', error);
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
                            renderPDFPreview(filePath, containerId);
                            observer.unobserve(container);
                        }
                    }
                });
            }, {
                rootMargin: '100px' // Start loading 100px before element is visible
            });

            // Observe all PDF preview containers
            document.querySelectorAll('.file-preview').forEach(container => {
                observer.observe(container);
            });

            // Fallback for browsers that don't support Intersection Observer
            if (!('IntersectionObserver' in window)) {
                // Render all previews immediately
                renderAllPreviews();
            }

            function renderAllPreviews() {
                <?php
                $totalDelay = 0;
                foreach ($activeAnnouncements as $index => $announcement): ?>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderPDFPreview("<?= $announcement['file_path'] ?>", "file-preview-active-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($activeAnnouncements) * 200;
                endforeach;
                ?>

                <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderPDFPreview("<?= $announcement['file_path'] ?>", "file-preview-archived-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php endforeach; ?>
            }

            // Set a timeout to show fallback if PDF previews fail
            setTimeout(() => {
                document.querySelectorAll('.file-preview').forEach(container => {
                    // If still showing loading spinner after 5 seconds, show file icon
                    if (container.querySelector('.loading-spinner')) {
                        const fileType = 'pdf'; // Default to PDF for fallback
                        container.innerHTML = `<i class="fas fa-file-pdf file-icon pdf text-4xl"></i>`;
                    }
                });
            }, 5000);
        }

        // Initialize all other functionality
        function initializeOtherFunctionality() {
            console.log('Initializing other functionality...');

            // Upload modal functionality
            const uploadModal = document.getElementById('upload-modal');
            const uploadForm = document.getElementById('upload-form');

            // Upload button - show modal
            document.getElementById('upload-announcement-btn').addEventListener('click', function() {
                console.log('Upload button clicked');
                uploadModal.classList.remove('hidden');
            });

            // Cancel button - hide modal
            document.getElementById('cancel-upload-btn').addEventListener('click', function() {
                console.log('Cancel upload button clicked');
                uploadModal.classList.add('hidden');
                if (uploadForm) {
                    uploadForm.reset();
                }
            });

            // Close modal when clicking outside
            if (uploadModal) {
                uploadModal.addEventListener('click', function(e) {
                    if (e.target === uploadModal) {
                        console.log('Clicked outside modal - closing');
                        uploadModal.classList.add('hidden');
                        if (uploadForm) {
                            uploadForm.reset();
                        }
                    }
                });
            }

            // Upload form submission
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

                    fetch('CEIT_Modules/Announcements/AddAnnouncement.php', {
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
                                showNotification(data.message || 'Announcement uploaded successfully!', 'success');

                                // Close modal and reload after a short delay
                                setTimeout(() => {
                                    uploadModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showNotification(data.message || 'Error uploading announcement', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset form state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            // Show error notification
                            showNotification('An error occurred while uploading the announcement: ' + error.message, 'error');
                        });
                });
            }

            // Edit modal functionality
            const editModal = document.getElementById('edit-modal');
            const editForm = document.getElementById('edit-form');

            // Edit buttons
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    document.getElementById('edit-id').value = id;
                    document.getElementById('edit-description').value = description;
                    editModal.classList.remove('hidden');
                });
            });

            // Cancel edit button
            document.getElementById('cancel-edit').addEventListener('click', function() {
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

                    const id = document.getElementById('edit-id').value;
                    const description = document.getElementById('edit-description').value;
                    const fileInput = document.getElementById('edit-file');

                    const formData = new FormData();
                    formData.append('id', id);
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

                        formData.append('file', renamedFile);
                    }

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;

                    // Show loading state
                    submitBtn.textContent = 'Updating...';
                    submitBtn.disabled = true;

                    fetch('CEIT_Modules/Announcements/update_announcement.php', {
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
                                showNotification(data.message || 'Announcement updated successfully!', 'success');

                                // Hide modal and reload after a short delay
                                setTimeout(() => {
                                    editModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showNotification(data.message || 'Error updating announcement', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset button state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;

                            // Show error notification
                            showNotification('An error occurred while updating the announcement: ' + error.message, 'error');
                        });
                });
            }

            // Archive button
            const archiveBtn = document.getElementById('archive-announcement-btn');
            if (archiveBtn) {
                // Remove any existing event listeners first
                const newArchiveBtn = archiveBtn.cloneNode(true);
                archiveBtn.parentNode.replaceChild(newArchiveBtn, archiveBtn);

                // Add new event listener
                newArchiveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active archive request
                    if (activeRequests.archive) {
                        console.log('Archive request already in progress');
                        return;
                    }

                    if (announcementToAction) {
                        // Set active request flag
                        activeRequests.archive = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                        // Send the archive request to the server
                        fetch('CEIT_Modules/Announcements/archive_announcement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(announcementToAction)
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
                                    showNotification(data.message || 'Announcement archived successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('announcement-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showNotification(data.message || 'Error archiving announcement', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                                }
                            })
                            .catch(error => {
                                console.error('Archive error:', error);
                                showNotification('An error occurred while archiving the announcement: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                            })
                            .finally(() => {
                                // Reset active request flag
                                activeRequests.archive = false;
                            });
                    }
                });
            }

            // Restore button
            const restoreBtn = document.getElementById('restore-announcement-btn');
            if (restoreBtn) {
                // Remove any existing event listeners first
                const newRestoreBtn = restoreBtn.cloneNode(true);
                restoreBtn.parentNode.replaceChild(newRestoreBtn, restoreBtn);

                // Add new event listener
                newRestoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active restore request
                    if (activeRequests.restore) {
                        console.log('Restore request already in progress');
                        return;
                    }

                    if (announcementToAction) {
                        // Set active request flag
                        activeRequests.restore = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';

                        // Send the restore request to the server
                        fetch('CEIT_Modules/Announcements/restore_announcement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(announcementToAction)
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
                                    showNotification(data.message || 'Announcement restored successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('announcement-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showNotification(data.message || 'Error restoring announcement', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                                }
                            })
                            .catch(error => {
                                console.error('Restore error:', error);
                                showNotification('An error occurred while restoring the announcement: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                            })
                            .finally(() => {
                                // Reset active request flag
                                activeRequests.restore = false;
                            });
                    }
                });
            }

            // Delete confirmation
            const deleteBtn = document.getElementById('confirm-announcement-delete-btn');
            if (deleteBtn) {
                // Remove any existing event listeners first
                const newDeleteBtn = deleteBtn.cloneNode(true);
                deleteBtn.parentNode.replaceChild(newDeleteBtn, deleteBtn);

                // Add new event listener
                newDeleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active delete request
                    if (activeRequests.delete) {
                        console.log('Delete request already in progress');
                        return;
                    }

                    if (announcementToAction) {
                        // Log the ID for debugging
                        console.log("Attempting to delete announcement with ID:", announcementToAction);

                        // Set active request flag
                        activeRequests.delete = true;

                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                        fetch('CEIT_Modules/Announcements/delete_announcement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(announcementToAction)
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
                                    showNotification(data.message || 'Announcement deleted successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('announcement-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showNotification(data.message || 'Error deleting announcement', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                                }
                            })
                            .catch(error => {
                                console.error('Delete error:', error);
                                showNotification('An error occurred while deleting the announcement: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                            })
                            .finally(() => {
                                // Reset active request flag
                                activeRequests.delete = false;
                            });
                    }
                });
            }

            // Initialize page navigation
            initializePageNavigation();

            // Initialize download buttons
            initializeDownloadButtons();

            console.log('Other functionality initialized');
        }

        // Initialize download buttons
        function initializeDownloadButtons() {
            // Remove any existing event listeners by cloning the buttons
            document.querySelectorAll('.download-btn').forEach(button => {
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
            });

            // Add new event listeners
            document.querySelectorAll('.download-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const filePath = this.getAttribute('data-file-path');
                    const fileName = this.getAttribute('data-file-name');

                    // Get the full URL for the file
                    const fullUrl = getFullUrl(filePath);

                    // Create a temporary anchor element to trigger the download
                    const a = document.createElement('a');
                    a.href = fullUrl;
                    a.download = fileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    // Show a notification
                    showNotification(`Downloading ${fileName}...`, 'success');
                });
            });
        }

        // Helper function to get the full URL for a file
        function getFullUrl(filePath) {
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
        function initializePageNavigation() {
            // Previous page buttons
            document.querySelectorAll('[id^="prevPageBtn-"]').forEach(button => {
                const idParts = button.id.split('-');
                const status = idParts[1];
                const index = idParts[2];
                button.addEventListener('click', () => goToPrevPage(status, index));
            });

            // Next page buttons
            document.querySelectorAll('[id^="nextPageBtn-"]').forEach(button => {
                const idParts = button.id.split('-');
                const status = idParts[1];
                const index = idParts[2];
                button.addEventListener('click', () => goToNextPage(status, index));
            });
        }

        // Show notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
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
        function closeFileModal(status, index) {
            const modal = document.getElementById(`file-modal-${status}-${index}`);
            if (modal) {
                modal.classList.remove('modal-active');
                modal.style.display = "none";

                // Clean up PDF resources
                const key = `${status}-${index}`;
                if (window.pdfDocs[key]) {
                    // You can add cleanup code here if needed
                }
            }
        }
    </script>
</body>

</html>