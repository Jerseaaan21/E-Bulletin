<?php
// Include database connection (one level up from CEIT_Modules)
require_once '../../db.php';

// Enable error logging
error_log("GraphUpdateProcess.php accessed");

// Disable error display for JSON responses
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    handleUpdate($conn);
} catch (Exception $e) {
    error_log("Error in GraphUpdateProcess.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function handleUpdate($conn)
{
    error_log("handleUpdate function called");

    $graphId = $_POST['id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? 'pie';

    error_log("Update request - ID: $graphId, Title: $title, Type: $type");
    error_log("POST data: " . print_r($_POST, true));

    if ($graphId <= 0) {
        error_log("Invalid graph ID: $graphId");
        echo json_encode(['success' => false, 'message' => 'Invalid graph ID']);
        exit;
    }

    if (empty($title)) {
        error_log("Empty title provided");
        echo json_encode(['success' => false, 'message' => 'Please provide a title']);
        exit;
    }

    // Get existing graph data
    $stmt = $conn->prepare("SELECT * FROM graphs WHERE id = ?");
    $stmt->bind_param('i', $graphId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Graph not found with ID: $graphId");
        echo json_encode(['success' => false, 'message' => 'Graph not found']);
        exit;
    }

    $existingGraph = $result->fetch_assoc();
    error_log("Found graph: " . json_encode($existingGraph));

    // Prepare graph data
    if ($type === 'pie') {
        $labels = $_POST['label'] ?? [];
        $values = $_POST['value'] ?? [];
        $colors = $_POST['color'] ?? [];

        $graphData = [];
        for ($i = 0; $i < count($labels); $i++) {
            if (!empty($labels[$i]) && isset($values[$i])) {
                // Validate color format
                $color = $colors[$i] ?? '#FF6384';
                if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
                    $color = '#FF6384'; // Default color if invalid
                }

                $graphData[] = [
                    'label' => $labels[$i],
                    'value' => is_numeric($values[$i]) ? floatval($values[$i]) : 0,
                    'color' => $color
                ];
            }
        }
    } else {
        $seriesCount = intval($_POST['seriesCount'] ?? 2);
        $categories = $_POST['bar_category'] ?? [];

        $graphData = [];
        for ($i = 0; $i < count($categories); $i++) {
            if (!empty($categories[$i])) {
                $dataPoint = ['category' => $categories[$i]];

                for ($k = 1; $k <= $seriesCount; $k++) {
                    $seriesKey = "bar_series{$k}";
                    $labelKey = "series{$k}Label";
                    $colorKey = "series{$k}Color";

                    if (isset($_POST[$seriesKey][$i])) {
                        $dataPoint["series{$k}"] = is_numeric($_POST[$seriesKey][$i]) ? floatval($_POST[$seriesKey][$i]) : 0;
                        $dataPoint["series{$k}_label"] = $_POST[$labelKey] ?? "Series {$k}";

                        // Validate color format
                        $color = $_POST[$colorKey] ?? getChartColor($k - 1);
                        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
                            $color = getChartColor($k - 1); // Default color if invalid
                        }
                        $dataPoint["series{$k}_color"] = $color;
                    }
                }

                $graphData[] = $dataPoint;
            }
        }
    }

    if (empty($graphData)) {
        error_log("No valid data provided");
        echo json_encode(['success' => false, 'message' => 'No valid data provided']);
        exit;
    }

    // Update graph in database
    $stmt = $conn->prepare("UPDATE graphs SET title = ?, type = ?, data = ? WHERE id = ?");
    $jsonData = json_encode($graphData);
    $stmt->bind_param('sssi', $title, $type, $jsonData, $graphId);

    if (!$stmt->execute()) {
        error_log("Database error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        exit;
    }

    error_log("Graph updated successfully");
    echo json_encode(['success' => true, 'message' => 'Graph updated successfully']);
    exit;
}

function getChartColor($index)
{
    $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8A2BE2', '#20B2AA', '#FF69B4', '#7B68EE'];
    return $colors[$index % count($colors)];
}
