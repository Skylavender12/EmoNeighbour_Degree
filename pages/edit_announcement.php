<?php
session_start();
include __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','leader'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: announcements.php");
    exit();
}

// Fetch announcement
$sql = "SELECT * FROM announcements WHERE id = $id";
$res = $conn->query($sql);

if (!$res || $res->num_rows === 0) {
    header("Location: announcements.php");
    exit();
}

$announcement = $res->fetch_assoc();

// Permission check
if ($role === 'leader' && $announcement['leader_id'] != $userId) {
    header("Location: announcements.php");
    exit();
}

$errorMsg = "";

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']);
    $message = trim($_POST['message']);

    if ($title !== '' && $message !== '') {
        $title   = $conn->real_escape_string($title);
        $message = $conn->real_escape_string($message);

        $conn->query("
            UPDATE announcements
            SET title='$title', message='$message'
            WHERE id=$id
        ");

        header("Location: announcements.php");
        exit();
    } else {
        $errorMsg = "<div class='notice error'>Title and message cannot be empty.</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Announcement | EmoNeighbour</title>

    <!-- consistent app look -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>

<div class="dash-container">
  <div class="form-wrap">
    <div class="card form-card">

      <h2 class="form-title">Edit Announcement</h2>

      <?php echo $errorMsg; ?>

      <form method="POST">
          <div class="form-group">
            <label for="title">Title</label>
            <input
              id="title"
              class="form-control"
              type="text"
              name="title"
              value="<?= htmlspecialchars($announcement['title']) ?>"
              required
            >
          </div>

          <div class="form-group">
            <label for="message">Message</label>
            <textarea
              id="message"
              class="form-textarea"
              name="message"
              required
            ><?= htmlspecialchars($announcement['message']) ?></textarea>
          </div>

          <div class="btn-row">
            <button class="btn-primary" type="submit">Save Changes</button>
          </div>
      </form>

      <div class="back-center">
        <a class="btn-secondary" href="announcements.php">← Cancel</a>
      </div>

    </div>
  </div>
</div>

</body>
</html>
