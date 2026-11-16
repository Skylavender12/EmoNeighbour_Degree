<?php
session_start();
include __DIR__ . '/../config/config.php';

// Ensure login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role   = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// --- ROLE-BASED BACK BUTTON ---
$backLink = "dashboard.php";
if ($role === "leader") {
    $backLink = "leader_dashboard.php";
} elseif ($role === "admin") {
    $backLink = "admin_dashboard.php";
}

// Fetch announcements with leader username
$sql = "
    SELECT a.id, a.title, a.message, a.created_at, a.leader_id,
           u.username AS leader_name
    FROM announcements a
    LEFT JOIN users u ON a.leader_id = u.id
    ORDER BY a.created_at DESC
";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Announcements | EmoNeighbour</title>

    <!-- absolute path to avoid CSS not loading -->
    <link rel="stylesheet" href="/EmoNeighbour/assets/css/announcement.css">
</head>
<body>

<div class="page-container">

    <div class="page-header">
        <div class="header-top">
            <h2 class="page-title">📢 Community Announcements</h2>
            <a class="back-btn" href="<?= $backLink ?>">← Back</a>
        </div>
        <p class="page-subtitle">Latest updates from community leaders.</p>
    </div>

    <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()): ?>
            <?php
                $leaderName = $row['leader_name'] ? $row['leader_name'] : 'Leader';

                // EDIT / DELETE permission
                $canEdit =
                    ($role === 'admin') ||
                    ($role === 'leader' && (int)$row['leader_id'] === (int)$userId);
            ?>

            <div class="announcement-card">
                <div class="announcement-header">
                    <?= htmlspecialchars($row['title']) ?>
                </div>

                <div class="announcement-meta">
                    <span class="meta-chip">👤 <?= htmlspecialchars($leaderName) ?></span>
                    <span class="meta-chip">🕒 <?= date("d M Y, h:i A", strtotime($row['created_at'])) ?></span>
                </div>

                <div class="announcement-body">
                    <?= nl2br(htmlspecialchars($row['message'])) ?>
                </div>

                <?php if ($canEdit): ?>
                    <div class="announcement-actions">
                        <a href="edit_announcement.php?id=<?= (int)$row['id'] ?>" class="btn-edit">Edit</a>
                        <a href="delete_announcement.php?id=<?= (int)$row['id'] ?>"
                           class="btn-delete"
                           onclick="return confirm('Delete this announcement?');">
                           Delete
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-card">
            <strong>No announcements yet</strong>
            <div style="margin-top:8px;">No announcements have been posted yet.</div>
        </div>
    <?php endif; ?>



</div>

</body>
</html>
