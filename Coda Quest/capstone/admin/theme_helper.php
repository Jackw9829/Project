<?php
function getAdminTheme() {
    // Default to dark theme if not set
    $currentTheme = $_SESSION['admin_theme'] ?? 'dark';
    return $currentTheme;
}
?> 