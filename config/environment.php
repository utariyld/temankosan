<?php
/**
 * Environment Configuration
 * Sesuaikan dengan environment development Anda
 */

// ========================================
// ENVIRONMENT SETTINGS
// ========================================

// Development mode (set to false for production)
define('DEBUG', true);

// Error reporting untuk development
if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ========================================
// SERVER CONFIGURATION
// ========================================

// Base URL - UBAH sesuai dengan setup localhost Anda
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = dirname($_SERVER['SCRIPT_NAME']);

define('BASE_URL', $protocol . '://' . $host . $path);

// Path configurations
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// ========================================
// XAMPP SPECIFIC SETTINGS
// ========================================
if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false && 
    strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'xampp') !== false) {
    
    // XAMPP detected
    define('IS_XAMPP', true);
    define('XAMPP_PATH', 'C:/xampp'); // UBAH jika XAMPP di lokasi lain
    
} else {
    define('IS_XAMPP', false);
}

// ========================================
// MAMP SPECIFIC SETTINGS (Mac)
// ========================================
if (strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'MAMP') !== false) {
    
    // MAMP detected
    define('IS_MAMP', true);
    define('MAMP_PATH', '/Applications/MAMP'); // Default MAMP path
    
} else {
    define('IS_MAMP', false);
}

// ========================================
// WAMP SPECIFIC SETTINGS (Windows)
// ========================================
if (strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'wamp') !== false) {
    
    // WAMP detected
    define('IS_WAMP', true);
    define('WAMP_PATH', 'C:/wamp64'); // UBAH jika WAMP di lokasi lain
    
} else {
    define('IS_WAMP', false);
}

// ========================================
// AUTO-DETECT DATABASE SETTINGS
// ========================================
function getDefaultDatabaseConfig() {
    $config = [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'port' => 3306
    ];
    
    // MAMP has different defaults
    if (defined('IS_MAMP') && IS_MAMP) {
        $config['password'] = 'root';
        $config['port'] = 8889;
    }
    
    // Check for custom MySQL port
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
        // Might be using custom ports
    }
    
    return $config;
}

// ========================================
// TIMEZONE SETTINGS
// ========================================
date_default_timezone_set('Asia/Jakarta'); // UBAH sesuai timezone Anda

// ========================================
// SESSION SETTINGS
// ========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Get environment info for debugging
 */
function getEnvironmentInfo() {
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'base_url' => BASE_URL,
        'is_xampp' => defined('IS_XAMPP') ? IS_XAMPP : false,
        'is_mamp' => defined('IS_MAMP') ? IS_MAMP : false,
        'is_wamp' => defined('IS_WAMP') ? IS_WAMP : false,
        'debug_mode' => DEBUG,
        'timezone' => date_default_timezone_get()
    ];
}

/**
 * Display environment info (for debugging)
 */
function displayEnvironmentInfo() {
    if (!DEBUG) return;
    
    $info = getEnvironmentInfo();
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba;'>";
    echo "<h4>Environment Info (Debug Mode)</h4>";
    echo "<ul>";
    foreach ($info as $key => $value) {
        echo "<li><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> " . 
             (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}
?>
