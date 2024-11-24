<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
if (!isset($_SESSION['employee_id'])) {
    header('Location: index.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];

// Check for active session
$active_session = $db->query("
    SELECT * FROM time_entries 
    WHERE employee_id = $employee_id 
    AND logout_time IS NULL
    ORDER BY login_time DESC LIMIT 1
")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employee Dashboard - TimeTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .timer-display {
            font-size: 3em;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            font-family: monospace;
            color: #28a745;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
        }
        .entry-type {
            font-size: 0.85em;
            padding: 2px 8px;
            border-radius: 10px;
            background-color: #e9ecef;
        }
        .navbar {
            background-color: #0d6efd !important;
            padding: 1rem;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .clock-button {
            min-width: 120px;
            padding: 10px 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">TimeTrack</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo $_SESSION['employee_name']; ?></span>
                <a href="logout.php" class="btn btn-light btn-sm">Logout System</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-center">Time Tracker</h5>
                <p class="text-center text-muted mb-0" id="currentTimezone"></p>
            </div>
            <div class="card-body text-center">
                <?php if ($active_session): ?>
                    <p class="text-muted mb-2">Current Session Started at</p>
                    <p class="h4 mb-3" id="startTime" data-time="<?php echo $active_session['login_time']; ?>">
                        <?php echo date('h:i:s A', strtotime($active_session['login_time'])); ?>
                    </p>
                    <div class="timer-display" id="timer">00:00:00</div>
                    <form action="record_time.php" method="POST">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="timezone" id="logoutTimezone">
                        <button type="submit" class="btn btn-danger btn-lg clock-button">Clock Out</button>
                    </form>
                <?php else: ?>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <p class="text-muted mb-4">You haven't clocked in yet</p>
                            <form action="record_time.php" method="POST" id="clockInForm" class="mb-3">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="timezone" id="loginTimezone">
                                <button type="submit" class="btn btn-success btn-lg clock-button mb-3">Clock In</button>
                            </form>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualEntryModal">
                                Add Manual Entry
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<!-- Time Entries Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Time Entry History</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th>Total Time</th>
                        <th>Entry Type</th>
                        <th>Status</th>
                        <th>Timezone</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Updated query to get the latest entries first
                    $entries_query = $db->prepare("
                        SELECT * FROM time_entries 
                        WHERE employee_id = ? 
                        ORDER BY login_time DESC
                    ");
                    $entries_query->bind_param("i", $employee_id);
                    $entries_query->execute();
                    $entries = $entries_query->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    foreach ($entries as $entry): 
                        // Convert times to user's timezone
                        $login_time = new DateTime($entry['login_time']);
                        $login_time->setTimezone(new DateTimeZone($entry['timezone'] ?? 'America/Chicago'));
                        
                        $logout_time = null;
                        if ($entry['logout_time']) {
                            $logout_time = new DateTime($entry['logout_time']);
                            $logout_time->setTimezone(new DateTimeZone($entry['timezone'] ?? 'America/Chicago'));
                        }
                    ?>
                        <tr>
                            <td><?php echo $login_time->format('Y-m-d'); ?></td>
                            <td><?php echo $login_time->format('h:i:s A'); ?></td>
                            <td>
                                <?php 
                                if ($logout_time) {
                                    echo $logout_time->format('h:i:s A');
                                } else {
                                    echo '<span class="text-primary">Active</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($logout_time) {
                                    $interval = $logout_time->diff($login_time);
                                    echo sprintf("%02d:%02d:%02d", 
                                        ($interval->days * 24) + $interval->h, 
                                        $interval->i, 
                                        $interval->s
                                    );
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
                            <td><?php echo htmlspecialchars($entry['timezone'] ?: 'UTC'); ?></td>
                            <td><?php echo $entry['remarks'] ? htmlspecialchars($entry['remarks']) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No time entries found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Manual Entry Modal -->
    <div class="modal fade" id="manualEntryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manual Time Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="record_time.php" method="POST" id="manualEntryForm" onsubmit="return validateManualEntry()">
                        <input type="hidden" name="action" value="manual_entry">
                        <input type="hidden" name="timezone" id="manualTimezone">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="entry_date" required 
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Login Time</label>
                            <input type="time" class="form-control" name="login_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Logout Time</label>
                            <input type="time" class="form-control" name="logout_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" 
                                      placeholder="Please provide reason for manual entry" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit for Approval</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function getUserTimeZone() {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    }

    function formatTimeForDisplay(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
            hour12: true
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const timezone = getUserTimeZone();
        document.getElementById('currentTimezone').textContent = `Your timezone: ${timezone}`;
        
        // Set timezone for all forms
        document.querySelectorAll('[id$="Timezone"]').forEach(input => {
            input.value = timezone;
        });

        // Update all time displays
        document.querySelectorAll('td[data-time]').forEach(el => {
            if (el.dataset.time && el.dataset.time !== '') {
                const localTime = new Date(el.dataset.time);
                el.textContent = formatTimeForDisplay(localTime);
            }
        });

        <?php if ($active_session): ?>
        function updateTimer() {
            const startTimeStr = '<?php echo $active_session['login_time']; ?>';
            const startTime = new Date(startTimeStr);
            
            function update() {
                const now = new Date();
                const timeDiff = Math.max(0, now - startTime);
                
                const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                
                document.getElementById('timer').textContent = 
                    `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }

            update();
            const intervalId = setInterval(update, 1000);
            
            // Cleanup
            window.addEventListener('unload', () => clearInterval(intervalId));
            return intervalId;
        }

        updateTimer();
        <?php endif; ?>
    });

    function validateManualEntry() {
        const form = document.getElementById('manualEntryForm');
        if (!form) return true;

        const date = form.entry_date.value;
        const loginTime = new Date(`${date}T${form.login_time.value}`);
        const logoutTime = new Date(`${date}T${form.logout_time.value}`);
        
        if (logoutTime <= loginTime) {
            alert('Logout time must be after login time');
            return false;
        }

        const now = new Date();
        if (loginTime > now || logoutTime > now) {
            alert('Cannot enter future dates');
            return false;
        }

        return true;
    }
    </script>
</body>
</html>