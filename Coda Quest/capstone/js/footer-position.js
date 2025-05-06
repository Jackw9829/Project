/**
 * Footer Position Script
 * 
 * This script ensures the footer stays at the bottom of the page
 * even when there isn't enough content to scroll.
 */

document.addEventListener('DOMContentLoaded', function() {
    adjustFooterPosition();
    
    // Also adjust on window resize
    window.addEventListener('resize', adjustFooterPosition);
});

function adjustFooterPosition() {
    const footer = document.querySelector('footer');
    const mainContent = document.querySelector('.main-content');
    
    if (!footer || !mainContent) return;
    
    const windowHeight = window.innerHeight;
    const bodyHeight = document.body.offsetHeight;
    const footerHeight = footer.offsetHeight;
    
    // If the page content is less than the viewport height
    if (bodyHeight < windowHeight) {
        // Position the footer at the bottom of the viewport
        mainContent.style.minHeight = (windowHeight - footerHeight) + 'px';
    } else {
        // Reset the main content min-height if there's enough content
        mainContent.style.minHeight = '';
    }
}
