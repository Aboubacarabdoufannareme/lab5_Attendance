<?php
require_once "auth.php";
require_once "connect.php";

// Allow only students
if ($_SESSION['role'] !== 'student') {
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

$firstName = $user['firstname'] ?? 'Student';
$lastName = $user['lastname'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
if (empty($fullName)) {
    $fullName = $_SESSION['username'] ?? 'Student';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="student-dashboard.css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="#my-courses">My Courses</a></li>
            <li><a href="#sessions">Session Schedule</a></li>
            <li><a href="#reports">Reports / Grades</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <main>
        <h2>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h2>

        <section id="my-courses">
            <h3>My Courses</h3>
            <ul>
                <li>Math 101 <button>View</button> <button>Join as Auditor</button></li>
                <li>Physics 102 <button>View</button> <button>Join as Auditor</button></li>
            </ul>
        </section>

        <section id="sessions">
            <h3>Session Schedule</h3>
            <ul>
                <li>Math 101 – 25 Nov 2025 – <button>Mark Attendance</button></li>
                <li>Physics 102 – 26 Nov 2025 – <button>Mark Attendance</button></li>
            </ul>
        </section>

        <section id="reports">
            <h3>Attendance & Feedback</h3>
            <table>
                <tr><th>Course</th><th>Attended</th><th>Total</th><th>Percentage</th><th>Feedback</th></tr>
                <tr><td>Math 101</td><td>8</td><td>10</td><td>80%</td><td>Good Participation</td></tr>
            </table>
        </section>
    </main>
    <script src="student-dashboard.js"></script>
</body>
</html>