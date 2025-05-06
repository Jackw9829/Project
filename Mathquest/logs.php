<?php
session_start();
require_once 'config/db_connect.php';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$logs = [];
$error = null;
$page_title = "Admin Logs";

try {
    // Get user activity with recent and last login/logout times
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.name,
            u.user_type,
            (
                SELECT activity_time 
                FROM user_activity ua2 
                WHERE ua2.user_id = u.user_id 
                AND ua2.activity_details = 'User logged in'
                ORDER BY activity_time DESC 
                LIMIT 1
            ) as last_login,
            (
                SELECT activity_time 
                FROM user_activity ua3 
                WHERE ua3.user_id = u.user_id 
                AND ua3.activity_details = 'User logged out'
                ORDER BY activity_time DESC 
                LIMIT 1
            ) as last_logout,
            (
                SELECT GROUP_CONCAT(activity_time ORDER BY activity_time DESC SEPARATOR '|') 
                FROM (
                    SELECT activity_time 
                    FROM user_activity ua4 
                    WHERE ua4.user_id = u.user_id 
                    AND ua4.activity_details = 'User logged in'
                    ORDER BY activity_time DESC 
                    LIMIT 5
                ) recent_logins
            ) as recent_logins,
            (
                SELECT GROUP_CONCAT(activity_time ORDER BY activity_time DESC SEPARATOR '|') 
                FROM (
                    SELECT activity_time 
                    FROM user_activity ua5 
                    WHERE ua5.user_id = u.user_id 
                    AND ua5.activity_details = 'User logged out'
                    ORDER BY activity_time DESC 
                    LIMIT 5
                ) recent_logouts
            ) as recent_logouts
        FROM users u
        GROUP BY u.user_id, u.name, u.user_type
        ORDER BY u.user_id ASC
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    // Debug the results
    error_log("Debug - Processed logs: " . print_r($logs, true));
    
    if (empty($logs)) {
        $error = "No login records found.";
    }
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while fetching the logs.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Admin Logs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: 'Tiny5', sans-serif;
            background: #ffffff;
            position: relative;
            padding-top: 80px;
        }

        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .container {
            max-width: 1200px;
            width: 95%;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            margin-top: 6rem;
            padding: 0 1rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        a {
            text-decoration: none;
        }

        header, .header {
            background-color: #ffdcdc;
            height: 80px;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            height: 100%;
        }

        .logo img {
            height: 60px;
            margin-right: 10px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .header-links {
            display: flex;
            gap: 20px;
            font-size: 20px;
        }

        .header-links a {
            color: #666;
            transition: color 0.3s ease;
        }

        .header-links a:hover {
            color: #007bff;
        }

        footer {
            background-color: #d4e0ee;
            height: 80px;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            margin-top: auto;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        .footer-links a {
            color: #333;
            transition: color 0.3s ease;
            font-size: 20px;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .footer-links a:hover {
            color: #007bff;
        }

        @media (max-width: 768px) {
            header, .header {
                height: auto;
                min-height: 80px;
                padding: 10px 20px;
            }

            .logo img {
                height: 50px;
            }

            footer {
                height: auto;
                min-height: 80px;
                padding: 10px 20px;
            }

            .footer-left, .footer-right {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            header, .header {
                height: auto;
                min-height: 80px;
                padding: 10px 20px;
            }

            .logo img {
                height: 50px;
            }

            footer {
                height: auto;
                min-height: 80px;
                padding: 10px 20px;
            }

            .footer-left, .footer-right {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
        }

        .login-container {
            max-width: 800px;
            width: 90%;
            margin: 2rem auto;
            padding: 20px 20px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: grid;
            grid-template-columns: 120px auto 1fr;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            width: 100%;
        }

        .form-group label {
            color: #333;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .colon {
            justify-self: start;
        }

        .form-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            width: 100%;
        }

        .form-group input:focus {
            outline: none;
            border-color: #333;
            box-shadow: 3px 3px 6px rgba(0, 0, 0, 0.15);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .login-btn {
            background: #ffdcdc;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-size: 16px;
            font-family: 'Tiny5', sans-serif;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            width: 100%;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: #333;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .login-btn:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        button:hover {
            opacity: 0.9;
        }

        .text-link {
            font-family: 'Tiny5', sans-serif;
            font-size: 16px;
            text-decoration: none;
            color: #333;
            cursor: pointer;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
        }

        .text-link:hover {
            color: #0066cc;
            text-decoration: none;
        }

        select, input[type="date"] {
            font-family: 'Tiny5', sans-serif;
            font-size: 14px;
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        select:focus, input[type="date"]:focus {
            outline: none;
            border-color: #333;
            box-shadow: 3px 3px 6px rgba(0, 0, 0, 0.15);
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
        }

        input[type="date"]::-webkit-datetime-edit {
            font-family: 'Tiny5', sans-serif;
        }

        select option {
            font-family: 'Tiny5', sans-serif;
        }
        input::placeholder,
        select::placeholder {
            font-family: 'Tiny5', sans-serif;
        }

        ::-webkit-input-placeholder { 
            font-family: 'Tiny5', sans-serif;
        }
        :-moz-placeholder { 
            font-family: 'Tiny5', sans-serif;
        }
        ::-moz-placeholder { 
            font-family: 'Tiny5', sans-serif;
        }
        :-ms-input-placeholder { 
            font-family: 'Tiny5', sans-serif;
        }

        .form-actions {
            text-align: center;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-actions span,
        .form-actions a {
            font-family: 'Tiny5', sans-serif;
        }

        .form-row {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .form-actions, 
        .login-btn {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        @media screen and (max-width: 768px) {
            .login-container {
                width: 95%;
                padding: 15px;
                margin: 1rem auto;
            }

            .form-group {
                grid-template-columns: 100px auto 1fr;
                gap: 5px;
            }

            .form-row {
                flex-direction: column;
                gap: 1rem;
            }

            .dob-group, .gender-group {
                width: 100%;
            }

            .button-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .confirm-btn, .cancel-btn {
                width: 100%;
            }
        }

        @media screen and (max-width: 480px) {
            .login-container {
                width: 100%;
                padding: 10px;
                margin: 0.5rem auto;
            }

            .form-group {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .form-group label {
                margin-bottom: 5px;
            }

            .colon {
                display: none;
            }

            .profile-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
            }

            .header-right {
                flex-direction: column;
                width: 100%;
            }

            .nav-btn {
                width: 100%;
                text-align: center;
            }
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            flex-wrap: wrap;
        }

        .header-right {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav-btn {
            white-space: nowrap;
        }

        .teacher-register-btn {
            font-family: 'Tiny5', sans-serif;
            background-color: transparent;
            border: 2px solid #333;
            color: #333;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .teacher-register-btn:hover {
            background-color: #333;
            color: white;
        }

        @media screen and (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .teacher-register-btn {
                padding: 6px 12px;
                font-size: 14px;
            }
        }

        @media screen and (max-width: 480px) {
            .header {
                padding: 0.8rem;
            }
            
            .teacher-register-btn {
                padding: 5px 10px;
                font-size: 12px;
            }
        }

        .header-right {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            color: #666;
            transition: all 0.3s ease;
            font-size: 20px;
            padding: 8px 16px;
            margin: 5px;
            border-radius: 6px;
            background: transparent;
            white-space: nowrap;
            border: none;
        }

        .nav-btn:hover {
            background: #333;
            color: #fff;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .edit-btn {
            font-family: 'Tiny5', sans-serif;
            background: transparent;
            border: 2px solid #333;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-btn:hover {
            background: #333;
            color: white;
        }

        .profile-form input {
            background-color: #f5f5f5;
            cursor: default;
        }

        .logout-btn {
            background: #ffdcdc;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-size: 16px;
            font-family: 'Tiny5', sans-serif;
            width: 200px;
            margin: 1rem auto 0;
            display: block;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .logout-btn:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        @media screen and (max-width: 480px) {
            .logout-btn {
                width: 150px;
                font-size: 14px;
                padding: 6px 12px;
            }
        }

        .form-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            width: 100%;
            background-color: #f5f5f5;
            cursor: default;
        }

        .form-group select:disabled {
            background-color: #f5f5f5;
            cursor: default;
        }

        .form-group select:not(:disabled) {
            background-color: white;
            cursor: pointer;
        }

        .form-group select:focus {
            outline: none;
            border-color: #333;
            box-shadow: 3px 3px 6px rgba(0, 0, 0, 0.15);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
        }

        .modal-content .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .modal-content input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Tiny5', sans-serif;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .confirm-btn, .cancel-btn {
            font-family: 'Tiny5', sans-serif;
            padding: 8px 16px;
            border: 2px solid #333;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .confirm-btn {
            background-color: #ffdcdc;
            color: #333;
        }

        .cancel-btn {
            background-color: transparent;
            color: #333;
        }

        .confirm-btn:hover, .cancel-btn:hover {
            background-color: #333;
            color: white;
        }

        .close {
            color: #aaaaaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .confirm-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .confirm-btn:hover {
            background-color: #45a049;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .cancel-btn:hover {
            background-color: #e53935;
        }

        .form-row {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .dob-group, .gender-group {
            flex: 1;
            min-width: 0;
        }

        .button-group {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .confirm-btn, .cancel-btn {
            padding: 8px 24px;
            border-radius: 4px;
            font-family: 'Tiny5', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .confirm-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            border: none;
        }

        .form-group input, 
        .form-group select {
            background-color: #f5f5f5;
            cursor: default;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .quizzes-counter {
            text-align: right;
            margin-bottom: 2rem;
            font-size: 1.2rem;
        }

        .quiz-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .quiz-card {
            display: grid;
            grid-template-columns: 200px 1fr 200px;
            gap: 2rem;
            border: 1px solid #ddd;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .quiz-image {
            background-color: #f0f0f0;
            aspect-ratio: 1;
            border-radius: 8px;
        }

        .quiz-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .quiz-title {
            font-size: 1.5rem;
            margin: 0;
        }

        .quiz-author {
            color: #666;
            margin: 0;
        }

        .quiz-description {
            margin: 0;
        }

        .quiz-due-date {
            color: #666;
            margin: 0;
        }

        .attempt-btn {
            background: #ffdcdc;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-family: 'Tiny5', sans-serif;
            width: fit-content;
            margin-top: auto;
            transition: all 0.3s ease;
        }

        .attempt-btn:hover {
            background: #333;
            color: #fff;
        }

        .quiz-feedback {
            padding: 1rem;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .quiz-feedback h4 {
            margin: 0 0 1rem 0;
        }

        @media (max-width: 768px) {
            .quiz-card {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .quiz-image {
                max-width: 200px;
            }

            .quiz-feedback {
                margin-top: 1rem;
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .modal-buttons button {
            padding: 8px 24px;
            border: 2px solid #333;
            border-radius: 4px;
            font-family: 'Tiny5', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #ffdcdc;
        }

        .modal-buttons button:hover {
            background-color: #333;
            color: white;
        }

        .modal h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .homepage-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        .hero {
            background: linear-gradient(rgba(255, 220, 220, 0.2), rgba(255, 220, 220, 0.8));
            padding: 4rem 2rem;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 4rem;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 3rem;
            color: #333;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .hero p {
            font-size: 1.5rem;
            color: #666;
            margin-bottom: 2rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .cta-button, .secondary-button {
            font-family: 'Tiny5', sans-serif;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .cta-button {
            background: #333;
            color: white;
            border: none;
        }

        .secondary-button {
            background: transparent;
            border: 2px solid #333;
            color: #333;
        }

        .cta-button:hover, .secondary-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .features {
            padding: 4rem 0;
            text-align: center;
        }

        .features h2 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 3rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 0 1rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #666;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .cta-button, .secondary-button {
                width: 100%;
                max-width: 300px;
            }
        }

        .about-container {
            max-width: 1200px;
            margin: 120px auto 40px;
            padding: 0 20px;
        }

        .about-hero {
            text-align: center;
            margin-bottom: 60px;
        }

        .about-hero h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 10px;
        }

        .about-hero p {
            font-size: 1.2rem;
            color: #666;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 20px;
            justify-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .team-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid #ffdcdc;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .member-circle {
            width: 80px;
            height: 80px;
            background: #ffdcdc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.5rem;
            color: #333;
            font-family: 'Tiny5', sans-serif;
        }

        .member-info h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 8px;
        }

        .role {
            display: block;
            color: #666;
            font-size: 1rem;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .description {
            color: #777;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        @media (min-width: 992px) {
            .team-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .team-card:last-child {
                grid-column: 2;
            }
        }

        @media (max-width: 991px) {
            .team-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .team-card:last-child {
                grid-column: 1 / -1;
                max-width: 300px;
            }
        }

        @media (max-width: 650px) {
            .team-grid {
                grid-template-columns: 1fr;
            }
            
            .team-card {
                max-width: 300px;
                width: 100%;
            }
        }

        /* Add new styles for logs page */
        .page-title-container {
            text-align: center;
            margin: 4rem auto 4rem;
            position: relative;
            z-index: 1;
            padding-top: 2rem;
        }

        .page-title {
            font-size: 3rem;
            color: #2c3e50;
            margin: 0;
            padding: 0;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #ffdcdc, transparent);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-top: 1rem;
            font-weight: normal;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5rem;
            }
            .page-subtitle {
                font-size: 1rem;
            }
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table th {
            background: rgba(255, 192, 203, 0.2);
            color: #2c3e50;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 1.1rem;
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2c3e50;
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table tr:last-child td {
            border-bottom: none;
        }

        .logs-table tr:hover {
            background: rgba(255, 192, 203, 0.1);
        }

        .logs-table td.recent-times {
            padding: 0.5rem 1rem;
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table td.recent-times ul {
            list-style: none;
            margin: 0;
            padding: 0;
            font-size: 0.9rem;
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table td.recent-times li {
            padding: 0.25rem 0;
            color: #666;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table td.recent-times li:last-child {
            border-bottom: none;
        }

        .logs-table td.timestamp {
            white-space: nowrap;
            font-family: 'Tiny5', sans-serif;
            font-size: 0.9rem;
        }

        .logs-table td[data-label="User Type"] {
            text-transform: capitalize;
            color: #666;
            font-family: 'Tiny5', sans-serif;
        }

        .logs-table td[data-label="User Type"]::before {
            content: attr(data-label);
            font-weight: bold;
            display: none;
            margin-bottom: 0.25rem;
            color: #2c3e50;
        }

        @media screen and (max-width: 768px) {
            .logs-table {
                font-size: 0.9rem;
            }

            .logs-table td.timestamp,
            .logs-table td.recent-times {
                font-size: 0.8rem;
            }

            .logs-table td[data-label="User Type"]::before {
                display: block;
            }
        }

        @media screen and (max-width: 768px) {
            .logs-table thead {
                display: none;
            }

            .logs-table tr {
                display: block;
                margin-bottom: 1rem;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .logs-table td {
                display: block;
                text-align: left;
                padding: 0.5rem 1rem;
            }

            .logs-table td::before {
                content: attr(data-label);
                font-weight: bold;
                display: block;
                margin-bottom: 0.25rem;
                color: #2c3e50;
                font-family: 'Tiny5', sans-serif;
            }

            .logs-table td.recent-times ul {
                margin-left: 1rem;
            }
        }

        .error-message {
            background: rgba(255, 0, 0, 0.1);
            color: #d63031;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-family: 'Tiny5', sans-serif;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div id="particles-js"></div>
    
    <div class="page-title-container">
        <h1 class="page-title">Activity Logs</h1>
        <p class="page-subtitle">Track user activity and system events</p>
    </div>

    <main class="container">
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <table class="logs-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>User Type</th>
                    <th>Last Login</th>
                    <th>Recent Logins</th>
                    <th>Last Logout</th>
                    <th>Recent Logouts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    $recent_logins = $log['recent_logins'] ? explode('|', $log['recent_logins']) : [];
                    $recent_logouts = $log['recent_logouts'] ? explode('|', $log['recent_logouts']) : [];
                    
                    // Remove the last login/logout from recent lists as they're shown separately
                    if (!empty($recent_logins) && $recent_logins[0] === $log['last_login']) {
                        array_shift($recent_logins);
                    }
                    if (!empty($recent_logouts) && $recent_logouts[0] === $log['last_logout']) {
                        array_shift($recent_logouts);
                    }
                ?>
                    <tr>
                        <td data-label="User"><?php echo htmlspecialchars($log['name']); ?></td>
                        <td data-label="User Type"><?php echo htmlspecialchars($log['user_type']); ?></td>
                        <td class="timestamp" data-label="Last Login"><?php echo $log['last_login'] ? date('M d, Y - h:i A', strtotime($log['last_login'])) : 'Never'; ?></td>
                        <td class="recent-times" data-label="Recent Logins">
                            <?php if (!empty($recent_logins)): ?>
                                <ul>
                                    <?php foreach (array_slice($recent_logins, 0, 4) as $time): ?>
                                        <li><?php echo date('M d, Y - h:i A', strtotime($time)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                No recent activity
                            <?php endif; ?>
                        </td>
                        <td class="timestamp" data-label="Last Logout"><?php echo $log['last_logout'] ? date('M d, Y - h:i A', strtotime($log['last_logout'])) : 'Never'; ?></td>
                        <td class="recent-times" data-label="Recent Logouts">
                            <?php if (!empty($recent_logouts)): ?>
                                <ul>
                                    <?php foreach (array_slice($recent_logouts, 0, 4) as $time): ?>
                                        <li><?php echo date('M d, Y - h:i A', strtotime($time)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                No recent activity
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded');
        });
    </script>
</body>
</html>