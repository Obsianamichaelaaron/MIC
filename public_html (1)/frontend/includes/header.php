<?php
require_once __DIR__ . '/config/session.php';
$currentRole = getUserRole();
$currentUserName = getCurrentUserName();
$currentUserId = getCurrentUserId();
$profilePic = '';

// Fetch profile picture for applicant
if ($currentRole === 'applicant' && $currentUserId) {
    require_once __DIR__ . '/config/database.php';
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT profile_pic FROM applicants WHERE user_id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $profilePic = $row['profile_pic'] ?? '';
    }
    $stmt->close();
    $conn->close();
}

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'MULTIBIZ INTERNATIONAL CORPORATION'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Anti-flash: apply saved theme before paint -->
    <script>
        (function(){
            var t = localStorage.getItem('mb_theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
            else if (window.matchMedia('(prefers-color-scheme: dark)').matches)
                document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #0056b3;
            --secondary: #ff6b00;
            --dark: #333;
            --light: #f8f9fa;
            --gray: #6c757d;
            --shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;

            /* Theme tokens — Light defaults */
            --bg-body:       #f8f9fa;
            --bg-navbar:     rgba(255,255,255,0.95);
            --bg-card:       #ffffff;
            --text-main:     #333333;
            --text-muted:    #6c757d;
            --border-color:  rgba(0,0,0,0.07);
            --nav-link:      #333333;
            --nav-link-hover-bg: rgba(0,86,179,0.1);
            --input-border:  #e0e0e0;
            --input-bg:      #ffffff;
            --table-stripe:  #f8f9fa;
        }

        /* ── Dark mode overrides ── */
        [data-theme="dark"] {
            --bg-body:       #0f1117;
            --bg-navbar:     rgba(17,20,30,0.97);
            --bg-card:       #1a1d27;
            --text-main:     #e2e8f0;
            --text-muted:    #94a3b8;
            --border-color:  rgba(255,255,255,0.08);
            --nav-link:      #cbd5e1;
            --nav-link-hover-bg: rgba(99,179,237,0.12);
            --input-border:  #2d3348;
            --input-bg:      #242736;
            --table-stripe:  #1e2235;
            --shadow:        0 5px 20px rgba(0,0,0,0.4);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-body);
            min-height: 100vh;
            color: var(--text-main);
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* Mobile-First Navigation */
        .navbar {
            background: var(--bg-navbar);
            padding: 15px 5%;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
            width: 100%;
            flex-wrap: wrap;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .navbar-brand:hover {
            color: #004494;
        }
        
        .navbar-brand img {
            height: 40px;
            width: auto;
            object-fit: contain;
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: var(--transition);
        }
        
        .mobile-menu-btn:hover {
            background: rgba(0, 86, 179, 0.1);
        }
        
        /* Navigation Menu */
        .navbar-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            flex: 1;
            margin: 0 2rem;
            transition: var(--transition);
        }
        
        .navbar-menu a {
            color: var(--nav-link);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            padding: 8px 12px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }
        
        .navbar-menu a:hover {
            color: var(--primary);
            background: var(--nav-link-hover-bg);
        }
        
        .navbar-menu a.active {
            color: var(--primary);
            background: rgba(0, 86, 179, 0.15);
            font-weight: 600;
        }
        
        .navbar-menu a.active i {
            color: var(--primary);
        }
        
        .navbar-menu a i {
            font-size: 0.9rem;
        }
        
        .welcome-message {
            color: var(--gray);
            font-size: 0.9rem;
            margin-right: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            flex-shrink: 0;
            margin-left: auto;
            transition: var(--transition);
        }
        
        .user-name {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-name i {
            font-size: 1.1rem;
        }
        
        /* Standard Button Styles */
        .btn, button.btn, .logout-btn, .btn-logout, a.btn {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            line-height: 1.5;
            white-space: nowrap;
        }
        
        .btn:hover, button.btn:hover, .logout-btn:hover, .btn-logout:hover, a.btn:hover {
            background: #004494;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.3);
        }
        
        .btn:active, button.btn:active, .logout-btn:active, .btn-logout:active, a.btn:active {
            transform: translateY(0);
        }
        
        /* Button Variants */
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #004494;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #F44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #da190b;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Button Sizes */
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-lg {
            padding: 14px 28px;
            font-size: 1.1rem;
        }
        
        /* Mobile-First Responsive Design */
        @media (max-width: 1024px) {
            .navbar {
                padding: 12px 3%;
                gap: 1rem;
            }
            
            .navbar-menu {
                margin: 0 1rem;
                gap: 1rem;
            }
            
            .navbar-menu a {
                font-size: 0.9rem;
                padding: 6px 10px;
            }
        }
        
        @media (max-width: 768px) {
            .navbar {
                padding: 12px 3%;
                gap: 0.5rem;
            }
            
            .mobile-menu-btn {
                display: block;
                order: 1;
            }
            
            .navbar-brand {
                order: 2;
                margin-bottom: 0;
                flex: 1;
                justify-content: center;
            }
            
            .navbar-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--bg-card);
                flex-direction: column;
                padding: 1rem;
                box-shadow: var(--shadow);
                margin: 0;
                gap: 0.5rem;
                border-top: 1px solid var(--border-color);
                z-index: 999;
            }
            
            .navbar-menu.active {
                display: flex;
            }
            
            .navbar-menu a {
                width: 100%;
                justify-content: flex-start;
                padding: 12px 15px;
                border-radius: 8px;
                font-size: 0.9rem;
            }
            
            .navbar-menu a span {
                display: inline !important;
            }
            
            .user-info {
                order: 3;
                width: 100%;
                justify-content: space-between;
                margin: 0.5rem 0 0 0;
                padding-top: 0.5rem;
                border-top: 1px solid var(--border-color);
            }
            
            .user-name {
                font-size: 0.9rem;
            }
            
            .btn-logout {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .navbar {
                padding: 10px 3%;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
            
            .navbar-brand img {
                height: 35px;
            }
            
            .mobile-menu-btn {
                font-size: 1.3rem;
            }
            
            .navbar-menu a {
                font-size: 0.85rem;
                padding: 10px 12px;
            }
            
            .user-info {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }
            
            .user-name {
                justify-content: center;
                text-align: center;
            }
            
            .btn-logout {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Very Small Screens */
        @media (max-width: 360px) {
            .navbar {
                padding: 8px 2%;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .navbar-brand img {
                height: 30px;
            }
            
            .mobile-menu-btn {
                font-size: 1.2rem;
                padding: 4px;
            }
            
            .navbar-menu a {
                font-size: 0.8rem;
                padding: 8px 10px;
            }
        }
        
        /* Container and Card Styles */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 5% 1rem;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            margin-top: 0;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .container > .card:first-child,
        .container > div:first-child > .card:first-child {
            margin-top: 0;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .card h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card h1 i {
            font-size: 1.8rem;
        }
        
        .card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--dark);
        }
        
        /* Mobile Content Padding */
        @media (max-width: 768px) {
            .container {
                padding: 0 3% 1rem;
            }
            
            .card {
                padding: 1.5rem;
                border-radius: 12px;
                margin-bottom: 1.5rem;
            }
            
            .card h1 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .card h2 {
                font-size: 1.3rem;
            }
            
            .card h3 {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 0 2% 1rem;
            }
            
            .card {
                padding: 1.25rem;
                border-radius: 10px;
                margin-bottom: 1rem;
            }
            
            .card h1 {
                font-size: 1.3rem;
            }
            
            .card h2 {
                font-size: 1.1rem;
            }
        }

        /* ── Avatar Dropdown Styles ─────────────────────────────────── */
        .ud-wrapper {
            position: relative;
            margin-left: auto;
            flex-shrink: 0;
        }

        .ud-trigger {
            display: flex;
            align-items: center;
            gap: 7px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 50px;
            transition: background 0.2s;
        }
        .ud-trigger:hover { background: rgba(0,86,179,0.08); }

        .ud-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0056b3, #003d82);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.5px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,86,179,0.35);
            font-family: 'Poppins', sans-serif;
            border: 2px solid rgba(255,255,255,0.9);
            overflow: hidden;
        }

        .ud-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .ud-avatar-lg {
            width: 46px;
            height: 46px;
            font-size: 1rem;
            overflow: hidden;
        }

        .ud-avatar-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .ud-caret {
            font-size: 0.7rem;
            color: #666;
            transition: transform 0.25s ease;
        }
        .ud-caret.open { transform: rotate(180deg); }

        /* Dropdown panel */
        .ud-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 240px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.14), 0 2px 8px rgba(0,0,0,0.07);
            border: 1px solid rgba(0,0,0,0.07);
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.97);
            transform-origin: top right;
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
            z-index: 9999;
        }
        .ud-dropdown.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        /* Header inside dropdown */
        .ud-header {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 16px 16px 14px;
            position: relative;
        }
        .ud-header-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }
        .ud-full-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1a1a2e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: 'Poppins', sans-serif;
        }
        .ud-email {
            font-size: 0.75rem;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }
        .ud-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #f0f0f0;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.65rem;
            color: #888;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
            flex-shrink: 0;
        }
        .ud-close:hover { background: #e0e0e0; color: #333; }

        /* Divider */
        .ud-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 2px 0;
        }

        /* Menu items */
        .ud-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 16px;
            color: #333;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: background 0.15s, color 0.15s;
            font-family: 'Poppins', sans-serif;
        }
        .ud-item:hover {
            background: #f5f7fb;
            color: #0056b3;
        }
        .ud-item:hover .ud-item-icon { color: #0056b3; }

        .ud-item-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: #f0f4fb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #555;
            flex-shrink: 0;
            transition: background 0.15s, color 0.15s;
        }

        .ud-item-logout { color: #d32f2f; }
        .ud-item-logout:hover { background: #fff5f5; color: #c62828; }
        .ud-item-logout .ud-item-icon { background: #fdecea; color: #d32f2f; }
        .ud-item-logout:hover .ud-item-icon { background: #fcd9d7; }

        /* ── Dark Mode Toggle Button ───────────────────────────── */
        .dm-toggle {
            position: relative;
            width: 52px;
            height: 28px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            background: #e2e8f0;
            flex-shrink: 0;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            padding: 0 4px;
            margin-right: 6px;
        }
        .dm-toggle:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        [data-theme="dark"] .dm-toggle { background: #2d3a5e; }

        .dm-pill {
            position: absolute;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            transition: transform 0.3s cubic-bezier(.4,0,.2,1), background 0.3s;
            pointer-events: none;
        }
        [data-theme="dark"] .dm-pill {
            transform: translateX(24px);
            background: #7aa2f7;
        }

        .dm-icon {
            position: absolute;
            font-size: 0.65rem;
            pointer-events: none;
            transition: opacity 0.25s;
            display: flex; align-items: center;
        }
        .dm-sun  { left: 6px;  color: #f59e0b; }
        .dm-moon { right: 6px; color: #94a3b8; }

        /* In light mode: show sun, hide moon */
        [data-theme="light"] .dm-sun,
        :root:not([data-theme="dark"]) .dm-sun { opacity: 1; }
        [data-theme="light"] .dm-moon,
        :root:not([data-theme="dark"]) .dm-moon { opacity: 0.3; }

        /* In dark mode: hide sun, show moon */
        [data-theme="dark"] .dm-sun  { opacity: 0.3; }
        [data-theme="dark"] .dm-moon { opacity: 1; color: #7aa2f7; }

        /* ── Dropdown dark mode overrides ──────────────────────── */
        [data-theme="dark"] .ud-trigger:hover { background: rgba(99,179,237,0.1); }
        [data-theme="dark"] .ud-dropdown {
            background: #1e2235;
            border-color: rgba(255,255,255,0.08);
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
        [data-theme="dark"] .ud-full-name { color: #e2e8f0; }
        [data-theme="dark"] .ud-email     { color: #94a3b8; }
        [data-theme="dark"] .ud-close     { background: #2d3348; color: #94a3b8; }
        [data-theme="dark"] .ud-close:hover { background: #3a4060; color: #e2e8f0; }
        [data-theme="dark"] .ud-divider   { background: rgba(255,255,255,0.07); }
        [data-theme="dark"] .ud-item      { color: #cbd5e1; }
        [data-theme="dark"] .ud-item:hover { background: rgba(99,179,237,0.1); color: #7aa2f7; }
        [data-theme="dark"] .ud-item:hover .ud-item-icon { color: #7aa2f7; background: rgba(99,179,237,0.12); }
        [data-theme="dark"] .ud-item-icon { background: #242736; color: #94a3b8; }
        [data-theme="dark"] .ud-item-logout { color: #fc8181; }
        [data-theme="dark"] .ud-item-logout:hover { background: rgba(252,129,129,0.08); color: #fc8181; }
        [data-theme="dark"] .ud-item-logout .ud-item-icon { background: rgba(252,129,129,0.1); color: #fc8181; }

        /* ── Global dark mode page styles ──────────────────────── */
        [data-theme="dark"] .navbar {
            background: var(--bg-navbar);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        [data-theme="dark"] .navbar-menu a { color: #cbd5e1; }
        [data-theme="dark"] .navbar-menu a:hover { color: #7aa2f7; background: rgba(99,179,237,0.1); }
        [data-theme="dark"] .navbar-menu a.active { color: #7aa2f7; background: rgba(99,179,237,0.12); }
        [data-theme="dark"] .navbar-menu {
            background: #1a1d27;
            border-top-color: rgba(255,255,255,0.07);
        }
        [data-theme="dark"] .card {
            border-color: rgba(255,255,255,0.06);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        [data-theme="dark"] .card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.4); }
        [data-theme="dark"] input,
        [data-theme="dark"] textarea,
        [data-theme="dark"] select {
            background: var(--input-bg) !important;
            border-color: var(--input-border) !important;
            color: var(--text-main) !important;
        }
        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder { color: #4a5568 !important; }
        [data-theme="dark"] label { color: #cbd5e1 !important; }
        [data-theme="dark"] small { color: #64748b !important; }

        @media (max-width: 768px) {
            .dm-toggle { margin-right: 4px; }
            .ud-wrapper { margin-left: 0; order: 3; }
            .ud-dropdown { right: -5px; width: 220px; }
        }
        @media (max-width: 480px) {
            .ud-dropdown { right: 0; width: 210px; }
        }
    </style>
</head>
<body>
<script>
    // When the browser restores a page from back/forward cache (bfcache),
    // check if the session is still valid. If not, redirect to login.
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Page was restored from bfcache — verify session is still alive
            fetch('/frontend/includes/auth/check_session.php', { cache: 'no-store' })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data.logged_in) {
                        window.location.replace('/frontend/loginregister.php');
                    }
                })
                .catch(function() {
                    window.location.replace('/frontend/loginregister.php');
                });
        }
    });
</script>
    <nav class="navbar">
        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand Logo -->
        <a href="../<?php echo $currentRole; ?>/dashboard.php" class="navbar-brand">
            <img src="../images/mbLogo.png" alt="MULTIBIZ INTERNATIONAL CORPORATION" style="height: 40px; margin-right: 10px;">
        </a>
        
        <!-- Navigation Menu -->
        <div class="navbar-menu" id="navbarMenu">
            <?php if (isLoggedIn()): ?>
                <a href="../<?php echo $currentRole; ?>/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
                <?php if ($currentRole === 'applicant'): ?>
                    
                    <a href="../applicant/applications.php" class="<?php echo ($currentPage == 'applications.php') ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i> <span>Applications</span>
                    </a>
                    <a href="../applicant/jobs.php" class="<?php echo ($currentPage == 'jobs.php') ? 'active' : ''; ?>">
                        <i class="fas fa-search"></i> <span>Browse Jobs</span>
                    </a>
                    <a href="../applicant/chat.php" class="<?php echo ($currentPage == 'chat.php') ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i> <span>Messages</span>
                    </a>
                <?php elseif ($currentRole === 'employer'): ?>
                    <a href="../employer/post_job.php" class="<?php echo ($currentPage == 'post_job.php') ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle"></i> <span>Post Job</span>
                    </a>
                    <a href="../employer/candidates.php" class="<?php echo ($currentPage == 'candidates.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> <span>Candidates</span>
                    </a>
                    <a href="../employer/jobs.php" class="<?php echo ($currentPage == 'jobs.php') ? 'active' : ''; ?>">
                        <i class="fas fa-briefcase"></i> <span>My Jobs</span>
                    </a>
                    <a href="../employer/chat.php" class="<?php echo ($currentPage == 'chat.php') ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i> <span>Messages</span>
                    </a>
                <?php elseif ($currentRole === 'admin'): ?>
                    <a href="../admin/users.php" class="<?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> <span>Users</span>
                    </a>
                    <a href="../admin/jobs.php" class="<?php echo ($currentPage == 'jobs.php') ? 'active' : ''; ?>">
                        <i class="fas fa-briefcase"></i> <span>All Jobs</span>
                    </a>
                    <a href="../admin/analytics.php" class="<?php echo ($currentPage == 'analytics.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> <span>Analytics</span>
                    </a>
                      <a href="../admin/cms.php" class="<?php echo ($currentPage == 'cms.php') ? 'active' : ''; ?>">
                        <i class="fas fa-edit"></i> <span>CMS</span>
                    </a>
                    <a href="../admin/messages.php" class="<?php echo ($currentPage == 'messages.php') ? 'active' : ''; ?>" style="position:relative;">
                        <i class="fas fa-envelope"></i> <span>Messages</span>
                        <?php
                        // Show unread badge — only compute if we're admin
                        if (isset($conn) || true) {
                            try {
                                $hConn = getDBConnection();
                                $hRes  = $hConn->query("SELECT COUNT(*) AS c FROM contact_inquiries WHERE is_read = 0");
                                $hCnt  = $hRes ? (int)$hRes->fetch_assoc()['c'] : 0;
                                $hConn->close();
                                if ($hCnt > 0) {
                                    echo '<span style="position:absolute;top:-4px;right:-6px;background:#ef4444;color:white;
                                                       border-radius:10px;padding:1px 5px;font-size:10px;font-weight:700;
                                                       min-width:16px;text-align:center;line-height:16px;">'
                                         . $hCnt . '</span>';
                                }
                            } catch (Exception $e) { /* table may not exist yet */ }
                        }
                        ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Dark Mode Toggle -->
        <button class="dm-toggle" id="dmToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
            <span class="dm-icon dm-sun"><i class="fas fa-sun"></i></span>
            <span class="dm-icon dm-moon"><i class="fas fa-moon"></i></span>
            <span class="dm-pill"></span>
        </button>

        <!-- User Avatar Dropdown -->
        <?php
        $currentUserEmail = getCurrentUserEmail() ?? '';
        $avatarInitials = '';
        $nameParts = explode(' ', trim($currentUserName));
        foreach ($nameParts as $part) { $avatarInitials .= strtoupper(substr($part, 0, 1)); }
        $avatarInitials = substr($avatarInitials, 0, 2);
        $roleLabel = ucfirst($currentRole ?? 'User');

        // Check if profile picture exists and is valid
        $hasProfilePic = !empty($profilePic) && file_exists(__DIR__ . '/../' . $profilePic);
        $profilePicUrl = $hasProfilePic ? '../' . $profilePic : '';
        ?>
        <div class="ud-wrapper" id="udWrapper">
            <button class="ud-trigger" id="udTrigger" aria-haspopup="true" aria-expanded="false">
                <span class="ud-avatar" id="udAvatar">
                    <?php if ($hasProfilePic): ?>
                        <img src="<?php echo htmlspecialchars($profilePicUrl); ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo htmlspecialchars($avatarInitials); ?>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-down ud-caret" id="udCaret"></i>
            </button>

            <div class="ud-dropdown" id="udDropdown" role="menu">
                <!-- Header card -->
                <div class="ud-header">
                    <span class="ud-avatar ud-avatar-lg" id="udAvatarLg">
                        <?php if ($hasProfilePic): ?>
                            <img src="<?php echo htmlspecialchars($profilePicUrl); ?>?t=<?php echo time(); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo htmlspecialchars($avatarInitials); ?>
                        <?php endif; ?>
                    </span>
                    <div class="ud-header-text">
                        <span class="ud-full-name"><?php echo htmlspecialchars($currentUserName); ?></span>
                        <span class="ud-email"><?php echo htmlspecialchars($currentUserEmail ?: $roleLabel); ?></span>
                    </div>
                    <button class="ud-close" id="udClose" title="Close"><i class="fas fa-times"></i></button>
                </div>

                <div class="ud-divider"></div>

                <!-- Menu items -->
                <?php if ($currentRole === 'applicant'): ?>
                <a href="../applicant/profile.php" class="ud-item" role="menuitem">
                    <span class="ud-item-icon"><i class="fas fa-user-edit"></i></span>
                    <span>My Profile</span>
                </a>
                <?php elseif ($currentRole === 'employer'): ?>
                <a href="../employer/jobs.php" class="ud-item" role="menuitem">
                    <span class="ud-item-icon"><i class="fas fa-briefcase"></i></span>
                    <span>My Jobs</span>
                </a>
                <?php endif; ?>
                <a href="../<?php echo $currentRole; ?>/chat.php" class="ud-item" role="menuitem">
                    <span class="ud-item-icon"><i class="fas fa-comments"></i></span>
                    <span>Messages</span>
                </a>

                <div class="ud-divider"></div>

                <a href="/frontend/includes/auth/logout.php" class="ud-item ud-item-logout" role="menuitem">
                    <span class="ud-item-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span>Log Out</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
    // Mobile Menu Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navbarMenu = document.getElementById('navbarMenu');
        
        // Toggle mobile menu
        mobileMenuBtn.addEventListener('click', function() {
            navbarMenu.classList.toggle('active');
            
            // Change icon based on menu state
            const icon = this.querySelector('i');
            if (navbarMenu.classList.contains('active')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });
        
        // Close mobile menu when clicking on a link
        const navLinks = navbarMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                navbarMenu.classList.remove('active');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!navbarMenu.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                navbarMenu.classList.remove('active');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                navbarMenu.classList.remove('active');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
            }
        });
        
        // Prevent body scroll when mobile menu is open (optional)
        function handleBodyScroll() {
            if (window.innerWidth <= 768 && navbarMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        
        mobileMenuBtn.addEventListener('click', handleBodyScroll);
        window.addEventListener('resize', handleBodyScroll);
    });

    // ── Dark Mode ─────────────────────────────────────────────
    (function() {
        var html = document.documentElement;
        var saved = localStorage.getItem('mb_theme');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = saved || (prefersDark ? 'dark' : 'light');
        html.setAttribute('data-theme', theme);

        function applyTheme(t) {
            html.setAttribute('data-theme', t);
            localStorage.setItem('mb_theme', t);
        }

        function initDM() {
            var btn = document.getElementById('dmToggle');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var current = html.getAttribute('data-theme');
                applyTheme(current === 'dark' ? 'light' : 'dark');
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDM);
        } else {
            initDM();
        }
    })();
    // ── End Dark Mode ─────────────────────────────────────────

    // ── User Dropdown ─────────────────────────────────────────
    (function() {
        function initDropdown() {
            var trigger  = document.getElementById('udTrigger');
            var dropdown = document.getElementById('udDropdown');
            var caret    = document.getElementById('udCaret');
            var closeBtn = document.getElementById('udClose');
            if (!trigger || !dropdown) return;

            function open() {
                dropdown.classList.add('open');
                if (caret) caret.classList.add('open');
                trigger.setAttribute('aria-expanded', 'true');
            }
            function close() {
                dropdown.classList.remove('open');
                if (caret) caret.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            }
            function toggle() {
                dropdown.classList.contains('open') ? close() : open();
            }

            trigger.addEventListener('click', function(e) { e.stopPropagation(); toggle(); });
            if (closeBtn) closeBtn.addEventListener('click', function(e) { e.stopPropagation(); close(); });
            document.addEventListener('click', function(e) {
                var wrapper = document.getElementById('udWrapper');
                if (wrapper && !wrapper.contains(e.target)) close();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') close();
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDropdown);
        } else {
            initDropdown();
        }
    })();
    </script>