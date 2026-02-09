<?php
// CEIT_Modules/Announcements/AddAnnouncement.php
session_start();
include "../../db.php";

// Check if user is Lead MIS Officer
if (!isset($_SESSION['user_info']) || $_SESSION['user_info']['role'] !== 'LEAD_MIS' || $_SESSION['user_info']['dept_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_info']['id'];

// Get department acronym from database
$dept_id = $_SESSION['user_info']['dept_id'];
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

// Create dynamic upload directory
$uploadDir = "../../uploads/{$dept_acronym}/Announcement/";
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create uploads directory']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['description'];

    if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
        $fileName = $_FILES['pdfFile']['name'];
        $fileSize = $_FILES['pdfFile']['size'];
        $fileType = $_FILES['pdfFile']['type'];

        // Check file size (limit to 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
            exit;
        }

        // Check file type
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif'
        ];

        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed']);
            exit;
        }

        // Generate new filename with timestamp and random component for uniqueness
        $now = new DateTime();
        $day = $now->format('d');
        $month = $now->format('m');
        $year = $now->format('Y');
        $hours = $now->format('H');
        $minutes = $now->format('i');
        $seconds = $now->format('s');
        $random = mt_rand(1000, 9999);

        // Get file extension
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        // Format: announcement_DD_MM_YYYY_HH_MM_SS_RANDOM.extension
        $newFilename = "announcement_{$day}_{$month}_{$year}_{$hours}_{$minutes}_{$seconds}_{$random}.{$fileExtension}";

        // Move the file to the uploads directory
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Insert the record into the database
            $query = "INSERT INTO main_post (module, description, file_path, user_id, status) VALUES (?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($query);
            $moduleId = 1; // Assuming module ID 1 is for announcements
            $stmt->bind_param("issi", $moduleId, $description, $newFilename, $userId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Announcement created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error uploading file']);
        }
    } else {
        $error_message = "Error: No file uploaded or file upload error";
        if (isset($_FILES['pdfFile']['error'])) {
            switch ($_FILES['pdfFile']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_message = "Error: The uploaded file exceeds the upload_max_filesize directive in php.ini";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = "Error: The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = "Error: The uploaded file was only partially uploaded";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = "Error: No file was uploaded";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message = "Error: Missing a temporary folder";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message = "Error: Failed to write file to disk";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message = "Error: A PHP extension stopped the file upload";
                    break;
                default:
                    $error_message = "Error: Unknown upload error";
            }
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
