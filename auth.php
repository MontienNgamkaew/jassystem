<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    // Determine the relative path to login.php
    $loginPath = (strpos($_SERVER['PHP_SELF'], '/includes/') !== false) ? '../login.php' : 'login.php';
    header('Location: ' . $loginPath);
    exit;
}
