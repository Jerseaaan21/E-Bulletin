<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizational Chart</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'dit-dark': '#ff6a00ff',
                        'dit-medium': '#fd6b00',
                        'dit-light': '#e7a06dff'
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">

<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if database connection exists, if not include it
if (!isset($conn)) {
    include '../../db.php';
}

// Ensure department ID is available in session
if (!isset($_SESSION['dept_id'])) {
    // Try to get it from user_info if available
    if (isset($_SESSION['user_info']['dept_id'])) {
        $_SESSION['dept_id'] = $_SESSION['user_info']['dept_id'];
    } else {
        // If still not available, show error and exit
        echo "<div class='alert alert-danger'>Error: Department ID not found in session. Please log in again.</div>";
        exit;
    }
}

// Function to get the label from the database
function getOrganizationLabel($position, $department_id, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM organization_label WHERE position = ? AND department_id = ?");
    $stmt->bind_param("si", $position, $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['label'] : 'DIT Personnel'; // Default if not found
}

function getMember($code, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM ceit_organization WHERE position_code = ? AND department_id = ?");
    $stmt->bind_param("si", $code, $_SESSION['dept_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getFacultyMembers($unit, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM ceit_organization WHERE position_code LIKE ? AND department_id = ? ORDER BY 
                            CASE 
                                -- Associate Professor (highest rank)
                                WHEN role LIKE '%Associate Professor%' THEN
                                    CASE 
                                        WHEN role LIKE '%IV%' THEN 1
                                        WHEN role LIKE '%III%' THEN 2
                                        WHEN role LIKE '%II%' THEN 3
                                        WHEN role LIKE '%I%' THEN 4
                                        ELSE 5
                                    END
                                -- Assistant Professor (2nd rank)
                                WHEN role LIKE '%Assistant Professor%' THEN
                                    CASE 
                                        WHEN role LIKE '%IV%' THEN 6
                                        WHEN role LIKE '%III%' THEN 7
                                        WHEN role LIKE '%II%' THEN 8
                                        WHEN role LIKE '%I%' THEN 9
                                        ELSE 10
                                    END
                                -- Instructor (lowest rank)
                                WHEN role LIKE '%Instructor%' THEN
                                    CASE 
                                        WHEN role LIKE '%IV%' THEN 11
                                        WHEN role LIKE '%III%' THEN 12
                                        WHEN role LIKE '%II%' THEN 13
                                        WHEN role LIKE '%I%' THEN 14
                                        ELSE 15
                                    END
                                ELSE 16
                            END, id ASC");
    $pattern = $unit . '_faculty_%';
    $stmt->bind_param("si", $pattern, $_SESSION['dept_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    return $members;
}

// New function to get all coordinators dynamically
function getCoordinators($conn)
{
    $stmt = $conn->prepare("SELECT * FROM ceit_organization WHERE position_code LIKE 'coordinator_%' AND department_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $_SESSION['dept_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $coordinators = [];
    while ($row = $result->fetch_assoc()) {
        $coordinators[] = $row;
    }
    return $coordinators;
}

function getInitials($name)
{
    if (empty($name)) return "N/A";

    // Remove any prefixes like Dr., Mr., Mrs., etc.
    $prefixes = array('Dr.', 'Mr.', 'Mrs.', 'Ms.', 'Prof.');
    $name = str_replace($prefixes, '', $name);

    // Trim and split the name into words
    $name = trim($name);
    $words = explode(' ', $name);

    // Filter out empty words
    $words = array_filter($words, function ($word) {
        return !empty($word);
    });

    // Reset array keys
    $words = array_values($words);

    $initials = '';
    $count = count($words);

    if ($count >= 2) {
        // Use first and last word
        $initials = strtoupper(substr($words[0], 0, 1)) . strtoupper(substr($words[$count - 1], 0, 1));
    } else if ($count == 1) {
        // Only one word, use first two characters if available
        $word = $words[0];
        if (strlen($word) >= 2) {
            $initials = strtoupper(substr($word, 0, 2));
        } else {
            $initials = strtoupper($word);
        }
    } else {
        $initials = "N/A";
    }

    return $initials;
}

function getColorShade($position_code)
{
    if (in_array($position_code, ['president', 'vice_president', 'college_dean', 'chairperson'])) {
        return [
            'bg' => 'bg-[#FF6B00]',
            'text' => 'text-white',
            'border' => 'border-[#FF6B00]'
        ];
    } else if (strpos($position_code, 'coordinator_') === 0 || in_array($position_code, ['cs_coordinator', 'it_coordinator'])) {
        return [
            'bg' => 'bg-[#FF9500]',
            'text' => 'text-white',
            'border' => 'border-[#FF9500]'
        ];
    } else if (strpos($position_code, 'faculty') !== false) {
        return [
            'bg' => 'bg-[#FF9500]',
            'text' => 'text-white',
            'border' => 'border-[#FF9500]'
        ];
    } else {
        return [
            'bg' => 'bg-[#FF9500]',
            'text' => 'text-white',
            'border' => 'border-[#FF9500]'
        ];
    }
}

// Function to get the photo path with the new directory structure
function getPhotoPath($deptAcronym, $photo)
{
    // New directory structure: uploads/OrgChart_Photo/{deptAcronym}/{photo}
    return 'uploads/' . $deptAcronym . '/OrgChart_Photo/' . $photo;
}

function showBox($member, $position_code, $isFaculty = false)
{
    global $conn; // Make $conn available in this function

    $colorShade = getColorShade($position_code);

    $defaultRoles = [
        'president' => 'President, CvSU',
        'vice_president' => 'Vice President, OVPAA',
        'college_dean' => 'Dean, CEIT',
        'chairperson' => 'Chairperson, DIT'
    ];

    $defaultRole = isset($defaultRoles[$position_code]) ? $defaultRoles[$position_code] : 'Faculty Member';

    // For coordinators, we want to show only their academic rank in the role field
    if ($member && in_array($position_code, ['cs_coordinator', 'it_coordinator'])) {
        // Extract just the academic rank part
        $academicRank = htmlspecialchars($member['role']);

        // Remove any coordinator titles that might be included
        $academicRank = str_replace(['CS Coordinator', 'IT Coordinator'], '', $academicRank);
        $academicRank = trim($academicRank, " ,");

        $roleDisplay = $academicRank;
    } else {
        $roleDisplay = htmlspecialchars($member['role'] ?? $defaultRole);
    }

    if (!$member) {
        $initials = 'NA';
        $circleContent = "<div class='h-12 w-12 rounded-full border {$colorShade['border']} {$colorShade['bg']} {$colorShade['text']} flex items-center justify-center text-sm shadow-lg font-bold'>$initials</div>";

        $buttonContainer = "<div class='mt-1 flex space-x-1 text-[11px]'>
                    <button class='p-1 border text-dit-dark border-dit-dark hover:text-white hover:bg-dit-dark rounded transition duration-200 transform hover:scale-110 edit-btn' 
                            data-id='0' 
                            data-name='' 
                            data-role='" . htmlspecialchars($defaultRole) . "' 
                            data-photo='' 
                            data-position='$position_code' 
                            title='Edit Personnel'>
                        <i class=\"fas fa-pen\"></i>
                    </button>
                </div>";

        // Different box dimensions for faculty vs non-faculty
        $boxWidth = $isFaculty ? 'w-full' : 'w-[235px]';

        return "<div class='border {$colorShade['border']} p-1 rounded-md bg-white shadow-md text-left h-[70px] {$boxWidth} flex items-center space-x-2 mb-[-2px]'>
            $circleContent
            <div class='text-[11px] leading-tight flex-1 min-w-0'>
                <strong class='block truncate font-medium'>Full Name</strong>
                <p class='text-gray-600 truncate'>" . htmlspecialchars($defaultRole) . "</p>
                $buttonContainer
            </div>
        </div>";
    }

    $deleteButton = "<button class='p-1 border text-red-600 border-red-600 hover:text-white hover:bg-red-600 rounded transition duration-200 transform hover:scale-110 delete-btn' 
                    data-id='" . $member['id'] . "' 
                    title='Delete'>
                <i class=\"fas fa-trash\"></i>
            </button>";

    $editButton = "<button class='p-1 border text-dit-dark border-dit-dark hover:text-white hover:bg-dit-dark rounded transition duration-200 transform hover:scale-110 edit-btn' 
                    data-id='" . $member['id'] . "' 
                    data-name='" . htmlspecialchars($member['name']) . "' 
                    data-role='" . htmlspecialchars($member['role']) . "' 
                    data-photo='" . htmlspecialchars($member['photo']) . "' 
                    data-position='" . $member['position_code'] . "' 
                    title='Edit Person'>
                <i class=\"fas fa-pen\"></i>
            </button>";

    // For coordinators, we need to show both the fixed title and the academic rank
    if (in_array($position_code, ['cs_coordinator', 'it_coordinator'])) {
        $roleDisplay = $defaultRole . '<br><span class="text-[10px] font-normal">' . $roleDisplay . '</span>';
    }

    if (!empty($member['photo'])) {
        // Get department acronym for photo path
        $deptQuery = $conn->prepare("SELECT acronym FROM departments WHERE dept_id = ?");
        $deptQuery->bind_param("i", $_SESSION['dept_id']);
        $deptQuery->execute();
        $deptResult = $deptQuery->get_result();
        $deptRow = $deptResult->fetch_assoc();
        $deptAcronym = $deptRow['acronym'];

        // Use the new directory structure for the photo path
        $photoPath = getPhotoPath($deptAcronym, htmlspecialchars($member['photo']));
        $circleContent = "<img src='$photoPath' class='h-12 w-12 rounded-full border {$colorShade['border']} object-cover shadow-lg'>";
    } else {
        $initials = getInitials($member['name']);
        $circleContent = "<div class='h-12 w-12 rounded-full border {$colorShade['border']} {$colorShade['bg']} {$colorShade['text']} flex items-center justify-center text-sm shadow-lg font-bold'>$initials</div>";
    }

    // Different box dimensions for faculty vs non-faculty
    $boxWidth = $isFaculty ? 'w-full' : 'w-[235px]';

    return "<div class='border {$colorShade['border']} p-1 rounded-md bg-white shadow-md text-left h-[70px] {$boxWidth} flex items-center space-x-2 mb-[-2px]'>
        $circleContent
        <div class='text-[11px] leading-tight flex-1 min-w-0'>
            <strong class='block truncate font-medium'>" . htmlspecialchars($member['name']) . "</strong>
            <p class='text-gray-600 truncate'>" . $roleDisplay . "</p>
            <div class='mt-1 flex space-x-1 text-[11px]'>
                $editButton
                $deleteButton
            </div>
        </div>
    </div>";
}
?>

<!-- Organizational Chart Content -->
<div class="container w-full mx-auto px-4 py-6 text-center org-container" id="orgChartContainer">
    <div class="aspect-w-16 aspect-h-9">
        <div class="scale-wrapper">
            <div class="org-chart border-2 border-dit-dark rounded-xl p-6 bg-dit-medium/15 shadow-lg space-y-8 ">

                <!-- Top Management - Vertically aligned -->
                <div>
                    <div class="flex flex-col items-center space-y-4">
                        <?php
                        $positions = ['president', 'vice_president', 'college_dean', 'chairperson'];
                        foreach ($positions as $pos) {
                            $member = getMember($pos, $conn);
                            echo showBox($member, $pos); // Default circle size for top management
                        }
                        ?>
                    </div>
                </div>

                <!-- Faculty Members Section with Editable Label -->
                <div class="relative group">
                    <p class="text-center font-bold bg-dit-medium text-white rounded-lg text-2xl mb-0 p-2" id="facultyLabel">
                        <?php echo htmlspecialchars(getOrganizationLabel('label1', $_SESSION['dept_id'], $conn)); ?>
                    </p>
                    <button
                        class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-white bg-dit-medium hover:text-dit-dark hover:bg-white bg-opacity-70 px-2 py-1 rounded-md"
                        onclick="editFacultyLabel()"
                        title="Edit Label">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>

                <!-- Single Faculty Container with 8 columns -->
                <div class="border border-dit-medium rounded-lg p-4 bg-white shadow-sm">
                    <?php
                    // Get all faculty members from all units
                    $stmt = $conn->prepare("SELECT * FROM ceit_organization WHERE position_code LIKE '%faculty%' AND department_id = ? ORDER BY 
                                CASE 
                                    -- Associate Professor (highest rank)
                                    WHEN role LIKE '%Associate Professor%' THEN
                                        CASE 
                                            WHEN role LIKE '%IV%' THEN 1
                                            WHEN role LIKE '%III%' THEN 2
                                            WHEN role LIKE '%II%' THEN 3
                                            WHEN role LIKE '%I%' THEN 4
                                            ELSE 5
                                        END
                                    -- Assistant Professor (2nd rank)
                                    WHEN role LIKE '%Assistant Professor%' THEN
                                        CASE 
                                            WHEN role LIKE '%IV%' THEN 6
                                            WHEN role LIKE '%III%' THEN 7
                                            WHEN role LIKE '%II%' THEN 8
                                            WHEN role LIKE '%I%' THEN 9
                                            ELSE 10
                                        END
                                    -- Instructor (lowest rank)
                                    WHEN role LIKE '%Instructor%' THEN
                                        CASE 
                                            WHEN role LIKE '%IV%' THEN 11
                                            WHEN role LIKE '%III%' THEN 12
                                            WHEN role LIKE '%II%' THEN 13
                                            WHEN role LIKE '%I%' THEN 14
                                            ELSE 15
                                        END
                                    ELSE 16
                                END, id ASC");
                    $stmt->bind_param("i", $_SESSION['dept_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $allFacultyMembers = [];
                    while ($row = $result->fetch_assoc()) {
                        $allFacultyMembers[] = $row;
                    }
                    ?>

                    <!-- 8-column grid for faculty members -->
                    <div class="grid grid-cols-8 gap-3">
                        <?php
                        // Display all faculty members
                        foreach ($allFacultyMembers as $member) {
                            echo showBox($member, $member['position_code'], true);
                        }
                        ?>
                    </div>

                    <!-- Add button for new faculty members -->
                    <div class="flex justify-center mt-3">
                        <button class="border text-dit-dark border-dit-dark hover:text-white hover:bg-dit-dark text-sm px-3 py-1 rounded transition duration-200 transform hover:scale-110 add-faculty-btn" 
                            data-unit="general"
                            title="Add Faculty">
                            <i class="fas fa-user-plus mr-1"></i> Add Faculty
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Member Modal -->
<div id="memberModal" class="fixed inset-0 hidden z-50 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-full max-w-md shadow-2xl">
        <form id="memberForm" method="post" enctype="multipart/form-data">
            <h5 class="text-lg font-bold mb-4 text-black" id="modalTitle">Edit Member</h5>
            <input type="hidden" name="member_id" id="member_id">
            <input type="hidden" name="department_id" id="department_id" value="<?php echo isset($_SESSION['dept_id']) ? $_SESSION['dept_id'] : ''; ?>">
            <div class="mb-3">
                <label class="block text-sm font-medium text-black">Name</label>
                <input type="text" name="name" id="name" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-dit-dark" required>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-black">Role</label>
                <input type="text" name="role" id="role" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-dit-dark" required>
            </div>
            <input type="hidden" name="position_code" id="position_code">
            <div class="mb-3">
                <label class="block text-sm font-medium text-black">Photo</label>
                <input type="file" name="photo" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-dit-dark" id="photoInput">
                <div id="currentPhoto" class="mt-2 text-sm text-gray-500 hidden"></div>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-700 hover:bg-gray-700 hover:text-white rounded text-gray-700 transition duration-200 transform hover:scale-110">Cancel</button>
                <button type="submit" id="submitBtn" class="px-4 py-2 border border-green-600 bg-white text-green-600 rounded hover:bg-green-600 hover:text-white transition duration-200 transform hover:scale-110">
                    <i class="fas fa-save fa-sm"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Label Editing Modal -->
<div id="labelModal" class="fixed inset-0 hidden z-50 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-full max-w-md">
        <form id="labelForm" method="post">
            <h5 class="text-lg font-bold mb-4 text-black" id="labelModalTitle">Edit Label</h5>
            <input type="hidden" name="position" id="labelPosition">
            <input type="hidden" name="department_id" value="<?php echo isset($_SESSION['dept_id']) ? $_SESSION['dept_id'] : ''; ?>">
            <div class="mb-3">
                <label class="block text-sm font-medium text-black">Label Text</label>
                <input type="text" name="label" id="labelInput" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-dit-dark" required>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeLabelModal()" class="px-4 py-2 border border-gray-700 hover:bg-gray-700 hover:text-white rounded text-gray-700 transition duration-200 transform hover:scale-110">Cancel</button>
                <button type="submit" id="labelSubmitBtn" class="px-4 py-2 border border-green-600 bg-white text-green-600 rounded hover:bg-green-600 hover:text-white transition duration-200 transform hover:scale-110">
                    <i class="fas fa-save fa-sm"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notification Toast -->
<div id="notificationToast" class="fixed bottom-4 right-4 bg-dit-dark text-white px-4 py-2 rounded shadow-lg transform transition-transform duration-300 translate-y-20 opacity-0 flex items-center">
    <i class="fas fa-check-circle mr-2"></i>
    <span id="notificationMessage"></span>
</div>

<style>
    /* Custom styles to prevent overflow */
    .org-container {
        max-width: 100%;
        overflow-x: auto;
    }

    .org-chart {
        min-width: 1000px;
    }

    @media (max-width: 1280px) {
        .org-chart {
            min-width: 900px;
        }
    }

    @media (max-width: 1024px) {
        .org-chart {
            min-width: 800px;
        }
    }

    @media (max-width: 768px) {
        .org-chart {
            min-width: 700px;
        }
    }
</style>

<script>
    let isSubmitting = false;

    function reloadOrgChart() {
        fetch('CEIT_Modules/Organizational_Chart/Organizational_Chart.php')
            .then(response => response.text())
            .then(html => {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newOrgChartContainer = tempDiv.querySelector('#orgChartContainer');
                document.getElementById('orgChartContainer').innerHTML = newOrgChartContainer.innerHTML;
                attachEventListeners();
                showNotification('Changes saved successfully!');
            })
            .catch(error => {
                console.error('Error reloading organizational chart:', error);
                showNotification('Error saving changes', 'error');
            });
    }

    function showNotification(message, type = 'success') {
        const toast = document.getElementById("notificationToast");
        const messageElement = document.getElementById("notificationMessage");
        messageElement.textContent = message;
        toast.className = "fixed bottom-4 right-4 px-4 py-2 rounded shadow-lg transform transition-transform duration-300 flex items-center";
        if (type === 'success') {
            toast.classList.add("bg-dit-dark", "text-white");
            toast.innerHTML = '<i class="fas fa-check-circle mr-2"></i><span id="notificationMessage">' + message + '</span>';
        } else if (type === 'error') {
            toast.classList.add("bg-red-500", "text-white");
            toast.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i><span id="notificationMessage">' + message + '</span>';
        }
        toast.classList.remove("translate-y-20", "opacity-0");
        setTimeout(() => {
            toast.classList.add("translate-y-20", "opacity-0");
        }, 3000);
    }

    function openEditModal(id, name, role, photo, position_code) {
        document.getElementById("modalTitle").innerText = id === 0 ? "Add Person" : "Edit Person";
        document.getElementById("member_id").value = id;
        document.getElementById("name").value = name;
        document.getElementById("role").value = role;
        document.getElementById("position_code").value = position_code;

        // Reset the file input and current photo display
        const photoInput = document.getElementById('photoInput');
        photoInput.value = ''; // Clear the file input
        const currentPhoto = document.getElementById("currentPhoto");
        currentPhoto.classList.add("hidden");
        currentPhoto.innerHTML = '';

        // If editing and there is a photo, show the current photo
        if (id !== 0 && photo) {
            currentPhoto.classList.remove("hidden");
            currentPhoto.innerHTML = `Current: ${photo}`;
        }

        document.getElementById("memberModal").classList.remove("hidden");
    }

    function closeModal() {
        document.getElementById("memberModal").classList.add("hidden");
        isSubmitting = false;
        const submitBtn = document.getElementById("submitBtn");
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save fa-sm"></i> Save';
    }

    // Label editing functions
    function editFacultyLabel() {
        const currentLabel = document.getElementById('facultyLabel').textContent;
        document.getElementById('labelInput').value = currentLabel.trim();
        document.getElementById('labelPosition').value = 'label1';
        document.getElementById('labelModalTitle').textContent = 'Edit Faculty Label';
        document.getElementById('labelModal').classList.remove('hidden');
    }

    function closeLabelModal() {
        document.getElementById('labelModal').classList.add('hidden');
    }

    // Event delegation for handling clicks on dynamically added elements
    function handleContainerClick(event) {
        // Edit button
        if (event.target.closest('.edit-btn')) {
            const button = event.target.closest('.edit-btn');
            const id = button.dataset.id;
            const name = button.dataset.name;
            const role = button.dataset.role;
            const photo = button.dataset.photo;
            const position = button.dataset.position;
            openEditModal(id, name, role, photo, position);
        }
        // Delete button
        else if (event.target.closest('.delete-btn')) {
            const button = event.target.closest('.delete-btn');
            const id = button.dataset.id;
            if (confirm("Delete this member?")) {
                fetch('CEIT_Modules/Organizational_Chart/OrgChart_process.php?delete=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            reloadOrgChart();
                        } else {
                            showNotification('Error deleting member', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An error occurred', 'error');
                    });
            }
        }
        // Add faculty button
        else if (event.target.closest('.add-faculty-btn')) {
            const button = event.target.closest('.add-faculty-btn');
            const unit = button.dataset.unit;
            // Generate unique position code with current timestamp
            const position = unit + "_faculty_" + Date.now();
            openEditModal(0, '', '', '', position);
        }
    }

    function attachEventListeners() {
        // Use event delegation for the container
        const container = document.getElementById('orgChartContainer');
        if (container) {
            container.removeEventListener('click', handleContainerClick);
            container.addEventListener('click', handleContainerClick);
        }

        // Form submission handlers
        const memberForm = document.getElementById('memberForm');
        if (memberForm) {
            memberForm.removeEventListener('submit', handleFormSubmit);
            memberForm.addEventListener('submit', handleFormSubmit);
        }

        const labelForm = document.getElementById('labelForm');
        if (labelForm) {
            labelForm.removeEventListener('submit', handleLabelFormSubmit);
            labelForm.addEventListener('submit', handleLabelFormSubmit);
        }
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        if (isSubmitting) {
            return;
        }
        isSubmitting = true;
        const submitBtn = document.getElementById("submitBtn");
        const originalButtonText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const formData = new FormData(this);

        fetch('CEIT_Modules/Organizational_Chart/OrgChart_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Server response:', text); // Debug: Log the response

                if (!text.trim()) {
                    throw new Error('Empty response from server');
                }

                try {
                    // Try to parse the response as JSON
                    const data = JSON.parse(text);
                    if (data.success) {
                        closeModal();
                        reloadOrgChart();
                    } else {
                        // Handle specific error messages
                        if (data.error && data.error.includes('department')) {
                            showNotification('Department error: ' + data.error, 'error');
                        } else {
                            showNotification('Error saving data: ' + (data.error || 'Unknown error'), 'error');
                        }
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalButtonText;
                        isSubmitting = false;
                    }
                } catch (e) {
                    // If JSON parsing fails, log the error and show the raw response
                    console.error('JSON Parse Error:', e);
                    console.error('Raw Response:', text);
                    showNotification('Server returned invalid response. Please check console for details.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalButtonText;
                    isSubmitting = false;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showNotification('Network error: ' + error.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalButtonText;
                isSubmitting = false;
            });
    }

    function handleLabelFormSubmit(e) {
        e.preventDefault();
        const formData = new FormData(this);
        // Trim the label value before sending
        const labelValue = formData.get('label').trim();
        formData.set('label', labelValue);

        fetch('CEIT_Modules/Organizational_Chart/CEIT_Label_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Server response:', text); // Debug: Log the response

                if (!text.trim()) {
                    throw new Error('Empty response from server');
                }

                try {
                    // Try to parse the response as JSON
                    const data = JSON.parse(text);
                    if (data.success) {
                        const position = formData.get('position');
                        if (position === 'label1') {
                            document.getElementById('facultyLabel').textContent = labelValue;
                        }
                        closeLabelModal();
                        showNotification('Label updated successfully!');
                    } else {
                        // Handle specific error messages
                        if (data.error && data.error.includes('department')) {
                            showNotification('Department error: ' + data.error, 'error');
                        } else {
                            showNotification('Error updating label: ' + (data.error || 'Unknown error'), 'error');
                        }
                    }
                } catch (e) {
                    // If JSON parsing fails, log the error and show the raw response
                    console.error('JSON Parse Error:', e);
                    console.error('Raw Response:', text);
                    showNotification('Server returned invalid response. Please check console for details.', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showNotification('Network error: ' + error.message, 'error');
            });
    }

    // Initialize the module with multiple approaches to ensure it works
    function initializeOrgChart() {
        // Try to attach event listeners immediately
        attachEventListeners();

        // Also try after a short delay to ensure DOM is ready
        setTimeout(attachEventListeners, 100);

        // Also try when DOM is fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachEventListeners);
        } else {
            attachEventListeners();
        }
    }

    // Initialize the module
    initializeOrgChart();    // Initialize the module
    initializeOrgChart();
</script>

</body>
</html>