<?php
// Debug script to check event handling in Graph module
?>
<!DOCTYPE html>
<html>
<head>
    <title>Graph Module Event Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        button { margin: 5px; padding: 10px; }
    </style>
</head>
<body>
    <h1>Graph Module Event Debug Tool</h1>
    
    <div class="debug-section">
        <h2>Module State Debug</h2>
        <button onclick="checkModuleState()">Check Module State</button>
        <button onclick="resetModuleState()">Reset Module State</button>
        <div id="module-state-results"></div>
    </div>
    
    <div class="debug-section">
        <h2>Event Listener Test</h2>
        <button id="test-add-btn">Test Add Graph Button</button>
        <button id="test-upload-btn">Test Upload Button</button>
        <button id="test-edit-btn" class="graph-edit-btn" data-id="1" data-description="Test Graph" data-graph-type="individual" data-status="pending" data-index="0">Test Edit Button</button>
        <div id="test-results"></div>
    </div>

    <script>
        // Module state functions
        function checkModuleState() {
            const results = document.getElementById('module-state-results');
            results.innerHTML = '<h3>Module State Check...</h3>';
            
            // Check global variables
            const checks = [
                { name: 'window.graphModuleInitialized', value: window.graphModuleInitialized },
                { name: 'window.globalEventDelegationInitialized', value: window.globalEventDelegationInitialized },
                { name: 'window.initializationAttempts', value: window.initializationAttempts },
                { name: 'resetGraphModuleState function', value: typeof window.resetGraphModuleState === 'function' }
            ];
            
            checks.forEach(check => {
                const status = check.value ? 'success' : 'error';
                results.innerHTML += `<div class="${status}">${check.name}: ${check.value}</div>`;
            });
            
            // Check DOM elements
            const elements = [
                { name: 'graph-add-btn', element: document.getElementById('graph-add-btn') },
                { name: 'graph-upload-btn', element: document.getElementById('graph-upload-btn') },
                { name: 'graph-add-modal', element: document.getElementById('graph-add-modal') },
                { name: 'graph-upload-modal', element: document.getElementById('graph-upload-modal') }
            ];
            
            results.innerHTML += '<h4>DOM Elements:</h4>';
            elements.forEach(item => {
                const status = item.element ? 'success' : 'error';
                results.innerHTML += `<div class="${status}">${item.name}: ${item.element ? 'EXISTS' : 'NOT FOUND'}</div>`;
            });
        }
        
        function resetModuleState() {
            if (typeof window.resetGraphModuleState === 'function') {
                window.resetGraphModuleState();
                document.getElementById('module-state-results').innerHTML = '<div class="success">Module state reset successfully</div>';
            } else {
                document.getElementById('module-state-results').innerHTML = '<div class="error">Reset function not available</div>';
            }
        }

        // Test the global event delegation system
        function testEventDelegation() {
            const results = document.getElementById('test-results');
            results.innerHTML = '<h3>Testing Event Delegation...</h3>';
            
            // Test if global event delegation is working
            let tests = [];
            
            // Test 1: Check if buttons exist
            const addBtn = document.getElementById('graph-add-btn');
            const uploadBtn = document.getElementById('graph-upload-btn');
            
            tests.push({
                name: 'Add Button Exists',
                result: addBtn !== null,
                element: addBtn
            });
            
            tests.push({
                name: 'Upload Button Exists', 
                result: uploadBtn !== null,
                element: uploadBtn
            });
            
            // Test 2: Check if modals exist
            const addModal = document.getElementById('graph-add-modal');
            const uploadModal = document.getElementById('graph-upload-modal');
            
            tests.push({
                name: 'Add Modal Exists',
                result: addModal !== null,
                element: addModal
            });
            
            tests.push({
                name: 'Upload Modal Exists',
                result: uploadModal !== null,
                element: uploadModal
            });
            
            // Display results
            tests.forEach(test => {
                const status = test.result ? 'success' : 'error';
                const statusText = test.result ? 'PASS' : 'FAIL';
                results.innerHTML += `<div class="${status}">${test.name}: ${statusText}</div>`;
            });
            
            // Test event listeners
            results.innerHTML += '<h4>Event Listener Tests:</h4>';
            
            // Check if global event delegation is initialized
            if (typeof globalEventDelegationInitialized !== 'undefined') {
                results.innerHTML += `<div class="info">Global Event Delegation: ${globalEventDelegationInitialized ? 'INITIALIZED' : 'NOT INITIALIZED'}</div>`;
            } else {
                results.innerHTML += '<div class="error">Global Event Delegation variable not found</div>';
            }
            
            // Test clicking buttons programmatically
            results.innerHTML += '<h4>Button Click Tests:</h4>';
            
            if (addBtn) {
                try {
                    addBtn.click();
                    results.innerHTML += '<div class="success">Add button click: SUCCESS</div>';
                } catch (e) {
                    results.innerHTML += `<div class="error">Add button click: ERROR - ${e.message}</div>`;
                }
            }
        }
        
        // Test buttons
        document.getElementById('test-add-btn').addEventListener('click', function() {
            const addBtn = document.getElementById('graph-add-btn');
            if (addBtn) {
                addBtn.click();
                document.getElementById('test-results').innerHTML += '<div class="info">Triggered Add Graph button</div>';
            } else {
                document.getElementById('test-results').innerHTML += '<div class="error">Add Graph button not found</div>';
            }
        });
        
        document.getElementById('test-upload-btn').addEventListener('click', function() {
            const uploadBtn = document.getElementById('graph-upload-btn');
            if (uploadBtn) {
                uploadBtn.click();
                document.getElementById('test-results').innerHTML += '<div class="info">Triggered Upload button</div>';
            } else {
                document.getElementById('test-results').innerHTML += '<div class="error">Upload button not found</div>';
            }
        });
        
        document.getElementById('test-edit-btn').addEventListener('click', function() {
            document.getElementById('test-results').innerHTML += '<div class="info">Edit button test clicked</div>';
        });
        
        // Run tests on page load
        setTimeout(testEventDelegation, 1000);
    </script>
</body>
</html>