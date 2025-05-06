/**
 * CodaQuest Theme System
 * Handles theme switching between light and dark modes
 */

document.addEventListener('DOMContentLoaded', function() {
    // Apply theme immediately on page load
    const htmlElement = document.documentElement;
    const currentTheme = htmlElement.getAttribute('data-theme') || 'dark';
    
    // Ensure the data-theme attribute is set
    htmlElement.setAttribute('data-theme', currentTheme);
    
    // Preview theme changes when radio buttons are clicked
    const themeRadios = document.querySelectorAll('input[name="theme"]');
    themeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const selectedTheme = this.value;
            htmlElement.setAttribute('data-theme', selectedTheme);
        });
    });
    
    // No need to handle form submission as it's handled by the server-side PHP
    // The form will submit normally and the PHP code will update the database
});

