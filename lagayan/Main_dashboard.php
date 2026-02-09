<?php
session_start();
// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_info'])) {
    header("Location: logout.php");
    exit;
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Main Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
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

        /* Sidebar Section Titles */
        .sidebar-section {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fed7aa;
            /* orange-200 */
            padding: 1rem 0.75rem 0.5rem 0.75rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 0.5rem;
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
        <aside id="sidebar" class="sidebar w-128 bg-gradient-to-b from-orange-500 to-orange-600 text-white p-4 flex flex-col justify-between shadow-xl">
            <div>
                <div class="flex flex-col items-center mb-8">
                    <img src="cvsulogo.png" alt="School Logo" class="w-16 h-16 object-contain drop-shadow-[0_10px_10px_rgba(0,0,0,0.3)]" />
                    <h2 class="mt-4 text-lg font-bold">MIS Dashboard</h2>
                </div>
                <nav class="space-y-1">

                    <!-- SECTION 1: Manage Departments -->
                    <div class="sidebar-section">Manage Departments</div>
                    <!-- Modules from Manage_Modules folder go here -->
                    <div id="list-manage-dept" class="mb-2"></div>

                    <!-- SECTION 2: Posting -->
                    <div class="sidebar-section">Posting</div>
                    <!-- Modules from CEIT_Modules folder go here -->
                    <div id="list-posting" class="mb-4"></div>

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
                            Main Dashboard
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
        // Function to convert underscores to spaces for display
        function underscoresToSpaces(str) {
            return str.replace(/_/g, ' ');
        }

        // Function to fetch main dashboard data
        async function fetchMainData() {
            try {
                const response = await fetch('get_main_data.php');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return await response.json();
            } catch (error) {
                console.error('Error fetching main data:', error);
                return null;
            }
        }

        // Load module content
        async function loadModuleContent(moduleId, moduleName, folderName) {
            const moduleContentContainer = document.getElementById(moduleId);

            if (!moduleContentContainer) return;

            // Show loading indicator
            moduleContentContainer.innerHTML = '<div class="loading-spinner"></div><p class="text-center text-gray-500">Loading module content...</p>';

            try {
                const safeModuleName = moduleName.toLowerCase().replace(/\s+/g, '_');
                const response = await fetch(`${folderName}/${safeModuleName}/${safeModuleName}.php`);

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const html = await response.text();
                moduleContentContainer.innerHTML = html;

                // Execute scripts
                const scripts = moduleContentContainer.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.text = script.innerText;
                    document.head.appendChild(newScript).parentNode.removeChild(newScript);
                });

                // Initialize specific functionality
                if (typeof initializeAnnouncementsModule === 'function') initializeAnnouncementsModule();
                else if (typeof initializeMemosModule === 'function') initializeMemosModule();
                else if (typeof initializeAboutModule === 'function') initializeAboutModule();

                if (moduleName === 'Graphs' && typeof initializeCharts === 'function') {
                    setTimeout(initializeCharts, 100);
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
                                    Error loading module from <strong>${folderName}</strong>.
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
            // Use orange for success instead of green
            const bgColor = type === 'success' ? 'orange' : 'red';
            notification.className = `fixed top-4 right-4 z-50 bg-${bgColor}-500 text-white px-4 py-3 rounded-lg shadow-lg mb-2`;
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

        // Function to update the UI with main dashboard data
        function updateUIWithMainData(data) {
            if (!data || !data.success) {
                console.error('Error fetching main data:', data?.message || 'Unknown error');
                return;
            }

            // Update user information
            const userName = document.getElementById('userName');
            const userEmail = document.getElementById('userEmail');
            if (data.user) {
                userName.textContent = data.user.name || 'Unknown User';
                userEmail.textContent = data.user.email || 'No email provided';
            }

            const dynamicModuleContent = document.getElementById('dynamicModuleContent');
            dynamicModuleContent.innerHTML = '';

            // Helper to create module button and content
            // Added 'prefix' to prevent ID collisions
            const createModuleItem = (module, folderName, prefix) => {
                const uniqueId = `upload-${prefix}-module-${module.id}`; // e.g., upload-manage-module-1

                const btn = document.createElement('button');
                btn.className = 'upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center';
                btn.setAttribute('data-tab', uniqueId);
                btn.setAttribute('data-module-name', module.name);
                btn.setAttribute('data-folder', folderName);

                // Convert underscores to spaces for display
                const displayName = underscoresToSpaces(module.name);
                btn.innerHTML = `<i class="${module.icon || 'fas fa-file'} mr-3 w-5"></i> <span>${displayName}</span>`;

                const content = document.createElement('div');
                content.id = uniqueId;
                content.className = 'tab-content';
                content.setAttribute('data-module-id', module.id);
                content.setAttribute('data-module-name', module.name);
                content.setAttribute('data-folder', folderName);

                content.innerHTML = `
                    <div class="mb-6">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800">${displayName}</h2>
                        <p class="text-gray-600">${module.description || 'Module content management'}</p>
                    </div>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-500 text-center">Click to load module content</p>
                    </div>
                `;

                return { btn, content };
            };

            // 1. POPULATE MANAGE DEPARTMENTS (CEIT_Modules table -> Manage_Modules folder) -> Prefix: 'manage'
            const deptContainer = document.getElementById('list-manage-dept');
            if (data.ceit_modules && Array.isArray(data.ceit_modules)) {
                data.ceit_modules.forEach(module => {
                    const { btn, content } = createModuleItem(module, 'Manage_Modules', 'manage');
                    deptContainer.appendChild(btn);
                    dynamicModuleContent.appendChild(content);
                });
            }

            // 2. POPULATE POSTING (Modules table -> CEIT_Modules folder) -> Prefix: 'ceit'
            const postingContainer = document.getElementById('list-posting');
            if (data.modules && Array.isArray(data.modules)) {
                data.modules.forEach(module => {
                    const { btn, content } = createModuleItem(module, 'CEIT_Modules', 'ceit');
                    postingContainer.appendChild(btn);
                    dynamicModuleContent.appendChild(content);
                });
            }
        }

        // Track active tabs
        let activeTab = null;
        let mainData = null;

        // Function to clean up tab resources
        function cleanupTabResources(tabId) {
            // Clean up PDF resources for announcements
            if (tabId.startsWith('upload-manage-module-') || tabId.startsWith('upload-ceit-module-')) {
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

        // Function to reinitialize a module
        function reinitializeModule(tabId) {
            if (tabId.startsWith('upload-manage-module-') || tabId.startsWith('upload-ceit-module-')) {
                // Extract the original module ID (last number) to search in arrays
                const parts = tabId.split('-');
                const realId = parseInt(parts[parts.length - 1]);

                let module = null;
                if (tabId.startsWith('upload-manage-module-')) {
                    // Manage departments uses ceit_modules array (from CEIT_Modules table)
                    module = mainData?.ceit_modules?.find(m => m.id == realId);
                } else if (tabId.startsWith('upload-ceit-module-')) {
                    // Posting uses modules array (from Modules table)
                    module = mainData?.modules?.find(m => m.id == realId);
                }

                if (module) {
                    const lowerName = module.name.toLowerCase();
                    if (lowerName.includes('announcement') && typeof initializeAnnouncementsModule === 'function') {
                        console.log('Reinitializing Announcements module');
                        initializeAnnouncementsModule();
                    } else if (lowerName.includes('memo') && typeof initializeMemosModule === 'function') {
                        console.log('Reinitializing Memos module');
                        initializeMemosModule();
                    } else if (lowerName === 'about' && typeof initializeAboutModule === 'function') {
                        console.log('Reinitializing About module');
                        initializeAboutModule();
                    } else if (lowerName === 'graphs' && typeof initializeCharts === 'function') {
                        setTimeout(initializeCharts, 100);
                    }
                }
            }
        }

        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('DOM fully loaded');

            // Fetch main data and update UI
            mainData = await fetchMainData();
            updateUIWithMainData(mainData);

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
                        // For dynamic modules, use the module name with spaces
                        if (tabId.startsWith('upload-manage-module-') || tabId.startsWith('upload-ceit-module-')) {
                            // Get attributes directly from button, don't need to search arrays for folder/name
                            const folderName = button.getAttribute('data-folder');
                            const moduleName = button.getAttribute('data-module-name');
                            
                            // Convert underscores to spaces for display
                            const displayName = moduleName ? underscoresToSpaces(moduleName) : 'Module';
                            pageTitle.textContent = displayName;

                            // Load module content if not already loaded
                            if (moduleName && tabContent && !tabContent.hasAttribute('data-loaded')) {
                                loadModuleContent(tabId, moduleName, folderName);
                                tabContent.setAttribute('data-loaded', 'true');
                            } else if (tabContent && tabContent.hasAttribute('data-loaded')) {
                                // Reinitialize module if already loaded
                                reinitializeModule(tabId);
                            }
                        } else {
                            pageTitle.textContent = 'Main Dashboard';
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
                // Try Manage Dept first, then Posting
                let firstBtn = document.querySelector('#list-manage-dept .upload-tab-btn');
                if (!firstBtn) firstBtn = document.querySelector('#list-posting .upload-tab-btn');
                if (firstBtn) firstBtn.click();
                else document.querySelector('[data-tab="upload-archive"]').click();
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