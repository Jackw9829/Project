/**
 * Matrix Background Animation
 * This file contains the code for the matrix-style background animation
 * used across all pages of the CodaQuest platform with retro gaming aesthetic.
 */

// Matrix Background Animation
document.addEventListener('DOMContentLoaded', function() {
    // Get the canvas element
    const canvas = document.getElementById('matrix-bg');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Make canvas full screen
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    // Characters to display (binary + coding symbols for Python learning theme)
    const chars = '01{}[]()<>:;+-*/=&|!?@#$%^~πλΩ∑∫√∂PyThOnCoDe';
    const charArray = chars.split('');
    
    // Font size - larger for more impact
    const fontSize = 24;
    
    // Calculate columns (based on canvas width and font size)
    const columns = Math.floor(canvas.width / fontSize);
    
    // Array to track the y position of each column of text
    const drops = [];
    
    // Initialize drops at random y positions
    for (let i = 0; i < columns; i++) {
        drops[i] = Math.random() * -100;
    }
    
    // Get matrix color from CSS variable
    function getMatrixColor() {
        return getComputedStyle(document.documentElement).getPropertyValue('--matrix-color') || '#a1cff5';
    }
    
    // Set text style with Press Start 2P font for retro gaming aesthetic
    ctx.font = fontSize + "px 'Press Start 2P', cursive";
    // Initial style will be overridden in draw function
    
    // Function to draw the matrix effect
    function draw() {
        // Black background with opacity to create fading effect
        // Using a slightly higher opacity for better visibility
        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Set text color from CSS variable
        ctx.fillStyle = getMatrixColor();
        
        // Loop over each column
        for (let i = 0; i < drops.length; i++) {
            // Select a random character
            const text = charArray[Math.floor(Math.random() * charArray.length)];
            
            // Draw the character
            ctx.fillText(text, i * fontSize, drops[i] * fontSize);
            
            // Send the drop back to the top after it reaches the bottom
            // Add randomness to the reset to make it look more natural
            if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                drops[i] = 0;
            }
            
            // Increment y coordinate for next draw
            drops[i]++;
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        // Recalculate columns
        const newColumns = Math.floor(canvas.width / fontSize);
        
        // Adjust drops array
        if (newColumns > columns) {
            for (let i = columns; i < newColumns; i++) {
                drops[i] = Math.random() * -100;
            }
        }
        
        // Update font to Press Start 2P
        ctx.font = fontSize + "px 'Press Start 2P', cursive";
    });
    
    // Start animation with faster refresh rate for smoother effect
    setInterval(draw, 50);
});
