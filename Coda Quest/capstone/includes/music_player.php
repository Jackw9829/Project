<?php
// Music Player Component for CodaQuest
// This file should be included in pages that need background music

// Determine which audio track to play based on the current page
$audioTrack = 'homepage.mp3'; // Default track

// Get the current script name
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;

// Set audio track based on page type
if ($isAdmin) {
    // Use the same music for all admin pages
    $audioTrack = 'admindashboard.mp3';
} else {
    // Student pages
    switch ($currentPage) {
        case 'take_quiz.php':
            $audioTrack = 'quiz.mp3';
            break;
        case 'take_challenge.php':
            // Check challenge difficulty if available
            if (isset($_GET['id'])) {
                $challenge_id = intval($_GET['id']);
                require_once dirname(__FILE__) . '/../config/db_connect.php';
                $sql = "SELECT difficulty_level FROM challenges WHERE challenge_id = ?";
                $result = executeQuery($sql, [$challenge_id]);
                
                if ($result && count($result) > 0) {
                    $difficulty = $result[0]['difficulty_level'];
                    switch ($difficulty) {
                        case 'Beginner':
                            $audioTrack = 'challengeeasy.mp3';
                            break;
                        case 'Intermediate':
                            $audioTrack = 'challengeintermediate.mp3';
                            break;
                        case 'Advanced':
                            $audioTrack = 'challengehard.mp3';
                            break;
                        default:
                            $audioTrack = 'challengeintermediate.mp3';
                    }
                } else {
                    $audioTrack = 'challengeintermediate.mp3';
                }
            } else {
                $audioTrack = 'challengeintermediate.mp3';
            }
            break;
        case 'level.php':
            $audioTrack = 'level.mp3';
            break;
        default:
            $audioTrack = 'homepage.mp3';
    }
}

// Construct a simple and reliable path to the sound files
$baseUrl = "http://{$_SERVER['HTTP_HOST']}";

// Determine if we're in the admin section
if ($isAdmin) {
    $soundUrl = $baseUrl . '/capstone/sound/' . $audioTrack;
} else {
    $soundUrl = $baseUrl . '/capstone/sound/' . $audioTrack;
}

// Set the audio path
$audioPath = $soundUrl;

// Debug information
error_log("Music Player - Audio Path: {$audioPath}");
error_log("Music Player - Current Page: {$currentPage}");
error_log("Music Player - Is Admin: " . ($isAdmin ? 'Yes' : 'No'));

?>

<!-- Background Music -->
<audio id="bgMusic" loop preload="auto">
    <source src="<?php echo $audioPath; ?>" type="audio/mpeg">
    Your browser does not support the audio element.
</audio>

<!-- Button Sound for Interactions -->
<audio id="buttonSound" preload="auto">
    <source src="<?php echo $baseUrl . '/capstone/sound/button.mp3'; ?>" type="audio/mpeg">
</audio>

<!-- Music Player Controls -->
<div id="music-player-controls" class="music-player-controls">
    <button id="toggleMusic" class="music-toggle"><i class="material-icons">music_note</i></button>
    <div class="volume-control">
        <input type="range" id="volumeSlider" min="0" max="100" value="50" class="volume-slider">
    </div>
</div>

<!-- CSS Styles for Music Player -->
<style>
    .music-player-controls {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: var(--card-bg, #f5f9ff);
        border-radius: var(--border-radius, 0px);
        padding: 8px 16px;
        display: flex;
        align-items: center;
        z-index: 1000;
        box-shadow: var(--shadow, 0 4px 0 rgba(0, 0, 0, 0.2));
        border: 2px solid var(--primary-color, #b2defd);
        transition: var(--transition, all 0.2s ease);
    }
    
    .music-toggle {
        background: none;
        border: none;
        color: var(--primary-color, #b2defd);
        cursor: pointer;
        font-size: 24px;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition, all 0.2s ease);
    }
    
    .music-toggle:hover {
        color: var(--accent-color, #a6d1f8);
        transform: scale(1.1);
    }
    
    .volume-control {
        display: flex;
        align-items: center;
        margin-left: 10px;
    }
    
    .volume-slider {
        width: 80px;
        height: 5px;
        -webkit-appearance: none;
        appearance: none;
        background: var(--text-color, #222222);
        opacity: 0.3;
        outline: none;
        border-radius: 5px;
        transition: var(--transition, all 0.2s ease);
    }
    
    .volume-slider:hover {
        opacity: 0.8;
    }
    
    .volume-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: var(--primary-color, #b2defd);
        cursor: pointer;
        border: 2px solid var(--card-bg, #f5f9ff);
    }
    
    .volume-slider::-moz-range-thumb {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: var(--primary-color, #b2defd);
        cursor: pointer;
        border: 2px solid var(--card-bg, #f5f9ff);
    }
    
    /* Dark theme support */
    .dark-theme .music-player-controls {
        background-color: rgba(40, 40, 40, 0.9);
    }
</style>

<!-- Background Music Script -->
<script>
    console.log('Music player script loaded');
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing music player');
        const bgMusic = document.getElementById('bgMusic');
        const buttonSound = document.getElementById('buttonSound');
        let isPlaying = false;
        let currentTime = 0;
        
        // Log audio element details
        console.log('Audio element:', bgMusic);
        console.log('Audio source:', bgMusic.querySelector('source').src);

        // Add a global click handler for all buttons and clickable elements
        document.addEventListener('click', function(e) {
            // Get the clicked element and all its parent elements up to the document
            let element = e.target;
            let elementsToCheck = [element];
            while (element.parentElement) {
                element = element.parentElement;
                elementsToCheck.push(element);
            }
            
            // Check if any of the elements in the path is a button or clickable element
            const isClickable = elementsToCheck.some(el => {
                return (
                    el.tagName === 'BUTTON' || 
                    el.tagName === 'A' || 
                    (el.tagName === 'INPUT' && (el.type === 'button' || el.type === 'submit' || el.type === 'checkbox' || el.type === 'radio')) ||
                    el.classList.contains('btn') ||
                    el.classList.contains('action-btn') ||
                    el.classList.contains('nav-link') ||
                    el.classList.contains('dropdown-toggle') ||
                    el.classList.contains('card-clickable') ||
                    el.getAttribute('role') === 'button' ||
                    el.hasAttribute('onclick') ||
                    el.hasAttribute('data-toggle') ||
                    el.hasAttribute('data-target')
                );
            });
            
            // If it's a clickable element and not part of the music player controls
            if (isClickable && !elementsToCheck.some(el => el.closest('#music-player-controls'))) {
                // Find the actual clickable element (could be a child of it)
                const clickableElement = elementsToCheck.find(el => el.tagName === 'A');
                
                // If it's a link and not opening in a new tab/window
                if (clickableElement && clickableElement.tagName === 'A' && 
                    !clickableElement.getAttribute('target') && 
                    clickableElement.getAttribute('href') && 
                    !clickableElement.getAttribute('href').startsWith('#') && 
                    !clickableElement.getAttribute('href').startsWith('javascript:')) {
                    
                    // Prevent the default navigation
                    e.preventDefault();
                    
                    // Get the URL to navigate to
                    const href = clickableElement.getAttribute('href');
                    
                    // Play button sound
                    buttonSound.currentTime = 0; // Reset to start
                    buttonSound.play()
                        .then(() => {
                            // After sound plays (or immediately if it fails), navigate to the URL
                            setTimeout(() => {
                                window.location.href = href;
                            }, 100); // Small delay to ensure sound plays
                        })
                        .catch(err => {
                            console.log("Couldn't play button sound: " + err);
                            // Navigate even if sound fails
                            window.location.href = href;
                        });
                } else {
                    // For non-links, just play the sound without delaying
                    buttonSound.currentTime = 0; // Reset to start
                    buttonSound.play().catch(err => console.log("Couldn't play button sound: " + err));
                }
            }
        }, true); // Use capture phase to ensure this runs before other handlers
        
        // For form submissions, also play the sound
        document.addEventListener('submit', function(e) {
            // Don't play sound if it's from the music player controls
            if (e.target.closest('#music-player-controls')) return;
            
            // Prevent the default form submission
            e.preventDefault();
            
            // Get the form
            const form = e.target;
            
            // Play button sound for form submissions
            buttonSound.currentTime = 0; // Reset to start
            buttonSound.play()
                .then(() => {
                    // After sound plays, submit the form
                    setTimeout(() => {
                        // If the form has an onsubmit handler that returns false, we should respect that
                        if (form.onsubmit && typeof form.onsubmit === 'function') {
                            const result = form.onsubmit();
                            if (result === false) return;
                        }
                        
                        // Check if the form is using AJAX (has event listeners)
                        const hasSubmitListeners = form._events && form._events.submit && form._events.submit.length > 0;
                        
                        // If not using AJAX, submit the form normally
                        if (!hasSubmitListeners) {
                            form.submit();
                        }
                    }, 100); // Small delay to ensure sound plays
                })
                .catch(err => {
                    console.log("Couldn't play button sound on form submit: " + err);
                    // Submit the form even if sound fails
                    form.submit();
                });
        }, true);
        
        // For any element with onclick attribute, ensure it has sound
        function addSoundToInlineHandlers() {
            // Find all elements with onclick attribute
            const elementsWithOnclick = document.querySelectorAll('[onclick]');
            elementsWithOnclick.forEach(element => {
                if (!element.hasAttribute('data-sound-applied')) {
                    element.setAttribute('data-sound-applied', 'true');
                    const originalOnclick = element.getAttribute('onclick');
                    element.setAttribute('onclick', `document.getElementById('buttonSound').play().catch(e => {}); ${originalOnclick}`);
                }
            });
        }
        
        // Run initially
        addSoundToInlineHandlers();
        
        // Set up a mutation observer to catch dynamically added elements
        const bodyObserver = new MutationObserver(function(mutations) {
            addSoundToInlineHandlers();
        });
        
        // Start observing the document body for DOM changes
        bodyObserver.observe(document.body, { 
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['onclick']
        });

        // Use localStorage instead of sessionStorage to persist across sessions
        // Initialize audio state management
        const audioState = {
            initialize: function() {
                // Set up event listeners for audio events
                bgMusic.addEventListener('timeupdate', function() {
                    // Save current playback position every 3 seconds
                    if (Math.abs(bgMusic.currentTime - currentTime) > 3) {
                        currentTime = bgMusic.currentTime;
                        localStorage.setItem('bgMusicCurrentTime', currentTime);
                    }
                });
                
                // Handle page visibility changes
                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'hidden') {
                        // Page is hidden, save state
                        this.saveState();
                    } else {
                        // Page is visible again, sync state
                        this.syncState();
                    }
                }.bind(this));
                
                // Load saved state
                this.loadState();
            },
            
            saveState: function() {
                // Save current playback state
                localStorage.setItem('bgMusicIsPlaying', isPlaying);
                localStorage.setItem('bgMusicCurrentTime', bgMusic.currentTime);
            },
            
            loadState: function() {
                // Get saved playback position
                const savedTime = localStorage.getItem('bgMusicCurrentTime');
                if (savedTime !== null) {
                    bgMusic.currentTime = parseFloat(savedTime);
                }
                
                // Get saved volume
                const savedVolume = localStorage.getItem('bgMusicVolume');
                if (savedVolume !== null) {
                    bgMusic.volume = parseInt(savedVolume) / 100;
                } else {
                    // Default volume (50%)
                    bgMusic.volume = 0.5;
                }
                
                // Check if music was playing
                const wasPlaying = localStorage.getItem('bgMusicIsPlaying') === 'true';
                if (wasPlaying) {
                    // Try to resume playback (may require user interaction)
                    this.playAudio();
                }
            },
            
            syncState: function() {
                // Synchronize audio state with saved state
                const wasPlaying = localStorage.getItem('bgMusicIsPlaying') === 'true';
                const savedTime = localStorage.getItem('bgMusicCurrentTime');
                
                if (savedTime !== null) {
                    // Only update time if the difference is significant
                    if (Math.abs(bgMusic.currentTime - parseFloat(savedTime)) > 5) {
                        bgMusic.currentTime = parseFloat(savedTime);
                    }
                }
                
                if (wasPlaying && !isPlaying) {
                    this.playAudio();
                } else if (!wasPlaying && isPlaying) {
                    this.pauseAudio();
                }
            },
            
            playAudio: function() {
                try {
                    const playPromise = bgMusic.play();
                    
                    if (playPromise !== undefined) {
                        playPromise.then(_ => {
                            // Playback started successfully
                            isPlaying = true;
                            this.saveState();
                        })
                        .catch(error => {
                            // Auto-play prevented by browser
                            console.log("Audio playback prevented by browser: " + error);
                            isPlaying = false;
                            
                            // Add event listener for first user interaction
                            const startAudio = () => {
                                bgMusic.play().then(() => {
                                    isPlaying = true;
                                    this.saveState();
                                }).catch(e => console.log("Still couldn't play audio: " + e));
                                
                                // Remove event listeners after first interaction
                                document.removeEventListener('click', startAudio);
                                document.removeEventListener('keydown', startAudio);
                            };
                            
                            // Add event listeners for user interaction
                            document.addEventListener('click', startAudio);
                            document.addEventListener('keydown', startAudio);
                        });
                    }
                } catch (e) {
                    console.error("Error playing audio: " + e);
                }
            },
            
            pauseAudio: function() {
                bgMusic.pause();
                isPlaying = false;
                this.saveState();
            },
            
            togglePlayPause: function() {
                if (isPlaying) {
                    this.pauseAudio();
                } else {
                    this.playAudio();
                }
            },
            
            updateVolume: function(value) {
                bgMusic.volume = value / 100;
                localStorage.setItem('bgMusicVolume', value);
            }
        };
        
        // Save state before unloading the page
        window.addEventListener('beforeunload', function() {
            audioState.saveState();
        });
        
        // Initialize audio state management
        audioState.initialize();
        
        // Set up UI controls
        const toggleButton = document.getElementById('toggleMusic');
        const volumeSlider = document.getElementById('volumeSlider');
        
        // Update toggle button icon based on play state
        function updateToggleIcon() {
            // When music is playing, show music_note (indicating you can click to mute)
            // When music is not playing, show music_off (indicating you can click to play)
            toggleButton.innerHTML = isPlaying ? '<i class="material-icons">music_note</i>' : '<i class="material-icons">music_off</i>';
        }
        
        // Initialize volume slider value from localStorage
        const savedVolume = localStorage.getItem('bgMusicVolume');
        if (savedVolume !== null) {
            volumeSlider.value = savedVolume;
        }
        
        // Toggle button click event
        toggleButton.addEventListener('click', function() {
            audioState.togglePlayPause();
            updateToggleIcon();
        });
        
        // Volume slider change event
        volumeSlider.addEventListener('input', function() {
            audioState.updateVolume(this.value);
        });
        
        // Update toggle icon on audio play/pause events
        bgMusic.addEventListener('play', function() {
            isPlaying = true;
            updateToggleIcon();
        });
        
        bgMusic.addEventListener('pause', function() {
            isPlaying = false;
            updateToggleIcon();
        });
        
        // Initial icon update
        updateToggleIcon();
    });
</script>
