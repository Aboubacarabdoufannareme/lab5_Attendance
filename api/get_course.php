<?php
session_start();
header("Content-Type: application/json");

// Enable error reporting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../connect.php";

$response = [
    "success" => false,
    "data" => null,
    "message" => ""
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        $response["message"] = "You must be logged in to perform this action.";
        echo json_encode($response);
        exit;
    }
    
    // Check authorization - only faculty can view course details
    if ($_SESSION['role'] !== 'faculty') {
        $response["message"] = "Only faculty members can view course details.";
        echo json_encode($response);
        exit;
    }
    
    // Get course ID from query parameter
    $courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($courseId <= 0) {
        $response["message"] = "Invalid course ID.";
        echo json_encode($response);
        exit;
    }
    
    // Get faculty ID
    $facultyId = $_SESSION['user_id'];
    
    // First, let's check what columns exist in the courses table
    $checkColumns = $conn->query("SHOW COLUMNS FROM courses");
    $columns = [];
    while ($col = $checkColumns->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    // Build SELECT statement with only existing columns
    $selectColumns = [];
    $possibleColumns = ['id', 'course_code', 'course_name', 'description', 'faculty_id', 'created_at', 'updated_at'];
    
    foreach ($possibleColumns as $col) {
        if (in_array($col, $columns)) {
            $selectColumns[] = $col;
        }
    }
    
    if (empty($selectColumns)) {
        throw new Exception("No valid columns found in courses table");
    }
    
    $selectString = implode(', ', $selectColumns);
    
    // Fetch course details and verify ownership
    $stmt = $conn->prepare("
        SELECT $selectString
        FROM courses 
        WHERE id = ? AND faculty_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $courseId, $facultyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response["message"] = "Course not found or you don't have permission to view it.";
        $stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $course = $result->fetch_assoc();
    
    $response["success"] = true;
    $response["data"] = $course;
    $response["message"] = "Course details fetched successfully";
    $response["debug"] = [
        "available_columns" => $columns,
        "selected_columns" => $selectColumns
    ];
    
    $stmt->close();
    
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
    error_log("Get course error: " . $e->getMessage());
} catch (Error $e) {
    $response["message"] = "A fatal error occurred: " . $e->getMessage();
    error_log("Get course fatal error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>