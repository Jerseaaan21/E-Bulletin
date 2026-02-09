<?php
include "../../db.php";
session_start();

// Get department acronym from session
 $dept_acronym = $_SESSION['dept_acronym'] ?? 'CEIT';
 $dept_id = $_SESSION['dept_id'] ?? 1;

// Get institutional development documents by status
 $active = [];
 $archived = [];

// Helper function to get category name from value
function getCategoryName($category) {
    switch($category) {
        case 'gender':
            return 'Gender & Development';
        case 'student':
            return 'Student Development';
        case 'strategic':
            return 'CvSU Strategic Plan';
        default:
            return 'Uncategorized';
    }
}

// Helper function to get category color
function getCategoryColor($category) {
    switch($category) {
        case 'gender':
            return 'bg-purple-100 text-purple-800';
        case 'student':
            return 'bg-blue-100 text-blue-800';
        case 'strategic':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Active documents
 $query = "SELECT mp.*, u.name as user_name 
         FROM main_post mp 
         LEFT JOIN users u ON mp.user_id = u.id 
         WHERE mp.module = (SELECT id FROM modules WHERE name = 'Institutional_Development' LIMIT 1) 
         AND mp.status = 'active' 
         ORDER BY mp.created_at DESC";
 $stmt = $conn->prepare($query);
 $stmt->execute();
 $result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    // Extract category from content field if it exists
    $category = 'default';
    if (!empty($row['content']) && json_decode($row['content'], true)) {
        $contentData = json_decode($row['content'], true);
        $category = $contentData['category'] ?? 'default';
    }
    
    $active[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Institutional_Development/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown',
        'category' => $category
    ];
}

// Archived documents
 $query = "SELECT mp.*, u.name as user_name 
         FROM main_post mp 
         LEFT JOIN users u ON mp.user_id = u.id 
         WHERE mp.module = (SELECT id FROM modules WHERE name = 'Institutional_Development' LIMIT 1) 
         AND mp.status = 'archived' 
         ORDER BY mp.created_at DESC";
 $stmt = $conn->prepare($query);
 $stmt->execute();
 $result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    // Extract category from content field if it exists
    $category = 'default';
    if (!empty($row['content']) && json_decode($row['content'], true)) {
        $contentData = json_decode($row['content'], true);
        $category = $contentData['category'] ?? 'default';
    }
    
    $archived[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Institutional_Development/archive/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown',
        'category' => $category
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Development Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Institutional Development-specific styles */
        .institutional-file-preview {
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

        .institutional-file-modal {
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

        .institutional-modal-content {
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

        .institutional-modal-header {
            padding: 15px 20px;
            background-color: #ea580c;
            /* Changed from blue to orange */
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .institutional-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .institutional-modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .institutional-modal-close:hover {
            transform: scale(1.2);
        }

        .institutional-modal-body {
            padding: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            height: calc(100% - 140px);
        }

        .institutional-pdf-container {
            width: 100%;
            height: 100%;
            min-height: 82vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .institutional-pdf-page {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            max-width: 100%;
            max-height: 100%;
        }

        .institutional-modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .institutional-modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .institutional-page-navigation {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .institutional-page-nav-btn {
            background-color: #ea580c;
            /* Changed from blue to orange */
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

        .institutional-page-nav-btn:hover {
            background-color: #c2410c;
            /* Darker orange for hover */
            transform: scale(1.1);
        }

        .institutional-page-nav-btn:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
            transform: scale(1);
        }

        .institutional-page-indicator {
            font-weight: 600;
            color: #4b5563;
            min-width: 80px;
            text-align: center;
        }

        .institutional-loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #ea580c;
            /* Changed from blue to orange */
            width: 40px;
            height: 40px;
            animation: institutional-spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes institutional-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .institutional-file-icon {
            font-size: 4rem;
        }

        .institutional-file-icon.pdf {
            color: #dc2626;
        }

        .institutional-file-icon.doc,
        .institutional-file-icon.docx,
        .institutional-file-icon.wps {
            color: #2563eb;
        }

        .institutional-file-icon.xls,
        .institutional-file-icon.xlsx {
            color: #16a34a;
        }

        .institutional-file-icon.ppt,
        .institutional-file-icon.pptx {
            color: #ea580c;
            /* Changed to match orange theme */
        }

        .institutional-file-icon.jpg,
        .institutional-file-icon.jpeg,
        .institutional-file-icon.png,
        .institutional-file-icon.gif {
            color: #ea580c;
            /* Changed to match orange theme */
        }

        .institutional-file-icon.default {
            color: #6b7280;
        }

        .institutional-image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #000;
        }

        .institutional-image-viewer {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .institutional-office-viewer {
            border: none;
            border-radius: 0;
            overflow: hidden;
            width: 100%;
            height: 100%;
            background-color: #fff;
        }

        /* Status sections */
        .institutional-status-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
        }

        .institutional-status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }

        .institutional-status-section.active .institutional-status-title {
            color: #ea580c;
            border-color: #ea580c;
        }

        .institutional-status-section.archived .institutional-status-title {
            color: #ebb703;
            border-color: #ebb703;
        }

        /* Notification styles */
        .institutional-notification {
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
            animation: institutional-slideIn 0.3s ease-out;
        }

        .institutional-notification.success {
            background-color: #22C55E;
        }

        .institutional-notification.error {
            background-color: #ef4444;
        }

        .institutional-notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        @keyframes institutional-slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Document action modal */
        .institutional-document-action-modal {
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

        .institutional-document-action-modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }

        .institutional-document-action-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .institutional-document-action-modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #dc2626;
        }

        .institutional-document-action-modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .institutional-document-action-modal-close:hover {
            color: #dc2626;
        }

        .institutional-document-action-modal-body {
            margin-bottom: 20px;
        }

        .institutional-document-action-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .institutional-modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .institutional-modal-header {
                padding: 10px 15px;
            }

            .institutional-modal-title {
                font-size: 1.2rem;
            }

            .institutional-modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .institutional-page-navigation {
                width: 100%;
                justify-content: center;
            }

            .institutional-modal-meta {
                text-align: center;
                width: 100%;
            }

            .institutional-status-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .institutional-status-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .institutional-file-preview canvas {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                display: block;
                margin: 0 auto;
            }

            .institutional-file-preview {
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
            <h1 class="text-2xl md:text-3xl font-bold text-orange-600 mb-4 md:mb-0"> <!-- Changed from blue to orange -->
                <i class="fas fa-building mr-3 w-5"></i> Institutional Development Management
            </h1>
            <button id="institutional-upload-document-btn"
                class="border-2 border-orange-500 bg-white hover:bg-orange-500 text-orange-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110"> <!-- Changed from blue to orange -->
                <i class="fas fa-upload mr-2"></i> Upload Document
            </button>
        </div>

        <!-- Active Documents -->
        <div class="institutional-status-section active">
            <h2 class="institutional-status-title">Active Documents</h2>
            <?php if (count($active) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($active as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-orange-500 transition duration-200 transform hover:scale-105 document-card">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="institutional-file-preview-active-<?= $index ?>" class="institutional-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="institutional-loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image institutional-file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file institutional-file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="file-title font-semibold text-gray-800 text-lg truncate flex-grow">
                                        <?= htmlspecialchars($pdf['description']) ?>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[8px] font-medium <?= getCategoryColor($pdf['category']) ?>">
                                        <?= getCategoryName($pdf['category']) ?>
                                    </span>
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
                                <button id="institutional-view-full-active-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-download-btn" data-file-path="<?= $pdf['file_path'] ?>" data-file-name="<?= basename($pdf['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-edit-btn" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-file-path="<?= $pdf['file_path'] ?>" data-category="<?= $pdf['category'] ?>" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-document-archive-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="active" title="Archive">
                                    <i class="fas fa-archive fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox fa-3x mb-4"></i>
                    <p class="text-lg">No active documents yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Documents -->
        <div class="institutional-status-section archived">
            <h2 class="institutional-status-title">Archived Documents</h2>
            <?php if (count($archived) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archived as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="institutional-file-preview-archived-<?= $index ?>" class="institutional-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="institutional-loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image institutional-file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file institutional-file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body flex-grow">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="file-title font-semibold text-gray-800 text-lg truncate flex-grow">
                                        <?= htmlspecialchars($pdf['description']) ?>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[8px] font-medium <?= getCategoryColor($pdf['category']) ?>">
                                        <?= getCategoryName($pdf['category']) ?>
                                    </span>
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
                                <button id="institutional-view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-download-btn" data-file-path="<?= $pdf['file_path'] ?>" data-file-name="<?= basename($pdf['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-document-restore-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Restore">
                                    <i class="fas fa-undo fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-document-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="archived" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-archive fa-3x mb-4"></i>
                    <p class="text-lg">No archived documents</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File View Modals -->
    <?php foreach ($active as $index => $pdf): ?>
        <div id="institutional-file-modal-active-<?= $index ?>" class="institutional-file-modal">
            <div class="institutional-modal-content">
                <div class="institutional-modal-header">
                    <h3 class="institutional-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="institutional-modal-close" onclick="closeInstitutionalFileModal('active', <?= $index ?>)">&times;</span>
                </div>
                <div class="institutional-modal-body">
                    <div id="institutional-pdfContainer-active-<?= $index ?>" class="institutional-pdf-container">
                        <div class="institutional-loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading document...</p>
                    </div>
                </div>
                <div class="institutional-modal-footer">
                    <div class="institutional-modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="institutional-page-navigation">
                            <button id="institutional-prevPageBtn-active-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="institutional-pageIndicator-active-<?= $index ?>" class="institutional-page-indicator">Page 1 of 1</div>
                            <button id="institutional-nextPageBtn-active-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archived as $index => $pdf): ?>
        <div id="institutional-file-modal-archived-<?= $index ?>" class="institutional-file-modal">
            <div class="institutional-modal-content">
                <div class="institutional-modal-header">
                    <h3 class="institutional-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="institutional-modal-close" onclick="closeInstitutionalFileModal('archived', <?= $index ?>)">&times;</span>
                </div>
                <div class="institutional-modal-body">
                    <div id="institutional-pdfContainer-archived-<?= $index ?>" class="institutional-pdf-container">
                        <div class="institutional-loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading document...</p>
                    </div>
                </div>
                <div class="institutional-modal-footer">
                    <div class="institutional-modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="institutional-page-navigation">
                            <button id="institutional-prevPageBtn-archived-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="institutional-pageIndicator-archived-<?= $index ?>" class="institutional-page-indicator">Page 1 of 1</div>
                            <button id="institutional-nextPageBtn-archived-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Edit Modal -->
    <div id="institutional-edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Document</h3>
                <form id="institutional-edit-form" class="mt-2 py-3" enctype="multipart/form-data">
                    <input type="hidden" id="institutional-edit-id" name="id">
                    <input type="hidden" id="institutional-edit-current-file" name="current_file">
                    <div class="mb-4">
                        <label for="institutional-edit-description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <input type="text" id="institutional-edit-description" name="description"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label for="institutional-edit-category" class="block text-gray-700 text-sm font-bold mb-2">Category:</label>
                        <select id="institutional-edit-category" name="category" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="gender">Gender & Development</option>
                            <option value="student">Student Development</option>
                            <option value="strategic">CvSU Strategic Plan</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="institutional-edit-current-file-name" class="block text-gray-700 text-sm font-bold mb-2">Current File:</label>
                        <p id="institutional-edit-current-file-name" class="text-gray-600 text-sm truncate"></p>
                    </div>
                    <div>
                        <label for="institutional-edit-file" class="block text-sm font-medium text-gray-700">Replace File (optional)</label>
                        <input
                            type="file"
                            id="institutional-edit-file"
                            name="file"
                            accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="institutional-cancel-edit"
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

    <!-- Active Document Archive/Delete Modal -->
    <div id="active-institutional-document-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Archive or Delete Document</h3>
            </div>
            <div class="mb-4">
                <p>What actions do you want?</p>
                <p class="font-semibold mt-2" id="active-institutional-document-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="cancel-active-institutional-document-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200" id="archive-active-institutional-document-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="delete-active-institutional-document-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Archived Document Archive Modal -->
    <div id="archived-institutional-document-archive-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Archive Document</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to archive this document?</p>
                <p class="font-semibold mt-2" id="archived-institutional-document-archive-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="cancel-archived-institutional-document-archive-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200" id="confirm-archived-institutional-document-archive-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
            </div>
        </div>
    </div>

    <!-- Archived Document Restore Modal -->
    <div id="archived-institutional-document-restore-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-green-600">Restore Document</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to restore this document?</p>
                <p class="font-semibold mt-2" id="archived-institutional-document-restore-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="cancel-archived-institutional-document-restore-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="confirm-archived-institutional-document-restore-btn">
                    <i class="fas fa-undo mr-2"></i> Restore
                </button>
            </div>
        </div>
    </div>

    <!-- Archived Document Delete Modal -->
    <div id="archived-institutional-document-delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">Delete Document</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to delete this document?</p>
                <p class="font-semibold mt-2" id="archived-institutional-document-delete-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="cancel-archived-institutional-document-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="confirm-archived-institutional-document-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="institutional-upload-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Upload Document</h2>
            <form id="institutional-upload-form" action="AddInstitutionalDevelopment.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select id="category" name="category" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a category</option>
                        <option value="gender">Gender & Development</option>
                        <option value="student">Student Development</option>
                        <option value="strategic">CvSU Strategic Plan</option>
                    </select>
                </div>
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
                        id="institutional-cancel-upload-btn"
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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script>
        // Set PDF.js worker source
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        // Global variables for PDF handling
        window.institutionalPdfDocs = {};
        window.institutionalCurrentPageNum = {};
        window.institutionalTotalPages = {};
        window.institutionalIsRendering = {};

        // Global variable to track active requests
        const institutionalActiveRequests = {
            archive: false,
            restore: false,
            delete: false,
            edit: false
        };

        // Global variable to track document being acted upon
        let institutionalDocumentToAction = null;

        // Function to initialize the module - can be called from dashboard
        function initializeInstitutionalDevelopmentModule() {
            console.log('Initializing Institutional Development module...');

            // Prevent multiple initializations
            if (window.institutionalDevelopmentModuleInitialized) {
                console.log('Institutional Development module already initialized, reinitializing...');
                // Force reinitialize PDF previews
                initializeInstitutionalPDFPreviews();
                return;
            }

            window.institutionalDevelopmentModuleInitialized = true;

            // Initialize modal event listeners
            initializeInstitutionalModalEventListeners();

            // Re-initialize all functionality
            if (typeof pdfjsLib !== 'undefined') {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';
                initializeInstitutionalPDFPreviews();
            }

            initializeInstitutionalViewButtons();
            initializeInstitutionalPageNavigation();
            initializeInstitutionalOtherFunctionality();

            console.log('Institutional Development module initialized');
        }

        // For direct access (not through dashboard)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(() => {
                // Only initialize if not already initialized by dashboard
                if (!window.institutionalDevelopmentModuleInitialized) {
                    initializeInstitutionalDevelopmentModule();
                    window.institutionalDevelopmentModuleInitialized = true;
                }
            }, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => {
                    // Only initialize if not already initialized by dashboard
                    if (!window.institutionalDevelopmentModuleInitialized) {
                        initializeInstitutionalDevelopmentModule();
                        window.institutionalDevelopmentModuleInitialized = true;
                    }
                }, 100);
            });
        }

        // Initialize modal event listeners
        function initializeInstitutionalModalEventListeners() {
            // --- Upload Modal Logic (Matching Announcements.php pattern) ---
            const institutionalUploadModal = document.getElementById('institutional-upload-modal');
            const institutionalUploadForm = document.getElementById('institutional-upload-form');

            // Upload button - show modal
            document.getElementById('institutional-upload-document-btn').addEventListener('click', function() {
                console.log('Institutional Upload button clicked');
                institutionalUploadModal.classList.remove('hidden');
            });

            // Cancel upload button - hide modal
            document.getElementById('institutional-cancel-upload-btn').addEventListener('click', function() {
                console.log('Cancel institutional upload button clicked');
                institutionalUploadModal.classList.add('hidden');
                institutionalUploadForm.reset();
            });

            // Close upload modal when clicking outside
            if (institutionalUploadModal) {
                institutionalUploadModal.addEventListener('click', function(e) {
                    if (e.target === institutionalUploadModal) {
                        console.log('Clicked outside institutional upload modal - closing');
                        institutionalUploadModal.classList.add('hidden');
                        institutionalUploadForm.reset();
                    }
                });
            }

            // Upload form submission
            if (institutionalUploadForm) {
                institutionalUploadForm.addEventListener('submit', function(e) {
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

                    fetch('CEIT_Modules/Institutional_Development/AddInstitutionalDevelopment.php', {
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
                                showInstitutionalNotification(data.message || 'Document uploaded successfully!', 'success');

                                // Close modal and reload after a short delay
                                setTimeout(() => {
                                    institutionalUploadModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showInstitutionalNotification(data.message || 'Error uploading document', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset form state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            // Show error notification
                            showInstitutionalNotification('An error occurred while uploading the document: ' + error.message, 'error');
                        });
                });
            }

            // --- Edit Modal Logic (Matching Announcements.php pattern) ---
            const institutionalEditModal = document.getElementById('institutional-edit-modal');
            const institutionalEditForm = document.getElementById('institutional-edit-form');

            // Edit buttons
            document.querySelectorAll('.institutional-edit-btn').forEach(button => {
                // Remove any existing event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');
                    const filePath = this.getAttribute('data-file-path');
                    const category = this.getAttribute('data-category');

                    // Set the edit form values
                    document.getElementById('institutional-edit-id').value = id;
                    document.getElementById('institutional-edit-description').value = description;
                    document.getElementById('institutional-edit-category').value = category;
                    document.getElementById('institutional-edit-current-file').value = filePath;
                    document.getElementById('institutional-edit-current-file-name').textContent = filePath.split('/').pop();

                    // Show the edit modal
                    institutionalEditModal.classList.remove('hidden');
                });
            });

            // Cancel edit button
            document.getElementById('institutional-cancel-edit').addEventListener('click', function() {
                institutionalEditModal.classList.add('hidden');
            });

            // Close edit modal when clicking outside
            if (institutionalEditModal) {
                institutionalEditModal.addEventListener('click', function(e) {
                    if (e.target === institutionalEditModal) {
                        institutionalEditModal.classList.add('hidden');
                    }
                });
            }

            // Edit form submission
            if (institutionalEditForm) {
                institutionalEditForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Check if form is already submitting
                    if (this.dataset.submitting === 'true') {
                        return;
                    }

                    // Mark form as submitting
                    this.dataset.submitting = 'true';

                    const id = document.getElementById('institutional-edit-id').value;
                    const description = document.getElementById('institutional-edit-description').value;
                    const category = document.getElementById('institutional-edit-category').value;
                    const fileInput = document.getElementById('institutional-edit-file');
                    const currentFile = document.getElementById('institutional-edit-current-file').value;

                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('description', description);
                    formData.append('category', category);
                    formData.append('current_file', currentFile);

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
                        const newFilename = `document_${day}_${month}_${year}_${hours}_${minutes}_${seconds}_${random}.${fileExtension}`;

                        const originalFile = fileInput.files[0];
                        const renamedFile = new File([originalFile], newFilename, {
                            type: originalFile.type
                        });

                        formData.append('file', renamedFile);
                    }

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
                    submitBtn.disabled = true;

                    fetch('CEIT_Modules/Institutional_Development/update_document.php', {
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
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            if (data.success) {
                                // Show success notification
                                showInstitutionalNotification(data.message || 'Document updated successfully!', 'success');

                                // Hide modal and reload after a short delay
                                setTimeout(() => {
                                    institutionalEditModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showInstitutionalNotification(data.message || 'Error updating document', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset button state
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            // Show error notification
                            showInstitutionalNotification('An error occurred while updating the document: ' + error.message, 'error');
                        });
                });
            }

            // --- Confirmation Modals Logic (Generic, but excluding upload/edit) ---
            
            // Active document archive buttons
            document.querySelectorAll('.institutional-document-archive-btn').forEach(button => {
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

                    institutionalDocumentToAction = id;
                    document.getElementById('active-institutional-document-title').textContent = description;

                    // Show active document modal
                    document.getElementById('active-institutional-document-modal').style.display = 'flex';
                });
            });

            // Archived document restore buttons
            document.querySelectorAll('.institutional-document-restore-btn').forEach(button => {
                // Remove any existing event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    institutionalDocumentToAction = id;
                    document.getElementById('archived-institutional-document-restore-title').textContent = description;

                    // Show restore modal
                    document.getElementById('archived-institutional-document-restore-modal').style.display = 'flex';
                });
            });

            // Archived document delete buttons
            document.querySelectorAll('.institutional-document-delete-btn').forEach(button => {
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

                    institutionalDocumentToAction = id;
                    document.getElementById('archived-institutional-document-delete-title').textContent = description;

                    // Show delete modal
                    document.getElementById('archived-institutional-document-delete-modal').style.display = 'flex';
                });
            });

            // Active document modal buttons
            const cancelActiveDocumentBtn = document.getElementById('cancel-active-institutional-document-btn');
            if (cancelActiveDocumentBtn) {
                cancelActiveDocumentBtn.addEventListener('click', function() {
                    document.getElementById('active-institutional-document-modal').style.display = 'none';
                });
            }

            const archiveActiveDocumentBtn = document.getElementById('archive-active-institutional-document-btn');
            if (archiveActiveDocumentBtn) {
                archiveActiveDocumentBtn.addEventListener('click', function() {
                    if (institutionalActiveRequests.archive) return;
                    
                    institutionalActiveRequests.archive = true;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                    fetch('CEIT_Modules/Institutional_Development/archive_document.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(institutionalDocumentToAction)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showInstitutionalNotification(data.message || 'Document archived successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('active-institutional-document-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showInstitutionalNotification(data.message || 'Error archiving document', 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                        }
                    })
                    .catch(error => {
                        console.error('Archive error:', error);
                        showInstitutionalNotification('An error occurred while archiving the document: ' + error.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                    })
                    .finally(() => {
                        institutionalActiveRequests.archive = false;
                    });
                });
            }

            const deleteActiveDocumentBtn = document.getElementById('delete-active-institutional-document-btn');
            if (deleteActiveDocumentBtn) {
                deleteActiveDocumentBtn.addEventListener('click', function() {
                    if (institutionalActiveRequests.delete) return;
                    
                    institutionalActiveRequests.delete = true;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                    fetch('CEIT_Modules/Institutional_Development/delete_document.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(institutionalDocumentToAction)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showInstitutionalNotification(data.message || 'Document deleted successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('active-institutional-document-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showInstitutionalNotification(data.message || 'Error deleting document', 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        showInstitutionalNotification('An error occurred while deleting the document: ' + error.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                    })
                    .finally(() => {
                        institutionalActiveRequests.delete = false;
                    });
                });
            }

            // Archived document restore modal buttons
            const cancelArchivedRestoreBtn = document.getElementById('cancel-archived-institutional-document-restore-btn');
            if (cancelArchivedRestoreBtn) {
                cancelArchivedRestoreBtn.addEventListener('click', function() {
                    document.getElementById('archived-institutional-document-restore-modal').style.display = 'none';
                });
            }

            const confirmArchivedRestoreBtn = document.getElementById('confirm-archived-institutional-document-restore-btn');
            if (confirmArchivedRestoreBtn) {
                confirmArchivedRestoreBtn.addEventListener('click', function() {
                    if (institutionalActiveRequests.restore) return;
                    
                    institutionalActiveRequests.restore = true;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';

                    fetch('CEIT_Modules/Institutional_Development/restore_document.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(institutionalDocumentToAction)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showInstitutionalNotification(data.message || 'Document restored successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('archived-institutional-document-restore-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showInstitutionalNotification(data.message || 'Error restoring document', 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                        }
                    })
                    .catch(error => {
                        console.error('Restore error:', error);
                        showInstitutionalNotification('An error occurred while restoring the document: ' + error.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                    })
                    .finally(() => {
                        institutionalActiveRequests.restore = false;
                    });
                });
            }

            // Archived document delete modal buttons
            const cancelArchivedDeleteBtn = document.getElementById('cancel-archived-institutional-document-delete-btn');
            if (cancelArchivedDeleteBtn) {
                cancelArchivedDeleteBtn.addEventListener('click', function() {
                    document.getElementById('archived-institutional-document-delete-modal').style.display = 'none';
                });
            }

            const confirmArchivedDeleteBtn = document.getElementById('confirm-archived-institutional-document-delete-btn');
            if (confirmArchivedDeleteBtn) {
                confirmArchivedDeleteBtn.addEventListener('click', function() {
                    if (institutionalActiveRequests.delete) return;
                    
                    institutionalActiveRequests.delete = true;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                    fetch('CEIT_Modules/Institutional_Development/delete_document.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + encodeURIComponent(institutionalDocumentToAction)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showInstitutionalNotification(data.message || 'Document deleted successfully!', 'success');
                            setTimeout(() => {
                                document.getElementById('archived-institutional-document-delete-modal').style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            showInstitutionalNotification(data.message || 'Error deleting document', 'error');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        showInstitutionalNotification('An error occurred while deleting the document: ' + error.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                    })
                    .finally(() => {
                        institutionalActiveRequests.delete = false;
                    });
                });
            }

            // Generic Close Modals when clicking outside
            // We explicitly EXCLUDE 'institutional-upload-modal' and 'institutional-edit-modal' here.
            // These specific modals use the 'hidden' class (Tailwind) for visibility logic.
            // The other modals (confirmation modals) use style.display = 'flex'.
            // Setting style.display = 'none' on a 'hidden' class modal breaks the class toggle logic.
            document.querySelectorAll('[id$="-modal"]').forEach(modal => {
                if (modal.id === 'institutional-upload-modal' || modal.id === 'institutional-edit-modal') return;

                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });
        }

        // Function to initialize view buttons
        function initializeInstitutionalViewButtons() {
            console.log('Setting up institutional view buttons...');

            // Add click event listeners to all view buttons
            document.querySelectorAll('[id^="institutional-view-full-"]').forEach(button => {
                // Remove any existing event listeners first
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', handleInstitutionalViewButtonClick);
            });

            console.log('Institutional view buttons initialized');
        }

        // Handle view button click
        function handleInstitutionalViewButtonClick(event) {
            const button = this;
            const idParts = button.id.split('-');
            const status = idParts[3];
            const index = idParts[4];

            const modalId = `institutional-file-modal-${status}-${index}`;
            const containerId = `institutional-pdfContainer-${status}-${index}`;
            const fileType = button.dataset.fileType;
            const filePath = button.dataset.filePath;

            const modal = document.getElementById(modalId);
            const container = document.getElementById(containerId);

            if (!modal || !container) return;

            modal.classList.add('modal-active');
            modal.style.display = "block";

            requestAnimationFrame(() => {
                displayInstitutionalFileContent(fileType, filePath, status, index, container);
            });
        }

        // Display file content
        function displayInstitutionalFileContent(fileType, filePath, status, index, container) {
            const fileExtension = filePath.split('.').pop().toLowerCase();

            // Clear container and show loading
            container.innerHTML = `
                <div class="institutional-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading file...</p>
            `;

            // Create full URL for file - resolve relative to Testing directory
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
                loadInstitutionalPDFFile(fullUrl, status, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                container.innerHTML = `
                    <div class="institutional-image-container">
                        <img src="${fullUrl}" alt="Full view" class="institutional-image-viewer" 
                             onerror="this.onerror=null; this.style.display='none'; 
                             container.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-lg text-gray-700\\'>Failed to load image</p></div>'">
                    </div>
                `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                // Use Microsoft Office Online viewer
                const encodedUrl = encodeURIComponent(fullUrl);
                container.innerHTML = `
                    <div class="institutional-office-viewer">
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
                        <p class="text-gray-600 mb-4">This file type cannot be previewed in browser.</p>
                        <p class="text-gray-600">File: ${filePath.split('/').pop()}</p>
                    </div>
                `;
            }
        }

        // Load PDF file
        function loadInstitutionalPDFFile(filePath, status, index, container) {
            const key = `${status}-${index}`;

            container.innerHTML = `
                <div class="institutional-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading PDF document...</p>
            `;

            pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                window.institutionalPdfDocs[key] = pdfDoc;
                window.institutionalTotalPages[key] = pdfDoc.numPages;
                window.institutionalCurrentPageNum[key] = 1;
                window.institutionalIsRendering[key] = false; // Initialize the rendering flag

                const pageIndicator = document.getElementById(`institutional-pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdfDoc.numPages}`;
                }

                const prevBtn = document.getElementById(`institutional-prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`institutional-nextPageBtn-${status}-${index}`);

                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdfDoc.numPages <= 1;

                // Force render after modal is visible
                setTimeout(() => {
                    renderInstitutionalPDFPage(status, index, 1);
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
        function renderInstitutionalPDFPage(status, index, pageNum) {
            return new Promise((resolve, reject) => {
                const key = `${status}-${index}`;
                if (!window.institutionalPdfDocs[key]) {
                    reject(new Error('PDF document not found'));
                    return;
                }

                const container = document.getElementById(`institutional-pdfContainer-${status}-${index}`);

                container.innerHTML = `
                    <div class="institutional-loading-spinner"></div>
                    <p class="text-center text-gray-600">Rendering page ${pageNum}...</p>
                `;

                window.institutionalPdfDocs[key].getPage(pageNum).then(page => {
                    const modalBody = document.querySelector(`#institutional-file-modal-${status}-${index} .institutional-modal-body`);

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
        function goToInstitutionalPrevPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.institutionalPdfDocs || !window.institutionalPdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.institutionalIsRendering[key]) return;

            if (window.institutionalCurrentPageNum[key] > 1) {
                // Set the rendering flag
                window.institutionalIsRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`institutional-prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`institutional-nextPageBtn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.institutionalCurrentPageNum[key]--;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`institutional-pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.institutionalCurrentPageNum[key]} of ${window.institutionalTotalPages[key]}`;
                }

                renderInstitutionalPDFPage(status, index, window.institutionalCurrentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.institutionalCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.institutionalCurrentPageNum[key] === window.institutionalTotalPages[key];

                    // Clear the rendering flag
                    window.institutionalIsRendering[key] = false;
                });
            }
        }

        function goToInstitutionalNextPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.institutionalPdfDocs || !window.institutionalPdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.institutionalIsRendering[key]) return;

            if (window.institutionalCurrentPageNum[key] < window.institutionalTotalPages[key]) {
                // Set the rendering flag
                window.institutionalIsRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`institutional-prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`institutional-nextPageBtn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.institutionalCurrentPageNum[key]++;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`institutional-pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.institutionalCurrentPageNum[key]} of ${window.institutionalTotalPages[key]}`;
                }

                renderInstitutionalPDFPage(status, index, window.institutionalCurrentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.institutionalCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.institutionalCurrentPageNum[key] === window.institutionalTotalPages[key];

                    // Clear the rendering flag
                    window.institutionalIsRendering[key] = false;
                });
            }
        }

        // Initialize PDF previews with optimizations
        function initializeInstitutionalPDFPreviews() {
            console.log('Initializing institutional PDF previews...');

            // Clear any existing PDF resources first
            if (window.institutionalPdfDocs) {
                Object.keys(window.institutionalPdfDocs).forEach(key => {
                    if (window.institutionalPdfDocs[key]) {
                        try {
                            window.institutionalPdfDocs[key].destroy();
                        } catch (e) {
                            console.warn('Error destroying PDF document:', e);
                        }
                        delete window.institutionalPdfDocs[key];
                    }
                });
            }

            // Reset PDF-related variables
            window.institutionalCurrentPageNum = {};
            window.institutionalTotalPages = {};
            window.institutionalIsRendering = {};

            // Optimized PDF preview rendering function
            function renderInstitutionalPDFPreview(filePath, containerId) {
                console.log('Rendering institutional PDF preview for:', filePath, containerId);

                const container = document.getElementById(containerId);
                if (!container) {
                    console.log('Container not found:', containerId);
                    return;
                }

                // Show loading state
                container.innerHTML = '<div class="institutional-loading-spinner"></div>';

                // Create full URL for file
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

                // Set maximum dimensions for preview
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
                        console.log('Institutional PDF preview rendered successfully for:', containerId);
                    });
                }).catch(function(error) {
                    console.error('Institutional PDF preview error for', containerId, ':', error);
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
                            renderInstitutionalPDFPreview(filePath, containerId);
                            observer.unobserve(container);
                        }
                    }
                });
            }, {
                rootMargin: '100px' // Start loading 100px before element is visible
            });

            // Observe all PDF preview containers
            document.querySelectorAll('.institutional-file-preview').forEach(container => {
                observer.observe(container);
            });

            // Fallback for browsers that don't support Intersection Observer
            if (!('IntersectionObserver' in window)) {
                // Render all previews immediately
                renderAllInstitutionalPreviews();
            }

            function renderAllInstitutionalPreviews() {
                <?php
                $totalDelay = 0;
                foreach ($active as $index => $pdf): ?>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderInstitutionalPDFPreview("<?= $pdf['file_path'] ?>", "institutional-file-preview-active-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($active) * 200;
                endforeach;
                ?>

                <?php foreach ($archived as $index => $pdf): ?>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderInstitutionalPDFPreview("<?= $pdf['file_path'] ?>", "institutional-file-preview-archived-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php endforeach; ?>
            }

            // Set a timeout to show fallback if PDF previews fail
            setTimeout(() => {
                document.querySelectorAll('.institutional-file-preview').forEach(container => {
                    // If still showing loading spinner after 5 seconds, show file icon
                    if (container.querySelector('.institutional-loading-spinner')) {
                        const fileType = 'pdf'; // Default to PDF for fallback
                        container.innerHTML = `<i class="fas fa-file-pdf institutional-file-icon pdf text-4xl"></i>`;
                    }
                });
            }, 5000);
        }

        // Initialize all other functionality
        function initializeInstitutionalOtherFunctionality() {
            console.log('Initializing institutional other functionality...');

            // Initialize page navigation
            initializeInstitutionalPageNavigation();

            // Initialize download buttons
            initializeInstitutionalDownloadButtons();

            console.log('Institutional other functionality initialized');
        }

        // Initialize download buttons
        function initializeInstitutionalDownloadButtons() {
            // Remove any existing event listeners by cloning the buttons
            document.querySelectorAll('.institutional-download-btn').forEach(button => {
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
            });

            // Add new event listeners
            document.querySelectorAll('.institutional-download-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const filePath = this.getAttribute('data-file-path');
                    const fileName = this.getAttribute('data-file-name');

                    // Get the full URL for the file
                    const fullUrl = getInstitutionalFullUrl(filePath);

                    // Create a temporary anchor element to trigger the download
                    const a = document.createElement('a');
                    a.href = fullUrl;
                    a.download = fileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    // Show a notification
                    showInstitutionalNotification(`Downloading ${fileName}...`, 'success');
                });
            });
        }

        // Helper function to get the full URL for a file
        function getInstitutionalFullUrl(filePath) {
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
        function initializeInstitutionalPageNavigation() {
            // Previous page buttons
            document.querySelectorAll('[id^="institutional-prevPageBtn-"]').forEach(button => {
                const idParts = button.id.split('-');
                const status = idParts[2];
                const index = idParts[3];
                button.addEventListener('click', () => goToInstitutionalPrevPage(status, index));
            });

            // Next page buttons
            document.querySelectorAll('[id^="institutional-nextPageBtn-"]').forEach(button => {
                const idParts = button.id.split('-');
                const status = idParts[2];
                const index = idParts[3];
                button.addEventListener('click', () => goToInstitutionalNextPage(status, index));
            });
        }

        // Show notification function
        function showInstitutionalNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `institutional-notification ${type}`;
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
        function closeInstitutionalFileModal(status, index) {
            const modal = document.getElementById(`institutional-file-modal-${status}-${index}`);
            if (modal) {
                modal.classList.remove('modal-active');
                modal.style.display = "none";

                // Clean up PDF resources
                const key = `${status}-${index}`;
                if (window.institutionalPdfDocs[key]) {
                    // You can add cleanup code here if needed
                }
            }
        }
    </script>
</body>

</html>