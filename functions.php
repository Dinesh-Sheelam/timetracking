<?php
require_once 'config.php';

// Existing functions
function checkAdminLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit();
    }
}

function checkEmployeeLogin() {
    if (!isset($_SESSION['employee_id'])) {
        header('Location: ' . SITE_URL . '/employee/index.php');
        exit();
    }
}

function getEmployeeTimeEntries($employeeId) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM time_entries WHERE employee_id = ? ORDER BY login_time DESC");
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// New functions to add
function calculateWorkHours($login_time, $logout_time) {
    $login = new DateTime($login_time);
    $logout = new DateTime($logout_time);
    $interval = $login->diff($logout);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    
    return sprintf("%d:%02d", $hours, $minutes);
}

function getMonthlyReport($month, $year, $department = null) {
    global $db;
    
    $query = "
        SELECT 
            e.name,
            e.department,
            COUNT(t.id) as total_entries,
            SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) as approved_entries,
            SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(t.logout_time, t.login_time)))) as avg_hours
        FROM 
            employees e
        LEFT JOIN 
            time_entries t ON e.id = t.employee_id
        WHERE 
            MONTH(t.login_time) = ? AND YEAR(t.login_time) = ?
    ";
    
    if ($department) {
        $query .= " AND e.department = ?";
    }
    
    $query .= " GROUP BY e.id";
    
    $stmt = $db->prepare($query);
    
    if ($department) {
        $stmt->bind_param("iis", $month, $year, $department);
    } else {
        $stmt->bind_param("ii", $month, $year);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function sendNotification($type, $message, $user_id) {
    global $db;
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, message) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $user_id, $type, $message);
    return $stmt->execute();
}

function checkLateEntry($login_time, $expected_time = '09:00:00') {
    $login = new DateTime($login_time);
    $expected = new DateTime($login->format('Y-m-d') . ' ' . $expected_time);
    return $login > $expected;
}

// Add notifications table if it doesn't exist
function createNotificationsTable() {
    global $db;
    $sql = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    return $db->query($sql);
}

// Additional helper functions
function formatDateTime($datetime) {
    return date('Y-m-d h:i A', strtotime($datetime));
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'approved':
            return 'bg-success';
        case 'pending':
            return 'bg-warning';
        case 'rejected':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Error logging function
function logError($error_message) {
    $log_file = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $error_message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Input validation functions
function validateTimeEntry($login_time, $logout_time) {
    if (empty($login_time) || empty($logout_time)) {
        return false;
    }
    
    $login = new DateTime($login_time);
    $logout = new DateTime($logout_time);
    
    return $logout > $login;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>