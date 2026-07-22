<?php
/**
 * Google OAuth 2.0 & Docs API Configuration
 * 
 * To set up:
 * 1. Visit https://console.cloud.google.com/
 * 2. Create a new project
 * 3. Enable: Google Drive API, Google Docs API, Google Meet API
 * 4. Create OAuth 2.0 credentials (Web application)
 * 5. Add authorized redirect URI: https://yourdomain.com/api/google/callback.php
 * 6. Download credentials JSON and extract values below
 */

// Google Cloud Project credentials
define('GOOGLE_CLIENT_ID',     ''); // ← Paste your Client ID
define('GOOGLE_CLIENT_SECRET', ''); // ← Paste your Client Secret
define('GOOGLE_REDIRECT_URI',  'https://pallicare.local/api/google/callback.php'); // ← Update domain

// Google Docs template
define('GOOGLE_PRESCRIPTION_TEMPLATE_ID', ''); // ← Optional: Use template doc for new prescriptions

// Session storage keys
define('GOOGLE_TOKEN_SESSION_KEY', 'google_oauth_token');
define('GOOGLE_REFRESH_TOKEN_KEY', 'google_refresh_token');

// Scopes required by the app
define('GOOGLE_OAUTH_SCOPES', [
    'https://www.googleapis.com/auth/drive',      // Read/write Google Drive files
    'https://www.googleapis.com/auth/documents',   // Read/write Google Docs
    'https://www.googleapis.com/auth/userinfo.email', // Get user email
]);

// Google OAuth endpoints
define('GOOGLE_OAUTH_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_OAUTH_REVOKE_URL', 'https://oauth2.googleapis.com/revoke');

// API base URLs
define('GOOGLE_DRIVE_API_URL', 'https://www.googleapis.com/drive/v3');
define('GOOGLE_DOCS_API_URL', 'https://docs.googleapis.com/v1');
define('GOOGLE_MEET_API_URL', 'https://meet.googleapis.com/v2');
