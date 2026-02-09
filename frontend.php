<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Department Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
        <aside id="sidebar" class="sidebar w-64 bg-gradient-to-b from-orange-500 to-orange-600 text-white p-4 flex flex-col justify-between shadow-xl">
            <div>
                <div class="flex flex-col items-center mb-8">
                    <img src="https://via.placeholder.com/64" alt="School Logo" class="w-16 h-16 object-contain drop-shadow-[0_10px_10px_rgba(0,0,0,0.3)]" />
                    <h2 class="mt-4 text-lg font-bold">DEPARTMENT MANAGEMENT</h2>
                </div>
                <nav class="space-y-1">
                    <button data-tab="upload-announcements" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-bullhorn mr-3 w-5"></i> <span>Announcements</span>
                    </button>
                    <button data-tab="upload-memos" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-file-alt mr-3 w-5"></i> <span>Memos</span>
                    </button>
                    <button data-tab="upload-graphs" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-chart-pie mr-3 w-5"></i> <span>Graphs</span>
                    </button>
                    <button data-tab="upload-calendar" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-calendar-alt mr-3 w-5"></i> <span>University Calendar</span>
                    </button>
                    <button data-tab="upload-chart" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-sitemap mr-3 w-5"></i> <span>Organizational Chart</span>
                    </button>
                    <button data-tab="upload-archive" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-archive mr-3 w-5"></i> <span>Archive</span>
                    </button>
                    <button data-tab="upload-about" class="upload-tab-btn sidebar-item w-full text-left p-3 text-sm hover:bg-orange-700 rounded-lg transition duration-200 flex items-center">
                        <i class="fas fa-info-circle mr-3 w-5"></i> <span>About Department</span>
                    </button>
                </nav>
            </div>
            <div class="mt-6 p-3 bg-orange-700 rounded-lg">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-orange-800 flex items-center justify-center">
                        <i class="fas fa-user-tie text-white"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium text-sm">User Name</p>
                        <p class="text-xs opacity-80">Department Officer</p>
                    </div>
                </div>
                <!-- Logout Button -->
                <form action="#" method="POST" class="mt-3">
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
                        <div class="text-sm text-gray-500">Monday, January 1, 2024</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6">
                    <div id="uploadContent">
                        <div id="upload-announcements" class="tab-content active">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Announcements</h2>
                                <p class="text-gray-600">Create and manage department announcements</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Announcements content will appear here</p>
                            </div>
                        </div>
                        <div id="upload-memos" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Memos</h2>
                                <p class="text-gray-600">Create and manage department memos</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Memos content will appear here</p>
                            </div>
                        </div>
                        <div id="upload-graphs" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Graphs</h2>
                                <p class="text-gray-600">Create and manage department graphs</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Graphs content will appear here</p>
                            </div>
                        </div>
                        <div id="upload-calendar" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">University Calendar</h2>
                                <p class="text-gray-600">Manage university calendar events</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Calendar content will appear here</p>
                            </div>
                        </div>
                        <div id="upload-chart" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Organizational Chart</h2>
                                <p class="text-gray-600">Manage department organizational chart</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Organizational chart content will appear here</p>
                            </div>
                        </div>
                        <div id="upload-archive" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">Archive</h2>
                                <p class="text-gray-600">View archived content</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <p class="text-gray-500 text-center">Archive content will appear here</p>
                            </div>
                        </div>
                        <div id="upload-about" class="tab-content">
                            <div class="mb-6">
                                <h2 class="text-xl md:text-2xl font-bold text-gray-800">About Department</h2>
                                <p class="text-gray-600">Manage department information, mission, vision, and other details</p>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- About Department Section -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-university text-orange-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">About The Department</h3>
                                    </div>
                                    <div id="ad-container">
                                        <textarea
                                            id="ad-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-orange-500 transition-colors"
                                            rows="12"
                                            disabled>Department information will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="ad-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="ad-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="ad-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                                <!-- Mission Section -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-bullseye text-blue-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">Mission</h3>
                                    </div>
                                    <div id="mission-container">
                                        <textarea
                                            id="mission-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-blue-500 transition-colors"
                                            rows="10"
                                            disabled>Mission statement will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="mission-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="mission-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="mission-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                                <!-- Vision Section -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-eye text-purple-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">Vision</h3>
                                    </div>
                                    <div id="vision-container">
                                        <textarea
                                            id="vision-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-purple-500 transition-colors"
                                            rows="4"
                                            disabled>Vision statement will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="vision-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="vision-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="vision-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                                <!-- Quality Policy Section -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-certificate text-green-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">Quality Policy</h3>
                                    </div>
                                    <div id="qp-container">
                                        <textarea
                                            id="qp-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-green-500 transition-colors"
                                            rows="4"
                                            disabled>Quality policy will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="qp-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="qp-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="qp-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                                <!-- College Goals -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-trophy text-yellow-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">College Goals</h3>
                                    </div>
                                    <div id="cg-container">
                                        <textarea
                                            id="cg-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-yellow-500 transition-colors"
                                            rows="6"
                                            disabled>College goals will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="cg-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="cg-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="cg-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                                <!-- Core Values -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-star text-indigo-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">Core Values</h3>
                                    </div>
                                    <div id="cv-container">
                                        <textarea
                                            id="cv-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-indigo-500 transition-colors"
                                            rows="6"
                                            disabled>Core values will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="cv-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="cv-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="cv-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                                <!-- Program Offerings -->
                                <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow">
                                    <div class="flex items-center mb-4">
                                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-graduation-cap text-red-600"></i>
                                        </div>
                                        <h3 class="text-lg lg:text-xl font-semibold text-gray-800">Program Offerings</h3>
                                    </div>
                                    <div id="po-container">
                                        <textarea
                                            id="po-textarea"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-red-500 transition-colors"
                                            rows="8"
                                            disabled>Program offerings will appear here</textarea>
                                        <div class="flex justify-end mt-4">
                                            <button
                                                id="po-edit-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                                                <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                                            </button>
                                            <button
                                                id="po-save-btn"
                                                class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                                                <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                                            </button>
                                        </div>
                                        <div id="po-message" class="mt-3 hidden"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');

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

            // Close sidebar when a menu item is clicked (on mobile)
            const sidebarItems = document.querySelectorAll('.upload-tab-btn');
            sidebarItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 1024) {
                        sidebar.classList.remove('open');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });

            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.upload-tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');

                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');

                    // Update page title based on active tab
                    const pageTitle = document.getElementById('pageTitle');
                    switch (tabId) {
                        case 'upload-announcements':
                            pageTitle.textContent = 'Announcements';
                            break;
                        case 'upload-memos':
                            pageTitle.textContent = 'Memos';
                            break;
                        case 'upload-graphs':
                            pageTitle.textContent = 'Graphs';
                            break;
                        case 'upload-calendar':
                            pageTitle.textContent = 'University Calendar';
                            break;
                        case 'upload-chart':
                            pageTitle.textContent = 'Organizational Chart';
                            break;
                        case 'upload-archive':
                            pageTitle.textContent = 'Archive';
                            break;
                        case 'upload-about':
                            pageTitle.textContent = 'About Department';
                            break;
                        default:
                            pageTitle.textContent = 'Department Dashboard';
                    }
                });
            });

            // Add a small delay to ensure all included scripts are ready
            setTimeout(function() {
                console.log('Initializing tabs');
                // Trigger a click on the default tab if none is active
                if (!document.querySelector('.upload-tab-btn.active')) {
                    const defaultTab = document.querySelector('[data-tab="upload-announcements"]');
                    if (defaultTab) defaultTab.click();
                }
            }, 100);
        });
    </script>
</body>

</html>