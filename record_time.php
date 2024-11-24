<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: index.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$action = $_POST['action'] ?? '';
$timezone = $_POST['timezone'] ?? 'America/Chicago';

// Create logs directory if it doesn't exist
$log_dir = '../logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

function writeLog($message) {
    global $log_dir;
    $log = date('Y-m-d H:i:s') . ": " . $message . "\n";
    file_put_contents($log_dir . '/time_log.txt', $log, FILE_APPEND);
}

// Function to get current time in local timezone
function getCurrentLocalTime($timezone) {
    try {
        $date = new DateTime('now', new DateTimeZone($timezone));
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        writeLog("Error getting current time: " . $e->getMessage());
        return date('Y-m-d H:i:s'); // Fallback to server time
    }
}

writeLog("Action: $action, Employee: $employee_id, Timezone: $timezone");

try {
    switch ($action) {
        case 'login':
            // Check for active session
            $check_query = "SELECT COUNT(*) as active_count FROM time_entries 
                          WHERE employee_id = ? AND logout_time IS NULL";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bind_param("i", $employee_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $active_count = $result->fetch_assoc()['active_count'];
            
            writeLog("Active sessions found: " . $active_count);

            if ($active_count == 0) {
                $current_time = getCurrentLocalTime($timezone);
                writeLog("Current time for login: " . $current_time);

                $insert_query = "INSERT INTO time_entries 
                              (employee_id, login_time, entry_type, status, timezone) 
                              VALUES (?, ?, 'auto', 'approved', ?)";
                $stmt = $db->prepare($insert_query);
                $stmt->bind_param("iss", $employee_id, $current_time, $timezone);
                
                if ($stmt->execute()) {
                    writeLog("Login entry created successfully with ID: " . $stmt->insert_id);
                } else {
                    writeLog("Error creating login entry: " . $stmt->error);
                }
            }
            break;

        case 'logout':
            $current_time = getCurrentLocalTime($timezone);
            writeLog("Current time for logout: " . $current_time);

            $update_query = "UPDATE time_entries 
                          SET logout_time = ?,
                              total_time = TIMEDIFF(?, login_time),
                              timezone = ?
                          WHERE employee_id = ? 
                          AND logout_time IS NULL";
            
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("sssi", $current_time, $current_time, $timezone, $employee_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    writeLog("Logout successful. Rows affected: " . $stmt->affected_rows);
                } else {
                    writeLog("No active session found to logout");
                }
            } else {
                writeLog("Error updating logout time: " . $stmt->error);
            }
            break;

        case 'manual_entry':
            $entry_date = $_POST['entry_date'];
            $login_dt = new DateTime($entry_date . ' ' . $_POST['login_time'], new DateTimeZone($timezone));
            $logout_dt = new DateTime($entry_date . ' ' . $_POST['logout_time'], new DateTimeZone($timezone));
            
            $login_time = $login_dt->format('Y-m-d H:i:s');
            $logout_time = $logout_dt->format('Y-m-d H:i:s');
            $remarks = $_POST['remarks'];
            
            writeLog("Manual entry - Login: $login_time, Logout: $logout_time");
            
            $insert_query = "INSERT INTO time_entries 
                          (employee_id, login_time, logout_time, total_time, entry_type, status, remarks, timezone) 
                          VALUES (?, ?, ?, TIMEDIFF(?, ?), 'manual', 'pending', ?, ?)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->bind_param("issssss", 
                $employee_id,
                $login_time,
                $logout_time,
                $logout_time,
                $login_time,
                $remarks,
                $timezone
            );
            
            if ($stmt->execute()) {
                writeLog("Manual entry created successfully with ID: " . $stmt->insert_id);
            } else {
                writeLog("Error creating manual entry: " . $stmt->error);
            }
            break;

        default:
            writeLog("Invalid action received: " . $action);
            break;
    }
} catch (Exception $e) {
    writeLog("Critical error: " . $e->getMessage());
}

writeLog("Processing complete - redirecting to dashboard");
header('Location: dashboard.php');
exit();
?>