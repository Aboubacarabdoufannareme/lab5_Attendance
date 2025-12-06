<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = ["success" => false, "message" => ""];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty_intern') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['request_id'])) {
        $response["message"] = "Invalid data";
        echo json_encode($response);
        exit;
    }
    
    $request_id = intval($data['request_id']);
    $reviewed_by = $_SESSION['user_id'];
    
    // Update the audit request
    $stmt = $conn->prepare("
        UPDATE audit_requests 
        SET status = 'approved', 
            reviewed_by = ?, 
            review_date = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    
    $stmt->bind_param("ii", $reviewed_by, $request_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response["success"] = true;
            $response["message"] = "Auditor request approved successfully";
            
            // You could also create an enrollment for the auditor here
            // Get the student_id and course_id from the request
            $get_request = $conn->prepare("SELECT student_id, course_id FROM audit_requests WHERE id = ?");
            $get_request->bind_param("i", $request_id);
            $get_request->execute();
            $request_data = $get_request->get_result()->fetch_assoc();
            $get_request->close();
            
            if ($request_data) {
                // Create an enrollment as auditor
                $enroll_stmt = $conn->prepare("
                    INSERT INTO enrollments (student_id, course_id, enrollment_status)
                    VALUES (?, ?, 'active')
                    ON DUPLICATE KEY UPDATE enrollment_status = 'active'
                ");
                $enroll_stmt->bind_param("ii", $request_data['student_id'], $request_data['course_id']);
                $enroll_stmt->execute();
                $enroll_stmt->close();
            }
        } else {
            $response["message"] = "Request not found or already processed";
        }
    } else {
        throw new Exception("Failed to approve request: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>