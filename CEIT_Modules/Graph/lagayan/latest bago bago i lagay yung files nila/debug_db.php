<?php
// Debug script to check database structure
include_once "../../db.php";

echo "<h2>Database Debug Information</h2>";

// Check connection
if (!$conn) {
    echo "<p style='color: red;'>Database connection failed: " . mysqli_connect_error() . "</p>";
    exit;
}

echo "<p style='color: green;'>Database connection successful</p>";

// Check if main_graph table exists
$tableCheckQuery = "SHOW TABLES LIKE 'main_graph'";
$tableCheckResult = $conn->query($tableCheckQuery);

if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
    echo "<p style='color: red;'>main_graph table does not exist</p>";
    
    // Show all tables
    echo "<h3>Available tables:</h3>";
    $allTablesQuery = "SHOW TABLES";
    $allTablesResult = $conn->query($allTablesQuery);
    if ($allTablesResult) {
        while ($row = $allTablesResult->fetch_array()) {
            echo "<p>" . $row[0] . "</p>";
        }
    }
} else {
    echo "<p style='color: green;'>main_graph table exists</p>";
    
    // Show table structure
    echo "<h3>main_graph table structure:</h3>";
    $structureQuery = "DESCRIBE main_graph";
    $structureResult = $conn->query($structureQuery);
    
    if ($structureResult) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structureResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show recent records
    echo "<h3>Recent records (last 5):</h3>";
    $recentQuery = "SELECT id, module, dept_id, user_id, description, graph_type, status, created_at FROM main_graph ORDER BY created_at DESC LIMIT 5";
    $recentResult = $conn->query($recentQuery);
    
    if ($recentResult && $recentResult->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Module</th><th>Dept ID</th><th>User ID</th><th>Description</th><th>Type</th><th>Status</th><th>Created</th></tr>";
        while ($row = $recentResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['module'] . "</td>";
            echo "<td>" . $row['dept_id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['description'] . "</td>";
            echo "<td>" . $row['graph_type'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No records found</p>";
    }
}

// Check modules table
echo "<h3>Modules table:</h3>";
$modulesQuery = "SELECT * FROM modules";
$modulesResult = $conn->query($modulesQuery);

if ($modulesResult && $modulesResult->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Description</th></tr>";
    while ($row = $modulesResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . ($row['description'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No modules found or modules table doesn't exist</p>";
}

$conn->close();
?>