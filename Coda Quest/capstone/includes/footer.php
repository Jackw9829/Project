    </div> <!-- End of main-content div -->

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-logo">
                <span class="shimmer-title">CodaQuest</span>
            </div>
            <div class="footer-links">
                <a href="about.php" class="footer-link"><i class="material-icons">info</i>About</a>
                <a href="contact_admin.php" class="footer-link"><i class="material-icons">email</i>Contact</a>
            </div>
        </div>
        <div class="footer-copyright">
            <div class="pixel-border"></div>
            &copy; <?php echo date('Y'); ?> CodaQuest. All rights reserved.
        </div>
    </footer>

    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
    
    <!-- Include footer position script -->
    <script src="js/footer-position.js"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
</body>
</html>
