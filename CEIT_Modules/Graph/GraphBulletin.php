<?php
// CEIT_Modules/Graph/GraphBulletin.php
// This file provides graph data for the bulletin display
// It will be included by the main Bulletin.php

// Ensure we have database connection and session
if (!isset($conn)) {
    include "../../db.php";
}

// Get department information - this should be set by the calling bulletin
$bulletin_dept_id = $dept_id ?? 1; // Default to CEIT if not set
$bulletin_dept_acronym = $dept_acronym ?? 'CEIT';

// Get module ID for Graph
$moduleQuery = "SELECT id FROM modules WHERE name = 'Graph' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Debug logging
error_log("CEIT GraphBulletin - Starting with Dept ID: $bulletin_dept_id, Module ID: $moduleId");

// Get active graphs for this department
$query = "SELECT dg.*, u.name as user_name 
        FROM main_graph dg 
        LEFT JOIN users u ON dg.user_id = u.id 
        WHERE dg.dept_id = ? AND dg.status = 'active' AND dg.module = ? 
        ORDER BY dg.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $bulletin_dept_id, $moduleId);
$stmt->execute();
$result = $stmt->get_result();

error_log("CEIT GraphBulletin - Query executed, rows found: " . $result->num_rows);

// Organize graphs by type for carousel display
$ceit_bulletin_graphs = [
    'individual' => [],
    'group' => []
];

while ($row = $result->fetch_assoc()) {
    error_log("CEIT GraphBulletin - Processing graph ID: " . $row['id'] . ", Type: " . $row['graph_type']);
    
    $graphData = json_decode($row['data'], true);
    if (!$graphData) {
        error_log("CEIT GraphBulletin - Failed to decode JSON for graph ID: " . $row['id']);
        continue;
    }
    
    $graphItem = [
        'id' => $row['id'],
        'description' => $row['description'],
        'graph_type' => $row['graph_type'],
        'data' => $graphData,
        'posted_on' => date("F j, Y", strtotime($row['created_at'])),
        'user_name' => $row['user_name'] ?? 'Unknown'
    ];
    
    // Categorize graphs
    if ($row['graph_type'] === 'group') {
        $ceit_bulletin_graphs['group'][] = $graphItem;
        error_log("CEIT GraphBulletin - Added group graph: " . $row['description']);
    } else {
        $ceit_bulletin_graphs['individual'][] = $graphItem;
        error_log("CEIT GraphBulletin - Added individual graph: " . $row['description']);
    }
}

error_log("CEIT GraphBulletin - Final counts - Individual: " . count($ceit_bulletin_graphs['individual']) . ", Group: " . count($ceit_bulletin_graphs['group']));

// Function to render individual graph
function renderBulletinGraph($graph, $containerId) {
    $graphId = 'bulletin_graph_' . $graph['id'] . '_' . uniqid();
    $graphType = $graph['graph_type'];
    $data = $graph['data'];
    
    echo "<div class='graph-container' id='{$containerId}'>";
    echo "<div class='graph-header text-center mb-2'>";
    echo "<h4 class='text-lg font-semibold text-gray-800'>" . htmlspecialchars($graph['description']) . "</h4>";
    echo "</div>";
    
    // Special layout for pie charts - table on left, chart on right
    if ($graphType === 'pie') {
        echo "<div class='pie-chart-layout flex flex-col lg:flex-row' style='min-height: 400px;'>";
        
        // Left side: Data Table
        echo "<div class='pie-data-table w-full lg:w-2/5 p-3 border-r border-gray-200'>";
        echo "<div class='table-container bg-white rounded-lg shadow-inner p-3'>";
        echo "<h5 class='text-sm font-semibold text-gray-700 mb-2 border-b pb-2 text-center'>Data Details</h5>";
        
        $labels = $data['labels'] ?? [];
        $values = $data['values'] ?? [];
        $colors = $data['colors'] ?? ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
        
        if (count($labels) > 0 && count($values) > 0) {
            // Calculate totals for percentages
            $total = array_sum($values);
            
            echo "<table class='w-full text-xs'>";
            echo "<thead class='bg-gray-50'>";
            echo "<tr>";
            echo "<th class='py-1 px-2 text-left font-medium text-gray-700 text-xs'>Category</th>";
            echo "<th class='py-1 px-2 text-center font-medium text-gray-700 text-xs'>Value</th>";
            echo "<th class='py-1 px-2 text-center font-medium text-gray-700 text-xs'>Percentage</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody class='divide-y divide-gray-100'>";
            
            for ($i = 0; $i < count($labels); $i++) {
                $value = $values[$i];
                $percentage = $total > 0 ? round(($value / $total) * 100, 2) : 0;
                $color = $colors[$i % count($colors)];
                
                echo "<tr class='hover:bg-gray-50'>";
                echo "<td class='py-1 px-2 text-xs'>";
                echo "<div class='flex items-center'>";
                echo "<div class='w-2 h-2 rounded-full mr-1 flex-shrink-0' style='background-color: {$color}'></div>";
                echo "<span class='text-xs'>" . htmlspecialchars($labels[$i]) . "</span>";
                echo "</div>";
                echo "</td>";
                echo "<td class='py-1 px-2 font-medium text-center text-xs'>" . number_format($value, 2) . "</td>";
                echo "<td class='py-1 px-2 text-center text-xs'>";
                echo "<div class='flex items-center justify-center'>";
                echo "<span class='mr-1 text-xs'>{$percentage}%</span>";
                echo "<div class='w-12 bg-gray-200 rounded-full h-1.5'>";
                echo "<div class='bg-orange-500 h-1.5 rounded-full' style='width: {$percentage}%'></div>";
                echo "</div>";
                echo "</div>";
                echo "</td>";
                echo "</tr>";
            }
            
            // Total row
            echo "<tr class='bg-gray-50 font-semibold'>";
            echo "<td class='py-1 px-2 text-xs'>Total</td>";
            echo "<td class='py-1 px-2 text-center text-xs'>" . number_format($total, 2) . "</td>";
            echo "<td class='py-1 px-2 text-center text-xs'>100%</td>";
            echo "</tr>";
            
            echo "</tbody>";
            echo "</table>";
        } else {
            echo "<div class='text-center p-8 text-gray-500'>";
            echo "<i class='fas fa-chart-pie text-3xl mb-3'></i>";
            echo "<p>No data available for this pie chart</p>";
            echo "</div>";
        }
        
        echo "</div>"; // Close table-container
        echo "</div>"; // Close pie-data-table
        
        // Right side: Pie Chart
        echo "<div class='pie-chart-display w-full lg:w-3/5 p-3'>";
        echo "<div class='chart-container bg-white rounded-lg shadow-inner p-2'>";
        echo "<div style='width: 100%; height: 400px; position: relative;'>";
        echo "<canvas id='{$graphId}' style='width: 100% !important; height: 100% !important;'></canvas>";
        echo "</div>";
        echo "</div>";
        echo "</div>"; // Close pie-chart-display
        
        echo "</div>"; // Close pie-chart-layout
        
    } else {
        // For non-pie charts (bar charts), use the original layout
        echo "<div class='graph-canvas-container' style='height: 400px; position: relative; display: flex; align-items: stretch; justify-content: center; padding: 10px; overflow: visible;'>";
        echo "<canvas id='{$graphId}' style='width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%; display: block;'></canvas>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Generate JavaScript for this graph
    echo "<script>";
    echo "document.addEventListener('DOMContentLoaded', function() {";
    echo "  console.log('=== RENDERING INDIVIDUAL GRAPH ===');";
    echo "  console.log('Graph ID: {$graph['id']}, Type: {$graphType}');";
    echo "  console.log('Canvas ID: {$graphId}');";
    echo "  console.log('Data:', " . json_encode($data) . ");";
    
    echo "  const canvas = document.getElementById('{$graphId}');";
    echo "  if (!canvas) {";
    echo "    console.error('Canvas not found: {$graphId}');";
    echo "    return;";
    echo "  }";
    echo "  const ctx = canvas.getContext('2d');";
    
    if ($graphType === 'pie') {
        $labels = json_encode($data['labels'] ?? []);
        $values = json_encode($data['values'] ?? []);
        $colors = json_encode($data['colors'] ?? ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']);
        
        echo "  const labels = {$labels};";
        echo "  const values = {$values};";
        echo "  const colors = {$colors};";
        
        echo "  console.log('Pie chart - Labels:', labels, 'Values:', values);";
        
        echo "  if (labels.length > 0 && values.length > 0) {";
        echo "    new Chart(ctx, {";
        echo "      type: 'pie',";
        echo "      data: {";
        echo "        labels: labels,";
        echo "        datasets: [{";
        echo "          data: values,";
        echo "          backgroundColor: colors.slice(0, values.length),";
        echo "          borderWidth: 2,";
        echo "          borderColor: '#fff',";
        echo "          hoverOffset: 15,";
        echo "          hoverBorderWidth: 3";
        echo "        }]";
        echo "      },";
        echo "      options: {";
        echo "        responsive: true,";
        echo "        maintainAspectRatio: true,";
        echo "        layout: {";
        echo "          padding: {";
        echo "            top: 10,";
        echo "            bottom: 10,";
        echo "            left: 10,";
        echo "            right: 3";
        echo "          }";
        echo "        },";
        echo "        plugins: {";
        echo "          legend: { ";
        echo "            position: 'right',";
        echo "            align: 'center',";
        echo "            maxWidth: 250,";
        echo "            labels: { ";
        echo "              boxWidth: 10,";
        echo "              font: { size: 9 },";
        echo "              padding: 4,";
        echo "              usePointStyle: true";
        echo "            }";
        echo "          },";
        echo "          tooltip: {";
        echo "            callbacks: {";
        echo "              label: function(context) {";
        echo "                let label = context.label || '';";
        echo "                if (label) {";
        echo "                  label += ': ';";
        echo "                }";
        echo "                const total = context.dataset.data.reduce((a, b) => a + b, 0);";
        echo "                const value = context.raw;";
        echo "                const percentage = Math.round((value / total) * 100);";
        echo "                label += value + ' (' + percentage + '%)';";
        echo "                return label;";
        echo "              }";
        echo "            }";
        echo "          }";
        echo "        }";
        echo "      }";
        echo "    });";
        echo "  } else {";
        echo "    console.warn('Empty pie chart data');";
        echo "    canvas.parentElement.innerHTML = '<div class=\"text-center p-4\"><p class=\"text-gray-500\">No pie data available</p></div>';";
        echo "  }";
        
    } elseif ($graphType === 'bar') {
        echo "  const rawData = " . json_encode($data) . ";";
        echo "  console.log('Raw bar chart data:', rawData);";
        
        echo "  let categories = rawData.categories || [];";
        echo "  let values = rawData.values || [];";
        echo "  let seriesLabels = rawData.seriesLabels || ['Data'];";
        echo "  let seriesColors = rawData.seriesColors || ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'];";
        
        echo "  console.log('Bar chart parsed - Categories:', categories, 'Values:', values, 'Labels:', seriesLabels);";
        
        echo "  if (categories.length > 0 && values.length > 0) {";
        echo "    let datasets = [];";
        echo "    ";
        echo "    // Check if we have multiple series (2D array)";
        echo "    if (Array.isArray(values) && values.length > 0 && Array.isArray(values[0])) {";
        echo "      console.log('Multiple series detected');";
        echo "      const numSeries = values[0].length;";
        echo "      for (let seriesIndex = 0; seriesIndex < numSeries; seriesIndex++) {";
        echo "        const seriesData = values.map(row => parseFloat(row[seriesIndex]) || 0);";
        echo "        datasets.push({";
        echo "          label: seriesLabels[seriesIndex] || 'Series ' + (seriesIndex + 1),";
        echo "          data: seriesData,";
        echo "          backgroundColor: seriesColors[seriesIndex % seriesColors.length],";
        echo "          borderColor: seriesColors[seriesIndex % seriesColors.length],";
        echo "          borderWidth: 1";
        echo "        });";
        echo "      }";
        echo "    } else {";
        echo "      console.log('Single series detected');";
        echo "      const seriesData = Array.isArray(values) ? values.map(v => parseFloat(v) || 0) : [];";
        echo "      datasets.push({";
        echo "        label: seriesLabels[0] || 'Data',";
        echo "        data: seriesData,";
        echo "        backgroundColor: seriesColors[0] || '#36A2EB',";
        echo "        borderColor: seriesColors[0] || '#36A2EB',";
        echo "        borderWidth: 1";
        echo "      });";
        echo "    }";
        echo "    ";
        echo "    console.log('Final datasets:', datasets);";
        echo "    ";
        echo "    new Chart(ctx, {";
        echo "      type: 'bar',";
        echo "      data: {";
        echo "        labels: categories,";
        echo "        datasets: datasets";
        echo "      },";
        echo "      options: {";
        echo "        responsive: true,";
        echo "        maintainAspectRatio: false,";
        echo "        plugins: {";
        echo "          legend: { ";
        echo "            display: datasets.length > 1,";
        echo "            position: 'top',";
        echo "            labels: { boxWidth: 12, font: { size: 10 } }";
        echo "          }";
        echo "        },";
        echo "        scales: {";
        echo "          y: { ";
        echo "            beginAtZero: true,";
            echo "            ticks: { font: { size: 10 } }";
        echo "          },";
        echo "          x: {";
        echo "            ticks: { font: { size: 9 }, maxRotation: 0, minRotation: 0 }";
        echo "          }";
        echo "        }";
        echo "      }";
        echo "    });";
        echo "    console.log('Bar chart created successfully');";
        echo "  } else {";
        echo "    console.warn('Empty bar chart data');";
        echo "    canvas.parentElement.innerHTML = '<div class=\"text-center p-4\"><p class=\"text-gray-500\">No bar data available</p></div>';";
        echo "  }";
    }
    
    echo "  console.log('=== END INDIVIDUAL GRAPH ===');";
    echo "});";
    echo "</script>";
}

// Function to render group graph (nested carousel) - UPDATED FOR PIE CHARTS
function renderBulletinGroupGraph($groupGraph, $containerId) {
    $groupId = 'group_' . $groupGraph['id'];
    $data = $groupGraph['data'];
    $graphs = $data['graphs'] ?? [];
    
    echo "<div class='group-graph-container' id='{$containerId}' data-group-id='{$groupId}'>";
    echo "<div class='graph-header text-center mb-2'>";
    echo "<h4 class='text-lg font-semibold text-gray-800'>" . htmlspecialchars($groupGraph['description']) . "</h4>";
    echo "</div>";
    
    if (!empty($graphs)) {
        echo "<div class='nested-carousel relative' data-group-id='{$groupId}'>";
        echo "<div class='nested-carousel-container overflow-hidden' style='height: 420px; margin-bottom: 20px;'>";
        
        foreach ($graphs as $index => $graph) {
            $isActive = $index === 0 ? 'active' : '';
            $nestedGraphId = $groupId . '_graph_' . $index;
            $graphType = $graph['type'] ?? 'pie';
            
            echo "<div class='nested-carousel-item {$isActive}' data-index='{$index}' style='display: " . ($isActive ? 'block' : 'none') . "; height: 100%;'>";
            
            // Special layout for pie charts in nested carousel
            if ($graphType === 'pie') {
                echo "<div class='pie-chart-layout-nested flex flex-col md:flex-row h-full' style='height: calc(100% - 40px);'>";
                
                // Left side: Data Table
                echo "<div class='pie-data-table-nested w-full md:w-1/2 p-2 border-r border-gray-200'>";
                echo "<div class='table-container bg-white rounded-lg shadow-inner p-2'>";
                echo "<h6 class='text-xs font-semibold text-gray-700 mb-1 border-b pb-1 text-center'>Data Details</h6>";
                
                // Get data
                $labels = $graph['labels'] ?? $graph['pieLabels'] ?? $graph['categories'] ?? [];
                $values = $graph['values'] ?? $graph['pieValues'] ?? $graph['data'] ?? [];
                $colors = $graph['colors'] ?? $graph['pieColors'] ?? $graph['backgroundColor'] ?? ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
                
                // Try nested data structure
                if (empty($labels) && isset($graph['data']) && is_array($graph['data'])) {
                    $nestedData = $graph['data'];
                    $labels = $nestedData['labels'] ?? $nestedData['categories'] ?? [];
                    $values = $nestedData['values'] ?? $nestedData['data'] ?? [];
                    $colors = $nestedData['colors'] ?? $colors;
                }
                
                if (count($labels) > 0 && count($values) > 0) {
                    $total = array_sum($values);
                    
                    echo "<table class='w-full' style='font-size: 0.6rem;'>";
                    echo "<thead class='bg-gray-50'>";
                    echo "<tr>";
                    echo "<th class='text-left font-medium text-gray-700' style='font-size: 0.6rem; padding: 2px 4px;'>Category</th>";
                    echo "<th class='text-center font-medium text-gray-700' style='font-size: 0.6rem; padding: 2px 4px;'>Value</th>";
                    echo "<th class='text-center font-medium text-gray-700' style='font-size: 0.6rem; padding: 2px 4px;'>Percentage</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody class='divide-y divide-gray-100'>";
                    
                    for ($i = 0; $i < count($labels); $i++) {
                        $value = $values[$i] ?? 0;
                        $percentage = $total > 0 ? round(($value / $total) * 100, 1) : 0;
                        $color = $colors[$i % count($colors)];
                        
                        echo "<tr class='hover:bg-gray-50'>";
                        echo "<td style='font-size: 0.6rem; padding: 2px 4px; line-height: 1.1;'>";
                        echo "<div class='flex items-center'>";
                        echo "<div class='w-1.5 h-1.5 rounded-full flex-shrink-0' style='background-color: {$color}; margin-right: 3px;'></div>";
                        echo "<span style='font-size: 0.6rem;'>" . htmlspecialchars($labels[$i]) . "</span>";
                        echo "</div>";
                        echo "</td>";
                        echo "<td class='font-medium text-center' style='font-size: 0.6rem; padding: 2px 4px;'>" . number_format($value, 1) . "</td>";
                        echo "<td class='text-center' style='font-size: 0.6rem; padding: 2px 4px;'>";
                        echo "<div class='flex items-center justify-center'>";
                        echo "<span style='margin-right: 3px; font-size: 0.6rem;'>{$percentage}%</span>";
                        echo "<div style='width: 40px; background-color: #e5e7eb; border-radius: 9999px; height: 6px; overflow: hidden;'>";
                        echo "<div style='height: 100%; background-color: #f97316; border-radius: 9999px; width: {$percentage}%'></div>";
                        echo "</div>";
                        echo "</div>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    
                    // Total row
                    echo "<tr class='bg-gray-50 font-semibold'>";
                    echo "<td style='font-size: 0.6rem; padding: 2px 4px;'>Total</td>";
                    echo "<td class='text-center' style='font-size: 0.6rem; padding: 2px 4px;'>" . number_format($total, 1) . "</td>";
                    echo "<td class='text-center' style='font-size: 0.6rem; padding: 2px 4px;'>100%</td>";
                    echo "</tr>";
                    
                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<div class='text-center p-4 text-gray-400 text-sm'>";
                    echo "<i class='fas fa-chart-pie text-lg mb-2'></i>";
                    echo "<p>No data available</p>";
                    echo "</div>";
                }
                
                echo "</div>"; // Close table-container
                echo "</div>"; // Close pie-data-table-nested
                
                // Right side: Pie Chart
                echo "<div class='pie-chart-display-nested w-full md:w-1/2 p-2'>";
                echo "<div class='chart-container bg-white rounded-lg shadow-inner p-2 flex items-center justify-center'>";
                echo "<div style='width: 100%; height: 330px; position: relative;'>";
                echo "<canvas id='{$nestedGraphId}' style='width: 100% !important; height: 100% !important;'></canvas>";
                echo "</div>";
                echo "</div>";
                echo "</div>"; // Close pie-chart-display-nested
                
                echo "</div>"; // Close pie-chart-layout-nested
                
            } else {
                // For non-pie charts
                echo "<div class='nested-graph-canvas' style='height: 380px; position: relative; display: flex; align-items: center; justify-content: center; overflow: visible;'>";
                echo "<canvas id='{$nestedGraphId}' style='width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%;'></canvas>";
                echo "</div>";
            }
            
            echo "</div>"; // Close nested-carousel-item
        }
        
        echo "</div>"; // Close nested-carousel-container
        
        // Navigation for nested carousel
        if (count($graphs) > 1) {
            echo "<div class='nested-carousel-nav flex justify-center items-center mt-4 space-x-2' style='padding: 12px; background: rgba(255,255,255,0.95); border-radius: 8px; margin-top: 15px;'>";
            echo "<button class='nested-prev-btn bg-orange-500 text-white p-1 rounded-full hover:bg-orange-600 transition-colors' onclick='prevNestedGraph(\"{$groupId}\")'>";
            echo "<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>";
            echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 19l-7-7 7-7'></path>";
            echo "</svg>";
            echo "</button>";
            
            echo "<span class='nested-indicator text-sm text-gray-600'>1 of " . count($graphs) . "</span>";
            
            echo "<button class='nested-next-btn bg-orange-500 text-white p-1 rounded-full hover:bg-orange-600 transition-colors' onclick='nextNestedGraph(\"{$groupId}\")'>";
            echo "<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>";
            echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 5l7 7-7 7'></path>";
            echo "</svg>";
            echo "</button>";
            echo "</div>";
        }
        
        echo "</div>"; // Close nested-carousel
        
        // Generate JavaScript for nested graphs
        echo "<script>";
        echo "document.addEventListener('DOMContentLoaded', function() {";
        echo "  console.log('=== RENDERING GROUP GRAPH ===');";
        echo "  console.log('Group ID: {$groupGraph['id']}, Nested graphs: " . count($graphs) . "');";
        echo "  console.log('Full group data:', " . json_encode($data) . ");";
        
        foreach ($graphs as $index => $graph) {
            $nestedGraphId = $groupId . '_graph_' . $index;
            $graphType = $graph['type'] ?? 'pie';
            
            echo "  console.log('--- Processing nested graph {$index} ---');";
            echo "  console.log('Canvas ID: {$nestedGraphId}, Type: {$graphType}');";
            echo "  console.log('Graph data:', " . json_encode($graph) . ");";
            
            echo "  const canvas_{$index} = document.getElementById('{$nestedGraphId}');";
            echo "  if (!canvas_{$index}) {";
            echo "    console.error('Canvas not found: {$nestedGraphId}');";
            echo "    return;";
            echo "  }";
            echo "  const ctx_{$index} = canvas_{$index}.getContext('2d');";
            
            if ($graphType === 'pie') {
                echo "  // Try multiple data key combinations for pie charts";
                echo "  const graphData_{$index} = " . json_encode($graph) . ";";
                echo "  let labels_{$index} = graphData_{$index}.labels || graphData_{$index}.pieLabels || graphData_{$index}.categories || [];";
                echo "  let values_{$index} = graphData_{$index}.values || graphData_{$index}.pieValues || graphData_{$index}.data || [];";
                echo "  let colors_{$index} = graphData_{$index}.colors || graphData_{$index}.pieColors || graphData_{$index}.backgroundColor || ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];";
                
                echo "  // If still empty, try nested data structure";
                echo "  if (labels_{$index}.length === 0 && graphData_{$index}.data && typeof graphData_{$index}.data === 'object') {";
                echo "    console.log('Trying nested data structure for pie chart {$index}');";
                echo "    const nestedData = graphData_{$index}.data;";
                echo "    labels_{$index} = nestedData.labels || nestedData.categories || [];";
                echo "    values_{$index} = nestedData.values || [];";
                echo "    colors_{$index} = nestedData.colors || colors_{$index};";
                echo "  }";
                
                echo "  console.log('Pie data {$index} - Labels:', labels_{$index}, 'Values:', values_{$index});";
                
                echo "  if (labels_{$index}.length > 0 && values_{$index}.length > 0) {";
                echo "    new Chart(ctx_{$index}, {";
                echo "      type: 'pie',";
                echo "      data: {";
                echo "        labels: labels_{$index},";
                echo "        datasets: [{";
                echo "          data: values_{$index}.map(v => parseFloat(v) || 0),";
                echo "          backgroundColor: colors_{$index}.slice(0, values_{$index}.length),";
                echo "          borderWidth: 1.5,";
                echo "          borderColor: '#fff',";
                echo "          hoverOffset: 10";
                echo "        }]";
                echo "      },";
                echo "      options: {";
                echo "        responsive: true,";
                echo "        maintainAspectRatio: false,";
                echo "        layout: {";
                echo "          padding: {";
                echo "            top: 5,";
                echo "            bottom: 5,";
                echo "            left: 5,";
                echo "            right: 2";
                echo "          }";
                echo "        },";
                echo "        plugins: {";
                echo "          legend: { ";
                echo "            position: 'right',";
                echo "            align: 'center',";
                echo "            labels: { ";
                echo "              boxWidth: 8,";
                echo "              font: { size: 8 },";
                echo "              padding: 3,";
                echo "              usePointStyle: true";
                echo "            }";
                echo "          }";
                echo "        }";
                echo "      }";
                echo "    });";
                echo "    console.log('Nested pie chart {$index} created successfully');";
                echo "  } else {";
                echo "    console.warn('Empty pie chart data for nested graph {$index}');";
                echo "    canvas_{$index}.parentElement.innerHTML = '<div class=\"text-center p-2\"><p class=\"text-xs text-gray-500\">No pie data</p></div>';";
                echo "  }";
                
            } elseif ($graphType === 'bar') {
                echo "  // Try multiple data key combinations for bar charts";
                echo "  const graphData_{$index} = " . json_encode($graph) . ";";
                echo "  let categories_{$index} = graphData_{$index}.categories || graphData_{$index}.barCategories || graphData_{$index}.labels || [];";
                echo "  let values_{$index} = graphData_{$index}.values || graphData_{$index}.barValues || graphData_{$index}.data || [];";
                echo "  let seriesLabels_{$index} = graphData_{$index}.seriesLabels || graphData_{$index}.barLabels || ['Data'];";
                echo "  let seriesColors_{$index} = graphData_{$index}.seriesColors || graphData_{$index}.barColors || ['#36A2EB'];";
                
                echo "  // If still empty, try nested data structure";
                echo "  if (categories_{$index}.length === 0 && graphData_{$index}.data && typeof graphData_{$index}.data === 'object') {";
                echo "    console.log('Trying nested data structure for bar chart {$index}');";
                echo "    const nestedData = graphData_{$index}.data;";
                echo "    categories_{$index} = nestedData.categories || nestedData.labels || [];";
                echo "    values_{$index} = nestedData.values || [];";
                echo "    seriesLabels_{$index} = nestedData.seriesLabels || seriesLabels_{$index};";
                echo "    seriesColors_{$index} = nestedData.seriesColors || seriesColors_{$index};";
                echo "  }";
                
                echo "  console.log('Bar data {$index} - Categories:', categories_{$index}, 'Values:', values_{$index});";
                
                echo "  if (categories_{$index}.length > 0 && values_{$index}.length > 0) {";
                echo "    let datasets_{$index} = [];";
                echo "    if (Array.isArray(values_{$index}) && values_{$index}.length > 0 && Array.isArray(values_{$index}[0])) {";
                echo "      const numSeries = values_{$index}[0].length;";
                echo "      for (let seriesIndex = 0; seriesIndex < numSeries; seriesIndex++) {";
                echo "        const seriesData = values_{$index}.map(row => parseFloat(row[seriesIndex]) || 0);";
                echo "        datasets_{$index}.push({";
                echo "          label: seriesLabels_{$index}[seriesIndex] || 'Series ' + (seriesIndex + 1),";
                echo "          data: seriesData,";
                echo "          backgroundColor: seriesColors_{$index}[seriesIndex % seriesColors_{$index}.length],";
                echo "          borderWidth: 1";
                echo "        });";
                echo "      }";
                echo "    } else {";
                echo "      const seriesData = Array.isArray(values_{$index}) ? values_{$index}.map(v => parseFloat(v) || 0) : [];";
                echo "      datasets_{$index}.push({";
                echo "        label: seriesLabels_{$index}[0] || 'Data',";
                echo "        data: seriesData,";
                echo "        backgroundColor: seriesColors_{$index}[0] || '#36A2EB',";
                echo "        borderWidth: 1";
                echo "      });";
                echo "    }";
                echo "    ";
                echo "    new Chart(ctx_{$index}, {";
                echo "      type: 'bar',";
                echo "      data: {";
                echo "        labels: categories_{$index},";
                echo "        datasets: datasets_{$index}";
                echo "      },";
                echo "      options: {";
                echo "        responsive: true,";
                echo "        maintainAspectRatio: false,";
                echo "        plugins: { ";
                echo "          legend: { ";
                echo "            display: datasets_{$index}.length > 1,";
                echo "            position: 'top',";
                echo "            labels: { boxWidth: 8, font: { size: 8 } }";
                echo "          }";
                echo "        },";
        echo "        scales: { ";
        echo "          y: { beginAtZero: true, ticks: { font: { size: 8 } } },";
        echo "          x: { ticks: { font: { size: 7 }, maxRotation: 45 } }";
        echo "        }";
        echo "      }";
        echo "    });";
        echo "    console.log('Nested bar chart {$index} created successfully');";
        echo "  } else {";
        echo "    console.warn('Empty bar chart data for nested graph {$index}');";
        echo "    canvas_{$index}.parentElement.innerHTML = '<div class=\"text-center p-2\"><p class=\"text-xs text-gray-500\">No bar data</p></div>';";
        echo "  }";
      }
    }
    
    echo "  console.log('=== END GROUP GRAPH ===');";
    echo "});";
    echo "</script>";
  } else {
    echo "<div class='text-center p-4'>";
    echo "<p class='text-gray-500'>No graphs in this group</p>";
    echo "</div>";
  }
  
  echo "</div>";
}

// Create a unique variable name for this department's graphs
$ceit_bulletin_graphs_var = $ceit_bulletin_graphs;
?>

<style>
/* Bulletin Graph Styles */
.graph-container, .group-graph-container {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: visible;
}

.graph-header {
    flex-shrink: 0;
    margin-bottom: 12px;
}

.graph-canvas-container {
    flex: 1;
    min-height: 400px;
    display: flex !important;
    align-items: stretch;
    justify-content: center;
    position: relative;
    overflow: visible !important;
}

/* Pie Chart Layout Styles */
.pie-chart-layout {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    gap: 0;
}

@media (min-width: 1024px) {
    .pie-chart-layout {
        flex-direction: row;
    }
}

.pie-data-table {
    background: #f8fafc;
    border-radius: 8px;
    overflow: visible;
    display: flex;
    flex-direction: column;
}

.pie-data-table .table-container {
    flex: 1;
    overflow-y: visible;
    max-height: none !important;
}

.pie-data-table table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 0.75rem;
}

.pie-data-table th {
    background-color: #f1f5f9;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.75rem;
}

.pie-data-table td, .pie-data-table th {
    padding: 4px 6px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 0.75rem;
}

.pie-data-table tr:hover {
    background-color: #f7fafc;
}

/* Column widths for better distribution */
.pie-data-table th:nth-child(1),
.pie-data-table td:nth-child(1) {
    width: 45%;
    text-align: left;
    padding-left: 12px;
}

.pie-data-table th:nth-child(2),
.pie-data-table td:nth-child(2) {
    width: 20%;
    text-align: center;
}

.pie-data-table th:nth-child(3),
.pie-data-table td:nth-child(3) {
    width: 35%;
    text-align: center;
}

.pie-chart-display {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 8px;
    overflow: visible;
    flex: 1;
    position: relative;
}

.pie-chart-display .chart-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
}

/* Nested pie chart layout */
.pie-chart-layout-nested {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    gap: 0;
}

@media (min-width: 768px) {
    .pie-chart-layout-nested {
        flex-direction: row;
    }
}

.pie-data-table-nested {
    background: #f8fafc;
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pie-data-table-nested .table-container {
    flex: 1;
    overflow-y: visible;
    max-height: none !important;
}

.pie-data-table-nested table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
    table-layout: fixed;
}

.pie-data-table-nested th {
    background-color: #f1f5f9;
    position: sticky;
    top: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pie-data-table-nested td, .pie-data-table-nested th {
    padding: 4px 2px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

/* Column widths for nested tables */
.pie-data-table-nested th:nth-child(1),
.pie-data-table-nested td:nth-child(1) {
    width: 50%;
    text-align: left;
    padding-left: 8px;
}

.pie-data-table-nested th:nth-child(2),
.pie-data-table-nested td:nth-child(2) {
    width: 25%;
    text-align: center;
}

.pie-data-table-nested th:nth-child(3),
.pie-data-table-nested td:nth-child(3) {
    width: 25%;
    text-align: center;
}

.pie-chart-display-nested {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 6px;
    overflow: visible;
}

.nested-carousel {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.nested-carousel-container {
    flex: 1;
    position: relative;
    min-height: 420px;
}

.nested-carousel-item {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
}

.nested-carousel-item.active {
    display: flex !important;
}

.nested-graph-header {
    flex-shrink: 0;
}

.nested-graph-canvas {
    flex: 1;
    min-height: 380px;
    display: flex !important;
    align-items: stretch;
    justify-content: center;
    position: relative;
    overflow: visible !important;
}

.nested-carousel-nav {
    margin-top: 15px;
    flex-shrink: 0;
    z-index: 100;
    position: relative;
    padding: 8px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
}

/* Ensure canvas fills container properly */
canvas {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
}

/* Chart.js responsive container */
.chartjs-render-monitor {
    animation: chartjs-render-animation 0.001s;
}

/* Progress bar styling */
.pie-data-table .progress-bar-container {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.pie-data-table .progress-bar {
    flex-grow: 1;
    max-width: 120px;
    background-color: #e5e7eb;
    border-radius: 9999px;
    height: 8px;
    overflow: hidden;
}

.pie-data-table .progress-bar-fill {
    height: 100%;
    background-color: #f97316;
    border-radius: 9999px;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .pie-chart-layout {
        flex-direction: column;
    }
    
    .pie-data-table, .pie-data-table-nested {
        width: 100%;
        max-height: none;
    }
    
    .pie-chart-display, .pie-chart-display-nested {
        width: 100%;
        height: 300px;
    }
}

@media (max-width: 768px) {
    .graph-container, .group-graph-container {
        padding: 12px;
    }
    
    .graph-canvas-container {
        min-height: 350px !important;
    }
    
    .nested-carousel-container {
        min-height: 400px !important;
    }
    
    .pie-chart-layout-nested {
        flex-direction: column;
    }
    
    .pie-data-table table, .pie-data-table-nested table {
        font-size: 0.7rem;
    }
    
    .pie-data-table td, .pie-data-table th,
    .pie-data-table-nested td, .pie-data-table-nested th {
        padding: 4px 2px;
    }
}

@media (max-width: 640px) {
    .pie-data-table table, .pie-data-table-nested table {
        font-size: 0.65rem;
    }
    
    .pie-data-table td, .pie-data-table th,
    .pie-data-table-nested td, .pie-data-table-nested th {
        padding: 3px 1px;
    }
    
    .pie-data-table th:nth-child(1),
    .pie-data-table td:nth-child(1) {
        width: 40%;
        padding-left: 8px;
    }
    
    .pie-data-table th:nth-child(2),
    .pie-data-table td:nth-child(2) {
        width: 25%;
    }
    
    .pie-data-table th:nth-child(3),
    .pie-data-table td:nth-child(3) {
        width: 35%;
    }
    
    .pie-data-table-nested th:nth-child(1),
    .pie-data-table-nested td:nth-child(1) {
        width: 45%;
        padding-left: 6px;
    }
    
    .pie-data-table-nested th:nth-child(2),
    .pie-data-table-nested td:nth-child(2) {
        width: 30%;
    }
    
    .pie-data-table-nested th:nth-child(3),
    .pie-data-table-nested td:nth-child(3) {
        width: 25%;
    }
}

@media (max-width: 480px) {
    .pie-data-table table, .pie-data-table-nested table {
        font-size: 0.6rem;
    }
    
    .pie-data-table td, .pie-data-table th,
    .pie-data-table-nested td, .pie-data-table-nested th {
        padding: 2px 1px;
    }
    
    .pie-chart-layout {
        flex-direction: column;
        height: auto;
    }
    
    .pie-data-table, .pie-chart-display {
        width: 100%;
        height: 300px;
    }
}
</style>

<script>
// Nested carousel navigation functions - FIXED VERSION
function nextNestedGraph(groupId) {
    try {
        console.log('nextNestedGraph called with groupId:', groupId);
        
        // Find the container using data attribute
        const carousel = document.querySelector(`[data-group-id="${groupId}"] .nested-carousel-container`);
        if (!carousel) {
            console.warn('Nested carousel container not found for groupId:', groupId);
            return;
        }
        
        const items = carousel.querySelectorAll('.nested-carousel-item');
        const indicator = carousel.parentElement.querySelector('.nested-indicator');
        
        if (items.length === 0) {
            console.warn('No nested carousel items found');
            return;
        }
        
        // Find current active item - check multiple conditions for visibility
        let currentIndex = 0;
        items.forEach((item, index) => {
            const isVisible = item.style.display === 'block' || 
                            item.style.display === 'flex' || 
                            item.classList.contains('active') ||
                            (!item.style.display && index === 0); // Default first item
            if (isVisible) {
                currentIndex = index;
            }
        });
        
        console.log(`Current active item: ${currentIndex + 1}`);
        
        // Hide all items first
        items.forEach(item => {
            item.style.display = 'none';
            item.classList.remove('active');
        });
        
        // Calculate next index
        const nextIndex = (currentIndex + 1) % items.length;
        
        // Show next item
        items[nextIndex].style.display = 'flex';
        items[nextIndex].classList.add('active');
        
        // Update indicator
        if (indicator) {
            indicator.textContent = `${nextIndex + 1} of ${items.length}`;
        }
        
        console.log(`Switched from graph ${currentIndex + 1} to graph ${nextIndex + 1} of ${items.length}`);
    } catch (error) {
        console.error('Error in nextNestedGraph:', error);
    }
}

function prevNestedGraph(groupId) {
    try {
        console.log('prevNestedGraph called with groupId:', groupId);
        
        // Find the container using data attribute
        const carousel = document.querySelector(`[data-group-id="${groupId}"] .nested-carousel-container`);
        if (!carousel) {
            console.warn('Nested carousel container not found for groupId:', groupId);
            return;
        }
        
        const items = carousel.querySelectorAll('.nested-carousel-item');
        const indicator = carousel.parentElement.querySelector('.nested-indicator');
        
        if (items.length === 0) {
            console.warn('No nested carousel items found');
            return;
        }
        
        // Find current active item - check multiple conditions for visibility
        let currentIndex = 0;
        items.forEach((item, index) => {
            const isVisible = item.style.display === 'block' || 
                            item.style.display === 'flex' || 
                            item.classList.contains('active') ||
                            (!item.style.display && index === 0); // Default first item
            if (isVisible) {
                currentIndex = index;
            }
        });
        
        console.log(`Current active item: ${currentIndex + 1}`);
        
        // Hide all items first
        items.forEach(item => {
            item.style.display = 'none';
            item.classList.remove('active');
        });
        
        // Calculate previous index
        const prevIndex = (currentIndex - 1 + items.length) % items.length;
        
        // Show previous item
        items[prevIndex].style.display = 'flex';
        items[prevIndex].classList.add('active');
        
        // Update indicator
        if (indicator) {
            indicator.textContent = `${prevIndex + 1} of ${items.length}`;
        }
        
        console.log(`Switched from graph ${currentIndex + 1} to graph ${prevIndex + 1} of ${items.length}`);
    } catch (error) {
        console.error('Error in prevNestedGraph:', error);
    }
}

// Auto-rotate nested carousels
document.addEventListener('DOMContentLoaded', function() {
    console.log('Setting up nested carousel auto-rotation');
    
    const nestedCarousels = document.querySelectorAll('.nested-carousel[data-group-id]');
    console.log('Found', nestedCarousels.length, 'nested carousels');
    
    nestedCarousels.forEach(carousel => {
        const groupId = carousel.getAttribute('data-group-id');
        const items = carousel.querySelectorAll('.nested-carousel-item');
        
        console.log('Setting up auto-rotation for group', groupId, 'with', items.length, 'items');
        
        if (items.length > 1) {
            setInterval(() => {
                nextNestedGraph(groupId);
            }, 5000); // Change every 5 seconds
        }
    });
});
</script>