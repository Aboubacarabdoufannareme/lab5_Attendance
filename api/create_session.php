<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "message" => "",
    "session_id" => null
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        $response["message"] = "You must be logged in to perform this action.";
        echo json_encode($response);
        exit;
    }
    
    // Check authorization - only faculty can create sessions
    if ($_SESSION['role'] !== 'faculty') {
        $response["message"] = "Only faculty members can create sessions.";
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
    
    $courseId = intval($data['course_id'] ?? 0);
    $sessionDate = trim($data['session_date'] ?? '');
    $topic = trim($data['topic'] ?? '');
    $location = trim($data['location'] ?? 'Classroom');
    $sessionTime = trim($data['session_time'] ?? '10:00:00');
    $createdBy = $_SESSION['user_id'];
    
    // Validation
    if ($courseId <= 0) {
        $response["message"] = "Invalid course selection.";
        echo json_encode($response);
        exit;
    }
    
    if (empty($sessionDate)) {
        $response["message"] = "Session date is required.";
        echo json_encode($response);
        exit;
    }
    
    // Verify the course exists and faculty owns it
    $checkCourse = $conn->prepare("SELECT id FROM courses WHERE id = ? AND faculty_id = ?");
    $checkCourse->bind_param("ii", $courseId, $createdBy);
    $checkCourse->execute();
    $courseResult = $checkCourse->get_result();
    
    if ($courseResult->num_rows === 0) {
        $response["message"] = "Course not found or you don't own this course.";
        $checkCourse->close();
        echo json_encode($response);
        exit;
    }
    $checkCourse->close();
    
    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $sessionDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $sessionDate) {
        $response["message"] = "Invalid date format. Use YYYY-MM-DD.";
        echo json_encode($response);
        exit;
    }
    
    // Check if session already exists for this course on this date
    $checkSession = $conn->prepare("SELECT id FROM sessions WHERE course_id = ? AND session_date = ?");
    $checkSession->bind_param("is", $courseId, $sessionDate);
    $checkSession->execute();
    $sessionResult = $checkSession->get_result();
    
    if ($sessionResult->num_rows > 0) {
        $response["message"] = "A session already exists for this course on the selected date.";
        $checkSession->close();
        echo json_encode($response);
        exit;
    }
    $checkSession->close();
    
    // Insert the session
    $stmt = $conn->prepare("
        INSERT INTO sessions (course_id, session_date, session_time, topic, location, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("issssi", $courseId, $sessionDate, $sessionTime, $topic, $location, $createdBy);
    
    if ($stmt->execute()) {
        $sessionId = $conn->insert_id;
        $response["success"] = true;
        $response["message"] = "Session created successfully.";
        $response["session_id"] = $sessionId;
        
        // Get course name for response
        $courseStmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
        $courseStmt->bind_param("i", $courseId);
        $courseStmt->execute();
        $courseNameResult = $courseStmt->get_result();
        if ($courseNameResult->num_rows > 0) {
            $courseData = $courseNameResult->fetch_assoc();
            $response["course_name"] = $courseData['course_name'];
        }
        $courseStmt->close();
        
    } else {
        throw new Exception("Failed to create session: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Create session error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Create session fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>