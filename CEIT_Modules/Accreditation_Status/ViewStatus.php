<?php
session_start();
include "../../db.php";

// Fetch all programs from programs_status table, sorted by accreditation level
$result = $conn->query("
    SELECT id, dept_id, program_name, program_code, accreditation_level, accreditation_date 
    FROM programs_status 
    ORDER BY 
        CASE accreditation_level
            WHEN 'Level IV Re-accredited' THEN 4
            WHEN 'Level III Re-accredited' THEN 3
            WHEN 'Level II Re-accredited' THEN 2
            WHEN 'Level I Re-accredited' THEN 1
            ELSE 0
        END DESC,
        program_name ASC
");

if (!$result) {
    die("Query failed: " . $conn->error);
}

$rows = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Accreditation Status</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
</head>
<body class="bg-gray-100 p-1">

<div class="max-w-6xl mx-auto bg-white p-0 rounded-xl space-y-6">

    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded">
            <thead class="bg-orange-200">
                <tr>
                    <th class="px-2 py-2 text-left text-[8px]">Program Name</th>
                    <th class="px-2 py-2 text-left text-[8px]">Program Code</th>
                    <th class="px-2 py-2 text-left text-[8px]">Accreditation Level</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($rows) > 0): ?>
                    <?php foreach($rows as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-1 py-1 text-[8px] text-left"><?= htmlspecialchars($row['program_name']) ?></td>
                            <td class="px-1 py-1 text-[8px] text-left"><?= htmlspecialchars($row['program_code']) ?></td>
                            <td class="px-1 py-1 text-[8px] text-left">
                                <?php if($row['accreditation_level']): ?>
                                    <?php 
                                        $status = $row['accreditation_level'];
                                        $badgeColor = '';

                                        switch($status) {
                                            case 'Level IV Re-accredited':
                                                $badgeColor = 'bg-yellow-400';
                                                break;
                                            case 'Level III Re-accredited':
                                                $badgeColor = 'bg-gray-400'; 
                                                break;
                                            case 'Level II Re-accredited':
                                                $badgeColor = 'bg-orange-500';
                                                break;
                                            case 'Level I Re-accredited':
                                                $badgeColor = 'bg-blue-400';
                                                break;
                                        }
                                    ?>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="inline-block w-2 h-2 rounded-full <?= $badgeColor ?>"></span>
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-center text-gray-500 text-[8px]">No programs found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
