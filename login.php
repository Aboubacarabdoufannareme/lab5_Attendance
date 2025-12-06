<?php
session_start();
header("Content-Type: application/json");

// Database connection
require_once "connect.php";

// Helper function to send JSON
function sendResponse($success, $message = "", $extra = []) {
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit();
}

// Check POST data
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    sendResponse(false, "Missing required fields.");
}

$username = trim($_POST['username']);
$password = trim($_POST['password']);

// Validation
if ($username === "" || $password === "") {
    sendResponse(false, "All fields are required.");
}

// Prepare SQL - include role
$sql = "SELECT id, username, password, role FROM users1 WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

// Check if user exists
if ($result->num_rows === 0) {
    sendResponse(false, "Invalid username or password.");
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user["password"])) {
    sendResponse(false, "Invalid username or password.");
}

// SUCCESS → create session variables
$_SESSION["user_id"] = $user["id"];
$_SESSION["username"] = $user["username"];
$_SESSION["role"] = $user["role"];

// Return JSON including role
sendResponse(true, "Login successful!", [
    "username" => $user["username"],
    "user_id" => $user["id"],
    "role" => $user["role"]
]);
?>