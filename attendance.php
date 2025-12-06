<?php
// attendance.php - CORRECTED VERSION
require_once "auth.php";
require_once "connect.php";

// Allow only faculty
if ($_SESSION['role'] !== 'faculty') {
    header("Location: login.html");
    exit();
}

// Get session ID from URL
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id <= 0) {
    die("Invalid session ID.");
}

// Get session details
$stmt = $conn->prepare("
    SELECT s.*, c.course_name, c.course_code, u.firstname, u.lastname
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    JOIN users1 u ON s.created_by = u.id
    WHERE s.id = ? AND c.faculty_id = ?
");
$stmt->bind_param("ii", $session_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Session not found or you don't have permission to mark attendance.");
}

$session = $result->fetch_assoc();
$stmt->close();

// Get enrolled students for this course - UPDATED QUERY
$students_stmt = $conn->prepare("
    SELECT u.id, u.firstname, u.lastname, u.email, 
           IFNULL(a.status, 'absent') as attendance_status,
           a.notes as attendance_notes
    FROM enrollments e
    JOIN users1 u ON e.student_id = u.id
    LEFT JOIN attendance a ON e.student_id = a.student_id AND a.session_id = ?
    WHERE e.course_id = ? 
    AND e.enrollment_status = 'active'  -- CHANGED HERE
    AND u.role = 'student'
    ORDER BY u.lastname, u.firstname
");

$students_stmt->bind_param("ii", $session_id, $session['course_id']);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = $students_result->fetch_all(MYSQLI_ASSOC);
$students_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['attendance'])) {
        $attendance_data = $_POST['attendance'];
        $marked_by = $_SESSION['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            foreach ($attendance_data as $student_id => $data) {
                $student_id = intval($student_id);
                $status = $conn->real_escape_string($data['status']);
                $notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';
                
                // Check if attendance record already exists
                $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE session_id = ? AND student_id = ?");
                $check_stmt->bind_param("ii", $session_id, $student_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing record
                    $update_stmt = $conn->prepare("
                        UPDATE attendance 
                        SET status = ?, notes = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP 
                        WHERE session_id = ? AND student_id = ?
                    ");
                    $update_stmt->bind_param("ssiii", $status, $notes, $marked_by, $session_id, $student_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Insert new record
                    $insert_stmt = $conn->prepare("
                        INSERT INTO attendance (session_id, student_id, status, notes, marked_by) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->bind_param("iissi", $session_id, $student_id, $status, $notes, $marked_by);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                
                $check_stmt->close();
            }
            
            $conn->commit();
            $success_message = "Attendance saved successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Failed to save attendance: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance - <?php echo htmlspecialchars($session['course_name']); ?></title>
    <style>
        /* Keep your existing CSS styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(255, 71, 87, 0.2);
        }
        
        .header {
            background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .attendance-form {
            margin-top: 20px;
        }
        
        .student-row {
            display: grid;
            grid-template-columns: 40px 2fr 1fr 1fr 2fr;
            gap: 15px;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .student-row:hover {
            background: #fff5f5;
        }
        
        .student-row:nth-child(even) {
            background: #fffafa;
        }
        
        .student-header {
            font-weight: bold;
            background: #ffefef;
            border-radius: 5px;
        }
        
        .status-select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 2px solid #ddd;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .status-select:focus {
            border-color: #ff4757;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.2);
        }
        
        .status-present { border-color: #2ed573; background: #f1fff7; }
        .status-absent { border-color: #ff4757; background: #fff5f5; }
        .status-late { border-color: #ff9f43; background: #fff9f2; }
        .status-excused { border-color: #3742fa; background: #f5f6ff; }
        
        .notes-input {
            padding: 8px 12px;
            border-radius: 5px;
            border: 2px solid #ddd;
            width: 100%;
            box-sizing: border-box;
        }
        
        .notes-input:focus {
            border-color: #ff4757;
            outline: none;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 30px;
            transition: all 0.3s;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 71, 87, 0.3);
        }
        
        .back-btn {
            display: inline-block;
            background: #f1f2f6;
            color: #2f3542;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: #dfe4ea;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ff4757;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="facultyDashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="header">
            <h1>📝 Mark Attendance</h1>
            <p>
                <strong>Course:</strong> <?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?> |
                <strong>Date:</strong> <?php echo date('F j, Y', strtotime($session['session_date'])); ?> |
                <strong>Time:</strong> <?php echo $session['session_time'] ? date('g:i A', strtotime($session['session_time'])) : 'N/A'; ?> |
                <strong>Topic:</strong> <?php echo htmlspecialchars($session['topic'] ?: 'N/A'); ?>
            </p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="message success">✅ <?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error">❌ <?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Session ID: <?php echo $session_id; ?><br>
            Course ID: <?php echo $session['course_id']; ?><br>
            Students Found: <?php echo count($students); ?>
        </div>
        
        <form method="POST" class="attendance-form">
            <div class="student-row student-header">
                <div>#</div>
                <div>Student Name</div>
                <div>Student ID</div>
                <div>Status</div>
                <div>Notes</div>
            </div>
            
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $index => $student): ?>
                <div class="student-row">
                    <div><?php echo $index + 1; ?></div>
                    <div><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></div>
                    <div><?php echo htmlspecialchars($student['email']); ?></div>
                    <div>
                        <select name="attendance[<?php echo $student['id']; ?>][status]" 
                                class="status-select status-<?php echo $student['attendance_status']; ?>"
                                onchange="this.className='status-select status-'+this.value">
                            <option value="present" <?php echo $student['attendance_status'] == 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="absent" <?php echo $student['attendance_status'] == 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="late" <?php echo $student['attendance_status'] == 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="excused" <?php echo $student['attendance_status'] == 'excused' ? 'selected' : ''; ?>>Excused</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" 
                               name="attendance[<?php echo $student['id']; ?>][notes]" 
                               class="notes-input" 
                               placeholder="Optional notes..."
                               value="<?php echo htmlspecialchars($student['attendance_notes'] ?: ''); ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>⚠️ No active students enrolled in this course.</h3>
                    <p>Make sure:</p>
                    <ol style="text-align: left; display: inline-block;">
                        <li>Students are enrolled in this course</li>
                        <li>Their enrollment status is 'active'</li>
                        <li>Students have role = 'student' in users table</li>
                    </ol>
                </div>
            <?php endif; ?>
            
            <?php if (count($students) > 0): ?>
                <button type="submit" class="submit-btn">💾 Save Attendance</button>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        // Add color coding to status dropdowns on load
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select');
            statusSelects.forEach(select => {
                select.className = 'status-select status-' + select.value;
            });
        });
    </script>
</body>
</html>