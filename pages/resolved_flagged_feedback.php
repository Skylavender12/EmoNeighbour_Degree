<?php
// resolved_flagged_feedback
session_start();
include __DIR__ . '/../config/config.php';

// ROLE ACCESS: Admin + Leader only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','leader'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$backLink = ($role === 'admin') ? 'admin_dashboard.php' : 'leader_dashboard.php';

// SEARCH
$search = '';
$whereSearch = '';

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search = trim($_GET['search']);
    $safe   = $conn->real_escape_string($search);

    $whereSearch = "
        AND (
            f.feedback_text LIKE '%$safe%' OR
            f.reason LIKE '%$safe%' OR
            f.zone LIKE '%$safe%' OR
            COALESCE(f.resolved_by_name, u.username, '') LIKE '%$safe%' OR
            COALESCE(f.resolved_by_role, u.role, '') LIKE '%$safe%'
        )
    ";
}

/*
IMPORTANT (for Q2 "last known username"):
You MUST have these columns in flagged_feedback:
- resolved_by_name (VARCHAR)
- resolved_by_role (VARCHAR)

Run once in phpMyAdmin:

ALTER TABLE flagged_feedback
  ADD COLUMN resolved_by_name VARCHAR(255) NULL,
  ADD COLUMN resolved_by_role VARCHAR(50) NULL;
*/

// FETCH RESOLVED FLAGGED FEEDBACK
$sql = "
    SELECT
        f.id AS flag_id,
        f.feedback_text,
        f.zone,
        f.reason,
        f.resolved_at,

        -- last-known snapshot first, then live users table, then fallback
        COALESCE(f.resolved_by_name, u.username, 'Unknown') AS resolver_name,
        COALESCE(f.resolved_by_role, u.role, 'Unknown')     AS resolver_role

    FROM flagged_feedback f
    LEFT JOIN users u ON f.resolved_by = u.id
    WHERE f.status = 'resolved'
    $whereSearch
    ORDER BY f.resolved_at DESC
";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resolved Flagged Feedback | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .page-wrap{ max-width:1100px; margin:0 auto; padding:25px; }

        .top-card{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:14px;
        }
        .top-title{
            margin:0;
            font-size:30px;
            font-weight:900;
            color:var(--text);
            display:flex;
            align-items:center;
            gap:10px;
        }
        .top-sub{
            margin:8px 0 0;
            font-weight:700;
            opacity:.78;
        }

        .btn-back{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            height:38px;
            padding:0 14px;
            border-radius:10px;
            border:1px solid rgba(0,0,0,0.14);
            background:#fff;
            color:#111;
            text-decoration:none;
            font-weight:900;
            white-space:nowrap;
        }
        .btn-back:hover{ background:rgba(0,0,0,0.04); }

        .search-row{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            align-items:center;
            margin-top:6px;
        }
        .search-row input{
            flex:1;
            min-width:240px;
            height:42px;
            padding:0 14px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,0.16);
            outline:none;
        }
        .search-row input:focus{
            border-color: rgba(25,118,210,0.65);
            box-shadow: 0 0 0 4px rgba(25,118,210,0.18);
        }
        .btn-search{
            height:42px;
            padding:0 16px;
            border:none;
            border-radius:12px;
            background:#1976d2;
            color:#fff;
            font-weight:900;
            cursor:pointer;
        }
        .btn-search:hover{ background:#1565c0; }

        .clear-link{
            text-decoration:none;
            font-weight:900;
            color:var(--link);
        }
        .clear-link:hover{ text-decoration:underline; }

        .resolved-card{ margin-top:14px; }

        .resolved-title{
            margin:0 0 6px;
            font-size:18px;
            font-weight:900;
            color:#2e7d32;
        }

        .meta{
            font-size:13px;
            opacity:.78;
            margin-bottom:10px;
            font-weight:700;
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            align-items:center;
        }

        .role-pill{
            display:inline-flex;
            align-items:center;
            padding:4px 10px;
            border-radius:999px;
            border:1px solid rgba(0,0,0,0.12);
            font-size:12px;
            font-weight:900;
            background:rgba(0,0,0,0.03);
            opacity:.9;
        }

        .reason{
            margin-top:10px;
            padding-top:10px;
            border-top:1px solid rgba(0,0,0,0.08);
        }

        .empty{
            padding:14px 0;
            font-weight:800;
            opacity:.75;
        }

        .to-top{
            position:fixed;
            right:18px;
            bottom:18px;
            width:44px;
            height:44px;
            border-radius:999px;
            border:1px solid rgba(0,0,0,0.14);
            background:#fff;
            box-shadow:0 10px 22px rgba(0,0,0,0.16);
            display:none;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            z-index:999;
            font-weight:900;
        }
        .to-top:hover{ background:rgba(0,0,0,0.05); }

        @media (max-width:520px){
            .top-card{ flex-direction:column; align-items:flex-start; }
            .btn-back{ width:100%; }
        }
    </style>
</head>

<body>
<div class="page-wrap">

    <div class="welcome-box top-card">
        <div>
            <h2 class="top-title">Resolved Flagged Feedback</h2>
            <p class="top-sub">These feedback entries have been reviewed and marked as resolved.</p>
        </div>
        <a class="btn-back" href="<?= $backLink ?>">← Back</a>
    </div>

    <div class="card">
        <h3 style="margin:0 0 10px;">Search</h3>

        <form method="GET" class="search-row">
            <input
                type="text"
                name="search"
                placeholder="Search feedback, reason, block, resolver name/role..."
                value="<?= htmlspecialchars($search) ?>"
            >
            <button class="btn-search" type="submit">Search</button>

            <?php if ($search !== ''): ?>
                <a class="clear-link" href="resolved_flagged_feedback.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()): ?>
            <div class="card resolved-card">
                <div class="resolved-title">Resolved Feedback</div>

                <div class="meta">
                    <span>Block: <b><?= htmlspecialchars($row['zone']) ?></b></span>

                    <span>
                        Resolved by:
                        <b><?= htmlspecialchars($row['resolver_name']) ?></b>
                        <span class="role-pill"><?= htmlspecialchars($row['resolver_role']) ?></span>
                    </span>

                    <span>
                        <?= $row['resolved_at']
                            ? date("d M Y, h:i A", strtotime($row['resolved_at']))
                            : '—'
                        ?>
                    </span>
                </div>

                <div>
                    <?= nl2br(htmlspecialchars($row['feedback_text'])) ?>
                </div>

                <div class="reason">
                    <b>Flag Reason:</b> <?= htmlspecialchars($row['reason']) ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <div class="empty">No resolved flagged feedback found.</div>
        </div>
    <?php endif; ?>

</div>

<button id="toTopBtn" class="to-top" title="Back to top">↑</button>

<script>
const btn = document.getElementById('toTopBtn');

window.addEventListener('scroll', () => {
  btn.style.display = (window.scrollY > 260) ? 'inline-flex' : 'none';
});

btn.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

</body>
</html>
