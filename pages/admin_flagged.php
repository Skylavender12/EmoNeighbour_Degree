<?php
session_start();
include __DIR__ . '/../config/config.php';

// ROLE ACCESS: Leader + Admin can view alerts
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

if (!in_array($role, ['leader', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

// --- ROLE-BASED BACK BUTTON ---
$backLink = "dashboard.php";
if ($role === "leader") {
    $backLink = "leader_dashboard.php";
} elseif ($role === "admin") {
    $backLink = "admin_dashboard.php";
}

// --- SEARCH HANDLING ---
$search = '';
$whereSearch = '';

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search = trim($_GET['search']);
    $safe = $conn->real_escape_string($search);

    $whereSearch = "
        AND (
            fb.feedback_text LIKE '%$safe%' OR
            fb.flag_reason LIKE '%$safe%' OR
            fb.zone LIKE '%$safe%' OR
            u.username LIKE '%$safe%'
        )
    ";
}

// Fetch flagged feedback (PENDING ONLY)
$sql = "
    SELECT f.id AS flag_id,
           fb.feedback_text,
           fb.zone,
           fb.flag_reason,
           fb.created_at,
           u.username
    FROM flagged_feedback f
    JOIN feedback fb ON fb.id = f.feedback_id
    LEFT JOIN users u ON fb.user_id = u.id
    WHERE f.status = 'pending'
    $whereSearch
    ORDER BY f.created_at DESC
";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>High-Risk Alerts | EmoNeighbour</title>

    <!-- keep your existing base styling -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <!-- NEW: page-specific styling -->
    <link rel="stylesheet" href="../assets/css/alerts.css">
</head>
<body>

<div class="dash-container">

    <!-- HEADER CARD -->
    <div class="card alerts-hero">
        <div class="alerts-header-top">
            <div class="alerts-title-row">
                <div class="alerts-title">⚠ High-Risk Alerts</div>
                <div class="alerts-sub">Flagged feedback requiring review</div>
            </div>

            <!-- ✅ Back button EXACTLY at top-right (your red box area) -->
            <a class="btn-secondary back-top" href="<?= $backLink ?>">← Back</a>
        </div>

        <div class="alerts-desc">
            These entries were flagged by the system for sensitive or harmful content.
        </div>
    </div>

    <!-- SEARCH CARD -->
    <div class="card search-card">
        <form method="GET" class="search-row">
            <div class="search-left">
                <label class="search-label" for="searchInput">Search</label>
                <input
                    id="searchInput"
                    class="search-input"
                    type="text"
                    name="search"
                    placeholder="Search feedback, reason, zone, or resident…"
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>

            <div class="search-actions">
                <button class="btn-primary" type="submit">Search</button>

                <?php if ($search !== ''): ?>
                    <a class="btn-secondary" href="admin_flagged.php">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ALERT LIST -->
    <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()): ?>
            <div class="card alert-card">
                <div class="alert-top">
                    <div class="alert-title">Flagged Feedback</div>
                    <div class="alert-meta">
                        <span>Resident: <b><?= htmlspecialchars($row['username']) ?></b></span>
                        <span class="dot">•</span>
                        <span>Block: <b><?= htmlspecialchars($row['zone']) ?></b></span>
                        <span class="dot">•</span>
                        <span><?= date("d M Y, h:i A", strtotime($row['created_at'])) ?></span>
                    </div>
                </div>

                <div class="alert-body">
                    <div class="alert-section">
                        <div class="alert-label">Feedback</div>
                        <div class="alert-text"><?= nl2br(htmlspecialchars($row['feedback_text'])) ?></div>
                    </div>

                    <div class="alert-section">
                        <div class="alert-label">Reason</div>
                        <div class="alert-reason"><?= htmlspecialchars($row['flag_reason']) ?></div>
                    </div>
                </div>

                <div class="alert-actions">
                    <form method="POST" action="resolve_flag.php">
                        <input type="hidden" name="id" value="<?= (int)$row['flag_id'] ?>">
                        <button type="submit" class="btn-success">Mark as Resolved</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card empty-card">
            <div class="empty-title">No high-risk alerts found.</div>
            <div class="empty-sub">If new flagged feedback appears, it will show up here.</div>
        </div>
    <?php endif; ?>

</div>

<!-- Floating "scroll to top" button -->
<button id="toTopBtn" class="to-top" aria-label="Back to top" title="Back to top">↑</button>

<script>
(function () {
    const btn = document.getElementById('toTopBtn');

    function toggle() {
        if (window.scrollY > 250) btn.classList.add('show');
        else btn.classList.remove('show');
    }

    window.addEventListener('scroll', toggle, { passive: true });
    toggle();

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>

</body>
</html>
