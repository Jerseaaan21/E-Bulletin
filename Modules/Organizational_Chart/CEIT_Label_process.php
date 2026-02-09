<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if database connection exists, if not include it
if (!isset($conn)) {
    include '../../db.php';
}

// Set header to ensure JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Debug: Log session data
    $debug_data = [
        'session_user_info' => isset($_SESSION['user_info']) ? $_SESSION['user_info'] : 'NOT SET',
        'session_dept_id' => isset($_SESSION['user_info']['dept_id']) ? $_SESSION['user_info']['dept_id'] : 'NOT SET',
        'post_dept_id' => isset($_POST['department_id']) ? $_POST['department_id'] : 'NOT SET',
        'session_status' => session_status()
    ];

    // Check if user_info is set in session and contains dept_id
    if (!isset($_SESSION['user_info']) || !isset($_SESSION['user_info']['dept_id'])) {
        echo json_encode(['success' => false, 'error' => 'Department not set in session', 'debug' => $debug_data]);
        exit;
    }

    // Get the department ID from the session
    $dept_id = $_SESSION['user_info']['dept_id'];
    $debug_data['using_dept_id'] = $dept_id;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Check if required fields are set
        if (!isset($_POST['position']) || !isset($_POST['label'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields', 'debug' => $debug_data]);
            exit;
        }

        $position = $_POST['position'];
        $department_id = isset($_POST['department_id']) ? $_POST['department_id'] : $dept_id;

        // Verify the department_id again (in case it was passed in POST)
        $checkDept = $conn->prepare("SELECT dept_id FROM departments WHERE dept_id = ?");
        $checkDept->bind_param("i", $department_id);
        $checkDept->execute();
        $deptResult = $checkDept->get_result();

        if ($deptResult->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid department ID', 'debug' => $debug_data]);
            exit;
        }

        $label = $_POST['label'];

        // Check if the label already exists
        $stmt = $conn->prepare("SELECT id FROM organization_label WHERE position = ? AND department_id = ?");
        $stmt->bind_param("si", $position, $department_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing label
            $stmt = $conn->prepare("UPDATE organization_label SET label = ? WHERE position = ? AND department_id = ?");
            $stmt->bind_param("ssi", $label, $position, $department_id);
        } else {
            // Insert new label
            $stmt = $conn->prepare("INSERT INTO organization_label (label, position, department_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $label, $position, $department_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error, 'debug' => $debug_data]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request method', 'debug' => $debug_data]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug_data]);
}
