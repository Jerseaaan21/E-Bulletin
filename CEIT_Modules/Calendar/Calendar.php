<?php
include "../../db.php";
session_start();
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['user_info'])) {
    // Get user info from session
    $userEmail = $_SESSION['user_info']['email'];

    // Fetch user data from database
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $userId = $user['id'];
        $userDeptId = $user['dept_id'];
    }
}

// Get department information
$query = "SELECT * FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userDeptId);
$stmt->execute();
$result = $stmt->get_result();
$department = $result->fetch_assoc();

// Store both the numeric ID and acronym in session
$_SESSION['dept_id'] = $department['dept_id']; // Numeric ID
$_SESSION['dept_acronym'] = $department['acronym']; // Acronym

// Use the numeric ID for database operations
$dept_id = $department['dept_id'];

// Get current school year from session or use default
$current_school_year = isset($_SESSION['current_school_year']) ? $_SESSION['current_school_year'] : '2025-2026';

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Calendar POST request received");
    $input = file_get_contents("php://input");
    error_log("Raw input: " . $input);
    $data = json_decode($input, true);
    error_log("Calendar POST data: " . print_r($data, true));

    if (!isset($data['action'])) {
        error_log("Calendar POST: No action provided");
        http_response_code(400);
        echo json_encode(["error" => "Invalid request"]);
        exit;
    }

    // Handle school year update
    if ($data['action'] === 'update_school_year') {
        $new_school_year = $conn->real_escape_string($data['school_year']);
        
        // Store in session for persistence
        $_SESSION['current_school_year'] = $new_school_year;
        
        // Update the default value in the database table dynamically
        try {
            $alter_query = "ALTER TABLE ceit_calendar ALTER COLUMN school_year SET DEFAULT '$new_school_year'";
            $result = $conn->query($alter_query);
            if (!$result) {
                throw new Exception("Failed to update table default: " . $conn->error);
            }
            error_log("Successfully updated table default to: $new_school_year");
        } catch (Exception $e) {
            error_log("Could not update table default: " . $e->getMessage());
            // Continue anyway, session will handle the current value
        }
        
        echo json_encode(["success" => true, "school_year" => $new_school_year]);
        exit;
    }

    // Handle preview data loading for different school years
    if ($data['action'] === 'load_preview') {
        $preview_school_year = $conn->real_escape_string($data['school_year']);
        
        // Debug logging
        error_log("Loading preview for school year: $preview_school_year, dept_id: $dept_id");
        
        ob_start();
        renderPreviewTable($conn, 1, $dept_id, $preview_school_year); 
        renderPreviewTable($conn, 2, $dept_id, $preview_school_year);
        $preview_html = ob_get_clean();
        
        // Debug logging
        error_log("Preview HTML length: " . strlen($preview_html));
        
        echo json_encode([
            "success" => true, 
            "html" => $preview_html,
            "school_year" => $preview_school_year
        ]);
        exit;
    }

    if (!isset($data['entry'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid request"]);
        exit;
    }

    $entry = $data['entry'];
    $action = $data['action'];
    $id = isset($entry['id']) ? intval($entry['id']) : null;
    $semester = intval($entry['semester']);
    $start_date = $conn->real_escape_string($entry['start_date']);
    $end_date = $conn->real_escape_string($entry['end_date']);
    $desc = $conn->real_escape_string($entry['description']);
    $school_year = isset($entry['school_year']) ? $conn->real_escape_string($entry['school_year']) : '2025-2026';

    if ($action === "delete" && $id) {
        ob_clean();
        header('Content-Type: application/json');
        
        try {
            $result = $conn->query("DELETE FROM ceit_calendar WHERE id = $id");
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === "save") {
        ob_clean(); // Clear any previous output
        header('Content-Type: application/json');
        
        // Debug logging
        error_log("Calendar save action - ID: $id, Semester: $semester, Start: $start_date, End: $end_date, Desc: $desc, School Year: $school_year");
        
        try {
            if ($id) {
                // Update existing entry
                $query = "UPDATE ceit_calendar SET start_date='$start_date', end_date='$end_date', description='$desc', semester=$semester, school_year='$school_year' WHERE id=$id";
                error_log("Update query: $query");
                $result = $conn->query($query);
                if ($result) {
                    echo json_encode(["success" => true, "updated" => true]);
                } else {
                    throw new Exception("Update failed: " . $conn->error);
                }
            } else {
                // Insert new entry with all required fields
                $query = "INSERT INTO ceit_calendar (start_date, end_date, description, semester, department_id, title, subtitle, school_year) VALUES ('$start_date', '$end_date', '$desc', $semester, $dept_id, '', '', '$school_year')";
                error_log("Insert query: $query");
                $result = $conn->query($query);
                if ($result) {
                    $newId = $conn->insert_id;
                    echo json_encode(["success" => true, "inserted" => true, "id" => $newId]);
                } else {
                    throw new Exception("Insert failed: " . $conn->error);
                }
            }
        } catch (Exception $e) {
            error_log("Calendar save error: " . $e->getMessage());
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(["error" => "Unknown action"]);
    exit;
}

// Render tables
function renderSemesterTable($conn, $semester, $dept_id, $school_year = '2025-2026')
{
    $color = 'orange';
    $title = $semester === 1 ? 'First Semester' : 'Second Semester';

    // Only fetch regular entries (semester 1 or 2) for the specific school year
    $query = "SELECT * FROM ceit_calendar WHERE semester = $semester AND department_id = $dept_id AND school_year = '$school_year' ORDER BY start_date ASC";
    $result = $conn->query($query);

    echo <<<HTML
    <div>
        <h2 class="text-3xl font-bold text-$color-600 mb-5">$title</h2>
        <div class="overflow-x-auto">
            <table class="calendar-table w-full text-sm mb-4" data-semester="$semester">
                <thead class="bg-$color-200 text-gray-900">
                    <tr>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Description</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
    HTML;

    // Add predefined rows based on semester
    if ($semester == 1) {
        // First Semester predefined rows
        $predefinedRows = [
            ['description' => 'First Semester Start', 'class' => 'bg-blue-50'],
            ['description' => 'Prelim Exam', 'class' => 'bg-red-50'],
            ['description' => 'Midterm Exam', 'class' => 'bg-red-50'],
            ['description' => 'Finals Exam', 'class' => 'bg-red-50'],
            ['description' => 'Strasuc', 'class' => 'bg-green-50'],
            ['description' => 'Sem Break', 'class' => 'bg-yellow-50']
        ];
    } else {
        // Second Semester predefined rows
        $predefinedRows = [
            ['description' => 'Second Semester Start', 'class' => 'bg-blue-50'],
            ['description' => 'Prelim Exam', 'class' => 'bg-red-50'],
            ['description' => 'Midterm Exam', 'class' => 'bg-red-50'],
            ['description' => 'Finals Exam', 'class' => 'bg-red-50'],
            ['description' => 'Strasuc', 'class' => 'bg-green-50'],
            ['description' => 'Sem Break', 'class' => 'bg-yellow-50']
        ];
    }

    // Check if predefined rows already exist in database for this school year
    $existingPredefined = [];
    foreach ($predefinedRows as $predefined) {
        $desc = $predefined['description'];
        $checkQuery = "SELECT * FROM ceit_calendar WHERE description = '$desc' AND semester = $semester AND department_id = $dept_id AND school_year = '$school_year'";
        $checkResult = $conn->query($checkQuery);
        if ($checkResult && $checkResult->num_rows > 0) {
            $existingPredefined[$desc] = $checkResult->fetch_assoc();
        }
    }

    // Display predefined rows (either from database or as new)
    foreach ($predefinedRows as $predefined) {
        $desc = $predefined['description'];
        $existing = isset($existingPredefined[$desc]) ? $existingPredefined[$desc] : null;
        
        if ($existing) {
            // Show existing predefined row from database
            $id = $existing['id'];
            $start_date = htmlspecialchars($existing['start_date']);
            $end_date = htmlspecialchars($existing['end_date']);
            $start_date_formatted = formatDateToWords($start_date);
            $end_date_formatted = formatDateToWords($end_date);
            
            echo <<<HTML
            <tr class="{$predefined['class']}" data-predefined="true" data-id="$id">
                <td>
                    <input type="hidden" class="date-value" value="$start_date" />
                    <input type="date" class="date-picker" value="$start_date" disabled style="display: none;" />
                    <input type="text" class="date-display" value="$start_date_formatted" disabled />
                </td>
                <td>
                    <input type="hidden" class="date-value" value="$end_date" />
                    <input type="date" class="date-picker" value="$end_date" disabled style="display: none;" />
                    <input type="text" class="date-display" value="$end_date_formatted" disabled />
                </td>
                <td>
                    <input type="text" value="$desc" disabled style="font-weight: 600;" />
                </td>
                <td class="text-center space-x-2">
                    <button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit">
                        <i class="fas fa-pen fa-sm mr-1"></i>Edit
                    </button>
                    <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Reset">
                        <i class="fas fa-undo fa-sm mr-1"></i>Reset
                    </button>
                </td>
            </tr>
            HTML;
        } else {
            // Show new predefined row (not yet in database) - always show these as empty rows
            echo <<<HTML
            <tr class="{$predefined['class']}" data-predefined="true">
                <td>
                    <input type="hidden" class="date-value" value="" />
                    <input type="date" class="date-picker" value="" disabled style="display: none;" />
                    <input type="text" class="date-display" value="" disabled placeholder="Select date" />
                </td>
                <td>
                    <input type="hidden" class="date-value" value="" />
                    <input type="date" class="date-picker" value="" disabled style="display: none;" />
                    <input type="text" class="date-display" value="" disabled placeholder="Select date" />
                </td>
                <td>
                    <input type="text" value="$desc" disabled style="font-weight: 600;" />
                </td>
                <td class="text-center space-x-2">
                    <button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit">
                        <i class="fas fa-pen fa-sm mr-1"></i>Edit
                    </button>
                    <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Reset" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-undo fa-sm mr-1"></i>Reset
                    </button>
                </td>
            </tr>
            HTML;
        }
    }

    // Display other existing database entries (non-predefined)
    while ($row = $result->fetch_assoc()) {
        // Skip predefined entries as they're already shown above
        $isPredefined = in_array($row['description'], array_column($predefinedRows, 'description'));
        if ($isPredefined) continue;
        
        $id = $row['id'];
        $start_date = htmlspecialchars($row['start_date']);
        $end_date = htmlspecialchars($row['end_date']);
        $desc = htmlspecialchars($row['description']);

        // Format dates to words
        $start_date_formatted = formatDateToWords($start_date);
        $end_date_formatted = formatDateToWords($end_date);

        echo <<<HTML
        <tr data-id="$id">
            <td>
                <input type="hidden" class="date-value" value="$start_date" />
                <input type="date" class="date-picker" value="$start_date" disabled style="display: none;" />
                <input type="text" class="date-display" value="$start_date_formatted" disabled />
            </td>
            <td>
                <input type="hidden" class="date-value" value="$end_date" />
                <input type="date" class="date-picker" value="$end_date" disabled style="display: none;" />
                <input type="text" class="date-display" value="$end_date_formatted" disabled />
            </td>
            <td><input type="text" value="$desc" disabled /></td>
            <td class="text-center space-x-2">
                <button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit">
                    <i class="fas fa-pen fa-sm mr-1"></i>Edit
                </button>
                <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Delete">
                    <i class="fas fa-trash fa-sm mr-1"></i>Delete
                </button>
            </td>
        </tr>
        HTML;
    }

    echo <<<HTML
                </tbody>
            </table>
            <button class="add-row mb-5 ml-5 mt-2 px-3 py-1 text-orange-500 border border-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 transition duration-200 transform hover:scale-110" data-semester="$semester" title="Add">
                <i class="fas fa-plus fa-sm"></i> Add Row
            </button>
        </div>
    </div>
    HTML;
}

// Function to format date from YYYY-MM-DD to Month Day, Year
function formatDateToWords($dateString)
{
    if (empty($dateString)) return "";
    $date = new DateTime($dateString);
    return $date->format('F j, Y'); // e.g., "April 4, 2023"
}

// Preview functions for CalendarView display
function formatDate($dateString)
{
    return date("F j, Y", strtotime($dateString));
}

function formatEventDate($start_date, $end_date)
{
    // If start and end dates are the same, just format one date
    if ($start_date === $end_date) {
        return formatDate($start_date);
    }
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    // Check if dates are in the same month and year
    if ($start->format('m Y') === $end->format('m Y')) {
        // Format as "April 21-22, 2024"
        return $start->format('F j') . '-' . $end->format('j, Y');
    }
    
    // Different months or years, show full range
    return formatDate($start_date) . ' - ' . formatDate($end_date);
}

function renderPreviewTable($conn, $semester, $dept_id, $school_year = '2025-2026')
{
    $title = $semester === 1 ? 'First Semester' : 'Second Semester';
    $color = 'orange';

    // Only fetch regular entries (semester 1 or 2) for the specific school year
    $query = "SELECT * FROM ceit_calendar WHERE semester = $semester AND department_id = $dept_id AND school_year = '$school_year' ORDER BY start_date ASC";
    $result = $conn->query($query);

    echo <<<HTML
    <div id="preview-semester-$semester" class="mb-6" style="display: none;">
        <h3 class="text-lg font-bold text-{$color}-600 mb-3">$title</h3>
        <div class="overflow-x-auto">
            <table class="w-full table-fixed border-collapse text-xs">
                <thead class="bg-{$color}-100 text-gray-800">
                    <tr>
                        <th class="w-1/3 px-2 py-1 border border-gray-300">Date</th>
                        <th class="w-2/3 px-2 py-1 border border-gray-300">Event Description</th>
                    </tr>
                </thead>
                <tbody>
    HTML;

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $formattedDate = formatEventDate($row['start_date'], $row['end_date']);
            $desc = htmlspecialchars($row['description']);
            echo <<<HTML
            <tr>
                <td class="px-2 py-1 border border-gray-300 break-words">$formattedDate</td>
                <td class="px-2 py-1 border border-gray-300 break-words">$desc</td>
            </tr>
            HTML;
        }
    } else {
        echo <<<HTML
        <tr>
            <td colspan="2" class="p-3 border border-gray-300 text-center text-gray-500">No events scheduled</td>
        </tr>
        HTML;
    }

    echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
    HTML;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>University Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .calendar-table input {
            width: 100%;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .calendar-table th,
        .calendar-table td {
            padding: 8px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }

        textarea:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #f97316;
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
    </style>
</head>

<body class="bg-gray-100 p-6">
    <!-- Notification Container -->
    <div id="notification-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

    <div class="max-w-5xl mx-auto bg-white p-6 rounded shadow space-y-10">
        <!-- School Year Management Section -->
        <div class="bg-gradient-to-r from-orange-50 to-orange-100 p-6 rounded-lg border border-orange-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-orange-800 mb-2">School Year Management</h2>
                    <p class="text-orange-600 text-sm">Current school year: <span id="current-school-year" class="font-semibold"><?php echo $current_school_year; ?></span></p>
                </div>
                <button id="edit-school-year-btn" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition duration-200">
                    <i class="fas fa-edit mr-2"></i>Change School Year
                </button>
            </div>
            
            <!-- School Year Edit Form (Hidden by default) -->
            <div id="school-year-form" class="mt-4 p-4 bg-white rounded-lg border border-orange-300" style="display: none;">
                <div class="flex items-center gap-4">
                    <label for="new-school-year" class="text-sm font-medium text-gray-700">New School Year:</label>
                    <input type="text" id="new-school-year" placeholder="2025-2026" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" />
                    <button id="save-school-year-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-save mr-1"></i>Save
                    </button>
                    <button id="cancel-school-year-btn" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">Format: YYYY-YYYY (e.g., 2025-2026)</p>
            </div>
        </div>

        <div id="calendar-content">
            <?php 
            renderSemesterTable($conn, 1, $dept_id, $current_school_year); 
            renderSemesterTable($conn, 2, $dept_id, $current_school_year); 
            ?>
        </div>
        
        <!-- Preview Section - How it looks in CalendarView.php -->
        <div class="mt-12 border-t pt-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Preview - Public View</h2>
                <p class="text-gray-600">This is what viewers will see in the bulletin</p>
                
                <!-- School Year Selector for Preview -->
                <div class="mt-4 flex items-center gap-4">
                    <label for="preview-school-year-select" class="text-sm font-medium text-gray-700">View School Year:</label>
                    <select id="preview-school-year-select" class="px-3 py-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <?php
                        // Get all available school years from database
                        $years_query = "SELECT DISTINCT school_year FROM ceit_calendar WHERE department_id = $dept_id ORDER BY school_year DESC";
                        $years_result = $conn->query($years_query);
                        
                        // Add current school year if not in database yet
                        $available_years = [$current_school_year];
                        while ($year_row = $years_result->fetch_assoc()) {
                            if (!in_array($year_row['school_year'], $available_years)) {
                                $available_years[] = $year_row['school_year'];
                            }
                        }
                        
                        // Sort years in descending order
                        rsort($available_years);
                        
                        foreach ($available_years as $year) {
                            $selected = ($year === $current_school_year) ? 'selected' : '';
                            echo "<option value='$year' $selected>$year</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="bg-gray-50 p-6 rounded-lg">
                <div class="max-w-md mx-auto bg-white p-4 rounded-lg shadow-md">
                    <div class="text-center mb-4">
                        <h1 class="text-2xl font-bold text-orange-500">University Calendar</h1>
                        <h2 class="text-lg text-orange-400">Academic Year <span id="preview-school-year"><?php echo $current_school_year; ?></span></h2>
                    </div>

                    <div id="preview-content">
                        <?php 
                        renderPreviewTable($conn, 1, $dept_id, $current_school_year); 
                        renderPreviewTable($conn, 2, $dept_id, $current_school_year); 
                        ?>
                    </div>

                    <div class="text-center text-sm text-gray-500 mt-4">
                        Last updated: <?php echo date('F j, Y'); ?>
                    </div>
                </div>
                
                <!-- Preview Semester Buttons -->
                <div class="flex justify-center gap-4 mt-4">
                    <button id="preview-btn-sem1" onclick="showPreviewSemester(1)"
                        class="px-3 py-1 text-sm bg-orange-500 text-white rounded hover:bg-orange-600 transition duration-200 transform hover:scale-110 active-preview-semester">
                        First Semester
                    </button>
                    <button id="preview-btn-sem2" onclick="showPreviewSemester(2)"
                        class="px-3 py-1 text-sm bg-orange-500 text-white rounded hover:bg-orange-600 transition duration-200 transform hover:scale-110">
                        Second Semester
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentSchoolYear = '<?php echo $current_school_year; ?>';

        // Helper: format "YYYY-MM-DD" -> "Month DD, YYYY"
        function formatDateToWords(dateString) {
            if (!dateString) return "";

            // Split the date string to avoid timezone issues
            const parts = dateString.split('-');
            if (parts.length !== 3) return dateString;

            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]) - 1; // Months are 0-indexed in JS
            const day = parseInt(parts[2]);

            const date = new Date(year, month, day);

            // Check if date is valid
            if (isNaN(date)) return dateString;

            return date.toLocaleDateString("en-US", {
                year: "numeric",
                month: "long",
                day: "numeric"
            });
        }

        // Helper: convert "Month DD, YYYY" -> "YYYY-MM-DD"
        function parseDateFromWords(dateString) {
            if (!dateString) return "";

            // Try to parse the date string
            const date = new Date(dateString);

            // Check if date is valid
            if (isNaN(date)) return "";

            // Extract date components in local timezone
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        // Get today's date in YYYY-MM-DD format
        function getTodayDate() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // Toggle between text input and date picker
        function toggleDateInput(row, isEditing, isNewEntry = false) {
            const dateDisplays = row.querySelectorAll('.date-display');
            const datePickers = row.querySelectorAll('.date-picker');
            const today = getTodayDate();

            dateDisplays.forEach((display, index) => {
                const picker = datePickers[index];
                
                if (isEditing) {
                    // Show date picker, hide text display
                    display.style.display = 'none';
                    picker.style.display = 'block';
                    picker.disabled = false;
                    
                    // Only apply min date restriction for new entries
                    if (isNewEntry) {
                        picker.setAttribute('min', today);
                    } else {
                        picker.removeAttribute('min'); // Allow past dates in edit mode
                    }
                } else {
                    // Show text display, hide date picker
                    display.style.display = 'block';
                    picker.style.display = 'none';
                    picker.disabled = true;
                }
            });
        }

        // Store original values for cancel functionality
        function storeOriginalValues(row) {
            const dateValues = row.querySelectorAll('.date-value');
            const dateDisplays = row.querySelectorAll('.date-display');
            const descInput = row.querySelector("input[type='text']");
            
            row.dataset.originalStartDate = dateValues[0].value;
            row.dataset.originalEndDate = dateValues[1].value;
            row.dataset.originalStartDisplay = dateDisplays[0].value;
            row.dataset.originalEndDisplay = dateDisplays[1].value;
            row.dataset.originalDescription = descInput.value;
        }

        // Restore original values when canceling
        function restoreOriginalValues(row) {
            const dateValues = row.querySelectorAll('.date-value');
            const dateDisplays = row.querySelectorAll('.date-display');
            const datePickers = row.querySelectorAll('.date-picker');
            const descInput = row.querySelector("input[type='text']");
            
            dateValues[0].value = row.dataset.originalStartDate || '';
            dateValues[1].value = row.dataset.originalEndDate || '';
            dateDisplays[0].value = row.dataset.originalStartDisplay || '';
            dateDisplays[1].value = row.dataset.originalEndDisplay || '';
            datePickers[0].value = row.dataset.originalStartDate || '';
            datePickers[1].value = row.dataset.originalEndDate || '';
            descInput.value = row.dataset.originalDescription || '';
        }

        // Sync date picker value to text display and hidden input
        function syncDateValues(row) {
            const dateValues = row.querySelectorAll('.date-value');
            const dateDisplays = row.querySelectorAll('.date-display');
            const datePickers = row.querySelectorAll('.date-picker');

            datePickers.forEach((picker, index) => {
                if (picker.value) {
                    dateValues[index].value = picker.value;
                    dateDisplays[index].value = formatDateToWords(picker.value);
                }
            });
        }

        // Function to sort table rows by start date
        function sortTableByDate(table) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            // Sort rows by start date, handling empty dates
            rows.sort((a, b) => {
                const dateA = a.querySelector('.date-value').value;
                const dateB = b.querySelector('.date-value').value;
                
                // If both dates are empty, maintain original order
                if (!dateA && !dateB) return 0;
                
                // Empty dates go to the end
                if (!dateA) return 1;
                if (!dateB) return -1;
                
                // Compare actual dates
                const parsedDateA = new Date(dateA);
                const parsedDateB = new Date(dateB);
                
                return parsedDateA - parsedDateB;
            });

            // Clear tbody and re-append sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        }

        // Simple notification system using Tailwind
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');

            // Create notification element
            const notification = document.createElement('div');

            // Set classes based on type
            let bgColor = 'bg-green-500';
            let icon = 'fa-check-circle';

            if (type === 'error') {
                bgColor = 'bg-red-500';
                icon = 'fa-exclamation-circle';
            } else if (type === 'info') {
                bgColor = 'bg-blue-500';
                icon = 'fa-info-circle';
            }

            notification.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2 transform transition-transform duration-300 translate-x-full`;

            // Set notification content
            notification.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
                <button class="ml-auto text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            `;

            // Add to container
            container.appendChild(notification);

            // Trigger animation
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 10);

            // Add close functionality
            notification.querySelector('button').addEventListener('click', () => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Add new row
        document.querySelectorAll(".add-row").forEach(button => {
            button.addEventListener("click", () => {
                const semester = button.dataset.semester;
                const tbody = document.querySelector(`table[data-semester='${semester}'] tbody`);
                const row = document.createElement("tr");
                const today = getTodayDate();
                
                row.innerHTML = `
                    <td>
                        <input type="hidden" class="date-value" />
                        <input type="date" class="date-picker" min="${today}" />
                        <input type="text" class="date-display" placeholder="Start Date (e.g., April 5, 2025)" style="display: none;" />
                    </td>
                    <td>
                        <input type="hidden" class="date-value" />
                        <input type="date" class="date-picker" min="${today}" />
                        <input type="text" class="date-display" placeholder="End Date (optional)" style="display: none;" />
                    </td>
                    <td><input type="text" /></td>
                    <td class="text-center space-x-2">
                        <button class="save-row px-3 py-1 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110" title="Save">
                            <i class="fas fa-save fa-sm mr-1"></i>Save
                        </button>
                        <button class="cancel-row px-3 py-1 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="Cancel">
                            <i class="fas fa-times fa-sm mr-1"></i>Cancel
                        </button>
                        <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Delete">
                            <i class="fas fa-trash fa-sm mr-1"></i>Delete
                        </button>
                    </td>
                `;
                tbody.appendChild(row);

                // Add event listeners for the new date pickers
                const datePickers = row.querySelectorAll('.date-picker');
                datePickers.forEach(picker => {
                    picker.addEventListener('change', () => syncDateValues(row));
                });
            });
        });

        // Handle row actions
        document.addEventListener("click", e => {
            const target = e.target.closest("button");
            if (!target) return;

            const row = target.closest("tr");
            const id = row.dataset.id || null;
            const semester = row.closest("table").dataset.semester;

            // Get input fields
            const startValueInput = row.querySelectorAll("input.date-value")[0];
            const endValueInput = row.querySelectorAll("input.date-value")[1];
            const startDisplayInput = row.querySelectorAll("input.date-display")[0];
            const endDisplayInput = row.querySelectorAll("input.date-display")[1];
            const startPickerInput = row.querySelectorAll("input.date-picker")[0];
            const endPickerInput = row.querySelectorAll("input.date-picker")[1];
            const descInput = row.querySelector("input:not(.date-value):not(.date-display):not(.date-picker)");

            // Save
            if (target.classList.contains("save-row")) {
                // Sync date picker values to hidden inputs and display inputs
                if (startPickerInput && startPickerInput.value) {
                    startValueInput.value = startPickerInput.value;
                    startDisplayInput.value = formatDateToWords(startPickerInput.value);
                }
                if (endPickerInput && endPickerInput.value) {
                    endValueInput.value = endPickerInput.value;
                    endDisplayInput.value = formatDateToWords(endPickerInput.value);
                }

                // Get values for validation
                const start_date = startValueInput.value || parseDateFromWords(startDisplayInput.value);
                const end_date = endValueInput.value || parseDateFromWords(endDisplayInput.value);
                const description = descInput.value;

                if (!start_date || !description) {
                    showNotification("Please fill in start date and description. End date is optional.", "error");
                    return;
                }

                // If end date is provided, validate it
                if (end_date && new Date(end_date) < new Date(start_date)) {
                    showNotification("End date must be after start date.", "error");
                    return;
                }

                // If no end date provided, use start date as end date
                const final_end_date = end_date || start_date;

                // Check if dates are in the past ONLY for new entries (no ID and not predefined)
                const today = getTodayDate();
                const isNewEntry = !id && !row.hasAttribute('data-predefined');
                
                if (isNewEntry) {
                    if (start_date < today) {
                        showNotification("Start date cannot be in the past.", "error");
                        return;
                    }
                    if (final_end_date < today) {
                        showNotification("End date cannot be in the past.", "error");
                        return;
                    }
                }

                fetch('CEIT_Modules/Calendar/Calendar.php', {
                        method: "POST",
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: "save",
                            entry: {
                                id,
                                semester,
                                start_date,
                                end_date: final_end_date,
                                description,
                                school_year: currentSchoolYear
                            }
                        })
                    })
                    .then(res => res.json())
                    .then(response => {
                        if (response.success) {
                            if (response.id) row.dataset.id = response.id;

                            // Update hidden inputs with the actual date values
                            startValueInput.value = start_date;
                            endValueInput.value = final_end_date;

                            // Format and update display inputs
                            startDisplayInput.value = formatDateToWords(start_date);
                            endDisplayInput.value = formatDateToWords(final_end_date);

                            // Switch back to text display mode
                            toggleDateInput(row, false);
                            
                            // Disable description input
                            descInput.disabled = true;

                            // Change buttons based on whether it's predefined
                            if (row.hasAttribute('data-predefined')) {
                                target.closest('td').innerHTML = `
                                    <button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit">
                                        <i class="fas fa-pen fa-sm mr-1"></i>Edit
                                    </button>
                                    <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Reset">
                                        <i class="fas fa-undo fa-sm mr-1"></i>Reset
                                    </button>
                                `;
                            } else {
                                target.outerHTML = `<button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit"><i class="fas fa-pen fa-sm mr-1"></i>Edit</button>`;
                            }

                            // Sort the table by start date
                            const table = row.closest('table');
                            sortTableByDate(table);

                            showNotification("Calendar entry saved successfully.");
                            
                            // Refresh preview in real-time if it's showing the current school year
                            const previewSelect = document.getElementById('preview-school-year-select');
                            if (previewSelect && previewSelect.value === currentSchoolYear) {
                                console.log('Updating preview after save for school year:', currentSchoolYear);
                                // Force a small delay to ensure database is updated
                                setTimeout(() => {
                                    loadPreviewForYear(currentSchoolYear);
                                }, 200);
                            } else {
                                console.log('Preview not updated - different school year or no select element');
                                console.log('Preview select:', previewSelect);
                                console.log('Preview select value:', previewSelect ? previewSelect.value : 'N/A');
                                console.log('Current school year:', currentSchoolYear);
                            }
                        } else {
                            showNotification("Save failed: " + (response.error || 'Unknown error'), "error");
                        }
                    })
                    .catch(() => showNotification("Error saving row.", "error"));
            }

            // Edit
            if (target.classList.contains("edit-row")) {
                // Store original values for potential cancel
                storeOriginalValues(row);
                
                // Switch to date picker mode (not a new entry, so allow past dates)
                toggleDateInput(row, true, false);
                descInput.disabled = false;
                
                // Change buttons to Save, Cancel, Delete
                target.closest('td').innerHTML = `
                    <button class="save-row px-3 py-1 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110" title="Save">
                        <i class="fas fa-save fa-sm mr-1"></i>Save
                    </button>
                    <button class="cancel-row px-3 py-1 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110" title="Cancel">
                        <i class="fas fa-times fa-sm mr-1"></i>Cancel
                    </button>
                    <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Delete">
                        <i class="fas fa-trash fa-sm mr-1"></i>Delete
                    </button>
                `;
                
                // Add event listeners for the date pickers
                const datePickers = row.querySelectorAll('.date-picker');
                datePickers.forEach(picker => {
                    picker.addEventListener('change', () => syncDateValues(row));
                });
            }

            // Cancel
            if (target.classList.contains("cancel-row")) {
                // Check if this is a new row (no ID and not predefined) - if so, remove it entirely
                if (!id && !row.hasAttribute('data-predefined')) {
                    row.remove();
                    return;
                }
                
                // For existing rows and predefined rows, restore original values
                restoreOriginalValues(row);
                
                // Switch back to text display mode
                toggleDateInput(row, false);
                
                // Disable description input
                descInput.disabled = true;

                // For predefined rows, keep delete button enabled but change text to "Reset"
                if (row.hasAttribute('data-predefined')) {
                    target.closest('td').innerHTML = `
                        <button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit">
                            <i class="fas fa-pen fa-sm mr-1"></i>Edit
                        </button>
                        <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Reset">
                            <i class="fas fa-undo fa-sm mr-1"></i>Reset
                        </button>
                    `;
                } else {
                    // For regular rows, enable delete button
                    target.closest('td').innerHTML = `
                        <button class="edit-row px-3 py-1 border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110" title="Edit">
                            <i class="fas fa-pen fa-sm mr-1"></i>Edit
                        </button>
                        <button class="delete-row px-3 py-1 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition duration-200 transform hover:scale-110" title="Delete">
                            <i class="fas fa-trash fa-sm mr-1"></i>Delete
                        </button>
                    `;
                }
            }

            // Delete
            if (target.classList.contains("delete-row")) {
                // Check if this is a predefined row
                if (row.hasAttribute('data-predefined')) {
                    // For predefined rows, reset them to empty instead of deleting
                    if (!confirm("Are you sure you want to reset this predefined entry?")) return;
                    
                    if (id) {
                        // Delete from database if it exists
                        fetch('CEIT_Modules/Calendar/Calendar.php', {
                                method: "POST",
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    action: "delete",
                                    entry: {
                                        id: parseInt(id)
                                    }
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    // Reset the predefined row to empty state
                                    row.removeAttribute('data-id');
                                    const dateValues = row.querySelectorAll('.date-value');
                                    const dateDisplays = row.querySelectorAll('.date-display');
                                    const datePickers = row.querySelectorAll('.date-picker');
                                    
                                    dateValues.forEach(input => input.value = '');
                                    dateDisplays.forEach(input => input.value = '');
                                    datePickers.forEach(input => input.value = '');
                                    
                                    showNotification("Predefined calendar entry reset successfully.");
                                    
                                    // Update preview in real-time if it's showing the current school year
                                    const previewSelect = document.getElementById('preview-school-year-select');
                                    if (previewSelect && previewSelect.value === currentSchoolYear) {
                                        console.log('Updating preview after reset for school year:', currentSchoolYear);
                                        loadPreviewForYear(currentSchoolYear);
                                    }
                                } else {
                                    showNotification("Reset failed.", "error");
                                }
                            })
                            .catch(err => showNotification("Error resetting row: " + err.message, "error"));
                    } else {
                        // Just reset the empty predefined row
                        const dateValues = row.querySelectorAll('.date-value');
                        const dateDisplays = row.querySelectorAll('.date-display');
                        const datePickers = row.querySelectorAll('.date-picker');
                        
                        dateValues.forEach(input => input.value = '');
                        dateDisplays.forEach(input => input.value = '');
                        datePickers.forEach(input => input.value = '');
                        
                        showNotification("Predefined entry reset.");
                        
                        // Update preview in real-time if it's showing the current school year
                        const previewSelect = document.getElementById('preview-school-year-select');
                        if (previewSelect && previewSelect.value === currentSchoolYear) {
                            console.log('Updating preview after reset (no DB) for school year:', currentSchoolYear);
                            loadPreviewForYear(currentSchoolYear);
                        }
                    }
                    return;
                }
                
                // For regular rows, delete normally
                if (!confirm("Are you sure you want to delete this entry?")) return;
                if (!id) {
                    row.remove();
                    return;
                }

                fetch('CEIT_Modules/Calendar/Calendar.php', {
                        method: "POST",
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: "delete",
                            entry: {
                                id: parseInt(id)
                            }
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            row.remove();
                            showNotification("Calendar entry deleted successfully.");
                            
                            // Update preview in real-time if it's showing the current school year
                            const previewSelect = document.getElementById('preview-school-year-select');
                            if (previewSelect && previewSelect.value === currentSchoolYear) {
                                console.log('Updating preview after delete for school year:', currentSchoolYear);
                                loadPreviewForYear(currentSchoolYear);
                            }
                        } else {
                            showNotification("Delete failed.", "error");
                        }
                    })
                    .catch(err => showNotification("Error deleting row: " + err.message, "error"));
            }
        });

        // Preview school year dropdown handler - improved for dashboard compatibility
        function initializePreviewDropdown() {
            const previewSelect = document.getElementById('preview-school-year-select');
            console.log('Initializing preview dropdown, element found:', !!previewSelect);
            
            if (previewSelect) {
                // Remove any existing event listeners by cloning
                const newSelect = previewSelect.cloneNode(true);
                previewSelect.parentNode.replaceChild(newSelect, previewSelect);
                
                // Add new event listener
                newSelect.addEventListener('change', function() {
                    const selectedYear = this.value;
                    console.log('Preview dropdown changed to:', selectedYear);
                    loadPreviewForYear(selectedYear);
                });
                
                console.log('Preview dropdown event listener attached');
                return true;
            }
            return false;
        }

        // Function to load preview data for a specific school year
        function loadPreviewForYear(schoolYear) {
            // Show loading state
            const previewContent = document.getElementById('preview-content');
            if (previewContent) {
                previewContent.innerHTML = `
                    <div class="text-center py-8">
                        <div class="loading-spinner mx-auto mb-4"></div>
                        <p class="text-gray-600">Loading calendar data for ${schoolYear}...</p>
                    </div>
                `;
            }
            
            // Load preview data via AJAX
            // Determine the correct URL based on current context
            let ajaxUrl;
            if (window.location.pathname.includes('Main_dashboard.php')) {
                // Called from dashboard
                ajaxUrl = 'CEIT_Modules/Calendar/Calendar.php';
            } else if (window.location.pathname.includes('CEIT_Modules/Calendar/')) {
                // Called directly from calendar module
                ajaxUrl = 'Calendar.php';
            } else {
                // Fallback
                ajaxUrl = 'CEIT_Modules/Calendar/Calendar.php';
            }
            
            console.log('Current URL:', window.location.pathname);
            console.log('Using AJAX URL:', ajaxUrl);
            console.log('Requesting data for school year:', schoolYear);
            
            fetch(ajaxUrl, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: "load_preview",
                    school_year: schoolYear
                })
            })
            .then(res => {
                console.log('Preview response status:', res.status);
                return res.json();
            })
            .then(response => {
                console.log('Preview response:', response);
                if (response.success) {
                    // Update the preview content
                    if (previewContent) {
                        previewContent.innerHTML = response.html;
                        
                        // Update the preview school year display
                        const previewSchoolYearSpan = document.getElementById('preview-school-year');
                        if (previewSchoolYearSpan) {
                            previewSchoolYearSpan.textContent = schoolYear;
                        }
                        
                        // Re-initialize the semester view
                        setTimeout(() => {
                            showPreviewSemester(1);
                        }, 100);
                    }
                    
                    if (schoolYear !== currentSchoolYear) {
                        showNotification('Preview loaded for ' + schoolYear, 'info');
                    } else {
                        console.log('Preview updated for current school year');
                    }
                } else {
                    console.error('Preview load failed:', response);
                    if (previewContent) {
                        previewContent.innerHTML = `
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-exclamation-triangle fa-3x mb-4"></i>
                                <p class="text-lg">Failed to load preview data</p>
                                <p class="text-sm">Please try again</p>
                            </div>
                        `;
                    }
                    showNotification('Failed to load preview data', 'error');
                }
            })
            .catch(error => {
                console.error('Preview load error:', error);
                console.error('Error details:', error.stack);
                if (previewContent) {
                    previewContent.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-exclamation-triangle fa-3x mb-4"></i>
                            <p class="text-lg">Error loading preview data</p>
                            <p class="text-sm">${error.message}</p>
                        </div>
                    `;
                }
                showNotification('Error loading preview data: ' + error.message, 'error');
            });
        }

        // Initialize calendar when loaded as a module or standalone
        function initializeCalendar() {
            console.log('Initializing calendar module');
            
            // Initialize preview semester display
            setTimeout(function() {
                showPreviewSemester(1); // Show first semester by default
            }, 100);
            
            // Initialize dropdown with retries for dashboard compatibility
            let retryCount = 0;
            const maxRetries = 10;
            const retryInterval = 200;
            
            function tryInitializeDropdown() {
                if (initializePreviewDropdown()) {
                    console.log('Preview dropdown initialized successfully');
                    return;
                }
                
                retryCount++;
                if (retryCount < maxRetries) {
                    console.log(`Preview dropdown not found, retry ${retryCount}/${maxRetries}`);
                    setTimeout(tryInitializeDropdown, retryInterval);
                } else {
                    console.log('Max retries reached for preview dropdown initialization');
                }
            }
            
            tryInitializeDropdown();
        }

        // Multiple initialization strategies for different loading contexts
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCalendar);
        } else {
            // DOM already loaded
            initializeCalendar();
        }

        // Also try when window loads (for dashboard compatibility)
        window.addEventListener('load', function() {
            console.log('Window loaded - trying to initialize calendar again');
            setTimeout(initializeCalendar, 100);
        });

        // Manual initialization function for dashboard compatibility
        window.initializeCalendarPreview = function() {
            console.log('Manual calendar preview initialization called');
            initializeCalendar();
        };

        // Global function to reinitialize calendar (called from dashboard)
        window.reinitializeCalendar = function() {
            console.log('Reinitializing calendar module');
            initializeCalendar();
        };

        // Function to refresh calendar data based on current school year
        function refreshCalendarData() {
            // Show notification and reload after delay (same pattern as Announcements)
            showNotification('Calendar updated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }

        // Preview semester switching
        function showPreviewSemester(semester) {
            // Hide all preview semesters
            document.querySelectorAll('[id^="preview-semester-"]').forEach(el => {
                el.style.display = 'none';
            });

            // Show selected semester
            const semesterElement = document.getElementById(`preview-semester-${semester}`);
            if (semesterElement) {
                semesterElement.style.display = 'block';
            }

            // Update button states
            const btn1 = document.getElementById('preview-btn-sem1');
            const btn2 = document.getElementById('preview-btn-sem2');
            const btnCurrent = document.getElementById(`preview-btn-sem${semester}`);
            
            if (btn1) btn1.classList.remove('active-preview-semester');
            if (btn2) btn2.classList.remove('active-preview-semester');
            if (btnCurrent) btnCurrent.classList.add('active-preview-semester');
        }

        // School Year Management - with delay to ensure elements exist
        setTimeout(function() {
            const editBtn = document.getElementById('edit-school-year-btn');
            const form = document.getElementById('school-year-form');
            const saveBtn = document.getElementById('save-school-year-btn');
            const cancelBtn = document.getElementById('cancel-school-year-btn');
            const newSchoolYearInput = document.getElementById('new-school-year');
            const currentSchoolYearSpan = document.getElementById('current-school-year');
            const previewSchoolYearSpan = document.getElementById('preview-school-year');

            if (!editBtn || !form || !saveBtn || !cancelBtn) {
                console.log('School year management elements not found - this is normal when loaded as a module');
                return;
            }

            editBtn.addEventListener('click', function() {
                form.style.display = 'block';
                newSchoolYearInput.value = currentSchoolYearSpan.textContent;
                newSchoolYearInput.focus();
            });

            cancelBtn.addEventListener('click', function() {
                form.style.display = 'none';
                newSchoolYearInput.value = '';
            });

            saveBtn.addEventListener('click', function() {
                const newSchoolYear = newSchoolYearInput.value.trim();
                
                if (!newSchoolYear) {
                    showNotification('Please enter a valid school year.', 'error');
                    return;
                }

                // Validate format (YYYY-YYYY)
                const yearPattern = /^\d{4}-\d{4}$/;
                if (!yearPattern.test(newSchoolYear)) {
                    showNotification('Please use the format YYYY-YYYY (e.g., 2025-2026)', 'error');
                    return;
                }

                // Send update to server
                fetch('CEIT_Modules/Calendar/Calendar.php', {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: "update_school_year",
                        school_year: newSchoolYear
                    })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        // Update the current school year
                        currentSchoolYear = newSchoolYear;
                        currentSchoolYearSpan.textContent = newSchoolYear;
                        if (previewSchoolYearSpan) previewSchoolYearSpan.textContent = newSchoolYear;
                        
                        // Hide the form
                        form.style.display = 'none';
                        
                        showNotification('School year updated to ' + newSchoolYear + '. Reloading calendar data...');
                        
                        // Reload the page to show filtered data for the new school year
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotification('Failed to update school year.', 'error');
                    }
                })
                .catch(error => {
                    console.error('School year update error:', error);
                    showNotification('Error updating school year.', 'error');
                });
            });
        }, 500);
    </script>
</body>

</html>