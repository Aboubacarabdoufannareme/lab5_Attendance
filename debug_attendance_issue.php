<?php
// debug_attendance_issue.php
require_once "connect.php";

echo "<h2>Attendance System Debug - Complete Check</h2>";

// Get session ID from URL or use first available
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id === 0) {
    // Get first session
    $first_session = $conn->query("SELECT id FROM sessions LIMIT 1");
    if ($first_session->num_rows > 0) {
        $session_id = $first_session->fetch_assoc()['id'];
        echo "Using session ID: $session_id<br>";
    } else {
        echo "❌ No sessions found!<br>";
        exit;
    }
}

echo "<h3>1. Checking Session Details (ID: $session_id)</h3>";
$session_query = $conn->query("
    SELECT s.*, c.course_name, c.course_code, c.id as course_id
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.id = $session_id
");

if ($session_query->num_rows === 0) {
    echo "❌ Session not found!<br>";
    exit;
}

$session = $session_query->fetch_assoc();
echo "✅ Session found: {$session['course_code']} - {$session['course_name']}<br>";
echo "Course ID: {$session['course_id']}<br>";
echo "Session Date: {$session['session_date']}<br>";

echo "<h3>2. Checking Course Details</h3>";
$course_id = $session['course_id'];
$course_check = $conn->query("SELECT * FROM courses WHERE id = $course_id");
$course = $course_check->fetch_assoc();
echo "Course: {$course['course_code']} - {$course['course_name']}<br>";
echo "Faculty ID: {$course['faculty_id']}<br>";

echo "<h3>3. Checking Enrollments Table Structure</h3>";
$structure = $conn->query("DESCRIBE enrollments");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while($row = $structure->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>4. Checking for Enrollments in This Course</h3>";
$enrollments_check = $conn->query("
    SELECT 
        COUNT(*) as total_enrollments,
        COUNT(CASE WHEN enrollment_status = 'active' THEN 1 END) as active_enrollments
    FROM enrollments 
    WHERE course_id = $course_id
");

$enrollment_data = $enrollments_check->fetch_assoc();
echo "Total enrollments: " . $enrollment_data['total_enrollments'] . "<br>";
echo "Active enrollments: " . $enrollment_data['active_enrollments'] . "<br>";

if ($enrollment_data['total_enrollments'] == 0) {
    echo "❌ NO ENROLLMENTS FOUND!<br>";
    
    echo "<h4>4a. Checking Available Students</h4>";
    $students = $conn->query("SELECT id, firstname, lastname FROM users1 WHERE role = 'student'");
    echo "Available students: " . $students->num_rows . "<br>";
    
    if ($students->num_rows > 0) {
        echo "<h4>4b. Enrolling Students Now...</h4>";
        while ($student = $students->fetch_assoc()) {
            $student_id = $student['id'];
            
            // Check if enrollment exists
            $check = $conn->query("SELECT id FROM enrollments WHERE student_id = $student_id AND course_id = $course_id");
            
            if ($check->num_rows === 0) {
                $sql = "INSERT INTO enrollments (student_id, course_id, enrollment_status) 
                        VALUES ($student_id, $course_id, 'active')";
                
                if ($conn->query($sql)) {
                    echo "✅ Enrolled: {$student['firstname']} {$student['lastname']}<br>";
                } else {
                    echo "❌ Failed: {$student['firstname']} - " . $conn->error . "<br>";
                }
            } else {
                echo "ℹ️ Already enrolled: {$student['firstname']} {$student['lastname']}<br>";
            }
        }
    }
}

echo "<h3>5. Testing the Attendance Query</h3>";
$test_query = "
    SELECT 
        u.id, 
        u.firstname, 
        u.lastname, 
        u.email,
        IFNULL(a.status, 'absent') as attendance_status,
        a.notes as attendance_notes
    FROM users1 u
    INNER JOIN enrollments e ON u.id = e.student_id
    LEFT JOIN attendance a ON u.id = a.student_id AND a.session_id = $session_id
    WHERE e.course_id = $course_id
    AND e.enrollment_status = 'active'
    AND u.role = 'student'
    ORDER BY u.lastname, u.firstname
";

echo "<pre>Query being used:<br>" . htmlspecialchars($test_query) . "</pre>";

$result = $conn->query($test_query);
if (!$result) {
    echo "❌ Query failed: " . $conn->error . "<br>";
} else {
    echo "✅ Query executed successfully<br>";
    echo "Found " . $result->num_rows . " students<br>";
    
    if ($result->num_rows > 0) {
        echo "<h4>Students Found:</h4>";
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";
        while ($student = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $student['id'] . "</td>";
            echo "<td>" . $student['firstname'] . " " . $student['lastname'] . "</td>";
            echo "<td>" . $student['email'] . "</td>";
            echo "<td>" . $student['attendance_status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Query returned 0 rows<br>";
        
        // Debug step by step
        echo "<h4>Debugging step by step:</h4>";
        
        // Step 1: Check users with role student
        echo "Step 1 - Students in users table:<br>";
        $step1 = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
        $step1_data = $step1->fetch_assoc();
        echo "Students: " . $step1_data['count'] . "<br>";
        
        // Step 2: Check enrollments
        echo "Step 2 - Enrollments for course $course_id:<br>";
        $step2 = $conn->query("SELECT * FROM enrollments WHERE course_id = $course_id");
        echo "Enrollments: " . $step2->num_rows . "<br>";
        if ($step2->num_rows > 0) {
            while ($enrollment = $step2->fetch_assoc()) {
                echo "Student ID: {$enrollment['student_id']}, Status: {$enrollment['enrollment_status']}<br>";
            }
        }
        
        // Step 3: Check if users exist for those enrollments
        echo "Step 3 - Checking user existence:<br>";
        $step3 = $conn->query("
            SELECT e.*, u.firstname, u.lastname, u.role 
            FROM enrollments e
            LEFT JOIN users1 u ON e.student_id = u.id
            WHERE e.course_id = $course_id
        ");
        
        while ($row = $step3->fetch_assoc()) {
            echo "Student ID: {$row['student_id']}, ";
            echo "Name: " . ($row['firstname'] ?: 'NULL') . " " . ($row['lastname'] ?: 'NULL') . ", ";
            echo "Role: " . ($row['role'] ?: 'NULL') . ", ";
            echo "Enrollment Status: {$row['enrollment_status']}<br>";
        }
    }
}

echo "<h3>6. Quick Fix Commands</h3>";
echo "<pre>
-- Fix 1: Update all enrollments to 'active' status
UPDATE enrollments SET enrollment_status = 'active' 
WHERE enrollment_status IS NULL OR enrollment_status = '';

-- Fix 2: Enroll all students in this course
INSERT INTO enrollments (student_id, course_id, enrollment_status)
SELECT u.id, $course_id, 'active'
FROM users1 u
WHERE u.role = 'student'
AND NOT EXISTS (
    SELECT 1 FROM enrollments e 
    WHERE e.student_id = u.id AND e.course_id = $course_id
);

-- Fix 3: Check data integrity
SELECT 
    u.id as user_id,
    u.firstname,
    u.lastname,
    u.role,
    e.enrollment_status,
    c.course_code
FROM users1 u
LEFT JOIN enrollments e ON u.id = e.student_id AND e.course_id = $course_id
LEFT JOIN courses c ON e.course_id = c.id
WHERE u.role = 'student'
ORDER BY u.lastname;
</pre>";

echo "<h3>7. Test Links</h3>";
echo "<a href='attendance.php?session_id=$session_id' target='_blank' style='background: #ff4757; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Attendance Page</a><br><br>";
echo "<a href='fix_enrollments_now.php?course_id=$course_id' target='_blank' style='background: #3742fa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fix Enrollments Now</a>";
?>