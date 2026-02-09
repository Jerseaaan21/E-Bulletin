<?php
// CEIT_Modules/Institutional_Development/InstitutionalDevelopmentBulletin.php
include "../../db.php";
session_start();

// Get department ID from session or URL parameter
if (isset($_SESSION['dept_id'])) {
    $dept_id = $_SESSION['dept_id'];
} elseif (isset($_GET['dept_id'])) {
    $dept_id = (int)$_GET['dept_id'];
} else {
    $dept_id = 1; // Default to CEIT department
}

// Get user_id from session if available
$user_id = isset($_SESSION['user_info']['id']) ? $_SESSION['user_info']['id'] : null;

// Get department acronym
$dept_query = "SELECT acronym FROM departments WHERE dept_id = ?";
$dept_stmt = $conn->prepare($dept_query);
$dept_stmt->bind_param("i", $dept_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$dept_acronym = 'CEIT'; // Default

if ($dept_result && $dept_result->num_rows > 0) {
    $dept_row = $dept_result->fetch_assoc();
    $dept_acronym = $dept_row['acronym'];
}

// Get Institutional Development module ID
$query = "SELECT id FROM Modules WHERE name = 'Institutional_Development'";
$result = $conn->query($query);
$InstDev_Id = 1; // Default to 1 if not found

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $InstDev_Id = $row['id'];
}

// Get active institutional development documents filtered by user_id and category
$genderDevelopment = [];
$studentDevelopment = [];
$strategicPlan = [];

if ($user_id) {
    // Get all Institutional Development documents
    $query = "SELECT * FROM main_post WHERE status = 'active' AND module = ? AND user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $InstDev_Id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Extract category from content field
        $category = 'default';
        if (!empty($row['content']) && json_decode($row['content'], true)) {
            $contentData = json_decode($row['content'], true);
            $category = $contentData['category'] ?? 'default';
        }
        
        $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
        $relativeFilePath = "uploads/{$dept_acronym}/Institutional_Development/" . $row['file_path'];

        $document = [
            'id' => $row['id'],
            'file_path' => $relativeFilePath,
            'description' => $row['description'],
            'posted_on' => $row['created_at'],
            'file_type' => strtolower($fileExtension),
            'category' => $category
        ];

        // Sort into appropriate category
        if ($category === 'gender') {
            $genderDevelopment[] = $document;
        } elseif ($category === 'student') {
            $studentDevelopment[] = $document;
        } elseif ($category === 'strategic') {
            $strategicPlan[] = $document;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Development Bulletin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
        }

        .instdev-carousel-container {
            position: relative;
            height: 100%;
            width: 100%;
        }

        .instdev-carousel-card {
            display: none;
            height: 100%;
            width: 100%;
        }

        .instdev-carousel-card.active {
            display: block;
            animation: instdevFadeIn 0.5s ease-in-out;
        }

        @keyframes instdevFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .instdev-pdf-preview {
            width: 100%;
            height: 100%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .instdev-pdf-preview:hover {
            transform: scale(1.02);
        }

        .instdev-pdf-preview canvas {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .instdev-carousel-indicators {
            display: flex;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .instdev-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #d1d5db;
            cursor: pointer;
            transition: all 0.3s;
        }

        .instdev-indicator.active {
            background: #f97316;
            width: 32px;
            border-radius: 6px;
        }

        .instdev-loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #f97316;
            width: 40px;
            height: 40px;
            animation: instdevSpin 1s linear infinite;
        }

        @keyframes instdevSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .instdev-file-icon {
            font-size: 6rem;
            color: #9ca3af;
        }
    </style>
</head>
<body class="bg-gray-50 h-screen w-screen overflow-hidden m-0 p-0">
    <div class="h-full w-full flex flex-col">
        <?php 
        // Determine which category to display based on URL parameter
        $displayCategory = isset($_GET['category']) ? $_GET['category'] : 'gender';
        
        // Select the appropriate documents array based on category
        if ($displayCategory === 'student') {
            $documents = $studentDevelopment;
            $categoryTitle = 'Student Development';
        } elseif ($displayCategory === 'strategic') {
            $documents = $strategicPlan;
            $categoryTitle = 'CvSU Strategic Plan';
        } else {
            $documents = $genderDevelopment;
            $categoryTitle = 'Gender & Development';
        }
        ?>
        
        <?php if (count($documents) > 0): ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="instdev-carousel-container h-full w-full">
                    <!-- Carousel Cards -->
                    <?php foreach ($documents as $index => $doc): ?>
                        <div class="instdev-carousel-card h-full <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                            <?php if ($displayCategory === 'strategic'): ?>
                                <!-- Strategic Plan: Full screen display without description/date/arrows -->
                                <div class="h-full w-full bg-white">
                                    <div class="instdev-pdf-preview" id="instdev-preview-<?= $index ?>" 
                                         style="height: 100vh; width: 100%;"
                                         data-file-path="<?= $doc['file_path'] ?>" 
                                         data-file-type="<?= $doc['file_type'] ?>"
                                         onclick="openInstDevPDFInParent('<?= $doc['file_path'] ?>', '<?= htmlspecialchars($doc['description']) ?>', '<?= date('F j, Y', strtotime($doc['posted_on'])) ?>', '<?= basename($doc['file_path']) ?>')"
                                         title="Click to view full document">
                                        <?php if ($doc['file_type'] === 'pdf'): ?>
                                            <div class="instdev-loading-spinner"></div>
                                        <?php elseif (in_array($doc['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="../../<?= $doc['file_path'] ?>" alt="Preview" class="h-full w-full object-contain">
                                        <?php else: ?>
                                            <i class="fas fa-file instdev-file-icon"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Gender & Student Development: Normal display with description/date/arrows -->
                                <div class="h-full flex flex-col bg-white" style="max-height: 100vh;">
                                    <div class="instdev-pdf-preview" id="instdev-preview-<?= $index ?>" 
                                         style="height: calc(100vh - 150px);"
                                         data-file-path="<?= $doc['file_path'] ?>" 
                                         data-file-type="<?= $doc['file_type'] ?>"
                                         onclick="openInstDevPDFInParent('<?= $doc['file_path'] ?>', '<?= htmlspecialchars($doc['description']) ?>', '<?= date('F j, Y', strtotime($doc['posted_on'])) ?>', '<?= basename($doc['file_path']) ?>')"
                                         title="Click to view full document">
                                        <?php if ($doc['file_type'] === 'pdf'): ?>
                                            <div class="instdev-loading-spinner"></div>
                                        <?php elseif (in_array($doc['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="../../<?= $doc['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain">
                                        <?php else: ?>
                                            <i class="fas fa-file instdev-file-icon"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-6 bg-white" style="flex-shrink: 0;">
                                        <h2 class="text-xl font-bold text-gray-800 mb-2">
                                            <?= htmlspecialchars($doc['description']) ?>
                                        </h2>
                                        <p class="text-gray-600 flex items-center mb-4">
                                            <i class="fas fa-calendar-alt mr-2"></i>
                                            Posted on: <?= date('F j, Y', strtotime($doc['posted_on'])) ?>
                                        </p>
                                        <!-- Navigation Buttons -->
                                        <div class="flex justify-center gap-4">
                                            <button class="bg-orange-500 text-white p-2 sm:p-2 rounded-full hover:bg-orange-600 transition duration-200 transform hover:scale-110" onclick="changeInstDevSlide(-1)">
                                                <svg class="w-5 h-5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M15 19l-7-7 7-7" />
                                                </svg>
                                            </button>
                                            <button class="bg-orange-500 text-white p-2 sm:p-2 rounded-full hover:bg-orange-600 transition duration-200 transform hover:scale-110" onclick="changeInstDevSlide(1)">
                                                <svg class="w-5 h-5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Indicators (hidden for strategic plan) -->
                    <?php if ($displayCategory !== 'strategic'): ?>
                        <div class="instdev-carousel-indicators absolute bottom-2 left-0 right-0">
                            <?php foreach ($documents as $index => $doc): ?>
                                <div class="instdev-indicator <?= $index === 0 ? 'active' : '' ?>" onclick="goToInstDevSlide(<?= $index ?>)"></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="no-instdev-message flex flex-col justify-center items-center h-full p-4 text-center">
                    <i class="fas fa-building text-gray-400 text-5xl mb-4"></i>
                    <p class="text-gray-600 text-lg font-bold">No <?= htmlspecialchars($categoryTitle) ?> documents available</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // PDF.js configuration
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let currentInstDevSlide = 0;
        const instdevSlides = document.querySelectorAll('.instdev-carousel-card');
        const instdevIndicators = document.querySelectorAll('.instdev-indicator');
        const totalInstDevSlides = instdevSlides.length;

        // Function to open PDF in parent window modal
        function openInstDevPDFInParent(filePath, title, postedDate, fileName) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'openPDF',
                    filePath: filePath,
                    title: title,
                    postedDate: postedDate,
                    fileName: fileName
                }, '*');
            } else {
                // Fallback if not in iframe
                window.open(filePath, '_blank');
            }
        }

        // Load PDF previews
        function loadInstDevPDFPreview(index) {
            const previewDiv = document.getElementById(`instdev-preview-${index}`);
            const filePath = previewDiv.dataset.filePath;
            const fileType = previewDiv.dataset.fileType;

            if (fileType !== 'pdf') return;

            const loadingTask = pdfjsLib.getDocument('../../' + filePath);
            loadingTask.promise.then(pdf => {
                pdf.getPage(1).then(page => {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale: scale });
                    
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };

                    page.render(renderContext).promise.then(() => {
                        previewDiv.innerHTML = '';
                        previewDiv.appendChild(canvas);
                    });
                });
            }).catch(error => {
                console.error('Error loading PDF:', error);
                previewDiv.innerHTML = '<i class="fas fa-file-pdf instdev-file-icon" style="color: #dc2626;"></i>';
            });
        }

        function showInstDevSlide(index) {
            instdevSlides.forEach((slide, i) => {
                slide.classList.remove('active');
                instdevIndicators[i].classList.remove('active');
            });

            currentInstDevSlide = (index + totalInstDevSlides) % totalInstDevSlides;
            instdevSlides[currentInstDevSlide].classList.add('active');
            instdevIndicators[currentInstDevSlide].classList.add('active');
        }

        function changeInstDevSlide(direction) {
            showInstDevSlide(currentInstDevSlide + direction);
        }

        function goToInstDevSlide(index) {
            showInstDevSlide(index);
        }

        // Auto-advance carousel every 10 seconds (disabled for strategic plan)
        <?php if ($displayCategory !== 'strategic'): ?>
        setInterval(() => {
            changeInstDevSlide(1);
        }, 10000);
        <?php endif; ?>

        // Load all PDF previews on page load
        window.addEventListener('DOMContentLoaded', () => {
            for (let i = 0; i < totalInstDevSlides; i++) {
                loadInstDevPDFPreview(i);
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') changeInstDevSlide(-1);
            if (e.key === 'ArrowRight') changeInstDevSlide(1);
        });
    </script>
</body>
</html>
