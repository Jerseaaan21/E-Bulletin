<?php
// Debug script to see what requests are being made
session_start();
include "../../db.php";

echo "<h2>Request Debug Tool</h2>";

// Show session info
echo "<h3>Session Information:</h3>";
if (isset($_SESSION['user_info'])) {
    echo "<pre>" . print_r($_SESSION['user_info'], true) . "</pre>";
} else {
    echo "<p style='color: red;'>No session found</p>";
}

// Show recent graphs with their actual data attributes
$deptId = $_SESSION['user_info']['dept_id'] ?? 1;
$query = "SELECT id, description, graph_type, status, created_at FROM main_graph WHERE dept_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Recent Graphs with Test Buttons:</h3>";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<h4>Graph ID: " . $row['id'] . " - " . htmlspecialchars($row['description']) . "</h4>";
        echo "<p>Type: " . $row['graph_type'] . " | Status: " . $row['status'] . " | Created: " . $row['created_at'] . "</p>";
        
        // Test buttons that mimic the actual interface
        echo "<div>";
        echo "<button onclick='testArchive(" . $row['id'] . ", \"" . htmlspecialchars($row['description']) . "\", \"" . $row['status'] . "\")' style='margin: 5px; padding: 5px 10px; background: #f59e0b; color: white; border: none; cursor: pointer;'>Test Archive</button>";
        echo "<button onclick='testDelete(" . $row['id'] . ", \"" . htmlspecialchars($row['description']) . "\", \"" . $row['status'] . "\")' style='margin: 5px; padding: 5px 10px; background: #ef4444; color: white; border: none; cursor: pointer;'>Test Delete</button>";
        echo "<button onclick='testEdit(" . $row['id'] . ", \"" . htmlspecialchars($row['description']) . "\", \"" . $row['graph_type'] . "\", \"" . $row['status'] . "\")' style='margin: 5px; padding: 5px 10px; background: #10b981; color: white; border: none; cursor: pointer;'>Test Edit</button>";
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "<p>No graphs found</p>";
}

// Show what the actual buttons in the interface look like
echo "<h3>Sample Button HTML (like in the actual interface):</h3>";
echo "<pre>";
echo htmlspecialchars('<button class="graph-archive-btn" data-index="0" data-id="123" data-description="Test Graph" data-status="active">Archive</button>');
echo "\n";
echo htmlspecialchars('<button class="graph-delete-btn" data-index="0" data-id="123" data-description="Test Graph" data-status="active">Delete</button>');
echo "\n";
echo htmlspecialchars('<button class="graph-edit-btn" data-index="0" data-id="123" data-description="Test Graph" data-graph-type="pie" data-status="active">Edit</button>');
echo "</pre>";

?>

<script>
function testArchive(id, description, status) {
    console.log('Testing archive with:', {id, description, status});
    
    fetch('archive_graph.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(response => {
        console.log('Archive response status:', response.status);
        console.log('Archive response headers:', response.headers);
        return response.text();
    })
    .then(data => {
        console.log('Archive response data:', data);
        alert('Archive Response (Status: ' + (data.includes('success') ? 'Success' : 'Error') + '):\n' + data);
    })
    .catch(error => {
        console.error('Archive error:', error);
        alert('Archive Error: ' + error.message);
    });
}

function testDelete(id, description, status) {
    console.log('Testing delete with:', {id, description, status});
    
    if (!confirm('Really delete graph ID ' + id + '?')) return;
    
    fetch('delete_graph.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(response => {
        console.log('Delete response status:', response.status);
        console.log('Delete response headers:', response.headers);
        return response.text();
    })
    .then(data => {
        console.log('Delete response data:', data);
        alert('Delete Response (Status: ' + (data.includes('success') ? 'Success' : 'Error') + '):\n' + data);
        if (data.includes('success')) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Delete Error: ' + error.message);
    });
}

function testEdit(id, description, graphType, status) {
    console.log('Testing edit with:', {id, description, graphType, status});
    
    const newDescription = prompt('Enter new description for graph ID ' + id + ':', description);
    if (!newDescription) return;
    
    fetch('update_graph.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(id) + '&description=' + encodeURIComponent(newDescription) + '&graphType=' + encodeURIComponent(graphType)
    })
    .then(response => {
        console.log('Edit response status:', response.status);
        console.log('Edit response headers:', response.headers);
        return response.text();
    })
    .then(data => {
        console.log('Edit response data:', data);
        alert('Edit Response (Status: ' + (data.includes('success') ? 'Success' : 'Error') + '):\n' + data);
        if (data.includes('success')) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Edit error:', error);
        alert('Edit Error: ' + error.message);
    });
}

// Test if the files exist
function testFileExistence() {
    const files = ['archive_graph.php', 'delete_graph.php', 'update_graph.php'];
    
    files.forEach(file => {
        fetch(file, {method: 'HEAD'})
        .then(response => {
            console.log(file + ' exists:', response.status === 200 || response.status === 405);
        })
        .catch(error => {
            console.log(file + ' error:', error);
        });
    });
}

// Run file existence test
testFileExistence();
</script>

<style>
button:hover {
    opacity: 0.8;
}
</style>