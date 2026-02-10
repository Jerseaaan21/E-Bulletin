<?php
// Modules/Memo/MemoBulletin.php
include "../../db.php";
session_start();

// Get department acronym from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';
$dept_id = $_SESSION['dept_id'] ?? 0;

// Get module ID for Memo
$moduleQuery = "SELECT id FROM modules WHERE name = 'Memo' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Get approved memos only for the bulletin
$query = "SELECT dp.*, u.name as user_name 
          FROM department_post dp 
          LEFT JOIN users u ON dp.user_id = u.id 
          WHERE dp.module = ? 
          AND dp.dept_id = ? 
          AND dp.status = 'Approved' 
          ORDER BY dp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $moduleId, $dept_id);
$stmt->execute();
$result = $stmt->get_result();
$pdfs = [];
while ($row = $result->fetch_assoc()) {
    $pdfs[] = [
        'file_path' => "Testing/uploads/{$dept_acronym}/Memo/" . $row['file_path'],
        'description' => $row['description'],
        'posted_on' => date("F j, Y", strtotime($row['created_at'])),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Department Memo Carousel Viewer</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .carousel-container {
            width: 100%;
            height: 100%;
            max-height: 630px;
            overflow: hidden;
        }

        .pdf-preview-area {
            width: 100%;
            height: 350px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            cursor: pointer;
        }

        canvas {
            max-width: 100%;
            max-height: 100%;
        }

        .view-hint {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: rgba(59, 130, 246, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
        }

        .pdf-preview-area:hover .view-hint {
            opacity: 1;
        }

        .loading-indicator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .spinner {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 3px solid #3b82f6;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-100 flex justify-center items-center min-h-screen">
    <div class="carousel-container bg-white shadow-lg rounded-lg p-4 flex flex-col justify-between h-full">
        <div id="pdf-viewer" class="pdf-preview-area rounded mb-4 bg-gray-50">
            <div class="loading-indicator">
                <div class="spinner"></div>
                <p class="text-gray-500 text-sm">Loading preview...</p>
            </div>
            <div class="view-hint">Click to view full memo</div>
        </div>
        <div class="text-center mb-2">
            <p id="pdf-description" class="text-base font-semibold text-gray-800 truncate px-2"></p>
        </div>
        <div class="text-center text-sm text-gray-500">
            <span id="pdf-posted-date"></span>
        </div>
        <div class="text-center text-xs text-gray-400 mt-1">
            <span id="pdf-user-name"></span>
        </div>
        <div class="flex justify-center space-x-2 mt-2">
            <button id="prev-btn" class="bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button id="next-btn" class="bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </div>
    <script>
        // Initialize PDF.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

        // Pass PDF data to parent window
        const pdfDataLocal = <?= json_encode($pdfs) ?>;
        window.parent.pdfData = pdfDataLocal;

        document.addEventListener('DOMContentLoaded', () => {
            let currentIndex = 0;
            const viewer = document.getElementById('pdf-viewer');
            const desc = document.getElementById('pdf-description');
            const postedDate = document.getElementById('pdf-posted-date');
            const userName = document.getElementById('pdf-user-name');

            function renderPDF(index) {
                // Use the parent's pdfData
                const pdf = window.parent.pdfData[index];

                // Show loading indicator
                viewer.innerHTML = `
                <div class="loading-indicator">
                    <div class="spinner"></div>
                    <p class="text-gray-500 text-sm">Loading preview...</p>
                </div>
                <div class="view-hint">Click to view full memo</div>
            `;

                desc.textContent = pdf.description;
                postedDate.textContent = "Posted on: " + pdf.posted_on;
                userName.textContent = "By: " + pdf.user_name;

                // Get file extension
                const fileExtension = pdf.file_path.split('.').pop().toLowerCase();

                if (fileExtension === 'pdf') {
                    pdfjsLib.getDocument(pdf.file_path).promise.then(pdfDoc => {
                        return pdfDoc.getPage(1);
                    }).then(page => {
                        // Calculate scale to fit the container
                        const containerWidth = viewer.clientWidth;
                        const containerHeight = viewer.clientHeight;
                        const viewport = page.getViewport({
                            scale: 1.0
                        });

                        // Calculate scale to fit container while maintaining aspect ratio
                        const scale = Math.min(
                            containerWidth / viewport.width,
                            containerHeight / viewport.height
                        ) * 0.9; // 90% of container size for padding

                        const scaledViewport = page.getViewport({
                            scale
                        });
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = scaledViewport.height;
                        canvas.width = scaledViewport.width;

                        // Clear viewer and add canvas
                        viewer.innerHTML = '';
                        viewer.appendChild(canvas);

                        // Add view hint back
                        const hint = document.createElement('div');
                        hint.className = 'view-hint';
                        hint.textContent = 'Click to view full memo';
                        viewer.appendChild(hint);

                        return page.render({
                            canvasContext: context,
                            viewport: scaledViewport
                        }).promise;
                    }).catch(error => {
                        console.error('Preview error:', error);
                        viewer.innerHTML = `
                        <div class="text-center p-4">
                            <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
                            <p class="text-red-500">Failed to load preview</p>
                        </div>
                        <div class="view-hint">Click to view full memo</div>
                    `;
                    });
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                    // Display image preview
                    viewer.innerHTML = `
                    <img src="${pdf.file_path}" alt="Preview" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    <div class="view-hint">Click to view full image</div>
                `;
                } else {
                    // For other file types, show icon
                    viewer.innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-file text-gray-400 text-6xl mb-2"></i>
                        <p class="text-gray-600">No preview available</p>
                    </div>
                    <div class="view-hint">Click to view file</div>
                `;
                }
            }

            // Add click event to open modal
            viewer.addEventListener('click', () => {
                const pdf = window.parent.pdfData[currentIndex];
                const fileExtension = pdf.file_path.split('.').pop().toLowerCase();
                
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'openPDF',
                        filePath: pdf.file_path,
                        title: pdf.description,
                        postedDate: pdf.posted_on,
                        fileName: pdf.file_path.split('/').pop(),
                        fileType: fileExtension
                    }, '*');
                } else {
                    console.error('Parent window not accessible');
                }
            });

            document.getElementById('prev-btn').addEventListener('click', () => {
                currentIndex = (currentIndex - 1 + window.parent.pdfData.length) % window.parent.pdfData.length;
                renderPDF(currentIndex);
            });

            document.getElementById('next-btn').addEventListener('click', () => {
                currentIndex = (currentIndex + 1) % window.parent.pdfData.length;
                renderPDF(currentIndex);
            });

            renderPDF(currentIndex); // Initial render
        });
    </script>
</body>

</html>