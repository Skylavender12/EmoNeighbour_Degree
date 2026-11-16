<?php
session_start();
include '../config/config.php';

// Only allow residents
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Resident Dashboard | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="container">
    <h2>Welcome, <?php echo $username; ?> 👋</h2>
    <p>You can submit your emotional feedback or view the overall neighbourhood emotion summary.</p>

    <a class="btn" href="feedback.php">Submit Feedback</a>
    <a class="btn btn-green" href="emotion_report.php">View Emotion Report</a>

    <br><br>
    <a href="logout.php">Logout</a>
</div>

</body>
</html>
