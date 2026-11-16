<?php
session_start();
include '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName = htmlspecialchars($_SESSION['username']);

// ---------------- USER STATS ----------------
$totalUsers = 0;
$roleCounts = ['resident'=>0,'leader'=>0,'admin'=>0];

$resUsers = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
if ($resUsers) {
    while ($row = $resUsers->fetch_assoc()) {
        $r = $row['role'];
        if (isset($roleCounts[$r])) {
            $roleCounts[$r] = (int)$row['total'];
            $totalUsers += (int)$row['total'];
        }
    }
}

// ---------------- FEEDBACK STATS ----------------
$resFbTotal = $conn->query("SELECT COUNT(*) AS c FROM feedback");
$totalFeedback = ($resFbTotal && $row=$resFbTotal->fetch_assoc()) ? (int)$row['c'] : 0;

$startMonth = date('Y-m-01');
$endMonth   = date('Y-m-t');
$resFbMonth = $conn->query("SELECT COUNT(*) AS c FROM feedback WHERE created_at >= '$startMonth' AND created_at <= '$endMonth'");
$monthFeedback = ($resFbMonth && $row=$resFbMonth->fetch_assoc()) ? (int)$row['c'] : 0;

// ---------------- PENDING ALERTS ----------------
$resAlerts = $conn->query("SELECT COUNT(*) AS c FROM flagged_feedback WHERE status='pending'");
$pendingAlerts = ($resAlerts && $row=$resAlerts->fetch_assoc()) ? (int)$row['c'] : 0;

// ---------------- RECENT FEEDBACK (LAST 5) ----------------
$sqlRecent = "
    SELECT f.id, f.feedback_text, f.created_at, f.sentiment, f.domain, f.zone, u.username
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
    LIMIT 5
";
$resRecent = $conn->query($sqlRecent);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body>

<div class="dash-container">

    <!-- WELCOME / HEADER CARD -->
    <div class="welcome-box compact">

        <div class="brand-row">
            <img class="brand-logo" src="../assets/img/logo.png" alt="EmoNeighbour Logo">
            <div class="brand-name">EmoNeighbour</div>
        </div>

        <h2>Welcome, Admin <?= $adminName; ?> 👋</h2>
        <p class="muted-text" style="margin:0;">Overview of EmoNeighbour system status.</p>

        <!-- ACTION BUTTONS ROW (Logout now in the same row) -->
        <div class="action-bar" style="margin-top:14px;">

            <a class="action-btn primary" href="admin_users.php">Manage Users</a>
            <a class="action-btn primary" href="emotion_report.php">Emotional Report</a>

            <!-- Flagged button with badge -->
            <a class="action-btn danger" href="admin_flagged.php">
                Flagged Feedback
                <?php if ($pendingAlerts > 0): ?>
                    <span class="badge"><?= $pendingAlerts ?></span>
                <?php endif; ?>
            </a>

            <a class="action-btn" href="resolved_flagged_feedback.php">Resolved Feedback</a>
            <a class="action-btn" href="admin_audit.php">System Audit</a>

            <!-- Logout pushed to far right by CSS (.logout-btn { margin-left:auto; }) -->
            <a class="action-btn danger logout-btn" href="logout.php">Logout</a>
        </div>

    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">

        <div class="card">
            <h3>Total Users</h3>
            <div class="metric-num"><?= $totalUsers ?></div>

            <div class="muted-text">
                Residents: <b><?= $roleCounts['resident'] ?></b><br>
                Leaders: <b><?= $roleCounts['leader'] ?></b><br>
                Admins: <b><?= $roleCounts['admin'] ?></b>
            </div>
        </div>

        <div class="card">
            <h3>Feedback Activity</h3>
            <div class="muted-text">Total feedback:</div>
            <div class="metric-num" style="font-size:30px;"><?= $totalFeedback ?></div>

            <div class="muted-text" style="margin-top:10px;">This month:</div>
            <div class="metric-num" style="font-size:30px;"><?= $monthFeedback ?></div>
        </div>

        <div class="card">
            <h3>High-Risk Alerts</h3>
            <div class="muted-text">Pending flagged feedback:</div>
            <div class="metric-num danger-num"><?= $pendingAlerts ?></div>

            <div class="muted-text" style="margin-top:10px;">
                <a href="admin_flagged.php" style="text-decoration:none; color: var(--link); font-weight:900;">
                    Review flagged items →
                </a>
            </div>
        </div>

    </div>

    <!-- RECENT FEEDBACK -->
    <div class="card">
        <h3>Recent Feedback (Last 5)</h3>

        <div class="recent-list">
            <?php if ($resRecent && $resRecent->num_rows > 0): ?>
                <?php while ($row = $resRecent->fetch_assoc()): ?>
                    <?php
                        $uname = htmlspecialchars($row['username']);
                        $zone  = htmlspecialchars($row['zone']);
                        $sent  = htmlspecialchars($row['sentiment']);
                        $dom   = htmlspecialchars($row['domain'] ?: 'neutral');
                        $text  = nl2br(htmlspecialchars($row['feedback_text']));
                        $dt    = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '';
                    ?>
                    <div class="recent-item">
                        <div class="recent-head">
                            <?= $uname ?> (Block <?= $zone ?>) - <i><?= $sent ?> / <?= $dom ?></i>
                        </div>
                        <div class="recent-text"><?= $text ?></div>
                        <div class="recent-meta"><?= $dt ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="muted-text">No feedback yet.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>
