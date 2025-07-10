<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Search.php Dependencies</h2>";

try {
    // Test database connection
    require_once 'config/database.php';
    $db = Database::getInstance();
    echo "✅ Database connection successful<br>";
    
    // Test functions include
    require_once 'includes/functions.php';
    echo "✅ Functions included successfully<br>";
    
    // Test Kos model
    require_once 'models/Kos.php';
    $kosModel = new Kos();
    echo "✅ Kos model created successfully<br>";
    
    // Test the search function
    $kosData = get_search_kos();
    echo "✅ Search function executed - Found " . count($kosData) . " records<br>";
    
    echo "<h3>✅ All tests passed! search.php should work now.</h3>";
    echo "<p><a href='search.php'>Test search.php</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Please check the error and fix accordingly.</p>";
}
?>