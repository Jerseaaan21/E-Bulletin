<?php
include "db.php";
session_start();

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';
$dept_id = $_SESSION['dept_id'] ?? 0;

// Create dynamic upload path
$uploadBaseDir = "uploads/{$dept_acronym}/Announcement/";

// Get announcements by status
$approved = [];
$pending = [];
$not_approved = [];
$archived = [];

// Approved announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1) 
          AND dp.dept_id = ? 
          AND dp.status = 'Approved' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $approved[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Announcement/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Pending announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1) 
          AND dp.dept_id = ? 
          AND dp.status = 'Pending' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $pending[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Announcement/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Not Approved announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1) 
          AND dp.dept_id = ? 
          AND dp.status = 'Not Approved' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $not_approved[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Announcement/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'], // This contains the rejection reason
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Archived announcements
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1) 
          AND dp.dept_id = ? 
          AND dp.status = 'Archived' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $archived[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Announcement/archive/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
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
    <title>Announcements Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
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

        .announcement-delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            align-items: center;
            justify-content: center;
        }

        .announcement-delete-modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }

        .announcement-delete-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .announcement-delete-modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #dc2626;
        }

        .announcement-delete-modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .announcement-delete-modal-close:hover {
            color: #dc2626;
        }

        .announcement-delete-modal-body {
            margin-bottom: 20px;
        }

        .announcement-delete-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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

        .approved {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .pending {
            background-color: #fffbeb;
            border: 1px solid #fef08a;
        }

        .not-approved {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
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

        .approved .status-title {
            color: #16a34a;
            border-color: #16a34a;
        }

        .pending .status-title {
            color: #d97706;
            border-color: #d97706;
        }

        .not-approved .status-title {
            color: #dc2626;
            border-color: #dc2626;
        }

        .archived .status-title {
            color: #2563eb;
            border-color: #2563eb;
        }

        /* Ensure modals are on top when opened */
        .modal-active {
            z-index: 1001 !important;
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
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0">
                <i class="fas fa-bullhorn mr-3 w-5"></i> Announcements Management
            </h1>
            <button id="upload-announcement-btn"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-upload mr-2"></i> Upload Announcement
            </button>
        </div>

        <!-- Approved Announcements -->
        <div class="status-section approved">
            <h2 class="status-title">Approved Announcements</h2>
            <?php if (count($approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-green-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="file-preview-approved-<?= $index ?>" class="file-preview">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                    <?= htmlspecialchars($pdf['description']) ?>
                                </div>
                                <p class="card-text text-gray-600 text-sm truncate">
                                    <?= basename($pdf['file_path']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted by: <?= htmlspecialchars($pdf['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="view-full-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-archive-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Archive">
                                    <i class="fas fa-archive fa-sm"></i>
                                    Archive
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox fa-3x mb-4"></i>
                    <p class="text-lg">No approved announcements yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Announcements -->
        <div class="status-section pending">
            <h2 class="status-title">Pending Announcements</h2>
            <?php if (count($pending) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($pending as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="file-preview-pending-<?= $index ?>" class="file-preview">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                    <?= htmlspecialchars($pdf['description']) ?>
                                </div>
                                <p class="card-text text-gray-600 text-sm truncate">
                                    <?= basename($pdf['file_path']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted by: <?= htmlspecialchars($pdf['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="view-full-pending-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 edit-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                    Edit
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox fa-3x mb-4"></i>
                    <p class="text-lg">No pending announcements</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Not Approved Announcements -->
        <div class="status-section not-approved">
            <h2 class="status-title">Not Approved Announcements</h2>
            <?php if (count($not_approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($not_approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-red-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="file-preview-not-approved-<?= $index ?>" class="file-preview">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                    <?= htmlspecialchars($pdf['description']) ?>
                                </div>
                                <p class="card-text text-gray-600 text-sm overflow-hidden">
                                    Reason: <?= htmlspecialchars($pdf['content']) ?>
                                </p>
                                <p class="card-text text-gray-600 text-sm truncate">
                                    <?= basename($pdf['file_path']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted by: <?= htmlspecialchars($pdf['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="view-full-not-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox fa-3x mb-4"></i>
                    <p class="text-lg">No not approved announcements</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Announcements -->
        <div class="status-section archived">
            <h2 class="status-title">Archived Announcements</h2>
            <?php if (count($archived) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archived as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="file-preview-archived-<?= $index ?>" class="file-preview">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="file-title font-semibold text-gray-800 text-lg mb-1 truncate">
                                    <?= htmlspecialchars($pdf['description']) ?>
                                </div>
                                <p class="card-text text-gray-600 text-sm truncate">
                                    <?= basename($pdf['file_path']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted by: <?= htmlspecialchars($pdf['user_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                    Delete
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
    <?php foreach ($approved as $index => $pdf): ?>
        <div id="file-modal-approved-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeFileModal('approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="pdfContainer-approved-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="page-navigation">
                            <button id="prevPageBtn-approved-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="pageIndicator-approved-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                            <button id="nextPageBtn-approved-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($pending as $index => $pdf): ?>
        <div id="file-modal-pending-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeFileModal('pending', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="pdfContainer-pending-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="page-navigation">
                            <button id="prevPageBtn-pending-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="pageIndicator-pending-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                            <button id="nextPageBtn-pending-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($not_approved as $index => $pdf): ?>
        <div id="file-modal-not-approved-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeFileModal('not-approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="pdfContainer-not-approved-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="page-navigation">
                            <button id="prevPageBtn-not-approved-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="pageIndicator-not-approved-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                            <button id="nextPageBtn-not-approved-<?= $index ?>" class="page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archived as $index => $pdf): ?>
        <div id="file-modal-archived-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
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
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
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

    <!-- Archive/Delete Confirmation Modal for Announcements -->
    <div id="announcement-delete-modal" class="announcement-delete-modal">
        <div class="announcement-delete-modal-content">
            <div class="announcement-delete-modal-header">
                <h3 class="announcement-delete-modal-title">Choose Action</h3>
                <span class="announcement-delete-modal-close" onclick="closeAnnouncementDeleteModal()">&times;</span>
            </div>
            <div class="announcement-delete-modal-body">
                <p>What would you like to do with this announcement?</p>
                <p class="font-semibold mt-2" id="delete-announcement-title"></p>
            </div>
            <div class="announcement-delete-modal-footer">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" onclick="closeAnnouncementDeleteModal()">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="archive-announcement-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="confirm-announcement-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        // Set PDF.js worker source
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        // Global variables for PDF handling
        window.pdfDocs = {};
        window.currentPageNum = {};
        window.totalPages = {};

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded - initializing view buttons');

            // Check if PDF.js is loaded
            if (typeof pdfjsLib === 'undefined') {
                console.error('PDF.js library not loaded');
                // Fallback: show file icons for PDFs
                document.querySelectorAll('.file-preview').forEach(container => {
                    if (container.innerHTML.includes('loading-spinner') && !container.querySelector('.fa-file')) {
                        container.innerHTML = '<i class="fas fa-file-pdf file-icon pdf text-4xl"></i>';
                    }
                });
            } else {
                // Initialize all view buttons
                initializeViewButtons();

                // Initialize all other functionality
                initializeOtherFunctionality();

                // Initialize PDF previews
                initializePDFPreviews();
            }
        });

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

        // Display file content in modal
        function displayFileContent(fileType, filePath, status, index, container) {
            const fileExtension = filePath.split('.').pop().toLowerCase();

            // Clear container and show loading
            container.innerHTML = `
                <div class="loading-spinner"></div>
                <p class="text-center text-gray-600">Loading file...</p>
            `;

            if (fileExtension === 'pdf') {
                loadPDFFile(filePath, status, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                container.innerHTML = `
                    <div class="image-container">
                        <img src="${filePath}" alt="Full view" class="image-viewer" 
                             onerror="this.onerror=null; this.style.display='none'; 
                             container.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-lg text-gray-700\\'>Failed to load image</p></div>'">
                    </div>
                `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                // Use Microsoft Office Online viewer
                const fullUrl = window.location.origin + '/' + filePath;
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
            const key = `${status}-${index}`;
            if (!window.pdfDocs[key]) return;

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

                page.render({
                    canvasContext: ctx,
                    viewport: scaledViewport
                });
            });
        }

        // Navigation functions
        function goToPrevPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.pdfDocs || !window.pdfDocs[key]) return;

            if (window.currentPageNum[key] > 1) {
                window.currentPageNum[key]--;
                renderPDFPage(status, index, window.currentPageNum[key]);

                // Update navigation buttons
                const prevBtn = document.getElementById(`prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`nextPageBtn-${status}-${index}`);
                const pageIndicator = document.getElementById(`pageIndicator-${status}-${index}`);

                if (prevBtn) prevBtn.disabled = window.currentPageNum[key] === 1;
                if (nextBtn) nextBtn.disabled = false;
                if (pageIndicator) pageIndicator.textContent = `Page ${window.currentPageNum[key]} of ${window.totalPages[key]}`;
            }
        }

        function goToNextPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.pdfDocs || !window.pdfDocs[key]) return;

            if (window.currentPageNum[key] < window.totalPages[key]) {
                window.currentPageNum[key]++;
                renderPDFPage(status, index, window.currentPageNum[key]);

                // Update navigation buttons
                const prevBtn = document.getElementById(`prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`nextPageBtn-${status}-${index}`);
                const pageIndicator = document.getElementById(`pageIndicator-${status}-${index}`);

                if (prevBtn) prevBtn.disabled = false;
                if (nextBtn) nextBtn.disabled = window.currentPageNum[key] === window.totalPages[key];
                if (pageIndicator) pageIndicator.textContent = `Page ${window.currentPageNum[key]} of ${window.totalPages[key]}`;
            }
        }

        // Initialize PDF previews for thumbnails
        function initializePDFPreviews() {
            // Function to render PDF preview with proper sizing
            function renderPDFPreview(filePath, containerId) {
                console.log('Rendering PDF preview for:', filePath, containerId);

                const container = document.getElementById(containerId);
                if (!container) {
                    console.log('Container not found:', containerId);
                    return;
                }

                // Show loading state
                container.innerHTML = '<div class="loading-spinner"></div>';

                pdfjsLib.getDocument(filePath).promise.then(function(pdf) {
                    return pdf.getPage(1);
                }).then(function(page) {
                    // Get container dimensions
                    const containerWidth = container.clientWidth - 10; // Reduced margin
                    const containerHeight = container.clientHeight - 10; // Reduced margin

                    // Calculate scale to fit container
                    const viewport = page.getViewport({
                        scale: 1.0
                    });
                    const scale = Math.min(
                        containerWidth / viewport.width,
                        containerHeight / viewport.height,
                        1.5 // Reduced max scale for better fit
                    );

                    const scaledViewport = page.getViewport({
                        scale: scale
                    });
                    const canvas = document.createElement("canvas");
                    const context = canvas.getContext("2d");

                    // Set canvas dimensions
                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    canvas.style.width = '100%';
                    canvas.style.height = '100%';
                    canvas.style.objectFit = 'contain';
                    canvas.style.borderRadius = '4px';

                    // Clear container and add canvas
                    container.innerHTML = "";
                    container.appendChild(canvas);

                    const renderContext = {
                        canvasContext: context,
                        viewport: scaledViewport
                    };

                    return page.render(renderContext).promise;
                }).catch(function(error) {
                    console.error('PDF preview error for', containerId, ':', error);
                    container.innerHTML =
                        '<div class="flex flex-col items-center justify-center h-full p-2">' +
                        '<i class="fas fa-file-pdf text-red-500 text-3xl mb-1"></i>' +
                        '<p class="text-red-500 text-xs text-center">Preview unavailable</p>' +
                        '</div>';
                });
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

            // Render PDF previews for each status with delays to prevent overwhelming the browser
            <?php
            $totalDelay = 0;
            foreach ($approved as $index => $pdf): ?>
                <?php if ($pdf['file_type'] === 'pdf'): ?>
                    setTimeout(() => {
                        renderPDFPreview("<?= $pdf['file_path'] ?>", "file-preview-approved-<?= $index ?>");
                    }, <?= $totalDelay + ($index * 200) ?>);
                <?php endif; ?>
            <?php
            endforeach;
            $totalDelay += count($approved) * 200;
            ?>

            <?php foreach ($pending as $index => $pdf): ?>
                <?php if ($pdf['file_type'] === 'pdf'): ?>
                    setTimeout(() => {
                        renderPDFPreview("<?= $pdf['file_path'] ?>", "file-preview-pending-<?= $index ?>");
                    }, <?= $totalDelay + ($index * 200) ?>);
                <?php endif; ?>
            <?php
            endforeach;
            $totalDelay += count($pending) * 200;
            ?>

            <?php foreach ($not_approved as $index => $pdf): ?>
                <?php if ($pdf['file_type'] === 'pdf'): ?>
                    setTimeout(() => {
                        renderPDFPreview("<?= $pdf['file_path'] ?>", "file-preview-not-approved-<?= $index ?>");
                    }, <?= $totalDelay + ($index * 200) ?>);
                <?php endif; ?>
            <?php
            endforeach;
            $totalDelay += count($not_approved) * 200;
            ?>

            <?php foreach ($archived as $index => $pdf): ?>
                <?php if ($pdf['file_type'] === 'pdf'): ?>
                    setTimeout(() => {
                        renderPDFPreview("<?= $pdf['file_path'] ?>", "file-preview-archived-<?= $index ?>");
                    }, <?= $totalDelay + ($index * 200) ?>);
                <?php endif; ?>
            <?php endforeach; ?>
        }

        // Initialize all other functionality
        function initializeOtherFunctionality() {
            // Upload button functionality
            const uploadBtn = document.getElementById('upload-announcement-btn');
            const uploadModal = document.getElementById('upload-modal');
            const cancelUploadBtn = document.getElementById('cancel-upload-btn');
            const uploadForm = document.getElementById('upload-form');

            if (uploadBtn && uploadModal) {
                uploadBtn.addEventListener('click', function() {
                    uploadModal.classList.remove('hidden');
                });
            }

            if (cancelUploadBtn && uploadModal) {
                cancelUploadBtn.addEventListener('click', function() {
                    uploadModal.classList.add('hidden');
                });
            }

            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;

                    // Show loading state
                    submitBtn.textContent = 'Uploading...';
                    submitBtn.disabled = true;

                    fetch('AddAnnouncement.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(data => {
                            if (data.trim() === 'success') {
                                // Close modal and reload
                                uploadModal.classList.add('hidden');
                                location.reload();
                            } else {
                                alert('Error uploading announcement: ' + data);
                                submitBtn.textContent = originalText;
                                submitBtn.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while uploading the announcement');
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                        });
                });
            }

            // Edit button functionality
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    // Set the values in the modal
                    document.getElementById('edit-id').value = id;
                    document.getElementById('edit-description').value = description;

                    // Show the modal
                    document.getElementById('edit-modal').classList.remove('hidden');
                });
            });

            // Cancel edit button
            const cancelEditBtn = document.getElementById('cancel-edit');
            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', function() {
                    document.getElementById('edit-modal').classList.add('hidden');
                });
            }

            // Form submission handling for edit
            const editForm = document.getElementById('edit-form');
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
                        // Generate new filename with timestamp and random component for uniqueness
                        const now = new Date();
                        const day = now.getDate().toString().padStart(2, '0');
                        const month = (now.getMonth() + 1).toString().padStart(2, '0');
                        const year = now.getFullYear();
                        const hours = now.getHours().toString().padStart(2, '0');
                        const minutes = now.getMinutes().toString().padStart(2, '0');
                        const seconds = now.getSeconds().toString().padStart(2, '0');
                        const random = Math.floor(Math.random() * 9000) + 1000;

                        // Get file extension
                        const fileName = fileInput.files[0].name;
                        const fileExtension = fileName.split('.').pop();

                        // Format: announcement_DD_MM_YYYY_HH_MM_SS_RANDOM.extension
                        const newFilename = `announcement_${day}_${month}_${year}_${hours}_${minutes}_${seconds}_${random}.${fileExtension}`;

                        // Get the original file and rename it
                        const originalFile = fileInput.files[0];
                        const renamedFile = new File([originalFile], newFilename, {
                            type: originalFile.type
                        });

                        formData.append('file', renamedFile);
                    }

                    // Send the data to the server
                    fetch('UpdateAnnouncement.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Hide modal and reload the page
                                document.getElementById('edit-modal').classList.add('hidden');
                                location.reload();
                            } else {
                                alert('Error updating announcement: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while updating the announcement');
                        });
                });
            }

            // Archive/Delete button functionality for announcements
            let announcementToAction = null;

            document.querySelectorAll('.announcement-delete-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    announcementToAction = id;

                    // Set the announcement title in the delete confirmation modal
                    document.getElementById('delete-announcement-title').textContent = description;

                    // Show the delete confirmation modal
                    document.getElementById('announcement-delete-modal').style.display = 'flex';
                });
            });

            document.querySelectorAll('.announcement-archive-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    announcementToAction = id;

                    // Set the announcement title in the delete confirmation modal
                    document.getElementById('delete-announcement-title').textContent = description;

                    // Show the delete confirmation modal
                    document.getElementById('announcement-delete-modal').style.display = 'flex';
                });
            });

            // Archive button
            const archiveBtn = document.getElementById('archive-announcement-btn');
            if (archiveBtn) {
                archiveBtn.addEventListener('click', function() {
                    if (announcementToAction) {
                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                        // Send the archive request to the server
                        fetch('ArchiveAnnouncement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(announcementToAction)
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Hide modal and reload the page
                                    closeAnnouncementDeleteModal();
                                    location.reload();
                                } else {
                                    alert('Error archiving announcement: ' + data.message);
                                    // Re-enable the button
                                    document.getElementById('archive-announcement-btn').disabled = false;
                                    document.getElementById('archive-announcement-btn').innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while archiving the announcement');
                                // Re-enable the button
                                document.getElementById('archive-announcement-btn').disabled = false;
                                document.getElementById('archive-announcement-btn').innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                            });
                    }
                });
            }

            // Delete button
            const deleteBtn = document.getElementById('confirm-announcement-delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    if (announcementToAction) {
                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                        // Send the delete request to the server
                        fetch('DeleteAnnouncement.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(announcementToAction)
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Hide modal and reload the page
                                    closeAnnouncementDeleteModal();
                                    location.reload();
                                } else {
                                    alert('Error deleting announcement: ' + data.message);
                                    // Re-enable the button
                                    document.getElementById('confirm-announcement-delete-btn').disabled = false;
                                    document.getElementById('confirm-announcement-delete-btn').innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while deleting the announcement');
                                // Re-enable the button
                                document.getElementById('confirm-announcement-delete-btn').disabled = false;
                                document.getElementById('confirm-announcement-delete-btn').innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                            });
                    }
                });
            }

            // Initialize page navigation
            initializePageNavigation();
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

        function closeAnnouncementDeleteModal() {
            document.getElementById('announcement-delete-modal').style.display = 'none';
        }

        // Re-initialize view buttons if content is loaded dynamically
        setTimeout(() => {
            initializeViewButtons();
            initializePageNavigation();
        }, 1000);
    </script>
</body>

</html>