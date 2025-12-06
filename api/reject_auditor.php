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
    
    // Check authorization - only faculty interns can reject auditor requests
    if ($_SESSION['role'] !== 'faculty_intern') {
        $response["message"] = "Only faculty interns can reject auditor requests.";
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
    
    $studentName = trim($data['student_name'] ?? '');
    $courseName = trim($data['course_name'] ?? '');
    $reviewedBy = $_SESSION['user_id'];
    
    // Validation
    if (empty($studentName)) {
        $response["message"] = "Student name is required.";
        echo json_encode($response);
        exit;
    }
    
    if (empty($courseName)) {
        $response["message"] = "Course name is required.";
        echo json_encode($response);
        exit;
    }
    
    // Find the student by name
    $studentParts = explode(' ', $studentName, 2);
    $firstName = $studentParts[0];
    $lastName = isset($studentParts[1]) ? $studentParts[1] : '';
    
    $studentStmt = $conn->prepare("SELECT id FROM users1 WHERE firstname = ? AND lastname = ? AND role = 'student'");
    if (!$studentStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $studentStmt->bind_param("ss", $firstName, $lastName);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    
    if ($studentResult->num_rows === 0) {
        $response["message"] = "Student not found.";
        $studentStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $student = $studentResult->fetch_assoc();
    $studentId = $student['id'];
    $studentStmt->close();
    
    // Find the course
    $courseStmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
    if (!$courseStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $courseStmt->bind_param("s", $courseName);
    $courseStmt->execute();
    $courseResult = $courseStmt->get_result();
    
    if ($courseResult->num_rows === 0) {
        $response["message"] = "Course not found.";
        $courseStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $course = $courseResult->fetch_assoc();
    $courseId = $course['id'];
    $courseStmt->close();
    
    // Find the auditor request
    $auditorStmt = $conn->prepare("SELECT id, status FROM auditors WHERE student_id = ? AND course_id = ?");
    if (!$auditorStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $auditorStmt->bind_param("ii", $studentId, $courseId);
    $auditorStmt->execute();
    $auditorResult = $auditorStmt->get_result();
    
    if ($auditorResult->num_rows === 0) {
        $response["message"] = "Auditor request not found.";
        $auditorStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $auditor = $auditorResult->fetch_assoc();
    
    if ($auditor['status'] === 'rejected') {
        $response["message"] = "This auditor request is already rejected.";
        $auditorStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $auditorId = $auditor['id'];
    $auditorStmt->close();
    
    // Update auditor request status to rejected
    $updateStmt = $conn->prepare("UPDATE auditors SET status = 'rejected', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    
    if (!$updateStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("ii", $reviewedBy, $auditorId);
    
    if ($updateStmt->execute()) {
        if ($updateStmt->affected_rows > 0) {
            $response["success"] = true;
            $response["message"] = "Auditor request rejected.";
        } else {
            $response["message"] = "No changes were made.";
        }
    } else {
        throw new Exception("Failed to reject auditor request: " . $updateStmt->error);
    }
    
    $updateStmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Reject auditor error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Reject auditor fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>
