<?php
require_once "auth.php";
require_once "connect.php";

// Allow only faculty interns
if ($_SESSION['role'] !== 'faculty_intern') {
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

$firstName = $user['firstname'] ?? 'Faculty Intern';
$lastName = $user['lastname'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
if (empty($fullName)) {
    $fullName = $_SESSION['username'] ?? 'Faculty Intern';
}

// Fetch courses that faculty interns can access
$courses_stmt = $conn->prepare("
    SELECT c.id, c.course_code, c.course_name, c.description,
           u.firstname as faculty_firstname, u.lastname as faculty_lastname
    FROM courses c
    JOIN users1 u ON c.faculty_id = u.id
    WHERE c.status = 'active' OR c.status IS NULL
    ORDER BY c.course_code
    LIMIT 10
");

$courses = [];
if ($courses_stmt) {
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
    $courses_stmt->close();
}

// Fetch upcoming sessions for these courses (next 7 days)
$sessions_stmt = $conn->prepare("
    SELECT s.id, s.session_date, s.session_time, s.topic, s.location,
           c.course_code, c.course_name
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.session_date >= CURDATE() 
    AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.session_date, s.session_time
    LIMIT 10
");

$sessions = [];
if ($sessions_stmt) {
    $sessions_stmt->execute();
    $sessions_result = $sessions_stmt->get_result();
    $sessions = $sessions_result->fetch_all(MYSQLI_ASSOC);
    $sessions_stmt->close();
}

// Fetch recent attendance reports (simplified)
$reports_stmt = $conn->prepare("
    SELECT 
        u.firstname, u.lastname,
        c.course_name,
        ROUND(
            (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / 
            GREATEST(COUNT(DISTINCT s.id), 1)) * 100, 2
        ) as attendance_percentage,
        GROUP_CONCAT(DISTINCT a.notes SEPARATOR '; ') as recent_notes
    FROM users1 u
    JOIN enrollments e ON u.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN sessions s ON c.id = s.course_id
    LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = u.id
    WHERE (c.status = 'active' OR c.status IS NULL)
    AND e.enrollment_status = 'active'
    GROUP BY u.id, c.id
    ORDER BY attendance_percentage DESC
    LIMIT 10
");

$reports = [];
if ($reports_stmt) {
    $reports_stmt->execute();
    $reports_result = $reports_stmt->get_result();
    $reports = $reports_result->fetch_all(MYSQLI_ASSOC);
    $reports_stmt->close();
}

// Fetch pending auditor requests
$pending_auditors = [];

// First check if the table exists
$table_check = $conn->query("SHOW TABLES LIKE 'audit_requests'");

if ($table_check && $table_check->num_rows > 0) {
    // Table exists, fetch data
    $auditors_stmt = $conn->prepare("
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
        LIMIT 10
    ");
    
    if ($auditors_stmt) {
        $auditors_stmt->execute();
        $auditors_result = $auditors_stmt->get_result();
        $pending_auditors = $auditors_result->fetch_all(MYSQLI_ASSOC);
        $auditors_stmt->close();
    }
} else {
    // Table doesn't exist, we'll handle it in the view
    $pending_auditors = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Intern Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="fi-dashboard.css">
    <style>
        .table-notice {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="#courses">Course List</a></li>
            <li><a href="#sessions">Sessions</a></li>
            <li><a href="#reports">Reports</a></li>
            <li><a href="#auditors">Auditors</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <main>
        <h2>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h2>

        <section id="courses">
            <h3>Course List</h3>
            <ul id="courses-list">
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - 
                            <?php echo htmlspecialchars($course['course_name']); ?>
                            <br>
                            <small>Faculty: <?php echo htmlspecialchars($course['faculty_firstname'] . ' ' . $course['faculty_lastname']); ?></small>
                            <button onclick="viewCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">View</button>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No courses available. Please contact administrator.</li>
                <?php endif; ?>
            </ul>
        </section>

        <section id="sessions">
            <h3>Upcoming Sessions (Next 7 Days)</h3>
            <ul id="sessions-list">
                <?php if (count($sessions) > 0): ?>
                    <?php foreach ($sessions as $session): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($session['course_code']); ?></strong> – 
                            <?php echo date('d M Y', strtotime($session['session_date'])); ?> – 
                            <?php echo date('g:i A', strtotime($session['session_time'])); ?>
                            <?php if ($session['topic']): ?>
                                <br><small>Topic: <?php echo htmlspecialchars($session['topic']); ?></small>
                            <?php endif; ?>
                            <button onclick="markAttendance(<?php echo $session['id']; ?>, '<?php echo htmlspecialchars($session['course_name']); ?>', '<?php echo $session['session_date']; ?>')">Mark Attendance</button>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No upcoming sessions in the next 7 days.</li>
                <?php endif; ?>
            </ul>
        </section>
                <section id="attendance">
            <h3>Mark Attendance</h3>
            <div class="attendance-controls">
                <div class="filter-group">
                    <label for="filter_course">Filter by Course:</label>
                    <select id="filter_course" onchange="filterSessions()">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="filter_date" style="margin-left: 20px;">Filter by Date:</label>
                    <input type="date" id="filter_date" onchange="filterSessions()" 
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="sessions-container">
                <table id="attendance-sessions-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Topic</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sessions) > 0): ?>
                            <?php foreach ($sessions as $session): 
                                // Check if attendance is already marked for this session
                                $attendance_check = $conn->prepare("
                                    SELECT COUNT(*) as marked_count 
                                    FROM attendance 
                                    WHERE session_id = ?
                                ");
                                $attendance_check->bind_param("i", $session['id']);
                                $attendance_check->execute();
                                $attendance_result = $attendance_check->get_result();
                                $attendance_data = $attendance_result->fetch_assoc();
                                $attendance_check->close();
                                
                                $is_marked = $attendance_data['marked_count'] > 0;
                            ?>
                            <tr data-course-id="<?php echo $session['course_id']; ?>" 
                                data-session-date="<?php echo $session['session_date']; ?>">
                                <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($session['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($session['topic'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($session['location'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $is_marked ? 'status-present' : 'status-pending'; ?>">
                                        <?php echo $is_marked ? 'Marked' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="openAttendanceModal(<?php echo $session['id']; ?>, '<?php echo htmlspecialchars($session['course_name']); ?>', '<?php echo $session['session_date']; ?>')" 
                                            class="attendance-btn">
                                        📝 Mark Attendance
                                    </button>
                                    <?php if ($is_marked): ?>
                                        <button onclick="viewAttendance(<?php echo $session['id']; ?>)" 
                                                class="view-btn" style="margin-left: 5px;">
                                            👁️ View
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No sessions available for marking attendance.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="reports">
            <h3>Attendance Reports</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Attendance</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reports) > 0): ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['firstname'] . ' ' . $report['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($report['course_name']); ?></td>
                                <td><?php echo $report['attendance_percentage']; ?>%</td>
                                <td><?php echo htmlspecialchars(substr($report['recent_notes'] ?? '', 0, 50)) . (strlen($report['recent_notes'] ?? '') > 50 ? '...' : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No attendance data available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="auditors">
            <h3>Pending Auditor Requests</h3>
            <?php 
            $table_check = $conn->query("SHOW TABLES LIKE 'audit_requests'");
            if (!$table_check || $table_check->num_rows === 0): ?>
                <div class="table-notice">
                    <p><strong>⚠️ Auditor system not configured</strong></p>
                    <p>The audit_requests table doesn't exist. Click the button below to create it:</p>
                    <button onclick="createAuditTable()" style="background: #ffc107; color: #333; margin-top: 10px;">Create Auditor System</button>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Request Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pending_auditors) > 0): ?>
                            <?php foreach ($pending_auditors as $auditor): ?>
                                <tr data-request-id="<?php echo $auditor['request_id']; ?>">
                                    <td><?php echo htmlspecialchars($auditor['firstname'] . ' ' . $auditor['lastname']); ?></td>
                                    <td><?php echo htmlspecialchars($auditor['course_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($auditor['request_date'])); ?></td>
                                    <td>
                                        <button onclick="approveAuditorRequest(<?php echo $auditor['request_id']; ?>, '<?php echo htmlspecialchars($auditor['firstname'] . ' ' . $auditor['lastname']); ?>', '<?php echo htmlspecialchars($auditor['course_name']); ?>')">Approve</button>
                                        <button onclick="rejectAuditorRequest(<?php echo $auditor['request_id']; ?>, '<?php echo htmlspecialchars($auditor['firstname'] . ' ' . $auditor['lastname']); ?>', '<?php echo htmlspecialchars($auditor['course_name']); ?>')">Reject</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No pending auditor requests.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
    <script src="fi-dashboard.js"></script>
    <script>
    function createAuditTable() {
        Swal.fire({
            title: 'Create Auditor System?',
            html: `This will create the necessary database tables for auditor requests.<br><br>
                  <strong>Tables to create:</strong>
                  <ul style="text-align: left;">
                    <li>audit_requests</li>
                    <li>Sample demo data</li>
                  </ul>`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Create',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#32cd32'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Creating...',
                    text: 'Setting up auditor system',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Call API to create table
                fetch('api/create_audit_table.php')
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonColor: '#32cd32'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Failed to create table',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'Network error: ' + error.message,
                            icon: 'error'
                        });
                    });
            }
        });
    }
    </script>
</body>
</html>