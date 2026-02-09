<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead MIS Officer Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'orange-primary': '#FF8C00',
                        'orange-light': '#FFA500',
                        'orange-dark': '#E67E00',
                        'orange-bg': '#FFF8F0',
                        'orange-sidebar': '#FFF5EB',
                        'orange-border': '#FFDAB9',
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-item:hover {
            background-color: rgba(255, 140, 0, 0.1);
        }

        .sidebar-item.active {
            background-color: rgba(255, 140, 0, 0.15);
            border-left: 4px solid #FF8C00;
        }

        .sidebar-subitem:hover {
            background-color: rgba(255, 140, 0, 0.05);
        }

        .sidebar-subitem.active {
            background-color: rgba(255, 140, 0, 0.1);
            border-left: 4px solid #FFA500;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 140, 0, 0.2);
        }

        .notification {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .loading-spinner {
            border: 3px solid rgba(255, 140, 0, 0.3);
            border-radius: 50%;
            border-top: 3px solid #FF8C00;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .tab-active {
            border-bottom: 3px solid #FF8C00;
            color: #E67E00;
        }
    </style>
</head>

<body class="bg-orange-bg font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-orange-sidebar border-r border-orange-border flex flex-col">
            <div class="p-6 border-b border-orange-border">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-orange-primary flex items-center justify-center">
                        <i class="fas fa-bullhorn text-white"></i>
                    </div>
                    <h1 class="text-xl font-bold text-orange-dark">E-Bulletin</h1>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-4">
                <!-- Main Navigation -->
                <div class="px-3 mb-6">
                    <a href="#" id="manageDepartmentsNav" class="sidebar-item active flex items-center px-3 py-3 text-orange-dark rounded-lg mb-1">
                        <i class="fas fa-building w-5 mr-3"></i>
                        <span>Manage Departments</span>
                    </a>
                    <a href="#" id="postContentNav" class="sidebar-item flex items-center px-3 py-3 text-gray-600 rounded-lg mb-1">
                        <i class="fas fa-edit w-5 mr-3"></i>
                        <span>Post Content</span>
                    </a>
                </div>

                <!-- Manage Departments Submenu (Hidden by default) -->
                <div id="manageDepartmentsSubmenu" class="px-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase px-3 mb-2">Department Management</p>
                    <a href="#" class="sidebar-subitem active flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-subsection="departments">
                        <i class="fas fa-building w-5 mr-3"></i>
                        <span>Departments</span>
                    </a>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-subsection="mis-officers">
                        <i class="fas fa-user-tie w-5 mr-3"></i>
                        <span>MIS Officers</span>
                    </a>
                </div>

                <!-- Post Content Submenu (Hidden by default) -->
                <div id="postContentSubmenu" class="hidden px-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase px-3 mb-2">Content Modules</p>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-module="announcements">
                        <i class="fas fa-bullhorn w-5 mr-3"></i>
                        <span>Announcements</span>
                    </a>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-module="memos">
                        <i class="fas fa-file-alt w-5 mr-3"></i>
                        <span>Memos</span>
                    </a>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-module="graphs">
                        <i class="fas fa-chart-bar w-5 mr-3"></i>
                        <span>Graphs</span>
                    </a>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-module="calendar">
                        <i class="fas fa-calendar-alt w-5 mr-3"></i>
                        <span>Calendar</span>
                    </a>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-module="orgchart">
                        <i class="fas fa-sitemap w-5 mr-3"></i>
                        <span>Org Chart</span>
                    </a>
                    <a href="#" class="sidebar-subitem flex items-center px-3 py-2 text-gray-600 rounded-lg mb-1" data-module="others">
                        <i class="fas fa-ellipsis-h w-5 mr-3"></i>
                        <span>Others</span>
                    </a>
                </div>
            </nav>

            <div class="p-4 border-t border-orange-border">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-orange-light flex items-center justify-center">
                        <span class="text-white font-bold">LM</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-orange-dark">Lead MIS Officer</p>
                        <p class="text-xs text-gray-500">admin@ebulletin.com</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center">
                <h2 id="pageTitle" class="text-2xl font-bold text-orange-dark">Manage Departments</h2>
                <div class="flex space-x-3">
                    <button id="addDepartmentBtn" class="bg-orange-primary hover:bg-orange-dark text-white px-4 py-2 rounded-lg flex items-center transition duration-300">
                        <i class="fas fa-plus mr-2"></i>
                        Add Department
                    </button>
                    <button id="addOfficerBtn" class="bg-orange-light hover:bg-orange-primary text-white px-4 py-2 rounded-lg flex items-center transition duration-300 hidden">
                        <i class="fas fa-plus mr-2"></i>
                        Add Officer
                    </button>
                    <button id="newPostBtn" class="bg-orange-light hover:bg-orange-primary text-white px-4 py-2 rounded-lg flex items-center transition duration-300 hidden">
                        <i class="fas fa-plus mr-2"></i>
                        New Post
                    </button>
                </div>
            </header>

            <!-- Notification Container -->
            <div id="notificationContainer" class="fixed top-4 right-4 z-50"></div>

            <!-- Manage Departments Section -->
            <div id="manageDepartmentsSection" class="p-6">
                <!-- Section Tabs -->
                <div class="bg-white border-b border-orange-border px-6 mb-6">
                    <div class="flex space-x-8">
                        <button id="departmentsTab" class="tab-active py-4 font-medium text-orange-dark transition duration-300">
                            Departments
                        </button>
                        <button id="misOfficersTab" class="py-4 font-medium text-gray-500 hover:text-orange-dark transition duration-300">
                            MIS Officers
                        </button>
                    </div>
                </div>

                <!-- Departments Subsection -->
                <div id="departmentsSubsection">
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h3 class="text-xl font-semibold text-orange-dark mb-4">Department Management</h3>
                        <p class="text-gray-600 mb-4">Create and manage departments, assign MIS officers, and configure module permissions.</p>
                        <button class="bg-orange-primary hover:bg-orange-dark text-white px-4 py-2 rounded-lg flex items-center transition duration-300">
                            <i class="fas fa-plus mr-2"></i>
                            Create New Department
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Pending Approvals Cards -->
                        <div class="bg-white rounded-xl shadow-md p-6 card-hover transition duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-lg bg-orange-primary flex items-center justify-center">
                                    <i class="fas fa-bullhorn text-white text-xl"></i>
                                </div>
                                <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">3 Pending</span>
                            </div>
                            <h3 class="text-lg font-semibold text-orange-dark mb-2">Announcements</h3>
                            <p class="text-gray-600 text-sm">Review and approve department announcements</p>
                            <button class="mt-4 text-orange-primary hover:text-orange-dark font-medium text-sm flex items-center">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                        </div>

                        <div class="bg-white rounded-xl shadow-md p-6 card-hover transition duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-lg bg-orange-light flex items-center justify-center">
                                    <i class="fas fa-file-alt text-white text-xl"></i>
                                </div>
                                <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">5 Pending</span>
                            </div>
                            <h3 class="text-lg font-semibold text-orange-dark mb-2">Memos</h3>
                            <p class="text-gray-600 text-sm">Review and approve department memos</p>
                            <button class="mt-4 text-orange-primary hover:text-orange-dark font-medium text-sm flex items-center">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                        </div>

                        <div class="bg-white rounded-xl shadow-md p-6 card-hover transition duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-lg bg-red-500 flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-white text-xl"></i>
                                </div>
                                <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">2 Pending</span>
                            </div>
                            <h3 class="text-lg font-semibold text-orange-dark mb-2">Graphs</h3>
                            <p class="text-gray-600 text-sm">Review and approve department graphs</p>
                            <button class="mt-4 text-orange-primary hover:text-orange-dark font-medium text-sm flex items-center">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MIS Officers Subsection (Hidden by default) -->
                <div id="misOfficersSubsection" class="hidden">
                    <!-- MIS Officers content will be loaded here -->
                    <div id="misOfficersContent"></div>
                </div>
            </div>

            <!-- Post Content Section (Hidden by default) -->
            <div id="postContentSection" class="p-6 hidden">
                <!-- Module Content Container -->
                <div id="moduleContentContainer" class="hidden">
                    <!-- Loading indicator -->
                    <div id="loadingIndicator" class="flex justify-center items-center py-12 hidden">
                        <div class="loading-spinner"></div>
                    </div>

                    <!-- Module content will be loaded here -->
                    <div id="moduleContent"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Department Modal -->
    <div id="addDepartmentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4">
            <div class="p-6 border-b border-orange-border">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-orange-dark">Create New Department</h3>
                    <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Department Name</label>
                    <input type="text" class="w-full px-4 py-2 border border-orange-border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-primary" placeholder="Enter department name" required>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Assign MIS Officer</label>
                    <select class="w-full px-4 py-2 border border-orange-border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-primary">
                        <option>Select an officer</option>
                        <option>John Smith</option>
                        <option>Sarah Johnson</option>
                        <option>Michael Brown</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Select Modules</label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="rounded text-orange-primary focus:ring-orange-primary">
                            <span>Announcements</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="rounded text-orange-primary focus:ring-orange-primary">
                            <span>Memos</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="rounded text-orange-primary focus:ring-orange-primary">
                            <span>Graphs</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="rounded text-orange-primary focus:ring-orange-primary">
                            <span>Calendar</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="rounded text-orange-primary focus:ring-orange-primary">
                            <span>Org Chart</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="rounded text-orange-primary focus:ring-orange-primary">
                            <span>Others</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button id="cancelBtn" class="px-4 py-2 border border-orange-border rounded-lg text-gray-700 hover:bg-orange-bg">
                        Cancel
                    </button>
                    <button type="submit" id="createDepartmentBtn" class="px-4 py-2 bg-orange-primary hover:bg-orange-dark text-white rounded-lg">
                        Create Department
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the MIS Officers JavaScript file -->
    <script src="mis_officers.js"></script>

    <script>
        // DOM Elements
        const manageDepartmentsNav = document.getElementById('manageDepartmentsNav');
        const postContentNav = document.getElementById('postContentNav');
        const manageDepartmentsSubmenu = document.getElementById('manageDepartmentsSubmenu');
        const postContentSubmenu = document.getElementById('postContentSubmenu');
        const manageDepartmentsSection = document.getElementById('manageDepartmentsSection');
        const postContentSection = document.getElementById('postContentSection');
        const departmentsTab = document.getElementById('departmentsTab');
        const misOfficersTab = document.getElementById('misOfficersTab');
        const departmentsSubsection = document.getElementById('departmentsSubsection');
        const misOfficersSubsection = document.getElementById('misOfficersSubsection');
        const pageTitle = document.getElementById('pageTitle');
        const addDepartmentBtn = document.getElementById('addDepartmentBtn');
        const addOfficerBtn = document.getElementById('addOfficerBtn');
        const newPostBtn = document.getElementById('newPostBtn');
        const moduleItems = document.querySelectorAll('.sidebar-subitem[data-module]');
        const subsectionItems = document.querySelectorAll('.sidebar-subitem[data-subsection]');
        const moduleContentContainer = document.getElementById('moduleContentContainer');
        const moduleContent = document.getElementById('moduleContent');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const addDepartmentModal = document.getElementById('addDepartmentModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const createDepartmentBtn = document.getElementById('createDepartmentBtn');
        const notificationContainer = document.getElementById('notificationContainer');

        // State management
        let currentSection = 'manage-departments';
        let currentSubsection = 'departments';
        let currentModule = null;

        // Initialize state from localStorage
        function initializeState() {
            const savedSection = localStorage.getItem('currentSection');
            const savedSubsection = localStorage.getItem('currentSubsection');
            const savedModule = localStorage.getItem('currentModule');

            if (savedSection) {
                currentSection = savedSection;
                if (savedSection === 'manage-departments' && savedSubsection) {
                    currentSubsection = savedSubsection;
                } else if (savedSection === 'post-content' && savedModule) {
                    currentModule = savedModule;
                }
            }

            // Apply the saved state
            if (currentSection === 'manage-departments') {
                showManageDepartments();
                if (currentSubsection === 'mis-officers') {
                    showMisOfficers();
                }
            } else if (currentSection === 'post-content') {
                showPostContent();
                if (currentModule) {
                    showModule(currentModule);
                }
            }
        }

        // Save state to localStorage
        function saveState() {
            localStorage.setItem('currentSection', currentSection);
            if (currentSubsection) {
                localStorage.setItem('currentSubsection', currentSubsection);
            } else {
                localStorage.removeItem('currentSubsection');
            }
            if (currentModule) {
                localStorage.setItem('currentModule', currentModule);
            } else {
                localStorage.removeItem('currentModule');
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification bg-${type === 'success' ? 'green' : 'red'}-500 text-white px-4 py-3 rounded-lg shadow-lg mb-2`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;

            notificationContainer.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notificationContainer.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Show Manage Departments section
        function showManageDepartments() {
            // Update active states
            manageDepartmentsNav.classList.add('active');
            postContentNav.classList.remove('active');

            // Show/hide sections
            manageDepartmentsSection.classList.remove('hidden');
            postContentSection.classList.add('hidden');
            manageDepartmentsSubmenu.classList.remove('hidden');
            postContentSubmenu.classList.add('hidden');

            // Update page title
            pageTitle.textContent = 'Manage Departments';

            // Update buttons
            addDepartmentBtn.classList.remove('hidden');
            addOfficerBtn.classList.add('hidden');
            newPostBtn.classList.add('hidden');

            // Update state
            currentSection = 'manage-departments';
            currentModule = null;
            saveState();
        }

        // Show Post Content section
        function showPostContent() {
            // Update active states
            postContentNav.classList.add('active');
            manageDepartmentsNav.classList.remove('active');

            // Show/hide sections
            postContentSection.classList.remove('hidden');
            manageDepartmentsSection.classList.add('hidden');
            postContentSubmenu.classList.remove('hidden');
            manageDepartmentsSubmenu.classList.add('hidden');

            // Update page title
            pageTitle.textContent = 'Post Content';

            // Update buttons
            addDepartmentBtn.classList.add('hidden');
            addOfficerBtn.classList.add('hidden');
            newPostBtn.classList.remove('hidden');

            // Update state
            currentSection = 'post-content';
            currentSubsection = null;
            saveState();
        }

        // Show Departments subsection
        function showDepartments() {
            // Update active states
            departmentsTab.classList.add('tab-active', 'text-orange-dark');
            departmentsTab.classList.remove('text-gray-500');
            misOfficersTab.classList.remove('tab-active', 'text-orange-dark');
            misOfficersTab.classList.add('text-gray-500');

            subsectionItems.forEach(item => {
                if (item.getAttribute('data-subsection') === 'departments') {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Show/hide subsections
            departmentsSubsection.classList.remove('hidden');
            misOfficersSubsection.classList.add('hidden');

            // Update buttons
            addDepartmentBtn.classList.remove('hidden');
            addOfficerBtn.classList.add('hidden');

            // Update state
            currentSubsection = 'departments';
            saveState();
        }

        // Show MIS Officers subsection
        function showMisOfficers() {
            // Update active states
            misOfficersTab.classList.add('tab-active', 'text-orange-dark');
            misOfficersTab.classList.remove('text-gray-500');
            departmentsTab.classList.remove('tab-active', 'text-orange-dark');
            departmentsTab.classList.add('text-gray-500');

            subsectionItems.forEach(item => {
                if (item.getAttribute('data-subsection') === 'mis-officers') {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Show/hide subsections
            misOfficersSubsection.classList.remove('hidden');
            departmentsSubsection.classList.add('hidden');

            // Update buttons
            addDepartmentBtn.classList.add('hidden');
            addOfficerBtn.classList.remove('hidden');

            // Load MIS Officers content
            loadMisOfficersContent();

            // Update state
            currentSubsection = 'mis-officers';
            saveState();
        }

        // Handle form submissions
        function handleFormSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

            // Send form data to PHP file
            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;

                    if (data.success) {
                        // Show success notification
                        showNotification(data.message || 'Operation completed successfully!');

                        // Reset form
                        form.reset();

                        // Reload content if needed
                        if (data.reload) {
                            loadMisOfficersContent();
                        }
                    } else {
                        // Show error notification
                        showNotification(data.message || 'An error occurred. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    // Reset button
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;

                    // Show error notification
                    showNotification('Network error. Please try again.', 'error');
                    console.error('Form submission error:', error);
                });
        }

        // Handle edit officer
        function handleEditOfficer(e) {
            const officerId = e.currentTarget.getAttribute('data-id');
            // Implement edit functionality
            showNotification('Edit functionality would open here', 'success');
        }

        // Handle delete officer
        function handleDeleteOfficer(e) {
            const officerId = e.currentTarget.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this MIS Officer?')) {
                // Implement delete functionality
                showNotification('Delete functionality would process here', 'success');
            }
        }

        // Load module content via AJAX
        function loadModuleContent(module) {
            // Show loading indicator
            moduleContentContainer.classList.remove('hidden');
            loadingIndicator.classList.remove('hidden');
            moduleContent.innerHTML = '';

            // Fetch the module content from PHP file
            fetch(`CEIT_Modules/${module}/${module}.php`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    // Hide loading indicator
                    loadingIndicator.classList.add('hidden');

                    // Insert the module content
                    moduleContent.innerHTML = html;

                    // Attach event listeners to the form
                    const form = moduleContent.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', handleFormSubmit);
                    }

                    // Attach event listeners to cancel buttons
                    const cancelButtons = moduleContent.querySelectorAll('.cancel-btn');
                    cancelButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            if (form) form.reset();
                        });
                    });
                })
                .catch(error => {
                    // Hide loading indicator
                    loadingIndicator.classList.add('hidden');

                    // Show error message
                    moduleContent.innerHTML = `
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
                    console.error('Error loading module:', error);
                });
        }

        // Show specific module
        function showModule(module) {
            // Update active states
            moduleItems.forEach(item => {
                if (item.getAttribute('data-module') === module) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Load module content
            loadModuleContent(module);

            // Update state
            currentModule = module;
            saveState();
        }

        // Event Listeners
        manageDepartmentsNav.addEventListener('click', (e) => {
            e.preventDefault();
            showManageDepartments();
            if (currentSubsection === 'mis-officers') {
                showMisOfficers();
            } else {
                showDepartments();
            }
        });

        postContentNav.addEventListener('click', (e) => {
            e.preventDefault();
            showPostContent();

            // If no module is selected, default to announcements
            if (!currentModule) {
                showModule('announcements');
            }
        });

        departmentsTab.addEventListener('click', () => {
            showDepartments();
        });

        misOfficersTab.addEventListener('click', () => {
            showMisOfficers();
        });

        subsectionItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const subsection = item.getAttribute('data-subsection');
                if (subsection === 'departments') {
                    showDepartments();
                } else if (subsection === 'mis-officers') {
                    showMisOfficers();
                }
            });
        });

        moduleItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const module = item.getAttribute('data-module');
                showModule(module);
            });
        });

        // Modal functionality
        addDepartmentBtn.addEventListener('click', () => {
            addDepartmentModal.classList.remove('hidden');
        });

        addOfficerBtn.addEventListener('click', () => {
            // This will open the modal for adding a new MIS Officer
            openAddModal();
        });

        closeModalBtn.addEventListener('click', () => {
            addDepartmentModal.classList.add('hidden');
        });

        cancelBtn.addEventListener('click', () => {
            addDepartmentModal.classList.add('hidden');
        });

        // Close modal when clicking outside
        addDepartmentModal.addEventListener('click', (e) => {
            if (e.target === addDepartmentModal) {
                addDepartmentModal.classList.add('hidden');
            }
        });

        // Create department button
        createDepartmentBtn.addEventListener('click', (e) => {
            e.preventDefault();
            addDepartmentModal.classList.add('hidden');
            showNotification('Department created successfully!');
        });

        // Initialize state on page load
        initializeState();
    </script>
    <script src="CEIT_Modules/Account/mis_officers.js"></script>
</body>

</html>