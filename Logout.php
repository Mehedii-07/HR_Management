<?php
include 'includes/config.php';

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>