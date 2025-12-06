<?php
/**
 * Database Setup Script
 * 
 * This script helps you set up the database and create the necessary tables.
 * Run this once to initialize your database.
 * 
 * Usage: Open this file in your browser or run: php setup_database.php
 */

// Check if running from command line or web
$isCLI = php_sapi_name() === 'cli';

// Load connection configuration
$envPath = __DIR__ . "/env/connect.env";

if (!file_exists($envPath)) {
    die("Error: Please create env/connect.env file first. See connect.env.example for reference.\n");
}

$env = parse_ini_file($envPath);

if (!$env) {
    die("Error: Could not parse env/connect.env file.\n");
}

$host = $env['host'] ?? 'localhost';
$username = $env['username'] ?? 'root';
$password = $env['password'] ?? '';
$database = $env['database'] ?? 'attendance_system';

echo "===========================================\n";
echo "Attendance Management System - Database Setup\n";
echo "===========================================\n\n";

// Connect to MySQL server (without database)
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "✓ Connected to MySQL server\n";

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql)) {
    echo "✓ Database '$database' created or already exists\n";
} else {
    die("Error creating database: " . $conn->error . "\n");
}

// Select the database
$conn->select_db($database);
echo "✓ Selected database '$database'\n\n";

// Read and execute SQL file
$sqlFile = __DIR__ . '/database.sql';

if (!file_exists($sqlFile)) {
    die("Error: database.sql file not found!\n");
}

echo "Reading database.sql...\n";
$sqlContent = file_get_contents($sqlFile);

// Remove CREATE DATABASE and USE statements since we're already connected
$sqlContent = preg_replace('/CREATE DATABASE.*?;/i', '', $sqlContent);
$sqlContent = preg_replace('/USE.*?;/i', '', $sqlContent);

// Split by semicolon and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $sqlContent)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

echo "Creating tables...\n";

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    // Skip comments and empty lines
    $statement = trim($statement);
    if (empty($statement) || preg_match('/^--/', $statement)) {
        continue;
    }
    
    // Execute statement
    if ($conn->multi_query($statement)) {
        // Consume all results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        $successCount++;
    } else {
        // Check if it's a "table already exists" error (which is okay)
        if (strpos($conn->error, 'already exists') !== false) {
            $successCount++;
        } else {
            echo "Error: " . $conn->error . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
            $errorCount++;
        }
    }
}

echo "\n===========================================\n";
echo "Setup Complete!\n";
echo "===========================================\n";
echo "Tables created/verified: $successCount\n";
if ($errorCount > 0) {
    echo "Errors encountered: $errorCount\n";
}

// Check if tables exist
echo "\nVerifying tables...\n";
$tables = ['users', 'courses', 'enrollments', 'auditors', 'sessions', 'attendance', 'feedback'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' NOT found\n";
    }
}

echo "\n===========================================\n";
echo "Database setup completed!\n";
echo "You can now use the Attendance Management System.\n";
echo "===========================================\n";

$conn->close();
?>

