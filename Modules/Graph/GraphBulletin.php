<?php
// Modules/Graph/GraphBulletin.php
// This file provides graph data for the bulletin display for general departments
// It will be included by the main Bulletin.php

// Ensure we have database connection and session
if (!isset($conn)) {
    include "../../db.php";
}

// Get department information - this should be set by the calling bulletin
$bulletin_dept_id = $dept_id ?? 1;
$bulletin_dept_acronym = $dept_acronym ?? 'DEFAULT';

// Get module ID for Graph
$moduleQuery = "SELECT id FROM modules WHERE name = 'Graph' LIMIT 1";
$moduleResult = $conn->query($moduleQuery);
$moduleRow = $moduleResult->fetch_assoc();
$moduleId = $moduleRow['id'] ?? 0;

// Get active graphs for this department from the general graph table
$query = "SELECT dg.*, u.name as user_name 
        FROM graph dg 
        LEFT JOIN users u ON dg.user_id = u.id 
        WHERE dg.dept_id = ? AND dg.status = 'Approved' AND dg.module = ? 
        ORDER BY dg.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $bulletin_dept_id, $moduleId);
$stmt->execute();
$result = $stmt->get_result();

// Organize graphs by type for carousel display
$general_bulletin_graphs = [
    'individual' => [],
    'group' => []
];

while ($row = $result->fetch_assoc()) {
    $graphData = json_decode($row['data'], true) ?: [];
    
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
        $general_bulletin_graphs['group'][] = $graphItem;
    } else {
        $general_bulletin_graphs['individual'][] = $graphItem;
    }
}

// Function to render individual graph
function renderBulletinGraph($graph, $containerId) {
    $graphId = 'bulletin_graph_' . $graph['id'] . '_' . uniqid();
    $graphType = $graph['graph_type'];
    $data = $graph['data'];
    
    echo "<div class='graph-container' id='{$containerId}'>";
    echo "<div class='graph-header text-center mb-2'>";
    echo "<h4 class='text-lg font-semibold text-gray-800'>" . htmlspecialchars($graph['description']) . "</h4>";
    echo "</div>";
    
    echo "<div class='graph-canvas-container' style='height: 400px; position: relative; display: flex; align-items: stretch; justify-content: center; padding: 10px; overflow: visible;'>";
    echo "<canvas id='{$graphId}' style='width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%; display: block;'></canvas>";
    echo "</div>";
    echo "</div>";
    
    // Generate JavaScript for this graph
    echo "<script>";
    echo "document.addEventListener('DOMContentLoaded', function() {";
    echo "  const ctx_{$graph['id']} = document.getElementById('{$graphId}').getContext('2d');";
    
    if ($graphType === 'pie') {
        $labels = json_encode($data['labels'] ?? []);
        $values = json_encode($data['values'] ?? []);
        $colors = json_encode($data['colors'] ?? []);
        
        echo "  const labels = {$labels};";
        echo "  const values = {$values};";
        echo "  const colors = {$colors}.length > 0 ? {$colors} : ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];";
        echo "  ";
        echo "  if (labels.length > 0 && values.length > 0) {";
        echo "    new Chart(ctx_{$graph['id']}, {";
        echo "      type: 'pie',";
        echo "      data: {";
        echo "        labels: labels,";
        echo "        datasets: [{";
        echo "          data: values,";
        echo "          backgroundColor: colors.slice(0, values.length),";
        echo "          borderWidth: 1,";
        echo "          borderColor: '#fff'";
        echo "        }]";
        echo "      },";
        echo "      options: {";
        echo "        responsive: true,";
        echo "        maintainAspectRatio: false,";
        echo "        plugins: {";
        echo "          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }";
        echo "        }";
        echo "      }";
        echo "    });";
        echo "  } else {";
        echo "    console.warn('Empty pie chart data');";
        echo "    document.getElementById('{$graphId}').parentElement.innerHTML = '<div class=\"text-center p-4\"><p class=\"text-gray-500\">No data available</p></div>';";
        echo "  }";
        
    } elseif ($graphType === 'bar') {
        $categories = $data['categories'] ?? [];
        $values = $data['values'] ?? [];
        $seriesLabels = $data['seriesLabels'] ?? ['Data'];
        $seriesColors = $data['seriesColors'] ?? ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'];
        
        // Ensure we have valid data
        if (empty($categories) || empty($values)) {
            echo "  console.warn('Bar chart {$graph['id']} has empty data');";
            echo "  document.getElementById('{$graphId}').parentElement.innerHTML = '<div class=\"text-center p-4\"><p class=\"text-gray-500\">No data available</p></div>';";
        } else {
            // Debug logging with actual data
            echo "  console.log('Bar chart data for graph {$graph['id']}:', {";
            echo "    categories: " . json_encode($categories) . ",";
            echo "    values: " . json_encode($values) . ",";
            echo "    seriesLabels: " . json_encode($seriesLabels) . ",";
            echo "    seriesColors: " . json_encode($seriesColors);
            echo "  });";
            
            // Create datasets for Chart.js
            echo "  const categories = " . json_encode($categories) . ";";
            echo "  const rawValues = " . json_encode($values) . ";";
            echo "  const seriesLabels = " . json_encode($seriesLabels) . ";";
            echo "  const seriesColors = " . json_encode($seriesColors) . ";";
            
            echo "  let datasets = [];";
            echo "  ";
            echo "  // Check if we have multiple series (2D array)";
            echo "  if (Array.isArray(rawValues) && rawValues.length > 0 && Array.isArray(rawValues[0])) {";
            echo "    // Multiple series - each row contains values for all series";
            echo "    const numSeries = rawValues[0].length;";
            echo "    console.log('Multiple series detected:', numSeries, 'series');";
            echo "    ";
            echo "    for (let seriesIndex = 0; seriesIndex < numSeries; seriesIndex++) {";
            echo "      const seriesData = rawValues.map(row => parseFloat(row[seriesIndex]) || 0);";
            echo "      datasets.push({";
            echo "        label: seriesLabels[seriesIndex] || 'Series ' + (seriesIndex + 1),";
            echo "        data: seriesData,";
            echo "        backgroundColor: seriesColors[seriesIndex] || seriesColors[seriesIndex % seriesColors.length],";
            echo "        borderColor: seriesColors[seriesIndex] || seriesColors[seriesIndex % seriesColors.length],";
            echo "        borderWidth: 1";
            echo "      });";
            echo "    }";
            echo "  } else {";
            echo "    // Single series - flat array of values";
            echo "    console.log('Single series detected');";
            echo "    const seriesData = Array.isArray(rawValues) ? rawValues.map(v => parseFloat(v) || 0) : [];";
            echo "    datasets.push({";
            echo "      label: seriesLabels[0] || 'Data',";
            echo "      data: seriesData,";
            echo "      backgroundColor: seriesColors[0] || '#36A2EB',";
            echo "      borderColor: seriesColors[0] || '#36A2EB',";
            echo "      borderWidth: 1";
            echo "    });";
            echo "  }";
            echo "  ";
            echo "  console.log('Final datasets for graph {$graph['id']}:', datasets);";
            echo "  ";
            echo "  new Chart(ctx_{$graph['id']}, {";
            echo "    type: 'bar',";
            echo "    data: {";
            echo "      labels: categories,";
            echo "      datasets: datasets";
            echo "    },";
            echo "    options: {";
            echo "      responsive: true,";
            echo "      maintainAspectRatio: false,";
            echo "      layout: {";
            echo "        padding: 10";
            echo "      },";
            echo "      plugins: {";
            echo "        legend: { ";
            echo "          display: datasets.length > 1, ";
            echo "          position: 'top',";
            echo "          labels: { boxWidth: 12, font: { size: 10 } }";
            echo "        }";
            echo "      },";
            echo "      scales: {";
            echo "        y: { ";
            echo "          beginAtZero: true,";
            echo "          ticks: { font: { size: 10 } }";
            echo "        },";
            echo "        x: {";
            echo "          ticks: { font: { size: 9 }, maxRotation: 0, minRotation: 0 }";
            echo "        }";
            echo "      }";
            echo "    }";
            echo "  });";
        }
    }
    
    echo "});";
    echo "</script>";
}

// Function to render group graph
function renderBulletinGroupGraph($groupGraph, $containerId) {
    $groupId = 'group_' . $groupGraph['id']; // Simplified ID
    $data = $groupGraph['data'];
    $graphs = $data['graphs'] ?? [];
    
    echo "<div class='group-graph-container' id='{$containerId}' data-group-id='{$groupId}'>";
    echo "<div class='graph-header text-center mb-2'>";
    echo "<h4 class='text-lg font-semibold text-gray-800'>" . htmlspecialchars($groupGraph['description']) . "</h4>";
    echo "</div>";
    
    if (!empty($graphs)) {
        echo "<div class='nested-carousel relative' data-group-id='{$groupId}'>";
        echo "<div class='nested-carousel-container overflow-hidden' style='height: 300px; margin-bottom: 20px;'>";
        
        foreach ($graphs as $index => $graph) {
            $isActive = $index === 0 ? 'active' : '';
            $nestedGraphId = $groupId . '_graph_' . $index;
            
            echo "<div class='nested-carousel-item {$isActive}' data-index='{$index}' style='display: " . ($isActive ? 'block' : 'none') . ";'>";
            
            echo "<div class='nested-graph-canvas' style='height: 280px; position: relative; display: flex; align-items: center; justify-content: center; overflow: visible;'>";
            echo "<canvas id='{$nestedGraphId}' style='width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%;'></canvas>";
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
        
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
        
        echo "</div>";
        
        // Generate JavaScript for nested graphs
        echo "<script>";
        echo "document.addEventListener('DOMContentLoaded', function() {";
        echo "  console.log('Rendering group graph {$groupGraph['id']} with " . count($graphs) . " nested graphs');";
        
        foreach ($graphs as $index => $graph) {
            $nestedGraphId = $groupId . '_graph_' . $index;
            $graphType = $graph['type'] ?? 'pie';
            
            echo "  console.log('Rendering nested graph {$index}: {$nestedGraphId}, type: {$graphType}');";
            echo "  console.log('Graph data:', " . json_encode($graph) . ");";
            
            echo "  const canvas_{$nestedGraphId} = document.getElementById('{$nestedGraphId}');";
            echo "  if (canvas_{$nestedGraphId}) {";
            echo "    console.log('Canvas found for {$nestedGraphId}');";
            echo "    const ctx_{$nestedGraphId} = canvas_{$nestedGraphId}.getContext('2d');";
            
            if ($graphType === 'pie') {
                // Try multiple possible data key combinations for pie charts
                echo "    let labels = " . json_encode($graph['labels'] ?? []) . ";";
                echo "    let values = " . json_encode($graph['values'] ?? []) . ";";
                echo "    let colors = " . json_encode($graph['colors'] ?? []) . ";";
                
                echo "    // Try alternative data keys if main ones are empty";
                echo "    const graphData = " . json_encode($graph) . ";";
                echo "    if (labels.length === 0) {";
                echo "      labels = graphData.pieLabels || graphData.categories || graphData.label || [];";
                echo "    }";
                echo "    if (values.length === 0) {";
                echo "      values = graphData.pieValues || graphData.data || graphData.value || [];";
                echo "    }";
                echo "    if (colors.length === 0) {";
                echo "      colors = graphData.pieColors || graphData.backgroundColor || graphData.color || ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'];";
                echo "    }";
                
                echo "    console.log('Final pie data - Labels:', labels, 'Values:', values, 'Colors:', colors);";
                
                echo "    if (labels.length > 0 && values.length > 0) {";
                echo "      new Chart(ctx_{$nestedGraphId}, {";
                echo "        type: 'pie',";
                echo "        data: {";
                echo "          labels: labels,";
                echo "          datasets: [{";
                echo "            data: values.map(v => parseFloat(v) || 0),";
                echo "            backgroundColor: colors.slice(0, values.length),";
                echo "            borderWidth: 1,";
                echo "            borderColor: '#fff'";
                echo "          }]";
                echo "        },";
                echo "        options: {";
                echo "          responsive: true,";
                echo "          maintainAspectRatio: false,";
                echo "          plugins: {";
                echo "            legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 8 } } }";
                echo "          }";
                echo "        }";
                echo "      });";
                echo "      console.log('Pie chart created for {$nestedGraphId}');";
                echo "    } else {";
                echo "      console.warn('Empty pie chart data for {$nestedGraphId}');";
                echo "      canvas_{$nestedGraphId}.parentElement.innerHTML = '<div class=\"text-center p-2\"><p class=\"text-xs text-gray-500\">No data</p></div>';";
                echo "    }";
                
            } elseif ($graphType === 'bar') {
                // Try multiple possible data key combinations for bar charts
                echo "    let categories = " . json_encode($graph['categories'] ?? []) . ";";
                echo "    let values = " . json_encode($graph['values'] ?? []) . ";";
                echo "    let seriesLabels = " . json_encode($graph['seriesLabels'] ?? ['Data']) . ";";
                echo "    let seriesColors = " . json_encode($graph['seriesColors'] ?? ['#36A2EB']) . ";";
                
                echo "    // Try alternative data keys if main ones are empty";
                echo "    const graphData = " . json_encode($graph) . ";";
                echo "    if (categories.length === 0) {";
                echo "      categories = graphData.barCategories || graphData.labels || graphData.label || [];";
                echo "    }";
                echo "    if (values.length === 0) {";
                echo "      values = graphData.barValues || graphData.data || graphData.value || [];";
                echo "    }";
                echo "    if (seriesLabels.length <= 1 && seriesLabels[0] === 'Data') {";
                echo "      seriesLabels = graphData.barLabels || graphData.seriesLabels || graphData.legend || ['Data'];";
                echo "    }";
                echo "    if (seriesColors.length <= 1) {";
                echo "      seriesColors = graphData.barColors || graphData.backgroundColor || graphData.color || ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'];";
                echo "    }";
                
                echo "    console.log('Final bar data - Categories:', categories, 'Values:', values, 'Labels:', seriesLabels, 'Colors:', seriesColors);";
                
                echo "    if (categories.length > 0 && values.length > 0) {";
                echo "      let datasets = [];";
                echo "      ";
                echo "      // Check if we have multiple series";
                echo "      if (Array.isArray(values) && values.length > 0 && Array.isArray(values[0])) {";
                echo "        // Multiple series";
                echo "        const numSeries = values[0].length;";
                echo "        for (let seriesIndex = 0; seriesIndex < numSeries; seriesIndex++) {";
                echo "          const seriesData = values.map(row => parseFloat(row[seriesIndex]) || 0);";
                echo "          datasets.push({";
                echo "            label: seriesLabels[seriesIndex] || 'Series ' + (seriesIndex + 1),";
                echo "            data: seriesData,";
                echo "            backgroundColor: seriesColors[seriesIndex] || seriesColors[seriesIndex % seriesColors.length],";
                echo "            borderWidth: 1";
                echo "          });";
                echo "        }";
                echo "      } else {";
                echo "        // Single series";
                echo "        const seriesData = Array.isArray(values) ? values.map(v => parseFloat(v) || 0) : [];";
                echo "        datasets.push({";
                echo "          label: seriesLabels[0] || 'Data',";
                echo "          data: seriesData,";
                echo "          backgroundColor: seriesColors[0] || '#36A2EB',";
                echo "          borderWidth: 1";
                echo "        });";
                echo "      }";
                echo "      ";
                echo "      new Chart(ctx_{$nestedGraphId}, {";
                echo "        type: 'bar',";
                echo "        data: {";
                echo "          labels: categories,";
                echo "          datasets: datasets";
                echo "        },";
                echo "        options: {";
                echo "          responsive: true,";
                echo "          maintainAspectRatio: false,";
                echo "          plugins: { ";
                echo "            legend: { ";
                echo "              display: datasets.length > 1,";
                echo "              position: 'top',";
                echo "              labels: { boxWidth: 8, font: { size: 8 } }";
                echo "            }";
                echo "          },";
                echo "          scales: { ";
                echo "            y: { beginAtZero: true, ticks: { font: { size: 8 } } },";
                echo "            x: { ticks: { font: { size: 7 }, maxRotation: 0, minRotation: 0 } }";
                echo "          }";
                echo "        }";
                echo "      });";
                echo "      console.log('Bar chart created for {$nestedGraphId}');";
                echo "    } else {";
                echo "      console.warn('Empty bar chart data for {$nestedGraphId}');";
                echo "      canvas_{$nestedGraphId}.parentElement.innerHTML = '<div class=\"text-center p-2\"><p class=\"text-xs text-gray-500\">No data</p></div>';";
                echo "    }";
            }
            
            echo "  } else {";
            echo "    console.error('Canvas element not found: {$nestedGraphId}');";
            echo "  }";
        }
        
        echo "});";
        echo "</script>";
    }
    
    echo "</div>";
}

// Create a unique variable name for this department's graphs
$general_bulletin_graphs_var = $general_bulletin_graphs;
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
}

.graph-canvas-container {
    flex: 1;
    min-height: 350px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    overflow: visible !important;
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
    min-height: 300px;
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

.nested-graph-canvas {
    flex: 1;
    min-height: 260px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    overflow: visible !important;
}

.nested-carousel-nav {
    margin-top: 15px;
    flex-shrink: 0;
    z-index: 10;
    position: relative;
}

/* Ensure canvas fills container */
canvas {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .graph-container, .group-graph-container {
        padding: 12px;
    }
    
    .graph-canvas-container {
        min-height: 300px !important;
    }
    
    .nested-graph-canvas {
        min-height: 250px !important;
    }
    
    .nested-carousel-container {
        min-height: 280px !important;
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
        items[nextIndex].style.display = 'block';
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
        items[prevIndex].style.display = 'block';
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
            }, 8000); // Change every 8 seconds
        }
    });
});
</script>