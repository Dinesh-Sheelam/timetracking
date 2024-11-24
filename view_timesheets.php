<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAdminLogin();

// Get filter parameters
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'custom';
$today = date('Y-m-d');

// Set date range based on filter type
switch ($filter_type) {
    case 'last_week':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        $end_date = $today;
        break;
    case 'last_2_weeks':
        $start_date = date('Y-m-d', strtotime('-2 weeks'));
        $end_date = $today;
        break;
    case 'current_month':
        $start_date = date('Y-m-01');
        $end_date = $today;
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        break;
    default:
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? min($_GET['end_date'], $today) : $today;
}

$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';

// Base query with date formatting
$query = "
    SELECT t.*, e.name as employee_name, e.department 
    FROM time_entries t
    JOIN employees e ON t.employee_id = e.id
    WHERE DATE(t.login_time) BETWEEN ? AND ?";

// Add employee filter if selected
if ($employee_id) {
    $query .= " AND t.employee_id = ?";
}

$query .= " ORDER BY t.login_time DESC";

// Get employees for filter dropdown
$employees = $db->query("SELECT id, name FROM employees ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Prepare and execute the query
$stmt = $db->prepare($query);

if ($employee_id) {
    $stmt->bind_param("ssi", $start_date, $end_date, $employee_id);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$timesheets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics
$total_hours = 0;
$entry_count = count($timesheets);

foreach ($timesheets as $entry) {
    if ($entry['logout_time']) {
        $login = new DateTime($entry['login_time']);
        $logout = new DateTime($entry['logout_time']);
        $diff = $logout->diff($login);
        $total_hours += ($diff->h + ($diff->days * 24));
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Timesheets - TimeTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">TimeTrack Admin</a>
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filter Timesheets</h5>
            </div>
            <div class="card-body">
                <div class="btn-group mb-3" role="group">
                    <a href="?filter_type=last_week" class="btn btn-outline-primary <?php echo $filter_type === 'last_week' ? 'active' : ''; ?>">Last Week</a>
                    <a href="?filter_type=last_2_weeks" class="btn btn-outline-primary <?php echo $filter_type === 'last_2_weeks' ? 'active' : ''; ?>">Last 2 Weeks</a>
                    <a href="?filter_type=current_month" class="btn btn-outline-primary <?php echo $filter_type === 'current_month' ? 'active' : ''; ?>">Current Month</a>
                    <a href="?filter_type=last_month" class="btn btn-outline-primary <?php echo $filter_type === 'last_month' ? 'active' : ''; ?>">Last Month</a>
                </div>

                <form method="GET" class="row g-3">
                    <input type="hidden" name="filter_type" value="custom">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo $start_date; ?>" 
                               max="<?php echo $today; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo $end_date; ?>" 
                               max="<?php echo $today; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employee</label>
                        <select name="employee_id" class="form-control">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    <?php echo $employee_id == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo $emp['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Entries</h5>
                        <h2 class="mb-0"><?php echo $entry_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Hours</h5>
                        <h2 class="mb-0"><?php echo $total_hours; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Average Hours/Entry</h5>
                        <h2 class="mb-0"><?php echo $entry_count > 0 ? round($total_hours / $entry_count, 1) : 0; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timesheets Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employee Timesheets</h5>
                <button onclick="exportToExcel()" class="btn btn-success btn-sm">
                    Export to Excel
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="timesheetTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th>Total Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($timesheets)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No entries found for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($timesheets as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['department']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($entry['login_time'])); ?></td>
                                        <td><?php echo date('h:i:s A', strtotime($entry['login_time'])); ?></td>
                                        <td>
                                            <?php 
                                            echo $entry['logout_time'] 
                                                ? date('h:i:s A', strtotime($entry['logout_time'])) 
                                                : '<span class="text-primary">Active</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($entry['logout_time']) {
                                                $login = new DateTime($entry['login_time']);
                                                $logout = new DateTime($entry['logout_time']);
                                                $diff = $logout->diff($login);
                                                echo $diff->format('%H:%I:%S');
                                            } else {
                                                echo '<span class="text-muted">In Progress</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="entry-type <?php echo $entry['entry_type'] == 'auto' ? 'bg-light' : 'bg-warning bg-opacity-25'; ?>">
                                                <?php echo ucfirst($entry['entry_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge bg-<?php 
                                                echo $entry['status'] == 'approved' ? 'success' : 
                                                    ($entry['status'] == 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                <?php echo ucfirst($entry['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    onclick="editTimesheet(<?php echo htmlspecialchars(json_encode($entry), ENT_QUOTES, 'UTF-8'); ?>)">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Time Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_timesheet.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="entry_id" id="edit_entry_id">
                    <input type="hidden" name="timezone" id="edit_timezone">
                    <div class="mb-3">
                        <label>Login Time</label>
                        <input type="datetime-local" name="login_time" id="edit_login_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Logout Time</label>
                        <input type="datetime-local" name="logout_time" id="edit_logout_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Admin Comments</label>
                        <textarea name="admin_comments" id="edit_comments" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<script>
function convertUTCToLocal(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return localDate.toISOString().slice(0, 16);  // Format for datetime-local input
}

function getCurrentTimezone() {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

function editTimesheet(entry) {
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    
    // Convert times to local timezone for editing
    const loginTime = convertUTCToLocal(entry.login_time);
    const logoutTime = entry.logout_time ? convertUTCToLocal(entry.logout_time) : '';
    
    // Set values in the form
    document.getElementById('edit_entry_id').value = entry.id;
    document.getElementById('edit_login_time').value = loginTime;
    document.getElementById('edit_logout_time').value = logoutTime;
    document.getElementById('edit_status').value = entry.status;
    document.getElementById('edit_comments').value = entry.remarks || '';
    
    // Add timezone information
    if (!document.getElementById('edit_timezone')) {
        const timezoneInput = document.createElement('input');
        timezoneInput.type = 'hidden';
        timezoneInput.name = 'timezone';
        timezoneInput.id = 'edit_timezone';
        document.querySelector('#editModal form').appendChild(timezoneInput);
    }
    document.getElementById('edit_timezone').value = getCurrentTimezone();
    
    modal.show();
}

function validateEditForm(e) {
    const form = e.target;
    const loginTime = new Date(form.login_time.value);
    const logoutTime = new Date(form.logout_time.value);
    
    if (logoutTime <= loginTime) {
        alert('Logout time must be after login time');
        e.preventDefault();
        return false;
    }
    return true;
}

// Add form validation
document.querySelector('#editModal form').addEventListener('submit', validateEditForm);

function exportToExcel() {
    const table = document.getElementById('timesheetTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Timesheets"});
    XLSX.writeFile(wb, `Timesheets_${new Date().toISOString().slice(0,10)}.xlsx`);
}
</script>
</body>
</html>