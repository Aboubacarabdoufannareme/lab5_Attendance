<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "message" => "",
    "course" => null
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $student_id = $_SESSION['user_id'];
    
    if ($course_id <= 0) {
        $response["message"] = "Invalid course ID";
        echo json_encode($response);
        exit;
    }
    
    // Check if student is enrolled in this course
    $enrollment_check = $conn->prepare("
        SELECT 1 
        FROM enrollments 
        WHERE student_id = ? AND course_id = ? AND enrollment_status = 'active'
    ");
    $enrollment_check->bind_param("ii", $student_id, $course_id);
    $enrollment_check->execute();
    $enrollment_result = $enrollment_check->get_result();
    
    if ($enrollment_result->num_rows === 0) {
        $response["message"] = "You are not enrolled in this course";
        $enrollment_check->close();
        echo json_encode($response);
        exit;
    }
    $enrollment_check->close();
    
    // Fetch course details
    $course_stmt = $conn->prepare("
        SELECT 
            c.*,
            CONCAT(u.firstname, ' ', u.lastname) as faculty_name,
            (SELECT COUNT(*) FROM sessions WHERE course_id = c.id) as total_sessions,
            (SELECT COUNT(*) FROM sessions WHERE course_id = c.id AND session_date >= CURDATE()) as upcoming_sessions,
            (SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE course_id = c.id AND enrollment_status = 'active') as total_students
        FROM courses c
        JOIN users1 u ON c.faculty_id = u.id
        WHERE c.id = ?
    ");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    
    if ($course_result->num_rows > 0) {
        $response["course"] = $course_result->fetch_assoc();
        $response["success"] = true;
        $response["message"] = "Course details fetched successfully";
    } else {
        $response["message"] = "Course not found";
    }
    
    $course_stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Get course details error: " . $e->getMessage());
}

echo json_encode($response);
?>