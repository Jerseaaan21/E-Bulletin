<?php
session_start();
// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_info'])) {
    header("Location: logout.php");
    exit;
}

// Set department ID in session for easier access
if (isset($_SESSION['user_info']['dept_id'])) {
    $_SESSION['dept_id'] = $_SESSION['user_info']['dept_id'];
}

// Include database connection
require_once 'db.php';

// Get user ID from session
$userId = null;
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
    }
}

if (!$userId) {
    header("Location: login.php");
    exit;
}

// Fetch department data
$query = "SELECT * FROM departments WHERE dept_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user['dept_id']);
$stmt->execute();
$result = $stmt->get_result();
$department = $result->fetch_assoc();

// Ensure dept_id is in session
$_SESSION['dept_id'] = $department['dept_id'];
$_SESSION['dept_acronym'] = $department['acronym'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Department Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        .nav-btn.active,
        .upload-tab-btn.active {
            background-color: #ea580c;
            font-weight: bold;
        }

        textarea {
            resize: vertical;
            min-height: 250px;
            transition: all 0.3s ease;
        }

        textarea:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.7;
        }

        textarea:focus:not(:disabled) {
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.2);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sidebar-item {
            transition: all 0.2s ease;
        }

        .sidebar-item:hover {
            transform: translateX(5px);
        }

        .content-card {
            transition: all 0.3s ease;
        }

        .content-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .edit-btn {
            transition: all 0.2s ease;
        }

        .edit-btn:hover {
            transform: scale(1.05);
        }

        /* Loading spinner */
        .loading-spinner {
            border: 3px solid rgba(234, 88, 12, 0.3);
            border-radius: 50%;
            border-top: 3px solid #ea580c;
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

        /* Responsive styles */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                z-index: 50;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
                display: none;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .main-tab-btn {
                flex-direction: column;
                padding: 12px 8px;
                box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            .main-tab-btn i {
                margin-right: 0;
                margin-bottom: 4px;
            }

            .sidebar-item {
                padding: 12px 16px;
            }

            .sidebar-item i {
                margin-right: 12px;
            }

            .content-card {
                margin-bottom: 16px;
            }

            textarea {
                min-height: 80px;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.5rem;
            }

            .edit-btn {
                padding: 8px 12px;
                font-size: 0.875rem;
            }

            .sidebar-item span {
                display: none;
            }

            .sidebar-item i {
                margin-right: 0;
            }

            .sidebar-item {
                justify-content: center;
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen font-sans">
    <!-- Mobile Menu Button -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 w-12 h-12 rounded-full bg-orange-600 text-white flex items-center justify-center shadow-lg">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-80 bg-gradient-to-b from-orange-500 to-orange-600 text-white p-4 flex flex-col justify-between shadow-xl">
            <div>
                <div class="flex flex-col items-center mb-8">
                    <img src="cvsulogo.png" alt="School Logo" class="w-16 h-16 object-contain drop-shadow-[0_10px_10px_rgba(0,0,0,0.3)]" />
                    <h2 id="deptName" class="mt-4 text-lg font-bold">Loading...</h2>
                </div>
                <nav class="space-y-1">
                    <!-- Dynamic modules will be inserted here -->
                    <div id="dynamicModules"></div>
                    <a href="Department_Bulletin.php?dept_id=<?php echo htmlspecialchars($user['dept_id']); ?>" target="_blank" class="sidebar-item no-animation w-full text-left p-3 rounded-lg text-sm hover:bg-orange-700 flex items-center text-white">
                        <i class="fas fa-tv mr-3 w-5"></i> <span>View Bulletin</span>
                    </a>
                    <button data-tab="upload-archive" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-archive mr-3 w-5"></i> <span>Archive</span>
                    </button>
                </nav>
            </div>
            <div class="mt-6 p-3 bg-orange-700 rounded-lg">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-orange-800 flex items-center justify-center">
                        <i class="fas fa-user-tie text-white"></i>
                    </div>
                    <div class="ml-3">
                        <p id="userName" class="font-medium text-sm">Loading...</p>
                        <p id="userEmail" class="text-xs opacity-80">Loading...</p>
                    </div>
                </div>
                <!-- Logout Button -->
                <form action="logout.php" method="POST" class="mt-3">
                    <button type="submit" class="w-full py-2 px-4 bg-orange-800 hover:bg-orange-900 text-white rounded-lg transition duration-200 flex items-center justify-center">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </button>
                </form>
            </div>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-gradient-to-br from-gray-50 to-gray-100">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 lg:mb-8">
                    <div class="mb-4 sm:mb-0">
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-800" id="pageTitle">
                            Department Dashboard
                        </h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Management Information System</p>
                    </div>
                    <div class="text-center md:text-right">
                        <div class="text-sm text-gray-500" id="currentDate">Monday, January 1, 2024</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6">
                    <div id="uploadContent">
                        <!-- Dynamic module content containers will be inserted here -->
                        <div id="dynamicModuleContent"></div>

                        <div id="upload-archive" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Archive</h2>
                                <p class="text-gray-600">View archived content</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Archive content will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Function to fetch department data
        async function fetchDepartmentData() {
            try {
                const response = await fetch('get_department_data.php');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return await response.json();
            } catch (error) {
                console.error('Error fetching department data:', error);
                return null;
            }
        }

        // Load module content
        async function loadModuleContent(moduleId, moduleName) {
            // Validate inputs
            if (!moduleId || !moduleName) {
                console.error('Invalid module ID or name:', {
                    moduleId,
                    moduleName
                });
                return;
            }

            const moduleContentContainer = document.getElementById(`upload-module-${moduleId}`);

            // Check if container exists
            if (!moduleContentContainer) {
                console.error(`Module content container not found for module ID: ${moduleId}`);
                return;
            }

            // Show loading indicator
            moduleContentContainer.innerHTML = '<div class="loading-spinner"></div><p class="text-center text-gray-500">Loading module content...</p>';

            try {
                // Convert module name to a safe format for URL
                const safeModuleName = moduleName.toLowerCase().replace(/\s+/g, '_');
                const response = await fetch(`Modules/${safeModuleName}/${safeModuleName}.php`);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const html = await response.text();
                moduleContentContainer.innerHTML = html;

                // Execute any scripts in the loaded content
                const scripts = moduleContentContainer.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.text = script.innerText;
                    document.head.appendChild(newScript).parentNode.removeChild(newScript);
                });

                // Initialize the module functionality if it exists
                if (typeof initializeAnnouncementsModule === 'function') {
                    initializeAnnouncementsModule();
                } else if (typeof initializeMemosModule === 'function') {
                    initializeMemosModule();
                } else if (typeof initializeAboutModule === 'function') {
                    initializeAboutModule();
                }

            } catch (error) {
                console.error('Error loading module content:', error);
                moduleContentContainer.innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            Error loading module. Please try again later.
                        </p>
                    </div>
                </div>
            </div>
        `;
            }
        }

        if (typeof initializeAboutModule === 'function') {
            console.log('Initializing About module from dashboard');
            initializeAboutModule();
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 z-50 bg-${type === 'success' ? 'green' : 'red'}-500 text-white px-4 py-3 rounded-lg shadow-lg mb-2`;
            notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;

            document.body.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Function to update the UI with department data
        function updateUIWithDepartmentData(data) {
            if (!data || !data.success) {
                console.error('Error fetching department data:', data?.message || 'Unknown error');
                return;
            }

            // Update department name
            const deptName = document.getElementById('deptName');
            if (data.department && data.department.dept_name) {
                deptName.textContent = data.department.dept_name;
            }

            // Update user information
            const userName = document.getElementById('userName');
            const userEmail = document.getElementById('userEmail');
            if (data.user) {
                userName.textContent = data.user.name || 'Unknown User';
                userEmail.textContent = data.user.email || 'No email provided';
            }

            // Update dynamic modules
            const dynamicModules = document.getElementById('dynamicModules');
            const dynamicModuleContent = document.getElementById('dynamicModuleContent');

            // Clear existing dynamic modules
            dynamicModules.innerHTML = '';
            dynamicModuleContent.innerHTML = '';

            if (data.modules && Array.isArray(data.modules) && data.modules.length > 0) {
                data.modules.forEach(module => {
                    // Validate module data
                    if (!module || !module.id || !module.name) {
                        console.warn('Invalid module data:', module);
                        return;
                    }

                    // Create sidebar button for the module
                    const moduleButton = document.createElement('button');
                    moduleButton.className = 'upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center';
                    moduleButton.setAttribute('data-tab', `upload-module-${module.id}`);
                    moduleButton.setAttribute('data-module-name', module.name);

                    // Use module icon if available, otherwise use a default
                    const iconClass = module.icon || 'fas fa-file';

                    // Convert underscores to spaces for display
                    const displayName = underscoresToSpaces(module.name);

                    moduleButton.innerHTML = `
                <i class="${iconClass} mr-3 w-5"></i> 
                <span>${displayName}</span>
            `;

                    dynamicModules.appendChild(moduleButton);

                    // Create content container for the module
                    const moduleContent = document.createElement('div');
                    moduleContent.id = `upload-module-${module.id}`;
                    moduleContent.className = 'tab-content';
                    moduleContent.setAttribute('data-module-id', module.id);
                    moduleContent.setAttribute('data-module-name', module.name);

                    // Initial content - will be loaded when tab is clicked
                    moduleContent.innerHTML = `
                <div class="mb-6">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-800">${displayName}</h2>
                    <p class="text-gray-600">${module.description || 'Module content management'}</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg">
                    <p class="text-gray-500 text-center">Click to load module content</p>
                </div>
            `;

                    dynamicModuleContent.appendChild(moduleContent);
                });
            }
        }

        // Track active tabs
        let activeTab = null;
        let departmentData = null;

        // Function to convert underscores to spaces
        function underscoresToSpaces(str) {
            return str.replace(/_/g, ' ');
        }

        // Function to clean up tab resources
        function cleanupTabResources(tabId) {
            // Clean up PDF resources for announcements
            if (tabId.startsWith('upload-module-')) {
                const moduleId = tabId.replace('upload-module-', '');
                const module = departmentData?.modules?.find(m => m.id == moduleId);

                if (module && module.name === 'Announcements') {
                    // Clean up announcement PDF resources
                    Object.keys(window.pdfDocs || {}).forEach(key => {
                        if (window.pdfDocs[key]) {
                            try {
                                window.pdfDocs[key].destroy();
                            } catch (e) {
                                console.warn('Error destroying PDF document:', e);
                            }
                            delete window.pdfDocs[key];
                        }
                    });

                    // Reset other PDF-related variables
                    window.currentPageNum = {};
                    window.totalPages = {};
                    window.isRendering = {};
                }
            }
        }

        // Function to reinitialize a module
        function reinitializeModule(tabId) {
            if (tabId.startsWith('upload-module-')) {
                const moduleId = tabId.replace('upload-module-', '');
                const module = departmentData?.modules?.find(m => m.id == moduleId);

                if (module) {
                    if (module.name === 'Announcements' && typeof initializeAnnouncementsModule === 'function') {
                        console.log('Reinitializing Announcements module');
                        initializeAnnouncementsModule();
                    } else if (module.name === 'Memo' && typeof initializeMemosModule === 'function') {
                        console.log('Reinitializing Memos module');
                        initializeMemosModule();
                    } else if (module.name === 'About' && typeof initializeAboutModule === 'function') {
                        console.log('Reinitializing About module');
                        initializeAboutModule();
                    }
                }
            }
        }

        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('DOM fully loaded');

            // Fetch department data and update UI
            departmentData = await fetchDepartmentData();
            updateUIWithDepartmentData(departmentData);

            // Set current date
            const currentDateElement = document.getElementById('currentDate');
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            currentDateElement.textContent = new Date().toLocaleDateString('en-US', options);

            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            // Toggle sidebar on mobile
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                sidebarOverlay.classList.toggle('show');
            });

            // Close sidebar when clicking on overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            });

            // Tab switching functionality - use event delegation
            document.addEventListener('click', function(e) {
                // Check if the clicked element is a tab button
                const button = e.target.closest('.upload-tab-btn');
                if (!button) return;

                const tabId = button.getAttribute('data-tab');
                if (!tabId) return;

                // Clean up previous tab's PDF resources
                if (activeTab && activeTab !== tabId) {
                    cleanupTabResources(activeTab);
                }

                // Set new active tab
                activeTab = tabId;

                // Remove active class from all buttons and contents
                document.querySelectorAll('.upload-tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                const tabContent = document.getElementById(tabId);
                if (tabContent) {
                    tabContent.classList.add('active');
                }

                // Update page title based on active tab
                const pageTitle = document.getElementById('pageTitle');
                switch (tabId) {
                    case 'upload-archive':
                        pageTitle.textContent = 'Archive';
                        break;
                    default:
                        // For dynamic modules, use the module name
                        if (tabId.startsWith('upload-module-')) {
                            const moduleId = tabId.replace('upload-module-', '');
                            const module = departmentData?.modules?.find(m => m.id == moduleId);
                            const displayName = module ? underscoresToSpaces(module.name) : 'Module';
                            pageTitle.textContent = displayName;

                            // Load module content if not already loaded
                            if (module && tabContent && !tabContent.hasAttribute('data-loaded')) {
                                loadModuleContent(moduleId, module.name);
                                tabContent.setAttribute('data-loaded', 'true');
                            } else if (tabContent && tabContent.hasAttribute('data-loaded')) {
                                // Reinitialize module if already loaded
                                reinitializeModule(tabId);
                            }
                        } else {
                            pageTitle.textContent = 'Department Dashboard';
                        }
                }

                // Close sidebar when a menu item is clicked (on mobile)
                if (window.innerWidth <= 1024) {
                    sidebar.classList.remove('open');
                    sidebarOverlay.classList.remove('show');
                }
            });

            // Add a small delay to ensure all included scripts are ready
            setTimeout(function() {
                console.log('Initializing tabs');
                // If there are dynamic modules, select the first one by default
                const firstModuleButton = document.querySelector('#dynamicModules .upload-tab-btn');
                if (firstModuleButton) {
                    firstModuleButton.click();
                } else {
                    // If no dynamic modules, select the Archive tab by default
                    const archiveTab = document.querySelector('[data-tab="upload-archive"]');
                    if (archiveTab) archiveTab.click();
                }
            }, 100);
        });

        // Global functions
        function closeFileModal(status, index) {
            const modal = document.getElementById(`file-modal-${status}-${index}`);
            if (modal) {
                modal.classList.remove('modal-active');
                modal.style.display = "none";
            }
        }

        function closeAnnouncementDeleteModal() {
            document.getElementById('announcement-delete-modal').style.display = 'none';
        }

        function closeMemoDeleteModal() {
            document.getElementById('memo-delete-modal').style.display = 'none';
        }
    </script>
</body>

</html>