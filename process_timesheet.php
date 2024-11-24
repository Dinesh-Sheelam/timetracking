<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAdminLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $entry_id = $_POST['entry_id'];

    switch($action) {
        case 'approve':
            $stmt = $db->prepare("
                UPDATE time_entries 
                SET status = 'approved',
                    total_time = TIMEDIFF(logout_time, login_time)
                WHERE id = ?
            ");
            $stmt->bind_param("i", $entry_id);
            $stmt->execute();
            break;

        case 'reject':
            $stmt = $db->prepare("
                UPDATE time_entries 
                SET status = 'rejected' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $entry_id);
            $stmt->execute();
            break;

        case 'edit':
            $login_time = $_POST['login_time'];
            $logout_time = $_POST['logout_time'];
            $status = $_POST['status'];
            $comments = $_POST['admin_comments'];
            
            $stmt = $db->prepare("
                UPDATE time_entries 
                SET login_time = ?,
                    logout_time = ?,
                    total_time = TIMEDIFF(?, ?),
                    status = ?,
                    remarks = CONCAT(remarks, '\nAdmin Comments: ', ?)
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", 
                $login_time, 
                $logout_time, 
                $logout_time, 
                $login_time, 
                $status,
                $comments,
                $entry_id
            );
            $stmt->execute();
            break;
    }

    // Check if there's a return URL
    if (isset($_POST['return_url'])) {
        header('Location: ' . $_POST['return_url']);
    } else {
        header('Location: view_timesheets.php');
    }
    exit();
}

header('Location: view_timesheets.php');
?>