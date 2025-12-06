<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";
$response = ["success" => false, "data" => [], "message" => ""];

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty_intern') {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    $stmt = $conn->prepare("
        SELECT s.id, s.session_date, s.session_time, s.topic, s.location,
               c.course_code, c.course_name
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.session_date >= CURDATE() 
        AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY s.session_date, s.session_time
    ");
    
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