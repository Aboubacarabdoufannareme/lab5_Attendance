<?php

// signup.php - ADD THIS DEBUG CODE AT THE TOP
header("Content-Type: application/json");

$response = [
    "success" => false,
    "message" => "",
    "debug" => []
];

try {
    // Debug: Check current directory and env file
    $currentDir = __DIR__;
    $envPath = __DIR__ . "/env/connect.env";
    
    $response["debug"] = [
        "current_directory" => $currentDir,
        "env_file_path" => $envPath,
        "env_file_exists" => file_exists($envPath),
        "env_folder_exists" => file_exists(__DIR__ . "/env"),
        "env_folder_contents" => is_dir(__DIR__ . "/env") ? scandir(__DIR__ . "/env") : "Folder not found"
    ];
    
    // Include DB connection
    require_once __DIR__ . "/connect.php";
    
    // Rest of your existing code...
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
}

echo json_encode($response);
exit;


// Error handler to catch fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $errstr,
        "error" => "php_error"
    ]);
    exit;
});

header("Content-Type: application/json");

// Response structure
$response = [
    "success" => false,
    "message" => ""
];

try {
    // Include DB connection
    require_once __DIR__ . "/connect.php";
    
    // Receive form data (FormData from JavaScript)
    $firstName = trim($_POST["fname"] ?? "");
    $lastName  = trim($_POST["lname"] ?? "");
    $username  = trim($_POST["username"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $password  = trim($_POST["password"] ?? "");
    $role      = trim($_POST["role"] ?? "");
    
    // -------------------- SERVER-SIDE VALIDATION ----------------------
    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        $response["message"] = "All fields are required.";
        echo json_encode($response);
        exit;
    }
    
    // Validate role
    $validRoles = ['student', 'faculty', 'faculty_intern'];
    if (empty($role) || !in_array($role, $validRoles)) {
        $response["message"] = "Please select a valid role.";
        echo json_encode($response);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["message"] = "Invalid email format.";
        echo json_encode($response);
        exit;
    }
    
    if (strlen($password) < 6) {
        $response["message"] = "Password must be at least 6 characters.";
        echo json_encode($response);
        exit;
    }
    
    // Check if username already exists
    $checkUser = $conn->prepare("SELECT id FROM users1 WHERE username = ?");
    if (!$checkUser) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $checkUser->bind_param("s", $username);
    $checkUser->execute();
    $checkUser->store_result();
    
    if ($checkUser->num_rows > 0) {
        $response["message"] = "Username already taken.";
        echo json_encode($response);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into DB with the selected role
    $stmt = $conn->prepare("
        INSERT INTO users1 (firstname, lastname, username, email, password, role)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Database insert preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssssss", $firstName, $lastName, $username, $email, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Registration successful!";
    } else {
        $response["message"] = "Database insert failed: " . $stmt->error;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    echo json_encode($response);
} catch (Error $e) {
    $response["message"] = "Fatal error: " . $e->getMessage();
    echo json_encode($response);
}
?>
