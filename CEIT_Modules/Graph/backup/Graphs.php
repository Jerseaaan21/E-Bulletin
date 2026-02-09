<?php
include "../../db.php";
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-orange-600 flex items-center">
      <i class="fas fa-chart-pie mr-3 text-orange-600"></i>
      Graph Management
    </h2>
    <div class="flex space-x-3">
      <button onclick="showUploadModal()" class="px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110 flex items-center">
        <i class="fas fa-upload mr-2"></i> Upload File
      </button>
      <button onclick="showAddGraphModal()" class="px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110 flex items-center">
        <i class="fas fa-plus mr-2"></i> Add New Graph
      </button>
    </div>
  </div>
  
  <!-- Upload Modal -->
  <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto transform transition-all">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold text-gray-800">Upload File to Generate Graph</h3>
        <button onclick="closeUploadModal()" class="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100 transition duration-200 transform hover:scale-110">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Upload Options Tabs -->
      <div class="mb-6 border-b border-gray-200">
        <div class="flex space-x-4">
          <button id="individualUploadTab" onclick="showUploadTab('individual')" class="px-4 py-2 border-b-2 border-blue-500 text-blue-500 font-medium text-sm">
            <i class="fas fa-file-upload mr-2"></i> Individual Graph Upload
          </button>
          <button id="groupUploadTab" onclick="showUploadTab('group')" class="px-4 py-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
            <i class="fas fa-layer-group mr-2"></i> Group Graph Upload
          </button>
        </div>
      </div>
      
      <!-- Individual Upload Form -->
      <div id="individualUploadForm" class="upload-tab">
        <form id="uploadIndividualForm" action="upload_graph.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="mainTab" value="upload">
          <input type="hidden" name="currentTab" value="upload-graphs">
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="individualGraphTitle">
              Graph Title
            </label>
            <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   id="individualGraphTitle" name="graphTitle" type="text" placeholder="Enter graph title" required>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="individualFileUpload">
              Upload CSV or Excel File
            </label>
            <div class="border-2 border-dashed border-blue-300 rounded-lg p-6 text-center hover:border-blue-400 transition duration-200 bg-white">
              <i class="fas fa-cloud-upload-alt text-blue-400 text-4xl mb-3"></i>
              <p class="text-gray-600 mb-2">Drag and drop your file here or</p>
              <label for="individualFileUpload" class="cursor-pointer inline-block px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                <i class="fas fa-folder-open mr-2"></i> Browse Files
              </label>
              <input type="file" id="individualFileUpload" name="file" class="hidden" accept=".csv,.xlsx,.xls" required onchange="updateIndividualFileName(this)">
              <div id="individualFileName" class="mt-2 text-sm text-gray-500"></div>
              <p class="text-xs text-gray-400 mt-2">Supported formats: CSV, XLSX, XLS (Max 10MB)</p>
            </div>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
              Chart Type
            </label>
            <div class="flex space-x-4">
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="chartType" value="pie" class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500" checked>
                <span class="ml-2 text-gray-700">Pie Chart</span>
              </label>
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="chartType" value="bar" class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-gray-700">Bar Chart</span>
              </label>
            </div>
          </div>
          
          <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <h4 class="text-md font-semibold mb-2 text-gray-700">File Format Guide</h4>
            <p class="text-sm text-gray-600 mb-2">For Pie Charts:</p>
            <div class="text-xs bg-white p-3 rounded border mb-3">
              <code>Label,Value</code><br>
              <code>Category A,100</code><br>
              <code>Category B,200</code><br>
              <code>Category C,150</code>
            </div>
            <p class="text-sm text-gray-600 mb-2">For Bar Charts:</p>
            <div class="text-xs bg-white p-3 rounded border">
              <code>Category,Series1,Series2</code><br>
              <code>Month 1,100,150</code><br>
              <code>Month 2,200,180</code><br>
              <code>Month 3,150,220</code>
            </div>
            <p class="text-xs text-gray-500 mt-2">Note: For bar charts, the first column header will be used as Category label, and subsequent column headers will be used as Series names.</p>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeUploadModal()" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
              <i class="fas fa-upload mr-2"></i> Upload & Generate Graph
            </button>
          </div>
        </form>
      </div>
      
      <!-- Group Upload Form (Multiple Files) -->
      <div id="groupUploadForm" class="upload-tab hidden">
        <form id="uploadGroupForm" action="upload_group_multiple_files.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="mainTab" value="upload">
          <input type="hidden" name="currentTab" value="upload-graphs">
          <input type="hidden" name="isGroup" value="1">
          
          <div class="mb-6">
            <div class="flex items-center justify-center mb-6">
              <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-purple-600 text-white flex items-center justify-center font-bold">1</div>
                <div class="ml-2 text-sm font-medium text-purple-600">Group Info</div>
              </div>
              <div class="w-16 h-1 bg-gray-300 mx-2"></div>
              <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold">2</div>
                <div class="ml-2 text-sm font-medium text-gray-500">Graph Details</div>
              </div>
            </div>
            
            <!-- Step 1: Group Information -->
            <div id="groupStep1" class="step-content">
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="multiGroupTitle">
                  Group Title
                </label>
                <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                       id="multiGroupTitle" name="groupTitle" type="text" placeholder="Enter group title (e.g., Department Statistics 2024)" required>
              </div>
              
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="multiGraphCount">
                  Number of Graphs in Group
                </label>
                <select id="multiGraphCount" name="graphCount" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" onchange="updateMultiGraphForms()">
                  <option value="1">1 Graph</option>
                  <option value="2" selected>2 Graphs</option>
                  <option value="3">3 Graphs</option>
                  <option value="4">4 Graphs</option>
                  <option value="5">5 Graphs</option>
                  <option value="6">6 Graphs</option>
                  <option value="7">7 Graphs</option>
                  <option value="8">8 Graphs</option>
                  <option value="9">9 Graphs</option>
                  <option value="10">10 Graphs</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select how many graphs you want to create in this group</p>
              </div>
              
              <div class="flex justify-end">
                <button type="button" onclick="goToMultiStep2()" class="px-4 py-2 bg-purple-500 text-white hover:bg-purple-600 rounded-lg transition duration-200">
                  Next <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>
            
            <!-- Step 2: Graph Details & File Uploads -->
            <div id="groupStep2" class="step-content hidden">
              <div id="multiGraphFormsContainer">
                <!-- Graph forms will be dynamically added here -->
              </div>
              
              <div class="flex justify-between mt-6">
                <button type="button" onclick="goToMultiStep1()" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                  <i class="fas fa-arrow-left mr-2"></i> Back
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg transition duration-200">
                  <i class="fas fa-upload mr-2"></i> Upload & Create Group
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Add New Graph Modal (Matches Upload Modal Design) -->
  <div id="addGraphModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto transform transition-all">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold text-gray-800">Create New Graph Manually</h3>
        <button onclick="closeAddGraphModal()" class="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100 transition duration-200 transform hover:scale-110">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Creation Options Tabs -->
      <div class="mb-6 border-b border-gray-200">
        <div class="flex space-x-4">
          <button id="individualGraphTab" onclick="showGraphTab('individual')" class="px-4 py-2 border-b-2 border-orange-500 text-orange-500 font-medium text-sm">
            <i class="fas fa-chart-pie mr-2"></i> Individual Graph
          </button>
          <button id="groupGraphTab" onclick="showGraphTab('group')" class="px-4 py-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
            <i class="fas fa-layer-group mr-2"></i> Group Graphs
          </button>
        </div>
      </div>
      
      <!-- Individual Graph Form -->
      <div id="individualGraphForm" class="graph-tab">
        <form id="createIndividualGraphForm" action="add_graph_ceit.php" method="post">
          <input type="hidden" name="mainTab" value="upload">
          <input type="hidden" name="currentTab" value="upload-graphs">
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="individualManualGraphTitle">
              Graph Title
            </label>
            <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
                   id="individualManualGraphTitle" name="graphTitle" type="text" placeholder="Enter graph title" required>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
              Chart Type
            </label>
            <div class="flex space-x-4">
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="graphType" value="pie" class="form-radio h-4 w-4 text-orange-600 focus:ring-orange-500" checked onchange="switchGraphType('pie')">
                <span class="ml-2 text-gray-700">Pie Chart</span>
              </label>
              <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="graphType" value="bar" class="form-radio h-4 w-4 text-orange-600 focus:ring-orange-500" onchange="switchGraphType('bar')">
                <span class="ml-2 text-gray-700">Bar Chart</span>
              </label>
            </div>
          </div>
          
          <!-- Pie Chart Form -->
          <div id="pieForm">
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <label class="block text-gray-700 text-sm font-bold">
                  Data Points
                </label>
                <button type="button" onclick="addPieRow()" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-sm rounded-lg transition duration-200 transform hover:scale-110">
                  <i class="fas fa-plus mr-1"></i> Add Row
                </button>
              </div>
              
              <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
                <table class="min-w-full bg-white">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Label</th>
                      <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Value</th>
                      <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Color</th>
                      <th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="pieTableBody">
                    <tr class="data-row border-b border-gray-200 hover:bg-gray-50">
                      <td class="py-3 px-4">
                        <input type="text" name="label[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Label" required>
                      </td>
                      <td class="py-3 px-4">
                        <input type="text" name="value[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Value" required>
                      </td>
                      <td class="py-3 px-4">
                        <div class="flex items-center">
                          <input type="color" name="color[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="#FF6384">
                          <input type="text" name="color_text[]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="#FF6384">
                        </div>
                      </td>
                      <td class="py-3 px-4 text-center">
                        <button type="button" onclick="removePieRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
                          <i class="fas fa-trash mr-1"></i> Delete
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <!-- Bar Chart Form (Hidden by Default) -->
          <div id="barForm" class="hidden">
            <div class="mb-4">
              <label class="block text-gray-700 text-sm font-bold mb-2">Number of Series</label>
              <select id="seriesCount" name="seriesCount" onchange="updateSeriesInputs()" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                <option value="1">1</option>
                <option value="2" selected>2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
              </select>
            </div>
            
            <div class="mb-4">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                Series Labels
              </label>
              <div id="seriesLabelsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Series inputs will be dynamically added here -->
              </div>
            </div>
            
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <label class="block text-gray-700 text-sm font-bold">
                  Data Points
                </label>
                <button type="button" onclick="addBarRow()" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-sm rounded-lg transition duration-200 transform hover:scale-110">
                  <i class="fas fa-plus mr-1"></i> Add Row
                </button>
              </div>
              
              <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
                <table class="min-w-full bg-white">
                  <thead class="bg-gray-100">
                    <tr id="barTableHeader">
                      <!-- Table headers will be dynamically added here -->
                    </tr>
                  </thead>
                  <tbody id="barTableBody">
                    <!-- Table rows will be dynamically added here -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <h4 class="text-md font-semibold mb-2 text-gray-700">Data Format Guide</h4>
            <p class="text-sm text-gray-600 mb-2">For Pie Charts:</p>
            <div class="text-xs bg-white p-3 rounded border mb-3">
              <code>Label: Category A, Value: 100, Color: #FF6384</code><br>
              <code>Label: Category B, Value: 200, Color: #36A2EB</code><br>
              <code>Label: Category C, Value: 150, Color: #FFCE56</code>
            </div>
            <p class="text-sm text-gray-600 mb-2">For Bar Charts:</p>
            <div class="text-xs bg-white p-3 rounded border">
              <code>Category: Month 1, Series 1: 100, Series 2: 150</code><br>
              <code>Category: Month 2, Series 1: 200, Series 2: 180</code><br>
              <code>Category: Month 3, Series 1: 150, Series 2: 220</code>
            </div>
            <p class="text-xs text-gray-500 mt-2">Note: You can use numbers, decimals, or percentages (e.g., 25, 15.5, or 25%)</p>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeAddGraphModal()" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
              Cancel
            </button>
            <button type="button" onclick="validateAndSubmitForms()" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
              <i class="fas fa-plus mr-2"></i> Create Graph
            </button>
          </div>
        </form>
      </div>
      
      <!-- Group Graph Form -->
      <div id="groupGraphForm" class="graph-tab hidden">
        <form id="createGroupGraphForm" action="add_graph_ceit.php" method="post">
          <input type="hidden" name="mainTab" value="upload">
          <input type="hidden" name="currentTab" value="upload-graphs">
          <input type="hidden" name="isGroup" value="1">
          
          <div class="mb-6">
            <div class="flex items-center justify-center mb-6">
              <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-orange-600 text-white flex items-center justify-center font-bold">1</div>
                <div class="ml-2 text-sm font-medium text-orange-600">Group Info</div>
              </div>
              <div class="w-16 h-1 bg-gray-300 mx-2"></div>
              <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold">2</div>
                <div class="ml-2 text-sm font-medium text-gray-500">Graph Details</div>
              </div>
            </div>
            
            <!-- Step 1: Group Information -->
            <div id="manualGroupStep1" class="step-content">
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="manualGroupTitle">
                  Group Title
                </label>
                <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
                       id="manualGroupTitle" name="groupTitle" type="text" placeholder="Enter group title (e.g., Department Statistics 2024)" required>
              </div>
              
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="manualGraphCount">
                  Number of Graphs in Group
                </label>
                <select id="manualGraphCount" name="graphCount" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" onchange="updateGraphForms()">
                  <option value="1">1 Graph</option>
                  <option value="2" selected>2 Graphs</option>
                  <option value="3">3 Graphs</option>
                  <option value="4">4 Graphs</option>
                  <option value="5">5 Graphs</option>
                  <option value="6">6 Graphs</option>
                  <option value="7">7 Graphs</option>
                  <option value="8">8 Graphs</option>
                  <option value="9">9 Graphs</option>
                  <option value="10">10 Graphs</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select how many graphs you want to create in this group</p>
              </div>
              
              <div class="flex justify-end">
                <button type="button" onclick="goToManualStep2()" class="px-4 py-2 bg-orange-500 text-white hover:bg-orange-600 rounded-lg transition duration-200">
                  Next <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>
            
            <!-- Step 2: Graph Details -->
            <div id="manualGroupStep2" class="step-content hidden">
              <div id="graphFormsContainer">
                <!-- Graph forms will be dynamically added here -->
              </div>
              
              <div class="mb-4 p-4 bg-gray-50 rounded-lg mt-6">
                <h4 class="text-md font-semibold mb-2 text-gray-700">Data Format Guide</h4>
                <p class="text-sm text-gray-600 mb-2">You can create multiple graphs with different data sets. Each graph can be either a Pie Chart or Bar Chart.</p>
                <p class="text-xs text-gray-500">Note: Use consistent color schemes across graphs for better visual presentation.</p>
              </div>
              
              <div class="flex justify-between mt-6">
                <button type="button" onclick="goToManualStep1()" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200">
                  <i class="fas fa-arrow-left mr-2"></i> Back
                </button>
                <button type="button" onclick="validateAndSubmitForms()" class="px-4 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg transition duration-200">
                  <i class="fas fa-plus mr-2"></i> Create Group
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- All Graphs Section -->
  <div>
    <h3 class="text-xl font-semibold mb-4 text-gray-700 flex items-center">
      <i class="fas fa-chart-bar mr-2 text-blue-500"></i>
      All Graphs
    </h3>
    
    <?php
// Get all graphs from Bulletin database for CEIT department (department_id = 1)
$query = "SELECT * FROM graphs WHERE department_id = 1 ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $currentGroup = null;
    $individualGraphs = [];
    
    while ($row = $result->fetch_assoc()) {
        $title = $row['title'];
        $graphType = $row['type'];
        $data = json_decode($row['data'], true);
        $groupTitle = $row['group_title'];
        
        // If this is a new group, display the group header
        if ($groupTitle && $groupTitle !== $currentGroup) {
            // Close previous group container if exists
            if ($currentGroup !== null) {
                echo '</div></div>';
            }
            
            // Start new group
            echo '<div class="mb-8">';
            echo '<h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center p-3 bg-purple-50 rounded-lg border border-purple-100 shadow-sm">';
            echo '<i class="fas fa-layer-group mr-2 text-purple-500"></i>';
            echo htmlspecialchars($groupTitle);
            echo '</h4>';
            echo '<div class="grid grid-cols-1 gap-6">';
            
            $currentGroup = $groupTitle;
        }
        // If this is not part of a group and we were in a group, close the group container
        else if (!$groupTitle && $currentGroup !== null) {
            echo '</div></div>';
            $currentGroup = null;
        }
        
        // Only display group graphs immediately, collect individual graphs
        if ($groupTitle) {
            // Display group graph immediately
            displayGraphCard([
                'id' => $row['id'],
                'title' => $title,
                'type' => $graphType,
                'data' => $data,
                'created_at' => $row['created_at'],
                'group_title' => $groupTitle
            ]);
        } else {
            // Collect individual graphs
            $individualGraphs[] = [
                'id' => $row['id'],
                'title' => $title,
                'type' => $graphType,
                'data' => $data,
                'created_at' => $row['created_at']
            ];
        }
    }
    
    // Close any open group container
    if ($currentGroup !== null) {
        echo '</div></div>';
    }
    
    // Display individual graphs section if there are any
    if (!empty($individualGraphs)) {
        echo '<div class="mb-8">';
        echo '<h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center p-3 bg-blue-50 rounded-lg border border-blue-100 shadow-sm">';
        echo '<i class="fas fa-chart-area mr-2 text-blue-500"></i>';
        echo 'Individual Graphs';
        echo '</h4>';
        echo '<div class="grid grid-cols-1 gap-6">';
        foreach ($individualGraphs as $graph) {
            displayGraphCard($graph);
        }
        echo '</div></div>';
    }
} else {
    echo '<div class="bg-blue-50 border-l-4 border-blue-400 p-6 rounded-lg shadow-sm">';
    echo '<div class="flex items-start">';
    echo '<div class="flex-shrink-0">';
    echo '<i class="fas fa-info-circle text-blue-500 text-2xl"></i>';
    echo '</div>';
    echo '<div class="ml-3">';
    echo '<p class="text-base text-blue-700 font-medium">No graphs found</p>';
    echo '<p class="text-sm text-blue-600 mt-1">Add graphs to display them here.</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
?>
  </div>
</div>

<!-- Delete/Archive Modal -->
<div id="deleteArchiveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md transform transition-all">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold text-gray-800">Confirm Action</h3>
      <button onclick="closeDeleteArchiveModal()" class="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100 transition duration-200 transform hover:scale-110">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div class="mb-6">
      <p class="text-gray-600 mb-4">What would you like to do with this graph?</p>
      <div class="flex flex-col space-y-3">
        <button onclick="archiveGraph()" class="px-4 py-3 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-[1.02] flex items-center justify-center">
          <i class="fas fa-archive mr-2"></i> Archive
        </button>
        <button onclick="deleteGraph()" class="px-4 py-3 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-[1.02] flex items-center justify-center">
          <i class="fas fa-trash mr-2"></i> Delete
        </button>
      </div>
    </div>
    <div class="flex justify-end">
      <button onclick="closeDeleteArchiveModal()" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
        Cancel
      </button>
    </div>
  </div>
</div>

<!-- Edit Graph Modal -->
<div id="editGraphModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-2xl max-h-screen overflow-y-auto transform transition-all">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold text-gray-800">Edit Graph</h3>
      <button onclick="closeEditGraphModal()" class="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100 transition duration-200 transform hover:scale-110">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    
    <div id="editGraphContent">
      <!-- Content will be loaded dynamically -->
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Color palette for charts
const colorPalettes = [
  ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
  ['#8AC926', '#1982C4', '#6A4C93', '#F15BB5', '#00BBF9', '#00F5D4'],
  ['#FB5607', '#FF006E', '#8338EC', '#3A86FF', '#06FFA5', '#FFBE0B'],
  ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557', '#F77F00'],
  ['#2A9D8F', '#E9C46A', '#F4A261', '#E76F51', '#264653', '#E9D8A6']
];

// Function to get colors for a chart
function getChartColors(count, paletteIndex) {
  const palette = colorPalettes[paletteIndex % colorPalettes.length];
  const colors = [];
  for (let i = 0; i < count; i++) {
    colors.push(palette[i % palette.length]);
  }
  return colors;
}

// Store the current graph ID for delete/archive operations
let currentGraphId = null;

// Global variable to track if we're on the graphs tab
let isGraphsTabActive = false;

// Form Validation and Submission Functions
function validateAndSubmitForms() {
  // Get the active tab
  const individualTab = document.getElementById('individualGraphForm');
  const groupTab = document.getElementById('groupGraphForm');
  
  if (!individualTab.classList.contains('hidden')) {
    // Validate individual graph form
    const individualForm = document.getElementById('createIndividualGraphForm');
    if (validateIndividualGraphForm(individualForm)) {
      individualForm.submit();
    }
  } else if (!groupTab.classList.contains('hidden')) {
    // Validate group graph form
    const groupForm = document.getElementById('createGroupGraphForm');
    if (validateGroupGraphForm(groupForm)) {
      groupForm.submit();
    }
  }
}

function validateIndividualGraphForm(form) {
  // Validate graph title
  const titleInput = form.querySelector('input[name="graphTitle"]');
  if (!titleInput || !titleInput.value.trim()) {
    showNotification('Please enter a graph title', 'warning');
    titleInput.focus();
    return false;
  }
  
  // Validate data rows based on chart type
  const isPieChart = form.querySelector('input[name="graphType"][value="pie"]').checked;
  
  if (isPieChart) {
    // Validate pie chart data
    const labels = form.querySelectorAll('input[name="label[]"]');
    const values = form.querySelectorAll('input[name="value[]"]');
    
    if (labels.length === 0 || values.length === 0) {
      showNotification('Please add at least one data row for the pie chart', 'warning');
      return false;
    }
    
    let hasValidData = false;
    for (let i = 0; i < labels.length; i++) {
      if (labels[i].value.trim() && values[i].value.trim()) {
        hasValidData = true;
        break;
      }
    }
    
    if (!hasValidData) {
      showNotification('Please fill in at least one label and value for the pie chart', 'warning');
      return false;
    }
    
    // Validate that values are numbers or percentages
    for (let i = 0; i < values.length; i++) {
      const value = values[i].value.trim();
      if (value) {
        // Remove % sign for validation
        const numericValue = value.replace('%', '');
        if (isNaN(numericValue) || numericValue === '') {
          showNotification(`Value "${value}" is not a valid number or percentage`, 'warning');
          values[i].focus();
          return false;
        }
      }
    }
  } else {
    // Validate bar chart data
    const categories = form.querySelectorAll('input[name="bar_category[]"]');
    
    if (categories.length === 0) {
      showNotification('Please add at least one category for the bar chart', 'warning');
      return false;
    }
    
    let hasValidData = false;
    for (let i = 0; i < categories.length; i++) {
      if (categories[i].value.trim()) {
        hasValidData = true;
        break;
      }
    }
    
    if (!hasValidData) {
      showNotification('Please fill in at least one category for the bar chart', 'warning');
      categories[0].focus();
      return false;
    }
    
    // Validate series values
    const seriesCount = parseInt(document.getElementById('seriesCount').value);
    for (let series = 1; series <= seriesCount; series++) {
      const seriesInputs = form.querySelectorAll(`input[name="bar_series${series}[]"]`);
      for (let i = 0; i < seriesInputs.length; i++) {
        const value = seriesInputs[i].value.trim();
        if (value) {
          // Remove % sign for validation
          const numericValue = value.replace('%', '');
          if (isNaN(numericValue) || numericValue === '') {
            showNotification(`Series ${series} value "${value}" is not a valid number or percentage`, 'warning');
            seriesInputs[i].focus();
            return false;
          }
        }
      }
    }
  }
  
  return true;
}

function validateGroupGraphForm(form) {
  // Check if we're on step 2
  const step2 = document.getElementById('manualGroupStep2');
  if (step2.classList.contains('hidden')) {
    showNotification('Please complete step 1 first', 'warning');
    return false;
  }
  
  // Validate each graph in the group
  const graphForms = document.querySelectorAll('#graphFormsContainer .graph-form-item');
  
  for (let i = 0; i < graphForms.length; i++) {
    const graphForm = graphForms[i];
    const titleInput = graphForm.querySelector('input[name="graphTitle[]"]');
    const isPieChart = graphForm.querySelector(`input[name="graphType[${i}]"][value="pie"]`).checked;
    
    // Validate title
    if (!titleInput || !titleInput.value.trim()) {
      showNotification(`Please enter a title for Graph ${i + 1}`, 'warning');
      titleInput.focus();
      return false;
    }
    
    if (isPieChart) {
      // Validate pie chart data
      const labels = graphForm.querySelectorAll(`input[name="label[${i}][]"]`);
      const values = graphForm.querySelectorAll(`input[name="value[${i}][]"]`);
      
      if (labels.length === 0 || values.length === 0) {
        showNotification(`Please add at least one data row for Graph ${i + 1}`, 'warning');
        return false;
      }
      
      let hasValidData = false;
      for (let j = 0; j < labels.length; j++) {
        if (labels[j].value.trim() && values[j].value.trim()) {
          hasValidData = true;
          break;
        }
      }
      
      if (!hasValidData) {
        showNotification(`Please fill in at least one label and value for Graph ${i + 1}`, 'warning');
        return false;
      }
      
      // Validate that values are numbers or percentages
      for (let j = 0; j < values.length; j++) {
        const value = values[j].value.trim();
        if (value) {
          // Remove % sign for validation
          const numericValue = value.replace('%', '');
          if (isNaN(numericValue) || numericValue === '') {
            showNotification(`Graph ${i + 1}: Value "${value}" is not a valid number or percentage`, 'warning');
            values[j].focus();
            return false;
          }
        }
      }
    } else {
      // Validate bar chart data
      const categories = graphForm.querySelectorAll(`input[name="bar_category[${i}][]"]`);
      
      if (categories.length === 0) {
        showNotification(`Please add at least one category for Graph ${i + 1}`, 'warning');
        return false;
      }
      
      let hasValidData = false;
      for (let j = 0; j < categories.length; j++) {
        if (categories[j].value.trim()) {
          hasValidData = true;
          break;
        }
      }
      
      if (!hasValidData) {
        showNotification(`Please fill in at least one category for Graph ${i + 1}`, 'warning');
        return false;
      }
      
      // Validate series values
      const seriesCountInput = graphForm.querySelector(`#seriesCount${i}`);
      if (seriesCountInput) {
        const seriesCount = parseInt(seriesCountInput.value);
        for (let series = 1; series <= seriesCount; series++) {
          const seriesInputs = graphForm.querySelectorAll(`input[name="bar_series${series}[${i}][]"]`);
          for (let j = 0; j < seriesInputs.length; j++) {
            const value = seriesInputs[j].value.trim();
            if (value) {
              // Remove % sign for validation
              const numericValue = value.replace('%', '');
              if (isNaN(numericValue) || numericValue === '') {
                showNotification(`Graph ${i + 1}: Series ${series} value "${value}" is not a valid number or percentage`, 'warning');
                seriesInputs[j].focus();
                return false;
              }
            }
          }
        }
      }
    }
  }
  
  return true;
}

// Add New Graph Modal Functions
let currentManualStep = 1;

function updateManualSteps() {
  // Update step indicators
  const steps = document.querySelectorAll('#groupGraphForm .w-8');
  const stepTexts = document.querySelectorAll('#groupGraphForm .text-sm.font-medium');
  
  steps.forEach((step, index) => {
    const stepNum = index + 1;
    if (stepNum < currentManualStep) {
      step.classList.remove('bg-gray-300', 'bg-orange-600');
      step.classList.add('bg-green-500');
    } else if (stepNum === currentManualStep) {
      step.classList.remove('bg-gray-300', 'bg-green-500');
      step.classList.add('bg-orange-600');
    } else {
      step.classList.remove('bg-orange-600', 'bg-green-500');
      step.classList.add('bg-gray-300');
    }
  });
  
  stepTexts.forEach((text, index) => {
    const stepNum = index + 1;
    if (stepNum < currentManualStep) {
      text.classList.remove('text-gray-500', 'text-orange-600');
      text.classList.add('text-green-600');
    } else if (stepNum === currentManualStep) {
      text.classList.remove('text-gray-500', 'text-green-600');
      text.classList.add('text-orange-600');
    } else {
      text.classList.remove('text-orange-600', 'text-green-600');
      text.classList.add('text-gray-500');
    }
  });
  
  // Show/hide step content
  const stepContents = document.querySelectorAll('#groupGraphForm .step-content');
  stepContents.forEach((content, index) => {
    if (index + 1 === currentManualStep) {
      content.classList.remove('hidden');
    } else {
      content.classList.add('hidden');
    }
  });
}

function goToManualStep1() {
  currentManualStep = 1;
  updateManualSteps();
}

function goToManualStep2() {
  const groupTitle = document.getElementById('manualGroupTitle').value;
  if (!groupTitle.trim()) {
    showNotification('Please enter a group title', 'warning');
    return;
  }
  currentManualStep = 2;
  updateManualSteps();
  updateGraphForms();
}

function updateGraphForms() {
  const graphCount = document.getElementById('manualGraphCount').value;
  const container = document.getElementById('graphFormsContainer');
  
  container.innerHTML = '';
  
  for (let i = 0; i < graphCount; i++) {
    const formHtml = `
      <div class="mb-6 p-4 bg-white rounded-lg border border-gray-200 shadow-sm graph-form-item">
        <h4 class="text-md font-semibold mb-3 text-gray-700">Graph ${i + 1}</h4>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">Graph Title</label>
          <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
                 name="graphTitle[]" type="text" placeholder="Enter graph title" required>
        </div>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">Graph Type</label>
          <div class="flex space-x-4">
            <label class="inline-flex items-center cursor-pointer">
              <input type="radio" name="graphType[${i}]" value="pie" class="form-radio h-4 w-4 text-orange-600 focus:ring-orange-500" checked onchange="switchGroupGraphType(${i}, 'pie')">
              <span class="ml-2 text-gray-700">Pie Chart</span>
            </label>
            <label class="inline-flex items-center cursor-pointer">
              <input type="radio" name="graphType[${i}]" value="bar" class="form-radio h-4 w-4 text-orange-600 focus:ring-orange-500" onchange="switchGroupGraphType(${i}, 'bar')">
              <span class="ml-2 text-gray-700">Bar Chart</span>
            </label>
          </div>
        </div>
        
        <div id="pieForm${i}" class="graph-form">
          <div class="mb-4">
            <div class="flex justify-between items-center mb-2">
              <label class="block text-gray-700 text-sm font-bold">
                Data Points
              </label>
              <button type="button" onclick="addGroupPieRow(${i})" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-sm rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-plus mr-1"></i> Add Row
              </button>
            </div>
            
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
              <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Label</th>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Value</th>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Color</th>
                    <th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">Actions</th>
                  </tr>
                </thead>
                <tbody id="pieTableBody${i}">
                  <tr class="data-row border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-4">
                      <input type="text" name="label[${i}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Label" required>
                    </td>
                    <td class="py-3 px-4">
                      <input type="text" name="value[${i}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Value" required>
                    </td>
                    <td class="py-3 px-4">
                      <div class="flex items-center">
                        <input type="color" name="color[${i}][]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="#FF6384">
                        <input type="text" name="color_text[${i}][]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="#FF6384">
                      </div>
                    </td>
                    <td class="py-3 px-4 text-center">
                      <button type="button" onclick="removeGroupPieRow(${i}, this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
                        <i class="fas fa-trash mr-1"></i> Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <div id="barForm${i}" class="graph-form hidden">
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Number of Series</label>
            <select id="seriesCount${i}" name="seriesCount[${i}]" onchange="updateGroupSeriesInputs(${i})" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
              <option value="1">1</option>
              <option value="2" selected>2</option>
              <option value="3">3</option>
              <option value="4">4</option>
              <option value="5">5</option>
            </select>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
              Series Labels
            </label>
            <div id="seriesLabelsContainer${i}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Series inputs will be dynamically added here -->
            </div>
          </div>
          
          <div class="mb-4">
            <div class="flex justify-between items-center mb-2">
              <label class="block text-gray-700 text-sm font-bold">
                Data Points
              </label>
              <button type="button" onclick="addGroupBarRow(${i})" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-sm rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-plus mr-1"></i> Add Row
              </button>
            </div>
            
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
              <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                  <tr id="barTableHeader${i}">
                    <!-- Table headers will be dynamically added here -->
                  </tr>
                </thead>
                <tbody id="barTableBody${i}">
                  <!-- Table rows will be dynamically added here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    `;
    
    container.innerHTML += formHtml;
    
    // Initialize the bar form for this graph if it's a bar chart
    setTimeout(() => {
      updateGroupSeriesInputs(i);
    }, 100);
  }
}

function switchGroupGraphType(index, type) {
  const pieForm = document.getElementById(`pieForm${index}`);
  const barForm = document.getElementById(`barForm${index}`);
  
  if (type === 'pie') {
    pieForm.classList.remove('hidden');
    barForm.classList.add('hidden');
  } else {
    pieForm.classList.add('hidden');
    barForm.classList.remove('hidden');
    // Initialize the series inputs for the bar form
    updateGroupSeriesInputs(index);
  }
}

function addGroupPieRow(index) {
  const tableBody = document.getElementById(`pieTableBody${index}`);
  const newRow = document.createElement('tr');
  newRow.className = 'data-row border-b border-gray-200 hover:bg-gray-50';
  newRow.innerHTML = `
    <td class="py-3 px-4">
      <input type="text" name="label[${index}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Label" required>
    </td>
    <td class="py-3 px-4">
      <input type="text" name="value[${index}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Value" required>
    </td>
    <td class="py-3 px-4">
      <div class="flex items-center">
        <input type="color" name="color[${index}][]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="#FF6384">
        <input type="text" name="color_text[${index}][]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="#FF6384">
      </div>
    </td>
    <td class="py-3 px-4 text-center">
      <button type="button" onclick="removeGroupPieRow(${index}, this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
        <i class="fas fa-trash mr-1"></i> Delete
      </button>
    </td>
  `;
  tableBody.appendChild(newRow);
}

function removeGroupPieRow(index, button) {
  const row = button.closest('tr');
  const tableBody = document.getElementById(`pieTableBody${index}`);
  
  // Don't remove if it's the only row
  if (tableBody.children.length > 1) {
    row.remove();
  } else {
    showNotification('You must have at least one data row', 'warning');
  }
}

function addGroupBarRow(index) {
  const tableBody = document.getElementById(`barTableBody${index}`);
  const seriesCount = document.getElementById(`seriesCount${index}`).value;
  
  const newRow = document.createElement('tr');
  newRow.className = 'bar-data-row border-b border-gray-200 hover:bg-gray-50';
  
  // Start with the category column
  let rowHtml = `
    <td class="py-3 px-4">
      <input type="text" name="bar_category[${index}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Category" required>
    </td>
  `;
  
  // Add columns for each series
  for (let i = 0; i < seriesCount; i++) {
    rowHtml += `
      <td class="py-3 px-4">
        <input type="text" name="bar_series${i + 1}[${index}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Series ${i + 1} Value" required>
      </td>
    `;
  }
  
  // Add the actions column
  rowHtml += `
    <td class="py-3 px-4 text-center">
      <button type="button" onclick="removeGroupBarRow(${index}, this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
        <i class="fas fa-trash mr-1"></i> Delete
      </button>
    </td>
  `;
  
  newRow.innerHTML = rowHtml;
  tableBody.appendChild(newRow);
}

function removeGroupBarRow(index, button) {
  const row = button.closest('tr');
  const tableBody = document.getElementById(`barTableBody${index}`);
  
  // Don't remove if it's the only row
  if (tableBody.children.length > 1) {
    row.remove();
  } else {
    showNotification('You must have at least one data row', 'warning');
  }
}

function updateGroupSeriesInputs(index) {
  const seriesCount = document.getElementById(`seriesCount${index}`).value;
  const container = document.getElementById(`seriesLabelsContainer${index}`);
  const tableHeader = document.getElementById(`barTableHeader${index}`);
  
  // Clear existing content
  container.innerHTML = '';
  tableHeader.innerHTML = '';
  
  // Add category header
  tableHeader.innerHTML += '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Category</th>';
  
  // Create series label and color inputs
  for (let i = 0; i < seriesCount; i++) {
    const seriesDiv = document.createElement('div');
    seriesDiv.innerHTML = `
      <input type="text" name="series${i + 1}Label[${index}]" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
               placeholder="Series ${i + 1} Label">
      <div class="mt-2 flex items-center">
        <label class="text-sm text-gray-600 mr-2">Color:</label>
        <input type="color" name="series${i + 1}Color[${index}]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${getChartColors(1, i)[0]}">
        <input type="text" name="series${i + 1}ColorText[${index}]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="${getChartColors(1, i)[0]}">
      </div>
    `;
    container.appendChild(seriesDiv);
    
    // Add series header to table
    tableHeader.innerHTML += `<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Series ${i + 1}</th>`;
  }
  
  // Add actions header to table
  tableHeader.innerHTML += '<th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">Actions</th>';
  
  // Update existing rows if any
  const tableBody = document.getElementById(`barTableBody${index}`);
  if (tableBody.children.length === 0) {
    // Add the first row if no rows exist
    addGroupBarRow(index);
  } else {
    // Update existing rows to match the new series count
    Array.from(tableBody.children).forEach(row => {
      // Keep the category column
      const categoryCell = row.cells[0];
      
      // Remove all cells except the first one (category) and the last one (actions)
      while (row.cells.length > 2) {
        row.removeChild(row.cells[1]);
      }
      
      // Add cells for each series
      for (let i = 0; i < seriesCount; i++) {
        const seriesCell = document.createElement('td');
        seriesCell.className = 'py-3 px-4';
        seriesCell.innerHTML = `
          <input type="text" name="bar_series${i + 1}[${index}][]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Series ${i + 1} Value" required>
        `;
        row.insertBefore(seriesCell, row.cells[row.cells.length - 1]);
      }
    });
  }
}

// Multi-file Group Upload Functions
let currentMultiStep = 1;

function updateMultiSteps() {
  // Update step indicators
  const steps = document.querySelectorAll('#groupUploadForm .w-8');
  const stepTexts = document.querySelectorAll('#groupUploadForm .text-sm.font-medium');
  
  steps.forEach((step, index) => {
    const stepNum = index + 1;
    if (stepNum < currentMultiStep) {
      step.classList.remove('bg-gray-300', 'bg-purple-600');
      step.classList.add('bg-green-500');
    } else if (stepNum === currentMultiStep) {
      step.classList.remove('bg-gray-300', 'bg-green-500');
      step.classList.add('bg-purple-600');
    } else {
      step.classList.remove('bg-purple-600', 'bg-green-500');
      step.classList.add('bg-gray-300');
    }
  });
  
  stepTexts.forEach((text, index) => {
    const stepNum = index + 1;
    if (stepNum < currentMultiStep) {
      text.classList.remove('text-gray-500', 'text-purple-600');
      text.classList.add('text-green-600');
    } else if (stepNum === currentMultiStep) {
      text.classList.remove('text-gray-500', 'text-green-600');
      text.classList.add('text-purple-600');
    } else {
      text.classList.remove('text-purple-600', 'text-green-600');
      text.classList.add('text-gray-500');
    }
  });
  
  // Show/hide step content
  const stepContents = document.querySelectorAll('#groupUploadForm .step-content');
  stepContents.forEach((content, index) => {
    if (index + 1 === currentMultiStep) {
      content.classList.remove('hidden');
    } else {
      content.classList.add('hidden');
    }
  });
}

function goToMultiStep1() {
  currentMultiStep = 1;
  updateMultiSteps();
}

function goToMultiStep2() {
  const groupTitle = document.getElementById('multiGroupTitle').value;
  if (!groupTitle.trim()) {
    showNotification('Please enter a group title', 'warning');
    return;
  }
  currentMultiStep = 2;
  updateMultiSteps();
  updateMultiGraphForms();
}

function updateMultiGraphForms() {
  const graphCount = document.getElementById('multiGraphCount').value;
  const container = document.getElementById('multiGraphFormsContainer');
  
  container.innerHTML = '';
  
  for (let i = 0; i < graphCount; i++) {
    const formHtml = `
      <div class="mb-6 p-4 bg-white rounded-lg border border-gray-200 shadow-sm graph-form-item">
        <h4 class="text-md font-semibold mb-3 text-gray-700">Graph ${i + 1}</h4>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">Graph Title</label>
          <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                 name="graphTitle[]" type="text" placeholder="Enter graph title" required>
        </div>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">Graph Type</label>
          <div class="flex space-x-4">
            <label class="inline-flex items-center cursor-pointer">
              <input type="radio" name="graphType[${i}]" value="pie" class="form-radio h-4 w-4 text-purple-600 focus:ring-purple-500" checked>
              <span class="ml-2 text-gray-700">Pie Chart</span>
            </label>
            <label class="inline-flex items-center cursor-pointer">
              <input type="radio" name="graphType[${i}]" value="bar" class="form-radio h-4 w-4 text-purple-600 focus:ring-purple-500">
              <span class="ml-2 text-gray-700">Bar Chart</span>
            </label>
          </div>
        </div>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">
            Upload CSV or Excel File for this Graph
          </label>
          <div class="border-2 border-dashed border-purple-300 rounded-lg p-6 text-center hover:border-purple-400 transition duration-200 bg-white">
            <i class="fas fa-cloud-upload-alt text-purple-400 text-4xl mb-3"></i>
            <p class="text-gray-600 mb-2">Drag and drop your file here or</p>
            <label for="graphFile${i}" class="cursor-pointer inline-block px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition duration-200">
              <i class="fas fa-folder-open mr-2"></i> Browse Files
            </label>
            <input type="file" id="graphFile${i}" name="graphFiles[]" class="hidden" accept=".csv,.xlsx,.xls" required onchange="updateMultiFileName(${i}, this)">
            <div id="graphFileName${i}" class="mt-2 text-sm text-gray-500">No file selected</div>
            <p class="text-xs text-gray-400 mt-2">Supported formats: CSV, XLSX, XLS (Max 10MB)</p>
          </div>
        </div>
        
        <div class="text-xs text-gray-600 bg-gray-50 p-3 rounded border">
          <p class="font-medium mb-1">File Format:</p>
          <p class="mb-1" id="formatInfo${i}">For Pie Charts: First column = Labels, Second column = Values</p>
        </div>
      </div>
    `;
    
    container.innerHTML += formHtml;
    
    // Add event listener for graph type change
    const pieRadio = container.querySelector(`input[name="graphType[${i}]"][value="pie"]`);
    const barRadio = container.querySelector(`input[name="graphType[${i}]"][value="bar"]`);
    const formatInfo = document.getElementById(`formatInfo${i}`);
    
    if (pieRadio && barRadio && formatInfo) {
      pieRadio.addEventListener('change', function() {
        formatInfo.textContent = 'For Pie Charts: First column = Labels, Second column = Values';
      });
      
      barRadio.addEventListener('change', function() {
        formatInfo.textContent = 'For Bar Charts: First column = Categories, Other columns = Series values';
      });
    }
    
    // Add drag and drop for this file input
    const uploadArea = container.querySelector(`#graphFile${i}`).parentElement;
    const fileInput = document.getElementById(`graphFile${i}`);
    
    if (uploadArea && fileInput) {
      // Prevent default drag behaviors
      ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
      });
      
      // Highlight drop area when item is dragged over it
      ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlightMulti, false);
      });
      
      ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlightMulti, false);
      });
      
      // Handle dropped files
      uploadArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
          fileInput.files = files;
          updateMultiFileName(i, fileInput);
        }
      }, false);
    }
  }
}

function updateMultiFileName(index, input) {
  const fileNameDiv = document.getElementById(`graphFileName${index}`);
  if (input.files && input.files[0]) {
    fileNameDiv.textContent = 'Selected file: ' + input.files[0].name;
    fileNameDiv.className = 'mt-2 text-sm text-green-600';
  } else {
    fileNameDiv.textContent = 'No file selected';
    fileNameDiv.className = 'mt-2 text-sm text-gray-500';
  }
}

function highlightMulti() {
  this.classList.add('border-purple-500', 'bg-purple-50');
}

function unhighlightMulti() {
  this.classList.remove('border-purple-500', 'bg-purple-50');
}

function preventDefaults(e) {
  e.preventDefault();
  e.stopPropagation();
}

// Upload Modal Functions
function showUploadModal() {
  document.getElementById('uploadModal').classList.remove('hidden');
  showUploadTab('individual'); // Show individual upload by default
  // Reset multi-step
  currentMultiStep = 1;
  updateMultiSteps();
}

function closeUploadModal() {
  document.getElementById('uploadModal').classList.add('hidden');
  // Reset forms
  document.getElementById('uploadIndividualForm').reset();
  document.getElementById('uploadGroupForm').reset();
  document.getElementById('individualFileName').textContent = 'No file selected';
  document.getElementById('individualFileName').className = 'mt-2 text-sm text-gray-500';
  // Reset multi-step form
  document.getElementById('multiGroupTitle').value = '';
  document.getElementById('multiGraphCount').value = '2';
  currentMultiStep = 1;
  updateMultiSteps();
}

function showUploadTab(tab) {
  const individualForm = document.getElementById('individualUploadForm');
  const groupForm = document.getElementById('groupUploadForm');
  const individualTab = document.getElementById('individualUploadTab');
  const groupTab = document.getElementById('groupUploadTab');
  
  if (tab === 'individual') {
    individualForm.classList.remove('hidden');
    groupForm.classList.add('hidden');
    individualTab.classList.add('border-blue-500', 'text-blue-500');
    individualTab.classList.remove('border-transparent', 'text-gray-500');
    groupTab.classList.remove('border-purple-500', 'text-purple-500');
    groupTab.classList.add('border-transparent', 'text-gray-500');
  } else {
    individualForm.classList.add('hidden');
    groupForm.classList.remove('hidden');
    groupTab.classList.add('border-purple-500', 'text-purple-500');
    groupTab.classList.remove('border-transparent', 'text-gray-500');
    individualTab.classList.remove('border-blue-500', 'text-blue-500');
    individualTab.classList.add('border-transparent', 'text-gray-500');
  }
}

function updateIndividualFileName(input) {
  const fileNameDiv = document.getElementById('individualFileName');
  if (input.files && input.files[0]) {
    fileNameDiv.textContent = 'Selected file: ' + input.files[0].name;
    fileNameDiv.className = 'mt-2 text-sm text-green-600';
  } else {
    fileNameDiv.textContent = 'No file selected';
    fileNameDiv.className = 'mt-2 text-sm text-gray-500';
  }
}

// Add New Graph Modal Functions
function showAddGraphModal() {
  document.getElementById('addGraphModal').classList.remove('hidden');
  showGraphTab('individual'); // Show individual graph by default
  // Reset manual step
  currentManualStep = 1;
  updateManualSteps();
  // Reset forms
  document.getElementById('createIndividualGraphForm').reset();
  document.getElementById('createGroupGraphForm').reset();
  // Reset to pie chart by default
  switchGraphType('pie');
}

function closeAddGraphModal() {
  document.getElementById('addGraphModal').classList.add('hidden');
  // Reset forms
  document.getElementById('createIndividualGraphForm').reset();
  document.getElementById('createGroupGraphForm').reset();
  // Reset manual step form
  document.getElementById('manualGroupTitle').value = '';
  document.getElementById('manualGraphCount').value = '2';
  currentManualStep = 1;
  updateManualSteps();
  // Reset to pie chart by default
  switchGraphType('pie');
}

function showGraphTab(tab) {
  const individualForm = document.getElementById('individualGraphForm');
  const groupForm = document.getElementById('groupGraphForm');
  const individualTab = document.getElementById('individualGraphTab');
  const groupTab = document.getElementById('groupGraphTab');
  
  if (tab === 'individual') {
    individualForm.classList.remove('hidden');
    groupForm.classList.add('hidden');
    individualTab.classList.add('border-orange-500', 'text-orange-500');
    individualTab.classList.remove('border-transparent', 'text-gray-500');
    groupTab.classList.remove('border-orange-500', 'text-orange-500');
    groupTab.classList.add('border-transparent', 'text-gray-500');
  } else {
    individualForm.classList.add('hidden');
    groupForm.classList.remove('hidden');
    groupTab.classList.add('border-orange-500', 'text-orange-500');
    groupTab.classList.remove('border-transparent', 'text-gray-500');
    individualTab.classList.remove('border-orange-500', 'text-orange-500');
    individualTab.classList.add('border-transparent', 'text-gray-500');
  }
}

// Original Graph Functions
function switchGraphType(type) {
  if (type === 'pie') {
    document.getElementById('pieForm').style.display = 'block';
    document.getElementById('barForm').style.display = 'none';
  } else {
    document.getElementById('pieForm').style.display = 'none';
    document.getElementById('barForm').style.display = 'block';
    // Initialize the series inputs for the bar form
    updateSeriesInputs();
  }
}

function updateSeriesInputs() {
  const seriesCount = document.getElementById('seriesCount').value;
  const container = document.getElementById('seriesLabelsContainer');
  const tableHeader = document.getElementById('barTableHeader');
  
  // Clear existing content
  container.innerHTML = '';
  tableHeader.innerHTML = '';
  
  // Add category header
  tableHeader.innerHTML += '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Category</th>';
  
  // Create series label and color inputs
  for (let i = 0; i < seriesCount; i++) {
    const seriesDiv = document.createElement('div');
    seriesDiv.innerHTML = `
      <input type="text" name="series${i + 1}Label" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
               placeholder="Series ${i + 1} Label">
      <div class="mt-2 flex items-center">
        <label class="text-sm text-gray-600 mr-2">Color:</label>
        <input type="color" name="series${i + 1}Color" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${getChartColors(1, i)[0]}">
        <input type="text" name="series${i + 1}ColorText" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="${getChartColors(1, i)[0]}">
      </div>
    `;
    container.appendChild(seriesDiv);
    
    // Add series header to table
    tableHeader.innerHTML += `<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Series ${i + 1}</th>`;
  }
  
  // Add actions header to table
  tableHeader.innerHTML += '<th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">Actions</th>';
  
  // Update existing rows if any
  const tableBody = document.getElementById('barTableBody');
  if (tableBody.children.length === 0) {
    // Add the first row if no rows exist
    addBarRow();
  } else {
    // Update existing rows to match the new series count
    Array.from(tableBody.children).forEach(row => {
      // Keep the category column
      const categoryCell = row.cells[0];
      
      // Remove all cells except the first one (category) and the last one (actions)
      while (row.cells.length > 2) {
        row.removeChild(row.cells[1]);
      }
      
      // Add cells for each series
      for (let i = 0; i < seriesCount; i++) {
        const seriesCell = document.createElement('td');
        seriesCell.className = 'py-3 px-4';
        seriesCell.innerHTML = `
          <input type="text" name="bar_series${i + 1}[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Series ${i + 1} Value" required>
        `;
        row.insertBefore(seriesCell, row.cells[row.cells.length - 1]);
      }
    });
  }
}

function addBarRow() {
  const tableBody = document.getElementById('barTableBody');
  const seriesCount = document.getElementById('seriesCount').value;
  
  const newRow = document.createElement('tr');
  newRow.className = 'bar-data-row border-b border-gray-200 hover:bg-gray-50';
  
  // Start with the category column
  let rowHtml = `
    <td class="py-3 px-4">
      <input type="text" name="bar_category[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Category" required>
    </td>
  `;
  
  // Add columns for each series
  for (let i = 0; i < seriesCount; i++) {
    rowHtml += `
      <td class="py-3 px-4">
        <input type="text" name="bar_series${i + 1}[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Series ${i + 1} Value" required>
      </td>
    `;
  }
  
  // Add the actions column
  rowHtml += `
    <td class="py-3 px-4 text-center">
      <button type="button" onclick="removeBarRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
        <i class="fas fa-trash mr-1"></i> Delete
      </button>
    </td>
  `;
  
  newRow.innerHTML = rowHtml;
  tableBody.appendChild(newRow);
}

function removeBarRow(button) {
  const row = button.closest('tr');
  const tableBody = document.getElementById('barTableBody');
  
  // Don't remove if it's the only row
  if (tableBody.children.length > 1) {
    row.remove();
  } else {
    showNotification('You must have at least one data row', 'warning');
  }
}

function addPieRow() {
  const tableBody = document.getElementById('pieTableBody');
  const newRow = document.createElement('tr');
  newRow.className = 'data-row border-b border-gray-200 hover:bg-gray-50';
  newRow.innerHTML = `
    <td class="py-3 px-4">
      <input type="text" name="label[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Label" required>
    </td>
    <td class="py-3 px-4">
      <input type="text" name="value[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Value" required>
    </td>
    <td class="py-3 px-4">
      <div class="flex items-center">
        <input type="color" name="color[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="#FF6384">
        <input type="text" name="color_text[]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="#FF6384">
      </div>
    </td>
    <td class="py-3 px-4 text-center">
      <button type="button" onclick="removePieRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
        <i class="fas fa-trash mr-1"></i> Delete
      </button>
    </td>
  `;
  tableBody.appendChild(newRow);
}

function removePieRow(button) {
  const row = button.closest('tr');
  const tableBody = document.getElementById('pieTableBody');
  
  // Don't remove if it's the only row
  if (tableBody.children.length > 1) {
    row.remove();
  } else {
    showNotification('You must have at least one data row', 'warning');
  }
}

// Edit graph functions
function editGraph(graphId) {
  // Show loading indicator
  document.getElementById('editGraphContent').innerHTML = `
    <div class="text-center py-8">
      <i class="fas fa-spinner fa-spin text-2xl text-orange-500 mb-2"></i>
      <p>Loading graph data...</p>
    </div>
  `;
  
  // Show the modal
  document.getElementById('editGraphModal').classList.remove('hidden');
  
  // Fetch graph data
  fetch(`get_graph.php?id=${graphId}`)
    .then(response => response.json())
    .then(data => {
      // Build the edit form based on graph type
      let formHtml = `
        <form id="editGraphForm" action="update_graph.php" method="post">
          <input type="hidden" id="editGraphId" name="graph_id" value="${data.id}">
          <input type="hidden" name="graphType" value="${data.type}">
          <input type="hidden" name="mainTab" value="upload">
          <input type="hidden" name="currentTab" value="upload-graphs">
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="editGraphTitle">
              Graph Title
            </label>
            <input class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
                   id="editGraphTitle" name="graphTitle" type="text" value="${data.title}" required>
          </div>
      `;
      
      if (data.type === 'pie') {
        formHtml += `
          <div class="mb-4">
            <div class="flex justify-between items-center mb-2">
              <label class="block text-gray-700 text-sm font-bold">
                Data Points
              </label>
              <button type="button" onclick="addEditPieRow()" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-sm rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-plus mr-1"></i> Add Row
              </button>
            </div>
            
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
              <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Label</th>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Value</th>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Color</th>
                    <th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">Actions</th>
                  </tr>
                </thead>
                <tbody id="editPieTableBody">
        `;
        
        // Display existing data rows
        data.data.forEach(item => {
          formHtml += `
            <tr class="data-row border-b border-gray-200 hover:bg-gray-50">
              <td class="py-3 px-4">
                <input type="text" name="label[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" value="${item.label}" required>
              </td>
              <td class="py-3 px-4">
                <input type="text" name="value[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" value="${formatValueForInput(item.value, item.format)}" required>
              </td>
              <td class="py-3 px-4">
                <div class="flex items-center">
                  <input type="color" name="color[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${item.color || '#FF6384'}">
                  <input type="text" name="color_text[]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="${item.color || '#FF6384'}">
                </div>
              </td>
              <td class="py-3 px-4 text-center">
                <button type="button" onclick="removeEditPieRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
                  <i class="fas fa-trash mr-1"></i> Delete
                </button>
              </td>
            </tr>
          `;
        });
        
        formHtml += `
                </tbody>
              </table>
            </div>
          </div>
        `;
      } else {
        // Bar chart form
        // Determine the number of series from the data
        let seriesCount = 2; // Default to 2
        if (data.data && data.data.length > 0) {
          // Count the number of series by checking the properties of the first data item
          const firstItem = data.data[0];
          let count = 0;
          for (let i = 1; i <= 5; i++) {
            if (firstItem[`series${i}`] !== undefined) {
              count++;
            }
          }
          seriesCount = count;
        }

        formHtml += `
          <input type="hidden" id="editSeriesCount" name="seriesCount" value="${seriesCount}">
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
              Series Labels
            </label>
            <div id="editSeriesLabelsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        `;

        // Create series label and color inputs with existing values
        for (let i = 0; i < seriesCount; i++) {
          const seriesIndex = i + 1;
          const seriesLabel = data.data[0][`series${seriesIndex}_label`] || `Series ${seriesIndex}`;
          const seriesColor = data.data[0][`series${seriesIndex}_color`] || getChartColors(1, i)[0];

          formHtml += `
            <div>
              <input type="text" name="series${seriesIndex}Label" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
                       value="${seriesLabel}" placeholder="Series ${seriesIndex} Label">
              <div class="mt-2 flex items-center">
                <label class="text-sm text-gray-600 mr-2">Color:</label>
                <input type="color" name="series${seriesIndex}Color" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="${seriesColor}">
                <input type="text" name="series${seriesIndex}ColorText" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="${seriesColor}">
              </div>
            </div>
          `;
        }

        formHtml += `
            </div>
          </div>
          
          <div class="mb-4">
            <div class="flex justify-between items-center mb-2">
              <label class="block text-gray-700 text-sm font-bold">
                Data Points
              </label>
              <button type="button" onclick="addEditBarRow()" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-sm rounded-lg transition duration-200 transform hover:scale-110">
                <i class="fas fa-plus mr-1"></i> Add Row
              </button>
            </div>
            
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
              <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Category Name</th>
                    <th class="py-3 px-4 text-center text-sm font-semibold text-gray-700" colspan="${seriesCount}">Values</th>
                    <th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">Row Actions</th>
                  </tr>
                  <tr id="editBarTableHeader">
                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700"></th>
                    <!-- Series headers will be dynamically added here -->
                  </tr>
                </thead>
                <tbody id="editBarTableBody">
        `;

        // Add existing data rows
        data.data.forEach(item => {
          let rowHtml = `
            <tr class="bar-data-row border-b border-gray-200 hover:bg-gray-50">
              <td class="py-3 px-4">
                <input type="text" name="bar_category[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" value="${item.category}" required>
              </td>
          `;

          // Add cells for each series
          for (let i = 1; i <= seriesCount; i++) {
            const seriesValue = item[`series${i}`] || '';
            const seriesFormat = item[`series${i}_format`] || '';
            rowHtml += `
              <td class="py-3 px-4">
                <input type="text" name="bar_series${i}[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" value="${formatValueForInput(seriesValue, seriesFormat)}" required>
              </td>
            `;
          }

          // Add the actions column
          rowHtml += `
              <td class="py-3 px-4 text-center">
                <button type="button" onclick="removeEditBarRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
                  <i class="fas fa-trash mr-1"></i> Delete
                </button>
              </td>
            </tr>
          `;

          formHtml += rowHtml;
        });

        formHtml += `
                </tbody>
              </table>
            </div>
          </div>
        `;
      }
      
      formHtml += `
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeEditGraphModal()" class="px-4 py-2 border border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 border border-green-500 text-green-500 hover:bg-green-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
              <i class="fas fa-save mr-2"></i> Update Graph
            </button>
          </div>
        </form>
      `;
      
      // Update the modal content
      document.getElementById('editGraphContent').innerHTML = formHtml;
      
      // Initialize the series headers for the bar form
      if (data.type === 'bar') {
        setTimeout(() => {
          const tableHeader = document.getElementById('editBarTableHeader');
          if (tableHeader) {
            // Add series headers
            for (let i = 1; i <= seriesCount; i++) {
              const seriesLabel = data.data[0][`series${i}_label`] || `Series ${i}`;
              tableHeader.innerHTML += `<th class="py-3 px-4 text-center text-sm font-semibold text-gray-700">${seriesLabel}</th>`;
            }
          }
        }, 100);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('editGraphContent').innerHTML = `
        <div class="text-center py-8">
          <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
          <p class="text-red-700">Failed to load graph data</p>
          <p class="text-gray-600">${error.message}</p>
          <button onclick="closeEditGraphModal()" class="mt-4 px-4 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white rounded-lg transition duration-200 transform hover:scale-110">
            Close
          </button>
        </div>
      `;
    });
}

function closeEditGraphModal() {
  document.getElementById('editGraphModal').classList.add('hidden');
}

function addEditPieRow() {
  const tableBody = document.getElementById('editPieTableBody');
  const newRow = document.createElement('tr');
  newRow.className = 'data-row border-b border-gray-200 hover:bg-gray-50';
  newRow.innerHTML = `
    <td class="py-3 px-4">
      <input type="text" name="label[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Label" required>
    </td>
    <td class="py-3 px-4">
      <input type="text" name="value[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Value" required>
    </td>
    <td class="py-3 px-4">
      <div class="flex items-center">
        <input type="color" name="color[]" class="w-10 h-10 border border-gray-300 rounded cursor-pointer" value="#FF6384">
        <input type="text" name="color_text[]" class="ml-2 w-20 py-1 px-2 border border-gray-300 rounded text-sm" value="#FF6384">
      </div>
    </td>
    <td class="py-3 px-4 text-center">
      <button type="button" onclick="removeEditPieRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
        <i class="fas fa-trash mr-1"></i> Delete
      </button>
    </td>
  `;
  tableBody.appendChild(newRow);
}

function removeEditPieRow(button) {
  const row = button.closest('tr');
  const tableBody = document.getElementById('editPieTableBody');
  
  // Don't remove if it's the only row
  if (tableBody.children.length > 1) {
    row.remove();
  } else {
    showNotification('You must have at least one data row', 'warning');
  }
}

function addEditBarRow() {
  const tableBody = document.getElementById('editBarTableBody');
  const seriesCount = document.getElementById('editSeriesCount').value;
  
  const newRow = document.createElement('tr');
  newRow.className = 'bar-data-row border-b border-gray-200 hover:bg-gray-50';
  
  let rowHtml = `
    <td class="py-3 px-4">
      <input type="text" name="bar_category[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Enter category name (e.g., Month, Product)" required>
    </td>
  `;

  // And for the series value inputs
  for (let i = 0; i < seriesCount; i++) {
    rowHtml += `
      <td class="py-3 px-4">
        <input type="text" name="bar_series${i + 1}[]" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Enter value (e.g., 100, 25%)" required>
      </td>
    `;
  }
  
  // Add the actions column
  rowHtml += `
    <td class="py-3 px-4 text-center">
      <button type="button" onclick="removeEditBarRow(this)" class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition duration-200 transform hover:scale-110">
        <i class="fas fa-trash mr-1"></i> Delete
      </button>
    </td>
  `;
  
  newRow.innerHTML = rowHtml;
  tableBody.appendChild(newRow);
}

function removeEditBarRow(button) {
  const row = button.closest('tr');
  const tableBody = document.getElementById('editBarTableBody');
  
  // Don't remove if it's the only row
  if (tableBody.children.length > 1) {
    row.remove();
  } else {
    showNotification('You must have at least one data row', 'warning');
  }
}

// Delete/Archive modal functions
function showDeleteArchiveModal(graphId) {
  currentGraphId = graphId;
  document.getElementById('deleteArchiveModal').classList.remove('hidden');
}

function closeDeleteArchiveModal() {
  document.getElementById('deleteArchiveModal').classList.add('hidden');
  currentGraphId = null;
}

function deleteGraph() {
  if (!currentGraphId) return;
  
  // Create a form to submit the delete request
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'delete_graph.php';
  
  // Add graph ID
  const graphIdInput = document.createElement('input');
  graphIdInput.type = 'hidden';
  graphIdInput.name = 'graph_id';
  graphIdInput.value = currentGraphId;
  form.appendChild(graphIdInput);
  
  // Add tab state parameters
  const mainTabInput = document.createElement('input');
  mainTabInput.type = 'hidden';
  mainTabInput.name = 'mainTab';
  mainTabInput.value = 'upload';
  form.appendChild(mainTabInput);
  
  const currentTabInput = document.createElement('input');
  currentTabInput.type = 'hidden';
  currentTabInput.name = 'currentTab';
  currentTabInput.value = 'upload-graphs';
  form.appendChild(currentTabInput);
  
  // Submit the form
  document.body.appendChild(form);
  form.submit();
}

function archiveGraph() {
  if (!currentGraphId) return;
  
  // Create a form to submit the archive request
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'archive_graph.php';
  
  // Add graph ID
  const graphIdInput = document.createElement('input');
  graphIdInput.type = 'hidden';
  graphIdInput.name = 'graph_id';
  graphIdInput.value = currentGraphId;
  form.appendChild(graphIdInput);
  
  // Add tab state parameters
  const mainTabInput = document.createElement('input');
  mainTabInput.type = 'hidden';
  mainTabInput.name = 'mainTab';
  mainTabInput.value = 'upload';
  form.appendChild(mainTabInput);
  
  const currentTabInput = document.createElement('input');
  currentTabInput.type = 'hidden';
  currentTabInput.name = 'currentTab';
  currentTabInput.value = 'upload-graphs';
  form.appendChild(currentTabInput);
  
  // Submit the form
  document.body.appendChild(form);
  form.submit();
}

// Format number for display - preserve original format
function formatNumber(value, originalValue, format) {
  // If format is percentage, return with % sign
  if (format === 'percentage') {
    if (value == Math.round(value)) {
      return Math.round(value) + '%';
    }
    return parseFloat(value).toFixed(2) + '%';
  }
  
  // If original value is provided and contains %, return it as is
  if (originalValue && typeof originalValue === 'string' && originalValue.includes('%')) {
    return originalValue;
  }
  
  // If it's a whole number, return without decimals
  if (value == Math.round(value)) {
    return Math.round(value);
  }
  
  // Otherwise, return with 2 decimal places
  return parseFloat(value).toFixed(2);
}

// Format value for input field - preserve original format if possible
function formatValueForInput(value, format) {
  // If format is percentage, return with % sign
  if (format === 'percentage') {
    return value + '%';
  }
  
  // If it's already a string with % sign, return as is
  if (typeof value === 'string' && value.includes('%')) {
    return value;
  }
  
  // If it's a number, format it appropriately
  if (typeof value === 'number') {
    if (value == Math.round(value)) {
      return Math.round(value).toString();
    }
    return value.toFixed(2);
  }
  
  // If it's a string without %, try to parse and format
  const numValue = parseFloat(value);
  if (!isNaN(numValue)) {
    if (numValue == Math.round(numValue)) {
      return Math.round(numValue).toString();
    }
    return numValue.toFixed(2);
  }
  
  // Return as is if we can't parse it
  return value;
}

// Format percentage for display - add % sign and remove .00 for whole numbers
function formatPercentage(value) {
  const numValue = parseFloat(value);
  if (numValue == Math.round(numValue)) {
    return Math.round(numValue) + '%';
  }
  return numValue.toFixed(2) + '%';
}

// Show notification function
function showNotification(message, type = 'info') {
  // Create notification element
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 flex items-center transform transition-all duration-300 translate-x-full`;
  
  // Set color based on type
  if (type === 'success') {
    notification.classList.add('bg-green-500', 'text-white');
  } else if (type === 'warning') {
    notification.classList.add('bg-yellow-500', 'text-white');
  } else if (type === 'error') {
    notification.classList.add('bg-red-500', 'text-white');
  } else {
    notification.classList.add('bg-blue-500', 'text-white');
  }
  
  // Add icon based on type
  let icon = 'fa-info-circle';
  if (type === 'success') icon = 'fa-check-circle';
  else if (type === 'warning') icon = 'fa-exclamation-triangle';
  else if (type === 'error') icon = 'fa-times-circle';
  
  // Set content
  notification.innerHTML = `
    <i class="fas ${icon} mr-3 text-xl"></i>
    <span>${message}</span>
    <button onclick="this.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
      <i class="fas fa-times"></i>
    </button>
  `;
  
  // Add to DOM
  document.body.appendChild(notification);
  
  // Animate in
  setTimeout(() => {
    notification.classList.remove('translate-x-full');
  }, 10);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.classList.add('translate-x-full');
    setTimeout(() => {
      notification.remove();
    }, 300);
  }, 5000);
}

// Flag to track if charts have been initialized
let chartsInitialized = false;

// Initialize all charts on page load
document.addEventListener('DOMContentLoaded', function() {
  // Check if we're on the graphs tab
  const graphsTab = document.getElementById('upload-graphs');
  if (graphsTab && graphsTab.classList.contains('active')) {
    // Initialize all graphs
    initializeAllCharts();
    chartsInitialized = true;
    isGraphsTabActive = true;
  }
  
  // Set up a MutationObserver to detect when the graphs tab becomes active
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
        const target = mutation.target;
        if (target.id === 'upload-graphs') {
          // Check if the tab is now active
          const isActive = target.classList.contains('active');
          
          if (isActive && !isGraphsTabActive) {
            // The graphs tab is now active, initialize charts
            console.log('Graphs tab activated, initializing charts');
            setTimeout(initializeAllCharts, 100);
            chartsInitialized = true;
            isGraphsTabActive = true;
          } else if (!isActive && isGraphsTabActive) {
            // The graphs tab is now inactive
            console.log('Graphs tab deactivated');
            isGraphsTabActive = false;
          }
        }
      }
    });
  });
  
  // Start observing the graphs tab for class changes
  if (graphsTab) {
    observer.observe(graphsTab, { attributes: true });
  }
  
  // Also handle window resize to ensure charts are properly sized
  window.addEventListener('resize', function() {
    if (isGraphsTabActive) {
      // Reinitialize all charts on resize only if graphs tab is active
      setTimeout(initializeAllCharts, 100);
    }
  });
  
  // Add event listener for visibility change to handle tab switching
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && isGraphsTabActive) {
      // Page became visible again and we're on graphs tab, reinitialize charts
      console.log('Page became visible, reinitializing charts');
      setTimeout(initializeAllCharts, 100);
    }
  });
  
  // Add event listeners for color pickers to sync with text inputs
  document.addEventListener('input', function(e) {
    if (e.target.type === 'color') {
      const textInput = e.target.parentElement.querySelector('input[type="text"]');
      if (textInput) {
        textInput.value = e.target.value;
      }
    }
  });
  
  document.addEventListener('input', function(e) {
    if (e.target.type === 'text' && e.target.name.includes('color_text')) {
      const colorInput = e.target.parentElement.querySelector('input[type="color"]');
      if (colorInput && /^#[0-9A-F]{6}$/i.test(e.target.value)) {
        colorInput.value = e.target.value;
      }
    }
  });
  
  // Initialize the bar form series inputs if bar form is visible
  if (document.getElementById('barForm') && document.getElementById('barForm').style.display !== 'none') {
    updateSeriesInputs();
  }
  
  // Handle drag and drop for individual file upload
  const individualUploadArea = document.querySelector('#individualUploadForm .border-2.border-dashed');
  const individualFileInput = document.getElementById('individualFileUpload');
  
  if (individualUploadArea && individualFileInput) {
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      individualUploadArea.addEventListener(eventName, preventDefaults, false);
      document.body.addEventListener(eventName, preventDefaults, false);
    });
    
    // Highlight drop area when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
      individualUploadArea.addEventListener(eventName, highlightIndividual, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
      individualUploadArea.addEventListener(eventName, unhighlightIndividual, false);
    });
    
    // Handle dropped files
    individualUploadArea.addEventListener('drop', handleIndividualDrop, false);
  }
  
  function highlightIndividual() {
    individualUploadArea.classList.add('border-blue-500', 'bg-blue-50');
  }
  
  function unhighlightIndividual() {
    individualUploadArea.classList.remove('border-blue-500', 'bg-blue-50');
  }
  
  function handleIndividualDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
      individualFileInput.files = files;
      updateIndividualFileName(individualFileInput);
    }
  }
  
  // Initialize multi-step form
  const multiGraphCountSelect = document.getElementById('multiGraphCount');
  if (multiGraphCountSelect) {
    multiGraphCountSelect.addEventListener('change', updateMultiGraphForms);
  }
  
  // Prevent default form submission and use custom validation
  const individualForm = document.getElementById('createIndividualGraphForm');
  const groupForm = document.getElementById('createGroupGraphForm');
  
  if (individualForm) {
    individualForm.addEventListener('submit', function(e) {
      e.preventDefault();
      validateAndSubmitForms();
    });
  }
  
  if (groupForm) {
    groupForm.addEventListener('submit', function(e) {
      e.preventDefault();
      validateAndSubmitForms();
    });
  }
});

// Function to initialize all charts
function initializeAllCharts() {
  console.log('Initializing all charts...');
  
  // Get all canvas elements for charts
  const chartCanvases = document.querySelectorAll('canvas[id^="graph"]');
  
  if (chartCanvases.length === 0) {
    console.log('No chart canvases found');
    return;
  }
  
  chartCanvases.forEach((canvas, index) => {
    const graphId = canvas.id.replace('graph', '');
    const graphType = canvas.getAttribute('data-type');
    
    console.log(`Initializing chart ${index}: ${canvas.id}, type: ${graphType}`);
    
    // Get the graph data from the data attribute
    let graphData;
    try {
      // Try to parse the data directly from the data-graph attribute
      const graphDataAttr = canvas.getAttribute('data-graph');
      if (!graphDataAttr) {
        console.error(`No data-graph attribute found for canvas ${canvas.id}`);
        return;
      }
      graphData = JSON.parse(graphDataAttr);
    } catch (e) {
      console.error(`Error parsing graph data for canvas ${canvas.id}:`, e);
      return;
    }
    
    // Validate the data structure
    if (!Array.isArray(graphData) || graphData.length === 0) {
      console.error(`Invalid graph data structure for canvas ${canvas.id}`);
      return;
    }
    
    // Destroy existing chart if it exists
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
      existingChart.destroy();
    }
    
    // Create the chart with original data (no sorting)
    if (graphType === 'pie') {
      // For pie charts with many labels, adjust the legend position and chart size
      const labelCount = graphData.length;
      let legendPosition = 'bottom';
      let chartHeight = 250;
      
      // Adjust for many labels
      if (labelCount > 8) {
        legendPosition = 'right';
        chartHeight = 300;
      }
      
      // Set the container height
      const container = canvas.parentElement;
      container.style.height = chartHeight + 'px';
      
      // Use custom colors if available, otherwise use palette
      const backgroundColor = graphData.map(item => item.color || getChartColors(graphData.length, index)[graphData.indexOf(item)]);
      
      new Chart(canvas, {
        type: 'pie',  // Changed from 'doughnut' to 'pie' for full pie chart
        data: {
          labels: graphData.map(item => item.label || ''),
          datasets: [{
            data: graphData.map(item => item.value || 0),
            backgroundColor: backgroundColor,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 500
          },
          plugins: {
            legend: {
              position: legendPosition,
              labels: {
                boxWidth: 15,
                font: {
                  size: labelCount > 8 ? 10 : 12
                },
                padding: labelCount > 8 ? 8 : 15
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.parsed !== null) {
                    // Get the original value and format
                    const originalItem = graphData[context.dataIndex];
                    label += formatNumber(context.parsed, originalItem.original_value, originalItem.format);
                  }
                  return label;
                }
              }
            }
          }
        }
      });
      console.log(`Successfully initialized pie chart ${index}`);
    } else {
      // For bar charts - ensure proper container dimensions
      const container = canvas.parentElement;
      container.style.height = '300px';
      container.style.width = '100%';
      
      // Ensure canvas has proper dimensions
      canvas.style.height = '100%';
      canvas.style.width = '100%';
      
      // Determine the number of series from the data
      let seriesCount = 2; // Default to 2
      if (graphData && graphData.length > 0) {
        // Count the number of series by checking the properties of the first data item
        const firstItem = graphData[0];
        let count = 0;
        for (let i = 1; i <= 5; i++) {
          if (firstItem[`series${i}`] !== undefined) {
            count++;
          }
        }
        seriesCount = count;
      }
      
      // Create datasets for each series
      const datasets = [];
      for (let i = 1; i <= seriesCount; i++) {
        const seriesLabel = graphData[0][`series${i}_label`] || `Series ${i}`;
        const seriesColor = graphData[0][`series${i}_color`] || getChartColors(1, index + i - 1)[0];
        
        datasets.push({
          label: seriesLabel,
          data: graphData.map(item => item[`series${i}`] || 0),
          backgroundColor: seriesColor,
          borderWidth: 1
        });
      }
      
      new Chart(canvas, {
        type: 'bar',
        data: {
          labels: graphData.map(item => item.category || ''),
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              }
            },
            x: {
              ticks: {
                autoSkip: false,
                maxRotation: 90,
                minRotation: 0,
                font: {
                  size: 6
                }
              },
              grid: {
                display: false
              }
            }
          },
          plugins: {
            legend: {
              position: 'top',
              labels: {
                boxWidth: 15,
                font: {
                  size: 12
                }
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.parsed.y !== null) {
                    // Get the original value and format
                    const originalItem = graphData[context.dataIndex];
                    const seriesIndex = context.datasetIndex + 1;
                    const format = originalItem[`series${seriesIndex}_format`];
                    label += formatNumber(context.parsed.y, null, format);
                  }
                  return label;
                }
              }
            }
          },
          // Add layout padding to ensure labels are visible
          layout: {
            padding: {
              left: 10,
              right: 20,
              top: 10,
              bottom: 20
            }
          }
        }
      });
      console.log(`Successfully initialized bar chart ${index} with ${seriesCount} series`);
    }
  });
}

// Function to initialize charts when the tab is shown
function initializeChartsWhenTabIsShown() {
  // Check if we're on the graphs tab
  const graphsTab = document.getElementById('upload-graphs');
  if (graphsTab && graphsTab.classList.contains('active')) {
    // Initialize all graphs that haven't been initialized yet
    if (!chartsInitialized) {
      initializeAllCharts();
      chartsInitialized = true;
    } else {
      // If charts are already initialized, just resize them
      const chartCanvases = document.querySelectorAll('canvas[id^="graph"]');
      chartCanvases.forEach((canvas) => {
        const chart = Chart.getChart(canvas);
        if (chart) {
          chart.resize();
        }
      });
    }
  }
}
</script>
<style>
.graph-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: auto; /* Changed from fixed height to auto for better responsiveness */
    display: flex;
    flex-direction: column;
    overflow: hidden;
    width: 100%; /* Ensure full width */
    max-width: 100%;
    margin-bottom: 1.5rem; /* Add some spacing between cards */
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.graph-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.chart-container {
    position: relative;
    width: 100%;
    height: 400px; /* Increased height for better visibility */
    min-height: 400px;
}

/* Ensure the content area grows to fill available space */
.graph-card .flex-grow {
    flex-grow: 1;
    overflow: hidden;
    min-height: 450px; /* Minimum height for consistent display */
}

/* Add border-top to separate content from buttons */
.graph-card .border-t {
    border-top: 1px solid #f3f4f6;
}

/* Ensure text wraps properly */
.break-words {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Set max width for table cells to prevent overflow */
.max-w-[100px] {
    max-width: 100px;
}

/* Loading animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

/* Ensure canvas elements are visible */
canvas {
    display: block !important;
    visibility: visible !important;
    width: 100% !important;
    height: 100% !important;
}

/* Custom scrollbar for tables */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Graph card hover effect */
.graph-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.graph-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Smooth transitions for all interactive elements */
button, input, select, .graph-card {
    transition: all 0.2s ease;
}

/* Color picker styling */
input[type="color"] {
    -webkit-appearance: none;
    border: none;
    cursor: pointer;
}
input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
}
input[type="color"]::-webkit-color-swatch {
    border: none;
    border-radius: 4px;
}

/* Upload form specific styles */
.border-2.border-dashed {
    transition: all 0.3s ease;
}

.border-2.border-dashed:hover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

input[type="file"] {
    cursor: pointer;
}

/* Add Graph modal specific styles */
.border-orange-300 {
    border-color: #fdba74;
}

.hover\:border-orange-400:hover {
    border-color: #fb923c;
}

.bg-orange-50 {
    background-color: #fff7ed;
}

.hover\:bg-orange-50:hover {
    background-color: #fff7ed;
}

/* Group file upload specific styles */
.bg-purple-50 {
  background-color: #faf5ff;
}

.border-purple-200 {
  border-color: #e9d5ff;
}

.border-purple-300 {
  border-color: #d8b4fe;
}

.hover\:border-purple-400:hover {
  border-color: #c084fc;
}

.bg-purple-500 {
  background-color: #a855f7;
}

.hover\:bg-purple-600:hover {
  background-color: #9333ea;
}

.text-purple-500 {
  color: #a855f7;
}

.text-purple-400 {
  color: #c084fc;
}

.file\:bg-purple-50 {
  background-color: #faf5ff !important;
}

.file\:text-purple-700 {
  color: #7e22ce !important;
}

.hover\:file\:bg-purple-100:hover {
  background-color: #f3e8ff !important;
}

/* Modal styles */
.upload-tab, .graph-tab {
  transition: all 0.3s ease;
}

/* Step content styles */
.step-content {
  transition: all 0.3s ease;
}

/* Graph form item styles */
.graph-form-item {
  transition: all 0.3s ease;
}

.graph-form-item:hover {
  border-color: #a855f7;
}

/* Multi-step form styles */
#multiGraphFormsContainer .border-2.border-dashed,
#graphFormsContainer .border-2.border-dashed {
  transition: all 0.3s ease;
}

#multiGraphFormsContainer .border-2.border-dashed:hover {
  border-color: #a855f7;
  background-color: #faf5ff;
}

#graphFormsContainer .border-2.border-dashed:hover {
  border-color: #f97316;
  background-color: #fff7ed;
}

/* Full width table for pie charts in graphs */
.graph-card .flex.flex-col.md\:flex-row.gap-5 {
  display: flex;
  flex-direction: column;
  width: 100%;
}

@media (min-width: 768px) {
  .graph-card .flex.flex-col.md\:flex-row.gap-5 {
    flex-direction: row;
  }
}

.graph-card .w-full.md\:w-1\/2 {
  width: 100%;
}

@media (min-width: 768px) {
  .graph-card .w-full.md\:w-1\/2 {
    width: 50%;
  }
}

/* Larger chart containers for full width display */
.graph-card .h-80 {
  height: 400px !important; /* Increased height for bar charts */
}

.graph-card .h-96 {
  height: 500px !important; /* Increased height for other charts */
}

/* Better spacing for full width layout */
.grid.grid-cols-1.gap-6 {
  gap: 1.5rem;
}

/* Ensure charts take full width */
.graph-card .chart-container {
  width: 100% !important;
  max-width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}
</style>
<?php
// Function to display a graph card
function displayGraphCard($graph) {
  $title = $graph['title'];
  $graphType = $graph['type'];
  $data = $graph['data'];
  $id = $graph['id'];
  $created_at = $graph['created_at'];
  $groupTitle = isset($graph['group_title']) ? $graph['group_title'] : null;
  
  // Add a class for hover effects and consistent height
  echo '<div class="bg-white border border-gray-200 rounded-lg shadow-md p-5 hover:shadow-lg transition-shadow duration-200 graph-card flex flex-col">';
  
  // Show group badge if this is part of a group
  if ($groupTitle) {
    echo '<div class="mb-2">';
    echo '<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full font-medium">';
    echo '<i class="fas fa-layer-group mr-1"></i> ' . htmlspecialchars($groupTitle);
    echo '</span>';
    echo '</div>';
  }
  
  echo '<div class="flex justify-between items-start mb-4">';
  echo '<h5 class="font-semibold text-gray-800 text-lg">' . htmlspecialchars($title) . '</h5>';
  echo '<span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">' . ucfirst($graphType) . '</span>';
  echo '</div>';
  
  // Content area that will grow to fill available space
  echo '<div class="flex-grow">';
  
  // For pie charts, display table on left and chart on right
  if ($graphType === 'pie') {
    // Make sure we have the correct data structure
    $pieData = [];
    if (isset($data[0]['label']) && isset($data[0]['value'])) {
      $pieData = $data;
    } else {
      // Try to extract label and value from any structure
      foreach ($data as $item) {
        if (is_array($item) && isset($item['label']) && isset($item['value'])) {
          $pieData[] = $item;
        }
      }
    }
    
    // Keep original data order for table display
    $originalData = $pieData;
    
    // Use flex row layout for medium screens and above, column for small screens
    echo '<div class="flex flex-col md:flex-row gap-5 h-full">';
    // Table container (left on medium screens, top on small)
    echo '<div class="w-full md:w-1/2">';
    echo '<div class="h-full flex flex-col">';
    echo '<div class="overflow-hidden flex-grow mb-3">';
    
    $total = array_sum(array_column($originalData, 'value'));
    echo '<table class="w-full divide-y divide-gray-200 text-sm">';
    echo '<thead class="bg-gray-50">';
    echo '<tr>';
    echo '<th class="px-2 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>';
    echo '<th class="px-2 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>';
    echo '<th class="px-2 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>';
    echo '<th class="px-2 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';
    
    // Limit to 8 rows to prevent overflow
    $displayData = array_slice($originalData, 0, 8);
    foreach ($displayData as $item) {
      $percentage = round(($item['value'] / $total) * 100, 2);
      echo '<tr class="hover:bg-gray-50">';
      // Allow text to wrap and remove truncation
      echo '<td class="px-2 py-1 text-xs text-gray-900 text-center break-words max-w-[100px]" title="' . htmlspecialchars($item['label']) . '">' . htmlspecialchars($item['label']) . '</td>';
      echo '<td class="px-2 py-1 whitespace-nowrap text-xs text-gray-500 text-center">' . formatNumber($item['value'], $item['original_value'] ?? null, $item['format'] ?? null) . '</td>';
      echo '<td class="px-2 py-1 whitespace-nowrap text-xs text-gray-500 text-center">' . formatPercentage($percentage) . '</td>';
      echo '<td class="px-2 py-1 text-center">';
      echo '<div class="w-6 h-6 rounded-full mx-auto" style="background-color: ' . htmlspecialchars($item['color'] ?? '#FF6384') . '"></div>';
      echo '</td>';
      echo '</tr>';
    }
    
    // If there are more than 8 items, show a summary row
    if (count($originalData) > 8) {
      echo '<tr class="bg-gray-50 font-semibold">';
      echo '<td class="px-2 py-1 text-xs text-gray-900 text-center">Others</td>';
      $othersTotal = array_sum(array_column(array_slice($originalData, 8), 'value'));
      $othersPercentage = round(($othersTotal / $total) * 100, 2);
      echo '<td class="px-2 py-1 whitespace-nowrap text-xs text-gray-500 text-center">' . formatNumber($othersTotal) . '</td>';
      echo '<td class="px-2 py-1 whitespace-nowrap text-xs text-gray-500 text-center">' . formatPercentage($othersPercentage) . '</td>';
      echo '<td class="px-2 py-1 text-center">';
      echo '<div class="w-6 h-6 rounded-full mx-auto" style="background-color: #cccccc"></div>';
      echo '</td>';
      echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Chart container (right on medium screens, bottom on small)
    echo '<div class="w-full md:w-1/2 flex items-center justify-center">';
    echo '<div class="chart-container" style="height: 300px; width: 100%;">'; // Increased height
    // Use JSON_UNESCAPED_UNICODE to properly handle special characters
    echo '<canvas id="graph' . $id . '" data-type="pie" data-graph=\'' . json_encode($originalData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) . '\' style="width: 100%; height: 100%;"></canvas>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
  } else {
    // For bar charts, make sure we have the correct data structure
    $barData = [];
    if (isset($data[0]['category']) && isset($data[0]['series1'])) {
      $barData = $data;
    } else {
      // Try to extract category and series from any structure
      foreach ($data as $item) {
        if (is_array($item) && isset($item['category'])) {
          $barData[] = $item;
        }
      }
    }
    
    // Keep original data order for display
    $originalData = $barData;
    
    // Determine the number of series from the data
    $seriesCount = 2; // Default to 2
    if ($originalData && count($originalData) > 0) {
      // Count the number of series by checking the properties of the first data item
      $firstItem = $originalData[0];
      $count = 0;
      for ($i = 1; $i <= 5; $i++) {
        if (isset($firstItem["series{$i}"])) {
          $count++;
        }
      }
      $seriesCount = $count;
    }
    
    // Display the chart with increased height
    echo '<div class="h-96 w-full flex items-center justify-center">'; // Increased from h-80 to h-96
    echo '<div class="chart-container w-full h-full">';
    // Use JSON_UNESCAPED_UNICODE to properly handle special characters
    echo '<canvas id="graph' . $id . '" data-type="bar" data-graph=\'' . json_encode($originalData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) . '\' style="width: 100%; height: 100%;"></canvas>';
    echo '</div>';
    echo '</div>';
  }
  
  // Close the content area
  echo '</div>';
  
  // Consistent button placement at the bottom for all graphs
  echo '<div class="flex justify-between items-center mt-4 pt-3 border-t border-gray-100">';
  echo '<div class="text-xs text-gray-500">';
  echo 'Created: ' . date('M j, Y', strtotime($created_at));
  echo '</div>';
  
  echo '<div class="flex space-x-2">';
  echo '<button onclick="editGraph(' . $id . ')" class="px-3 py-2 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white text-xs rounded-lg transition duration-200 transform hover:scale-110">';
  echo '<i class="fas fa-edit mr-1"></i> Edit';
  echo '</button>';
  
  echo '<button onclick="showDeleteArchiveModal(' . $id . ')" class="px-3 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white text-xs rounded-lg transition duration-200 transform hover:scale-110">';
  echo '<i class="fas fa-trash mr-1"></i> Delete';
  echo '</button>';
  
  echo '<button onclick="showDeleteArchiveModal(' . $id . ')" class="px-3 py-2 border border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white text-xs rounded-lg transition duration-200 transform hover:scale-110">';
  echo '<i class="fas fa-archive mr-1"></i> Archive';
  echo '</button>';
  echo '</div>';
  echo '</div>';
  
  echo '</div>';
}

// Format number for display - preserve original format
function formatNumber($value, $originalValue = null, $format = null) {
  // If format is percentage, return with % sign
  if ($format === 'percentage') {
    if ($value == round($value)) {
      return round($value) . '%';
    }
    return number_format($value, 2) . '%';
  }
  
  // If original value is provided and contains %, return it as is
  if ($originalValue && is_string($originalValue) && strpos($originalValue, '%') !== false) {
    return $originalValue;
  }
  
  // If it's a whole number, return without decimals
  if ($value == round($value)) {
    return round($value);
  }
  
  // Otherwise, return with 2 decimal places
  return number_format($value, 2);
}

// Format percentage for display - add % sign and remove .00 for whole numbers
function formatPercentage($value) {
  if ($value == round($value)) {
    return round($value) . '%';
  }
  return number_format($value, 2) . '%';
}
?>