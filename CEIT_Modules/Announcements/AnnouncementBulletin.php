<?php
// CEIT_Modules/Announcements/AnnouncementBulletin.php
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

// Get Announcements module ID
$query = "SELECT id FROM Modules WHERE name = 'Announcements'";
$result = $conn->query($query);
$Ann_Id = 1; // Default to 1 if not found

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $Ann_Id = $row['id'];
}

// Get active announcements from main_post table filtered by user_id
$announcements = [];
if ($user_id) {
    $query = "SELECT * FROM main_post WHERE status = 'active' AND module = ? AND user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $Ann_Id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $fileExtension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
        $relativeFilePath = "uploads/{$dept_acronym}/Announcement/" . $row['file_path'];

        $announcements[] = [
            'id' => $row['id'],
            'file_path' => $relativeFilePath,
            'description' => $row['description'],
            'posted_on' => $row['created_at'],
            'file_type' => strtolower($fileExtension)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEIT Announcements Bulletin</title>
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

        .carousel-container {
            position: relative;
            height: 100%;
            width: 100%;
        }

        .carousel-card {
            display: none;
            height: 100%;
            width: 100%;
        }

        .carousel-card.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .pdf-preview {
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

        .pdf-preview:hover {
            transform: scale(1.02);
        }

        .pdf-preview canvas {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .carousel-indicators {
            display: flex;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #d1d5db;
            cursor: pointer;
            transition: all 0.3s;
        }

        .indicator.active {
            background: #f97316;
            width: 32px;
            border-radius: 6px;
        }

        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #f97316;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .file-icon {
            font-size: 6rem;
            color: #9ca3af;
        }
    </style>
</head>
<body class="bg-gray-50 h-screen w-screen overflow-hidden m-0 p-0">
    <div class="h-full w-full flex flex-col">
        <?php if (count($announcements) > 0): ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="carousel-container h-full w-full">
                    <!-- Carousel Cards -->
                    <?php foreach ($announcements as $index => $announcement): ?>
                        <div class="carousel-card h-full <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                            <div class="h-full flex flex-col bg-white" style="max-height: 100vh;">
                                <div class="pdf-preview" id="preview-<?= $index ?>" 
                                     style="height: calc(100vh - 150px);"
                                     data-file-path="<?= $announcement['file_path'] ?>" 
                                     data-file-type="<?= $announcement['file_type'] ?>"
                                     onclick="openPDFInParent('<?= $announcement['file_path'] ?>', '<?= htmlspecialchars($announcement['description']) ?>', '<?= date('F j, Y', strtotime($announcement['posted_on'])) ?>', '<?= basename($announcement['file_path']) ?>')"
                                     title="Click to view full document">
                                    <?php if ($announcement['file_type'] === 'pdf'): ?>
                                        <div class="loading-spinner"></div>
                                    <?php elseif (in_array($announcement['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="../../<?= $announcement['file_path'] ?>" alt="Preview" class="max-h-full max-w-full object-contain">
                                    <?php else: ?>
                                        <i class="fas fa-file file-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="p-6 bg-white" style="flex-shrink: 0;">
                                    <h2 class="text-xl font-bold text-gray-800 mb-2">
                                        <?= htmlspecialchars($announcement['description']) ?>
                                    </h2>
                                    <p class="text-gray-600 flex items-center mb-4">
                                        <i class="fas fa-calendar-alt mr-2"></i>
                                        Posted on: <?= date('F j, Y', strtotime($announcement['posted_on'])) ?>
                                    </p>
                                    <!-- Navigation Buttons -->
                                    <div class="flex justify-center gap-4">
                                        <button class="bg-orange-500 text-white p-2 sm:p-2 rounded-full hover:bg-orange-600 transition duration-200 transform hover:scale-110" onclick="changeSlide(-1)">
                                            <svg class="w-5 h-5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M15 19l-7-7 7-7" />
                                            </svg>
                                        </button>
                                        <button class="bg-orange-500 text-white p-2 sm:p-2 rounded-full hover:bg-orange-600 transition duration-200 transform hover:scale-110" onclick="changeSlide(1)">
                                            <svg class="w-5 h-5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="6" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Indicators -->
                    <div class="carousel-indicators absolute bottom-2 left-0 right-0">
                        <?php foreach ($announcements as $index => $announcement): ?>
                            <div class="indicator <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="no-memos-message flex flex-col justify-center items-center h-full p-4 text-center">
                    <i class="fas fa-file-alt text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 text-lg font-bold">No approved announcement available</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // PDF.js configuration
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-card');
        const indicators = document.querySelectorAll('.indicator');
        const totalSlides = slides.length;

        // Function to open PDF in parent window modal
        function openPDFInParent(filePath, title, postedDate, fileName) {
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
        function loadPDFPreview(index) {
            const previewDiv = document.getElementById(`preview-${index}`);
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
                previewDiv.innerHTML = '<i class="fas fa-file-pdf file-icon" style="color: #dc2626;"></i>';
            });
        }

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.remove('active');
                indicators[i].classList.remove('active');
            });

            currentSlide = (index + totalSlides) % totalSlides;
            slides[currentSlide].classList.add('active');
            indicators[currentSlide].classList.add('active');
        }

        function changeSlide(direction) {
            showSlide(currentSlide + direction);
        }

        function goToSlide(index) {
            showSlide(index);
        }

        // Auto-advance carousel every 10 seconds
        setInterval(() => {
            changeSlide(1);
        }, 10000);

        // Load all PDF previews on page load
        window.addEventListener('DOMContentLoaded', () => {
            for (let i = 0; i < totalSlides; i++) {
                loadPDFPreview(i);
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') changeSlide(-1);
            if (e.key === 'ArrowRight') changeSlide(1);
        });
    </script>
</body>
</html>
