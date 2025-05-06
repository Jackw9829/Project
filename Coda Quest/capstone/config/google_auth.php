<?php
/**
 * Google OAuth Configuration for CodaQuest
 * This file contains configuration and helper functions for Google OAuth integration
 *
 * SETUP INSTRUCTIONS:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project or select an existing one
 * 3. Navigate to "APIs & Services" > "Credentials"
 * 4. Click "Create Credentials" > "OAuth client ID"
 * 5. Select "Web application" as the application type
 * 6. Add a name for your OAuth client
 * 7. Add "http://localhost/capstone/google_callback.php" to the Authorized redirect URIs
 * 8. Click "Create"
 * 9. Copy the Client ID and Client Secret below
 */

// Define the redirect URI as a constant
define('GOOGLE_REDIRECT_URI', 'http://localhost/capstone/google_callback.php');

/**
 * Get Google OAuth credentials
 * 
 * @return array Array containing client_id and client_secret
 */
function getGoogleCredentials() {
    // Hardcoded credentials for development/testing
    // In a production environment, these should be stored securely
    // such as in environment variables or a secure configuration file
    $credentials = [
        // 'client_id' => '321895690303-2kshrntvv19v135cs551bd9bpff6cu04.apps.googleusercontent.com',
        // 'client_secret' => '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'
    ];
    
    return $credentials;
}

/**
 * Get Google login URL
 * @return string The Google login URL or empty string if credentials are not set
 */
function getGoogleLoginUrl() {
    // Create state token to prevent request forgery
    $state = bin2hex(random_bytes(16));
    
    // Store state in session for validation
    $_SESSION['google_auth_state'] = $state;
    
    // Get credentials
    $credentials = getGoogleCredentials();
    
    // Check if credentials are set
    if (empty($credentials['client_id'])) {
        // Return empty string if credentials are not set
        return '';
    }
    
    // Build authorization URL
    $params = [
        'client_id' => $credentials['client_id'],
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'state' => $state,
        'prompt' => 'select_account'
    ];
    
    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 * 
 * @param string $code The authorization code from Google
 * @return array|false The token response or false on failure
 */
function getGoogleAccessToken($code) {
    $token_url = 'https://oauth2.googleapis.com/token';
    
    // Get credentials
    $credentials = getGoogleCredentials();
    
    // Check if credentials are set
    if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
        return false;
    }
    
    $params = [
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'code' => $code,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Get user profile information from Google
 * 
 * @param string $access_token The access token from Google
 * @return array|false The user profile data or false on failure
 */
function getGoogleUserProfile($access_token) {
    $profile_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profile_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}
?>
