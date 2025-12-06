<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "message" => ""
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        $response["message"] = "You must be logged in to perform this action.";
        echo json_encode($response);
        exit;
    }
    
    // Check authorization - only faculty can delete courses
    if ($_SESSION['role'] !== 'faculty') {
        $response["message"] = "Only faculty members can delete courses.";
        echo json_encode($response);
        exit;
    }
    
    // Get and validate input data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        $response["message"] = "Invalid request data.";
        echo json_encode($response);
        exit;
    }
    
    // Use 'id' instead of 'course_id'
    $id = intval($data['id'] ?? 0); // Changed from course_id to id
    $facultyId = $_SESSION['user_id'];
    
    // Validation
    if ($id <= 0) {
        $response["message"] = "Invalid course ID.";
        echo json_encode($response);
        exit;
    }
    
    // Find the course and verify ownership
    $findStmt = $conn->prepare("SELECT id, faculty_id, course_name FROM courses WHERE id = ?");
    if (!$findStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $findStmt->bind_param("i", $id);
    $findStmt->execute();
    $courseResult = $findStmt->get_result();
    
    if ($courseResult->num_rows === 0) {
        $response["message"] = "Course not found.";
        $findStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $course = $courseResult->fetch_assoc();
    
    // Verify the faculty member owns this course
    if ($course['faculty_id'] != $facultyId) {
        $response["message"] = "You do not have permission to delete this course.";
        $findStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $findStmt->close();
    
    // Check if there are enrolled students
    $enrollmentCheck = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ? AND status = 'enrolled'");
    if ($enrollmentCheck) {
        $enrollmentCheck->bind_param("i", $id);
        $enrollmentCheck->execute();
        $enrollmentResult = $enrollmentCheck->get_result();
        $enrollmentData = $enrollmentResult->fetch_assoc();
        
        if ($enrollmentData['count'] > 0) {
            $response["message"] = "Cannot delete course with active enrollments. Please drop all students first.";
            $enrollmentCheck->close();
            echo json_encode($response);
            exit;
        }
        $enrollmentCheck->close();
    }
    
    // Delete the course
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response["success"] = true;
            $response["message"] = "Course deleted successfully.";
        } else {
            $response["message"] = "Failed to delete course. Course may not exist.";
        }
    } else {
        throw new Exception("Failed to delete course: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Delete course error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Delete course fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>