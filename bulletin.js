// Initialize PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js';

// Global variables for PDF data
let announcementData;
let memoData;
let gadData;
let studentDevData;

// Carousel and page rotation variables
let carouselIntervals = {};
let pageInterval;
let userInteracted = false;
let tabSwitchInterval;
let pageRotationActive = true;
let currentPageRotationTime = 60000; // Default to 1 minute
let page1TabSwitchTime = 60000; // 1 minute for each tab pair in page 1

function updateDateTime() {
  const now = new Date();
  const dateOptions = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  };
  const timeOptions = {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  };
  document.getElementById("date").textContent = now.toLocaleDateString(
    "en-PH",
    dateOptions
  );
  document.getElementById("time").textContent = now.toLocaleTimeString(
    "en-PH",
    timeOptions
  );
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Individual carousel functionality for About CvSU (mandates)
function prevMandatesCarousel() {
  const carousel = document.querySelector('.mandates-carousel');
  const items = carousel.querySelectorAll(".carousel-item");
  let activeIndex = Array.from(items).findIndex((item) =>
    item.classList.contains("active")
  );
  items[activeIndex].classList.remove("active");
  activeIndex = (activeIndex - 1 + items.length) % items.length;
  items[activeIndex].classList.add("active");
  
  // Reset the auto-rotation interval
  resetCarouselInterval('mandates-carousel');
}

function nextMandatesCarousel() {
  const carousel = document.querySelector('.mandates-carousel');
  const items = carousel.querySelectorAll(".carousel-item");
  let activeIndex = Array.from(items).findIndex((item) =>
    item.classList.contains("active")
  );
  items[activeIndex].classList.remove("active");
  activeIndex = (activeIndex + 1) % items.length;
  items[activeIndex].classList.add("active");
  
  // Reset the auto-rotation interval
  resetCarouselInterval('mandates-carousel');
}

// Synchronized carousel functionality for Announcement and GAD
function synchronizedAnnouncementGadChange(direction) {
  // Change announcement carousel
  const announcementViewer = document.getElementById('announcement-viewer');
  const announcementDesc = document.getElementById('announcement-description');
  const announcementFilename = document.getElementById('announcement-filename');
  const announcementPostedDate = document.getElementById('announcement-posted-date');
  
  // Change GAD carousel
  const gadViewer = document.getElementById('gad-viewer');
  const gadDesc = document.getElementById('gad-description');
  const gadFilename = document.getElementById('gad-filename');
  const gadPostedDate = document.getElementById('gad-posted-date');
  
  // Check if elements exist (they might be in iframes now)
  if (!announcementViewer || !gadViewer) {
    console.log('Announcement or GAD viewer not found - they may be in iframes');
    return;
  }
  
  // Get current indices
  let announcementIndex = parseInt(announcementViewer.dataset.currentIndex || '0');
  let gadIndex = parseInt(gadViewer.dataset.currentIndex || '0');
  
  // Calculate new indices based on direction
  if (direction === 'next') {
    announcementIndex = (announcementIndex + 1) % announcementData.length;
    gadIndex = (gadIndex + 1) % gadData.length;
  } else {
    announcementIndex = (announcementIndex - 1 + announcementData.length) % announcementData.length;
    gadIndex = (gadIndex - 1 + gadData.length) % gadData.length;
  }
  
  // Update the viewers
  updateCarouselItem('announcements', announcementIndex);
  updateCarouselItem('gad', gadIndex);
  
  // Store new indices
  announcementViewer.dataset.currentIndex = announcementIndex;
  gadViewer.dataset.currentIndex = gadIndex;
  
  // Reset the auto-rotation interval
  resetCarouselInterval('synchronized-announcement-gad');
}

// Function to update a specific carousel item
function updateCarouselItem(tabId, index) {
  let dataArray, viewerId, descId, filenameId, postedDateId;
  
  switch(tabId) {
    case 'announcements':
      dataArray = announcementData;
      viewerId = 'announcement-viewer';
      descId = 'announcement-description';
      filenameId = 'announcement-filename';
      postedDateId = 'announcement-posted-date';
      break;
    case 'memos':
      dataArray = memoData;
      viewerId = 'memo-viewer';
      descId = 'memo-description';
      filenameId = 'memo-filename';
      postedDateId = 'memo-posted-date';
      break;
    case 'gad':
      dataArray = gadData;
      viewerId = 'gad-viewer';
      descId = 'gad-description';
      filenameId = 'gad-filename';
      postedDateId = 'gad-posted-date';
      break;
    case 'student-dev':
      dataArray = studentDevData;
      viewerId = 'student-dev-viewer';
      descId = 'student-dev-description';
      filenameId = 'student-dev-filename';
      postedDateId = 'student-dev-posted-date';
      break;
    default:
      return;
  }
  
  const viewer = document.getElementById(viewerId);
  const desc = document.getElementById(descId);
  const filename = document.getElementById(filenameId);
  const postedDate = document.getElementById(postedDateId);
  
  if (!dataArray || dataArray.length === 0 || index >= dataArray.length) {
    return;
  }
  
  const pdf = dataArray[index];
  
  // Check if file exists
  if (!pdf.file_path || !fileExists(pdf.file_path)) {
    if (viewer) {
      viewer.innerHTML = `
        <div class="text-center p-4">
          <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
          <p class="text-red-500">File not found</p>
        </div>
      `;
    }
    return;
  }
  
  // Show loading indicator
  if (viewer) {
    viewer.innerHTML = `
      <div class="loading-indicator">
        <div class="spinner"></div>
        <p class="text-gray-500 text-sm">Loading preview...</p>
      </div>
      <div class="view-hint">Click to view full ${tabId.replace('-', ' ')}</div>
    `;
  }
  
  if (desc) desc.textContent = pdf.description;
  if (filename) filename.textContent = pdf.file_path.split('/').pop();
  if (postedDate) postedDate.textContent = "Posted on: " + pdf.posted_on;
  
  // Get file extension
  const fileExtension = pdf.file_path.split('.').pop().toLowerCase();
  
  if (fileExtension === 'pdf' && viewer) {
    pdfjsLib.getDocument(pdf.file_path).promise.then(pdfDoc => {
      return pdfDoc.getPage(1);
    }).then(page => {
      // Calculate scale to fit container
      const containerWidth = viewer.clientWidth;
      const containerHeight = viewer.clientHeight;
      const viewport = page.getViewport({
        scale: 1.0
      });
      // Calculate scale to fit container while maintaining aspect ratio
      const scale = Math.min(
        containerWidth / viewport.width,
        containerHeight / viewport.height
      ) * 1.2;
      const scaledViewport = page.getViewport({
        scale
      });
      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d');
      canvas.height = scaledViewport.height;
      canvas.width = scaledViewport.width;
      // Clear viewer and add canvas
      viewer.innerHTML = '';
      viewer.appendChild(canvas);
      // Add view hint back
      const hint = document.createElement('div');
      hint.className = 'view-hint';
      hint.textContent = `Click to view full ${tabId.replace('-', ' ')}`;
      viewer.appendChild(hint);
      return page.render({
        canvasContext: context,
        viewport: scaledViewport
      }).promise;
    }).catch(error => {
      console.error('Preview error:', error);
      if (viewer) {
        viewer.innerHTML = `
          <div class="text-center p-4">
            <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
            <p class="text-red-500">Failed to load preview</p>
          </div>
          <div class="view-hint">Click to view full ${tabId.replace('-', ' ')}</div>
        `;
      }
    });
  } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension) && viewer) {
    // Display image preview
    viewer.innerHTML = `
      <img src="${pdf.file_path}" alt="Preview" style="max-width: 100%; max-height: 100%; object-fit: contain;">
      <div class="view-hint">Click to view full ${tabId.replace('-', ' ')}</div>
    `;
  } else if (viewer) {
    // For other file types, show icon
    viewer.innerHTML = `
      <div class="text-center">
        <i class="fas fa-file text-gray-400 text-6xl mb-2"></i>
        <p class="text-gray-600">No preview available</p>
      </div>
      <div class="view-hint">Click to view full ${tabId.replace('-', ' ')}</div>
    `;
  }
  
  // Add click event to open modal with visual feedback
  if (viewer) {
    viewer.style.cursor = 'pointer';
    viewer.onclick = () => {
      openAnnouncementModal(tabId, index);
    };
    
    // Add hover effect for better UX
    viewer.onmouseenter = () => {
      viewer.style.transform = 'scale(1.02)';
      viewer.style.transition = 'transform 0.2s ease';
    };
    viewer.onmouseleave = () => {
      viewer.style.transform = 'scale(1)';
    };
  }
}

// Helper function to check if file exists
function fileExists(url) {
  const http = new XMLHttpRequest();
  http.open('HEAD', url, false);
  http.send();
  return http.status !== 404;
}

// Synchronized carousel functionality for Student Development only (Memo is now in iframe)
function synchronizedMemoStudentDevChange(direction) {
  // Memos are now in an iframe with their own carousel
  // Only change student development carousel
  const studentDevViewer = document.getElementById('student-dev-viewer');
  const studentDevDesc = document.getElementById('student-dev-description');
  const studentDevFilename = document.getElementById('student-dev-filename');
  const studentDevPostedDate = document.getElementById('student-dev-posted-date');
  
  // Get current index
  let studentDevIndex = parseInt(studentDevViewer.dataset.currentIndex || '0');
  
  // Calculate new index based on direction
  if (direction === 'next') {
    studentDevIndex = (studentDevIndex + 1) % studentDevData.length;
  } else {
    studentDevIndex = (studentDevIndex - 1 + studentDevData.length) % studentDevData.length;
  }
  
  // Update the viewer
  updateCarouselItem('student-dev', studentDevIndex);
  
  // Store new index
  studentDevViewer.dataset.currentIndex = studentDevIndex;
  
  // Reset the auto-rotation interval
  resetCarouselInterval('synchronized-memo-studentdev');
}

// Individual carousel functions (for other carousels)
function prevCarousel(carouselClass) {
  const carousel = document.querySelector(`.${carouselClass}`);
  const items = carousel.querySelectorAll(".carousel-item");
  let activeIndex = Array.from(items).findIndex((item) =>
    item.classList.contains("active")
  );
  items[activeIndex].classList.remove("active");
  activeIndex = (activeIndex - 1 + items.length) % items.length;
  items[activeIndex].classList.add("active");
  
  // Reset the auto-rotation interval
  resetCarouselInterval(carouselClass);
}

function nextCarousel(carouselClass) {
  const carousel = document.querySelector(`.${carouselClass}`);
  const items = carousel.querySelectorAll(".carousel-item");
  let activeIndex = Array.from(items).findIndex((item) =>
    item.classList.contains("active")
  );
  items[activeIndex].classList.remove("active");
  activeIndex = (activeIndex + 1) % items.length;
  items[activeIndex].classList.add("active");
  
  // Reset the auto-rotation interval
  resetCarouselInterval(carouselClass);
}

// Carousel rotation functions
function startCarouselRotation(carouselClass, interval = 10000) {
  // Clear any existing interval for this carousel
  if (carouselIntervals[carouselClass]) {
    clearInterval(carouselIntervals[carouselClass]);
  }
  
  // Set up the new interval
  carouselIntervals[carouselClass] = setInterval(() => {
    if (carouselClass === 'mandates-carousel') {
      nextMandatesCarousel();
    } else if (carouselClass === 'synchronized-announcement-gad') {
      synchronizedAnnouncementGadChange('next');
    } else if (carouselClass === 'synchronized-memo-studentdev') {
      synchronizedMemoStudentDevChange('next');
    } else {
      nextCarousel(carouselClass);
    }
  }, interval);
}

function resetCarouselInterval(carouselClass) {
  // Clear existing interval
  if (carouselIntervals[carouselClass]) {
    clearInterval(carouselIntervals[carouselClass]);
  }
  
  // Start a new interval
  startCarouselRotation(carouselClass);
}

function stopCarouselRotation(carouselClass) {
  if (carouselIntervals[carouselClass]) {
    clearInterval(carouselIntervals[carouselClass]);
    delete carouselIntervals[carouselClass];
  }
}

// Tab switching function
function switchToTabPair(tabPair) {
  // Stop all carousel rotations
  Object.keys(carouselIntervals).forEach(key => {
    stopCarouselRotation(key);
  });
  
  // Clear any existing tab switch interval
  if (tabSwitchInterval) {
    clearInterval(tabSwitchInterval);
  }
  
  if (tabPair === 'announcement-gad') {
    // Activate announcement and GAD tabs
    document.querySelector('[data-tab="announcements"]').click();
    document.querySelector('[data-tab="gad"]').click();
    
    // Note: Announcements and GAD now use iframes with their own auto-rotation
    
    // Set timer to switch to memo and student dev after 1 minute
    tabSwitchInterval = setTimeout(() => {
      switchToTabPair('memo-studentdev');
    }, page1TabSwitchTime);
  } else if (tabPair === 'memo-studentdev') {
    // Activate memo and student dev tabs
    document.querySelector('[data-tab="memos"]').click();
    document.querySelector('[data-tab="student-dev"]').click();
    
    // Note: Memos now use iframe with its own auto-rotation
    // Student dev still uses the old carousel system
    startCarouselRotation('synchronized-memo-studentdev', 10000);
    
    // Don't set a timer to switch back - let the page rotation handle this
  }
}

// Page rotation functions
function startPageRotation() {
  // Clear any existing page interval
  if (pageInterval) {
    clearInterval(pageInterval);
  }
  
  // Set up the new interval with different timing based on current page
  pageInterval = setInterval(() => {
    if (pageRotationActive) {
      // Simulate clicking the toggle page button
      togglePageBtn.click();
    }
  }, currentPageRotationTime);
}

function pausePageRotation() {
  pageRotationActive = false;
}

function resumePageRotation() {
  // Resume page rotation after a shorter delay (10 seconds)
  setTimeout(() => {
    pageRotationActive = true;
  }, 10000);
}

// Tab functionality
function setupTabs() {
  // Get all tab containers
  const tabContainers = document.querySelectorAll('.tab-container');
  
  // Process each container separately
  tabContainers.forEach(container => {
    // Get buttons and panes within this container only
    const tabButtons = container.querySelectorAll('.tab-btn');
    const tabPanes = container.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
      button.addEventListener('click', () => {
        // Remove active class from all buttons and panes within this container only
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabPanes.forEach(pane => pane.classList.remove('active'));
        
        // Add active class to clicked button
        button.classList.add('active');
        
        // Show corresponding tab pane
        const tabId = button.getAttribute('data-tab');
        const tabPane = container.querySelector(`#${tabId}-tab`);
        if (tabPane) {
          tabPane.classList.add('active');
          
          // Initialize carousel for active tab
          setTimeout(() => {
            initializeTabCarousel(tabId);
          }, 100);
        }
        
        // Pause page rotation temporarily
        pausePageRotation();
        resumePageRotation();
      });
    });
  });
}

// Page toggle functionality
const togglePageBtn = document.getElementById("togglePageBtn");
const prevPageBtn = document.getElementById("prevPageBtn");
let currentPage = 1;

// Update page navigation buttons
function updatePageNavigation() {
  // Clear any existing tab switch interval
  if (tabSwitchInterval) {
    clearInterval(tabSwitchInterval);
  }
  
  // Stop all carousel rotations
  Object.keys(carouselIntervals).forEach(key => {
    stopCarouselRotation(key);
  });
  
  if (currentPage === 1) {
    prevPageBtn.disabled = true;
    togglePageBtn.innerHTML = `<i class="fas fa-arrow-right mr-1 sm:mr-2"></i> <span class="text-sm sm:text-base">Go to Page 2</span>`;
    
    // Set page rotation time to 2 minutes for page 1
    currentPageRotationTime = 120000;
    
    // Initialize with announcement and GAD tabs
    switchToTabPair('announcement-gad');
    
    // Set timer to switch to memo and student dev after 1 minute
    tabSwitchInterval = setTimeout(() => {
      switchToTabPair('memo-studentdev');
    }, page1TabSwitchTime);
  } else if (currentPage === 2) {
    prevPageBtn.disabled = false;
    togglePageBtn.innerHTML = `<i class="fas fa-arrow-right mr-1 sm:mr-2"></i> <span class="text-sm sm:text-base">Go to Page 3</span>`;
    
    // Set page rotation time to 1 minute for page 2
    currentPageRotationTime = 60000;
    
    // Clear any existing tab switch interval since page 2 doesn't have tab switching
    if (tabSwitchInterval) {
      clearInterval(tabSwitchInterval);
    }
  } else if (currentPage === 3) {
    prevPageBtn.disabled = false;
    togglePageBtn.innerHTML = `<i class="fas fa-arrow-right mr-1 sm:mr-2"></i> <span class="text-sm sm:text-base">Go back to Page 1</span>`;
    
    // Set page rotation time to 1 minute for page 3
    currentPageRotationTime = 60000;
    
    // Clear any existing tab switch interval since page 3 doesn't have tab switching
    if (tabSwitchInterval) {
      clearInterval(tabSwitchInterval);
    }
  }
  
  // Restart page rotation with the new timing
  startPageRotation();
}

togglePageBtn.addEventListener("click", () => {
  // Hide current page
  document.getElementById(`page${currentPage}`).classList.remove("active");
  
  // Navigate to next page
  if (currentPage < 3) {
    currentPage++;
  } else {
    currentPage = 1;
  }
  
  // Show new page
  document.getElementById(`page${currentPage}`).classList.add("active");
  updatePageNavigation();
  
  // Pause page rotation temporarily
  pausePageRotation();
  resumePageRotation();
});

prevPageBtn.addEventListener("click", () => {
  // Hide current page
  document.getElementById(`page${currentPage}`).classList.remove("active");
  
  // Navigate to previous page
  if (currentPage > 1) {
    currentPage--;
  }
  
  // Show new page
  document.getElementById(`page${currentPage}`).classList.add("active");
  updatePageNavigation();
  
  // Pause page rotation temporarily
  pausePageRotation();
  resumePageRotation();
});

// Refresh button
document.getElementById('refreshBtn').addEventListener('click', function() {
  const loadingOverlay = document.getElementById('loadingOverlay');
  loadingOverlay.classList.remove('hidden'); // show overlay
  setTimeout(() => {
    location.reload();
  }, 1500); // 1.5s delay before reload
});

// Announcement Modal Functions
let currentPdfDoc = null;
let currentPageNum = 1;
let totalPages = 0;
let currentModalType = 'announcement';
let currentModalIndex = 0;

function openAnnouncementModal(type, index) {
  console.log("openAnnouncementModal called with type:", type, "index:", index);
  
  let dataArray;
  switch(type) {
    case 'announcements':
      dataArray = announcementData;
      break;
    case 'memos':
      dataArray = memoData;
      break;
    case 'gad':
      dataArray = gadData;
      break;
    case 'student-dev':
      dataArray = studentDevData;
      break;
    default:
      console.error("Invalid modal type:", type);
      return;
  }
  
  if (!dataArray || dataArray.length === 0) {
    console.error(`No ${type} data available`);
    return;
  }
  
  if (index >= dataArray.length) {
    console.error("Index out of bounds:", index, "data length:", dataArray.length);
    return;
  }
  
  const pdf = dataArray[index];
  console.log("Opening modal for:", pdf);
  
  // Set modal title based on type
  const typeTitles = {
    'announcements': 'Announcement',
    'memos': 'Memo',
    'gad': 'GAD Announcement',
    'student-dev': 'Student Development'
  };
  
  const typeIcons = {
    'announcements': 'bullhorn',
    'memos': 'file-alt',
    'gad': 'users',
    'student-dev': 'graduation-cap'
  };
  
  document.getElementById('modalTitle').innerHTML = `<i class="fas fa-${typeIcons[type]} mr-2"></i> ${typeTitles[type]}: ${pdf.description}`;
  document.getElementById('modalMeta').textContent = `Posted on: ${pdf.posted_on} | File: ${pdf.file_path.split('/').pop()}`;
  const modal = document.getElementById('announcementModal');
  const container = document.getElementById('pdfContainer');
  
  // Reset container
  container.innerHTML = `
    <div class="loading-spinner"></div>
    <p class="text-center text-gray-600">Loading ${typeTitles[type]}...</p>
  `;
  
  // Reset page navigation
  currentPageNum = 1;
  currentModalType = type;
  currentModalIndex = index;
  document.getElementById('prevPageBtn').disabled = true;
  document.getElementById('nextPageBtn').disabled = true;
  document.getElementById('pageIndicator').textContent = 'Page 1 of 1';
  
  modal.style.display = 'block';
  // Add smooth fade-in effect
  setTimeout(() => {
    modal.style.opacity = '1';
  }, 10);

  // Get file extension
  const fileExtension = pdf.file_path.split('.').pop().toLowerCase();
  console.log("File extension:", fileExtension);
  
  if (fileExtension === 'pdf') {
    // Load PDF
    pdfjsLib.getDocument(pdf.file_path).promise.then(pdfDoc => {
      console.log("PDF document loaded successfully");
      currentPdfDoc = pdfDoc;
      totalPages = pdfDoc.numPages;
      
      // Update page indicator
      if (totalPages === 1) {
        document.getElementById('pageIndicator').textContent = `Page 1 of 1`;
      } else {
        const endPage = Math.min(2, totalPages);
        document.getElementById('pageIndicator').textContent = `Pages 1-${endPage} of ${totalPages}`;
      }
      
      // Enable/disable navigation buttons
      document.getElementById('prevPageBtn').disabled = true;
      document.getElementById('nextPageBtn').disabled = totalPages <= 2;
      
      // Render first page(s)
      renderPage(1);
    }).catch(error => {
      console.error('Error loading PDF:', error);
      container.innerHTML = `
        <div class="text-center py-8">
          <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
          <p class="text-lg text-gray-700 mb-2">Failed to load ${typeTitles[type]}</p>
          <p class="text-gray-600 mb-4">${error.message}</p>
          <button onclick="window.open('${pdf.file_path}', '_blank')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-external-link-alt mr-2"></i> Open in New Tab
          </button>
        </div>
      `;
    });
  } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
    // Display image
    container.innerHTML = `
      <div class="image-container" style="max-width: 100%; max-height: 80vh; overflow: auto;">
        <img src="${pdf.file_path}" alt="Full view" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">
      </div>
    `;
  } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
    // Use Microsoft Office Online viewer
    const fileUrl = encodeURIComponent(pdf.file_path);
    container.innerHTML = `
      <div class="office-viewer">
        <iframe 
          src="https://view.officeapps.live.com/op/view.aspx?src=${fileUrl}" 
          style="width: 100%; height: 100%; border: none;"
          frameborder="0">
        </iframe>
      </div>
      <div class="text-center mt-4">
        <a href="${pdf.file_path}" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg inline-block">
          <i class="fas fa-download mr-2"></i> Download File
        </a>
      </div>
    `;
  } else {
    // For other file types
    container.innerHTML = `
      <div class="text-center p-8">
        <i class="fas fa-file text-gray-400 text-6xl mb-4"></i>
        <p class="text-lg text-gray-700 mb-2">Preview not available</p>
        <p class="text-gray-600 mb-4">This file type cannot be previewed in browser.</p>
        <p class="text-gray-600 mb-4">File: ${pdf.file_path.split('/').pop()}</p>
        <a href="${pdf.file_path}" download class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg">
          <i class="fas fa-download mr-2"></i> Download File
        </a>
      </div>
    `;
  }
}

function renderPage(pageNum) {
  if (!currentPdfDoc) return;
  const container = document.getElementById('pdfContainer');
  const modalBody = document.querySelector('.modal-body');
  
  // Show loading indicator
  container.innerHTML = `
    <div class="loading-spinner"></div>
    <p class="text-center text-gray-600">Loading pages...</p>
  `;
  
  // Check if this is the last page and it's odd
  const isLastPageOdd = (pageNum === totalPages && totalPages % 2 === 1);
  
  // Create container for pages
  const pagesContainer = document.createElement('div');
  pagesContainer.style.display = 'flex';
  pagesContainer.style.gap = '15px';
  pagesContainer.style.justifyContent = 'center';
  pagesContainer.style.alignItems = 'center';
  pagesContainer.style.width = '100%';
  pagesContainer.style.height = '100%';
  
  // Get dimensions of modal body
  const modalRect = modalBody.getBoundingClientRect();
  const availableWidth = isLastPageOdd ? (modalRect.width - 40) : (modalRect.width - 55) / 2; // Divide by 2 for two pages, account for gap
  const availableHeight = modalRect.height - 40;
  
  // Render first page
  currentPdfDoc.getPage(pageNum).then(page => {
    const viewport = page.getViewport({ scale: 1.5 });
    const widthScale = availableWidth / viewport.width;
    const heightScale = availableHeight / viewport.height;
    let scale = Math.min(widthScale, heightScale);
    scale = Math.max(scale, 0.8);
    
    const scaledViewport = page.getViewport({ scale });
    const canvas1 = document.createElement('canvas');
    const context1 = canvas1.getContext('2d');
    canvas1.height = scaledViewport.height;
    canvas1.width = scaledViewport.width;
    canvas1.className = 'pdf-page';
    canvas1.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
    canvas1.style.borderRadius = '8px';
    
    pagesContainer.appendChild(canvas1);
    
    // Render first page
    page.render({
      canvasContext: context1,
      viewport: scaledViewport
    }).promise.then(() => {
      // Render second page only if not the last odd page
      if (!isLastPageOdd && pageNum + 1 <= totalPages) {
        return currentPdfDoc.getPage(pageNum + 1);
      }
      return null;
    }).then(page2 => {
      if (page2) {
        const viewport2 = page2.getViewport({ scale: 1.5 });
        const widthScale2 = availableWidth / viewport2.width;
        const heightScale2 = availableHeight / viewport2.height;
        let scale2 = Math.min(widthScale2, heightScale2);
        scale2 = Math.max(scale2, 0.8);
        
        const scaledViewport2 = page2.getViewport({ scale: scale2 });
        const canvas2 = document.createElement('canvas');
        const context2 = canvas2.getContext('2d');
        canvas2.height = scaledViewport2.height;
        canvas2.width = scaledViewport2.width;
        canvas2.className = 'pdf-page';
        canvas2.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
        canvas2.style.borderRadius = '8px';
        
        pagesContainer.appendChild(canvas2);
        
        return page2.render({
          canvasContext: context2,
          viewport: scaledViewport2
        }).promise;
      }
    }).then(() => {
      // Clear container and add pages
      container.innerHTML = '';
      container.appendChild(pagesContainer);
    }).catch(error => {
      console.error('Error rendering pages:', error);
      container.innerHTML = `
        <div class="text-center py-8">
          <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
          <p class="text-lg text-gray-700 mb-2">Failed to load pages</p>
          <p class="text-gray-600">${error.message}</p>
        </div>
      `;
    });
  }).catch(error => {
    console.error('Error rendering page:', error);
    container.innerHTML = `
      <div class="text-center py-8">
        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
        <p class="text-lg text-gray-700 mb-2">Failed to load page ${pageNum}</p>
        <p class="text-gray-600">${error.message}</p>
      </div>
    `;
  });
}

function goToPrevPage() {
  if (currentPageNum > 1) {
    // If we're on the last odd page, go back by 1, otherwise by 2
    if (currentPageNum === totalPages && totalPages % 2 === 1) {
      currentPageNum -= 1;
    } else {
      currentPageNum = Math.max(1, currentPageNum - 2);
    }
    
    renderPage(currentPageNum);
    updatePageIndicator();
  }
}

function goToNextPage() {
  if (currentPageNum < totalPages) {
    // Move forward by 2 pages, but check if we're approaching the last odd page
    const nextPage = currentPageNum + 2;
    if (totalPages % 2 === 1 && nextPage >= totalPages) {
      // Jump directly to the last page
      currentPageNum = totalPages;
    } else {
      currentPageNum = Math.min(totalPages, nextPage);
    }
    
    renderPage(currentPageNum);
    updatePageIndicator();
  }
}

function updatePageIndicator() {
  const isLastPageOdd = (currentPageNum === totalPages && totalPages % 2 === 1);
  
  if (isLastPageOdd) {
    document.getElementById('pageIndicator').textContent = `Page ${currentPageNum} of ${totalPages}`;
  } else {
    const endPage = Math.min(currentPageNum + 1, totalPages);
    document.getElementById('pageIndicator').textContent = `Pages ${currentPageNum}-${endPage} of ${totalPages}`;
  }
  
  // Update navigation buttons
  document.getElementById('prevPageBtn').disabled = currentPageNum === 1;
  document.getElementById('nextPageBtn').disabled = currentPageNum >= totalPages;
}

function closeAnnouncementModal() {
  const modal = document.getElementById('announcementModal');
  // Add fade-out effect
  modal.style.opacity = '0';
  setTimeout(() => {
    modal.style.display = 'none';
    modal.style.opacity = '1'; // Reset for next open
  }, 300);
  
  currentPdfDoc = null;
  currentPageNum = 1;
  totalPages = 0;
  currentModalType = 'announcement';
  currentModalIndex = 0;
}

// Add event listeners for page navigation
document.getElementById('prevPageBtn').addEventListener('click', goToPrevPage);
document.getElementById('nextPageBtn').addEventListener('click', goToNextPage);

// Close modal when clicking outside content
window.onclick = function(event) {
  const modal = document.getElementById('announcementModal');
  if (event.target === modal) {
    closeAnnouncementModal();
  }
}

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAnnouncementModal();
  }
});

// Initialize carousel for a specific tab
function initializeTabCarousel(tabId) {
  console.log("Initializing carousel for tab:", tabId);
  
  // Check if the tab is actually visible before initializing
  const tabPane = document.getElementById(`${tabId}-tab`);
  if (!tabPane || !tabPane.classList.contains('active')) {
    return;
  }
  
  let dataArray, viewerId, descId, filenameId, postedDateId, prevBtnId, nextBtnId;
  
  switch(tabId) {
    case 'announcements':
      dataArray = announcementData;
      viewerId = 'announcement-viewer';
      descId = 'announcement-description';
      filenameId = 'announcement-filename';
      postedDateId = 'announcement-posted-date';
      prevBtnId = 'announcement-prev-btn';
      nextBtnId = 'announcement-next-btn';
      break;
    case 'memos':
      dataArray = memoData;
      viewerId = 'memo-viewer';
      descId = 'memo-description';
      filenameId = 'memo-filename';
      postedDateId = 'memo-posted-date';
      prevBtnId = 'memo-prev-btn';
      nextBtnId = 'memo-next-btn';
      break;
    case 'gad':
      dataArray = gadData;
      viewerId = 'gad-viewer';
      descId = 'gad-description';
      filenameId = 'gad-filename';
      postedDateId = 'gad-posted-date';
      prevBtnId = 'gad-prev-btn';
      nextBtnId = 'gad-next-btn';
      break;
    case 'student-dev':
      dataArray = studentDevData;
      viewerId = 'student-dev-viewer';
      descId = 'student-dev-description';
      filenameId = 'student-dev-filename';
      postedDateId = 'student-dev-posted-date';
      prevBtnId = 'student-dev-prev-btn';
      nextBtnId = 'student-dev-next-btn';
      break;
    default:
      return;
  }
  
  const viewer = document.getElementById(viewerId);
  const prevBtn = document.getElementById(prevBtnId);
  const nextBtn = document.getElementById(nextBtnId);
  
  if (!dataArray || dataArray.length === 0) {
    console.log(`No data for ${tabId}`);
    return;
  }
  
  // Initialize with first item
  updateCarouselItem(tabId, 0);
  
  // Store initial index
  if (viewer) {
    viewer.dataset.currentIndex = '0';
  }
  
  // Set up navigation buttons
  if (prevBtn) {
    prevBtn.onclick = () => {
      const currentIndex = parseInt(viewer.dataset.currentIndex || '0');
      const newIndex = (currentIndex - 1 + dataArray.length) % dataArray.length;
      updateCarouselItem(tabId, newIndex);
      viewer.dataset.currentIndex = newIndex;
      
      // Pause page rotation temporarily
      pausePageRotation();
      resumePageRotation();
    };
  }
  
  if (nextBtn) {
    nextBtn.onclick = () => {
      const currentIndex = parseInt(viewer.dataset.currentIndex || '0');
      const newIndex = (currentIndex + 1) % dataArray.length;
      updateCarouselItem(tabId, newIndex);
      viewer.dataset.currentIndex = newIndex;
      
      // Pause page rotation temporarily
      pausePageRotation();
      resumePageRotation();
    };
  }
}

// Function to initialize the bulletin with data from PHP
function initializeBulletin(announcements, memos, gads, studentDevs) {
  // Set the global data variables
  announcementData = announcements;
  memoData = memos;
  gadData = gads;
  studentDevData = studentDevs;
  
  // Individual carousel controls for About CvSU (mandates) only
  const mandatesPrevBtn = document.querySelector('.mandates-prev-btn');
  const mandatesNextBtn = document.querySelector('.mandates-next-btn');
  
  if (mandatesPrevBtn) {
    mandatesPrevBtn.addEventListener('click', function() {
      prevMandatesCarousel();
      
      // Pause page rotation temporarily
      pausePageRotation();
      resumePageRotation();
    });
  }
  
  if (mandatesNextBtn) {
    mandatesNextBtn.addEventListener('click', function() {
      nextMandatesCarousel();
      
      // Pause page rotation temporarily
      pausePageRotation();
      resumePageRotation();
    });
  }
  
  // Setup tabs
  setupTabs();
  
  // Initialize page navigation
  updatePageNavigation();
  
  // Start automatic rotation for mandates carousel only
  startCarouselRotation('mandates-carousel', 10000); // 10 seconds
  
  // Add event listeners to pause rotation on hover for mandates carousel
  const mandatesCarousel = document.querySelector('.mandates-carousel');
  if (mandatesCarousel) {
    mandatesCarousel.addEventListener('mouseenter', () => {
      stopCarouselRotation('mandates-carousel');
    });
    
    mandatesCarousel.addEventListener('mouseleave', () => {
      startCarouselRotation('mandates-carousel', 10000);
    });
  }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // The data will be passed from PHP via a separate script tag
  // See the modified HTML file below
});