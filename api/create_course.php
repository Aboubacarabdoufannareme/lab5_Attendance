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
    
    // Check authorization - only faculty can create courses
    if ($_SESSION['role'] !== 'faculty') {
        $response["message"] = "Only faculty members can create courses.";
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
    
    $courseCode = trim($data['course_code'] ?? '');
    $courseName = trim($data['course_name'] ?? '');
    $description = trim($data['description'] ?? '');
    $facultyId = $_SESSION['user_id'];
    
    // Validation
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
    
    // Check if course code already exists
    $checkStmt = $conn->prepare("SELECT id FROM courses WHERE course_code = ?");
    if (!$checkStmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("s", $courseCode);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $response["message"] = "A course with this code already exists.";
        echo json_encode($response);
        exit;
    }
    
    // Insert course into database
    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, faculty_id) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssi", $courseCode, $courseName, $description, $facultyId);
    
    if ($stmt->execute()) {
        $courseId = $conn->insert_id;
        $response["success"] = true;
        $response["message"] = "Course created successfully.";
        $response["course_id"] = $courseId;
    } else {
        throw new Exception("Failed to create course: " . $stmt->error);
    }
    
    $stmt->close();
    $checkStmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Create course error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Create course fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>
