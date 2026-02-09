<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Organizational Chart</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'dit-dark': '#fd6b00',
                        'dit-medium': '#fd6b00',
                        'dit-light': '#ffcaa575'
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
    if (isset($_SESSION['user_info']['dept_id'])) {
        $_SESSION['dept_id'] = $_SESSION['user_info']['dept_id'];
    } else {
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
    return $row ? $row['label'] : 'DIT Personnel';
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
                                WHEN role LIKE '%Associate Professor%' THEN
                                    CASE 
                                        WHEN role LIKE '%IV%' THEN 1
                                        WHEN role LIKE '%III%' THEN 2
                                        WHEN role LIKE '%II%' THEN 3
                                        WHEN role LIKE '%I%' THEN 4
                                        ELSE 5
                                    END
                                WHEN role LIKE '%Assistant Professor%' THEN
                                    CASE 
                                        WHEN role LIKE '%IV%' THEN 6
                                        WHEN role LIKE '%III%' THEN 7
                                        WHEN role LIKE '%II%' THEN 8
                                        WHEN role LIKE '%I%' THEN 9
                                        ELSE 10
                                    END
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

    $prefixes = array('Dr.', 'Mr.', 'Mrs.', 'Ms.', 'Prof.');
    $name = str_replace($prefixes, '', $name);
    $name = trim($name);
    $words = explode(' ', $name);
    $words = array_filter($words, function ($word) {
        return !empty($word);
    });
    $words = array_values($words);

    $initials = '';
    $count = count($words);

    if ($count >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1)) . strtoupper(substr($words[$count - 1], 0, 1));
    } else if ($count == 1) {
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

function getPhotoPath($deptAcronym, $photo)
{
    return '../../uploads/' . $deptAcronym . '/OrgChart_Photo/' . $photo;
}

function showViewBox($member, $position_code, $isFaculty = false)
{
    global $conn;

    $colorShade = getColorShade($position_code);

    $defaultRoles = [
        'president' => 'President, CvSU',
        'vice_president' => 'Vice President, OVPAA',
        'college_dean' => 'Dean, CEIT',
        'chairperson' => 'Chairperson, DIT'
    ];

    $defaultRole = isset($defaultRoles[$position_code]) ? $defaultRoles[$position_code] : 'Faculty Member';

    if ($member && in_array($position_code, ['cs_coordinator', 'it_coordinator'])) {
        $academicRank = htmlspecialchars($member['role']);
        $academicRank = str_replace(['CS Coordinator', 'IT Coordinator'], '', $academicRank);
        $academicRank = trim($academicRank, " ,");
        $roleDisplay = $academicRank;
    } else {
        $roleDisplay = htmlspecialchars($member['role'] ?? $defaultRole);
    }

    // Set height based on whether it's faculty or not
    $boxHeight = $isFaculty ? 'h-[28px]' : 'h-[30px]';

    if (!$member) {
        $initials = 'NA';
        $circleContent = "<div class='h-6 w-6 rounded-full border {$colorShade['border']} {$colorShade['bg']} {$colorShade['text']} flex items-center justify-center text-[8px] shadow font-bold'>$initials</div>";

        $boxWidth = $isFaculty ? 'w-full' : 'w-[120px]';

        return "<div class='border {$colorShade['border']} p-0.5 rounded bg-white shadow-sm text-left {$boxHeight} {$boxWidth} flex items-center space-x-1 mb-[-1px]'>
            $circleContent
            <div class='text-[7px] leading-tight flex-1 min-w-0'>
                <strong class='block truncate font-medium'>Full Name</strong>
                <p class='text-gray-600 truncate'>" . htmlspecialchars($defaultRole) . "</p>
            </div>
        </div>";
    }

    if (in_array($position_code, ['cs_coordinator', 'it_coordinator'])) {
        $roleDisplay = $defaultRole . '<br><span class="text-[6px] font-normal">' . $roleDisplay . '</span>';
    }

    if (!empty($member['photo'])) {
        $deptQuery = $conn->prepare("SELECT acronym FROM departments WHERE dept_id = ?");
        $deptQuery->bind_param("i", $_SESSION['dept_id']);
        $deptQuery->execute();
        $deptResult = $deptQuery->get_result();
        $deptRow = $deptResult->fetch_assoc();
        $deptAcronym = $deptRow['acronym'];

        $photoPath = getPhotoPath($deptAcronym, htmlspecialchars($member['photo']));
        $circleContent = "<img src='$photoPath' class='h-6 w-6 rounded-full border {$colorShade['border']} object-cover shadow'>";
    } else {
        $initials = getInitials($member['name']);
        $circleContent = "<div class='h-6 w-6 rounded-full border {$colorShade['border']} {$colorShade['bg']} {$colorShade['text']} flex items-center justify-center text-[8px] shadow font-bold'>$initials</div>";
    }

    $boxWidth = $isFaculty ? 'w-full' : 'w-[120px]';

    // Make the box clickable - add photo path to member data
    $memberDataForModal = $member;
    if (!empty($member['photo'])) {
        $deptQuery = $conn->prepare("SELECT acronym FROM departments WHERE dept_id = ?");
        $deptQuery->bind_param("i", $_SESSION['dept_id']);
        $deptQuery->execute();
        $deptResult = $deptQuery->get_result();
        $deptRow = $deptResult->fetch_assoc();
        $deptAcronym = $deptRow['acronym'];
        $memberDataForModal['photo_path'] = getPhotoPath($deptAcronym, $member['photo']);
    }
    
    $memberData = htmlspecialchars(json_encode($memberDataForModal), ENT_QUOTES, 'UTF-8');
    
    return "<div class='border {$colorShade['border']} p-0.5 rounded bg-white shadow-sm text-left {$boxHeight} {$boxWidth} flex items-center space-x-1 mb-[-1px] cursor-pointer hover:shadow-md hover:scale-105 transition-all duration-200 person-box' 
                 data-member='" . $memberData . "' 
                 data-position='" . htmlspecialchars($position_code) . "'>
        $circleContent
        <div class='text-[7px] leading-tight flex-1 min-w-0'>
            <strong class='block truncate font-medium'>" . htmlspecialchars($member['name']) . "</strong>
            <p class='text-gray-600 truncate'>" . $roleDisplay . "</p>
        </div>
    </div>";
}
?>

<!-- Organizational Chart Content -->
<div class="w-full h-full text-center org-container" id="orgChartContainer" style="max-height: 540px; overflow-y: auto;">
    <div class="w-full h-full">
        <div class="scale-wrapper">
            <div class="org-chart border border-dit-dark rounded-lg p-2 bg-dit-medium/15 space-y-2">

                <!-- Top Management -->
                <div>
                    <div class="flex flex-col items-center space-y-1">
                        <?php
                        $positions = ['president', 'vice_president', 'college_dean', 'chairperson'];
                        foreach ($positions as $pos) {
                            $member = getMember($pos, $conn);
                            echo showViewBox($member, $pos);
                        }
                        ?>
                    </div>
                </div>

                <!-- Program Coordinators -->
                <div>
                    <p class="text-center font-bold bg-dit-medium text-white rounded text-sm mb-0 p-1">
                        <?php echo htmlspecialchars(getOrganizationLabel('label1', $_SESSION['dept_id'], $conn)); ?>
                    </p>
                </div>

                <!-- Dynamic Coordinators Grid -->
                <?php
                $coordinators = getCoordinators($conn);
                $coordinatorCount = count($coordinators);
                ?>
                <div class="coor-box grid grid-cols-<?php echo min($coordinatorCount, 5); ?> gap-2 justify-items-center">
                    <?php
                    foreach ($coordinators as $coordinator) {
                        echo showViewBox($coordinator, $coordinator['position_code']);
                    }
                    ?>
                </div>

                <!-- Faculty Members Section -->
                <div>
                    <p class="text-center font-bold bg-dit-medium text-white rounded text-sm mb-0 p-1">
                        <?php echo htmlspecialchars(getOrganizationLabel('label2', $_SESSION['dept_id'], $conn)); ?>
                    </p>
                </div>

                <div class="grid grid-cols-<?php echo min($coordinatorCount, 5); ?> gap-2">
                    <?php
                    foreach ($coordinators as $coordinator) {
                        $unit = str_replace('coordinator_', '', $coordinator['position_code']);

                        echo "<div class='border border-dit-medium rounded p-1 bg-white'>";

                        $facultyMembers = getFacultyMembers($unit, $conn);

                        echo "<div class='grid grid-cols-2 gap-1'>";

                        foreach ($facultyMembers as $member) {
                            echo showViewBox($member, $member['position_code'], true);
                        }

                        echo "</div>";
                        echo "</div>";
                    }
                    ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Person Details Modal -->
<div id="personModal" class="fixed inset-0 hidden z-50 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-full max-w-lg shadow-2xl">
        
        <div class="flex flex-col items-center">
            <!-- Photo -->
            <div id="modalPhotoContainer" class="mb-4">
                <img id="modalPhoto" src="" class="h-32 w-32 rounded-full border-4 border-dit-dark object-cover shadow-lg hidden">
                <div id="modalInitials" class="h-32 w-32 rounded-full border-4 border-dit-dark bg-dit-dark text-white flex items-center justify-center text-4xl shadow-lg font-bold hidden"></div>
            </div>
            
            <!-- Details -->
            <div class="w-full space-y-3">
                <div class="border-b pb-2">
                    <label class="text-sm font-semibold text-gray-600">Name</label>
                    <p id="modalPersonName" class="text-lg font-medium text-gray-800"></p>
                </div>
                
                <div class="border-b pb-2">
                    <label class="text-sm font-semibold text-gray-600">Role/Position</label>
                    <p id="modalRole" class="text-lg text-gray-800"></p>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end mt-6">
            <button onclick="closePersonModal()" class="px-6 py-2 bg-dit-dark text-white rounded hover:bg-dit-medium transition duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<style>
    .org-container {
        max-width: 100%;
        overflow-y: auto;
        overflow-x: hidden;
        /* Hide scrollbar for Chrome, Safari and Opera */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;  /* IE and Edge */
    }

    .org-container::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }

    .org-chart {
        min-width: 700px;
        transform: scale(0.95);
        transform-origin: top center;
    }

    @media (max-width: 1280px) {
        .org-chart {
            min-width: 650px;
            transform: scale(0.9);
        }
    }

    @media (max-width: 1024px) {
        .org-chart {
            min-width: 600px;
            transform: scale(0.85);
        }
    }

    @media (max-width: 768px) {
        .org-chart {
            min-width: 550px;
            transform: scale(0.8);
        }
    }
</style>

<script>
    function openPersonModal(memberData, positionCode) {
        const modal = document.getElementById('personModal');
        
        // Set name
        document.getElementById('modalPersonName').textContent = memberData.name || 'N/A';
        
        // Set role
        document.getElementById('modalRole').textContent = memberData.role || 'N/A';
        
        // Handle photo or initials
        const modalPhoto = document.getElementById('modalPhoto');
        const modalInitials = document.getElementById('modalInitials');
        
        if (memberData.photo_path && memberData.photo_path.trim() !== '') {
            // Show photo
            modalPhoto.src = memberData.photo_path;
            modalPhoto.classList.remove('hidden');
            modalInitials.classList.add('hidden');
        } else {
            // Show initials
            const initials = getInitials(memberData.name);
            modalInitials.textContent = initials;
            modalInitials.classList.remove('hidden');
            modalPhoto.classList.add('hidden');
        }
        
        modal.classList.remove('hidden');
    }

    function closePersonModal() {
        document.getElementById('personModal').classList.add('hidden');
    }

    function getInitials(name) {
        if (!name) return "N/A";
        
        const prefixes = ['Dr.', 'Mr.', 'Mrs.', 'Ms.', 'Prof.'];
        prefixes.forEach(prefix => {
            name = name.replace(prefix, '');
        });
        
        name = name.trim();
        const words = name.split(' ').filter(word => word.length > 0);
        
        if (words.length >= 2) {
            return words[0][0].toUpperCase() + words[words.length - 1][0].toUpperCase();
        } else if (words.length === 1) {
            return words[0].substring(0, 2).toUpperCase();
        }
        
        return "N/A";
    }

    // Event delegation for person boxes
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('orgChartContainer');
        
        container.addEventListener('click', function(event) {
            const personBox = event.target.closest('.person-box');
            
            if (personBox) {
                const memberDataStr = personBox.getAttribute('data-member');
                const positionCode = personBox.getAttribute('data-position');
                
                try {
                    const memberData = JSON.parse(memberDataStr);
                    openPersonModal(memberData, positionCode);
                } catch (e) {
                    console.error('Error parsing member data:', e);
                }
            }
        });
    });

    // Close modal when clicking outside
    document.getElementById('personModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closePersonModal();
        }
    });
</script>

</body>
</html>
