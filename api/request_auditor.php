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
    
    // Check authorization - only students can request to audit
    if ($_SESSION['role'] !== 'student') {
        $response["message"] = "Only students can request to audit courses.";
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
    
    $courseName = trim($data['course_name'] ?? '');
    $studentId = $_SESSION['user_id'];
    
    // Validation
    if (empty($courseName)) {
        $response["message"] = "Course name is required.";
        echo json_encode($response);
        exit;
    }
    
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
    
    // Check if student is already enrolled
    $enrollmentCheck = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    if ($enrollmentCheck) {
        $enrollmentCheck->bind_param("ii", $studentId, $courseId);
        $enrollmentCheck->execute();
        $enrollmentResult = $enrollmentCheck->get_result();
        
        if ($enrollmentResult->num_rows > 0) {
            $response["message"] = "You are already enrolled in this course.";
            $enrollmentCheck->close();
            echo json_encode($response);
            exit;
        }
        $enrollmentCheck->close();
    }
    
    // Check if auditor request already exists
    $auditorCheck = $conn->prepare("SELECT id, status FROM auditors WHERE student_id = ? AND course_id = ?");
    if (!$auditorCheck) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $auditorCheck->bind_param("ii", $studentId, $courseId);
    $auditorCheck->execute();
    $auditorResult = $auditorCheck->get_result();
    
    if ($auditorResult->num_rows > 0) {
        $auditor = $auditorResult->fetch_assoc();
        if ($auditor['status'] === 'pending') {
            $response["message"] = "You already have a pending auditor request for this course.";
        } elseif ($auditor['status'] === 'approved') {
            $response["message"] = "You are already an approved auditor for this course.";
        } elseif ($auditor['status'] === 'rejected') {
            $response["message"] = "Your auditor request for this course was rejected. Please contact the faculty.";
        }
        $auditorCheck->close();
        echo json_encode($response);
        exit;
    }
    
    $auditorCheck->close();
    
    // Insert auditor request
    $stmt = $conn->prepare("INSERT INTO auditors (student_id, course_id, status) VALUES (?, ?, 'pending')");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $studentId, $courseId);
    
    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Auditor request submitted successfully. You will be notified once it's reviewed.";
        $response["auditor_id"] = $conn->insert_id;
    } else {
        throw new Exception("Failed to submit auditor request: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Request auditor error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Request auditor fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>
