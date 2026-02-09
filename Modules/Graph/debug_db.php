<?php
// Debug script to check current database state
include_once "../../db.php";

echo "<h2>Database Debug - Graph Table</h2>";

// Check connection
if (!$conn) {
    echo "<p style='color: red;'>Database connection failed: " . mysqli_connect_error() . "</p>";
    exit;
}

echo "<p style='color: green;'>Database connection successful</p>";

// Check if table exists
$tableCheckQuery = "SHOW TABLES LIKE 'graph'";
$tableCheckResult = $conn->query($tableCheckQuery);

if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
    echo "<p style='color: red;'>❌ graph table does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>✅ graph table exists</p>";

// Show table structure
echo "<h3>Table Structure:</h3>";
$structureQuery = "DESCRIBE graph";
$structureResult = $conn->query($structureQuery);

if ($structureResult) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structureResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show table status
echo "<h3>Table Status:</h3>";
$statusQuery = "SHOW TABLE STATUS LIKE 'graph'";
$statusResult = $conn->query($statusQuery);

if ($statusResult && $statusResult->num_rows > 0) {
    $status = $statusResult->fetch_assoc();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Property</th><th>Value</th></tr>";
    echo "<tr><td>Auto Increment</td><td>" . $status['Auto_increment'] . "</td></tr>";
    echo "<tr><td>Rows</td><td>" . $status['Rows'] . "</td></tr>";
    echo "<tr><td>Engine</td><td>" . $status['Engine'] . "</td></tr>";
    echo "<tr><td>Collation</td><td>" . $status['Collation'] . "</td></tr>";
    echo "</table>";
}

// Show all records
echo "<h3>All Records:</h3>";
$allQuery = "SELECT * FROM graph ORDER BY id";
$allResult = $conn->query($allQuery);

if ($allResult && $allResult->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Module</th><th>Dept ID</th><th>User ID</th><th>Description</th><th>Type</th><th>Status</th><th>Created At</th></tr>";
    while ($row = $allResult->fetch_assoc()) {
        $rowStyle = ($row['id'] == 0) ? "background-color: #ffcccc;" : "";
        echo "<tr style='$rowStyle'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['module'] . "</td>";
        echo "<td>" . $row['dept_id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['description'], 0, 30)) . "...</td>";
        echo "<td>" . $row['graph_type'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . ($row['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($allResult->num_rows > 0) {
        echo "<p><strong>Total records: " . $allResult->num_rows . "</strong></p>";
        
        // Check for problematic records
        $zeroIdQuery = "SELECT COUNT(*) as count FROM graph WHERE id = 0";
        $zeroIdResult = $conn->query($zeroIdQuery);
        if ($zeroIdResult) {
            $zeroCount = $zeroIdResult->fetch_assoc()['count'];
            if ($zeroCount > 0) {
                echo "<p style='color: red;'>⚠️ Found $zeroCount records with id = 0 (these cause the duplicate entry error)</p>";
            }
        }
    }
} else {
    echo "<p>No records in table</p>";
}

echo "<h3>Actions:</h3>";
echo "<p><a href='simple_fix.php' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Run Simple Fix</a></p>";
echo "<p><a href='Graph.php' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Graph Management</a></p>";

$conn->close();
?>