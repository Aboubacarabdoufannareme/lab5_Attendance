<?php
/**
 * Database Connection Test Script
 * Run this to diagnose connection issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Diagnostic</h2>";
echo "<pre>";

// Step 1: Check if env file exists
$envPath = __DIR__ . "/env/connect.env";
echo "Step 1: Checking environment file...\n";
if (file_exists($envPath)) {
    echo "✓ Environment file exists at: $envPath\n";
} else {
    echo "✗ ERROR: Environment file NOT found at: $envPath\n";
    echo "Please create env/connect.env file.\n";
    exit;
}

// Step 2: Parse env file
echo "\nStep 2: Parsing environment file...\n";
$env = parse_ini_file($envPath);

if (!$env) {
    echo "✗ ERROR: Could not parse environment file.\n";
    echo "Please check the format of env/connect.env\n";
    exit;
}

echo "✓ Environment file parsed successfully\n";
echo "   Host: " . ($env['host'] ?? 'NOT SET') . "\n";
echo "   Username: " . ($env['username'] ?? 'NOT SET') . "\n";
echo "   Password: " . (empty($env['password']) ? '(empty)' : '(set)') . "\n";
echo "   Database: " . ($env['database'] ?? 'NOT SET') . "\n";

// Step 3: Check MySQL extension
echo "\nStep 3: Checking MySQL extension...\n";
if (extension_loaded('mysqli')) {
    echo "✓ MySQLi extension is loaded\n";
} else {
    echo "✗ ERROR: MySQLi extension is NOT loaded\n";
    echo "Please enable mysqli extension in php.ini\n";
    exit;
}

// Step 4: Test connection to MySQL server (without database)
echo "\nStep 4: Testing connection to MySQL server...\n";
$host = $env['host'] ?? 'localhost';
$username = $env['username'] ?? 'root';
$password = $env['password'] ?? '';

$testConn = @new mysqli($host, $username, $password);

if ($testConn->connect_error) {
    echo "✗ ERROR: Cannot connect to MySQL server\n";
    echo "   Error: " . $testConn->connect_error . "\n";
    echo "\nPossible issues:\n";
    echo "- MySQL server is not running\n";
    echo "- Wrong username or password in env/connect.env\n";
    echo "- Wrong host address\n";
    exit;
}

echo "✓ Successfully connected to MySQL server\n";

// Step 5: Check if database exists
echo "\nStep 5: Checking if database exists...\n";
$database = $env['database'] ?? 'attendance_system';

$result = $testConn->query("SHOW DATABASES LIKE '$database'");
if ($result && $result->num_rows > 0) {
    echo "✓ Database '$database' exists\n";
} else {
    echo "✗ WARNING: Database '$database' does NOT exist\n";
    echo "You need to create it first.\n";
    echo "\nTo create the database, run:\n";
    echo "  CREATE DATABASE $database;\n";
    echo "\nOr use the setup_database.php script.\n";
}

$testConn->close();

// Step 6: Test connection with database
echo "\nStep 6: Testing connection to database...\n";
$conn = @new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo "✗ ERROR: Cannot connect to database\n";
    echo "   Error: " . $conn->connect_error . "\n";
    
    if (strpos($conn->connect_error, "Unknown database") !== false) {
        echo "\nThe database '$database' doesn't exist yet.\n";
        echo "Please create it first using setup_database.php or manually.\n";
    }
    exit;
}

echo "✓ Successfully connected to database '$database'\n";

// Step 7: Check if tables exist
echo "\nStep 7: Checking database tables...\n";
$tables = ['users', 'courses', 'enrollments', 'auditors', 'sessions', 'attendance', 'feedback'];
$existingTables = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
        $existingTables[] = $table;
    } else {
        echo "✗ Table '$table' NOT found\n";
    }
}

if (empty($existingTables)) {
    echo "\n⚠ WARNING: No tables found in the database.\n";
    echo "You need to import database.sql or run setup_database.php\n";
} elseif (count($existingTables) < count($tables)) {
    echo "\n⚠ WARNING: Some tables are missing.\n";
    echo "You should import database.sql to create all tables.\n";
} else {
    echo "\n✓ All required tables exist!\n";
}

// Step 8: Test query
echo "\nStep 8: Testing database query...\n";
if (in_array('users', $existingTables)) {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ Query successful - Found " . $row['count'] . " users in database\n";
    } else {
        echo "✗ ERROR: Query failed - " . $conn->error . "\n";
    }
} else {
    echo "⚠ Skipped: users table doesn't exist\n";
}

$conn->close();

echo "\n===========================================\n";
echo "Diagnostic complete!\n";
echo "===========================================\n";
echo "</pre>";
?>

