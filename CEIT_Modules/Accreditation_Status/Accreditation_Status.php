<?php
session_start();
include "../../db.php";

// Get department ID from session (assuming it's stored when user logs in)
$dept_id = $_SESSION['dept_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['action'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid request"]);
        exit;
    }

    $action = $data['action'];
    $entry = $data['entry'] ?? null;

    header('Content-Type: application/json');

    if ($action === "update" && isset($entry['id'])) {
        $id = intval($entry['id']);
        $status = isset($entry['status']) && $entry['status'] !== '' ? $conn->real_escape_string($entry['status']) : null;
        $date = isset($entry['date']) && $entry['date'] !== '' ? $conn->real_escape_string($entry['date']) : null;
        
        $statusSQL = $status ? "'$status'" : "NULL";
        $dateSQL = $date ? "'$date'" : "NULL";
        
        $sql = "UPDATE programs_status 
                SET accreditation_level=$statusSQL, accreditation_date=$dateSQL 
                WHERE id=$id";
        
        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        }
        exit;
    }

    echo json_encode(["error" => "Unknown action"]);
    exit;
}

// Fetch programs from programs_status table based on department, sorted by level
// For now, fetch all programs (remove dept_id filter for debugging)
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

// Debug: Check if query was successful
if (!$result) {
    die("Query failed: " . $conn->error);
}

$rows = $result->fetch_all(MYSQLI_ASSOC);

$statusOptions = [
    "Level I Re-accredited",
    "Level II Re-accredited",
    "Level III Re-accredited",
    "Level IV Re-accredited"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Accreditation Status</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
</head>
<body class="bg-gray-100 p-6">
<!-- Notification Container -->
<div id="notification-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow space-y-6">
    <div class="flex items-center mb-4">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center mr-3">
            <i class="fas fa-award text-orange-600"></i>
        </div>
        <div>
            <h3 class="text-lg lg:text-xl font-bold text-orange-600">Accreditation Status</h3>
            <p class="text-sm text-gray-600">Click Edit to add accreditation level and date to programs</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <?php if (empty($rows)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>No programs found in the database.</p>
                <p class="text-sm">Debug: dept_id = <?= $dept_id ?? 'NULL' ?>, Total rows: <?= count($rows) ?></p>
            </div>
        <?php endif; ?>
        
        <table class="min-w-full border border-gray-200 rounded" id="accreditationTable">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2 text-left">Program Name</th>
                    <th class="px-4 py-2 text-left">Program Code</th>
                    <th class="px-4 py-2 text-left">Accreditation Level</th>
                    <th class="px-4 py-2 text-left">Accreditation Date</th>
                    <th class="px-4 py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr data-id="<?= $row['id'] ?>" class="hover:bg-gray-50">
                        <td class="px-4 py-2"><?= htmlspecialchars($row['program_name']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['program_code']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['accreditation_level'] ?? 'N/A') ?></td>
                        <td class="px-4 py-2"><?= $row['accreditation_date'] ? date('M d, Y', strtotime($row['accreditation_date'])) : 'N/A' ?></td>
                        <td class="px-4 py-2 text-center space-x-2">
                            <button 
                                class="edit p-2 text-sm border border-blue-500 text-blue-500 rounded-lg hover:bg-blue-500 hover:text-white transition duration-200 transform hover:scale-110"
                                data-id="<?= $row['id'] ?>"
                                data-program-name="<?= htmlspecialchars($row['program_name']) ?>"
                                data-program-code="<?= htmlspecialchars($row['program_code']) ?>"
                                data-status="<?= htmlspecialchars($row['accreditation_level'] ?? '') ?>"
                                data-date="<?= htmlspecialchars($row['accreditation_date'] ?? '') ?>"
                            ><i class="fas fa-pen"></i> Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
        <button id="closeModal" class="absolute top-2 right-2 text-gray-600 hover:text-gray-800"><i class="fas fa-times"></i></button>
        <h2 class="text-xl font-bold mb-4">Update Accreditation Status</h2>

        <input type="hidden" id="editId">

        <div class="mb-3">
            <label class="block mb-1 font-medium text-gray-700">Program Name</label>
            <input type="text" id="editProgramName" class="border border-gray-300 rounded px-3 py-2 w-full bg-gray-100" readonly>
        </div>

        <div class="mb-3">
            <label class="block mb-1 font-medium text-gray-700">Program Code</label>
            <input type="text" id="editProgramCode" class="border border-gray-300 rounded px-3 py-2 w-full bg-gray-100" readonly>
        </div>

        <div class="mb-3">
            <label class="block mb-1 font-medium text-gray-700">Accreditation Level</label>
            <select id="editStatus" class="border border-gray-300 rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Status</option>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="block mb-1 font-medium text-gray-700">Accreditation Date</label>
            <input type="date" id="editDate" class="border border-gray-300 rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="flex justify-end gap-2 mt-4 text-sm">
            <button id="cancelEdit" class="p-2 border border-gray-500 text-gray-500 rounded-lg hover:bg-gray-500 hover:text-white transition duration-200 transform hover:scale-110">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="saveEdit" class="p-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white transition duration-200 transform hover:scale-110">
                <i class="fas fa-check"></i> Save
            </button>
        </div>
    </div>
</div>

<script>
// Notification system
function showNotification(message, type = 'success') {
    const container = document.getElementById('notification-container');
    
    const notification = document.createElement('div');
    
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
    
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
        <button class="ml-auto text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 10);
    
    notification.querySelector('button').addEventListener('click', () => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    });
    
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

const table = document.querySelector("#accreditationTable tbody");
const editModal = document.getElementById("editModal");
const closeModalBtn = document.getElementById("closeModal");
const cancelEditBtn = document.getElementById("cancelEdit");

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

table.addEventListener("click", e => {
    if (e.target.closest(".edit")) {
        const editBtn = e.target.closest(".edit");
        const id = editBtn.dataset.id;
        const program_name = editBtn.dataset.programName;
        const program_code = editBtn.dataset.programCode;
        const status = editBtn.dataset.status;
        const date = editBtn.dataset.date;
        
        document.getElementById("editId").value = id;
        document.getElementById("editProgramName").value = program_name;
        document.getElementById("editProgramCode").value = program_code;
        document.getElementById("editStatus").value = status;
        document.getElementById("editDate").value = date;
        
        editModal.classList.remove("hidden");
        editModal.classList.add("flex");
    }
});

closeModalBtn.addEventListener("click", () => {
    editModal.classList.add("hidden");
    editModal.classList.remove("flex");
});

cancelEditBtn.addEventListener("click", () => {
    editModal.classList.add("hidden");
    editModal.classList.remove("flex");
});

document.getElementById("saveEdit").addEventListener("click", () => {
    const id = document.getElementById("editId").value;
    const status = document.getElementById("editStatus").value;
    const date = document.getElementById("editDate").value;

    // Determine the correct path based on current location
    const currentPath = window.location.pathname;
    let fetchUrl;
    
    if (currentPath.includes('Main_dashboard.php') || currentPath.endsWith('/')) {
        // Called from dashboard or root - use full path
        fetchUrl = "CEIT_Modules/Accreditation_Status/Accreditation_Status.php";
    } else {
        // Called as standalone - use relative path
        fetchUrl = "Accreditation_Status.php";
    }

    fetch(fetchUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
            action: "update", 
            entry: { id, status, date } 
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = table.querySelector(`tr[data-id="${id}"]`);
            row.children[2].textContent = status || 'N/A';
            row.children[3].textContent = formatDate(date);
            const editBtn = row.querySelector(".edit");
            editBtn.dataset.status = status;
            editBtn.dataset.date = date;
            editModal.classList.add("hidden");
            editModal.classList.remove("flex");
            showNotification("Accreditation status updated successfully!");
        } else {
            showNotification(data.error || "Failed to update accreditation status.", "error");
        }
    })
    .catch((error) => {
        console.error("Fetch error:", error);
        showNotification("Error updating accreditation status.", "error");
    });
});
</script>

</body>
</html>