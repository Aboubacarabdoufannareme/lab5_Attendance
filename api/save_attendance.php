<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = ["success" => false, "message" => ""];

try {
    if (!isset($_SESSION['user_id'])) {
        $response["message"] = "Unauthorized access";
        echo json_encode($response);
        exit;
    }
    
    // Allow both faculty and faculty_intern to mark attendance
    if (!in_array($_SESSION['role'], ['faculty', 'faculty_intern'])) {
        $response["message"] = "You don't have permission to mark attendance";
        echo json_encode($response);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['session_id']) || !isset($data['attendance'])) {
        $response["message"] = "Invalid data";
        echo json_encode($response);
        exit;
    }
    
    $sessionId = intval($data['session_id']);
    $attendanceData = $data['attendance'];
    $markedBy = $_SESSION['user_id'];
    
    // Verify session exists and is valid
    $checkStmt = $conn->prepare("
        SELECT s.id, c.faculty_id
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = ?
    ");
    $checkStmt->bind_param("i", $sessionId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $response["message"] = "Session not found";
        $checkStmt->close();
        echo json_encode($response);
        exit;
    }
    
    $sessionData = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // For faculty_interns, check if they have permission
    if ($_SESSION['role'] === 'faculty_intern') {
        // You might want to add additional checks here
        // For example, check if intern is assigned to assist this faculty member
        // For now, we'll allow them to mark attendance
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    foreach ($attendanceData as $studentId => $record) {
        $studentId = intval($studentId);
        $status = $conn->real_escape_string($record['status']);
        $notes = isset($record['notes']) ? $conn->real_escape_string($record['notes']) : '';
        
        // Check if record exists
        $check = $conn->prepare("SELECT id FROM attendance WHERE session_id = ? AND student_id = ?");
        $check->bind_param("ii", $sessionId, $studentId);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        
        if ($exists) {
            // Update
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET status = ?, notes = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP 
                WHERE session_id = ? AND student_id = ?
            ");
            $stmt->bind_param("ssiii", $status, $notes, $markedBy, $sessionId, $studentId);
        } else {
            // Insert
            $stmt = $conn->prepare("
                INSERT INTO attendance (session_id, student_id, status, notes, marked_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissi", $sessionId, $studentId, $status, $notes, $markedBy);
        }
        
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    $response["success"] = true;
    $response["message"] = "Attendance saved successfully";
    
} catch (Exception $e) {
    $conn->rollback();
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>