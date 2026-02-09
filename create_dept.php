<?php
include "db.php";
session_start();

// Check if user is logged in and has appropriate role
/*
if (!isset($_SESSION['user_info']) || $_SESSION['user_info']['role'] !== 'LEAD_MIS') {
    header("Location: login.php");
    exit();
}
*/

// Fetch all available modules
$modulesQuery = "SELECT id, name, description FROM modules ORDER BY name";
$modulesResult = $conn->query($modulesQuery);

// Fetch available officers (users with MIS role and no department assigned)
$officersQuery = "SELECT id, name, email FROM users WHERE role = 'MIS' AND dept_id IS NULL ORDER BY name";
$officersResult = $conn->query($officersQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptName = $_POST['dept_name'];
    $acronym = $_POST['acronym'];
    $selectedModules = isset($_POST['modules']) ? $_POST['modules'] : [];
    $selectedOfficer = $_POST['officer_id'];

    // Validate inputs
    if (empty($deptName) || empty($acronym)) {
        $error = "Department name and acronym are required.";
    } else {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert new department
            $insertDept = "INSERT INTO departments (dept_name, acronym) VALUES (?, ?)";
            $stmt = $conn->prepare($insertDept);
            $stmt->bind_param("ss", $deptName, $acronym);
            $stmt->execute();
            $deptId = $conn->insert_id;

            // Assign selected modules to the department
            if (!empty($selectedModules)) {
                $insertModule = "INSERT INTO department_modules (department_id, module_id) VALUES (?, ?)";
                $stmt = $conn->prepare($insertModule);

                foreach ($selectedModules as $moduleId) {
                    $stmt->bind_param("ii", $deptId, $moduleId);
                    $stmt->execute();
                }
            }

            // Assign officer to the department if selected
            if (!empty($selectedOfficer)) {
                $updateOfficer = "UPDATE users SET dept_id = ? WHERE id = ?";
                $stmt = $conn->prepare($updateOfficer);
                $stmt->bind_param("ii", $deptId, $selectedOfficer);
                $stmt->execute();
            }

            // Commit transaction
            $conn->commit();

            // Redirect to success page or show success message
            $success = "Department created successfully!";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error creating department: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Department</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-xl shadow-lg p-6 md:p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Create New Department</h1>
                <p class="text-gray-600">Fill in the details below to create a new department and assign modules and officers.</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo $success; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <!-- Department Information -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-building mr-2 text-orange-500"></i>
                        Department Information
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="dept_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Department Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="dept_name" name="dept_name" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition"
                                placeholder="e.g., Department of Information Technology">
                        </div>

                        <div>
                            <label for="acronym" class="block text-sm font-medium text-gray-700 mb-1">
                                Acronym <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="acronym" name="acronym" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition"
                                placeholder="e.g., DIT">
                        </div>
                    </div>
                </div>

                <!-- Module Selection -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-puzzle-piece mr-2 text-orange-500"></i>
                        Assign Modules
                    </h2>

                    <p class="text-gray-600 mb-4">Select the modules that this department will have access to:</p>

                    <?php if ($modulesResult->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php while ($module = $modulesResult->fetch_assoc()): ?>
                                <div class="flex items-start p-3 bg-white border border-gray-200 rounded-lg hover:bg-orange-50 transition">
                                    <input type="checkbox" id="module_<?php echo $module['id']; ?>" name="modules[]"
                                        value="<?php echo $module['id']; ?>"
                                        class="mt-1 h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                                    <label for="module_<?php echo $module['id']; ?>" class="ml-3 block">
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($module['name']); ?></span>
                                        <?php if (!empty($module['description'])): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($module['description']); ?></p>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No modules available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Officer Assignment -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-tie mr-2 text-orange-500"></i>
                        Assign Officer
                    </h2>

                    <p class="text-gray-600 mb-4">Select an officer to manage this department:</p>

                    <?php if ($officersResult->num_rows > 0): ?>
                        <div>
                            <label for="officer_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Department Officer
                            </label>
                            <select id="officer_id" name="officer_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                                <option value="">-- Select an Officer --</option>
                                <?php while ($officer = $officersResult->fetch_assoc()): ?>
                                    <option value="<?php echo $officer['id']; ?>">
                                        <?php echo htmlspecialchars($officer['name']); ?> (<?php echo htmlspecialchars($officer['email']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No available officers. All officers are already assigned to departments.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="departments.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Create Department
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add some interactivity for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight selected modules
            const moduleCheckboxes = document.querySelectorAll('input[name="modules[]"]');
            moduleCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const container = this.closest('.flex');
                    if (this.checked) {
                        container.classList.add('bg-orange-50', 'border-orange-300');
                    } else {
                        container.classList.remove('bg-orange-50', 'border-orange-300');
                    }
                });
            });
        });
    </script>
</body>

</html>