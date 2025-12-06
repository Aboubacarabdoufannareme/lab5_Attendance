<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = ["success" => false, "data" => [], "message" => ""];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty_intern') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    // For interns, show all active courses or those assigned to them
    $stmt = $conn->prepare("
        SELECT c.id, c.course_code, c.course_name, c.description,
               u.firstname as faculty_firstname, u.lastname as faculty_lastname
        FROM courses c
        JOIN users1 u ON c.faculty_id = u.id
        WHERE c.status = 'active'
        ORDER BY c.course_code
    ");
    
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