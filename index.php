<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Not logged in
    header("Location: login.html");
    exit();
}

// Redirect based on role
switch ($_SESSION['role']) {
    case 'student':
        header("Location: studentDashboard.php");
        break;
    case 'faculty':
        header("Location: facultyDashboard.php");
        break;
    case 'faculty_intern':
        header("Location: facultyInternDashboard.php");
        break;
    default:
        header("Location: login.html");
        break;
}
exit();
