<?php
// Error handling function for JSON responses
function sendJsonError($message) {
    // Check if we're in a JSON context (Content-Type header already set)
    if (!headers_sent()) {
        $headers = headers_list();
        $isJsonContext = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                $isJsonContext = true;
                break;
            }
        }
        
        if ($isJsonContext) {
            header("Content-Type: application/json");
            echo json_encode([
                "success" => false,
                "message" => $message,
                "error" => "database_connection_error"
            ]);
            exit();
        }
    }
    // Fallback to plain text error
    die("Error: " . $message);
}

// Load environment file
$envPath = __DIR__ . "/../env/connect.env";

if (!file_exists($envPath)) {
    sendJsonError("Environment file not found. Please create env/connect.env file. See connect.env.example for reference.");
}

$env = parse_ini_file($envPath);

if (!$env) {
    sendJsonError("Could not parse environment file. Please check env/connect.env format.");
}

// Get database credentials
$host = $env['host'] ?? 'localhost';
$username = $env['username'] ?? 'fannareme.abdou';
$password = $env['password'] ?? 'fa889033';
$database = $env['database'] ?? 'webtech_2025A_fannareme_abdou';

if (empty($database)) {
    sendJsonError("Database name not specified in env/connect.env file.");
}

// Create database connection using standard key names
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    sendJsonError("Database connection failed: " . $conn->connect_error . ". Please check your database credentials in env/connect.env");
}
?>

 
