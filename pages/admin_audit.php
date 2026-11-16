<?php
session_start();
include '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// FILTERS
$filterUser   = isset($_GET['user']) ? $_GET['user'] : 'ALL';
$filterAction = isset($_GET['action']) ? $_GET['action'] : 'ALL';
$filterDate   = isset($_GET['date']) ? $_GET['date'] : '';

$where = [];
if ($filterUser !== 'ALL') {
    $where[] = "a.user_id = " . (int)$filterUser;
}
if ($filterAction !== 'ALL') {
    $where[] = "a.action = '" . $conn->real_escape_string($filterAction) . "'";
}
if ($filterDate !== '') {
    $where[] = "DATE(a.created_at) = '" . $conn->real_escape_string($filterDate) . "'";
}

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch logs (IMPORTANT: LEFT JOIN so logs remain even if user is deleted / user_id is NULL)
$sql = "
    SELECT 
        a.*,
        u.username,
        u.role
    FROM audit_log a
    LEFT JOIN users u ON a.user_id = u.id
    $whereSql
    ORDER BY a.created_at DESC
    LIMIT 300
";
$res = $conn->query($sql);

// Fetch all users for dropdown
$userList = $conn->query("SELECT id, username FROM users ORDER BY username");

// Fetch all actions for dropdown
$actionList = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>System Audit Logs | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .page-wrap{ max-width:1100px; margin:0 auto; padding:25px; }

        /* top header card */
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

        /* filter card layout */
        .filter-row{
            display:grid;
            grid-template-columns: 1.2fr 1.2fr 0.9fr auto;
            gap:14px;
            align-items:end;
        }

        .f-item label{
            display:block;
            font-weight:900;
            margin-bottom:6px;
        }

        .f-item select,
        .f-item input{
            width:100%;
            height:42px;
            padding:0 14px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,0.16);
            outline:none;
            background:#fff;
        }

        .f-item select:focus,
        .f-item input:focus{
            border-color: rgba(25,118,210,0.65);
            box-shadow: 0 0 0 4px rgba(25,118,210,0.18);
        }

        .f-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .btn-primary{
            height:42px;
            padding:0 16px;
            border:none;
            border-radius:12px;
            background:#1976d2;
            color:#fff;
            font-weight:900;
            cursor:pointer;
        }
        .btn-primary:hover{ background:#1565c0; }

        .btn-ghost{
            height:42px;
            padding:0 16px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,0.14);
            background:#fff;
            font-weight:900;
            text-decoration:none;
            color:#111;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }
        .btn-ghost:hover{ background:rgba(0,0,0,0.04); }

        /* logs */
        .log-card{ margin-top:14px; }
        .log-head{
            display:flex;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            align-items:center;
        }
        .who{
            font-weight:900;
        }
        .role-pill{
            display:inline-flex;
            align-items:center;
            padding:4px 10px;
            border-radius:999px;
            border:1px solid rgba(0,0,0,0.12);
            font-size:12px;
            font-weight:900;
            opacity:.85;
            margin-left:8px;
            background:rgba(0,0,0,0.03);
        }
        .action{
            font-weight:900;
            color:#0055aa;
            margin-top:10px;
        }
        .details{
            margin-top:8px;
            line-height:1.45;
        }
        .time{
            font-size:12px;
            opacity:.7;
            font-weight:700;
        }
        .empty{
            padding:14px 0;
            font-weight:800;
            opacity:.75;
        }

        /* floating back-to-top */
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

        @media (max-width: 980px){
            .filter-row{ grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 520px){
            .top-card{ flex-direction:column; align-items:flex-start; }
            .btn-back{ width:100%; }
            .filter-row{ grid-template-columns: 1fr; }
            .f-actions{ width:100%; }
            .btn-primary, .btn-ghost{ width:100%; }
        }
    </style>
</head>
<body>

<div class="page-wrap">

    <!-- TOP HEADER CARD -->
    <div class="welcome-box top-card">
        <div>
            <h2 class="top-title">📜 System Audit Logs</h2>
            <p class="top-sub">Monitor all actions taken by Admins, Leaders, and Users.</p>
        </div>

        <a class="btn-back" href="admin_dashboard.php">← Back</a>
    </div>

    <!-- FILTER CARD -->
    <div class="card">
        <form method="GET" class="filter-row">

            <div class="f-item">
                <label>User</label>
                <select name="user">
                    <option value="ALL">All Users</option>
                    <?php while ($u = $userList->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>" <?= ($filterUser==$u['id']?"selected":"") ?>>
                            <?= htmlspecialchars($u['username']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="f-item">
                <label>Action</label>
                <select name="action">
                    <option value="ALL">All Actions</option>
                    <?php while ($a = $actionList->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>" <?= ($filterAction==$a['action']?"selected":"") ?>>
                            <?= htmlspecialchars($a['action']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="f-item">
                <label>Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
            </div>

            <div class="f-actions">
                <button class="btn-primary" type="submit">Filter</button>
                <a class="btn-ghost" href="admin_audit.php">Reset</a>
            </div>

        </form>
    </div>

    <!-- LOG LIST -->
    <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()): ?>
            <?php
                $displayName = $row['username'] ? $row['username'] : 'Deleted User';
                $displayRole = $row['role'] ? $row['role'] : 'N/A';
            ?>
            <div class="card log-card">
                <div class="log-head">
                    <div class="who">
                        <?= htmlspecialchars($displayName) ?>
                        <span class="role-pill"><?= htmlspecialchars($displayRole) ?></span>
                    </div>
                    <div class="time"><?= htmlspecialchars($row['created_at']) ?></div>
                </div>

                <div class="action"><?= htmlspecialchars($row['action']) ?></div>

                <?php if (trim((string)$row['details']) !== ''): ?>
                    <div class="details"><?= nl2br(htmlspecialchars($row['details'])) ?></div>
                <?php endif; ?>

                <?php if (is_null($row['user_id'])): ?>
                    <div class="details" style="opacity:.75; margin-top:10px;">
                        (User account was deleted; audit record preserved.)
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <div class="empty">No logs found for this filter.</div>
        </div>
    <?php endif; ?>

</div>

<!-- floating back-to-top -->
<button id="toTopBtn" class="to-top" title="Back to top">↑</button>

<script>
const btn = document.getElementById('toTopBtn');

window.addEventListener('scroll', () => {
  if (window.scrollY > 260) btn.style.display = 'inline-flex';
  else btn.style.display = 'none';
});

btn.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

</body>
</html>
