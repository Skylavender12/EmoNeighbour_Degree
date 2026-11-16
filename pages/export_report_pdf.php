<?php
session_start();
include '../config/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['leader', 'admin'])) {
    header("Location: login.php");
    exit();
}

require_once '../vendor/autoload.php';
use Dompdf\Dompdf;

// Filters from POST
$selectedMonth  = isset($_POST['month'])  ? (int)$_POST['month']  : date('n');
$selectedYear   = isset($_POST['year'])   ? (int)$_POST['year']   : date('Y');
$selectedZone   = isset($_POST['zone'])   ? $_POST['zone']        : 'ALL';
$selectedDomain = isset($_POST['domain']) ? $_POST['domain']      : 'ALL';

// Chart images from POST (Base64)
$imgSentPie      = isset($_POST['chart_image_sent_pie'])     ? $_POST['chart_image_sent_pie']     : "";
$imgSentBar      = isset($_POST['chart_image_sent_bar'])     ? $_POST['chart_image_sent_bar']     : "";
$imgDomPie       = isset($_POST['chart_image_domain_pie'])   ? $_POST['chart_image_domain_pie']   : "";
$imgDomBar       = isset($_POST['chart_image_domain_bar'])   ? $_POST['chart_image_domain_bar']   : "";
$imgZoneCompare  = isset($_POST['chart_image_zone_compare']) ? $_POST['chart_image_zone_compare'] : "";
$imgDomTrend     = isset($_POST['chart_image_domain_trend']) ? $_POST['chart_image_domain_trend'] : "";

$startDate = sprintf("%04d-%02d-01", $selectedYear, $selectedMonth);
$endDate   = date('Y-m-t', strtotime($startDate));

$conditions = [];
$conditions[] = "created_at >= '$startDate'";
$conditions[] = "created_at <= '$endDate'";

if ($selectedZone !== 'ALL') {
    $conditions[] = "zone = '" . $conn->real_escape_string($selectedZone) . "'";
}
if ($selectedDomain !== 'ALL') {
    $conditions[] = "domain = '" . $conn->real_escape_string($selectedDomain) . "'";
}
$whereSql = "WHERE " . implode(" AND ", $conditions);

// Sentiment summary
$sentimentSummary = ['positive'=>0,'neutral'=>0,'negative'=>0];
$sqlSent = "SELECT sentiment, COUNT(*) AS total FROM feedback $whereSql GROUP BY sentiment";
$resSent = $conn->query($sqlSent);
if ($resSent) {
    while ($row = $resSent->fetch_assoc()) {
        $sentimentSummary[$row['sentiment']] = (int)$row['total'];
    }
}

// Domain summary
$domainCounts = [];
$sqlDom = "SELECT domain, COUNT(*) AS total FROM feedback $whereSql GROUP BY domain";
$resDom = $conn->query($sqlDom);
if ($resDom) {
    while ($row = $resDom->fetch_assoc()) {
        $d = $row['domain'] ?: 'unknown';
        $domainCounts[$d] = (int)$row['total'];
    }
}

// Weekly heatmap (avg score by zone & week)
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
        $z = $row['zone'];
        $w = (int)$row['week_num'];
        $heatmap[$z][$w] = round((float)$row['avg_score'], 2);
    }
}
$maxWeek = 0;
foreach ($heatmap as $z => $weeks) {
    $maxWeek = max($maxWeek, max(array_keys($weeks)));
}
if ($maxWeek == 0) $maxWeek = 4;

// Weekly domain heatmap (count)
$domainHeat = [];
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
        $d = $row['domain'] ?: 'unknown';
        $w = (int)$row['week_num'];
        $domainHeat[$d][$w] = (int)$row['total'];
    }
}
$domainList = ["stress","loneliness","support","appreciation","discontent","fear","anger","neutral"];

$monthName   = date('F', mktime(0,0,0,$selectedMonth,1));
$zoneLabel   = ($selectedZone === 'ALL') ? 'All Zones' : 'Block '.$selectedZone;
$domainLabel = ($selectedDomain === 'ALL') ? 'All Domains' : ucfirst($selectedDomain);

// Build PDF HTML
$html  = "<html><head><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
h1, h2, h3 { margin-bottom: 5px; }
table { width:100%; border-collapse: collapse; margin-top: 8px; }
th, td { border:1px solid #666; padding:4px 6px; text-align:center; }
th { background:#eee; }
img { margin-top:6px; margin-bottom:10px; }
</style></head><body>";

$html .= "
    <h1>EmoNeighbour Emotion Report</h1>
    <h3>Month: {$monthName} {$selectedYear}</h3>
    <h3>Zone: {$zoneLabel}</h3>
    <h3>Domain Filter: {$domainLabel}</h3>
    <hr/>

    <h3>Sentiment Summary</h3>
    <table>
        <tr><th>Positive</th><th>Neutral</th><th>Negative</th></tr>
        <tr>
            <td>{$sentimentSummary['positive']}</td>
            <td>{$sentimentSummary['neutral']}</td>
            <td>{$sentimentSummary['negative']}</td>
        </tr>
    </table>
";

// Insert sentiment charts if provided
if (!empty($imgSentPie)) {
    $html .= "<h3>Sentiment Pie Chart</h3><img src=\"".$imgSentPie."\" style=\"width:420px;\" />";
}
if (!empty($imgSentBar)) {
    $html .= "<h3>Sentiment Bar Chart</h3><img src=\"".$imgSentBar."\" style=\"width:420px;\" />";
}

// Domain summary
$html .= "
    <h3>Domain Distribution</h3>
    <table>
        <tr><th>Domain</th><th>Count</th></tr>";
foreach ($domainCounts as $d => $cnt) {
    $html .= "<tr><td>".htmlspecialchars($d)."</td><td>{$cnt}</td></tr>";
}
$html .= "</table>";

// Insert domain charts if provided
if (!empty($imgDomPie)) {
    $html .= "<h3>Domain Pie Chart</h3><img src=\"".$imgDomPie."\" style=\"width:420px;\" />";
}
if (!empty($imgDomBar)) {
    $html .= "<h3>Domain Bar Chart</h3><img src=\"".$imgDomBar."\" style=\"width:420px;\" />";
}

// Zone comparison chart
if (!empty($imgZoneCompare)) {
    $html .= "<h3>Zone Comparison Chart</h3><img src=\"".$imgZoneCompare."\" style=\"width:420px;\" />";
}

// Domain trend chart
if (!empty($imgDomTrend)) {
    $html .= "<h3>6-Month Domain Trend</h3><img src=\"".$imgDomTrend."\" style=\"width:420px;\" />";
}

// Weekly sentiment heatmap table
$html .= "
    <h3>Weekly Sentiment (Average Score)</h3>
    <table>
        <tr><th>Zone</th>";
for ($w=1; $w<=$maxWeek; $w++) {
    $html .= "<th>Week {$w}</th>";
}
$html .= "</tr>";

$zonesToShow = ($selectedZone === 'ALL') ? $zonesList : [$selectedZone];

foreach ($zonesToShow as $z) {
    $html .= "<tr><td>{$z}</td>";
    for ($w=1; $w<=$maxWeek; $w++) {
        $val = isset($heatmap[$z][$w]) ? $heatmap[$z][$w] : "-";
        $html .= "<td>{$val}</td>";
    }
    $html .= "</tr>";
}
$html .= "</table>";

// Weekly domain heatmap table
$html .= "
    <h3>Weekly Domain Heatmap (Count)</h3>
    <table>
        <tr><th>Domain</th>";
for ($w=1; $w<=$maxWeek; $w++) {
    $html .= "<th>Week {$w}</th>";
}
$html .= "</tr>";

$domainsToShow = ($selectedDomain === 'ALL') ? $domainList : [$selectedDomain];

foreach ($domainsToShow as $d) {
    $html .= "<tr><td>".htmlspecialchars(ucfirst($d))."</td>";
    for ($w=1; $w<=$maxWeek; $w++) {
        $cnt = isset($domainHeat[$d][$w]) ? $domainHeat[$d][$w] : 0;
        $html .= "<td>".($cnt > 0 ? $cnt : '-')."</td>";
    }
    $html .= "</tr>";
}
$html .= "</table>";

$html .= "</body></html>";

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "emotion_report_{$selectedYear}_{$selectedMonth}_{$selectedZone}.pdf";
$dompdf->stream($filename, ["Attachment" => 0]);
exit;
