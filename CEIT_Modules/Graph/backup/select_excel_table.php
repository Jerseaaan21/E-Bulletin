<?php
session_start();
require_once('../../db.php');

// Check if uploaded file info exists in session
if (!isset($_SESSION['uploaded_file']) || empty($_SESSION['uploaded_file']['tables'])) {
    header("Location: CEIT.php?main=upload&tab=upload-graphs");
    exit;
}

$fileInfo = $_SESSION['uploaded_file'];
$tables = $fileInfo['tables'];

// Get tab state parameters
$mainTab = isset($_GET['main']) ? $_GET['main'] : 'upload';
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'upload-graphs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Excel Table</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .table-preview {
            max-height: 200px;
            overflow-y: auto;
        }
        .table-preview table {
            font-size: 12px;
        }
        .table-option:hover {
            background-color: #f3f4f6;
        }
        .selected-table {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-table mr-2 text-blue-500"></i>
                        Select Data Table from Excel File
                    </h1>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                        Found <?php echo count($tables); ?> table(s)
                    </span>
                </div>
                
                <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <p class="text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        Your Excel file contains multiple data tables. Please select which table you want to use for creating the graph.
                    </p>
                </div>
            </div>
            
            <!-- Table Selection Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <form id="tableSelectionForm" action="process_selected_table.php" method="post">
                    <input type="hidden" name="mainTab" value="<?php echo htmlspecialchars($mainTab); ?>">
                    <input type="hidden" name="currentTab" value="<?php echo htmlspecialchars($currentTab); ?>">
                    <input type="hidden" name="skipTableSelection" value="1">
                    
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-700 mb-4">Available Tables</h2>
                        
                        <?php foreach ($tables as $index => $table): ?>
                            <div class="table-option mb-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer transition-all duration-200"
                                 onclick="selectTable(<?php echo $index; ?>)"
                                 id="table-<?php echo $index; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-medium text-gray-800">
                                            Table <?php echo $index + 1; ?>
                                            <?php if ($table['sheet_name']): ?>
                                                <span class="text-sm text-gray-600 ml-2">
                                                    (Sheet: <?php echo htmlspecialchars($table['sheet_name']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            Rows: <?php echo count($table['data']); ?> 
                                            | Columns: <?php echo count($table['data'][0] ?? []); ?>
                                            | Range: 
                                            <?php 
                                                echo chr(65 + $table['start_col']) . ($table['start_row'] + 1) . 
                                                     ':' . 
                                                     chr(65 + $table['end_col']) . ($table['end_row'] + 1);
                                            ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" 
                                               name="selectedTable" 
                                               value="<?php echo $index; ?>" 
                                               id="radio-<?php echo $index; ?>"
                                               class="hidden"
                                               <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                            <div class="w-3 h-3 rounded-full bg-blue-500 hidden"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Table Preview -->
                                <div class="table-preview mt-3 border border-gray-200 rounded overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <?php if (!empty($table['preview'][0])): ?>
                                                    <?php foreach ($table['preview'][0] as $colIndex => $header): ?>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            <?php echo htmlspecialchars($header); ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php for ($i = 1; $i < min(4, count($table['preview'])); $i++): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <?php foreach ($table['preview'][$i] as $cell): ?>
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($cell); ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endfor; ?>
                                            <?php if (count($table['preview']) > 4): ?>
                                                <tr>
                                                    <td colspan="<?php echo count($table['preview'][0] ?? []); ?>" 
                                                        class="px-3 py-2 text-center text-xs text-gray-500 bg-gray-50">
                                                        ... and <?php echo count($table['preview']) - 4; ?> more rows
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Chart Type Selection -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h2 class="text-lg font-semibold text-gray-700 mb-4">Chart Type for Selected Table</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="chart-option p-4 border-2 border-gray-200 rounded-lg cursor-pointer transition-all duration-200"
                                 onclick="selectChartType('pie')"
                                 id="chart-pie">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-medium text-gray-800">Pie Chart</h3>
                                        <p class="text-sm text-gray-500">Best for showing proportions</p>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" 
                                               name="chartType" 
                                               value="pie" 
                                               id="radio-pie"
                                               class="hidden"
                                               checked>
                                        <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                            <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 text-xs text-gray-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Uses first two columns: Labels and Values
                                </div>
                            </div>
                            
                            <div class="chart-option p-4 border-2 border-gray-200 rounded-lg cursor-pointer transition-all duration-200"
                                 onclick="selectChartType('bar')"
                                 id="chart-bar">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-medium text-gray-800">Bar Chart</h3>
                                        <p class="text-sm text-gray-500">Best for comparisons</p>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" 
                                               name="chartType" 
                                               value="bar" 
                                               id="radio-bar"
                                               class="hidden">
                                        <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                            <div class="w-3 h-3 rounded-full bg-blue-500 hidden"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 text-xs text-gray-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    First column: Categories, Other columns: Series
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                        <a href="CEIT.php?main=<?php echo $mainTab; ?>&tab=<?php echo $currentTab; ?>" 
                           class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        
                        <div class="flex space-x-3">
                            <button type="button" 
                                    onclick="cancelUpload()" 
                                    class="px-4 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200">
                                <i class="fas fa-times mr-2"></i> Cancel Upload
                            </button>
                            
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white hover:bg-blue-600 rounded-lg transition duration-200">
                                <i class="fas fa-check mr-2"></i> Continue with Selected Table
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Table selection
        function selectTable(index) {
            // Remove selection from all tables
            document.querySelectorAll('.table-option').forEach(option => {
                option.classList.remove('selected-table');
                option.classList.remove('border-blue-500');
            });
            
            // Uncheck all radio buttons
            document.querySelectorAll('input[name="selectedTable"]').forEach(radio => {
                radio.checked = false;
            });
            
            // Select this table
            const tableOption = document.getElementById('table-' + index);
            const radioBtn = document.getElementById('radio-' + index);
            
            tableOption.classList.add('selected-table', 'border-blue-500');
            radioBtn.checked = true;
            
            // Update radio visual
            document.querySelectorAll('.table-option .w-6 .w-3').forEach(indicator => {
                indicator.classList.add('hidden');
            });
            tableOption.querySelector('.w-6 .w-3').classList.remove('hidden');
        }
        
        // Chart type selection
        function selectChartType(type) {
            // Remove selection from all chart options
            document.querySelectorAll('.chart-option').forEach(option => {
                option.classList.remove('border-blue-500');
            });
            
            // Uncheck all radio buttons
            document.querySelectorAll('input[name="chartType"]').forEach(radio => {
                radio.checked = false;
            });
            
            // Select this chart type
            const chartOption = document.getElementById('chart-' + type);
            const radioBtn = document.getElementById('radio-' + type);
            
            chartOption.classList.add('border-blue-500');
            radioBtn.checked = true;
            
            // Update radio visual
            document.querySelectorAll('.chart-option .w-6 .w-3').forEach(indicator => {
                indicator.classList.add('hidden');
            });
            chartOption.querySelector('.w-6 .w-3').classList.remove('hidden');
        }
        
        // Cancel upload
        function cancelUpload() {
            if (confirm('Are you sure you want to cancel this upload?')) {
                window.location.href = 'cancel_upload.php?main=<?php echo $mainTab; ?>&tab=<?php echo $currentTab; ?>';
            }
        }
        
        // Initialize selections
        document.addEventListener('DOMContentLoaded', function() {
            selectTable(0); // Select first table by default
            selectChartType('pie'); // Select pie chart by default
        });
    </script>
</body>
</html>