<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAdminLogin();

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$department = isset($_GET['department']) ? $_GET['department'] : '';

// Get departments for filter
$departments = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL")->fetch_all(MYSQLI_ASSOC);

// Build query based on filters
$query = "
    SELECT e.name, e.department, 
           COUNT(t.id) as total_entries,
           SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) as approved_entries,
           SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(t.logout_time, t.login_time)))) as avg_hours
    FROM employees e
    LEFT JOIN time_entries t ON e.id = t.employee_id 
    WHERE DATE_FORMAT(t.login_time, '%Y-%m') = ?
";

if ($department) {
    $query .= " AND e.department = ?";
}

$query .= " GROUP BY e.id";
$stmt = $db->prepare($query);

if ($department) {
    $stmt->bind_param("ss", $month, $department);
} else {
    $stmt->bind_param("s", $month);
}

$stmt->execute();
$report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Reports - TimeTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Time Reports</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label>Month</label>
                        <input type="month" name="month" class="form-control" value="<?php echo $month; ?>">
                    </div>
                    <div class="col-md-4">
                        <label>Department</label>
                        <select name="department" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department']; ?>" 
                                    <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo $dept['department']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Time Report</h5>
                <button onclick="exportToExcel()" class="btn btn-success btn-sm">Export to Excel</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Total Entries</th>
                                <th>Approved Entries</th>
                                <th>Average Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['department']; ?></td>
                                <td><?php echo $row['total_entries']; ?></td>
                                <td><?php echo $row['approved_entries']; ?></td>
                                <td><?php echo $row['avg_hours']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
    function exportToExcel() {
        const table = document.getElementById('reportTable');
        const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
        XLSX.writeFile(wb, `TimeReport_${new Date().toISOString().slice(0,10)}.xlsx`);
    }
    </script>
</body>
</html>