/**
 * Matrix Background Animation
 * This file contains the code for the matrix-style background animation
 * used across all pages of the CodaQuest platform with retro gaming aesthetic.
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get canvas element
    const canvas = document.getElementById('matrix-bg');
    if (!canvas) return;
    
    // Get canvas context
    const ctx = canvas.getContext('2d');
    
    // Character settings - using more retro gaming symbols
    const charSize = 20; // Adjusted size for better visibility
    const chars = '01[]<>+-*/=?#$^~';
    
    // Initialize arrays
    let columns = Math.floor(window.innerWidth / charSize);
    let drops = [];
    
    // Initialize drops array
    for (let i = 0; i < columns; i++) {
        drops[i] = Math.floor(Math.random() * -canvas.height / charSize);
    }
    
    // Set up canvas size
    function setupCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        // Recalculate columns when canvas size changes
        columns = Math.floor(canvas.width / charSize);
        
        // Adjust drops array
        if (drops.length < columns) {
            // Add new drops if needed
            const currentLength = drops.length;
            for (let i = currentLength; i < columns; i++) {
                drops[i] = Math.floor(Math.random() * -canvas.height / charSize);
            }
        } else if (drops.length > columns) {
            // Remove extra drops if needed
            drops = drops.slice(0, columns);
        }
    }
    
    // Initial setup
    setupCanvas();
    
    // Handle window resize
    window.addEventListener('resize', setupCanvas);
    
    // Draw matrix effect
    function drawMatrix() {
        // Semi-transparent black to create fade effect
        ctx.fillStyle = 'rgba(0, 0, 0, 0.07)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Get the latest matrix color from CSS variables
        const matrixColor = getComputedStyle(document.documentElement).getPropertyValue('--matrix-color').trim() || '#a1cff5';
        ctx.fillStyle = matrixColor;
        ctx.font = `${charSize}px 'Press Start 2P', monospace`;
        
        // Draw characters
        for (let i = 0; i < drops.length; i++) {
            // Random character
            const char = chars[Math.floor(Math.random() * chars.length)];
            
            // Draw character
            ctx.fillText(char, i * charSize, drops[i] * charSize);
            
            // Move drop and reset if it's too low
            if (drops[i] * charSize > canvas.height && Math.random() > 0.98) {
                drops[i] = 0;
            }
            drops[i]++;
        }
    }
    
    // Start animation loop with slightly slower speed for retro effect
    setInterval(drawMatrix, 1000);
});
