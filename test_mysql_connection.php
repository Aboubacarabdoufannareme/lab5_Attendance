<?php
/**
 * Quick MySQL Connection Test
 * This will help you find the right password
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MySQL Connection Test</h2>";
echo "<pre>";

$host = 'localhost';
$username = 'root';

// Test 1: Try with empty password (most common for local development)
echo "Test 1: Trying connection with EMPTY password...\n";
$conn1 = @new mysqli($host, $username, '');

if ($conn1->connect_error) {
    echo "✗ Failed: " . $conn1->connect_error . "\n\n";
    $conn1->close();
} else {
    echo "✓ SUCCESS! Empty password works.\n";
    echo "Your env/connect.env should have: password=\n\n";
    $conn1->close();
    exit;
}

// Test 2: Try with common passwords
$commonPasswords = ['root', 'password', 'admin', '123456', ''];

echo "Test 2: Trying common passwords...\n";
foreach ($commonPasswords as $pwd) {
    if ($pwd === '') continue; // Already tested
    
    echo "  Trying password: '" . ($pwd ?: '(empty)') . "'...\n";
    $conn = @new mysqli($host, $username, $pwd);
    
    if (!$conn->connect_error) {
        echo "✓ SUCCESS! Password works: '" . ($pwd ?: 'empty') . "'\n";
        echo "Your env/connect.env should have: password=$pwd\n\n";
        $conn->close();
        exit;
    }
    $conn->close();
}

echo "✗ None of the common passwords worked.\n\n";

echo "===========================================\n";
echo "Next Steps:\n";
echo "===========================================\n";
echo "1. Check your MySQL/XAMPP/WAMP configuration\n";
echo "2. If you have phpMyAdmin, try logging in there to check credentials\n";
echo "3. You may need to reset your MySQL root password\n";
echo "4. Or create a new MySQL user with proper permissions\n\n";

echo "To reset MySQL root password on Windows/XAMPP:\n";
echo "  - Open XAMPP Control Panel\n";
echo "  - Stop MySQL\n";
echo "  - Check XAMPP documentation for password reset\n";
echo "</pre>";
?>

