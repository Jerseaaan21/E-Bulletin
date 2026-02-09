<?php
include "../../db.php";

// Function to get all MIS accounts
function getMISAccounts($conn)
{
    $sql = "SELECT u.*, d.dept_name 
            FROM users u 
            LEFT JOIN departments d ON u.dept_id = d.dept_id 
            WHERE u.role IN ('MIS', 'LEAD_MIS') 
            ORDER BY u.role DESC, d.dept_name";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all departments
function getDepartments($conn)
{
    $sql = "SELECT * FROM departments ORDER BY dept_name";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['add_mis_account'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $dept_id = !empty($_POST['dept_id']) ? $_POST['dept_id'] : null;

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO users (name, email, role, dept_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $email, $role, $dept_id);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode(['status' => 'success', 'id' => $new_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit();
    } elseif (isset($_POST['update_mis_account'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $dept_id = !empty($_POST['dept_id']) ? $_POST['dept_id'] : null;

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, dept_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $name, $email, $dept_id, $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit();
    } elseif (isset($_POST['delete_mis_account'])) {
        $id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit();
    }
}

$accounts = getMISAccounts($conn);
$departments = getDepartments($conn);
?>

<div class="bg-white rounded-xl shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-orange-dark">Manage MIS Officers</h3>
        <button onclick="openAddModal()" class="bg-orange-primary hover:bg-orange-dark text-white px-4 py-2 rounded-lg flex items-center transition duration-300">
            <i class="fas fa-plus mr-2"></i>
            Add New Officer
        </button>
    </div>

    <!-- MIS Officers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($accounts as $account): ?>
            <div class="bg-orange-bg rounded-xl shadow-md p-5 border border-orange-border relative overflow-hidden">
                <!-- Role Badge -->
                <div class="absolute top-4 right-4">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $account['role'] === 'LEAD_MIS' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                        <?php echo $account['role']; ?>
                    </span>
                </div>

                <!-- User Icon -->
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-orange-primary flex items-center justify-center">
                        <span class="text-white text-xl font-bold">
                            <?php echo strtoupper(substr($account['name'], 0, 1)); ?>
                        </span>
                    </div>
                </div>

                <!-- User Details -->
                <div class="text-center mb-4">
                    <h4 class="text-lg font-semibold text-orange-dark"><?php echo htmlspecialchars($account['name']); ?></h4>
                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($account['email']); ?></p>
                    <?php if (!empty($account['dept_name'])): ?>
                        <p class="text-gray-500 text-xs mt-1"><?php echo htmlspecialchars($account['dept_name']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-center space-x-2">
                    <button onclick="editAccount(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['name']); ?>', '<?php echo htmlspecialchars($account['email']); ?>', <?php echo $account['dept_id'] ?? 'null'; ?>)"
                        class="px-3 py-1 bg-orange-light text-orange-dark rounded-lg hover:bg-orange-primary hover:text-white transition duration-300 text-sm">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteAccount(<?php echo $account['id']; ?>)"
                        class="px-3 py-1 bg-red-100 text-red-600 rounded-lg hover:bg-red-500 hover:text-white transition duration-300 text-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <!-- Status Message -->
                <div id="message-<?php echo $account['id']; ?>" class="mt-3 text-center text-sm hidden"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State -->
    <?php if (empty($accounts)): ?>
        <div class="text-center py-12">
            <div class="w-16 h-16 rounded-full bg-orange-bg flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-tie text-orange-primary text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No MIS Officers Found</h3>
            <p class="text-gray-500 mb-4">Get started by adding a new MIS officer</p>
            <button onclick="openAddModal()" class="bg-orange-primary hover:bg-orange-dark text-white px-4 py-2 rounded-lg flex items-center transition duration-300 mx-auto">
                <i class="fas fa-plus mr-2"></i>
                Add New Officer
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="accountModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-6 border-b border-orange-border">
            <div class="flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-bold text-orange-dark">Add New MIS Officer</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <form id="accountForm" class="p-6">
            <input type="hidden" id="accountId" name="accountId">

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Full Name</label>
                <input type="text" id="name" name="name" class="w-full px-4 py-2 border border-orange-border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-primary" placeholder="Enter full name" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-2 border border-orange-border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-primary" placeholder="Enter email address" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Role</label>
                <select id="role" name="role" class="w-full px-4 py-2 border border-orange-border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-primary" required>
                    <option value="MIS">MIS Officer</option>
                    <option value="LEAD_MIS">Lead MIS Officer</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Department</label>
                <select id="dept_id" name="dept_id" class="w-full px-4 py-2 border border-orange-border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-primary">
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border border-orange-border rounded-lg text-gray-700 hover:bg-orange-bg">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" class="px-4 py-2 bg-orange-primary hover:bg-orange-dark text-white rounded-lg">
                    Add Officer
                </button>
            </div>
        </form>
    </div>
</div>