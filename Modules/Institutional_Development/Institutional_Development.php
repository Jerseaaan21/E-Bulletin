<?php
include "../../db.php";
session_start();

// Get department acronym from session
 $dept_acronym = $_SESSION['dept_acronym'] ?? 'default';
 $dept_id = $_SESSION['dept_id'] ?? 0;

// Get institutional development documents by status
 $approved = [];
 $pending = [];
 $not_approved = [];
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

// Approved documents
 $query = "SELECT dp.*, u.name as user_name 
         FROM department_post dp 
         LEFT JOIN users u ON dp.user_id = u.id 
         WHERE dp.module = (SELECT id FROM modules WHERE name = 'Institutional_Development' LIMIT 1) 
         AND dp.dept_id = ? 
         AND dp.status = 'Approved' 
         ORDER BY dp.created_at DESC";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $dept_id);
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
    
    $approved[] = [
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

// Pending documents
 $query = "SELECT dp.*, u.name as user_name 
         FROM department_post dp 
         LEFT JOIN users u ON dp.user_id = u.id 
         WHERE dp.module = (SELECT id FROM modules WHERE name = 'Institutional_Development' LIMIT 1) 
         AND dp.dept_id = ? 
         AND dp.status = 'Pending' 
         ORDER BY dp.created_at DESC";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $dept_id);
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
    
    $pending[] = [
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

// Not Approved documents
 $query = "SELECT dp.*, u.name as user_name 
         FROM department_post dp 
         LEFT JOIN users u ON dp.user_id = u.id 
         WHERE dp.module = (SELECT id FROM modules WHERE name = 'Institutional_Development' LIMIT 1) 
         AND dp.dept_id = ? 
         AND dp.status = 'Not Approved' 
         ORDER BY dp.created_at DESC";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $dept_id);
 $stmt->execute();
 $result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    // Extract category from content field if it exists
    $category = 'default';
    $rejectionReason = '';
    if (!empty($row['content']) && json_decode($row['content'], true)) {
        $contentData = json_decode($row['content'], true);
        $category = $contentData['category'] ?? 'default';
        $rejectionReason = $contentData['rejection_reason'] ?? $row['content'];
    } else {
        $rejectionReason = $row['content'];
    }
    
    $not_approved[] = [
        'id' => $row['id'],
        'file_path' => "uploads/{$dept_acronym}/Institutional_Development/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'], // Keep original content for compatibility
        'rejection_reason' => $rejectionReason, // Parsed rejection reason text only
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown',
        'category' => $category
    ];
}

// Archived documents
 $query = "SELECT dp.*, u.name as user_name 
         FROM department_post dp 
         LEFT JOIN users u ON dp.user_id = u.id 
         WHERE dp.module = (SELECT id FROM modules WHERE name = 'Institutional_Development' LIMIT 1) 
         AND dp.dept_id = ? 
         AND dp.status = 'Archived' 
         ORDER BY dp.created_at DESC";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $dept_id);
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

        .institutional-status-section.pending {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
        }

        .institutional-status-section.approved {
            background-color: #d1fae5;
            border: 1px solid #6ee7b7;
        }

        .institutional-status-section.not-approved {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
        }

        .institutional-status-section.archived {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .institutional-status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }

        .institutional-status-section.pending .institutional-status-title {
            color: #d97706;
            border-color: #d97706;
        }

        .institutional-status-section.approved .institutional-status-title {
            color: #059669;
            border-color: #059669;
        }

        .institutional-status-section.not-approved .institutional-status-title {
            color: #dc2626;
            border-color: #dc2626;
        }

        .institutional-status-section.archived .institutional-status-title {
            color: #2563eb;
            border-color: #2563eb;
        }

        /* Notification styles */
        .institutional-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
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

        <!-- Pending Documents -->
        <div class="institutional-status-section pending">
            <h2 class="institutional-status-title">Pending Documents</h2>
            <?php if (count($pending) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($pending as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105 document-card">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="institutional-file-preview-pending-<?= $index ?>" class="institutional-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getCategoryColor($pdf['category']) ?>">
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
                                <button id="institutional-view-full-pending-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>" onclick="openInstitutionalFileModal('pending', <?= $index ?>, '<?= $pdf['file_path'] ?>', '<?= $pdf['file_type'] ?>')">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-edit-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-file-path="<?= $pdf['file_path'] ?>" data-category="<?= $pdf['category'] ?>" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="pending" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-clock fa-3x mb-4"></i>
                    <p class="text-lg">No pending documents</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Approved Documents -->
        <div class="institutional-status-section approved">
            <h2 class="institutional-status-title">Approved Documents</h2>
            <?php if (count($approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-green-500 transition duration-200 transform hover:scale-105 document-card">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="institutional-file-preview-approved-<?= $index ?>" class="institutional-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getCategoryColor($pdf['category']) ?>">
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
                                <button id="institutional-view-full-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>" onclick="openInstitutionalFileModal('approved', <?= $index ?>, '<?= $pdf['file_path'] ?>', '<?= $pdf['file_type'] ?>')">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-download-btn" data-file-path="<?= $pdf['file_path'] ?>" data-file-name="<?= basename($pdf['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-archive-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="approved" title="Archive">
                                    <i class="fas fa-archive fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-check-circle fa-3x mb-4"></i>
                    <p class="text-lg">No approved documents yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Not Approved Documents -->
        <div class="institutional-status-section not-approved">
            <h2 class="institutional-status-title">Rejected Documents</h2>
            <?php if (count($not_approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($not_approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-red-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="institutional-file-preview-not-approved-<?= $index ?>" class="institutional-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getCategoryColor($pdf['category']) ?>">
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
                                <?php if (!empty($pdf['rejection_reason'])): ?>
                                    <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        <strong>Reason:</strong> <?= htmlspecialchars($pdf['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-end mt-4 space-x-2 text-xs">
                                <!-- View Button -->
                                <button id="institutional-view-full-not-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>" onclick="openInstitutionalFileModal('not-approved', <?= $index ?>, '<?= $pdf['file_path'] ?>', '<?= $pdf['file_type'] ?>')">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <!-- Download Button -->
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-download-btn" data-file-path="<?= $pdf['file_path'] ?>" data-file-name="<?= basename($pdf['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                </button>
                                <!-- Edit Button -->
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-edit-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-file-path="<?= $pdf['file_path'] ?>" data-category="<?= $pdf['category'] ?>" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </button>
                                <!-- Delete Button -->
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="not-approved" title="Delete">
                                    <i class="fas fa-trash fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-times-circle fa-3x mb-4"></i>
                    <p class="text-lg">No rejected documents</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Documents -->
        <div class="institutional-status-section archived">
            <h2 class="institutional-status-title">Archived Documents</h2>
            <?php if (count($archived) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archived as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105">
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getCategoryColor($pdf['category']) ?>">
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
                                <button id="institutional-view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>" onclick="openInstitutionalFileModal('archived', <?= $index ?>, '<?= $pdf['file_path'] ?>', '<?= $pdf['file_type'] ?>')">
                                    <i class="fas fa-eye fa-sm"></i>
                                </button>
                                <!-- Restore Button -->
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-restore-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Restore">
                                    <i class="fas fa-undo fa-sm"></i>
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 institutional-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="archived" title="Delete">
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
    <?php foreach ($approved as $index => $pdf): ?>
        <div id="institutional-file-modal-approved-<?= $index ?>" class="institutional-file-modal">
            <div class="institutional-modal-content">
                <div class="institutional-modal-header">
                    <h3 class="institutional-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="institutional-modal-close" onclick="closeInstitutionalFileModal('approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="institutional-modal-body">
                    <div id="institutional-pdfContainer-approved-<?= $index ?>" class="institutional-pdf-container">
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
                            <button id="institutional-prevPageBtn-approved-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="institutional-pageIndicator-approved-<?= $index ?>" class="institutional-page-indicator">Page 1 of 1</div>
                            <button id="institutional-nextPageBtn-approved-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($pending as $index => $pdf): ?>
        <div id="institutional-file-modal-pending-<?= $index ?>" class="institutional-file-modal">
            <div class="institutional-modal-content">
                <div class="institutional-modal-header">
                    <h3 class="institutional-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="institutional-modal-close" onclick="closeInstitutionalFileModal('pending', <?= $index ?>)">&times;</span>
                </div>
                <div class="institutional-modal-body">
                    <div id="institutional-pdfContainer-pending-<?= $index ?>" class="institutional-pdf-container">
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
                            <button id="institutional-prevPageBtn-pending-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="institutional-pageIndicator-pending-<?= $index ?>" class="institutional-page-indicator">Page 1 of 1</div>
                            <button id="institutional-nextPageBtn-pending-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($not_approved as $index => $pdf): ?>
        <div id="institutional-file-modal-not-approved-<?= $index ?>" class="institutional-file-modal">
            <div class="institutional-modal-content">
                <div class="institutional-modal-header">
                    <h3 class="institutional-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="institutional-modal-close" onclick="closeInstitutionalFileModal('not-approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="institutional-modal-body">
                    <div id="institutional-pdfContainer-not-approved-<?= $index ?>" class="institutional-pdf-container">
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
                            <button id="institutional-prevPageBtn-not-approved-<?= $index ?>" class="institutional-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="institutional-pageIndicator-not-approved-<?= $index ?>" class="institutional-page-indicator">Page 1 of 1</div>
                            <button id="institutional-nextPageBtn-not-approved-<?= $index ?>" class="institutional-page-nav-btn" disabled>
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

    <!-- Archive/Delete Confirmation Modal for Documents -->
    <div id="institutional-delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-yellow-600" id="institutional-modal-title">Archive Document</h3>
            </div>
            <div class="mb-4">
                <p>Are you sure you want to <span id="institutional-action-text">archive</span> this document?</p>
                <p class="font-semibold mt-2" id="institutional-delete-title"></p>
            </div>
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200" id="institutional-cancel-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200" id="institutional-archive-btn">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200" id="institutional-restore-btn" style="display: none;">
                    <i class="fas fa-undo mr-2"></i> Restore
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200" id="institutional-confirm-delete-btn">
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
                    <select id="category" name="category" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
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
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="pdfFile" class="block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        id="pdfFile"
                        name="pdfFile"
                        accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        required
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">

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
                        class="px-4 py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white transition duration-200 transform hover:scale-110">
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
        window.institutionalPdfDocs = {};
        window.institutionalCurrentPageNum = {};
        window.institutionalTotalPages = {};
        window.institutionalIsRendering = {};

        // Global variable to track active requests
        const institutionalActiveRequests = {
            archive: false,
            delete: false,
            restore: false
        };

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

        // Initialize view buttons for all document types
        function initializeInstitutionalViewButtons() {
            console.log('Initializing institutional view buttons...');

            // Handle view buttons for all document types
            const documentTypes = ['pending', 'approved', 'not-approved', 'archived'];
            
            documentTypes.forEach(type => {
                document.querySelectorAll(`[id^="institutional-view-full-${type}-"]`).forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const buttonId = this.id;
                        const index = buttonId.split('-').pop();
                        const filePath = this.getAttribute('data-file-path');
                        const fileType = this.getAttribute('data-file-type');
                        
                        console.log(`Opening modal for ${type} document ${index}:`, filePath);
                        
                        // Open the corresponding modal
                        openInstitutionalFileModal(type, index, filePath, fileType);
                    });
                });
            });
        }

        // Function to open file modal
        function openInstitutionalFileModal(status, index, filePath, fileType) {
            const modal = document.getElementById(`institutional-file-modal-${status}-${index}`);
            if (!modal) {
                console.error(`Modal not found: institutional-file-modal-${status}-${index}`);
                return;
            }

            modal.style.display = 'flex';
            modal.classList.add('modal-active');

            // Load the document content
            loadInstitutionalDocumentInModal(status, index, filePath, fileType);
        }

        // Function to load document content in modal
        function loadInstitutionalDocumentInModal(status, index, filePath, fileType) {
            const container = document.getElementById(`institutional-pdfContainer-${status}-${index}`);
            if (!container) {
                console.error(`Container not found: institutional-pdfContainer-${status}-${index}`);
                return;
            }

            // Show loading state
            container.innerHTML = '<div class="institutional-loading-spinner"></div><p class="text-center text-gray-600">Loading document...</p>';

            const fullUrl = getInstitutionalFullUrl(filePath);
            console.log('Loading document from:', fullUrl);

            if (fileType === 'pdf') {
                loadInstitutionalPDFInModal(status, index, fullUrl);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType.toLowerCase())) {
                loadInstitutionalImageInModal(status, index, fullUrl);
            } else {
                // For other file types, show a preview or download option
                container.innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-file institutional-file-icon ${fileType} text-6xl mb-4"></i>
                        <p class="text-gray-600 mb-4">Preview not available for this file type</p>
                        <a href="${fullUrl}" target="_blank" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition duration-200">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Open File
                        </a>
                    </div>
                `;
            }
        }

        // Function to load PDF in modal
        function loadInstitutionalPDFInModal(status, index, fullUrl) {
            const key = `${status}-${index}`;
            const container = document.getElementById(`institutional-pdfContainer-${status}-${index}`);

            pdfjsLib.getDocument({
                url: fullUrl,
                cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/cmaps/',
                cMapPacked: true
            }).promise.then(function(pdf) {
                window.institutionalPdfDocs[key] = pdf;
                window.institutionalTotalPages[key] = pdf.numPages;
                window.institutionalCurrentPageNum[key] = 1;

                // Update page indicator
                const pageIndicator = document.getElementById(`institutional-pageIndicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdf.numPages}`;
                }

                // Enable/disable navigation buttons
                const prevBtn = document.getElementById(`institutional-prevPageBtn-${status}-${index}`);
                const nextBtn = document.getElementById(`institutional-nextPageBtn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdf.numPages === 1;

                // Render first page
                return renderInstitutionalPDFPage(status, index, 1);
            }).catch(function(error) {
                console.error('Error loading PDF:', error);
                container.innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-red-600 mb-4">Error loading PDF document</p>
                        <a href="${fullUrl}" target="_blank" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition duration-200">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Open in New Tab
                        </a>
                    </div>
                `;
            });
        }

        // Function to load image in modal
        function loadInstitutionalImageInModal(status, index, fullUrl) {
            const container = document.getElementById(`institutional-pdfContainer-${status}-${index}`);
            
            container.innerHTML = `
                <div class="institutional-image-container">
                    <img src="${fullUrl}" alt="Document Image" class="institutional-image-viewer" 
                         onerror="this.parentElement.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-red-600\\'>Error loading image</p></div>'">
                </div>
            `;
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
            // Upload button - show modal
            document.getElementById('institutional-upload-document-btn').addEventListener('click', function() {
                console.log('Upload button clicked');
                document.getElementById('institutional-upload-modal').classList.remove('hidden');
            });

            // Cancel upload button - hide modal
            document.getElementById('institutional-cancel-upload-btn').addEventListener('click', function() {
                console.log('Cancel upload button clicked');
                document.getElementById('institutional-upload-modal').classList.add('hidden');
                document.getElementById('institutional-upload-form').reset();
            });

            // Close upload modal when clicking outside
            const uploadModal = document.getElementById('institutional-upload-modal');
            if (uploadModal) {
                uploadModal.addEventListener('click', function(e) {
                    if (e.target === uploadModal) {
                        console.log('Clicked outside upload modal - closing');
                        uploadModal.classList.add('hidden');
                        document.getElementById('institutional-upload-form').reset();
                    }
                });
            }

            // Upload form submission
            const uploadForm = document.getElementById('institutional-upload-form');
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

                    fetch('./Modules/Institutional_Development/AddInstitutionalDevelopment.php', {
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
                                    document.getElementById('institutional-upload-modal').classList.add('hidden');
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

            // Edit modal functionality
            const editModal = document.getElementById('institutional-edit-modal');
            const editForm = document.getElementById('institutional-edit-form');

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
                    editModal.classList.remove('hidden');
                });
            });

            // Cancel edit button
            document.getElementById('institutional-cancel-edit').addEventListener('click', function() {
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

                    fetch('./Modules/Institutional_Development/update_document.php', {
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
                                    editModal.classList.add('hidden');
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

            // Archive/Delete buttons
            document.querySelectorAll('.institutional-archive-btn, .institutional-delete-btn').forEach(button => {
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

                    window.institutionalToAction = id;
                    document.getElementById('institutional-delete-title').textContent = description;

                    // Show/hide archive button based on status
                    const archiveBtn = document.getElementById('institutional-archive-btn');
                    const restoreBtn = document.getElementById('institutional-restore-btn');
                    const modalTitle = document.getElementById('institutional-modal-title');
                    const actionText = document.getElementById('institutional-action-text');

                    if (status === 'approved') {
                        archiveBtn.style.display = 'inline-block';
                        restoreBtn.style.display = 'none';
                        modalTitle.textContent = 'Archive Document';
                        actionText.textContent = 'archive';
                    } else {
                        archiveBtn.style.display = 'none';
                        restoreBtn.style.display = 'none';
                        modalTitle.textContent = 'Delete Document';
                        actionText.textContent = 'delete';
                    }

                    document.getElementById('institutional-delete-modal').style.display = 'flex';
                });
            });

            // Restore buttons
            document.querySelectorAll('.institutional-restore-btn').forEach(button => {
                // Remove any existing event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    window.institutionalToAction = id;
                    document.getElementById('institutional-delete-title').textContent = description;

                    // Show restore button and hide others
                    const archiveBtn = document.getElementById('institutional-archive-btn');
                    const restoreBtn = document.getElementById('institutional-restore-btn');
                    const modalTitle = document.getElementById('institutional-modal-title');
                    const actionText = document.getElementById('institutional-action-text');

                    archiveBtn.style.display = 'none';
                    restoreBtn.style.display = 'inline-block';
                    modalTitle.textContent = 'Restore Document';
                    actionText.textContent = 'restore';

                    document.getElementById('institutional-delete-modal').style.display = 'flex';
                });
            });

            // Cancel button
            const cancelBtn = document.getElementById('institutional-cancel-delete-btn');
            if (cancelBtn) {
                const newCancelBtn = cancelBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

                newCancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    document.getElementById('institutional-delete-modal').style.display = 'none';
                });
            }

            // Close modal when clicking outside
            const modal = document.getElementById('institutional-delete-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        document.getElementById('institutional-delete-modal').style.display = 'none';
                    }
                });
            }

            // Archive button
            const archiveBtn = document.getElementById('institutional-archive-btn');
            if (archiveBtn) {
                // Remove any existing event listeners first
                const newArchiveBtn = archiveBtn.cloneNode(true);
                archiveBtn.parentNode.replaceChild(newArchiveBtn, archiveBtn);

                // Add new event listener
                newArchiveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active archive request
                    if (institutionalActiveRequests.archive) {
                        console.log('Archive request already in progress');
                        return;
                    }

                    if (window.institutionalToAction) {
                        // Set active request flag
                        institutionalActiveRequests.archive = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                        // Send the archive request to the server
                        fetch('./Modules/Institutional_Development/archive_document.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(window.institutionalToAction)
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
                                    showInstitutionalNotification(data.message || 'Document archived successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('institutional-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showInstitutionalNotification(data.message || 'Error archiving document', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                                }
                            })
                            .catch(error => {
                                console.error('Archive error:', error);
                                showInstitutionalNotification('An error occurred while archiving the document: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                            })
                            .finally(() => {
                                // Reset active request flag
                                institutionalActiveRequests.archive = false;
                            });
                    }
                });
            }

            // Restore button
            const restoreBtn = document.getElementById('institutional-restore-btn');
            if (restoreBtn) {
                // Remove any existing event listeners first
                const newRestoreBtn = restoreBtn.cloneNode(true);
                restoreBtn.parentNode.replaceChild(newRestoreBtn, restoreBtn);

                // Add new event listener
                newRestoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active restore request
                    if (institutionalActiveRequests.restore) {
                        console.log('Restore request already in progress');
                        return;
                    }

                    if (window.institutionalToAction) {
                        // Set active request flag
                        institutionalActiveRequests.restore = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';

                        // Send the restore request to the server
                        fetch('./Modules/Institutional_Development/restore_document.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(window.institutionalToAction)
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
                                    showInstitutionalNotification(data.message || 'Document restored successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('institutional-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showInstitutionalNotification(data.message || 'Error restoring document', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                                }
                            })
                            .catch(error => {
                                console.error('Restore error:', error);
                                showInstitutionalNotification('An error occurred while restoring the document: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-undo mr-2"></i> Restore';
                            })
                            .finally(() => {
                                // Reset active request flag
                                institutionalActiveRequests.restore = false;
                            });
                    }
                });
            }

            // Delete confirmation
            const deleteBtn = document.getElementById('institutional-confirm-delete-btn');
            if (deleteBtn) {
                // Remove any existing event listeners first
                const newDeleteBtn = deleteBtn.cloneNode(true);
                deleteBtn.parentNode.replaceChild(newDeleteBtn, deleteBtn);

                // Add new event listener
                newDeleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active delete request
                    if (institutionalActiveRequests.delete) {
                        console.log('Delete request already in progress');
                        return;
                    }

                    if (window.institutionalToAction) {
                        // Log the ID for debugging
                        console.log("Attempting to delete document with ID:", window.institutionalToAction);

                        // Set active request flag
                        institutionalActiveRequests.delete = true;

                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                        fetch('./Modules/Institutional_Development/delete_document.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(window.institutionalToAction)
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
                                    showInstitutionalNotification(data.message || 'Document deleted successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        document.getElementById('institutional-delete-modal').style.display = 'none';
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showInstitutionalNotification(data.message || 'Error deleting document', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                                }
                            })
                            .catch(error => {
                                console.error('Delete error:', error);
                                showInstitutionalNotification('An error occurred while deleting the document: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                            })
                            .finally(() => {
                                // Reset active request flag
                                institutionalActiveRequests.delete = false;
                            });
                    }
                });
            }
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
                        <p class="text-gray-600 mb-4">This file type cannot be previewed in the browser.</p>
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
                foreach ($approved as $index => $pdf): ?>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderInstitutionalPDFPreview("<?= $pdf['file_path'] ?>", "institutional-file-preview-approved-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($approved) * 200;
                endforeach;
                ?>

                <?php foreach ($pending as $index => $pdf): ?>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderInstitutionalPDFPreview("<?= $pdf['file_path'] ?>", "institutional-file-preview-pending-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($pending) * 200;
                endforeach;
                ?>

                <?php foreach ($not_approved as $index => $pdf): ?>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        setTimeout(() => {
                            renderInstitutionalPDFPreview("<?= $pdf['file_path'] ?>", "institutional-file-preview-not-approved-<?= $index ?>");
                        }, <?= $totalDelay + ($index * 200) ?>);
                    <?php endif; ?>
                <?php
                    $totalDelay += count($not_approved) * 200;
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