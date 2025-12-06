<?php
// api/get_session_attendance.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = ["success" => false, "data" => [], "message" => ""];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
    $facultyId = $_SESSION['user_id'];
    
    if ($sessionId <= 0) {
        $response["message"] = "Invalid session ID";
        echo json_encode($response);
        exit;
    }
    
    // Get session details
    $stmt = $conn->prepare("
        SELECT s.*, c.course_name, c.course_code
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = ? AND c.faculty_id = ?
    ");
    $stmt->bind_param("ii", $sessionId, $facultyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response["message"] = "Session not found";
        $stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $session = $result->fetch_assoc();
    $stmt->close();
    
    // Get students
    $studentsStmt = $conn->prepare("
        SELECT u.id, u.firstname, u.lastname, u.email,
               IFNULL(a.status, 'absent') as attendance_status,
               a.notes as attendance_notes
        FROM enrollments e
        JOIN users1 u ON e.student_id = u.id
        LEFT JOIN attendance a ON e.student_id = a.student_id AND a.session_id = ?
        WHERE e.course_id = ? AND e.status = 'enrolled'
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
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>