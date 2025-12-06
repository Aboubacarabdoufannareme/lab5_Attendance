<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "message" => "",
    "attendance_id" => null,
    "session_details" => null
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        $response["message"] = "You must be logged in as a student to mark attendance.";
        echo json_encode($response);
        exit;
    }
    
    // Get input data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        $response["message"] = "Invalid request data.";
        echo json_encode($response);
        exit;
    }
    
    $session_id = intval($data['session_id'] ?? 0);
    $student_id = $_SESSION['user_id'];
    $status = trim($data['status'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $course_name = trim($data['course_name'] ?? '');
    
    // Validation
    if ($session_id <= 0) {
        $response["message"] = "Invalid session ID.";
        echo json_encode($response);
        exit;
    }
    
    $valid_statuses = ['present', 'absent', 'late', 'excused'];
    if (!in_array($status, $valid_statuses)) {
        $response["message"] = "Invalid attendance status.";
        echo json_encode($response);
        exit;
    }
    
    // Check if student is enrolled in the course for this session
    $check_stmt = $conn->prepare("
        SELECT s.id, s.session_date, s.session_time, s.topic, s.location,
               c.course_code, c.course_name, c.id as course_id,
               CONCAT(u.firstname, ' ', u.lastname) as faculty_name
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        JOIN users1 u ON c.faculty_id = u.id
        WHERE s.id = ? AND e.student_id = ? AND e.enrollment_status = 'active'
    ");
    $check_stmt->bind_param("ii", $session_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $response["message"] = "You are not enrolled in this course or the session doesn't exist.";
        $check_stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $session_details = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Store session details in response for reference
    $response["session_details"] = $session_details;
    
    // Check if session date is today or in the past (can't mark attendance for future sessions)
    $date_check = $conn->prepare("
        SELECT session_date, session_time
        FROM sessions 
        WHERE id = ? AND session_date <= CURDATE()
    ");
    $date_check->bind_param("i", $session_id);
    $date_check->execute();
    $date_result = $date_check->get_result();
    
    if ($date_result->num_rows === 0) {
        $response["message"] = "Cannot mark attendance for future sessions.";
        $date_check->close();
        echo json_encode($response);
        exit;
    }
    
    $date_data = $date_result->fetch_assoc();
    $session_date = $date_data['session_date'];
    $session_time = $date_data['session_time'];
    $date_check->close();
    
    // For present/late status, check if session has already started (if time is specified)
    if (($status === 'present' || $status === 'late') && $session_time) {
        $current_time = date('H:i:s');
        $session_start_time = date('H:i:s', strtotime($session_time));
        
        // If session is today and hasn't started yet
        if ($session_date == date('Y-m-d') && $current_time < $session_start_time) {
            $response["message"] = "Session hasn't started yet. It starts at " . date('h:i A', strtotime($session_time));
            echo json_encode($response);
            exit;
        }
    }
    
    // Validate excuse notes if status is 'excused'
    if ($status === 'excused' && empty($notes)) {
        // Allow empty notes but suggest adding them
        $notes = "Excused absence - no reason provided";
    }
    
    // Check if attendance already marked
    $existing_stmt = $conn->prepare("
        SELECT id, status, notes 
        FROM attendance 
        WHERE session_id = ? AND student_id = ?
    ");
    $existing_stmt->bind_param("ii", $session_id, $student_id);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    
    if ($existing_result->num_rows > 0) {
        $existing_data = $existing_result->fetch_assoc();
        $existing_status = $existing_data['status'];
        $existing_notes = $existing_data['notes'];
        
        // Don't allow changing from excused to other status without faculty approval
        if ($existing_status === 'excused' && $status !== 'excused') {
            $response["message"] = "Cannot change excused attendance. Please contact your faculty.";
            $existing_stmt->close();
            echo json_encode($response);
            exit;
        }
        
        // Don't allow changing from present to absent (or vice versa) after 24 hours
        $time_check = $conn->prepare("
            SELECT TIMESTAMPDIFF(HOUR, marked_at, NOW()) as hours_passed
            FROM attendance 
            WHERE session_id = ? AND student_id = ?
        ");
        $time_check->bind_param("ii", $session_id, $student_id);
        $time_check->execute();
        $time_result = $time_check->get_result();
        $time_data = $time_result->fetch_assoc();
        $hours_passed = $time_data['hours_passed'] ?? 0;
        $time_check->close();
        
        if ($hours_passed > 24 && ($existing_status === 'present' || $existing_status === 'late' || $existing_status === 'absent')) {
            $response["message"] = "Attendance cannot be changed after 24 hours. Please contact your faculty.";
            $existing_stmt->close();
            echo json_encode($response);
            exit;
        }
        
        // Update existing attendance
        $update_stmt = $conn->prepare("
            UPDATE attendance 
            SET status = ?, notes = ?, marked_at = CURRENT_TIMESTAMP, marked_by = ?
            WHERE session_id = ? AND student_id = ?
        ");
        
        // Use existing notes if new notes are empty, unless status is excused
        $final_notes = empty($notes) ? $existing_notes : $notes;
        if ($status === 'excused' && empty($notes)) {
            $final_notes = "Excused absence";
        }
        
        $update_stmt->bind_param("ssiii", $status, $final_notes, $student_id, $session_id, $student_id);
        
        if ($update_stmt->execute()) {
            $response["success"] = true;
            $response["message"] = "Attendance updated successfully.";
            $response["attendance_id"] = $existing_data['id'];
            $response["previous_status"] = $existing_status;
            
            // Log the change
            $log_stmt = $conn->prepare("
                INSERT INTO attendance_logs (attendance_id, old_status, new_status, changed_by, change_reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $log_reason = "Student updated attendance";
            $log_stmt->bind_param("issss", $existing_data['id'], $existing_status, $status, $student_id, $log_reason);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            throw new Exception("Failed to update attendance: " . $update_stmt->error);
        }
        
        $update_stmt->close();
    } else {
        // Insert new attendance record
        $insert_stmt = $conn->prepare("
            INSERT INTO attendance (session_id, student_id, status, notes, marked_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        // Set default notes if empty
        $final_notes = $notes;
        if (empty($notes)) {
            if ($status === 'present') {
                $final_notes = "Marked present by student";
            } elseif ($status === 'late') {
                $final_notes = "Marked late by student";
            } elseif ($status === 'absent') {
                $final_notes = "Marked absent by student";
            } elseif ($status === 'excused') {
                $final_notes = "Excused absence";
            }
        }
        
        $insert_stmt->bind_param("iissi", $session_id, $student_id, $status, $final_notes, $student_id);
        
        if ($insert_stmt->execute()) {
            $attendance_id = $conn->insert_id;
            $response["success"] = true;
            $response["message"] = "Attendance marked successfully.";
            $response["attendance_id"] = $attendance_id;
            
            // Send notification to faculty if marked absent or late
            if ($status === 'absent' || $status === 'late') {
                $notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, related_id)
                    VALUES (?, ?, ?, 'attendance', ?)
                ");
                
                $faculty_id = $session_details['faculty_id'] ?? null;
                if ($faculty_id) {
                    $student_name = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'] ?? $_SESSION['username'];
                    $title = "Attendance Marked - " . ucfirst($status);
                    $message = "$student_name marked as $status for " . $session_details['course_name'] . " on " . 
                              date('M d, Y', strtotime($session_date));
                    
                    $notification_stmt->bind_param("issi", $faculty_id, $title, $message, $attendance_id);
                    $notification_stmt->execute();
                }
                $notification_stmt->close();
            }
        } else {
            throw new Exception("Failed to mark attendance: " . $insert_stmt->error);
        }
        
        $insert_stmt->close();
    }
    
    $existing_stmt->close();
    
    // Update student's last activity
    $activity_stmt = $conn->prepare("
        UPDATE users1 
        SET last_activity = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $activity_stmt->bind_param("i", $student_id);
    $activity_stmt->execute();
    $activity_stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Mark attendance error: " . $e->getMessage());
    
    // Add more debug info in development
    if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
        $response["debug"] = [
            "session_id" => $session_id ?? null,
            "student_id" => $student_id ?? null,
            "status" => $status ?? null,
            "notes" => $notes ?? null
        ];
    }
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Mark attendance fatal error: " . $e->getMessage());
}

echo json_encode($response);
?>