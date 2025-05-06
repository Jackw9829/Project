    </main>
<style>
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
    }

    main {
        flex: 1 0 auto;
        min-height: calc(100vh - 80px); /* 80px is footer height */
    }

    footer {
        background-color: #d4e0ee;
        min-height: 80px;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        width: 100%;
        box-sizing: border-box;
        flex-shrink: 0;
    }

    .footer-left {
        text-align: left;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .footer-right {
        text-align: right;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .footer-links {
        color: #333;
        transition: color 0.3s ease;
        font-size: 16px;
        text-decoration: none;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .footer-links:hover {
        color: #007bff;
    }

    footer p {
        color: #333;
        font-size: 16px;
    }
</style>
<footer>
    <div class="footer-left">
        <p>&copy; 2024 MathQuest. All rights reserved.</p>
        <a href="about.php" class="footer-links">About Us</a>
    </div>
    <div class="footer-right">
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
            <a href="admin_messages.php" class="footer-links">Admin Support</a>
        <?php else: ?>
            <a href="contactadmin.php" class="footer-links">Admin Support</a>
        <?php endif; ?>
    </div>
</footer>
</body>
</html>
