<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../connect.php";

echo "=== DEBUG START ===\n";

// Check session
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Session role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";

// Check GET parameters
echo "GET id parameter: " . ($_GET['id'] ?? 'NOT SET') . "\n";

// Check database connection
if (!$conn) {
    echo "Database connection FAILED\n";
    echo "Connection error: " . ($conn->connect_error ?? 'Unknown error') . "\n";
} else {
    echo "Database connection OK\n";
    
    // Test a simple query
    $test_query = $conn->query("SELECT 1 as test");
    if ($test_query) {
        echo "Simple query test: OK\n";
    } else {
        echo "Simple query test: FAILED - " . $conn->error . "\n";
    }
}

echo "=== DEBUG END ===\n";
?>