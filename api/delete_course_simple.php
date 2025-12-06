<?php
// delete_course_simple.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = ["success" => false, "message" => ""];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $id = intval($data['id'] ?? 0);
    $facultyId = $_SESSION['user_id'];
    
    if ($id <= 0) {
        $response["message"] = "Invalid course ID.";
        echo json_encode($response);
        exit;
    }
    
    // Verify course exists and faculty owns it
    $check = $conn->prepare("SELECT course_name FROM courses WHERE id = ? AND faculty_id = ?");
    $check->bind_param("ii", $id, $facultyId);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        $response["message"] = "Course not found or you don't have permission to delete it.";
        $check->close();
        echo json_encode($response);
        exit;
    }
    
    $course = $result->fetch_assoc();
    $check->close();
    
    // Delete the course (let foreign key constraints handle related data)
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Course deleted successfully.";
    } else {
        throw new Exception("Failed to delete course: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>