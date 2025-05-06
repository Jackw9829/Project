/**
 * Title Shimmer Effect for CodaQuest
 * Creates a retro 8-bit style shimmer effect for the CodaQuest title
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find the title element
    const title = document.querySelector('.shimmer-title');
    if (!title) return;
    
    // Get the computed style to access CSS variables
    const rootStyles = getComputedStyle(document.documentElement);
    const textColor = rootStyles.getPropertyValue('--text-color').trim() || '#222222';
    
    // Define 8-bit style colors
    const retro8BitColors = [
        '#ffffff', // White
        '#cccccc', // Light gray
        '#888888', // Medium gray
        textColor   // Default text color
    ];
    
    // Get the original text and clear the title
    const originalText = title.textContent;
    title.textContent = '';
    title.style.display = 'inline-block';
    
    // Create a span for each character
    const charSpans = [];
    for (let i = 0; i < originalText.length; i++) {
        const charSpan = document.createElement('span');
        charSpan.textContent = originalText[i];
        charSpan.style.display = 'inline-block';
        charSpan.style.color = textColor;
        charSpan.style.fontWeight = 'bold';
        charSpan.style.position = 'relative';
        
        // No transitions for a more pixelated, 8-bit feel
        charSpan.style.transition = 'none';
        
        // Add the character span to the title and our array
        title.appendChild(charSpan);
        charSpans.push(charSpan);
    }
    
    // Create a style element for the 8-bit font effect
    const styleElement = document.createElement('style');
    styleElement.textContent = `
        .shimmer-title span {
            font-family: 'Press Start 2P', monospace;
            image-rendering: pixelated;
            -webkit-font-smoothing: none;
            -moz-osx-font-smoothing: none;
        }
    `;
    document.head.appendChild(styleElement);
    
    // Create the flowing shimmer effect with 8-bit style
    let currentIndex = -1;
    
    function animateNextChar() {
        // Reset all characters first for a cleaner 8-bit look
        for (let i = 0; i < charSpans.length; i++) {
            charSpans[i].style.color = textColor;
            charSpans[i].style.textShadow = 'none';
        }
        
        // Move to next character
        currentIndex = (currentIndex + 1) % (charSpans.length + 5); // +5 for pause at the end
        
        // If we're in the valid range, create the 8-bit style highlight effect
        if (currentIndex < charSpans.length) {
            // Main highlighted character
            charSpans[currentIndex].style.color = retro8BitColors[0];
            
            // Create a pixelated glow effect with adjacent characters
            if (currentIndex > 0) {
                charSpans[currentIndex - 1].style.color = retro8BitColors[1];
            }
            if (currentIndex > 1) {
                charSpans[currentIndex - 2].style.color = retro8BitColors[2];
            }
            if (currentIndex < charSpans.length - 1) {
                charSpans[currentIndex + 1].style.color = retro8BitColors[1];
            }
            if (currentIndex < charSpans.length - 2) {
                charSpans[currentIndex + 2].style.color = retro8BitColors[2];
            }
        }
        
        // Schedule the next animation with a fixed frame rate for 8-bit feel
        setTimeout(animateNextChar, 250); // 4 frames per second for retro feel
    }
    
    // Start the animation
    animateNextChar();
});
