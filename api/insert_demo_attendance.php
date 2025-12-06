<?php
require_once "connect.php";

echo "<h2>Inserting Demo Attendance Data</h2>";

// 1. Get or create faculty
$faculty = $conn->query("SELECT id FROM users WHERE role = 'faculty' LIMIT 1");
if ($faculty->num_rows === 0) {
    // Create demo faculty
    $hashed_password = password_hash('faculty123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, email, role, firstname, lastname) 
                  VALUES ('prof.demo', '$hashed_password', 'prof.demo@uni.edu', 'faculty', 'Demo', 'Professor')");
    $faculty_id = $conn->insert_id;
    echo "✅ Created faculty user (ID: $faculty_id)<br>";
} else {
    $faculty_id = $faculty->fetch_assoc()['id'];
    echo "✅ Using existing faculty ID: $faculty_id<br>";
}

// 2. Create MATH101 course
$conn->query("INSERT INTO courses (course_code, course_name, description, faculty_id) 
              VALUES ('MATH101', 'Calculus I', 'Demo course for attendance testing', $faculty_id)
              ON DUPLICATE KEY UPDATE faculty_id = $faculty_id");

$course = $conn->query("SELECT id FROM courses WHERE course_code = 'MATH101'");
$course_id = $course->fetch_assoc()['id'];
echo "✅ Course MATH101 ID: $course_id<br>";

// 3. Create demo students
$students = [
    ['john.demo', 'John', 'Doe', 'john@demo.edu'],
    ['jane.demo', 'Jane', 'Smith', 'jane@demo.edu'],
    ['mike.demo', 'Mike', 'Jones', 'mike@demo.edu'],
    ['sarah.demo', 'Sarah', 'Wilson', 'sarah@demo.edu'],
    ['david.demo', 'David', 'Brown', 'david@demo.edu']
];

$student_ids = [];
foreach ($students as $student) {
    $username = $student[0];
    $firstname = $student[1];
    $lastname = $student[2];
    $email = $student[3];
    $hashed_pass = password_hash('student123', PASSWORD_DEFAULT);
    
    $conn->query("INSERT INTO users1 (username, password, email, role, firstname, lastname) 
                  VALUES ('$username', '$hashed_pass', '$email', 'student', '$firstname', '$lastname')
                  ON DUPLICATE KEY UPDATE email = '$email'");
    
    $student_id = $conn->insert_id ?: $conn->query("SELECT id FROM users1 WHERE username = '$username'")->fetch_assoc()['id'];
    $student_ids[] = $student_id;
    
    // Enroll in course
    $conn->query("INSERT INTO enrollments (student_id, course_id) VALUES ($student_id, $course_id)
                  ON DUPLICATE KEY UPDATE student_id = $student_id");
}
echo "✅ Created 5 students and enrolled them in MATH101<br>";

// 4. Create 4 sessions (3 past, 1 today)
$sessions = [];
for ($i = 3; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $topic = $i === 0 ? "Test Attendance Session" : "Demo Session " . (3-$i+1);
    
    $conn->query("INSERT INTO sessions (course_id, session_date, session_time, topic, location, created_by) 
                  VALUES ($course_id, '$date', '10:00:00', '$topic', 'Room 101', $faculty_id)
                  ON DUPLICATE KEY UPDATE session_date = '$date'");
    
    $session_id = $conn->insert_id ?: $conn->query("SELECT id FROM sessions WHERE topic = '$topic' AND course_id = $course_id")->fetch_assoc()['id'];
    $sessions[] = ['id' => $session_id, 'date' => $date, 'topic' => $topic];
    
    echo "✅ Created session: $topic (ID: $session_id) on $date<br>";
}

// 5. Add attendance for first 3 sessions (leave last one empty)
$attendance_data = [
    [['present', 'On time'], ['present', 'Good'], ['late', 'Late 10min'], ['absent', 'No excuse'], ['excused', 'Medical']],
    [['present', ''], ['present', 'Asked Qs'], ['present', ''], ['present', 'First time'], ['late', 'Traffic']],
    [['present', ''], ['present', ''], ['present', ''], ['present', ''], ['present', '']]
];

for ($i = 0; $i < 3; $i++) {
    $session_id = $sessions[$i]['id'];
    
    for ($j = 0; $j < 5; $j++) {
        $student_id = $student_ids[$j];
        $status = $attendance_data[$i][$j][0];
        $notes = $attendance_data[$i][$j][1];
        
        $conn->query("INSERT INTO attendance (session_id, student_id, status, notes, marked_by) 
                      VALUES ($session_id, $student_id, '$status', '$notes', $faculty_id)
                      ON DUPLICATE KEY UPDATE status = '$status'");
    }
    echo "✅ Added attendance data for session " . ($i+1) . "<br>";
}

// 6. Show test session info
$test_session_id = $sessions[3]['id'];
echo "<hr>";
echo "<h3>🎯 READY FOR TESTING!</h3>";
echo "<p>Test Session ID: <strong>$test_session_id</strong></p>";
echo "<p>Session Date: <strong>" . $sessions[3]['date'] . "</strong></p>";
echo "<p>Topic: <strong>" . $sessions[3]['topic'] . "</strong></p>";
echo "<br>";
echo "<a href='attendance.php?session_id=$test_session_id' target='_blank' style='background: #ff4757; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>🎯 Test Attendance Marking</a>";
echo "&nbsp;&nbsp;";
echo "<a href='facultyDashboard.php' target='_blank' style='background: #3742fa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>📊 Go to Dashboard</a>";

echo "<hr><h3>Verification Query:</h3>";
echo "<pre>SELECT COUNT(*) as students FROM enrollments WHERE course_id = $course_id;</pre>";
echo "<pre>SELECT COUNT(*) as sessions FROM sessions WHERE course_id = $course_id;</pre>";
echo "<pre>SELECT COUNT(*) as attendance FROM attendance WHERE session_id = $test_session_id;</pre>";
?>