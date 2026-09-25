<?php

// Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Check if user has specific role
function hasRole($role) {
    return isLoggedIn() && getUserRole() === $role;
}

// Require login
function requireLogin() {
    // Prevent browser from caching protected pages — back button won't show them after logout
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

    if (!isLoggedIn()) {
        header('Location: /frontend/loginregister.php');
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: /frontend/loginregister.php?error=unauthorized');
        exit();
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user email
function getCurrentUserEmail() {
    return $_SESSION['email'] ?? null;
}

// Get current user name
function getCurrentUserName() {
    return ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
}

// Logout function
function logout() {
    session_start();
    session_unset();
    session_destroy();

    // Start a new session just to carry the "session expired" flash message
    session_start();
    $_SESSION['session_expired'] = true;

    header('Location: /frontend/loginregister.php');
    exit;
}
?>