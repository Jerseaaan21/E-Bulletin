<?php
session_start();
include "../../db.php";

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get department acronym and ID from session
$dept_acronym = $_SESSION['dept_acronym'] ?? 'default';
$dept_id = $_SESSION['dept_id'] ?? 0;
$user_id = $_SESSION['user_info']['id'];

// Verify that the user exists in the database
$userCheckQuery = "SELECT id FROM users WHERE id = ?";
$userCheckStmt = $conn->prepare($userCheckQuery);
$userCheckStmt->bind_param("i", $user_id);
$userCheckStmt->execute();
$userCheckResult = $userCheckStmt->get_result();

if ($userCheckResult->num_rows === 0) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Get module ID for Announcements
$moduleQuery = "SELECT id FROM modules WHERE name = 'Announcements' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Create dynamic upload directory
$uploadDir = "../../uploads/{$dept_acronym}/Announcement/";
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header('Content-Type: application/json');
        http_response_code(500);
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
            header('Content-Type: application/json');
            http_response_code(413);
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
            header('Content-Type: application/json');
            http_response_code(415);
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
            // Insert the record into the database with status set to 'Pending'
            $query = "INSERT INTO department_post (module, description, file_path, status, dept_id, user_id) VALUES (?, ?, ?, 'Pending', ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issii", $moduleId, $description, $newFilename, $dept_id, $user_id);

            if ($stmt->execute()) {
                // Return success response
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Announcement uploaded successfully']);
                exit;
            } else {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error uploading file: ' . error_get_last()['message']]);
            exit;
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
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
