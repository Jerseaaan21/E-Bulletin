<?php
require_once('../../db.php');
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $graphId = $_POST['graph_id'];
    $title = $_POST['graphTitle'];
    $graphType = $_POST['graphType'];
    
    // Get tab state parameters
    $mainTab = isset($_POST['mainTab']) ? $_POST['mainTab'] : 'upload';
    $currentTab = isset($_POST['currentTab']) ? $_POST['currentTab'] : 'upload-graphs';
    
    // Validate data
    if (empty($title) || empty($graphType)) {
        echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // Prepare graph data based on graph type
    if ($graphType === 'pie') {
        // Get graph data from the form
        $labels = $_POST['label'];
        $values = $_POST['value'];
        $colors = $_POST['color'];
        
        if (empty($labels) || empty($values) || empty($colors)) {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Prepare graph data
        $graphData = [];
        for ($i = 0; $i < count($labels); $i++) {
            if (!empty($labels[$i]) && isset($values[$i]) && isset($colors[$i])) {
                // Check if value is numeric or percentage
                $value = $values[$i];
                $format = null;
                $originalValue = $value;
                
                // Check if it's a percentage
                if (is_string($value) && strpos($value, '%') !== false) {
                    $format = 'percentage';
                    // Convert to numeric value for storage
                    $value = floatval(str_replace('%', '', $value));
                } else {
                    $value = floatval($value);
                }
                
                $graphData[] = [
                    'label' => $labels[$i],
                    'value' => $value,
                    'color' => $colors[$i],
                    'original_value' => $originalValue,
                    'format' => $format
                ];
            }
        }
    } else {
        // Get bar graph data from the form
        $categories = $_POST['bar_category'];
        $seriesCount = isset($_POST['seriesCount']) ? intval($_POST['seriesCount']) : 2;
        
        // Collect series data
        $seriesData = [];
        for ($i = 1; $i <= $seriesCount; $i++) {
            $labelKey = 'series' . $i . 'Label';
            $colorKey = 'series' . $i . 'Color';
            $seriesData[$i] = [
                'label' => isset($_POST[$labelKey]) ? $_POST[$labelKey] : "Series $i",
                'color' => isset($_POST[$colorKey]) ? $_POST[$colorKey] : getChartColors(1, $i-1)[0]
            ];
        }
        
        if (empty($categories)) {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
            exit;
        }
        
        // Prepare graph data
        $graphData = [];
        for ($i = 0; $i < count($categories); $i++) {
            if (!empty($categories[$i])) {
                $dataPoint = [
                    'category' => $categories[$i]
                ];
                
                // Add each series value
                for ($j = 1; $j <= $seriesCount; $j++) {
                    $seriesKey = 'bar_series' . $j;
                    if (isset($_POST[$seriesKey][$i])) {
                        $value = $_POST[$seriesKey][$i];
                        $format = null;
                        $originalValue = $value;
                        
                        // Check if it's a percentage
                        if (is_string($value) && strpos($value, '%') !== false) {
                            $format = 'percentage';
                            // Convert to numeric value for storage
                            $value = floatval(str_replace('%', '', $value));
                        } else {
                            $value = floatval($value);
                        }
                        
                        $dataPoint['series' . $j] = $value;
                        $dataPoint['series' . $j . '_format'] = $format;
                    }
                }
                
                // Add series labels and colors
                foreach ($seriesData as $index => $series) {
                    $dataPoint['series' . $index . '_label'] = $series['label'];
                    $dataPoint['series' . $index . '_color'] = $series['color'];
                }
                
                $graphData[] = $dataPoint;
            }
        }
    }
    
    if (empty($graphData)) {
        echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        exit;
    }
    
    // Get the current file path from Bulletin database
    try {
        $stmt = $conn->prepare("SELECT file_path FROM graphs WHERE id = ?");
        $stmt->bind_param('i', $graphId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $filePath = $row['file_path'];
            
            // Update the CSV file
            $uploadDir = __DIR__ . '/uploads';
            $uploadPath = $uploadDir . '/' . $filePath;
            
            $csvContent = "";
            if ($graphType === 'pie') {
                $csvContent = "Label,Value,Color\n";
                foreach ($graphData as $item) {
                    $csvContent .= "{$item['label']},{$item['value']},{$item['color']}\n";
                }
            } else {
                // Create header with categories and series
                $header = "Category";
                for ($i = 1; $i <= $seriesCount; $i++) {
                    $header .= ",Series $i";
                }
                $csvContent = $header . "\n";
                
                foreach ($graphData as $item) {
                    $csvContent .= $item['category'];
                    for ($i = 1; $i <= $seriesCount; $i++) {
                        $csvContent .= "," . $item['series' . $i];
                    }
                    $csvContent .= "\n";
                }
            }
            
            if (file_put_contents($uploadPath, $csvContent) === false) {
                echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
                exit;
            }
            
            // Prepare data for database
            $jsonData = json_encode($graphData);
            
            // Update database in Bulletin database
            $stmt = $conn->prepare("UPDATE graphs SET title = ?, type = ?, data = ? WHERE id = ?");
            $stmt->bind_param('sssi', $title, $graphType, $jsonData, $graphId);
            $stmt->execute();
            
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        } else {
            echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
        }
    } catch (Exception $e) {
        echo "<script>window.location.href = 'CEIT.php?main=$mainTab&tab=$currentTab';</script>";
    }
}

// Function to get chart colors (replicated from JavaScript)
function getChartColors($count, $paletteIndex) {
    $colorPalettes = [
        ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
        ['#8AC926', '#1982C4', '#6A4C93', '#F15BB5', '#00BBF9', '#00F5D4'],
        ['#FB5607', '#FF006E', '#8338EC', '#3A86FF', '#06FFA5', '#FFBE0B'],
        ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557', '#F77F00'],
        ['#2A9D8F', '#E9C46A', '#F4A261', '#E76F51', '#264653', '#E9D8A6']
    ];
    
    $palette = $colorPalettes[$paletteIndex % count($colorPalettes)];
    $colors = [];
    for ($i = 0; $i < $count; $i++) {
        $colors[] = $palette[$i % count($palette)];
    }
    return $colors;
}
?>