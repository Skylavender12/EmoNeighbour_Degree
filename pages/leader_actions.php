<?php
session_start();
include '../config/config.php';

// Allow ONLY leaders & admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['leader','admin'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$backLink = ($role === 'admin') ? 'admin_dashboard.php' : 'leader_dashboard.php';

// ---------------- HANDLE ACTION SUBMISSION (LEADER ONLY) ----------------
$msg = '';

if ($role === 'leader' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $zone = $_POST['zone'] ?? '';
    $text = trim($_POST['action_text'] ?? '');

    if ($zone && $text !== '') {
        $safeText = $conn->real_escape_string($text);
        $safeZone = $conn->real_escape_string($zone);
        $monthYear = date('Y-m');

        $conn->query("
            INSERT INTO leader_actions (leader_id, zone, month_year, action_text, created_at)
            VALUES ({$_SESSION['user_id']}, '$safeZone', '$monthYear', '$safeText', NOW())
        ");

        $msg = "Action logged successfully.";
    } else {
        $msg = "Action description cannot be empty.";
    }
}

// ---------------- FETCH ALL LEADER ACTIONS ----------------
$sql = "
    SELECT 
        la.zone,
        la.action_text,
        la.created_at,
        u.username AS leader_name
    FROM leader_actions la
    JOIN users u ON la.leader_id = u.id
    ORDER BY la.created_at DESC
";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Leader Actions | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Page wrapper */
        .la-wrap{
            max-width: 1100px;
            margin: 0 auto;
            padding: 25px;
        }

        /* Top header card */
        .la-header{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
        }

        .la-title{
            margin:0;
            font-size:32px;
            font-weight:900;
        }

        .la-subtitle{
            margin:6px 0 0;
            font-weight:700;
            opacity:0.8;
        }

        .la-back{
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
        .la-back:hover{ background: rgba(0,0,0,0.04); }
        .la-back:focus{
            outline:3px solid rgba(9, 132, 227, 0.45);
            outline-offset:2px;
        }

        /* Form layout (STACK zone ABOVE textarea) */
        .la-form{
            margin-top:6px;
        }

        .field{
            display:flex;
            flex-direction:column;
            gap:8px;
            margin-bottom:14px;
        }

        .field label{
            font-weight:900;
        }

        .field select,
        .field textarea{
            border-radius:10px;
            border:1px solid rgba(0,0,0,0.18);
            padding:10px 12px;
            font-size:14px;
            outline:none;
            background:#fff;
        }

        .field select{
            height:42px;
            max-width:260px; /* keeps it neat */
        }

        .field textarea{
            min-height:140px; /* adjust if you want shorter */
            resize: vertical;
        }

        .field select:focus,
        .field textarea:focus{
            border-color: rgba(25,118,210,0.65);
            box-shadow: 0 0 0 4px rgba(25,118,210,0.18);
        }

        .la-actions{
            display:flex;
            justify-content:flex-end; /* button to the right */
            margin-top:10px;
        }

        .la-btn{
            height:42px;
            padding:0 18px;
            border:none;
            border-radius:12px;
            background:#1976d2;
            color:#fff;
            font-weight:900;
            cursor:pointer;
        }
        .la-btn:hover{ background:#1565c0; }
        .la-btn:focus{
            outline:3px solid rgba(9, 132, 227, 0.45);
            outline-offset:2px;
        }

        .la-msg{
            margin:10px 0 0;
            font-weight:900;
            opacity:0.85;
        }

        /* List styling */
        .la-list-note{
            margin:0 0 12px;
            opacity:0.75;
            font-weight:700;
        }

        .la-item{
            padding:12px 0;
            border-top:1px solid rgba(0,0,0,0.08);
        }
        .la-item:first-of-type{ border-top:none; padding-top:0; }

        .la-item-head{
            display:flex;
            align-items:baseline;
            gap:8px;
            flex-wrap:wrap;
        }

        .la-item-name{
            font-weight:900;
        }
        .la-item-zone{
            font-weight:900;
            opacity:0.75;
        }
        .la-item-date{
            margin-top:4px;
            font-size:13px;
            opacity:0.7;
            font-weight:700;
        }

        /* Responsive */
        @media (max-width: 640px){
            .la-header{ flex-direction:column; }
            .field select{ max-width:100%; }
            .la-actions{ justify-content:stretch; }
            .la-btn{ width:100%; }
        }
    </style>
</head>
<body>

<div class="la-wrap">

    <!-- HEADER CARD -->
    <div class="welcome-box la-header">
        <div>
            <h1 class="la-title">Leader Actions</h1>
            <p class="la-subtitle">Log actions taken by community leaders and review history.</p>
        </div>

        <a class="la-back" href="<?= $backLink ?>">← Back</a>
    </div>

    <!-- ACTION FORM (LEADER ONLY) -->
    <?php if ($role === 'leader'): ?>
        <div class="card">
            <h3 style="margin-top:0;">Log New Action</h3>

            <?php if ($msg): ?>
                <div class="la-msg"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form class="la-form" method="POST">
                <!-- ✅ Zone ABOVE -->
                <div class="field">
                    <label>Block</label>
                    <select name="zone" required>
                        <option value="A">Block A</option>
                        <option value="B">Block B</option>
                        <option value="C">Block C</option>
                        <option value="D">Block D</option>
                    </select>
                </div>

                <div class="field">
                    <label>Action Description</label>
                    <textarea name="action_text" required
                        placeholder="Describe the action taken, outcome, and next steps (if any)..."></textarea>
                </div>

                <div class="la-actions">
                    <button class="la-btn" type="submit">Log Action</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- ALL LEADERS ACTIONS -->
    <div class="card">
        <h3 style="margin-top:0;">All Leader Actions</h3>
        <p class="la-list-note">Latest actions appear first.</p>

        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
                <div class="la-item">
                    <div class="la-item-head">
                        <div class="la-item-name"><?= htmlspecialchars($row['leader_name']) ?></div>
                        <div class="la-item-zone">— Block <?= htmlspecialchars($row['zone']) ?></div>
                    </div>

                    <div style="margin-top:6px;">
                        <?= nl2br(htmlspecialchars($row['action_text'])) ?>
                    </div>

                    <div class="la-item-date">
                        <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No actions recorded yet.</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
