<?php
echo "Test file is accessible!";
echo "<br>Current directory: " . __DIR__;
echo "<br>AddGraph.php exists: " . (file_exists(__DIR__ . '/AddGraph.php') ? 'YES' : 'NO');
?>