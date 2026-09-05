<?php
if (!isset($_SESSION)) {
    session_start();
}

if (!function_exists('check_admin_access')) {
    require_once __DIR__ . '/functions.php';
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Check if user is seller
function is_seller() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'seller';
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: /auth/login.php");
        exit();
    }
}

// Redirect if not admin
function require_admin() {
    if (!is_admin()) {
        header("Location: /auth/login.php");
        exit();
    }
}

// Redirect if not seller
function require_seller() {
    if (!is_seller()) {
        header("Location: /auth/login.php");
        exit();
    }
}
?>