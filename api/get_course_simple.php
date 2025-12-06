<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "data" => null,
    "message" => ""
];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $facultyId = $_SESSION['user_id'];
    
    if ($courseId <= 0) {
        $response["message"] = "Invalid course ID.";
        echo json_encode($response);
        exit;
    }
    
    // Select only the basic columns that definitely exist
    $stmt = $conn->prepare("
        SELECT id, course_code, course_name, description, faculty_id, created_at
        FROM courses 
        WHERE id = ? AND faculty_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $courseId, $facultyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response["message"] = "Course not found or you don't have permission to view it.";
        $stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $course = $result->fetch_assoc();
    
    $response["success"] = true;
    $response["data"] = $course;
    $response["message"] = "Course details fetched successfully";
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Get course error: " . $e->getMessage());
}

echo json_encode($response);
?>