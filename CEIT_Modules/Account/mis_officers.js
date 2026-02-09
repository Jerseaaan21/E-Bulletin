// MIS Officers Management JavaScript

// MIS Officers Modal Functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New MIS Officer';
    document.getElementById('submitBtn').textContent = 'Add Officer';
    document.getElementById('accountForm').reset();
    document.getElementById('accountId').value = '';
    document.getElementById('accountModal').classList.remove('hidden');
}

function editAccount(id, name, email, deptId) {
    document.getElementById('modalTitle').textContent = 'Edit MIS Officer';
    document.getElementById('submitBtn').textContent = 'Update Officer';
    document.getElementById('accountId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('email').value = email;
    document.getElementById('dept_id').value = deptId || '';
    document.getElementById('accountModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('accountModal').classList.add('hidden');
}

function deleteAccount(id) {
    if (confirm('Are you sure you want to delete this MIS Officer?')) {
        const messageDiv = document.getElementById('message-' + id);
        messageDiv.textContent = "Deleting...";
        messageDiv.classList.remove('hidden', 'text-green-600', 'text-red-600');
        
        fetch('CEIT_modules/Account/manage_mis_officers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `delete_mis_account=1&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                messageDiv.textContent = "Officer deleted successfully!";
                messageDiv.classList.add('text-green-600');
                
                // Remove the card after a short delay
                setTimeout(() => {
                    loadMisOfficersContent();
                }, 1500);
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.textContent = "Error deleting. Please try again.";
            messageDiv.classList.add('text-red-600');
        });
    }
}

// Form submission handler for MIS Officers
function setupMISFormHandler() {
    const accountForm = document.getElementById('accountForm');
    if (accountForm) {
        accountForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const accountId = document.getElementById('accountId').value;
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const role = document.getElementById('role').value;
            const deptId = document.getElementById('dept_id').value;
            
            // Validate inputs
            if (!name.trim() || !email.trim()) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append(accountId ? 'update_mis_account' : 'add_mis_account', '1');
            if (accountId) formData.append('id', accountId);
            formData.append('name', name);
            formData.append('email', email);
            formData.append('role', role);
            if (deptId) formData.append('dept_id', deptId);
            
            // Send AJAX request
            fetch('CEIT_modules/Account/manage_mis_officers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                
                if (data.status === 'success') {
                    // Show success message
                    if (accountId) {
                        const messageDiv = document.getElementById('message-' + accountId);
                        if (messageDiv) {
                            messageDiv.textContent = "Officer updated successfully!";
                            messageDiv.classList.remove('hidden', 'text-red-600');
                            messageDiv.classList.add('text-green-600');
                        }
                    }
                    
                    // Close modal and reload content after a short delay
                    closeModal();
                    setTimeout(() => {
                        loadMisOfficersContent();
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + (error.message || 'Unknown error occurred'));
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }
    
    // Close modal when clicking outside
    const accountModal = document.getElementById('accountModal');
    if (accountModal) {
        accountModal.addEventListener('click', function(e) {
            if (e.target === accountModal) {
                closeModal();
            }
        });
    }
}

// Load MIS Officers content via AJAX
function loadMisOfficersContent() {
    // Show loading indicator
    const misOfficersContent = document.getElementById('misOfficersContent');
    misOfficersContent.innerHTML = `
        <div class="flex justify-center items-center py-12">
            <div class="loading-spinner"></div>
        </div>
    `;
    
    // Fetch the MIS Officers content from PHP file
    fetch('CEIT_modules/Account/manage_mis_officers.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            // Insert the MIS Officers content
            misOfficersContent.innerHTML = html;
            
            // Set up the form handler
            setupMISFormHandler();
        })
        .catch(error => {
            // Show error message
            misOfficersContent.innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-500 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                Error loading MIS Officers. Please try again later.
                            </p>
                        </div>
                    </div>
                </div>
            `;
            console.error('Error loading MIS Officers:', error);
        });
}