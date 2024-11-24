function confirmAction(message) {
    return confirm(message);
}

function toggleManualEntry() {
    const form = document.getElementById('manualEntryForm');
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

function validateTimeEntry() {
    const loginTime = document.getElementById('login_time').value;
    const logoutTime = document.getElementById('logout_time').value;
    
    if (!loginTime || !logoutTime) {
        alert('Both login and logout times are required');
        return false;
    }
    
    if (new Date(logoutTime) <= new Date(loginTime)) {
        alert('Logout time must be after login time');
        return false;
    }
    
    return true;

    // assets/js/script.js

// Existing functions (if any)

// New functions to add
function exportToExcel(tableId, filename = 'report') {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
    XLSX.writeFile(wb, `${filename}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

function generatePDF(elementId) {
    const element = document.getElementById(elementId);
    html2pdf()
        .from(element)
        .save(`report_${new Date().toISOString().slice(0,10)}.pdf`);
}

function validateDateRange(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (end <= start) {
        alert('End date must be after start date');
        return false;
    }
    return true;
}

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Additional utility functions
function formatDateTime(date) {
    return new Date(date).toLocaleString();
}

function formatTime(date) {
    return new Date(date).toLocaleTimeString();
}

function validateTimeEntry() {
    const loginTime = document.getElementById('login_time').value;
    const logoutTime = document.getElementById('logout_time').value;
    
    if (!loginTime || !logoutTime) {
        alert('Both login and logout times are required');
        return false;
    }
    
    if (!validateDateRange(loginTime, logoutTime)) {
        return false;
    }
    
    return true;
}

function toggleManualEntry() {
    const form = document.getElementById('manualEntryForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Error handling function
function handleAjaxError(error) {
    console.error('Ajax Error:', error);
    alert('An error occurred. Please try again later.');
}

}
