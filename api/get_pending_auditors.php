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
    
    // First check if audit_requests table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'audit_requests'");
    
    if ($table_check->num_rows === 0) {
        // Table doesn't exist, create it
        $create_table = "
            CREATE TABLE IF NOT EXISTS audit_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                course_id INT NOT NULL,
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                reviewed_by INT NULL,
                review_date DATETIME NULL,
                review_notes TEXT,
                FOREIGN KEY (student_id) REFERENCES users1(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES users1(id) ON DELETE SET NULL
            )
        ";
        
        $conn->query($create_table);
        
        // Insert some demo data for testing
        $demo_data = "
            INSERT INTO audit_requests (student_id, course_id, status)
            SELECT u.id, c.id, 'pending'
            FROM users1 u
            CROSS JOIN courses c
            WHERE u.role = 'student'
            AND c.course_code = 'MATH101'
            LIMIT 2
            ON DUPLICATE KEY UPDATE status = 'pending'
        ";
        
        $conn->query($demo_data);
    }
    
    $stmt = $conn->prepare("
        SELECT 
            u.firstname, u.lastname,
            c.course_name,
            ar.request_date,
            ar.id as request_id
        FROM audit_requests ar
        JOIN users1 u ON ar.student_id = u.id
        JOIN courses c ON ar.course_id = c.id
        WHERE ar.status = 'pending'
        ORDER BY ar.request_date DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $auditors = $result->fetch_all(MYSQLI_ASSOC);
    
    $response["success"] = true;
    $response["data"] = $auditors;
    $response["message"] = "Pending auditors fetched successfully";
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>