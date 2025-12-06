<?php
require_once "auth.php";
require_once "connect.php";

// Allow only faculty 
if ($_SESSION['role'] !== 'faculty') {
    header("Location: login.html");
    exit();
}

// Fetch user details from database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT firstname, lastname FROM users1 WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$firstName = $user['firstname'] ?? 'Faculty';
$lastName = $user['lastname'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
if (empty($fullName)) {
    $fullName = $_SESSION['username'] ?? 'Faculty';
}

// Fetch faculty's courses from database
$courses_stmt = $conn->prepare("SELECT id, course_code, course_name, description, created_at 
                               FROM courses 
                               WHERE faculty_id = ? AND status = 'active'
                               ORDER BY created_at DESC");
$courses_stmt->bind_param("i", $user_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);

// Fetch recent sessions
$sessions_stmt = $conn->prepare("
    SELECT s.id, s.session_date, s.topic, c.course_name, 
           COUNT(a.id) as attendance_count
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    LEFT JOIN attendance a ON s.id = a.session_id AND a.status = 'present'
    WHERE c.faculty_id = ?
    GROUP BY s.id
    ORDER BY s.session_date DESC
    LIMIT 5
");
$sessions_stmt->bind_param("i", $user_id);
$sessions_stmt->execute();
$sessions_result = $sessions_stmt->get_result();
$sessions = $sessions_result->fetch_all(MYSQLI_ASSOC);

// Fetch attendance reports
// In facultyDashboard.php - update the reports query
$reports_stmt = $conn->prepare("
    SELECT 
        u.firstname, u.lastname,
        c.course_name,
        ROUND(
            (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / 
            COUNT(DISTINCT s.id)) * 100, 2
        ) as attendance_percentage,
        CASE 
            WHEN ROUND(
                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / 
                COUNT(DISTINCT s.id)) * 100, 2
            ) >= 90 THEN 'Excellent'
            WHEN ROUND(
                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / 
                COUNT(DISTINCT s.id)) * 100, 2
            ) >= 75 THEN 'Good'
            WHEN ROUND(
                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / 
                COUNT(DISTINCT s.id)) * 100, 2
            ) >= 60 THEN 'Fair'
            ELSE 'Poor'
        END as participation
    FROM users1 u
    JOIN enrollments e ON u.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN sessions s ON c.id = s.course_id
    LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = u.id
    WHERE c.faculty_id = ? 
    AND e.enrollment_status = 'active'  -- ADD THIS LINE
    GROUP BY u.id, c.id
    LIMIT 10
");
$reports_stmt->bind_param("i", $user_id);
$reports_stmt->execute();
$reports_result = $reports_stmt->get_result();
$reports = $reports_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="faculty-dashboard.css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="#courses">Course Management</a></li>
            <li><a href="#sessions">Session Overview</a></li>
            <li><a href="#reports">Attendance Reports</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <main>
        <h2>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h2>

        <section id="courses">
            <h3>Course Management</h3>
            <button onclick="showCreateCourseModal()">Create New Course</button>
            <ul id="courses-list">
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - 
                            <?php echo htmlspecialchars($course['course_name']); ?>
                            <br>
                            <small><?php echo htmlspecialchars($course['description']); ?></small>
                            <button onclick="editCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">Edit</button>
                            <button onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">Delete</button>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No courses found. Create your first course!</li>
                <?php endif; ?>
            </ul>
        </section>

        <section id="sessions">
            <h3>Recent Sessions</h3>
            <button onclick="createSession()">Create New Session</button>
            <ul id="sessions-list">
                <?php if (count($sessions) > 0): ?>
                    <?php foreach ($sessions as $session): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($session['course_name']); ?></strong> – 
                            <?php echo date('d M Y', strtotime($session['session_date'])); ?> – 
                            <?php echo htmlspecialchars($session['attendance_count']); ?> Students Attended
                            <?php if ($session['topic']): ?>
                                <br><small>Topic: <?php echo htmlspecialchars($session['topic']); ?></small>
                            <?php endif; ?>
                            <button onclick="markAttendance(<?php echo $session['id']; ?>)">Mark Attendance</button>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No sessions found. Create your first session!</li>
                <?php endif; ?>
            </ul>
        </section>

        <section id="reports">
            <h3>Attendance Reports</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Attendance</th>
                        <th>Participation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reports) > 0): ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['firstname'] . ' ' . $report['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($report['course_name']); ?></td>
                                <td><?php echo $report['attendance_percentage']; ?>%</td>
                                <td><?php echo $report['participation']; ?></td>
                                <td>
                                    <button onclick="viewStudentReport(<?php echo $user_id; ?>, '<?php echo htmlspecialchars($report['course_name']); ?>')">Details</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No attendance data available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
    <script src="faculty-dashboard.js"></script>
</body>
</html>