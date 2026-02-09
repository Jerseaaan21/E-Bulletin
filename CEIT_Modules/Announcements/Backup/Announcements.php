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
echo $dept_id;
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

// Create dynamic upload path
$uploadBaseDir = "../../uploads/{$dept_acronym}/Announcement/";

// Get active announcements
$query = "SELECT * FROM main_post WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
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
$query = "SELECT * FROM main_post WHERE user_id = ? AND status = 'archived' ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
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
        /* Same styles as provided */
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

        .modal-active {
            z-index: 1001 !important;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-bullhorn mr-3 text-orange-500"></i> CEIT Announcements
                </h1>
                <p class="text-gray-600 mt-1">Manage announcements for the College of Engineering and Information Technology</p>
            </div>
            <button id="upload-announcement-btn"
                class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-plus mr-2"></i> New Announcement
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 mb-6">
            <button class="tab-btn py-3 px-6 font-medium text-orange-600 border-b-2 border-orange-500 focus:outline-none" data-tab="active">
                Active Announcements
            </button>
            <button class="tab-btn py-3 px-6 font-medium text-gray-500 hover:text-gray-700 focus:outline-none" data-tab="archived">
                Archived Announcements
            </button>
        </div>

        <!-- Active Announcements Tab -->
        <div id="active-tab" class="tab-content">
            <?php if (empty($activeAnnouncements)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <i class="fas fa-file-alt text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">No Active Announcements</h3>
                    <p class="text-gray-500 mb-4">Create your first announcement to get started</p>
                    <button class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition duration-200" onclick="document.getElementById('upload-announcement-btn').click()">
                        <i class="fas fa-plus mr-2"></i> Create Announcement
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($activeAnnouncements as $index => $announcement): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition duration-200">
                            <div class="p-4">
                                <div class="file-preview mb-4">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($announcement['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $announcement['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $announcement['file_type'] ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-semibold text-lg text-gray-800 mb-1 truncate"><?= htmlspecialchars($announcement['description']) ?></h3>
                                <p class="text-sm text-gray-600 truncate mb-2"><?= basename($announcement['file_path']) ?></p>
                                <p class="text-xs text-gray-500">
                                    Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-2">
                                <button class="view-btn p-2 text-blue-500 hover:bg-blue-50 rounded-full transition duration-200"
                                    data-index="<?= $index ?>"
                                    data-file-type="<?= $announcement['file_type'] ?>"
                                    data-file-path="<?= $announcement['file_path'] ?>"
                                    title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="edit-btn p-2 text-green-500 hover:bg-green-50 rounded-full transition duration-200"
                                    data-index="<?= $index ?>"
                                    data-id="<?= $announcement['id'] ?>"
                                    data-description="<?= htmlspecialchars($announcement['description']) ?>"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="archive-btn p-2 text-yellow-500 hover:bg-yellow-50 rounded-full transition duration-200"
                                    data-index="<?= $index ?>"
                                    data-id="<?= $announcement['id'] ?>"
                                    data-description="<?= htmlspecialchars($announcement['description']) ?>"
                                    title="Archive">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Announcements Tab -->
        <div id="archived-tab" class="tab-content hidden">
            <?php if (empty($archivedAnnouncements)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <i class="fas fa-archive text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">No Archived Announcements</h3>
                    <p class="text-gray-500">Archived announcements will appear here</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($archivedAnnouncements as $index => $announcement): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition duration-200 opacity-75">
                            <div class="p-4">
                                <div class="file-preview mb-4">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($announcement['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $announcement['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $announcement['file_type'] ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-semibold text-lg text-gray-800 mb-1 truncate"><?= htmlspecialchars($announcement['description']) ?></h3>
                                <p class="text-sm text-gray-600 truncate mb-2"><?= basename($announcement['file_path']) ?></p>
                                <p class="text-xs text-gray-500">
                                    Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-2">
                                <button class="view-btn p-2 text-blue-500 hover:bg-blue-50 rounded-full transition duration-200"
                                    data-index="<?= $index ?>"
                                    data-file-type="<?= $announcement['file_type'] ?>"
                                    data-file-path="<?= $announcement['file_path'] ?>"
                                    title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="restore-btn p-2 text-green-500 hover:bg-green-50 rounded-full transition duration-200"
                                    data-index="<?= $index ?>"
                                    data-id="<?= $announcement['id'] ?>"
                                    data-description="<?= htmlspecialchars($announcement['description']) ?>"
                                    title="Restore">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="delete-btn p-2 text-red-500 hover:bg-red-50 rounded-full transition duration-200"
                                    data-index="<?= $index ?>"
                                    data-id="<?= $announcement['id'] ?>"
                                    data-description="<?= htmlspecialchars($announcement['description']) ?>"
                                    title="Delete Permanently">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File View Modals -->
    <?php foreach ($activeAnnouncements as $index => $announcement): ?>
        <div id="file-modal-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($announcement['description']) ?></h3>
                    <span class="modal-close" onclick="closeFileModal(<?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="pdfContainer-<?= $index ?>" class="pdf-container">
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
                            <button id="prevPageBtn-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="pageIndicator-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                            <button id="nextPageBtn-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Upload Modal -->
    <div id="upload-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Create New Announcement</h2>
            <form id="upload-form" action="AddAnnouncement.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" id="description" name="description" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="pdfFile" class="block text-sm font-medium text-gray-700">File</label>
                    <input type="file" id="pdfFile" name="pdfFile"
                        accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" required
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-upload-btn"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition duration-200">
                        Create Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Edit Announcement</h2>
            <form id="edit-form" action="update_announcement.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" id="edit-id" name="id">
                <div>
                    <label for="edit-description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" id="edit-description" name="description" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="edit-file" class="block text-sm font-medium text-gray-700">Replace File (optional)</label>
                    <input type="file" id="edit-file" name="file"
                        accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-edit"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div id="archive-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4 text-yellow-600">Archive Announcement</h2>
            <p class="mb-4">Are you sure you want to archive this announcement?</p>
            <p class="font-semibold mb-4" id="archive-announcement-title"></p>
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancel-archive"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                    Cancel
                </button>
                <button type="button" id="confirm-archive"
                    class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition duration-200">
                    Archive
                </button>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restore-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4 text-green-600">Restore Announcement</h2>
            <p class="mb-4">Are you sure you want to restore this announcement?</p>
            <p class="font-semibold mb-4" id="restore-announcement-title"></p>
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancel-restore"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                    Cancel
                </button>
                <button type="button" id="confirm-restore"
                    class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition duration-200">
                    Restore
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4 text-red-600">Delete Announcement</h2>
            <p class="mb-4">Are you sure you want to permanently delete this announcement? This action cannot be undone.</p>
            <p class="font-semibold mb-4" id="delete-announcement-title"></p>
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancel-delete"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                    Cancel
                </button>
                <button type="button" id="confirm-delete"
                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200">
                    Delete Permanently
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        // PDF.js setup
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

            <?php foreach ($activeAnnouncements as $index => $announcement): ?>
                <?php if ($announcement['file_type'] === 'pdf'): ?>
                    // Render PDF preview
                    pdfjsLib.getDocument("<?= $announcement['file_path'] ?>").promise.then(function(pdf) {
                        return pdf.getPage(1);
                    }).then(function(page) {
                        const container = document.getElementById("file-preview-<?= $index ?>");
                        const scale = 0.5;
                        const viewport = page.getViewport({
                            scale: scale
                        });
                        const canvas = document.createElement("canvas");
                        const context = canvas.getContext("2d");
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        container.innerHTML = "";
                        container.appendChild(canvas);
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        page.render(renderContext);
                    }).catch(function(error) {
                        console.error('PDF preview error:', error);
                        document.getElementById("file-preview-<?= $index ?>").innerHTML =
                            '<div class="text-red-500 p-2 text-center">Could not load PDF preview</div>';
                    });
                <?php endif; ?>
            <?php endforeach; ?>
        }

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-tab');

                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('text-orange-600', 'border-b-2', 'border-orange-500');
                    btn.classList.add('text-gray-500');
                });
                button.classList.remove('text-gray-500');
                button.classList.add('text-orange-600', 'border-b-2', 'border-orange-500');

                // Show corresponding tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById(`${tabId}-tab`).classList.remove('hidden');
            });
        });

        // Upload modal
        document.getElementById('upload-announcement-btn').addEventListener('click', () => {
            document.getElementById('upload-modal').classList.remove('hidden');
        });

        document.getElementById('cancel-upload-btn').addEventListener('click', () => {
            document.getElementById('upload-modal').classList.add('hidden');
        });

        // Edit modal
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const description = button.getAttribute('data-description');

                document.getElementById('edit-id').value = id;
                document.getElementById('edit-description').value = description;
                document.getElementById('edit-modal').classList.remove('hidden');
            });
        });

        document.getElementById('cancel-edit').addEventListener('click', () => {
            document.getElementById('edit-modal').classList.add('hidden');
        });

        // Archive modal
        document.querySelectorAll('.archive-btn').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const description = button.getAttribute('data-description');

                document.getElementById('archive-announcement-title').textContent = description;
                document.getElementById('confirm-archive').setAttribute('data-id', id);
                document.getElementById('archive-modal').classList.remove('hidden');
            });
        });

        document.getElementById('cancel-archive').addEventListener('click', () => {
            document.getElementById('archive-modal').classList.add('hidden');
        });

        document.getElementById('confirm-archive').addEventListener('click', () => {
            const id = document.getElementById('confirm-archive').getAttribute('data-id');

            fetch('archive_announcement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while archiving the announcement.');
                });
        });

        // Restore modal
        document.querySelectorAll('.restore-btn').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const description = button.getAttribute('data-description');

                document.getElementById('restore-announcement-title').textContent = description;
                document.getElementById('confirm-restore').setAttribute('data-id', id);
                document.getElementById('restore-modal').classList.remove('hidden');
            });
        });

        document.getElementById('cancel-restore').addEventListener('click', () => {
            document.getElementById('restore-modal').classList.add('hidden');
        });

        document.getElementById('confirm-restore').addEventListener('click', () => {
            const id = document.getElementById('confirm-restore').getAttribute('data-id');

            fetch('restore_announcement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while restoring the announcement.');
                });
        });

        // Delete modal
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const description = button.getAttribute('data-description');

                document.getElementById('delete-announcement-title').textContent = description;
                document.getElementById('confirm-delete').setAttribute('data-id', id);
                document.getElementById('delete-modal').classList.remove('hidden');
            });
        });

        document.getElementById('cancel-delete').addEventListener('click', () => {
            document.getElementById('delete-modal').classList.add('hidden');
        });

        document.getElementById('confirm-delete').addEventListener('click', () => {
            const id = document.getElementById('confirm-delete').getAttribute('data-id');

            fetch('delete_announcement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the announcement.');
                });
        });

        // View file modals
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.getAttribute('data-index');
                const fileType = button.getAttribute('data-file-type');
                const filePath = button.getAttribute('data-file-path');

                const modal = document.getElementById(`file-modal-${index}`);
                modal.style.display = 'block';
                modal.classList.add('modal-active');

                // Load file content based on type
                const container = document.getElementById(`pdfContainer-${index}`);
                container.innerHTML = '<div class="loading-spinner"></div><p class="text-center text-gray-600">Loading file...</p>';

                if (fileType === 'pdf') {
                    // Load PDF
                    pdfjsLib.getDocument(filePath).promise.then(function(pdf) {
                        let pageNum = 1;
                        const pageRendering = pdf.getPage(pageNum).then(function(page) {
                            const scale = 1.5;
                            const viewport = page.getViewport({
                                scale: scale
                            });
                            const canvas = document.createElement('canvas');
                            const context = canvas.getContext('2d');
                            canvas.height = viewport.height;
                            canvas.width = viewport.width;

                            container.innerHTML = '';
                            container.appendChild(canvas);

                            const renderContext = {
                                canvasContext: context,
                                viewport: viewport
                            };
                            return page.render(renderContext).promise;
                        });

                        // Update page navigation
                        document.getElementById(`pageIndicator-${index}`).textContent = `Page ${pageNum} of ${pdf.numPages}`;
                        document.getElementById(`prevPageBtn-${index}`).disabled = pageNum <= 1;
                        document.getElementById(`nextPageBtn-${index}`).disabled = pageNum >= pdf.numPages;

                        // Page navigation
                        document.getElementById(`prevPageBtn-${index}`).onclick = function() {
                            if (pageNum <= 1) return;
                            pageNum--;
                            pdf.getPage(pageNum).then(function(page) {
                                const scale = 1.5;
                                const viewport = page.getViewport({
                                    scale: scale
                                });
                                const canvas = document.createElement('canvas');
                                const context = canvas.getContext('2d');
                                canvas.height = viewport.height;
                                canvas.width = viewport.width;

                                container.innerHTML = '';
                                container.appendChild(canvas);

                                const renderContext = {
                                    canvasContext: context,
                                    viewport: viewport
                                };
                                page.render(renderContext);

                                document.getElementById(`pageIndicator-${index}`).textContent = `Page ${pageNum} of ${pdf.numPages}`;
                                document.getElementById(`prevPageBtn-${index}`).disabled = pageNum <= 1;
                                document.getElementById(`nextPageBtn-${index}`).disabled = pageNum >= pdf.numPages;
                            });
                        };

                        document.getElementById(`nextPageBtn-${index}`).onclick = function() {
                            if (pageNum >= pdf.numPages) return;
                            pageNum++;
                            pdf.getPage(pageNum).then(function(page) {
                                const scale = 1.5;
                                const viewport = page.getViewport({
                                    scale: scale
                                });
                                const canvas = document.createElement('canvas');
                                const context = canvas.getContext('2d');
                                canvas.height = viewport.height;
                                canvas.width = viewport.width;

                                container.innerHTML = '';
                                container.appendChild(canvas);

                                const renderContext = {
                                    canvasContext: context,
                                    viewport: viewport
                                };
                                page.render(renderContext);

                                document.getElementById(`pageIndicator-${index}`).textContent = `Page ${pageNum} of ${pdf.numPages}`;
                                document.getElementById(`prevPageBtn-${index}`).disabled = pageNum <= 1;
                                document.getElementById(`nextPageBtn-${index}`).disabled = pageNum >= pdf.numPages;
                            });
                        };
                    }).catch(function(error) {
                        console.error('PDF loading error:', error);
                        container.innerHTML = '<div class="text-red-500 p-4 text-center">Could not load PDF</div>';
                    });
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType)) {
                    // Load image
                    container.innerHTML = `<div class="image-container"><img src="${filePath}" alt="Announcement" class="image-viewer"></div>`;
                } else {
                    // Load document using Office Online Viewer
                    container.innerHTML = `<iframe src="https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(window.location.origin + '/' + filePath)}" class="office-viewer"></iframe>`;
                }
            });
        });

        function closeFileModal(index) {
            const modal = document.getElementById(`file-modal-${index}`);
            modal.style.display = 'none';
            modal.classList.remove('modal-active');
        }
    </script>
</body>

</html>