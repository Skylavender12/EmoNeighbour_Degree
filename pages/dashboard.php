<?php
session_start();
include '../config/config.php';

// Only resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$zone     = htmlspecialchars($_SESSION['zone']);

// ---- CURRENT MONTH RANGE ----
$startMonth = date('Y-m-01');
$endMonth   = date('Y-m-t');

// ---- SENTIMENT SUMMARY (CURRENT MONTH) ----
$sentSummary = ['positive'=>0,'neutral'=>0,'negative'=>0];

$sql = "
    SELECT sentiment, COUNT(*) AS total
    FROM feedback
    WHERE user_id = $user_id
      AND created_at >= '$startMonth'
      AND created_at <= '$endMonth'
    GROUP BY sentiment
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (isset($sentSummary[$row['sentiment']])) {
            $sentSummary[$row['sentiment']] = (int)$row['total'];
        }
    }
}

// ---- LAST FEEDBACK ----
$lastSentiment = "N/A";
$lastDomain    = "N/A";
$lastDate      = "No feedback yet";

$sqlLast = "
    SELECT sentiment, domain, created_at
    FROM feedback
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT 1
";
$resLast = $conn->query($sqlLast);
if ($resLast && $row = $resLast->fetch_assoc()) {
    $lastSentiment = $row['sentiment'];
    $lastDomain    = $row['domain'] ?: 'N/A';
    $lastDate      = $row['created_at'];
}

// ---- MOTIVATIONAL QUOTE ----
$quotes = [
    "Every small step you take is still a step forward.",
    "It’s okay to feel down sometimes — you’re not alone.",
    "Your feelings are valid. Thank you for sharing them.",
    "Even on tough days, you’ve already survived all your worst moments.",
    "You matter more than you know."
];
$quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Resident Dashboard | EmoNeighbour</title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="dash-container">

    <!-- Welcome -->
    <div class="welcome-box">

        <div class="brand-row">
            <img class="brand-logo"
                 src="../assets/img/logo.png"
                 alt="EmoNeighbour Logo">
            <div class="brand-name">EmoNeighbour</div>
        </div>

        <a href="logout.php" class="welcome-logout">Logout</a>

        <h2>Welcome, <?php echo $username; ?> 👋</h2>
        <p>You are in <b>Block <?php echo strtoupper($zone); ?></b>.</p>
        <p class="quote">“<?php echo $quote; ?>”</p>
    </div>

    <!-- Mini Analytics -->
    <div class="grid-two">
        <div class="card">
            <h3>Your Mood Summary (<?php echo date('F Y'); ?>)</h3>
            <p>
                Positive: <b><?php echo $sentSummary['positive']; ?></b><br>
                Neutral:  <b><?php echo $sentSummary['neutral']; ?></b><br>
                Negative: <b><?php echo $sentSummary['negative']; ?></b>
            </p>
            <canvas id="mySentimentChart"></canvas>
        </div>

        <div class="card">
            <h3>Your Last Feedback</h3>
            <p>
                <b>Date:</b> <?php echo $lastDate; ?><br>
                <b>Sentiment:</b> <?php echo $lastSentiment; ?><br>
                <b>Domain:</b> <?php echo $lastDomain; ?>
            </p>
            <p>Your feedback helps improve community wellbeing.</p>
        </div>
    </div>

    <!-- Navigation -->
    <div class="nav-grid">
        <div class="card nav-card">
            <h3>Submit Feedback</h3>
            <p>Share your thoughts and emotions.</p>
            <a href="feedback.php">Go →</a>
        </div>

        <div class="card nav-card">
            <h3>Emotional Report</h3>
            <p>View how your block has been feeling.</p>
            <a href="emotion_report.php">Open →</a>
        </div>

        <div class="card nav-card">
            <h3>Announcements</h3>
            <p>Stay updated with neighbourhood news.</p>
            <a href="announcements.php">View →</a>
        </div>

        <div class="card nav-card">
            <h3>Help & Support</h3>
            <p>Find important contacts and resources.</p>
            <a href="support.php">Help →</a>
        </div>
    </div>

    <!-- Old logout link can remain; CSS hides it -->
    <a href="logout.php" class="logout-link">Logout</a>

</div>

<script>
const mySentData = {
    labels: ['Positive', 'Neutral', 'Negative'],
    datasets: [{
        label: 'Number of Feedback',
        data: [
            <?php echo $sentSummary['positive']; ?>,
            <?php echo $sentSummary['neutral']; ?>,
            <?php echo $sentSummary['negative']; ?>
        ],
        backgroundColor: ['#2e7d32', '#9e9e9e', '#c62828']
    }]
};

new Chart(document.getElementById('mySentimentChart'), {
    type: 'bar',
    data: mySentData,
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
</script>

</body>
</html>
