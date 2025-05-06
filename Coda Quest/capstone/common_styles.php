<?php
/**
 * Common Styles for CodaQuest
 * 
 * This file contains the common CSS styles for all pages including header, footer, and dashboard
 * with a pixel grid square bold design and retro gaming aesthetics.
 */

// Include student theme helper to get current theme
include_once dirname(__FILE__) . '/includes/student_theme_helper.php';
?>
<!-- Common Styles -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Round" rel="stylesheet">

<style>
    /* CSS Variables - Light Theme */
    :root, [data-theme="light"] {
        --matrix-color: #ffffff; /* White for light theme */
        --primary-color: #b2defd;
        --primary-color-rgb: 178, 222, 253;
        --secondary-color: #a0cdf5;
        --accent-color: #a6d1f8;
        --highlight-color: #a1cff5;
        --text-color: #222222;
        --background-color: #ffffff;
        --card-bg: #f5f9ff;
        --border-radius: 0px;
        --shadow: 0 4px 0 rgba(0, 0, 0, 0.2);
        --transition: all 0.2s ease;
        --font-family: "Press Start 2P", cursive;
        --header-bg: #ffffff;
        --nav-bg: #ffffff;
        --border-color: #b2defd;
    }
    
    /* Dark Theme */
    [data-theme="dark"] {
        --matrix-color: #a1cff5; /* Neon blue for dark theme */
        --primary-color: #ff6b8e;
        --primary-color-rgb: 255, 107, 142;
        --secondary-color: #e64c7a;
        --accent-color: #ff4d6d;
        --highlight-color: #ff8fa3;
        --text-color: #ffffff;
        --background-color: #121212;
        --card-bg: #1e1e1e;
        --border-radius: 0px;
        --shadow: 0 4px 0 rgba(0, 0, 0, 0.5);
        --transition: all 0.2s ease;
        --font-family: "Press Start 2P", cursive;
        --header-bg: #1e1e1e;
        --nav-bg: #1e1e1e;
        --border-color: #ff6b8e;
    }

    /* Common Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: var(--font-family);
        font-weight: 400;
        font-style: normal;
        image-rendering: pixelated;
    }

    body {
        background-color: var(--background-color);
        color: var(--text-color);
        min-height: 100vh;
        line-height: 1.6;
        position: relative;
        z-index: 1;
        image-rendering: pixelated;
        display: flex;
        flex-direction: column;
    }
    
    /* Text Shadow Effects */
    h1, h2, h3, h4, h5, h6 {
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.3);
    }
    
    .page-title, .section-title, .settings-section-title {
        text-shadow: 3px 3px 0px rgba(0, 0, 0, 0.4);
    }
    
    .nav-link, .btn-primary, .btn-secondary {
        text-shadow: 1px 1px 0px rgba(0, 0, 0, 0.3);
    }
    
    [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, 
    [data-theme="dark"] h4, [data-theme="dark"] h5, [data-theme="dark"] h6 {
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.5);
    }
    
    /* Page Header with Retro Gaming Theme */
    .page-header {
        background-color: rgba(var(--primary-color-rgb), 0.1) !important;
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        text-align: center;
        border: 4px solid var(--primary-color);
        box-shadow: 0 0 15px rgba(var(--primary-color-rgb), 0.5);
        position: relative;
        overflow: hidden;
    }
    
    /* Add pixelated border effect */
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: repeating-linear-gradient(
            to right,
            var(--primary-color),
            var(--primary-color) 8px,
            var(--secondary-color) 8px,
            var(--secondary-color) 16px
        );
    }
    
    /* Add subtle glow animation */
    .page-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(var(--primary-color-rgb), 0) 0%, rgba(var(--primary-color-rgb), 0.1) 50%, rgba(var(--primary-color-rgb), 0) 100%);
        animation: headerGlow 3s infinite alternate;
        pointer-events: none;
    }
    
    @keyframes headerGlow {
        0% { opacity: 0.3; }
        100% { opacity: 0.7; }
    }
    
    /* Ensure text is visible in dark mode */
    .dark-mode .page-header {
        color: var(--text-color);
        background-color: rgba(0, 0, 0, 0.3) !important;
    }
    
    .dark-mode .page-title, .dark-mode .section-title, .dark-mode .settings-section-title {
        text-shadow: 3px 3px 0px rgba(0, 0, 0, 0.6);
    }
    
    .main-content {
        flex: 1 0 auto;
    }

    /* Material Icons styling */
    .material-icons {
        font-family: 'Material Icons';
        vertical-align: middle;
        margin-right: 5px;
        font-size: 18px;
        font-weight: normal;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        -webkit-font-smoothing: antialiased;
    }

    .material-icons-outlined {
        font-family: 'Material Icons Outlined';
        vertical-align: middle;
        margin-right: 5px;
        font-size: 18px;
        font-weight: normal;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        -webkit-font-smoothing: antialiased;
    }

    .material-icons-round {
        font-family: 'Material Icons Round';
        vertical-align: middle;
        margin-right: 5px;
        font-size: 18px;
        font-weight: normal;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        -webkit-font-smoothing: antialiased;
    }
    
    /* Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Button Styles */
    .btn {
        padding: 10px 18px;
        border-radius: var(--border-radius);
        border: 3px solid var(--border-color);
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        position: relative;
        overflow: hidden;
        z-index: 1;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        z-index: -1;
    }
    
    .btn:hover::before {
        left: 0;
    }

    .btn-primary {
        background-color: var(--primary-color);
        color: var(--text-color);
        border: 2px solid var(--accent-color);
    }

    .btn-primary:hover {
        background-color: var(--accent-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .btn-primary:active {
        transform: translateY(1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn-secondary {
        background-color: var(--card-bg);
        color: var(--text-color);
        border: 2px solid var(--border-color);
    }

    .btn-secondary:hover {
        background-color: var(--primary-color);
        color: var(--text-color);
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0, 0, 0, 0.2);
    }

    .btn-danger {
        background-color: var(--accent-color);
        color: white;
        border-color: #ff4040;
    }

    .btn-danger:hover {
        background-color: #ff4040;
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0, 0, 0, 0.5);
    }

    /* Outline Button Style */
    .btn-outline {
        background-color: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline:hover {
        background-color: var(--primary-color);
        color: var(--text-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 0 rgba(0, 0, 0, 0.2);
    }

    /* Back Button Style */
    .back-button {
        display: inline-flex;
        align-items: center;
        margin-bottom: 20px;
        font-family: 'Press Start 2P', cursive;
        font-size: 0.8rem;
        padding: 8px 16px;
    }

    .back-button i {
        margin-right: 8px;
        font-size: 16px;
    }

    .back-button:hover {
        transform: translateX(-5px);
    }

    /* Header Styles */
    header {
        background-color: var(--header-bg);
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        z-index: 20;
        border-bottom: 4px solid var(--border-color);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    header::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--accent-color), var(--primary-color));
        opacity: 0.7;
    }
    
    .logo-section {
        display: flex;
        align-items: center;
        width: 33%;
    }
    
    .logo-placeholder {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        border: 4px solid var(--border-color);
        background-color: var(--card-bg);
    }
    
    .logo-placeholder i {
        font-size: 24px;
        color: var(--primary-color);
    }
    
    .site-title {
        text-align: center;
        flex-grow: 1;
        display: flex;
        justify-content: center;
        width: 34%;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
    }
    
    .site-title h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #ffffff;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.2);
    }
    
    .user-section {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        width: 33%;
        gap: 15px;
    }
    
    .welcome-text {
        font-weight: 600;
        color: #ffffff;
        font-size: 0.7rem;
    }
    
    .username {
        font-weight: 600;
        color: var(--highlight-color);
        font-size: 0.7rem;
    }
    
    .profile-pic {
        width: 40px;
        height: 40px;
        border: 4px solid var(--primary-color);
        background-color: var(--card-bg);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }
    
    .auth-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
        justify-content: flex-end;
    }

    /* Navigation Menu Styles */
    .nav-menu {
        display: flex;
        justify-content: center;
        width: 100%;
        background-color: var(--nav-bg);
        padding: 0;
        margin-top: 0;
        border-bottom: 4px solid var(--border-color);
        position: relative;
        z-index: 20;
    }
    
    .nav-links {
        display: flex;
        list-style: none;
        justify-content: center;
        align-items: center;
        padding: 0;
        margin: 0;
        background-color: var(--nav-bg);
        width: 100%;
        gap: 20px;
    }
    
    .nav-item {
        position: relative;
        margin: 0;
        padding: 0;
        background-color: var(--nav-bg);
        text-align: center;
    }
    
    .nav-link {
        display: block;
        padding: 15px 25px;
        color: #ffffff;
        text-decoration: none;
        transition: var(--transition);
        font-weight: 600;
        font-size: 0.7rem;
        background-color: var(--nav-bg);
        text-align: center;
        text-transform: uppercase;
    }
    
    .nav-link:hover, .nav-link.active {
        color: var(--highlight-color);
        background-color: rgba(178, 222, 253, 0.2);
    }
    
    /* Dropdown Menu Styles */
    .dropdown {
        position: relative;
        display: inline-block;
        z-index: 20;
        background-color: var(--nav-bg);
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--card-bg);
        min-width: 160px;
        box-shadow: var(--shadow);
        z-index: 20;
        border-radius: var(--border-radius);
        opacity: 1;
        border: 4px solid var(--border-color);
    }
    
    .dropdown-content a {
        color: var(--text-color);
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        text-align: left;
        font-size: 0.7rem;
        text-transform: uppercase;
        background-color: var(--card-bg);
    }
    
    .dropdown-content a:hover {
        background-color: rgba(178, 222, 253, 0.2);
        color: var(--header-bg);
    }
    
    .dropdown:hover .dropdown-content {
        display: block;
    }
    
    .dropdown-toggle {
        display: flex;
        align-items: center;
    }
    
    .dropdown-toggle i {
        margin-left: 5px;
        transition: var(--transition);
    }
    
    .dropdown:hover .dropdown-toggle i {
        transform: rotate(180deg);
    }

    /* Mobile Menu Toggle */
    .hamburger-menu {
        display: none;
        cursor: pointer;
        font-size: 24px;
        color: var(--primary-color);
    }

    /* Footer */
    footer {
        background-color: var(--header-bg);
        padding: 30px 0;
        margin-top: 60px;
        flex-shrink: 0;
        border-top: 4px solid var(--border-color);
    }
    
    .footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .footer-logo {
        font-size: 1.2rem;
        font-weight: 700;
        color: #ffffff;
        text-transform: uppercase;
    }
    
    .footer-links {
        display: flex;
        gap: 20px;
    }
    
    .footer-link {
        color: #222222;
        text-decoration: none;
        transition: var(--transition);
        font-size: 0.7rem;
        text-transform: uppercase;
    }
    
    [data-theme="dark"] .footer-link {
        color: #ffffff;
    }
    
    .footer-link:hover {
        color: var(--highlight-color);
    }
    
    .footer-copyright {
        text-align: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid rgba(178, 222, 253, 0.3);
        font-size: 0.7rem;
    }

    /* Section Styles */
    .section {
        padding: 40px 0;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 4px solid var(--border-color);
        padding-bottom: 15px;
        background-color: var(--card-bg);
    }
    
    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--header-bg);
        display: flex;
        align-items: center;
        text-transform: uppercase;
    }
    
    .section-icon {
        color: var(--header-bg);
        margin-right: 10px;
    }

    /* Card Styles */
    .card {
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        border-bottom: 2px solid rgba(178, 222, 253, 0.5);
        padding-bottom: 10px;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--header-bg);
        text-transform: uppercase;
    }
    
    .card-body {
        color: var(--text-color);
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.8rem;
        color: var(--header-bg);
        text-transform: uppercase;
    }
    
    .form-control {
        width: 100%;
        padding: 10px;
        border: 4px solid var(--border-color);
        background-color: var(--card-bg);
        color: var(--text-color);
        font-size: 0.8rem;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        .hamburger-menu {
            display: block;
        }
        
        .nav-menu {
            position: fixed;
            top: 70px;
            left: -100%;
            width: 100%;
            height: calc(100vh - 70px);
            background-color: var(--card-bg);
            transition: var(--transition);
            z-index: 100;
            border-top: 4px solid var(--border-color);
        }
        
        .nav-menu.active {
            left: 0;
        }
        
        .nav-links {
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
        }
        
        .nav-item {
            width: 100%;
            text-align: left;
        }
        
        .nav-link {
            padding: 15px 0;
            width: 100%;
            text-align: left;
        }
        
        .dropdown-content {
            position: static;
            box-shadow: none;
            width: 100%;
            margin-left: 20px;
            display: none;
        }
        
        .dropdown.active .dropdown-content {
            display: block;
        }
        
        .footer-content {
            flex-direction: column;
            gap: 20px;
        }
        
        .footer-links {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    /* File upload button styling */
    input[type="file"] {
        cursor: pointer;
        font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
        font-size: 0.8rem;
        padding: 8px;
        width: 100%;
        background-color: var(--card-bg);
        color: var(--text-color);
        border-radius: var(--border-radius);
        border: 2px solid var(--border-color);
    }

    input[type="file"]::-webkit-file-upload-button,
    input[type="file"]::file-selector-button {
        background-color: var(--primary-color);
        color: var(--text-color);
        padding: 8px 16px;
        border: none;
        border-radius: var(--border-radius);
        margin-right: 10px;
        cursor: pointer;
        font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }

    input[type="file"]::-webkit-file-upload-button:hover,
    input[type="file"]::file-selector-button:hover {
        background-color: var(--accent-color);
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    /* Custom file upload wrapper */
    .file-upload-wrapper {
        position: relative;
        display: block;
        width: 100%;
        margin-bottom: 1rem;
    }

    .file-upload-label {
        display: inline-block;
        padding: 10px 15px;
        background-color: var(--primary-color);
        color: var(--text-color);
        border-radius: var(--border-radius);
        text-align: center;
        cursor: pointer;
        margin-bottom: 8px;
        transition: all 0.3s ease;
        border: 2px solid var(--border-color);
    }

    .file-upload-label:hover {
        background-color: var(--accent-color);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .file-name-display {
        display: block;
        font-size: 0.8rem;
        margin-top: 5px;
        color: var(--text-color);
    }
</style>
