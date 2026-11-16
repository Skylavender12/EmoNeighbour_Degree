<?php
session_start();
include '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Support & Help | EmoNeighbour</title>

    <!-- Use new dedicated CSS -->
    <link rel="stylesheet" href="/EmoNeighbour/assets/css/support.css">
</head>
<body>

<div class="page-container">
  <div class="shell">

    <h2 class="page-title">Help & Support</h2>

    <div class="support-box">
      <h3>Emergency Contacts</h3>

      <div class="info-row">
        <div class="info-label">Emergency Hotline:</div>
        <div class="info-value">999</div>
      </div>

      <div class="info-row">
        <div class="info-label">Police Station:</div>
        <div class="info-value">+603-1234-5678</div>
      </div>

      <div class="info-row">
        <div class="info-label">Hospital:</div>
        <div class="info-value">+603-9876-5432</div>
      </div>
    </div>

    <div class="support-box">
      <h3>Mental Health Support</h3>

      <div class="info-row">
        <div class="info-label">Befrienders Hotline:</div>
        <div class="info-value">03-7627 2929</div>
      </div>

      <div class="info-row">
        <div class="info-label">Mental Health Helpline:</div>
        <div class="info-value">1-800-88-2424</div>
      </div>
    </div>

    <div class="support-box">
      <h3>Community Help</h3>
      <p style="margin:0; line-height:1.6;">
        Your community leader can assist you with neighbourhood-related issues.
      </p>
    </div>

    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

  </div>
</div>

</body>
</html>
