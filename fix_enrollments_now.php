<?php
// fix_enrollments_now.php
require_once "connect.php";

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id === 0) {
    die("Please provide course_id parameter");
}

echo "<h2>Fixing Enrollments for Course ID: $course_id</h2>";

// Get course info
$course = $conn->query("SELECT course_code, course_name FROM courses WHERE id = $course_id");
if ($course->num_rows === 0) {
    die("Course not found");
}
$course_data = $course->fetch_assoc();
echo "Course: {$course_data['course_code']} - {$course_data['course_name']}<br>";

// Step 1: Get all students
$students = $conn->query("SELECT id, firstname, lastname FROM users1 WHERE role = 'student'");
echo "Found " . $students->num_rows . " students<br>";

// Step 2: Enroll each student
$enrolled = 0;
$already = 0;
$errors = 0;

while ($student = $students->fetch_assoc()) {
    $student_id = $student['id'];
    
    // Check if already enrolled
    $check = $conn->query("SELECT id FROM enrollments WHERE student_id = $student_id AND course_id = $course_id");
    
    if ($check->num_rows === 0) {
        // Enroll the student
        $sql = "INSERT INTO enrollments (student_id, course_id, enrollment_status) 
                VALUES ($student_id, $course_id, 'active')";
        
        if ($conn->query($sql)) {
            echo "✅ Enrolled: {$student['firstname']} {$student['lastname']}<br>";
            $enrolled++;
        } else {
            echo "❌ Failed: {$student['firstname']} - " . $conn->error . "<br>";
            $errors++;
        }
    } else {
        // Update status to active
        $update = $conn->query("UPDATE enrollments SET enrollment_status = 'active' WHERE student_id = $student_id AND course_id = $course_id");
        echo "ℹ️ Updated: {$student['firstname']} {$student['lastname']}<br>";
        $already++;
    }
}

echo "<hr><h3>Summary:</h3>";
echo "Newly enrolled: $enrolled<br>";
echo "Already existed (updated): $already<br>";
echo "Errors: $errors<br>";

// Verify
$verify = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE course_id = $course_id AND enrollment_status = 'active'");
$verify_data = $verify->fetch_assoc();

echo "<h3>Verification:</h3>";
echo "Active enrollments: " . $verify_data['count'] . "<br>";

// Get session for this course
$session = $conn->query("SELECT id FROM sessions WHERE course_id = $course_id LIMIT 1");
if ($session->num_rows > 0) {
    $session_id = $session->fetch_assoc()['id'];
    echo "<hr><h3>Test Now:</h3>";
    echo "<a href='attendance.php?session_id=$session_id' target='_blank' style='background: #2ed573; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🎯 Test Attendance Now</a>";
}
?>