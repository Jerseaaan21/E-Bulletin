<?php
include "../../db.php";
session_start();

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';
$dept_id = $_SESSION['dept_id'] ?? 0;

// Get memos by status
$approved = [];
$pending = [];
$not_approved = [];
$archived = [];

// Approved memos
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Memo' LIMIT 1) 
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
        'file_path' => "uploads/{$dept_acronym}/Memo/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Pending memos
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Memo' LIMIT 1) 
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
        'file_path' => "uploads/{$dept_acronym}/Memo/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'],
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Not Approved memos
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Memo' LIMIT 1) 
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
        'file_path' => "uploads/{$dept_acronym}/Memo/" . $row['file_path'],
        'description' => $row['description'],
        'content' => $row['content'], // This contains the rejection reason
        'posted_on' => $row['created_at'],
        'file_type' => strtolower($fileExtension),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}

// Archived memos
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = (SELECT id FROM modules WHERE name = 'Memo' LIMIT 1) 
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
        'file_path' => "uploads/{$dept_acronym}/Memo/" . $row['file_path'],
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
    <title>Memo Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .memo-file-preview {
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

        .memo-file-modal {
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

        .memo-modal-content {
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

        .memo-modal-header {
            padding: 15px 20px;
            background-color: #3b82f6;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .memo-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .memo-modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .memo-modal-close:hover {
            transform: scale(1.2);
        }

        .memo-modal-body {
            padding: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            height: calc(100% - 140px);
        }

        .memo-pdf-container {
            width: 100%;
            height: 100%;
            min-height: 82vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .memo-pdf-page {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            max-width: 100%;
            max-height: 100%;
        }

        .memo-modal-footer {
            padding: 15px 20px;
            background-color: #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .memo-modal-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .memo-page-navigation {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .memo-page-nav-btn {
            background-color: #3b82f6;
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

        .memo-page-nav-btn:hover {
            background-color: #2563eb;
            transform: scale(1.1);
        }

        .memo-page-nav-btn:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
            transform: scale(1);
        }

        .memo-page-indicator {
            font-weight: 600;
            color: #4b5563;
            min-width: 80px;
            text-align: center;
        }

        .memo-loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #3b82f6;
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

        .memo-delete-modal {
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

        .memo-delete-modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }

        .memo-delete-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .memo-delete-modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #dc2626;
        }

        .memo-delete-modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .memo-delete-modal-close:hover {
            color: #dc2626;
        }

        .memo-delete-modal-body {
            margin-bottom: 20px;
        }

        .memo-delete-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .memo-file-icon {
            font-size: 4rem;
        }

        .memo-file-icon.pdf {
            color: #dc2626;
        }

        .memo-file-icon.doc,
        .memo-file-icon.docx,
        .memo-file-icon.wps {
            color: #2563eb;
        }

        .memo-file-icon.xls,
        .memo-file-icon.xlsx {
            color: #16a34a;
        }

        .memo-file-icon.ppt,
        .memo-file-icon.pptx {
            color: #ea580c;
        }

        .memo-file-icon.jpg,
        .memo-file-icon.jpeg,
        .memo-file-icon.png,
        .memo-file-icon.gif {
            color: #8b5cf6;
        }

        .memo-file-icon.default {
            color: #6b7280;
        }

        .memo-image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #000;
        }

        .memo-image-viewer {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .memo-office-viewer {
            border: none;
            border-radius: 0;
            overflow: hidden;
            width: 100%;
            height: 100%;
            background-color: #fff;
        }

        /* Status sections */
        .memo-status-section {
            margin-bottom: 40px;
            padding: 20px;
            border-radius: 8px;
        }

        .memo-approved {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .memo-pending {
            background-color: #fffbeb;
            border: 1px solid #fef08a;
        }

        .memo-not-approved {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
        }

        .memo-archived {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .memo-status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }

        .memo-approved .memo-status-title {
            color: #16a34a;
            border-color: #16a34a;
        }

        .memo-pending .memo-status-title {
            color: #d97706;
            border-color: #d97706;
        }

        .memo-not-approved .memo-status-title {
            color: #dc2626;
            border-color: #dc2626;
        }

        .memo-archived .memo-status-title {
            color: #2563eb;
            border-color: #2563eb;
        }

        /* Ensure modals are on top when opened */
        .memo-modal-active {
            z-index: 1001 !important;
        }

        /* Notification styles */
        .memo-notification {
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

        .memo-notification.success {
            background-color: #10b981;
        }

        .memo-notification.error {
            background-color: #ef4444;
        }

        .memo-notification i {
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
            .memo-modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .memo-modal-header {
                padding: 10px 15px;
            }

            .memo-modal-title {
                font-size: 1.2rem;
            }

            .memo-modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .memo-page-navigation {
                width: 100%;
                justify-content: center;
            }

            .memo-modal-meta {
                text-align: center;
                width: 100%;
            }

            .memo-status-section {
                padding: 15px;
                margin-bottom: 30px;
            }

            .memo-status-title {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .memo-file-preview canvas {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                display: block;
                margin: 0 auto;
            }

            .memo-file-preview {
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
            <h1 class="text-2xl md:text-3xl font-bold text-blue-600 mb-4 md:mb-0">
                <i class="fas fa-file-alt mr-3 w-5"></i> Memo Management
            </h1>
            <button id="memo-upload-btn"
                class="border-2 border-blue-500 bg-white hover:bg-blue-500 text-blue-500 hover:text-white px-4 py-2 rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-upload mr-2"></i> Upload Memo
            </button>
        </div>

        <!-- Approved Memos -->
        <div class="memo-status-section memo-approved">
            <h2 class="memo-status-title">Approved Memos</h2>
            <?php if (count($approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-green-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="memo-file-preview-approved-<?= $index ?>" class="memo-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="memo-loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image memo-file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file memo-file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
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
                                <button id="memo-view-full-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110 memo-download-btn" data-file-path="<?= $pdf['file_path'] ?>" data-file-name="<?= basename($pdf['file_path']) ?>" title="Download">
                                    <i class="fas fa-download fa-sm"></i>
                                    Download
                                </button>
                                <button class="p-2 border border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-500 hover:text-white transition duration-200 transform hover:scale-110 memo-archive-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="approved" title="Archive">
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
                    <p class="text-lg">No approved memos yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Memos -->
        <div class="memo-status-section memo-pending">
            <h2 class="memo-status-title">Pending Memos</h2>
            <?php if (count($pending) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($pending as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-yellow-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="memo-file-preview-pending-<?= $index ?>" class="memo-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="memo-loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image memo-file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file memo-file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
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
                                <button id="memo-view-full-pending-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110 memo-edit-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                    Edit
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 memo-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="pending" title="Delete">
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
                    <p class="text-lg">No pending memos</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Not Approved Memos -->
        <div class="memo-status-section memo-not-approved">
            <h2 class="memo-status-title">Not Approved Memos</h2>
            <?php if (count($not_approved) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($not_approved as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-red-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="memo-file-preview-not-approved-<?= $index ?>" class="memo-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="memo-loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image memo-file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file memo-file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
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
                                <button id="memo-view-full-not-approved-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 memo-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="not-approved" title="Delete">
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
                    <p class="text-lg">No not approved memos</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Memos -->
        <div class="memo-status-section memo-archived">
            <h2 class="memo-status-title">Archived Memos</h2>
            <?php if (count($archived) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($archived as $index => $pdf): ?>
                        <div class="bg-white shadow-md rounded-lg p-4 w-full h-full flex flex-col justify-between border border-blue-500 transition duration-200 transform hover:scale-105">
                            <div class="mb-3 border border-gray-300 rounded">
                                <div id="memo-file-preview-archived-<?= $index ?>" class="memo-file-preview" data-file-path="<?= $pdf['file_path'] ?>">
                                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                                        <!-- Loading spinner will be replaced by PDF preview -->
                                        <div class="memo-loading-spinner"></div>
                                    <?php elseif (in_array($pdf['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= $pdf['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain"
                                            onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-file-image memo-file-icon jpg text-4xl\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-file memo-file-icon <?= $pdf['file_type'] ?> text-4xl"></i>
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
                                <button id="memo-view-full-archived-<?= $index ?>" class="p-2 border rounded-lg border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="View Full Document" data-file-type="<?= $pdf['file_type'] ?>" data-file-path="<?= $pdf['file_path'] ?>">
                                    <i class="fas fa-eye fa-sm"></i>
                                    View
                                </button>
                                <button class="p-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110 memo-delete-btn" data-index="<?= $index ?>" data-id="<?= $pdf['id'] ?>" data-description="<?= htmlspecialchars($pdf['description']) ?>" data-status="archived" title="Delete">
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
                    <p class="text-lg">No archived memos</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File View Modals -->
    <?php foreach ($approved as $index => $pdf): ?>
        <div id="memo-file-modal-approved-<?= $index ?>" class="memo-file-modal">
            <div class="memo-modal-content">
                <div class="memo-modal-header">
                    <h3 class="memo-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="memo-modal-close" onclick="closeMemoFileModal('approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="memo-modal-body">
                    <div id="memo-pdf-container-approved-<?= $index ?>" class="memo-pdf-container">
                        <div class="memo-loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading memo...</p>
                    </div>
                </div>
                <div class="memo-modal-footer">
                    <div class="memo-modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="memo-page-navigation">
                            <button id="memo-prev-page-btn-approved-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="memo-page-indicator-approved-<?= $index ?>" class="memo-page-indicator">Page 1 of 1</div>
                            <button id="memo-next-page-btn-approved-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($pending as $index => $pdf): ?>
        <div id="memo-file-modal-pending-<?= $index ?>" class="memo-file-modal">
            <div class="memo-modal-content">
                <div class="memo-modal-header">
                    <h3 class="memo-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="memo-modal-close" onclick="closeMemoFileModal('pending', <?= $index ?>)">&times;</span>
                </div>
                <div class="memo-modal-body">
                    <div id="memo-pdf-container-pending-<?= $index ?>" class="memo-pdf-container">
                        <div class="memo-loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading memo...</p>
                    </div>
                </div>
                <div class="memo-modal-footer">
                    <div class="memo-modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="memo-page-navigation">
                            <button id="memo-prev-page-btn-pending-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="memo-page-indicator-pending-<?= $index ?>" class="memo-page-indicator">Page 1 of 1</div>
                            <button id="memo-next-page-btn-pending-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($not_approved as $index => $pdf): ?>
        <div id="memo-file-modal-not-approved-<?= $index ?>" class="memo-file-modal">
            <div class="memo-modal-content">
                <div class="memo-modal-header">
                    <h3 class="memo-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="memo-modal-close" onclick="closeMemoFileModal('not-approved', <?= $index ?>)">&times;</span>
                </div>
                <div class="memo-modal-body">
                    <div id="memo-pdf-container-not-approved-<?= $index ?>" class="memo-pdf-container">
                        <div class="memo-loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading memo...</p>
                    </div>
                </div>
                <div class="memo-modal-footer">
                    <div class="memo-modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="memo-page-navigation">
                            <button id="memo-prev-page-btn-not-approved-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="memo-page-indicator-not-approved-<?= $index ?>" class="memo-page-indicator">Page 1 of 1</div>
                            <button id="memo-next-page-btn-not-approved-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($archived as $index => $pdf): ?>
        <div id="memo-file-modal-archived-<?= $index ?>" class="memo-file-modal">
            <div class="memo-modal-content">
                <div class="memo-modal-header">
                    <h3 class="memo-modal-title"><?= htmlspecialchars($pdf['description']) ?></h3>
                    <span class="memo-modal-close" onclick="closeMemoFileModal('archived', <?= $index ?>)">&times;</span>
                </div>
                <div class="memo-modal-body">
                    <div id="memo-pdf-container-archived-<?= $index ?>" class="memo-pdf-container">
                        <div class="memo-loading-spinner"></div>
                        <p class="text-center text-gray-600">Loading memo...</p>
                    </div>
                </div>
                <div class="memo-modal-footer">
                    <div class="memo-modal-meta">
                        Posted by: <?= htmlspecialchars($pdf['user_name']) ?> | Posted on: <?= date('F j, Y', strtotime($pdf['posted_on'])) ?> | File: <?= basename($pdf['file_path']) ?>
                    </div>
                    <?php if ($pdf['file_type'] === 'pdf'): ?>
                        <div class="memo-page-navigation">
                            <button id="memo-prev-page-btn-archived-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div id="memo-page-indicator-archived-<?= $index ?>" class="memo-page-indicator">Page 1 of 1</div>
                            <button id="memo-next-page-btn-archived-<?= $index ?>" class="memo-page-nav-btn" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Edit Modal -->
    <div id="memo-edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Memo</h3>
                <form id="memo-edit-form" class="mt-2 py-3" enctype="multipart/form-data">
                    <input type="hidden" id="memo-edit-id" name="id">
                    <div class="mb-4">
                        <label for="memo-edit-description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <input type="text" id="memo-edit-description" name="description"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="memo-edit-file" class="block text-sm font-medium text-gray-700">Replace File (optional)</label>
                        <input
                            type="file"
                            id="memo-edit-file"
                            name="file"
                            accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="memo-cancel-edit"
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

    <!-- Archive/Delete Confirmation Modal for Memos -->
    <div id="memo-delete-modal" class="memo-delete-modal">
        <div class="memo-delete-modal-content">
            <div class="memo-delete-modal-header">
                <h3 class="memo-delete-modal-title">Choose Action</h3>
                <span class="memo-delete-modal-close" onclick="closeMemoDeleteModal()">&times;</span>
            </div>
            <div class="memo-delete-modal-body">
                <p>What would you like to do with this memo?</p>
                <p class="font-semibold mt-2" id="memo-delete-title"></p>
            </div>
            <div class="memo-delete-modal-footer">
                <button class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="memo-cancel-delete-btn">
                    Cancel
                </button>
                <button class="px-4 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="memo-archive-btn" style="display: none;">
                    <i class="fas fa-archive mr-2"></i> Archive
                </button>
                <button class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110" id="memo-confirm-delete-btn">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="memo-upload-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Upload Memo</h2>
            <form id="memo-upload-form" action="AddMemo.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="memo-description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input
                        type="text"
                        id="memo-description"
                        name="description"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="memo-file" class="block text-sm font-medium text-gray-700">File</label>
                    <input
                        type="file"
                        id="memo-file"
                        name="pdfFile"
                        accept=".pdf,.doc,.docx,.wps,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        required
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">

                </div>
                <div class="flex justify-end space-x-3 text-sm">
                    <button
                        type="button"
                        id="memo-cancel-upload-btn"
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
        window.memoPdfDocs = {};
        window.memoCurrentPageNum = {};
        window.memoTotalPages = {};
        window.memoIsRendering = {};

        // Global variable to track active requests
        const memoActiveRequests = {
            archive: false,
            delete: false
        };

        // Function to initialize the module - can be called from dashboard
        function initializeMemosModule() {
            console.log('Initializing Memos module...');

            // Initialize modal event listeners
            initializeMemoModalEventListeners();

            // Re-initialize all functionality
            if (typeof pdfjsLib !== 'undefined') {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';
                initializeMemoPDFPreviews();
            }

            initializeMemoViewButtons();
            initializeMemoPageNavigation();
            initializeMemoOtherFunctionality();

            console.log('Memos module initialized');
        }

        // For direct access (not through dashboard)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(() => {
                // Only initialize if not already initialized by dashboard
                if (!window.memosModuleInitialized) {
                    initializeMemosModule();
                    window.memosModuleInitialized = true;
                }
            }, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => {
                    // Only initialize if not already initialized by dashboard
                    if (!window.memosModuleInitialized) {
                        initializeMemosModule();
                        window.memosModuleInitialized = true;
                    }
                }, 100);
            });
        }

        // Initialize modal event listeners
        function initializeMemoModalEventListeners() {
            // Archive/Delete buttons
            document.querySelectorAll('.memo-archive-btn, .memo-delete-btn').forEach(button => {
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

                    memoToActionId = id;
                    document.getElementById('memo-delete-title').textContent = description;

                    // Show/hide archive button based on status
                    const archiveBtn = document.getElementById('memo-archive-btn');
                    if (status === 'approved') {
                        archiveBtn.style.display = 'inline-block';
                    } else {
                        archiveBtn.style.display = 'none';
                    }

                    document.getElementById('memo-delete-modal').style.display = 'flex';
                });
            });

            // Cancel button
            const cancelBtn = document.getElementById('memo-cancel-delete-btn');
            if (cancelBtn) {
                const newCancelBtn = cancelBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

                newCancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMemoDeleteModal();
                });
            }

            // Close modal when clicking outside
            const modal = document.getElementById('memo-delete-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeMemoDeleteModal();
                    }
                });
            }
        }

        // Function to initialize view buttons
        function initializeMemoViewButtons() {
            console.log('Setting up memo view buttons...');

            // Add click event listeners to all view buttons
            document.querySelectorAll('[id^="memo-view-full-"]').forEach(button => {
                // Remove any existing event listeners first
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Add new event listener
                newButton.addEventListener('click', handleMemoViewButtonClick);
            });

            console.log('Memo view buttons initialized');
        }

        // Handle view button click
        function handleMemoViewButtonClick(event) {
            const button = this;
            const idParts = button.id.split('-');
            const status = idParts[3];
            const index = idParts[4];

            const modalId = `memo-file-modal-${status}-${index}`;
            const containerId = `memo-pdf-container-${status}-${index}`;
            const fileType = button.dataset.fileType;
            const filePath = button.dataset.filePath;

            const modal = document.getElementById(modalId);
            const container = document.getElementById(containerId);

            if (!modal || !container) return;

            modal.classList.add('memo-modal-active');
            modal.style.display = "block";

            requestAnimationFrame(() => {
                displayMemoFileContent(fileType, filePath, status, index, container);
            });
        }

        // Display file content
        function displayMemoFileContent(fileType, filePath, status, index, container) {
            const fileExtension = filePath.split('.').pop().toLowerCase();

            // Clear container and show loading
            container.innerHTML = `
                <div class="memo-loading-spinner"></div>
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
                loadMemoPDFFile(fullUrl, status, index, container);
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                container.innerHTML = `
                    <div class="memo-image-container">
                        <img src="${fullUrl}" alt="Full view" class="memo-image-viewer" 
                             onerror="this.onerror=null; this.style.display='none'; 
                             container.innerHTML='<div class=\\'text-center p-8\\'><i class=\\'fas fa-exclamation-triangle text-red-500 text-4xl mb-4\\'></i><p class=\\'text-lg text-gray-700\\'>Failed to load image</p></div>'">
                    </div>
                `;
            } else if (['doc', 'docx', 'wps', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                // Use Microsoft Office Online viewer
                const encodedUrl = encodeURIComponent(fullUrl);
                container.innerHTML = `
                    <div class="memo-office-viewer">
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
        function loadMemoPDFFile(filePath, status, index, container) {
            const key = `${status}-${index}`;

            container.innerHTML = `
                <div class="memo-loading-spinner"></div>
                <p class="text-center text-gray-600">Loading PDF document...</p>
            `;

            pdfjsLib.getDocument(filePath).promise.then(pdfDoc => {
                window.memoPdfDocs[key] = pdfDoc;
                window.memoTotalPages[key] = pdfDoc.numPages;
                window.memoCurrentPageNum[key] = 1;
                window.memoIsRendering[key] = false; // Initialize the rendering flag

                const pageIndicator = document.getElementById(`memo-page-indicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page 1 of ${pdfDoc.numPages}`;
                }

                const prevBtn = document.getElementById(`memo-prev-page-btn-${status}-${index}`);
                const nextBtn = document.getElementById(`memo-next-page-btn-${status}-${index}`);

                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = pdfDoc.numPages <= 1;

                // Force render after modal is visible
                setTimeout(() => {
                    renderMemoPDFPage(status, index, 1);
                }, 25); // Reduced delay from 100ms to 50ms

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
        function renderMemoPDFPage(status, index, pageNum) {
            return new Promise((resolve, reject) => {
                const key = `${status}-${index}`;
                if (!window.memoPdfDocs[key]) {
                    reject(new Error('PDF document not found'));
                    return;
                }

                const container = document.getElementById(`memo-pdf-container-${status}-${index}`);

                container.innerHTML = `
                    <div class="memo-loading-spinner"></div>
                    <p class="text-center text-gray-600">Rendering page ${pageNum}...</p>
                `;

                window.memoPdfDocs[key].getPage(pageNum).then(page => {
                    const modalBody = document.querySelector(`#memo-file-modal-${status}-${index} .memo-modal-body`);

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
        function goToMemoPrevPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.memoPdfDocs || !window.memoPdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.memoIsRendering[key]) return;

            if (window.memoCurrentPageNum[key] > 1) {
                // Set the rendering flag
                window.memoIsRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`memo-prev-page-btn-${status}-${index}`);
                const nextBtn = document.getElementById(`memo-next-page-btn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.memoCurrentPageNum[key]--;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`memo-page-indicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.memoCurrentPageNum[key]} of ${window.memoTotalPages[key]}`;
                }

                renderMemoPDFPage(status, index, window.memoCurrentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.memoCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.memoCurrentPageNum[key] === window.memoTotalPages[key];

                    // Clear the rendering flag
                    window.memoIsRendering[key] = false;
                });
            }
        }

        function goToMemoNextPage(status, index) {
            const key = `${status}-${index}`;
            if (!window.memoPdfDocs || !window.memoPdfDocs[key]) return;

            // Check if a page is currently being rendered
            if (window.memoIsRendering[key]) return;

            if (window.memoCurrentPageNum[key] < window.memoTotalPages[key]) {
                // Set the rendering flag
                window.memoIsRendering[key] = true;

                // Disable navigation buttons temporarily
                const prevBtn = document.getElementById(`memo-prev-page-btn-${status}-${index}`);
                const nextBtn = document.getElementById(`memo-next-page-btn-${status}-${index}`);
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;

                window.memoCurrentPageNum[key]++;

                // Update page indicator immediately
                const pageIndicator = document.getElementById(`memo-page-indicator-${status}-${index}`);
                if (pageIndicator) {
                    pageIndicator.textContent = `Page ${window.memoCurrentPageNum[key]} of ${window.memoTotalPages[key]}`;
                }

                renderMemoPDFPage(status, index, window.memoCurrentPageNum[key]).then(() => {
                    // Re-enable navigation buttons after rendering is complete
                    if (prevBtn) prevBtn.disabled = window.memoCurrentPageNum[key] === 1;
                    if (nextBtn) nextBtn.disabled = window.memoCurrentPageNum[key] === window.memoTotalPages[key];

                    // Clear the rendering flag
                    window.memoIsRendering[key] = false;
                });
            }
        }

        // Initialize PDF previews with optimizations
        function initializeMemoPDFPreviews() {
            console.log('Initializing memo PDF previews...');

            // Clear any existing PDF resources first
            if (window.memoPdfDocs) {
                Object.keys(window.memoPdfDocs).forEach(key => {
                    if (window.memoPdfDocs[key]) {
                        try {
                            window.memoPdfDocs[key].destroy();
                        } catch (e) {
                            console.warn('Error destroying PDF document:', e);
                        }
                        delete window.memoPdfDocs[key];
                    }
                });
            }

            // Reset PDF-related variables
            window.memoCurrentPageNum = {};
            window.memoTotalPages = {};
            window.memoIsRendering = {};

            // Optimized PDF preview rendering function
            function renderMemoPDFPreview(filePath, containerId) {
                console.log('Rendering memo PDF preview for:', filePath, containerId);

                const container = document.getElementById(containerId);
                if (!container) {
                    console.log('Container not found:', containerId);
                    return;
                }

                // Show loading state
                container.innerHTML = '<div class="memo-loading-spinner"></div>';

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

                console.log('Loading memo PDF from:', fullUrl);

                // Add cache-busting parameter
                const cacheBuster = '?t=' + new Date().getTime();
                const urlWithCache = fullUrl + cacheBuster;

                // Set maximum dimensions for the preview
                const MAX_WIDTH = 300;
                const MAX_HEIGHT = 200;

                pdfjsLib.getDocument({
                    url: urlWithCache,
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
                        console.log('Memo PDF preview rendered successfully for:', containerId);
                    });
                }).catch(function(error) {
                    console.error('Memo PDF preview error for', containerId, ':', error);
                    container.innerHTML =
                        '<div class="flex flex-col items-center justify-center h-full p-2">' +
                        '<i class="fas fa-file-pdf text-red-500 text-3xl mb-1"></i>' +
                        '<p class="text-red-500 text-xs text-center">Preview unavailable</p>' +
                        '</div>';
                });
            }

            // Set a timeout to show fallback if PDF previews fail - reduced from 5000ms to 3000ms
            setTimeout(() => {
                document.querySelectorAll('.memo-file-preview').forEach(container => {
                    // If still showing loading spinner after 3 seconds, show file icon
                    if (container.querySelector('.memo-loading-spinner')) {
                        const fileType = 'pdf'; // Default to PDF for fallback
                        container.innerHTML = `<i class="fas fa-file-pdf memo-file-icon pdf text-4xl"></i>`;
                    }
                });
            }, 1500);

            // Use Intersection Observer for lazy loading with increased rootMargin
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const container = entry.target;
                        const filePath = container.dataset.filePath;
                        const containerId = container.id;

                        if (filePath && containerId) {
                            renderMemoPDFPreview(filePath, containerId);
                            observer.unobserve(container);
                        }
                    }
                });
            }, {
                rootMargin: '200px' // Increased from 100px to 200px to start loading earlier
            });

            // Observe all PDF preview containers
            document.querySelectorAll('.memo-file-preview').forEach(container => {
                // Store file path in data attribute
                const filePath = container.dataset.filePath;
                if (filePath) {
                    observer.observe(container);
                }
            });

            // Fallback for browsers that don't support Intersection Observer
            if (!('IntersectionObserver' in window)) {
                // Render all previews immediately
                renderAllPreviews();
            }

            function renderAllPreviews() {
                // Get all PDF preview containers
                const pdfContainers = document.querySelectorAll('.memo-file-preview[data-file-path]');

                // Process each container with a smaller delay to prevent overwhelming the browser
                pdfContainers.forEach((container, index) => {
                    setTimeout(() => {
                        const filePath = container.dataset.filePath;
                        const containerId = container.id;
                        if (filePath && containerId) {
                            renderMemoPDFPreview(filePath, containerId);
                        }
                    }, index * 25); // Reduced from 200ms to 50ms for faster loading
                });
            }
        }

        // Initialize all other functionality
        function initializeMemoOtherFunctionality() {
            console.log('Initializing memo other functionality...');

            // Upload modal functionality
            const uploadModal = document.getElementById('memo-upload-modal');
            const uploadForm = document.getElementById('memo-upload-form');

            // Upload button - show modal
            document.getElementById('memo-upload-btn').addEventListener('click', function() {
                console.log('Memo upload button clicked');
                uploadModal.classList.remove('hidden');
            });

            // Cancel button - hide modal
            document.getElementById('memo-cancel-upload-btn').addEventListener('click', function() {
                console.log('Cancel memo upload button clicked');
                uploadModal.classList.add('hidden');
                if (uploadForm) {
                    uploadForm.reset();
                }
            });

            // Close modal when clicking outside
            if (uploadModal) {
                uploadModal.addEventListener('click', function(e) {
                    if (e.target === uploadModal) {
                        console.log('Clicked outside memo modal - closing');
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

                    fetch('Modules/Memo/AddMemo.php', {
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
                                showMemoNotification(data.message || 'Memo uploaded successfully!', 'success');

                                // Close modal and reload after a short delay
                                setTimeout(() => {
                                    uploadModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showMemoNotification(data.message || 'Error uploading memo', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset form state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                            this.dataset.submitting = 'false';

                            // Show error notification
                            showMemoNotification('An error occurred while uploading the memo: ' + error.message, 'error');
                        });
                });
            }

            // Edit modal functionality
            const editModal = document.getElementById('memo-edit-modal');
            const editForm = document.getElementById('memo-edit-form');

            // Edit buttons
            document.querySelectorAll('.memo-edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const description = this.getAttribute('data-description');

                    document.getElementById('memo-edit-id').value = id;
                    document.getElementById('memo-edit-description').value = description;
                    editModal.classList.remove('hidden');
                });
            });

            // Cancel edit button
            document.getElementById('memo-cancel-edit').addEventListener('click', function() {
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

                    const id = document.getElementById('memo-edit-id').value;
                    const description = document.getElementById('memo-edit-description').value;
                    const fileInput = document.getElementById('memo-edit-file');

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
                        const newFilename = `memo_${day}_${month}_${year}_${hours}_${minutes}_${seconds}_${random}.${fileExtension}`;

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

                    fetch('Modules/Memo/update_memo.php', {
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
                                showMemoNotification(data.message || 'Memo updated successfully!', 'success');

                                // Hide modal and reload after a short delay
                                setTimeout(() => {
                                    editModal.classList.add('hidden');
                                    location.reload();
                                }, 1500);
                            } else {
                                // Show error notification
                                showMemoNotification(data.message || 'Error updating memo', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Reset button state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;

                            // Show error notification
                            showMemoNotification('An error occurred while updating the memo: ' + error.message, 'error');
                        });
                });
            }

            // Archive button
            const archiveBtn = document.getElementById('memo-archive-btn');
            if (archiveBtn) {
                // Remove any existing event listeners first
                const newArchiveBtn = archiveBtn.cloneNode(true);
                archiveBtn.parentNode.replaceChild(newArchiveBtn, archiveBtn);

                // Add new event listener
                newArchiveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active archive request
                    if (memoActiveRequests.archive) {
                        console.log('Memo archive request already in progress');
                        return;
                    }

                    if (memoToActionId) {
                        // Set active request flag
                        memoActiveRequests.archive = true;

                        // Disable the button to prevent multiple clicks
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

                        // Send the archive request to the server
                        fetch('Modules/Memo/archive_memo.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(memoToActionId)
                            })
                            .then(response => {
                                console.log('Memo archive response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Memo archive response data:', data);
                                if (data.success) {
                                    // Show success notification
                                    showMemoNotification(data.message || 'Memo archived successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        closeMemoDeleteModal();
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showMemoNotification(data.message || 'Error archiving memo', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                                }
                            })
                            .catch(error => {
                                console.error('Memo archive error:', error);
                                showMemoNotification('An error occurred while archiving the memo: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive';
                            })
                            .finally(() => {
                                // Reset active request flag
                                memoActiveRequests.archive = false;
                            });
                    }
                });
            }

            // Delete confirmation
            const deleteBtn = document.getElementById('memo-confirm-delete-btn');
            if (deleteBtn) {
                // Remove any existing event listeners first
                const newDeleteBtn = deleteBtn.cloneNode(true);
                deleteBtn.parentNode.replaceChild(newDeleteBtn, deleteBtn);

                // Add new event listener
                newDeleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if there's already an active delete request
                    if (memoActiveRequests.delete) {
                        console.log('Memo delete request already in progress');
                        return;
                    }

                    if (memoToActionId) {
                        // Log the ID for debugging
                        console.log("Attempting to delete memo with ID:", memoToActionId);

                        // Set active request flag
                        memoActiveRequests.delete = true;

                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

                        fetch('Modules/Memo/delete_memo.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'id=' + encodeURIComponent(memoToActionId)
                            })
                            .then(response => {
                                console.log('Memo delete response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Memo delete response data:', data);
                                if (data.success) {
                                    // Show success notification
                                    showMemoNotification(data.message || 'Memo deleted successfully!', 'success');

                                    // Hide modal and reload after a short delay
                                    setTimeout(() => {
                                        closeMemoDeleteModal();
                                        location.reload();
                                    }, 1500);
                                } else {
                                    // Show error notification
                                    showMemoNotification(data.message || 'Error deleting memo', 'error');

                                    // Re-enable the button
                                    this.disabled = false;
                                    this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                                }
                            })
                            .catch(error => {
                                console.error('Memo delete error:', error);
                                showMemoNotification('An error occurred while deleting the memo: ' + error.message, 'error');

                                // Re-enable the button
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete';
                            })
                            .finally(() => {
                                // Reset active request flag
                                memoActiveRequests.delete = false;
                            });
                    }
                });
            }

            // Initialize page navigation
            initializeMemoPageNavigation();

            // Initialize download buttons
            initializeMemoDownloadButtons();

            console.log('Memo other functionality initialized');
        }

        // Initialize download buttons
        function initializeMemoDownloadButtons() {
            // Remove any existing event listeners by cloning the buttons
            document.querySelectorAll('.memo-download-btn').forEach(button => {
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
            });

            // Add new event listeners
            document.querySelectorAll('.memo-download-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const filePath = this.getAttribute('data-file-path');
                    const fileName = this.getAttribute('data-file-name');

                    // Get the full URL for the file
                    const fullUrl = getMemoFullUrl(filePath);

                    // Create a temporary anchor element to trigger the download
                    const a = document.createElement('a');
                    a.href = fullUrl;
                    a.download = fileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    // Show a notification
                    showMemoNotification(`Downloading ${fileName}...`, 'success');
                });
            });
        }

        // Helper function to get the full URL for a file
        function getMemoFullUrl(filePath) {
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
        function initializeMemoPageNavigation() {
            // Previous page buttons
            document.querySelectorAll('[id^="memo-prev-page-btn-"]').forEach(button => {
                const idParts = button.id.split('-');
                // ID format: memo-prev-page-btn-{status}-{index}
                const status = idParts[4];
                const index = idParts[5];
                button.addEventListener('click', () => goToMemoPrevPage(status, index));
            });

            // Next page buttons
            document.querySelectorAll('[id^="memo-next-page-btn-"]').forEach(button => {
                const idParts = button.id.split('-');
                // ID format: memo-next-page-btn-{status}-{index}
                const status = idParts[4];
                const index = idParts[5];
                button.addEventListener('click', () => goToMemoNextPage(status, index));
            });
        }

        // Show notification function
        function showMemoNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `memo-notification ${type}`;
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
        function closeMemoFileModal(status, index) {
            const modal = document.getElementById(`memo-file-modal-${status}-${index}`);
            if (modal) {
                modal.classList.remove('memo-modal-active');
                modal.style.display = "none";

                // Clean up PDF resources
                const key = `${status}-${index}`;
                if (window.memoPdfDocs[key]) {
                    // You can add cleanup code here if needed
                }
            }
        }

        function closeMemoDeleteModal() {
            document.getElementById('memo-delete-modal').style.display = 'none';
        }
    </script>
</body>

</html>