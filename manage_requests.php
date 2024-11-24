<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAdminLogin();

$pending_requests = $db->query("
    SELECT t.*, e.name as employee_name, e.department 
    FROM time_entries t 
    JOIN employees e ON t.employee_id = e.id 
    WHERE t.status = 'pending' 
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - TimeTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Pending Requests</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($pending_requests)): ?>
            <div class="alert alert-info">No pending requests found.</div>
        <?php else: ?>
            <?php foreach ($pending_requests as $request): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Request from <?php echo $request['employee_name']; ?></h5>
                            <span class="badge bg-warning">Pending</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Department:</strong> <?php echo $request['department']; ?></p>
                                <p><strong>Date:</strong> <?php echo date('Y-m-d', strtotime($request['login_time'])); ?></p>
                                <p><strong>Login Time:</strong> <?php echo date('h:i A', strtotime($request['login_time'])); ?></p>
                                <p><strong>Logout Time:</strong> <?php echo date('h:i A', strtotime($request['logout_time'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Remarks:</strong></p>
                                <p><?php echo $request['remarks']; ?></p>
                                <form action="process_request.php" method="POST" class="mt-3">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <div class="mb-3">
                                        <label>Admin Comments</label>
                                        <textarea name="comments" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button type="submit" name="action" value="approve" class="btn btn-success me-2">
                                        Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>