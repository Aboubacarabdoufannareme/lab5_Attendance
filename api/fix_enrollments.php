<?php
// fix_enrollments.php
require_once "connect.php";

echo "<h2>Fixing Enrollments</h2>";

// 1. Get MATH101 course
$course = $conn->query("SELECT id FROM courses WHERE course_code = 'MATH101'");
if ($course->num_rows === 0) {
    die("MATH101 course not found. Create it first.");
}
$course_id = $course->fetch_assoc()['id'];
echo "MATH101 Course ID: $course_id<br>";

// 2. Get all students
$students = $conn->query("SELECT id, firstname, lastname FROM users1 WHERE role = 'student'");
echo "Found " . $students->num_rows . " students<br>";

// 3. Enroll them in MATH101
$enrolled_count = 0;
while ($student = $students->fetch_assoc()) {
    $student_id = $student['id'];
    
    // Check if already enrolled
    $check = $conn->query("SELECT id FROM enrollments WHERE student_id = $student_id AND course_id = $course_id");
    
    if ($check->num_rows === 0) {
        // Not enrolled, enroll them
        $sql = "INSERT INTO enrollments (student_id, course_id, enrollment_status) 
                VALUES ($student_id, $course_id, 'active')";
        
        if ($conn->query($sql)) {
            echo "✅ Enrolled " . $student['firstname'] . " " . $student['lastname'] . "<br>";
            $enrolled_count++;
        } else {
            echo "❌ Failed to enroll " . $student['firstname'] . ": " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ Already enrolled: " . $student['firstname'] . " " . $student['lastname'] . "<br>";
    }
}

echo "<hr><h3>Summary:</h3>";
echo "Total students: " . $students->num_rows . "<br>";
echo "Newly enrolled: $enrolled_count<br>";

// 4. Verify enrollments
echo "<h3>Current Enrollments in MATH101:</h3>";
$enrollments = $conn->query("
    SELECT u.firstname, u.lastname, e.enrollment_status 
    FROM enrollments e
    JOIN users1 u ON e.student_id = u.id
    WHERE e.course_id = $course_id
");

echo "<table border='1'><tr><th>Name</th><th>Status</th></tr>";
while ($enrollment = $enrollments->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $enrollment['firstname'] . " " . $enrollment['lastname'] . "</td>";
    echo "<td>" . $enrollment['enrollment_status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 5. Quick SQL to run manually
echo "<hr><h3>Quick SQL Commands:</h3>";
echo "<pre>
-- Enroll all students in MATH101:
INSERT INTO enrollments (student_id, course_id, enrollment_status)
SELECT u.id, c.id, 'active'
FROM users1 u
CROSS JOIN courses c
WHERE u.role = 'student'
AND c.course_code = 'MATH101'
ON DUPLICATE KEY UPDATE enrollment_status = 'active';

-- Check enrollments:
SELECT COUNT(*) as active_students 
FROM enrollments 
WHERE course_id = $course_id 
AND enrollment_status = 'active';

-- Fix enrollment status for all:
UPDATE enrollments SET enrollment_status = 'active' WHERE enrollment_status IS NULL;
</pre>";

echo "<hr><a href='attendance.php?session_id=1' style='background: #ff4757; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Attendance Now</a>";
?>