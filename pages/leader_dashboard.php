<?php
session_start();
include '../config/config.php';

// Only community leader can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: login.php");
    exit();
}

$leaderName = htmlspecialchars($_SESSION['username']);

// ----- CURRENT MONTH RANGE -----
$startMonth   = date('Y-m-01');
$endMonth     = date('Y-m-t');

$conditions = [];
$conditions[] = "created_at >= '$startMonth'";
$conditions[] = "created_at <= '$endMonth'";
$whereMonth = "WHERE " . implode(" AND ", $conditions);

// ----- SUMMARY COUNTS (CURRENT MONTH) -----
$sentSummary = ['positive'=>0, 'neutral'=>0, 'negative'=>0];
$totalFeedback = 0;

$sql = "SELECT sentiment, COUNT(*) AS total
        FROM feedback
        $whereMonth
        GROUP BY sentiment";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $s = $row['sentiment'];
        if (isset($sentSummary[$s])) {
            $sentSummary[$s] = (int)$row['total'];
            $totalFeedback  += (int)$row['total'];
        }
    }
}

// High-risk pending alerts count
$sqlAlert = "SELECT COUNT(*) AS c FROM flagged_feedback WHERE status='pending'";
$resAlert = $conn->query($sqlAlert);
$pendingAlerts = ($resAlert && $row=$resAlert->fetch_assoc()) ? (int)$row['c'] : 0;

// Resolved flagged feedback count
$sqlResolved = "SELECT COUNT(*) AS c FROM flagged_feedback WHERE status='resolved'";
$resResolved = $conn->query($sqlResolved);
$resolvedAlerts = ($resResolved && $row=$resResolved->fetch_assoc()) ? (int)$row['c'] : 0;

// ----- PER-ZONE STATUS (CURRENT MONTH) -----
$zoneStats = [
    'A'=>['pos'=>0,'neg'=>0],
    'B'=>['pos'=>0,'neg'=>0],
    'C'=>['pos'=>0,'neg'=>0],
    'D'=>['pos'=>0,'neg'=>0],
];

$sqlZones = "
    SELECT zone,
           SUM(CASE WHEN sentiment='positive' THEN 1 ELSE 0 END) AS pos_count,
           SUM(CASE WHEN sentiment='negative' THEN 1 ELSE 0 END) AS neg_count
    FROM feedback
    $whereMonth
    GROUP BY zone
";
$resZones = $conn->query($sqlZones);
if ($resZones) {
    while ($row = $resZones->fetch_assoc()) {
        $z = $row['zone'];
        if (isset($zoneStats[$z])) {
            $zoneStats[$z]['pos'] = (int)$row['pos_count'];
            $zoneStats[$z]['neg'] = (int)$row['neg_count'];
        }
    }
}

// ----- 6-MONTH TREND ANALYSIS (ALL ZONES) -----
$startTrend = date('Y-m-01', strtotime('-5 months'));
$sqlTrend = "
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
           SUM(CASE WHEN sentiment='positive' THEN 1 ELSE 0 END) AS pos,
           SUM(CASE WHEN sentiment='neutral'  THEN 1 ELSE 0 END) AS neu,
           SUM(CASE WHEN sentiment='negative' THEN 1 ELSE 0 END) AS neg
    FROM feedback
    WHERE created_at >= '$startTrend'
    GROUP BY ym
    ORDER BY ym
";
$resTrend = $conn->query($sqlTrend);

$trendLabels = [];
$trendPos = [];
$trendNeu = [];
$trendNeg = [];

if ($resTrend) {
    $period = new DatePeriod(
        new DateTime($startTrend),
        new DateInterval('P1M'),
        6
    );

    $raw = [];
    while ($row = $resTrend->fetch_assoc()) {
        $raw[$row['ym']] = [
            'pos'=>(int)$row['pos'],
            'neu'=>(int)$row['neu'],
            'neg'=>(int)$row['neg'],
        ];
    }

    foreach ($period as $dt) {
        $ym = $dt->format('Y-m');
        $label = $dt->format('M Y');
        $trendLabels[] = $label;

        if (isset($raw[$ym])) {
            $trendPos[] = $raw[$ym]['pos'];
            $trendNeu[] = $raw[$ym]['neu'];
            $trendNeg[] = $raw[$ym]['neg'];
        } else {
            $trendPos[] = 0;
            $trendNeu[] = 0;
            $trendNeg[] = 0;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Leader Dashboard | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dash-container">

    <!-- HEADER / WELCOME -->
    <div class="welcome-box">

        <div class="brand-row">
            <img class="brand-logo" src="../assets/img/logo.png" alt="EmoNeighbour Logo">
            <div class="brand-name">EmoNeighbour</div>
        </div>

        <a href="logout.php" class="welcome-logout">Logout</a>

        <h2>Welcome, Leader <?php echo $leaderName; ?> 👋</h2>
        <p>Here is the emotional overview of your neighbourhood for <b><?php echo date('F Y'); ?></b>.</p>

        <!-- ✅ Row 1: only 2 buttons -->
        <div class="action-bar action-bar-top">
            <a class="action-btn primary" href="emotion_report.php">View Emotional Reports</a>
            <a class="action-btn primary" href="leader_post_announcement.php">Post Announcement</a>
        </div>

        <!-- ✅ Row 2: rest of buttons -->
        <div class="action-bar action-bar-bottom">
            <a class="action-btn" href="announcements.php">View Announcements</a>

            <a class="action-btn danger" href="admin_flagged.php">
                High-Risk Alerts
                <span class="badge"><?php echo $pendingAlerts; ?></span>
            </a>

            <a class="action-btn success" href="resolved_flagged_feedback.php">
                Resolved Feedback
                <span class="badge badge-success"><?php echo $resolvedAlerts; ?></span>
            </a>

            <a class="action-btn" href="leader_actions.php">Action Taken</a>
        </div>

    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">
        <div class="card">
            <h3>Total Feedback (This Month)</h3>
            <div class="metric-num"><?php echo $totalFeedback; ?></div>
            <div class="muted-text">All zones combined.</div>
        </div>

        <div class="card">
            <h3>Sentiment Breakdown</h3>
            <p>
                <span class="pos">Positive: <b><?php echo $sentSummary['positive']; ?></b></span><br>
                Neutral: <b><?php echo $sentSummary['neutral']; ?></b><br>
                <span class="neg">Negative: <b><?php echo $sentSummary['negative']; ?></b></span>
            </p>
        </div>

        <div class="card">
            <h3>High-Risk Alerts</h3>
            <p>Pending flagged feedback:</p>
            <div class="metric-num danger-num"><?php echo $pendingAlerts; ?></div>
        </div>
    </div>

    <!-- PER-ZONE STATUS -->
    <div class="card">
        <h3>Block Status (Current Month)</h3>

        <div class="zone-grid">
            <?php foreach ($zoneStats as $zone => $st): ?>
                <div class="zone-card">
                    <h4 class="zone-title">Block <?php echo $zone; ?></h4>
                    <p class="zone-badge">
                        <span class="pos">Positive: <b><?php echo $st['pos']; ?></b></span><br>
                        <span class="neg">Negative: <b><?php echo $st['neg']; ?></b></span>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 6-MONTH TREND CHART -->
    <div class="card">
        <h3>6-Month Emotion Trend (All Blocks)</h3>
        <div class="chart-holder">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

</div>

<script>
const trendLabels = <?php echo json_encode($trendLabels); ?>;
const trendPos    = <?php echo json_encode($trendPos); ?>;
const trendNeu    = <?php echo json_encode($trendNeu); ?>;
const trendNeg    = <?php echo json_encode($trendNeg); ?>;

if (trendLabels.length > 0) {
    const ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Positive',
                    data: trendPos,
                    borderColor: '#2e7d32',
                    backgroundColor: 'rgba(46,125,50,0.15)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Neutral',
                    data: trendNeu,
                    borderColor: '#607d8b',
                    backgroundColor: 'rgba(96,125,139,0.15)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Negative',
                    data: trendNeg,
                    borderColor: '#c62828',
                    backgroundColor: 'rgba(198,40,40,0.15)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}
</script>

</body>
</html>
