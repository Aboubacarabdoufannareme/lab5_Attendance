<?php
// auth.php - Make it session-safe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Optional: You can also check role if needed
// Example: prevent students from accessing faculty dashboard
// if ($_SESSION['role'] !== 'faculty') {
//     header("Location: unauthorized.html");
//     exit();
// }




//session_start();

// If user is not logged in, redirect to login page
//if (!isset($_SESSION['user_id'])) {
 //   header("Location: login.html");
  //  exit();
//}

// Optional: You can also check role if needed
// Example: prevent students from accessing faculty dashboard
// if ($_SESSION['role'] !== 'faculty') {
//     header("Location: unauthorized.html");
//     exit();
// }
?>



