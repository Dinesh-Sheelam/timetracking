<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Simple query without password hashing for testing
    $query = "SELECT * FROM employees WHERE username = '$username' AND password = '$password'";
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        $_SESSION['employee_id'] = $employee['id'];
        $_SESSION['employee_name'] = $employee['name'];
        header('Location: dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = 'Invalid username or password';
        header('Location: index.php');
        exit();
    }
}

header('Location: index.php');
?>