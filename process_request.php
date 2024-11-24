<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAdminLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $comments = $_POST['comments'];
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $db->prepare("
        UPDATE time_entries 
        SET status = ?, remarks = CONCAT(remarks, '\nAdmin Comments: ', ?) 
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $status, $comments, $request_id);
    $stmt->execute();
}

header('Location: manage_requests.php');
?>