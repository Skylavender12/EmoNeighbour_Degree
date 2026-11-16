<?php
session_start();
include '../config/config.php';
include '../config/audit.php';

// Only community leaders can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: login.php");
    exit();
}

$leader_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $title   = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);

    if ($title == "" || $content == "") {
        $message = "<div class='notice error'>All fields are required.</div>";
    } else {
        $sql = "INSERT INTO announcements (leader_id, title, message)
                VALUES ('$leader_id', '$title', '$content')";

        if ($conn->query($sql)) {
            $message = "<div class='notice success'>Announcement posted! ✔</div>";
            audit_log($conn, $leader_id, "Posted Announcement", "Title: $title");
        } else {
            $message = "<div class='notice error'>Database error: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Post Announcement | Community Leader</title>

    <!-- keep the same overall app look -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <!-- new shared form styling -->
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>

<div class="dash-container">
  <div class="form-wrap">
    <div class="card form-card">

      <h2 class="form-title">Post Announcement</h2>

      <?php echo $message; ?>

      <form method="POST">
          <div class="form-group">
            <label for="title">Title</label>
            <input id="title" class="form-control" type="text" name="title" required>
          </div>

          <div class="form-group">
            <label for="content">Message</label>
            <textarea id="content" class="form-textarea" name="content" required></textarea>
          </div>

          <div class="btn-row">
            <button class="btn-primary" type="submit">Publish</button>
          </div>
      </form>

      <div class="back-center">
        <a class="btn-secondary" href="leader_dashboard.php">← Back to Leader Dashboard</a>
      </div>

    </div>
  </div>
</div>

</body>
</html>
