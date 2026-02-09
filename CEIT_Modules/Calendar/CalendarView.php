<?php
include "../../db.php";

// Get header data from ceit_calendar table (semester=0, department_id=1)
 $headerResult = $conn->query("SELECT * FROM ceit_calendar WHERE semester = 0 AND department_id = 1 LIMIT 1");
if ($headerResult && $headerResult->num_rows > 0) {
    $header = $headerResult->fetch_assoc();
    $hasHeader = true;
} else {
    // Set placeholder header if no records found
    $header = [
        'title' => ' ',
        'subtitle' => ' '
    ];
    $hasHeader = false;
}

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

function renderViewingTable($conn, $semester)
{
    $title = $semester === 1 ? 'First Semester' : 'Second Semester';
    $color = $semester === 1 ? 'orange' : 'orange';

    // Only fetch regular entries (semester 1 or 2) and department_id=1
    $query = "SELECT * FROM ceit_calendar WHERE semester = $semester AND department_id = 1 ORDER BY start_date ASC";
    $result = $conn->query($query);

    echo <<<HTML
    <div id="semester-$semester" class="mb-10" style="display: none;">
        <h2 class="text-2xl font-bold text-{$color}-600 mb-4 mt-[-20px]">$title</h2>
        <div class="overflow-x-auto">
            <table class="w-full table-fixed border-collapse">
                <thead class="bg-{$color}-100 text-gray-800 text-sm">
                    <tr>
                        <th class="w-1/3 px-1 py-0.5 border border-gray-300">Date</th>
                        <th class="w-2/3 px-1 py-0.5 border border-gray-300">Event Description</th>
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
                <td class="px-1 py-0.5 border border-gray-300 text-[8px] break-words">$formattedDate</td>
                <td class="px-1 py-0.5 border border-gray-300 text-[8px] break-words">$desc</td>
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
    <title>University Calendar View</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        table {
            width: 100%;
            table-layout: fixed;
        }

        .active-semester {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: translateY(-2px);
        }
        
        .placeholder-text {
            color: #a0a0a0;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col justify-between">
    <div class="max-w-md mx-auto bg-white p-2 rounded-lg shadow-md">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold text-orange-500 <?php echo !$hasHeader ? 'placeholder-text' : ''; ?>">
                <?php echo htmlspecialchars($header['title']); ?>
            </h1>
            <h2 class="text-lg text-orange-400 <?php echo !$hasHeader ? 'placeholder-text' : ''; ?>">
                <?php echo htmlspecialchars($header['subtitle']); ?>
            </h2>
        </div>

        <?php renderViewingTable($conn, 1); ?>
        <?php renderViewingTable($conn, 2); ?>

        <div class="text-center text-sm text-gray-500 mt-[-30px]">
            Last updated: <?php echo date('F j, Y'); ?>
        </div>
    </div>
    <!-- Semester Buttons -->
    <div class="flex justify-center gap-4 mt-2">
        <button id="btn-sem1" onclick="showSemester(1)"
            class="px-1 py-0.5 text-sm bg-orange-500 text-white rounded hover:bg-orange-600 transition duration-200 transform hover:scale-110 active-semester">
            First Semester
        </button>
        <button id="btn-sem2" onclick="showSemester(2)"
            class="px-1 py-0.5 text-sm bg-orange-500 text-white rounded hover:bg-orange-600 transition duration-200 transform hover:scale-110">
            Second Semester
        </button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showSemester(1);
        });

        function showSemester(semester) {
            document.querySelectorAll('[id^="semester-"]').forEach(el => {
                el.style.display = 'none';
            });

            document.getElementById(`semester-${semester}`).style.display = 'block';

            document.getElementById('btn-sem1').classList.remove('active-semester');
            document.getElementById('btn-sem2').classList.remove('active-semester');
            document.getElementById(`btn-sem${semester}`).classList.add('active-semester');
        }
    </script>
</body>

</html>