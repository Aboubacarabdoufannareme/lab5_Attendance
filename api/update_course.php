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
    
    // Check authorization - only faculty can update courses
    if ($_SESSION['role'] !== 'faculty') {
        $response["message"] = "Only faculty members can update courses.";
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
    $courseCode = trim($data['course_code'] ?? '');
    $courseName = trim($data['course_name'] ?? '');
    $description = trim($data['description'] ?? '');
    $facultyId = $_SESSION['user_id'];
    
    // Validation
    if ($id <= 0) {
        $response["message"] = "Invalid course ID.";
        echo json_encode($response);
        exit;
    }
    
    if (empty($courseCode)) {
        $response["message"] = "Course code is required.";
        echo json_encode($response);
        exit;
    }
    
    if (empty($courseName)) {
        $response["message"] = "Course name is required.";
        echo json_encode($response);
        exit;
    }
    
    if (strlen($courseCode) > 20) {
        $response["message"] = "Course code must be 20 characters or less.";
        echo json_encode($response);
        exit;
    }
    
    if (strlen($courseName) > 200) {
        $response["message"] = "Course name must be 200 characters or less.";
        echo json_encode($response);
        exit;
    }
    
    // Check if course exists and faculty owns it
    $checkStmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND faculty_id = ?");
    if (!$checkStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("ii", $id, $facultyId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $response["message"] = "Course not found or you don't have permission to update it.";
        $checkStmt->close();
        echo json_encode($response);
        exit;
    }
    $checkStmt->close();
    
    // Check if course code already exists (for another course)
    $duplicateCheck = $conn->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
    if ($duplicateCheck) {
        $duplicateCheck->bind_param("si", $courseCode, $id);
        $duplicateCheck->execute();
        $duplicateResult = $duplicateCheck->get_result();
        
        if ($duplicateResult->num_rows > 0) {
            $response["message"] = "A course with this code already exists.";
            $duplicateCheck->close();
            echo json_encode($response);
            exit;
        }
        $duplicateCheck->close();
    }
    
    // Update the course
    $stmt = $conn->prepare("
        UPDATE courses 
        SET course_code = ?, course_name = ?, description = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND faculty_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssii", $courseCode, $courseName, $description, $id, $facultyId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response["success"] = true;
            $response["message"] = "Course updated successfully.";
        } else {
            $response["message"] = "No changes were made to the course.";
        }
    } else {
        throw new Exception("Failed to update course: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Update course error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Update course fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>