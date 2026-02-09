<?php
// CEIT_Modules/About/AboutBulletin.php
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

// Get About module ID
$query = "SELECT id FROM Modules WHERE name = 'About'";
$result = $conn->query($query);
$About_Id = null;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $About_Id = $row['id'];
}

// Get active about posts from main_post table ordered by order_position
$aboutPosts = [];
if ($About_Id) {
    $query = "SELECT * FROM main_post WHERE status = 'active' AND module = ? ORDER BY order_position ASC, id ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $About_Id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $aboutPosts[] = [
            'id' => $row['id'],
            'description' => $row['description'],
            'content' => $row['content'],
            'order_position' => $row['order_position']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Department</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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

        .carousel {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .carousel-item {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            transition: all 0.5s ease-in-out;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .carousel-item:not(.active) {
            transform: translateY(20px) scale(0.95);
            opacity: 0;
            z-index: 0;
        }

        .carousel-item.active {
            transform: translateY(0) scale(1);
            opacity: 1;
            z-index: 1;
        }

        .responsive-bg {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .carousel-text {
            font-size: clamp(0.7rem, 1.8vw, 1.2rem);
        }
    </style>
</head>
<body>
    <?php if (count($aboutPosts) > 0): ?>
        <div class="carousel h-full">
            <?php foreach ($aboutPosts as $index => $post): ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?> h-full">
                    <div class="h-full flex items-center justify-center bg-gray-100 responsive-bg" style="background-image: url('../../bg-small.png');">
                        <div class="carousel-text p-2 sm:p-3" style="margin: 5px 10px; background-color: rgba(240, 240, 240, 0.8); border-radius: 6px; line-height: 1.5; max-width: 90%; max-height: 90%; overflow-y: auto;">
                            <strong><?= htmlspecialchars($post['description']) ?></strong><br><br>
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="h-full flex items-center justify-center bg-gray-100">
            <div class="text-center p-4">
                <i class="fas fa-info-circle text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600">No department information available</p>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Carousel functionality
        let currentIndex = 0;
        const items = document.querySelectorAll('.carousel-item');
        const totalItems = items.length;

        function showSlide(index) {
            items.forEach((item, i) => {
                item.classList.remove('active');
            });

            currentIndex = (index + totalItems) % totalItems;
            items[currentIndex].classList.add('active');
        }

        function nextSlide() {
            showSlide(currentIndex + 1);
        }

        function prevSlide() {
            showSlide(currentIndex - 1);
        }

        // Auto-advance carousel every 10 seconds
        if (totalItems > 1) {
            setInterval(() => {
                nextSlide();
            }, 10000);
        }

        // Listen for navigation events from parent
        window.addEventListener('message', function(event) {
            if (event.data.type === 'aboutNext') {
                nextSlide();
            } else if (event.data.type === 'aboutPrev') {
                prevSlide();
            }
        });
    </script>
</body>
</html>
