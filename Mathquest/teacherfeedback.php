<?php
session_start();
require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Teacher Feedback</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tiny5';
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
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
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
            font-family: 'Tiny5';
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
            background: #c6e5d9;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-size: 16px;
            font-family: 'Tiny5';
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
            font-family: 'Tiny5';
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
            font-family: 'Tiny5';
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
            font-family: 'Tiny5';
        }

        select option {
            font-family: 'Tiny5';
        }
        input::placeholder,
        select::placeholder {
            font-family: 'Tiny5';
        }

        ::-webkit-input-placeholder { 
            font-family: 'Tiny5';
        }
        :-moz-placeholder { 
            font-family: 'Tiny5';
        }
        ::-moz-placeholder { 
            font-family: 'Tiny5';
        }
        :-ms-input-placeholder { 
            font-family: 'Tiny5';
        }

        .form-actions {
            text-align: center;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-actions span,
        .form-actions a {
            font-family: 'Tiny5';
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
            font-family: 'tiny5';
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
            font-family: 'Tiny5';
            background-color: transparent;
            border: 2px solid #333;
            color: #333;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-btn:hover {
            background-color: #333;
            color: white;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .edit-btn {
            font-family: 'Tiny5';
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
            background: #c6e5d9;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-size: 16px;
            font-family: 'Tiny5';
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
            font-family: 'Tiny5';
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
            font-family: 'Tiny5';
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .confirm-btn, .cancel-btn {
            font-family: 'Tiny5';
            padding: 8px 16px;
            border: 2px solid #333;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .confirm-btn {
            background-color: #c6e5d9;
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
            font-family: 'Tiny5';
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
            background: #c6e5d9;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-family: 'Tiny5';
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
            font-family: 'Tiny5';
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #c6e5d9;
        }

        .modal-buttons button:hover {
            background-color: #333;
            color: white;
        }

        .modal h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .question-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .question-content {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .question-text {
            padding: 1rem;
        }

        .question-text h2 {
            margin-bottom: 1rem;
            color: #333;
        }

        .question-counter {
            text-align: center;
            padding: 1rem;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 2rem 0;
        }

        .option-btn {
            padding: 1.5rem;
            border: 2px solid #333;
            border-radius: 8px;
            background: #fff;
            font-family: 'Tiny5';
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-btn:hover {
            background: #333;
            color: #fff;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .back-btn, .next-btn {
            padding: 8px 24px;
            border: 2px solid #333;
            border-radius: 8px;
            background: #c6e5d9;
            font-family: 'Tiny5';
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .back-btn:hover, .next-btn:hover {
            background: #333;
            color: #fff;
        }

        @media (max-width: 768px) {
            .question-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .question-counter {
                max-width: 200px;
                margin: 0 auto;
            }

            .options-grid {
                grid-template-columns: 1fr;
            }
        }

        .review-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .review-container h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }

        .quiz-review {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 2rem;
        }

        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #ddd;
        }

        .quiz-title {
            margin: 0;
            margin-bottom: 0.5rem;
        }

        .quiz-description {
            margin: 0;
            color: #666;
        }

        .points-display {
            padding: 0.5rem 1rem;
            border: 1px solid #333;
            border-radius: 4px;
        }

        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .question-item {
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }

        .option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: default;
        }

        @media (max-width: 768px) {
            .quiz-header {
                flex-direction: column;
                gap: 1rem;
            }

            .points-display {
                align-self: flex-start;
            }
        }

        .feedback-section {
            margin-top: 1.5rem;
            padding: 1rem;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .feedback-section h4 {
            margin-bottom: 1rem;
            color: #333;
        }

        .feedback-input {
            width: 100%;
            min-height: 100px;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Tiny5';
            resize: vertical;
            margin-bottom: 1rem;
        }

        .feedback-input:focus {
            outline: none;
            border-color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .feedback-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .feedback-buttons button {
            padding: 8px 16px;
            border: 2px solid #333;
            border-radius: 4px;
            font-family: 'Tiny5';
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .feedback-buttons .confirm-btn {
            background-color: #c6e5d9;
            color: #333;
        }

        .feedback-buttons .cancel-btn {
            background-color: transparent;
            color: #333;
        }

        .feedback-buttons button:hover {
            background-color: #333;
            color: white;
        }

        @media (max-width: 768px) {
            .feedback-buttons {
                flex-direction: column;
            }
            
            .feedback-buttons button {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <a href="homepage.html" class="logo">
            <img src="mathquestlogo.png" alt="MathQuest Logo">
            <span class="logo-text">Mathquest</span>
        </a>
        <div class="header-right">
            <a href="dashboard.html" class="nav-btn">Dashboard</a>
            <a href="activity.html" class="nav-btn">Activity</a>
            <a href="leaderboard.html" class="nav-btn">Leaderboard</a>
            <a href="profile.html" class="nav-btn">Profile</a>
        </div>
    </header>
    
    <main class="review-container">
        <h1>Review Questions</h1>
        <div class="quiz-review">
            <div class="quiz-header">
                <div class="quiz-info">
                    <h2 class="quiz-title">Quiz Title</h2>
                    <p class="quiz-description">Description</p>
                </div>
                <div class="points-display">Points</div>
            </div>
            
            <div class="questions-list">
                <div class="question-item">
                    <h3>1. Question</h3>
                    <div class="options-list">
                        <label class="option">
                            <input type="radio" name="q1" disabled>
                            <span>Option 1</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="q1" disabled>
                            <span>Option 2</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="q1" disabled>
                            <span>Option 3</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="q1" disabled>
                            <span>Option 4</span>
                        </label>
                    </div>
                    <div class="feedback-section">
                        <h4>Feedback</h4>
                        <textarea class="feedback-input" placeholder="Enter feedback"></textarea>
                        <div class="feedback-buttons">
                            <button class="confirm-btn">Confirm</button>
                            <button class="cancel-btn">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="footer-left">
            <p>&copy; 2024 MathQuest. All rights reserved.</p>
            <a href="#" class="footer-links">About Us</a>
        </div>
        <div class="footer-right">
            <a href="contactadmin.html" class="footer-links">Admin Support</a>
        </div>
    </footer>
    
    <div id="quizModal" class="modal">
        <div class="modal-content">
            <h3>Start Quiz?</h3>
            <div class="modal-buttons">
                <button class="confirm-btn">Yes</button>
                <button class="cancel-btn">No</button>
            </div>
        </div>
    </div>
    
    <script>
    document.querySelector('.login-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email');
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(email.value)) {
            alert('Please enter a valid email address');
            email.focus();
            return;
        }
    
        const phone = document.getElementById('phone');
        const phoneRegex = /^[0-9]{11}$/;
        if (!phoneRegex.test(phone.value)) {
            alert('Please enter a valid 11-digit phone number');
            phone.focus();
            return;
        }
    
        const password = document.getElementById('password');
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9!@#$%^&*]).{8,}$/;
        if (!passwordRegex.test(password.value)) {
            alert('Password must be at least 8 characters and contain at least one lowercase letter, one uppercase letter, and one number or special character');
            password.focus();
            return;
        }
    
        const confirmPassword = document.getElementById('confirm-password');
        if (password.value !== confirmPassword.value) {
            alert('Passwords do not match');
            confirmPassword.focus();
            return;
        }
        
        this.submit();
    });
    
    document.getElementById('phone').addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
        
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
        
        if (this.value.length !== 10) {
            this.setCustomValidity('Please enter a valid 10-digit phone number');
        } else {
            this.setCustomValidity('');
        }
    });
    
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        let messages = [];
        
        if (password.length < 8) {
            messages.push('• Minimum of 8 characters are required');
        }
        if (!/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
            messages.push('• Must include both uppercase and lowercase letters');
        }
        if (!/[0-9!@#$%^&*]/.test(password)) {
            messages.push('• Must include at least one number or special character');
        }
        
        const message = messages.slice(0, 3).join('\n');
        
        this.setCustomValidity(message);
        this.title = message || 'Password meets requirements';
    });
    </script>
    <script>
    const editBtn = document.querySelector('.edit-btn');
    const confirmBtn = document.querySelector('.confirm-btn');
    const cancelBtn = document.querySelector('.cancel-btn');
    const formInputs = document.querySelectorAll('.form-group input, .form-group select');

    let originalValues = {};

    editBtn.addEventListener('click', function() {
        formInputs.forEach(input => {
            input.disabled = false;
            input.style.backgroundColor = 'white';
            input.style.cursor = 'text';
            originalValues[input.id] = input.value;
        });

        confirmBtn.style.display = 'block';
        cancelBtn.style.display = 'block';
        
        editBtn.style.display = 'none';
    });

    cancelBtn.addEventListener('click', function() {
        formInputs.forEach(input => {
            input.disabled = true;
            input.style.backgroundColor = '#f5f5f5';
            input.style.cursor = 'default';
            input.value = originalValues[input.id];
        });

        confirmBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        
        editBtn.style.display = 'block';
    });

    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        formInputs.forEach(input => {
            input.disabled = true;
            input.style.backgroundColor = '#f5f5f5';
            input.style.cursor = 'default';
        });

        confirmBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        
        editBtn.style.display = 'block';
    });
    </script>
    <script>
    const modal = document.getElementById('quizModal');
    const attemptButtons = document.querySelectorAll('.attempt-btn');

    attemptButtons.forEach(button => {
        button.addEventListener('click', function() {
            modal.style.display = 'flex';
        });
    });

    const modalConfirmBtn = modal.querySelector('.confirm-btn');
    const modalCancelBtn = modal.querySelector('.cancel-btn');

    modalConfirmBtn.addEventListener('click', function() {
        console.log('Starting quiz...');
        modal.style.display = 'none';
    });

    modalCancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    </script>
    </body>
    </html>