<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "data" => [],
    "message" => ""
];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $faculty_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT id, course_code, course_name, description, created_at 
        FROM courses 
        WHERE faculty_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    
    $response["success"] = true;
    $response["data"] = $courses;
    $response["message"] = "Courses fetched successfully";
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>