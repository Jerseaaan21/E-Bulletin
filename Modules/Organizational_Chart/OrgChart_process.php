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

    // Get department acronym for directory structure
    $deptQuery = $conn->prepare("SELECT acronym FROM departments WHERE dept_id = ?");
    $deptQuery->bind_param("i", $dept_id);
    $deptQuery->execute();
    $deptResult = $deptQuery->get_result();

    if ($deptResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Department not found', 'debug' => $debug_data]);
        exit;
    }

    $deptRow = $deptResult->fetch_assoc();
    $deptAcronym = $deptRow['acronym'];
    $debug_data['dept_acronym'] = $deptAcronym;

    // Verify that the department exists
    $checkDept = $conn->prepare("SELECT dept_id, dept_name FROM departments WHERE dept_id = ?");
    $checkDept->bind_param("i", $dept_id);
    $checkDept->execute();
    $deptResult = $checkDept->get_result();

    if ($deptResult->num_rows === 0) {
        // Debug: Get all departments to see what's available
        $allDepts = $conn->query("SELECT dept_id, dept_name FROM departments");
        $allDeptsResult = [];
        while ($row = $allDepts->fetch_assoc()) {
            $allDeptsResult[] = $row;
        }
        $debug_data['available_departments'] = $allDeptsResult;

        echo json_encode(['success' => false, 'error' => 'Invalid department ID', 'debug' => $debug_data]);
        exit;
    } else {
        // Debug: Log the department we found
        $deptRow = $deptResult->fetch_assoc();
        $debug_data['found_department'] = $deptRow;
    }

    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);

        $result = $conn->query("DELETE FROM ceit_organization WHERE id = $id AND department_id = $dept_id");

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error, 'debug' => $debug_data]);
        }
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Check if required fields are set
        if (!isset($_POST['name']) || !isset($_POST['role']) || !isset($_POST['position_code'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields', 'debug' => $debug_data]);
            exit;
        }

        $id = intval($_POST['member_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $role = $conn->real_escape_string($_POST['role']);
        $position = $conn->real_escape_string($_POST['position_code']);
        $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : $dept_id;

        // Verify the department_id again (in case it was passed in POST)
        $checkDept = $conn->prepare("SELECT dept_id FROM departments WHERE dept_id = ?");
        $checkDept->bind_param("i", $department_id);
        $checkDept->execute();
        $deptResult = $checkDept->get_result();

        if ($deptResult->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid department ID in POST', 'debug' => $debug_data]);
            exit;
        }

        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            // Create directory structure based on department acronym
            $targetDir = "../../uploads/" . $deptAcronym . "/OrgChart_Photo/";

            // Create directory if needed
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $photo = basename($_FILES["photo"]["name"]);
            $targetFile = $targetDir . $photo;

            if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
                echo json_encode(['success' => false, 'error' => 'Failed to upload photo', 'debug' => $debug_data]);
                exit;
            }
        }

        if ($id > 0) {
            // Update existing
            if ($photo) {
                $stmt = $conn->prepare("UPDATE ceit_organization SET name=?, role=?, photo=?, position_code=?, department_id=? WHERE id=? AND department_id=?");
                $stmt->bind_param("ssssiii", $name, $role, $photo, $position, $department_id, $id, $department_id);
            } else {
                // Keep existing photo
                $stmt = $conn->prepare("UPDATE ceit_organization SET name=?, role=?, position_code=?, department_id=? WHERE id=? AND department_id=?");
                $stmt->bind_param("sssiii", $name, $role, $position, $department_id, $id, $department_id);
            }
        } else {
            // Insert new - use the position_code from the form
            $stmt = $conn->prepare("INSERT INTO ceit_organization (name, role, photo, position_code, department_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $name, $role, $photo, $position, $department_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error, 'debug' => $debug_data]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request method', 'debug' => $debug_data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug_data]);
}
