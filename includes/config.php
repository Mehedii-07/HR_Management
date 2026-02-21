<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'hr_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isHR() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

function redirectBasedOnRole() {
    if (isLoggedIn()) {
        if (isHR()) {
            header('Location: dashboard.php');
        } else {
            // Check if employee has completed profile
            if (isset($_SESSION['profile_completed']) && $_SESSION['profile_completed']) {
                header('Location: dashboard.php');
            } else {
                header('Location: employee-info.php');
            }
        }
        exit();
    }
}
?>