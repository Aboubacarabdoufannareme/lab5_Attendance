<?php
// quick_check.php
require_once "connect.php";

$session_id = 1; // Change to your session ID

$check = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM enrollments WHERE enrollment_status = 'active') as active_enrollments,
        (SELECT COUNT(*) FROM enrollments e 
         JOIN sessions s ON e.course_id = s.course_id 
         WHERE s.id = $session_id AND e.enrollment_status = 'active') as enrolled_in_this_session
");

$data = $check->fetch_assoc();
echo "Total students: " . $data['total_students'] . "<br>";
echo "Active enrollments: " . $data['active_enrollments'] . "<br>";
echo "Enrolled in this session's course: " . $data['enrolled_in_this_session'] . "<br>";
?>