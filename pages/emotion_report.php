<?php
session_start();
include __DIR__ . '/../config/config.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_zone = isset($_SESSION['zone']) ? $_SESSION['zone'] : null;

// Role-based back link
$backLink = "dashboard.php";
if ($role === 'leader') {
    $backLink = "leader_dashboard.php";
} elseif ($role === 'admin') {
    $backLink = "admin_dashboard.php";
}

// Only resident, leader or admin allowed
if (!in_array($role, ['resident', 'leader', 'admin'])) {
    header("Location: login.php");
    exit();
}

// ---- FILTERS ----
$currentYear  = date('Y');
$currentMonth = date('n');

// Selected filters
$selectedYear   = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$selectedMonth  = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;
$selectedDomain = isset($_GET['domain']) ? $_GET['domain'] : 'ALL';

// Zone filter logic
if ($role === 'leader' || $role === 'admin') {
    $selectedZone = isset($_GET['zone']) ? $_GET['zone'] : 'ALL';
} else {
    $selectedZone = $user_zone; // Resident sees own zone
}

// Date range (for current month view)
$startDate = sprintf("%04d-%02d-01", $selectedYear, $selectedMonth);
$endDate   = date('Y-m-t', strtotime($startDate));

// Base conditions for current-month queries
$conditions = [];
$conditions[] = "created_at >= '$startDate'";
$conditions[] = "created_at <= '$endDate'";

if ($role === 'resident') {
    $conditions[] = "zone = '" . $conn->real_escape_string($user_zone) . "'";
} else {
    if ($selectedZone !== 'ALL') {
        $conditions[] = "zone = '" . $conn->real_escape_string($selectedZone) . "'";
    }
}

// Domain filter for current month
if ($selectedDomain !== 'ALL') {
    $conditions[] = "domain = '" . $conn->real_escape_string($selectedDomain) . "'";
}

$whereSql = "WHERE " . implode(" AND ", $conditions);

// ---- SENTIMENT SUMMARY (CURRENT MONTH) ----
$sentimentSummary = ['positive' => 0, 'neutral' => 0, 'negative' => 0];

$sqlSent = "SELECT sentiment, COUNT(*) AS total FROM feedback $whereSql GROUP BY sentiment";
$resSent = $conn->query($sqlSent);
if ($resSent) {
    while ($row = $resSent->fetch_assoc()) {
        if (isset($sentimentSummary[$row['sentiment']])) {
            $sentimentSummary[$row['sentiment']] = (int)$row['total'];
        }
    }
}

// ---- DOMAIN SUMMARY (CURRENT MONTH) ----
$domainCounts = [];
$sqlDom = "SELECT domain, COUNT(*) AS total FROM feedback $whereSql GROUP BY domain";
$resDom = $conn->query($sqlDom);
if ($resDom) {
    while ($row = $resDom->fetch_assoc()) {
        $domain = ($row['domain'] && trim($row['domain']) !== '') ? $row['domain'] : 'neutral';
        $domainCounts[$domain] = (int)$row['total'];
    }
}

// ---- WEEKLY SENTIMENT HEATMAP (CURRENT MONTH) ----
$zonesList = ['A','B','C','D'];
$heatmap = [];

$sqlHeat = "
    SELECT zone,
           FLOOR((DAY(created_at)-1)/7)+1 AS week_num,
           AVG(score) AS avg_score
    FROM feedback
    $whereSql
    GROUP BY zone, week_num
    ORDER BY zone, week_num
";
$resHeat = $conn->query($sqlHeat);
if ($resHeat) {
    while ($row = $resHeat->fetch_assoc()) {
        $zone = $row['zone'];
        $week = (int)$row['week_num'];
        $heatmap[$zone][$week] = round((float)$row['avg_score'], 2);
    }
}

// Determine max weeks in month
$maxWeek = 0;
foreach ($heatmap as $z => $weeks) {
    $maxWeek = max($maxWeek, max(array_keys($weeks)));
}
if ($maxWeek == 0) $maxWeek = 4;

// ---- WEEKLY DOMAIN HEATMAP (CURRENT MONTH) ----
$domainHeat = []; // [domain][week] = count

$sqlDomHeat = "
    SELECT domain,
           FLOOR((DAY(created_at)-1)/7)+1 AS week_num,
           COUNT(*) AS total
    FROM feedback
    $whereSql
    GROUP BY domain, week_num
    ORDER BY domain, week_num
";
$resDomHeat = $conn->query($sqlDomHeat);
if ($resDomHeat) {
    while ($row = $resDomHeat->fetch_assoc()) {
        $d = ($row['domain'] && trim($row['domain']) !== '') ? $row['domain'] : 'neutral';
        $w = (int)$row['week_num'];
        $domainHeat[$d][$w] = (int)$row['total'];
    }
}

// Get full domain list for rows
$domainList = ["stress","loneliness","support","appreciation","discontent","fear","anger","neutral"];

// ---- ZONE COMPARISON (CURRENT MONTH, ALL ZONES) ----
$condZoneCompare = [];
$condZoneCompare[] = "created_at >= '$startDate'";
$condZoneCompare[] = "created_at <= '$endDate'";
if ($selectedDomain !== 'ALL') {
    $condZoneCompare[] = "domain = '" . $conn->real_escape_string($selectedDomain) . "'";
}
$whereZoneCompare = "WHERE " . implode(" AND ", $condZoneCompare);

$zoneCompare = [
    'A' => ['pos'=>0,'neu'=>0,'neg'=>0],
    'B' => ['pos'=>0,'neu'=>0,'neg'=>0],
    'C' => ['pos'=>0,'neu'=>0,'neg'=>0],
    'D' => ['pos'=>0,'neu'=>0,'neg'=>0],
];

$sqlZoneCompare = "
    SELECT zone,
           SUM(CASE WHEN sentiment='positive' THEN 1 ELSE 0 END) AS pos,
           SUM(CASE WHEN sentiment='neutral'  THEN 1 ELSE 0 END) AS neu,
           SUM(CASE WHEN sentiment='negative' THEN 1 ELSE 0 END) AS neg
    FROM feedback
    $whereZoneCompare
    GROUP BY zone
";
$resZoneCompare = $conn->query($sqlZoneCompare);
if ($resZoneCompare) {
    while ($row = $resZoneCompare->fetch_assoc()) {
        $z = $row['zone'];
        if (isset($zoneCompare[$z])) {
            $zoneCompare[$z]['pos'] = (int)$row['pos'];
            $zoneCompare[$z]['neu'] = (int)$row['neu'];
            $zoneCompare[$z]['neg'] = (int)$row['neg'];
        }
    }
}

// ---- 6-MONTH DOMAIN TREND (BY ZONE FILTER) ----
$trendEnd   = $endDate;
$trendStart = date('Y-m-01', strtotime("$startDate -5 months"));

$condTrend = [];
$condTrend[] = "created_at >= '$trendStart'";
$condTrend[] = "created_at <= '$trendEnd'";

if ($role === 'resident') {
    $condTrend[] = "zone = '" . $conn->real_escape_string($user_zone) . "'";
} else {
    if ($selectedZone !== 'ALL') {
        $condTrend[] = "zone = '" . $conn->real_escape_string($selectedZone) . "'";
    }
}

$whereTrend = "WHERE " . implode(" AND ", $condTrend);

$sqlTrend = "
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
           domain,
           COUNT(*) AS total
    FROM feedback
    $whereTrend
    GROUP BY ym, domain
    ORDER BY ym
";
$resTrend = $conn->query($sqlTrend);

// Prepare time range
$trendLabels = [];
$trendDomainSeries = []; // [domain][ym] = total

$period = new DatePeriod(
    new DateTime($trendStart),
    new DateInterval('P1M'),
    6
);

// Pre-fill months labels
$ymList = [];
foreach ($period as $dt) {
    $ym  = $dt->format('Y-m');
    $lbl = $dt->format('M Y');
    $ymList[] = $ym;
    $trendLabels[] = $lbl;
}

// Collect raw data
$rawTrend = []; // [ym][domain] = total
if ($resTrend) {
    while ($row = $resTrend->fetch_assoc()) {
        $ym  = $row['ym'];
        $dom = ($row['domain'] && trim($row['domain']) !== '') ? $row['domain'] : 'neutral';
        $rawTrend[$ym][$dom] = (int)$row['total'];
    }
}

// Build domain series for known domains
foreach ($domainList as $domName) {
    $trendDomainSeries[$domName] = [];
    foreach ($ymList as $ym) {
        $trendDomainSeries[$domName][] = isset($rawTrend[$ym][$domName]) ? $rawTrend[$ym][$domName] : 0;
    }
}

// Zone comparison arrays for JS
$zoneCompareLabels = array_keys($zoneCompare);
$zoneComparePos    = array_map(function($z){return $z['pos'];}, $zoneCompare);
$zoneCompareNeu    = array_map(function($z){return $z['neu'];}, $zoneCompare);
$zoneCompareNeg    = array_map(function($z){return $z['neg'];}, $zoneCompare);

// Sentiment data for charts
$sentimentLabels = ['positive','neutral','negative'];
$sentimentValues = [
    $sentimentSummary['positive'],
    $sentimentSummary['neutral'],
    $sentimentSummary['negative'],
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Emotion Report | EmoNeighbour</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="../assets/css/report.css">

<style>
/* header card layout (title left, actions right) */
.header-card{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
}

.header-title{
  font-size: 32px;
  font-weight: 900;
  margin: 0 0 6px;
}

.header-sub{
  font-size: 14px;
  font-weight: 800;
  opacity: 0.75;
}

.header-actions{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:10px;
}

.btn-back-top{
  height: 38px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.16);
  background: #fff;
  color: #111;
  font-weight: 900;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}

.btn-back-top:hover{ background: rgba(25,118,210,0.08); }

.btn-back-top:focus{
  outline: 3px solid rgba(25,118,210,0.45);
  outline-offset: 2px;
}

/* Back-to-top floating button */
#backToTop{
  position: fixed;
  right: 18px;
  bottom: 18px;
  width: 44px;
  height: 44px;
  border-radius: 999px;
  border: 1px solid rgba(0,0,0,0.16);
  background: #fff;
  box-shadow: 0 10px 22px rgba(0,0,0,0.16);
  cursor: pointer;
  display: none;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  font-size: 18px;
  z-index: 9999;
}

#backToTop:hover{
  background: rgba(25,118,210,0.08);
}

#backToTop:focus{
  outline: 3px solid rgba(25,118,210,0.45);
  outline-offset: 2px;
}
</style>
</head>

<body>

<div class="report-container">

    <!-- ✅ Separate header card -->
    <div class="card-box header-card">
        <div>
            <div class="header-title">Emotion Report</div>
            <div class="header-sub">Monthly analytics, charts, and heatmaps.</div>
        </div>

        <div class="header-actions">
            <a href="<?= htmlspecialchars($backLink) ?>" class="btn-back-top">← Back</a>

            <!-- ✅ Export PDF directly under Back (leaders/admin only) -->
            <?php if ($role !== 'resident'): ?>
              <form id="pdfExportForm" method="POST" action="export_report_pdf.php" target="_blank" style="margin:0;">
                  <input type="hidden" name="month"  value="<?= $selectedMonth ?>">
                  <input type="hidden" name="year"   value="<?= $selectedYear ?>">
                  <input type="hidden" name="zone"   value="<?= $selectedZone ?>">
                  <input type="hidden" name="domain" value="<?= $selectedDomain ?>">

                  <!-- Hidden fields for chart images (Base64) -->
                  <textarea id="chart_image_sent_pie"      name="chart_image_sent_pie"      style="display:none"></textarea>
                  <textarea id="chart_image_sent_bar"      name="chart_image_sent_bar"      style="display:none"></textarea>
                  <textarea id="chart_image_domain_pie"    name="chart_image_domain_pie"    style="display:none"></textarea>
                  <textarea id="chart_image_domain_bar"    name="chart_image_domain_bar"    style="display:none"></textarea>
                  <textarea id="chart_image_zone_compare"  name="chart_image_zone_compare"  style="display:none"></textarea>
                  <textarea id="chart_image_domain_trend"  name="chart_image_domain_trend"  style="display:none"></textarea>

                  <button class="btn-secondary" type="submit">Export PDF</button>
              </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTER FORM -->
    <div class="filter-card">
      <form method="GET" class="filter-row">

        <div class="filter-item span-2">
          <label>Month</label>
          <select name="month">
              <?php for ($m=1; $m<=12; $m++): ?>
                  <option value="<?= $m ?>" <?= ($m == $selectedMonth ? "selected" : "") ?>>
                      <?= date("F", mktime(0,0,0,$m,1)) ?>
                  </option>
              <?php endfor; ?>
          </select>
        </div>

        <div class="filter-item span-2">
          <label>Year</label>
          <select name="year">
              <?php for ($y=$currentYear-3; $y <= $currentYear; $y++): ?>
                  <option value="<?= $y ?>" <?= ($y == $selectedYear ? "selected" : "") ?>><?= $y ?></option>
              <?php endfor; ?>
          </select>
        </div>

        <?php if ($role !== 'resident'): ?>
          <div class="filter-item span-2">
            <label>Block</label>
            <select name="zone">
                <option value="ALL" <?= ($selectedZone=="ALL"?"selected":"") ?>>All Zones</option>
                <option value="A" <?= ($selectedZone=="A"?"selected":"") ?>>Block A</option>
                <option value="B" <?= ($selectedZone=="B"?"selected":"") ?>>Block B</option>
                <option value="C" <?= ($selectedZone=="C"?"selected":"") ?>>Block C</option>
                <option value="D" <?= ($selectedZone=="D"?"selected":"") ?>>Block D</option>
            </select>
          </div>
        <?php else: ?>
          <div class="filter-item span-2">
            <label>Your Block</label>
            <div class="zone-pill"><?= strtoupper($user_zone) ?></div>
          </div>
        <?php endif; ?>

        <div class="filter-item span-3">
          <label>Domain</label>
          <select name="domain">
              <option value="ALL" <?= ($selectedDomain=="ALL"?"selected":"") ?>>All</option>
              <?php foreach ($domainList as $d): ?>
                  <option value="<?= $d ?>" <?= ($selectedDomain==$d?"selected":"") ?>><?= ucfirst($d) ?></option>
              <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-item span-3">
          <label>&nbsp;</label>
          <button class="btn-primary" type="submit">Apply</button>
        </div>

      </form>
    </div>

    <!-- SENTIMENT SUMMARY -->
    <div class="card-box">
        <h3>Sentiment Summary (<?= date("F Y", strtotime($startDate)) ?>)</h3>
        <p>
            Positive: <b><?= $sentimentSummary['positive'] ?></b> |
            Neutral: <b><?= $sentimentSummary['neutral'] ?></b> |
            Negative: <b><?= $sentimentSummary['negative'] ?></b>
        </p>
    </div>

    <!-- SENTIMENT CHARTS -->
    <div class="chart-row">
        <div class="card-box">
            <h3>Sentiment Distribution (Pie)</h3>
            <div class="chart-holder">
                <canvas id="sentimentPie"></canvas>
            </div>
        </div>
        <div class="card-box">
            <h3>Sentiment Distribution (Bar)</h3>
            <div class="chart-holder">
                <canvas id="sentimentBar"></canvas>
            </div>
        </div>
    </div>

    <!-- DOMAIN CHARTS -->
    <div class="chart-row">
        <div class="card-box">
            <h3>Domain Distribution (Pie)</h3>
            <div class="chart-holder">
                <canvas id="domainPie"></canvas>
            </div>
        </div>

        <div class="card-box">
            <h3>Domain Distribution (Bar)</h3>
            <div class="chart-holder">
                <canvas id="domainBar"></canvas>
            </div>
        </div>
    </div>

    <!-- ZONE COMPARISON (Leader/Admin) -->
    <?php if ($role !== 'resident'): ?>
    <div class="card-box">
        <h3>Block Comparison (Current Month<?= $selectedDomain!='ALL' ? ', Domain: '.ucfirst($selectedDomain) : '' ?>)</h3>
        <div class="chart-holder">
            <canvas id="zoneCompareChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6-MONTH DOMAIN TREND -->
    <div class="card-box">
        <h3>6-Month Domain Trend
            <?php if ($role !== 'resident' && $selectedZone !== 'ALL'): ?>
                (Block: <?= $selectedZone ?>)
            <?php elseif ($role === 'resident'): ?>
                (Your Block: <?= strtoupper($user_zone) ?>)
            <?php else: ?>
                (All Blocks)
            <?php endif; ?>
        </h3>
        <div class="chart-holder">
            <canvas id="domainTrendChart"></canvas>
        </div>
    </div>

    <!-- WEEKLY SENTIMENT HEATMAP -->
    <div class="card-box">
        <h3>Weekly Sentiment Heatmap (Score)</h3>

        <table class="heatmap-table">
            <tr>
                <th>Zone</th>
                <?php for ($w=1; $w <= $maxWeek; $w++): ?>
                    <th>Week <?= $w ?></th>
                <?php endfor; ?>
            </tr>

            <?php
            if ($role === 'resident') {
                $zonesToShow = [$user_zone];
            } else {
                $zonesToShow = ($selectedZone === 'ALL') ? $zonesList : [$selectedZone];
            }

            foreach ($zonesToShow as $z):
            ?>
                <tr>
                    <td><?= $z ?></td>
                    <?php for ($w=1; $w <= $maxWeek; $w++):
                        $val = $heatmap[$z][$w] ?? null;
                        if ($val === null) {
                            $class = "";
                            $text = "-";
                        } else {
                            if ($val >= 0.2) $class = "heat-pos";
                            elseif ($val <= -0.2) $class = "heat-neg";
                            else $class = "heat-neu";
                            $text = $val;
                        }
                    ?>
                        <td class="<?= $class ?>"><?= $text ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- WEEKLY DOMAIN HEATMAP -->
    <div class="card-box">
        <h3>Weekly Domain Heatmap (Count)</h3>
        <table class="heatmap-table">
            <tr>
                <th>Domain</th>
                <?php for ($w=1; $w <= $maxWeek; $w++): ?>
                    <th>Week <?= $w ?></th>
                <?php endfor; ?>
            </tr>
            <?php
            $domainsToShow = ($selectedDomain !== 'ALL') ? [$selectedDomain] : $domainList;
            foreach ($domainsToShow as $d):
            ?>
                <tr>
                    <td><?= ucfirst($d) ?></td>
                    <?php for ($w=1; $w <= $maxWeek; $w++):
                        $cnt = $domainHeat[$d][$w] ?? 0;
                        $class = $cnt > 0 ? 'domain-cell-nz' : '';
                    ?>
                        <td class="<?= $class ?>"><?= $cnt > 0 ? $cnt : '-' ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

</div>

<!-- ✅ floating back-to-top button -->
<button id="backToTop" type="button" aria-label="Back to top">↑</button>

<script>
// ===== SENTIMENT COLORS (LECTURER REQUIREMENT) =====
const SENTIMENT_COLORS = {
    positive: '#2e7d32',
    neutral:  '#607d8b',
    negative: '#c62828'
};

// ===== DOMAIN DATA =====
const domainLabels = <?= json_encode(array_keys($domainCounts)) ?>;
const domainValues = <?= json_encode(array_values($domainCounts)) ?>;

// ===== SENTIMENT DATA =====
const sentimentLabels = <?= json_encode($sentimentLabels) ?>;
const sentimentValues = <?= json_encode($sentimentValues) ?>;

// ===== ZONE COMPARISON DATA =====
const zoneLabels = <?= json_encode($zoneCompareLabels) ?>;
const zonePos    = <?= json_encode($zoneComparePos) ?>;
const zoneNeu    = <?= json_encode($zoneCompareNeu) ?>;
const zoneNeg    = <?= json_encode($zoneCompareNeg) ?>;

// ===== DOMAIN TREND DATA =====
const trendLabels = <?= json_encode($trendLabels) ?>;
const trendSeries = <?= json_encode($trendDomainSeries) ?>;

// ✅ IMPORTANT: stops charts from squishing inside fixed-height holder
const CHART_BASE_OPTIONS = {
    responsive: true,
    maintainAspectRatio: false
};

if (sentimentLabels.length > 0) {
    new Chart(document.getElementById('sentimentPie'), {
        type: 'pie',
        data: {
            labels: sentimentLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
            datasets: [{
                data: sentimentValues,
                backgroundColor: [
                    SENTIMENT_COLORS.positive,
                    SENTIMENT_COLORS.neutral,
                    SENTIMENT_COLORS.negative
                ]
            }]
        },
        options: {
            ...CHART_BASE_OPTIONS,
            plugins: { legend: { position: 'top' } }
        }
    });

    new Chart(document.getElementById('sentimentBar'), {
        type: 'bar',
        data: {
            labels: sentimentLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
            datasets: [{
                label: 'Count',
                data: sentimentValues,
                backgroundColor: [
                    SENTIMENT_COLORS.positive,
                    SENTIMENT_COLORS.neutral,
                    SENTIMENT_COLORS.negative
                ]
            }]
        },
        options: {
            ...CHART_BASE_OPTIONS,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

if (domainLabels.length > 0) {
    new Chart(document.getElementById('domainPie'), {
        type: 'pie',
        data: { labels: domainLabels, datasets: [{ data: domainValues }] },
        options: {
            ...CHART_BASE_OPTIONS,
            plugins: { legend: { position: 'top' } }
        }
    });

    new Chart(document.getElementById('domainBar'), {
        type: 'bar',
        data: {
            labels: domainLabels,
            datasets: [{
                label: 'Number of Feedback',
                data: domainValues,
                backgroundColor: domainLabels.map(() => '#64b5f6')
            }]
        },
        options: {
            ...CHART_BASE_OPTIONS,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

<?php if ($role !== 'resident'): ?>
new Chart(document.getElementById('zoneCompareChart'), {
    type: 'bar',
    data: {
        labels: zoneLabels,
        datasets: [
            { label: 'Positive', data: zonePos, backgroundColor: SENTIMENT_COLORS.positive },
            { label: 'Neutral',  data: zoneNeu, backgroundColor: SENTIMENT_COLORS.neutral },
            { label: 'Negative', data: zoneNeg, backgroundColor: SENTIMENT_COLORS.negative }
        ]
    },
    options: {
        ...CHART_BASE_OPTIONS,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
<?php endif; ?>

// Domain trend
const domainTrendDatasets = [];
Object.keys(trendSeries).forEach(function(domName) {
    const dataArr = trendSeries[domName];
    const sum = dataArr.reduce((a,b)=>a+b,0);
    if (sum > 0) {
        domainTrendDatasets.push({
            label: domName.charAt(0).toUpperCase() + domName.slice(1),
            data: dataArr,
            fill: false,
            tension: 0.3
        });
    }
});

if (trendLabels.length > 0) {
    new Chart(document.getElementById('domainTrendChart'), {
        type: 'line',
        data: { labels: trendLabels, datasets: domainTrendDatasets },
        options: {
            ...CHART_BASE_OPTIONS,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

// ===== EXPORT TO PDF: CAPTURE CHART IMAGES =====
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pdfExportForm');
    if (form) {
        form.addEventListener('submit', function() {
            function setImg(id, canvasId) {
                const canvas = document.getElementById(canvasId);
                const field  = document.getElementById(id);
                if (canvas && field) {
                    try { field.value = canvas.toDataURL('image/png'); }
                    catch (e) { field.value = ""; }
                }
            }
            setImg('chart_image_sent_pie',     'sentimentPie');
            setImg('chart_image_sent_bar',     'sentimentBar');
            setImg('chart_image_domain_pie',   'domainPie');
            setImg('chart_image_domain_bar',   'domainBar');
            <?php if ($role !== 'resident'): ?>
            setImg('chart_image_zone_compare', 'zoneCompareChart');
            <?php endif; ?>
            setImg('chart_image_domain_trend', 'domainTrendChart');
        });
    }
});

// ===== BACK TO TOP BUTTON =====
const backToTopBtn = document.getElementById('backToTop');

window.addEventListener('scroll', function(){
  if (window.scrollY > 500) backToTopBtn.style.display = 'inline-flex';
  else backToTopBtn.style.display = 'none';
});

backToTopBtn.addEventListener('click', function(){
  window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

</body>
</html>
