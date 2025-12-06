<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "data" => [],
    "message" => ""
];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $faculty_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT s.id, s.session_date, s.topic, c.course_name, 
               COUNT(a.id) as attendance_count
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN attendance a ON s.id = a.session_id AND a.status = 'present'
        WHERE c.faculty_id = ?
        GROUP BY s.id
        ORDER BY s.session_date DESC
        LIMIT 10
    ");
    
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = $result->fetch_all(MYSQLI_ASSOC);
    
    $response["success"] = true;
    $response["data"] = $sessions;
    $response["message"] = "Sessions fetched successfully";
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>