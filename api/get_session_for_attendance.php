<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = ["success" => false, "data" => null, "message" => ""];

try {
    if (!isset($_SESSION['user_id'])) {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
    
    if ($sessionId <= 0) {
        $response["message"] = "Invalid session ID";
        echo json_encode($response);
        exit;
    }
    
    // Get session details
    $sessionStmt = $conn->prepare("
        SELECT s.*, c.course_name, c.course_code, c.id as course_id
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = ?
    ");
    
    $sessionStmt->bind_param("i", $sessionId);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();
    
    if ($sessionResult->num_rows === 0) {
        $response["message"] = "Session not found";
        $sessionStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $session = $sessionResult->fetch_assoc();
    $sessionStmt->close();
    
    // Get enrolled students for this course
    $studentsStmt = $conn->prepare("
        SELECT 
            u.id,
            u.firstname,
            u.lastname,
            u.email,
            u.student_id,
            IFNULL(a.status, 'absent') as attendance_status,
            a.notes as attendance_notes
        FROM enrollments e
        JOIN users1 u ON e.student_id = u.id
        LEFT JOIN attendance a ON e.student_id = a.student_id AND a.session_id = ?
        WHERE e.course_id = ?
        AND e.enrollment_status = 'active'
        AND u.role = 'student'
        ORDER BY u.lastname, u.firstname
    ");
    
    $studentsStmt->bind_param("ii", $sessionId, $session['course_id']);
    $studentsStmt->execute();
    $studentsResult = $studentsStmt->get_result();
    $students = $studentsResult->fetch_all(MYSQLI_ASSOC);
    $studentsStmt->close();
    
    $response["success"] = true;
    $response["data"] = [
        "session" => $session,
        "students" => $students
    ];
    $response["message"] = "Data fetched successfully";
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>