<?php
// Modules/Announcements/update_announcement.php
session_start();
include "../../db.php";

// Set JSON header for all responses
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID and department ID from session
$userId = $_SESSION['user_info']['id'];
$deptId = $_SESSION['dept_id'] ?? 0;

// Get department acronym from database
$dept_acronym = 'default'; // fallback

// Query the departments table to get the acronym
$query = "SELECT acronym FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dept_acronym = $row['acronym'];
}

// Create dynamic upload directory
$uploadDir = "../../uploads/{$dept_acronym}/Announcement/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $description = $_POST['description'];

    // Check if a new file was uploaded
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

        // Get the current file path to delete it
        $query = "SELECT file_path FROM department_post WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $deptId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $oldFilePath = $uploadDir . $row['file_path'];

            // Delete the old file if it exists
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
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
            // Update the record in the database
            // If status is 'not-approved', set it back to 'pending' after editing
            $newStatus = ($status === 'not-approved') ? 'Pending' : $status;

            $query = "UPDATE department_post SET description = ?, file_path = ?, status = ? WHERE id = ? AND dept_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssii", $description, $newFilename, $newStatus, $id, $deptId);

            if ($stmt->execute()) {
                $message = ($status === 'not-approved')
                    ? 'Announcement updated successfully and moved back to pending'
                    : 'Announcement updated successfully';
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating announcement: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error uploading file']);
        }
    } else {
        // No new file, just update the description
        // If status is 'not-approved', set it back to 'pending' after editing
        $newStatus = ($status === 'not-approved') ? 'Pending' : $status;

        $query = "UPDATE department_post SET description = ?, status = ? WHERE id = ? AND dept_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssii", $description, $newStatus, $id, $deptId);

        if ($stmt->execute()) {
            $message = ($status === 'not-approved')
                ? 'Announcement updated successfully and moved back to pending'
                : 'Announcement updated successfully';
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating announcement: ' . $conn->error]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
