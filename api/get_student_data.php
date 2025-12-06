<?php
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "message" => "",
    "courses" => [],
    "sessions" => [],
    "attendance" => []
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $student_id = $_SESSION['user_id'];
    
    // Fetch enrolled courses
    $courses_stmt = $conn->prepare("
        SELECT c.id, c.course_code, c.course_name, c.description, 
               e.enrolled_at, u.firstname as faculty_fname, u.lastname as faculty_lname
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        JOIN users1 u ON c.faculty_id = u.id
        WHERE e.student_id = ? AND e.enrollment_status = 'active'
        ORDER BY c.course_name
    ");
    $courses_stmt->bind_param("i", $student_id);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
    $response["courses"] = $courses_result->fetch_all(MYSQLI_ASSOC);
    $courses_stmt->close();
    
    // Fetch upcoming sessions
    $sessions_stmt = $conn->prepare("
        SELECT s.id, s.session_date, s.session_time, s.topic, s.location,
               c.course_code, c.course_name,
               (SELECT status FROM attendance a WHERE a.session_id = s.id AND a.student_id = ?) as attendance_status
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? 
          AND s.session_date >= CURDATE() 
          AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY s.session_date, s.session_time
        LIMIT 10
    ");
    $sessions_stmt->bind_param("ii", $student_id, $student_id);
    $sessions_stmt->execute();
    $sessions_result = $sessions_stmt->get_result();
    $response["sessions"] = $sessions_result->fetch_all(MYSQLI_ASSOC);
    $sessions_stmt->close();
    
    // Fetch attendance summary - FIXED SQL SYNTAX
    $attendance_stmt = $conn->prepare("
        SELECT 
            c.id as course_id,
            c.course_code,
            c.course_name,
            COUNT(DISTINCT s.id) as total_sessions,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as attended,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            COALESCE(
                (SELECT feedback_text FROM feedback 
                 WHERE student_id = ? AND course_id = c.id 
                 ORDER BY created_at DESC LIMIT 1), 
                'No feedback yet'
            ) as latest_feedback
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN sessions s ON c.id = s.course_id
        LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = ?
        WHERE e.student_id = ? AND e.enrollment_status = 'active'
        GROUP BY c.id, c.course_code, c.course_name
        ORDER BY c.course_name
    ");
    $attendance_stmt->bind_param("iii", $student_id, $student_id, $student_id);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    $attendance_data = $attendance_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate percentages
    foreach ($attendance_data as &$record) {
        $total = $record['total_sessions'] > 0 ? $record['total_sessions'] : 1;
        $attended = $record['attended'] + $record['late_count'];
        $record['attendance_percentage'] = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
    }
    
    $response["attendance"] = $attendance_data;
    $attendance_stmt->close();
    
    $response["success"] = true;
    $response["message"] = "Data fetched successfully";
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Get student data error: " . $e->getMessage());
}

echo json_encode($response);
?>