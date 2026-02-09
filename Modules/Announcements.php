<?php
include "db.php";
session_start();

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';
$dept_id = $_SESSION['dept_id'] ?? 0;

// Create dynamic upload path
$uploadBaseDir = "../../uploads/{$dept_acronym}/Announcement/";

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
    <title>DIT Announcements</title>
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

        .btn-cancel {
            padding: 8px 16px;
            border: 1px solid #6b7280;
            border-radius: 4px;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            transform: scale(1);
        }

        .btn-cancel:hover {
            background-color: #6b7280;
            color: white;
            transform: scale(1.05);
        }

        .btn-archive {
            padding: 8px 16px;
            border: 1px solid #eab308;
            border-radius: 4px;
            color: #eab308;
            cursor: pointer;
            transition: all 0.2s;
            transform: scale(1);
        }

        .btn-archive:hover {
            background-color: #eab308;
            color: white;
            transform: scale(1.05);
        }

        .btn-delete {
            padding: 8px 16px;
            border: 1px solid #ef4444;
            border-radius: 4px;
            color: #ef4444;
            cursor: pointer;
            transition: all 0.2s;
            transform: scale(1);
        }

        .btn-delete:hover {
            background-color: #ef4444;
            color: white;
            transform: scale(1.05);
        }

        .file-icon {
            font-size: 4rem;
        }

        .file-icon.pdf {
            color: #dc2626;
        }

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
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0">DIT Announcements</h1>
            <button onclick="openAnnouncementPdfModal()"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-upload"></i> Upload Announcement
            </button>
        </div>

        <!-- Approved Announcements -->
        <div class="status-section approved">
            <h2 class="status-title">Approved Announcements</h2>
            <?php if (count($approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-green-500 transition duration-200 transform hover:scale-110">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-approved-<?= $index ?>" class="file-preview">
                                    <div class="loading-spinner"></div>
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
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="pdf" data-file-path="<?= $pdf['file_path'] ?>">
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
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-110">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-pending-<?= $index ?>" class="file-preview">
                                    <div class="loading-spinner"></div>
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
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-pending-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="pdf" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 announcement-edit-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" title="Edit">
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
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-red-500 transition duration-200 transform hover:scale-110">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-not-approved-<?= $index ?>" class="file-preview">
                                    <div class="loading-spinner"></div>
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
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-not-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="pdf" data-file-path="<?= $pdf['file_path'] ?>">
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
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-110">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="announcement-file-preview-archived-<?= $index ?>" class="file-preview">
                                    <div class="loading-spinner"></div>
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
                                    Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?>
                                </p>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <button id="announcement-view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="pdf" data-file-path="<?= $pdf['file_path'] ?>">
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
        <div id="announcement-file-modal-approved-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeAnnouncementFileModal('approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="announcement-pdfContainer-approved-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <div class="page-navigation">
                        <button id="announcement-prevPageBtn-approved-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div id="announcement-pageIndicator-approved-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                        <button id="announcement-nextPageBtn-approved-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($pending as $index => $pdf): ?>
        <div id="announcement-file-modal-pending-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeAnnouncementFileModal('pending', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="announcement-pdfContainer-pending-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <div class="page-navigation">
                        <button id="announcement-prevPageBtn-pending-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div id="announcement-pageIndicator-pending-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                        <button id="announcement-nextPageBtn-pending-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($not_approved as $index => $pdf): ?>
        <div id="announcement-file-modal-not-approved-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeAnnouncementFileModal('not-approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="announcement-pdfContainer-not-approved-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <div class="page-navigation">
                        <button id="announcement-prevPageBtn-not-approved-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div id="announcement-pageIndicator-not-approved-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                        <button id="announcement-nextPageBtn-not-approved-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archived as $index => $pdf): ?>
        <div id="announcement-file-modal-archived-<?= $index ?>" class="file-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="modal-close" onclick="closeAnnouncementFileModal('archived', <?= $index ?>)">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="announcement-pdfContainer-archived-<?= $index ?>" class="pdf-container">
                        <div class="loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading announcement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-meta">
                        Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <div class="page-navigation">
                        <button id="announcement-prevPageBtn-archived-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div id="announcement-pageIndicator-archived-<?= $index ?>" class="page-indicator">Page 1 of 1</div>
                        <button id="announcement-nextPageBtn-archived-<?= $index ?>" class="page-nav-btn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Edit Modal -->
    <div id="announcement-edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Announcement</h3>
                <form id="announcement-edit-form" class="mt-2 py-3" enctype="multipart/form-data">
                    <input type="hidden" id="announcement-edit-id" name="id">
                    <div class="mb-4">
                        <label for="announcement-edit-description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <input type="text" id="announcement-edit-description" name="description"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label for="announcement-edit-file" class="block text-gray-700 text-sm font-bold mb-2">Replace PDF (optional):</label>
                        <input type="file" id="announcement-edit-file" name="file" accept=".pdf"
                            class="block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-1 file:border-gray-500
                                  file:text-sm file:font-semibold
                                  file:bg-gray-50 file:text-gray-700
                                  hover:file:bg-gray-300 hover:file:text-black 
                                  hover:file:transition duration-200 transform hover:scale-110">
                        <p class="text-xs text-gray-500 mt-1">Only PDF files are accepted</p>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" onclick="document.getElementById('announcement-edit-modal').classList.add('hidden')"
                            class="border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white px-4 py-2 rounded-md transition duration-200 transform hover:scale-110">
                            Cancel
                        </button>
                        <button type="submit"
                            class="border border-green-500 text-green-500 hover:bg-green-500 hover:text-white px-4 py-2 rounded-md transition duration-200 transform hover:scale-110">
                            <i class="fas fa-save fa-sm"></i>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="announcement-delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-red-600">Confirm Action</h3>
                <span class="text-2xl cursor-pointer text-gray-500 hover:text-red-600" onclick="closeAnnouncementDeleteModal()">&times;</span>
            </div>
            <div class="mb-4">
                <p>Would you like to archive or delete this announcement?</p>
                <p class="font-semibold mt-2" id="announcement-delete-announcement-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white px-4 py-2 rounded-md transition duration-200 transform hover:scale-110" onclick="closeAnnouncementDeleteModal()">
                    Cancel
                </button>
                <button class="border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white px-4 py-2 rounded-md transition duration-200 transform hover:scale-110" id="announcement-confirm-archive-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-4 py-2 rounded-md transition duration-200 transform hover:scale-110" id="announcement-confirm-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- PDF Upload Modal -->
    <div id="announcement-pdfUploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Upload Announcement</h2>
            <form action="DIT_Add_Announcement.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="announcement-description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input
                        type="text"
                        id="announcement-description"
                        name="description"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="announcement-pdfFile" class="block text-sm font-medium text-gray-700">PDF File</label>
                    <input
                        type="file"
                        id="announcement-pdfFile"
                        name="pdfFile"
                        accept=".pdf"
                        required
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-sm text-gray-500">Only PDF files are accepted.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        onclick="closeAnnouncementPdfModal()"
                        class="border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white px-4 py-2 rounded-md transition duration-200 transform hover:scale-110">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded-md transition duration-200 transform hover:scale-110">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        document.addEventListener('DOMContentLoaded', function() {
            // PDF handling code
            const pdfDocs = {};
            const currentPageNum = {};
            const totalPages = {};

            // Render PDF previews
            function renderPdfPreviews(pdfs, status) {
                pdfs.forEach((pdf, index) => {
                    // Render first page as preview
                    pdfjsLib.getDocument(pdf.file_path).promise.then(function(pdfDoc) {
                        return pdfDoc.getPage(1);
                    }).then(function(page) {
                        const container = document.getElementById(`announcement-file-preview-${status}-${index}`);
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
                        document.getElementById(`announcement-file-preview-${status}-${index}`).innerHTML =
                            '<div class="text-red-500 p-2 text-center">Could not load PDF preview</div>';
                    });

                    // Set up view buttons for each file
                    document.getElementById(`announcement-view-full-${status}-${index}`).addEventListener("click", function() {
                        const modal = document.getElementById(`announcement-file-modal-${status}-${index}`);
                        const container = document.getElementById(`announcement-pdfContainer-${status}-${index}`);
                        const fileType = this.getAttribute('data-file-type');
                        const filePath = this.getAttribute('data-file-path');

                        // Set modal to highest z-index
                        modal.classList.add('modal-active');
                        modal.style.display = "block";

                        // Load PDF
                        pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                            pdfDocs[`${status}-${index}`] = pdfDoc;
                            totalPages[`${status}-${index}`] = pdfDoc.numPages;
                            currentPageNum[`${status}-${index}`] = 1;

                            // Update page indicator
                            document.getElementById(`announcement-pageIndicator-${status}-${index}`).textContent = `Page 1 of ${totalPages[`${status}-${index}`]}`;

                            // Enable/disable navigation buttons
                            document.getElementById(`announcement-prevPageBtn-${status}-${index}`).disabled = true;
                            document.getElementById(`announcement-nextPageBtn-${status}-${index}`).disabled = totalPages[`${status}-${index}`] <= 1;

                            // Render first page
                            renderPage(status, index, 1);
                        }).catch(error => {
                            console.error('Error loading PDF:', error);
                            container.innerHTML = `
                                <div class="text-center py-8">
                                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                                    <p class="text-lg text-gray-700 mb-2">Failed to load announcement</p>
                                    <p class="text-gray-600 mb-4">${error.message}</p>
                                </div>
                            `;
                        });
                    });

                    // Page navigation functions for this PDF
                    document.getElementById(`announcement-prevPageBtn-${status}-${index}`).addEventListener('click', function() {
                        goToPrevPage(status, index);
                    });

                    document.getElementById(`announcement-nextPageBtn-${status}-${index}`).addEventListener('click', function() {
                        goToNextPage(status, index);
                    });

                    // Close modal when clicking outside content
                    document.getElementById(`announcement-file-modal-${status}-${index}`).addEventListener("click", function(e) {
                        if (e.target === this) {
                            closeAnnouncementFileModal(status, index);
                        }
                    });
                });
            }

            // Function to render a page
            function renderPage(status, index, pageNum) {
                const key = `${status}-${index}`;
                if (!pdfDocs[key]) return;

                const container = document.getElementById(`announcement-pdfContainer-${status}-${index}`);
                const modalBody = document.querySelector(`#announcement-file-modal-${status}-${index} .modal-body`);

                // Show loading indicator
                container.innerHTML = `
                    <div class="loading-spinner"></div>
                    <p class="text-center text-gray-600">Loading page ${pageNum}...</p>
                `;

                pdfDocs[key].getPage(pageNum).then(page => {
                    // Get the dimensions of the modal body
                    const modalRect = modalBody.getBoundingClientRect();
                    const availableWidth = modalRect.width;
                    const availableHeight = modalRect.height;

                    // Get the viewport at a readable scale (1.5)
                    const viewport = page.getViewport({
                        scale: 1.5
                    });

                    // Calculate the scale needed to fit the width
                    const widthScale = availableWidth / viewport.width;
                    // Calculate the scale needed to fit the height
                    const heightScale = availableHeight / viewport.height;

                    // Use the smaller scale to ensure the entire page fits
                    let scale = Math.min(widthScale, heightScale);

                    // Ensure the scale is not too small (minimum 0.8)
                    scale = Math.max(scale, 0.8);

                    // Get the scaled viewport
                    const scaledViewport = page.getViewport({
                        scale
                    });

                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = scaledViewport.height;
                    canvas.width = scaledViewport.width;
                    canvas.className = 'pdf-page';

                    // Clear container and add canvas
                    container.innerHTML = '';
                    container.appendChild(canvas);

                    return page.render({
                        canvasContext: context,
                        viewport: scaledViewport
                    }).promise;
                }).catch(error => {
                    console.error('Error rendering page:', error);
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <p class="text-lg text-gray-700 mb-2">Failed to load page ${pageNum}</p>
                            <p class="text-gray-600">${error.message}</p>
                        </div>
                    `;
                });
            }

            // Function to go to previous page
            function goToPrevPage(status, index) {
                const key = `${status}-${index}`;
                if (!pdfDocs[key]) return;

                if (currentPageNum[key] > 1) {
                    currentPageNum[key]--;
                    renderPage(status, index, currentPageNum[key]);

                    // Update navigation
                    document.getElementById(`announcement-prevPageBtn-${status}-${index}`).disabled = currentPageNum[key] === 1;
                    document.getElementById(`announcement-nextPageBtn-${status}-${index}`).disabled = false;
                    document.getElementById(`announcement-pageIndicator-${status}-${index}`).textContent = `Page ${currentPageNum[key]} of ${totalPages[key]}`;
                }
            }

            // Function to go to next page
            function goToNextPage(status, index) {
                const key = `${status}-${index}`;
                if (!pdfDocs[key]) return;

                if (currentPageNum[key] < totalPages[key]) {
                    currentPageNum[key]++;
                    renderPage(status, index, currentPageNum[key]);

                    // Update navigation
                    document.getElementById(`announcement-prevPageBtn-${status}-${index}`).disabled = false;
                    document.getElementById(`announcement-nextPageBtn-${status}-${index}`).disabled = currentPageNum[key] === totalPages[key];
                    document.getElementById(`announcement-pageIndicator-${status}-${index}`).textContent = `Page ${currentPageNum[key]} of ${totalPages[key]}`;
                }
            }

            // Render PDFs for each status
            renderPdfPreviews(<?= json_encode($approved) ?>, 'approved');
            renderPdfPreviews(<?= json_encode($pending) ?>, 'pending');
            renderPdfPreviews(<?= json_encode($not_approved) ?>, 'not-approved');
            renderPdfPreviews(<?= json_encode($archived) ?>, 'archived');

            // Edit button functionality (only for pending announcements)
            document.querySelectorAll('.announcement-edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const index = this.getAttribute('data-index');
                    const status = this.closest('.status-section').querySelector('.status-title').textContent.toLowerCase();

                    // Get the current announcement data
                    const description = this.closest('.bg-white').querySelector('.file-title').textContent.trim();

                    // Set the values in the modal
                    document.getElementById('announcement-edit-id').value = id;
                    document.getElementById('announcement-edit-description').value = description;

                    // Show the modal
                    document.getElementById('announcement-edit-modal').classList.remove('hidden');
                });
            });

            // Form submission handling for edit
            document.getElementById('announcement-edit-form').addEventListener('submit', function(e) {
                e.preventDefault();

                const id = document.getElementById('announcement-edit-id').value;
                const description = document.getElementById('announcement-edit-description').value;
                const fileInput = document.getElementById('announcement-edit-file');

                const formData = new FormData();
                formData.append('id', id);
                formData.append('description', description);

                if (fileInput.files.length > 0) {
                    // Generate new filename with timestamp including seconds
                    const now = new Date();
                    const day = now.getDate().toString().padStart(2, '0');
                    const month = (now.getMonth() + 1).toString().padStart(2, '0');
                    const year = now.getFullYear();
                    const hours = now.getHours().toString().padStart(2, '0');
                    const minutes = now.getMinutes().toString().padStart(2, '0');
                    const seconds = now.getSeconds().toString().padStart(2, '0');

                    // Format: announcement_DD_MM_YYYY_HH_MM_SS.pdf
                    const newFilename = `announcement_${day}_${month}_${year}_${hours}_${minutes}_${seconds}.pdf`;

                    // Get the original file and rename it
                    const originalFile = fileInput.files[0];
                    const renamedFile = new File([originalFile], newFilename, {
                        type: originalFile.type
                    });

                    formData.append('file', renamedFile);
                }

                // Send the data to the server
                fetch('DIT_Update_Announcement.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Hide modal and reload the page
                            document.getElementById('announcement-edit-modal').classList.add('hidden');
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

            // Delete button functionality
            let deleteId = null;
            document.querySelectorAll('.announcement-delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');
                    const statusSection = this.closest('.status-section');

                    // Check if this is an approved item
                    const isApproved = statusSection.classList.contains('approved');

                    deleteId = id;

                    // Set the announcement title in the delete confirmation modal
                    document.getElementById('announcement-delete-announcement-title').textContent = description;

                    // Show the delete confirmation modal
                    document.getElementById('announcement-delete-modal').classList.remove('hidden');

                    // Show/hide the archive button based on status
                    const archiveBtn = document.getElementById('announcement-confirm-archive-btn');
                    if (archiveBtn) {
                        archiveBtn.style.display = isApproved ? 'inline-block' : 'none';
                    }
                });
            });
            // Confirm archive button
            document.getElementById('announcement-confirm-archive-btn').addEventListener('click', function() {
                if (deleteId) {
                    // Disable the button to prevent multiple clicks
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                    // Send the archive request to the server
                    fetch('DIT_Archive_Announcement.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'id=' + encodeURIComponent(deleteId)
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
                                document.getElementById('announcement-confirm-archive-btn').disabled = false;
                                document.getElementById('announcement-confirm-archive-btn').innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while archiving the announcement');
                            // Re-enable the button
                            document.getElementById('announcement-confirm-archive-btn').disabled = false;
                            document.getElementById('announcement-confirm-archive-btn').innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                        });
                }
            });

            // Confirm delete button
            document.getElementById('announcement-confirm-delete-btn').addEventListener('click', function() {
                if (deleteId) {
                    // Disable the button to prevent multiple clicks
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                    // Send the delete request to the server
                    fetch('DIT_Delete_Announcement.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'id=' + encodeURIComponent(deleteId)
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
                                document.getElementById('announcement-confirm-delete-btn').disabled = false;
                                document.getElementById('announcement-confirm-delete-btn').innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting the announcement');
                            // Re-enable the button
                            document.getElementById('announcement-confirm-delete-btn').disabled = false;
                            document.getElementById('announcement-confirm-delete-btn').innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                        });
                }
            });
        });

        // Global functions
        function openAnnouncementPdfModal() {
            document.getElementById('announcement-pdfUploadModal').classList.remove('hidden');
        }

        function closeAnnouncementPdfModal() {
            document.getElementById('announcement-pdfUploadModal').classList.add('hidden');
        }

        function closeAnnouncementFileModal(status, index) {
            const modal = document.getElementById(`announcement-file-modal-${status}-${index}`);
            if (modal) {
                modal.classList.remove('modal-active');
                modal.style.display = "none";
            }
        }

        function closeAnnouncementDeleteModal() {
            document.getElementById('announcement-delete-modal').classList.add('hidden');
        }
    </script>
</body>

</html>