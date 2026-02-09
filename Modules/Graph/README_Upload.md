# Graph Upload Functionality

## Overview
The Graph module now supports uploading CSV files to automatically generate graphs. Users can upload their data files and the system will guide them through creating either pie charts or bar charts with customizable colors and labels.

## Features

### File Upload Support
- **CSV Files**: Fully supported with automatic delimiter detection
- **Excel Files**: Supported (.xlsx format) - .xls legacy format requires conversion to .xlsx

### Graph Types
- **Pie Charts**: Perfect for showing parts of a whole
- **Bar Charts**: Great for comparing different categories across multiple series

### User Experience
1. **Upload Step**: Drag and drop or click to select CSV files
2. **Graph Type Selection**: Choose between pie chart or bar chart
3. **Data Mapping & Editing**: 
   - Map CSV columns to graph data
   - Edit labels and values
   - Customize colors for each data point
   - Add or remove data points as needed

## File Format Requirements

### CSV Files
- Must have at least 2 columns
- Must have at least 2 rows of data
- First row should contain column headers
- Supported delimiters: comma (,), semicolon (;), tab, pipe (|)

### Excel Files
- Supported format: .xlsx (Excel 2007 and later)
- Legacy .xls format not supported (please save as .xlsx)
- Must have at least 2 columns
- Must have at least 2 rows of data
- First row should contain column headers
- Data should be in the first worksheet

### Sample Excel for Performance Data (like your example)
```
Program                          | CVSU Overall Performance | National Overall Performance
Architecture (January)          | 90%                      | 66.17%
Electronics Engineering (April) | 12.50%                   | 35.13%
Electronics Technician (April)  | 83.33%                   | 77.04%
Electrical Engineering (April)  | 100.00%                  | 68.25%
Civil Engineering (May)         | 50.00%                   | 43.92%
```

### Sample CSV for Pie Chart
```csv
Category,Value
Engineering,45
Information Technology,32
Business Administration,28
Education,15
Arts and Sciences,20
```

### Sample CSV for Bar Chart
```csv
Department,2022,2023,2024
Engineering,120,135,150
Information Technology,95,110,125
Business Administration,80,85,90
Education,60,65,70
Arts and Sciences,75,80,85
```

## How to Use

1. **Access the Graph Module**: Navigate to the Graph section in your dashboard
2. **Click Upload File**: Use the "Upload File" button next to "Add Graph"
3. **Select Your File**: 
   - Click the upload area or drag and drop your CSV or Excel (.xlsx) file
   - Maximum file size: 10MB
4. **Process the File**: Click "Process File" to read and validate your data
5. **Choose Graph Type**: Select either Pie Chart or Bar Chart
6. **Configure Your Graph**:
   - Enter a graph title (predefined options or custom)
   - Map CSV columns to graph data
   - Edit labels, values, and colors as needed
   - Add or remove data points
7. **Create Graph**: Click "Create Graph" to save your visualization

## Column Mapping

### For Pie Charts
- **Label Column**: Choose which column contains the category names
- **Value Column**: Choose which column contains the numeric values

### For Bar Charts
- **Category Column**: Choose which column contains the category names
- **Value Columns**: Select one or more columns for different data series
- **Series Configuration**: Customize series labels and colors

## Data Editing Features

- **Real-time Preview**: See your data as you map and edit it
- **Color Customization**: Pick custom colors for each data point or series
- **Add/Remove Data**: Add new data points or remove unwanted ones
- **Label Editing**: Modify labels and values directly in the interface

## Technical Details

### Files Added/Modified
- `Modules/Graph/UploadGraph.php`: Handles file upload and processing
- `Modules/Graph/Graph.php`: Updated with upload modal and JavaScript functionality
- Enhanced CSV reading with automatic delimiter detection
- Comprehensive error handling and validation

### Security Features
- File type validation
- File size limits (10MB maximum)
- Input sanitization
- Session-based authentication

## Future Enhancements

### Additional Features Planned
- Support for more chart types (line charts, scatter plots)
- Legacy Excel format (.xls) support
- Batch file processing
- Data transformation options
- Export functionality for processed data

## Troubleshooting

### Common Issues
1. **"No data found in file"**: Ensure your CSV/Excel has at least 2 columns and 2 rows
2. **"Legacy Excel format not supported"**: Save your .xls file as .xlsx format
3. **"Unsupported Excel file format"**: 
   - Make sure your file is saved as .xlsx (not .xls)
   - Check if the file is password protected (not supported)
   - Try opening and re-saving the file in Excel
   - Convert to CSV as an alternative
4. **"Unable to open Excel file"**: File may be corrupted or password protected
5. **"File too large"**: Maximum file size is 10MB
6. **"Unable to read CSV"**: Check that your file is properly formatted

### Excel File Troubleshooting
If you're getting "Unsupported Excel file format" error:

1. **Check File Format**: 
   - Open your file in Excel
   - Go to File → Save As
   - Choose "Excel Workbook (*.xlsx)" format
   - Save and try uploading again

2. **Remove Password Protection**:
   - If your file is password protected, remove the password
   - Go to File → Info → Protect Workbook → Encrypt with Password
   - Clear the password and save

3. **Convert to CSV** (Recommended alternative):
   - Open your Excel file
   - Go to File → Save As
   - Choose "CSV (Comma delimited) (*.csv)" format
   - Upload the CSV file instead

### File Format Tips
- **CSV**: Use standard delimiters (comma, semicolon, tab)
- **Excel**: Save as .xlsx format, avoid password protection
- Ensure first row contains column headers
- Avoid empty rows in the middle of your data
- Use consistent data types in each column

## Support
For issues or questions about the upload functionality, please check:
1. File format requirements
2. Browser console for JavaScript errors
3. Server logs for PHP errors