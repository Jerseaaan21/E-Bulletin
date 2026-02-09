<?php
session_start();
if (!isset($_SESSION['user_info'])) {
    header("Location: ../../logout.php");
    exit;
}

require_once '../../db.php';

// Get department ID from session
$deptId = $_SESSION['dept_id'] ?? null;
if (!$deptId) {
    header("Location: ../../login.php");
    exit;
}

// Get module ID for About
$moduleQuery = "SELECT id FROM modules WHERE name = 'About' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? null;

if (!$moduleId) {
    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        About module not found. Please contact administrator.
                    </p>
                </div>
            </div>
          </div>';
    exit;
}

// Fetch department posts, ordered by order_position
$query = "SELECT * FROM department_post WHERE dept_id = ? AND module = ? ORDER BY order_position ASC, id ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $deptId, $moduleId);
$stmt->execute();
$result = $stmt->get_result();
$posts = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="mb-6">
    <h2 class="text-xl md:text-2xl font-bold text-gray-800">About Department</h2>
    <p class="text-gray-600">Manage department information, mission, vision, and other details</p>
</div>

<div class="mb-4 flex justify-between items-center">
    <button id="add-new-post-btn" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition duration-200">
        <i class="fas fa-plus mr-2"></i> Add New Section
    </button>
    <div class="text-sm text-gray-600">
        <i class="fas fa-info-circle mr-1"></i> Use the arrow buttons to reorder sections
    </div>
</div>

<div id="posts-container" class="sortable-container">
    <?php if (empty($posts)): ?>
        <div class="bg-gray-50 p-6 rounded-lg text-center">
            <p class="text-gray-500">No department information available. Click "Add New Section" to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($posts as $index => $post): ?>
            <div class="content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow mb-6 sortable-item" 
                 data-post-id="<?php echo $post['id']; ?>" 
                 data-order="<?php echo $post['order_position'] ?? $index + 1; ?>">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                        <i class="fas fa-file-alt text-orange-600"></i>
                    </div>
                    <h3 class="text-lg lg:text-xl font-semibold text-gray-800 flex-grow"><?php echo htmlspecialchars($post['description']); ?></h3>
                    <!-- Order Controls -->
                    <div class="order-controls flex flex-col gap-1 mr-3">
                        <button class="move-up-btn p-1 text-gray-400 hover:text-orange-500 transition-colors" title="Move Up">
                            <i class="fas fa-chevron-up text-sm"></i>
                        </button>
                        <button class="move-down-btn p-1 text-gray-400 hover:text-orange-500 transition-colors" title="Move Down">
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="post-container">
                    <textarea
                        class="post-textarea w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-orange-500 transition-colors"
                        rows="8"
                        disabled><?php echo htmlspecialchars($post['content']); ?></textarea>
                    <div class="flex justify-end mt-4">
                        <button
                            class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                            <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                        </button>
                        <button
                            class="save-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                            <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                        </button>
                        <button
                            class="delete-btn px-3 py-1 lg:px-4 lg:py-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                            <i class="fas fa-trash"></i> <span class="hidden sm:inline">Delete</span>
                        </button>
                    </div>
                    <div class="post-message mt-3 hidden"></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal for adding a new post -->
<div id="add-post-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Add New Section</h3>
        <form id="add-post-form">
            <div class="mb-4">
                <label for="post-description" class="block text-sm font-medium text-gray-700 mb-1">Section Title (e.g., Mission, Vision)</label>
                <input type="text" id="post-description" name="description" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" required>
            </div>
            <div class="mb-4">
                <label for="post-content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                <textarea id="post-content" name="content" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" required></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancel-add-post" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Flag to prevent multiple initializations
    let aboutModuleInitialized = false;

    // Function to initialize the About module
    function initializeAboutModule() {
        // Prevent multiple initializations
        if (aboutModuleInitialized) return;
        aboutModuleInitialized = true;

        console.log('Initializing About module');

        const addNewPostBtn = document.getElementById('add-new-post-btn');
        const addPostModal = document.getElementById('add-post-modal');
        const cancelAddPost = document.getElementById('cancel-add-post');
        const addPostForm = document.getElementById('add-post-form');
        const postsContainer = document.getElementById('posts-container');

        // Flag to prevent multiple submissions
        let isSubmitting = false;

        // Show add post modal
        if (addNewPostBtn) {
            addNewPostBtn.addEventListener('click', function() {
                console.log('Add new post button clicked');
                addPostModal.classList.remove('hidden');
            });
        }

        // Hide add post modal
        if (cancelAddPost) {
            cancelAddPost.addEventListener('click', function() {
                console.log('Cancel add post button clicked');
                addPostModal.classList.add('hidden');
                addPostForm.reset();
            });
        }

        // Handle add new post form submission
        if (addPostForm) {
            addPostForm.addEventListener('submit', function(e) {
                console.log('Add post form submitted');
                e.preventDefault();

                // Prevent multiple submissions
                if (isSubmitting) return;
                isSubmitting = true;

                const description = document.getElementById('post-description').value;
                const content = document.getElementById('post-content').value;

                // Send AJAX request to add new post
                fetch('Modules/About/save_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=add&description=${encodeURIComponent(description)}&content=${encodeURIComponent(content)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        isSubmitting = false; // Reset flag
                        if (data.success) {
                            // Hide modal and reset form
                            addPostModal.classList.add('hidden');
                            addPostForm.reset();

                            // Instead of reloading, add the new post to the DOM
                            addNewPostToDOM(data.post_id, description, content, data.order_position);
                        } else {
                            alert('Error adding post: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        isSubmitting = false; // Reset flag
                        alert('An error occurred while adding the post.');
                    });
            });
        }

        // Function to update order after drag and drop
        function updateOrder() {
            const items = postsContainer.querySelectorAll('.sortable-item');
            const orderData = [];

            items.forEach((item, index) => {
                const postId = item.getAttribute('data-post-id');
                orderData.push({
                    id: postId,
                    order: index + 1
                });
                item.setAttribute('data-order', index + 1);
            });

            // Send order update to server
            fetch('Modules/About/save_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_order',
                    order_data: orderData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('Order updated successfully!');
                } else {
                    console.error('Error updating order:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Function to move item up or down
        function moveItem(item, direction) {
            const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
            
            if (sibling && sibling.classList.contains('sortable-item')) {
                if (direction === 'up') {
                    item.parentNode.insertBefore(item, sibling);
                } else {
                    item.parentNode.insertBefore(sibling, item);
                }
                updateOrder();
            }
        }

        // Function to show success message
        function showSuccessMessage(message) {
            const successMessage = document.createElement('div');
            successMessage.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg';
            successMessage.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(successMessage);

            // Hide message after 3 seconds
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    if (document.body.contains(successMessage)) {
                        document.body.removeChild(successMessage);
                    }
                }, 300);
            }, 3000);
        }

        // Function to add a new post to the DOM without reloading
        function addNewPostToDOM(postId, description, content, orderPosition) {
            console.log('Adding new post to DOM');
            // If there's a "no posts" message, remove it
            const noPostsMessage = postsContainer.querySelector('.bg-gray-50');
            if (noPostsMessage && noPostsMessage.textContent.includes('No department information')) {
                noPostsMessage.remove();
            }

            // Create new post element
            const newPost = document.createElement('div');
            newPost.className = 'content-card bg-gray-50 p-4 lg:p-6 rounded-xl shadow mb-6 sortable-item';
            newPost.setAttribute('data-post-id', postId);
            newPost.setAttribute('data-order', orderPosition || 1);

            newPost.innerHTML = `
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                    <i class="fas fa-file-alt text-orange-600"></i>
                </div>
                <h3 class="text-lg lg:text-xl font-semibold text-gray-800 flex-grow">${description}</h3>
                <!-- Order Controls -->
                <div class="order-controls flex flex-col gap-1 mr-3">
                    <button class="move-up-btn p-1 text-gray-400 hover:text-orange-500 transition-colors" title="Move Up">
                        <i class="fas fa-chevron-up text-sm"></i>
                    </button>
                    <button class="move-down-btn p-1 text-gray-400 hover:text-orange-500 transition-colors" title="Move Down">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </button>
                </div>
            </div>
            <div class="post-container">
                <textarea
                    class="post-textarea w-full border border-gray-300 rounded-lg p-4 focus:outline-none focus:border-orange-500 transition-colors"
                    rows="8"
                    disabled>${content}</textarea>
                <div class="flex justify-end mt-4">
                    <button
                        class="edit-btn px-3 py-1 lg:px-4 lg:py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-500 hover:text-white flex items-center gap-2 text-sm">
                        <i class="fas fa-pen"></i> <span class="hidden sm:inline">Edit</span>
                    </button>
                    <button
                        class="save-btn px-3 py-1 lg:px-4 lg:py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                        <i class="fas fa-save"></i> <span class="hidden sm:inline">Save</span>
                    </button>
                    <button
                        class="delete-btn px-3 py-1 lg:px-4 lg:py-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white hidden flex items-center gap-2 ml-2 text-sm">
                        <i class="fas fa-trash"></i> <span class="hidden sm:inline">Delete</span>
                    </button>
                </div>
                <div class="post-message mt-3 hidden"></div>
            </div>
        `;

            // Add to the container
            postsContainer.appendChild(newPost);

            showSuccessMessage('Section added successfully!');
        }

        // Use event delegation for edit, save, delete, and move buttons
        if (postsContainer) {
            console.log('Setting up event delegation for posts container');
            postsContainer.addEventListener('click', function(e) {
                console.log('Click event in posts container');

                // Check if the clicked element is a move up button
                if (e.target.closest('.move-up-btn')) {
                    const button = e.target.closest('.move-up-btn');
                    const item = button.closest('.sortable-item');
                    moveItem(item, 'up');
                    return;
                }

                // Check if the clicked element is a move down button
                if (e.target.closest('.move-down-btn')) {
                    const button = e.target.closest('.move-down-btn');
                    const item = button.closest('.sortable-item');
                    moveItem(item, 'down');
                    return;
                }

                // Check if the clicked element is an edit button
                if (e.target.closest('.edit-btn')) {
                    console.log('Edit button clicked');
                    const button = e.target.closest('.edit-btn');
                    const postContainer = button.closest('.post-container');
                    const textarea = postContainer.querySelector('.post-textarea');
                    const saveBtn = postContainer.querySelector('.save-btn');
                    const deleteBtn = postContainer.querySelector('.delete-btn');

                    // Enable textarea and show save button
                    textarea.disabled = false;
                    button.classList.add('hidden');
                    saveBtn.classList.remove('hidden');
                    deleteBtn.classList.remove('hidden');
                }

                // Check if the clicked element is a save button
                if (e.target.closest('.save-btn')) {
                    console.log('Save button clicked');
                    const button = e.target.closest('.save-btn');
                    const postContainer = button.closest('.post-container');
                    const textarea = postContainer.querySelector('.post-textarea');
                    const editBtn = postContainer.querySelector('.edit-btn');
                    const deleteBtn = postContainer.querySelector('.delete-btn');
                    const messageDiv = postContainer.querySelector('.post-message');
                    const postId = button.closest('.content-card').getAttribute('data-post-id');

                    // Prevent multiple submissions
                    if (isSubmitting) return;
                    isSubmitting = true;

                    // Get updated content
                    const content = textarea.value;

                    // Send AJAX request to update post
                    fetch('Modules/About/save_post.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=update&id=${postId}&content=${encodeURIComponent(content)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            isSubmitting = false; // Reset flag
                            if (data.success) {
                                // Disable textarea and show edit button
                                textarea.disabled = true;
                                button.classList.add('hidden');
                                editBtn.classList.remove('hidden');
                                deleteBtn.classList.add('hidden');

                                // Show success message
                                messageDiv.className = 'mt-3 p-3 bg-green-100 text-green-700 rounded-lg';
                                messageDiv.textContent = 'Section updated successfully!';
                                messageDiv.classList.remove('hidden');

                                // Hide message after 3 seconds
                                setTimeout(() => {
                                    messageDiv.classList.add('hidden');
                                }, 3000);
                            } else {
                                // Show error message
                                messageDiv.className = 'mt-3 p-3 bg-red-100 text-red-700 rounded-lg';
                                messageDiv.textContent = 'Error updating section: ' + data.message;
                                messageDiv.classList.remove('hidden');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            isSubmitting = false; // Reset flag
                            // Show error message
                            messageDiv.className = 'mt-3 p-3 bg-red-100 text-red-700 rounded-lg';
                            messageDiv.textContent = 'An error occurred while updating the section.';
                            messageDiv.classList.remove('hidden');
                        });
                }

                // Check if the clicked element is a delete button
                if (e.target.closest('.delete-btn')) {
                    console.log('Delete button clicked');
                    const button = e.target.closest('.delete-btn');
                    if (confirm('Are you sure you want to delete this section?')) {
                        const postContainer = button.closest('.post-container');
                        const messageDiv = postContainer.querySelector('.post-message');
                        const postId = button.closest('.content-card').getAttribute('data-post-id');

                        // Prevent multiple submissions
                        if (isSubmitting) return;
                        isSubmitting = true;

                        // Send AJAX request to delete post
                        fetch('Modules/About/save_post.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=delete&id=${postId}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                isSubmitting = false; // Reset flag
                                if (data.success) {
                                    // Remove the post from the DOM
                                    button.closest('.content-card').remove();

                                    // Update order after deletion
                                    updateOrder();

                                    showSuccessMessage('Section deleted successfully!');
                                } else {
                                    // Show error message
                                    messageDiv.className = 'mt-3 p-3 bg-red-100 text-red-700 rounded-lg';
                                    messageDiv.textContent = 'Error deleting section: ' + data.message;
                                    messageDiv.classList.remove('hidden');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                isSubmitting = false; // Reset flag
                                // Show error message
                                messageDiv.className = 'mt-3 p-3 bg-red-100 text-red-700 rounded-lg';
                                messageDiv.textContent = 'An error occurred while deleting the section.';
                                messageDiv.classList.remove('hidden');
                            });
                    }
                }
            });
        }
    }

    // Initialize the module
    console.log('About module script loaded');
    initializeAboutModule();

    // Make the function globally available for reinitialization
    window.initializeAboutModule = initializeAboutModule;
</script>

<style>
    .sortable-container {
        min-height: 100px;
    }

    .sortable-item {
        transition: all 0.3s ease;
    }

    .sortable-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .order-controls {
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .content-card:hover .order-controls {
        opacity: 1;
    }

    .move-up-btn:hover,
    .move-down-btn:hover {
        transform: scale(1.2);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .order-controls {
            opacity: 1;
        }
    }
</style>