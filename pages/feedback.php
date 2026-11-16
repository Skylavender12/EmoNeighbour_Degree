<?php
session_start();
include __DIR__ . '/../config/config.php';
include __DIR__ . '/../config/audit.php';

// ------------------------------------------------------------
//  ACCESS CONTROL
// ------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];
$zone    = $_SESSION['zone'];

// Role-based back button
$backLink = "dashboard.php";
if ($role === "leader") $backLink = "leader_dashboard.php";
if ($role === "admin")  $backLink = "admin_dashboard.php";

$message = "";

// ------------------------------------------------------------
//  WHEN FORM IS SUBMITTED
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw_feedback = trim($_POST['feedback_text']);

    if ($raw_feedback === "") {
        $message = "Feedback cannot be empty.";
    } else {

        // Escape for SQL
        $clean_text = $conn->real_escape_string($raw_feedback);

        //  KEYWORD ALERT SYSTEM
        $danger_keywords = [
            'suicide','kill','die','death','hurt','unsafe','scared',
            'abuse','violence','attack','depressed','panic','threat'
        ];

        $keywordFlag = 0;
        $keywordReason = '';

        foreach ($danger_keywords as $kw) {
            if (stripos($raw_feedback, $kw) !== false) {
                $keywordFlag = 1;
                $keywordReason = "Keyword detected: $kw";
                break;
            }
        }

        //  CALL PYTHON AI API
        $api_url = "http://127.0.0.1:5000/analyze";
        $payload = json_encode(["text" => $raw_feedback]);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            $message = "Error contacting AI server.";
        } else {

            $data = json_decode($response, true);

            if (!is_array($data)) {
                $message = "AI response was invalid.";
            } else {

                // ------------------------------------------------------------
                //  SAFE EXTRACTION WITH DEFAULTS
                // ------------------------------------------------------------
                $sentiment  = isset($data['sentiment']) ? $conn->real_escape_string($data['sentiment']) : 'neutral';
                $score      = isset($data['score']) ? floatval($data['score']) : 0.0;
                $model_used = isset($data['model_used']) ? $conn->real_escape_string($data['model_used']) : 'roberta';
                $domain     = isset($data['domain']) ? $conn->real_escape_string($data['domain']) : 'neutral';

                // ------------------------------------------------------------
                //  MERGE AI FLAG + KEYWORD FLAG
                // ------------------------------------------------------------
                $aiFlag   = isset($data['is_flagged']) ? intval($data['is_flagged']) : 0;
                $aiReason = isset($data['flag_reason']) ? $data['flag_reason'] : '';

                $isFlag = ($aiFlag === 1 || $keywordFlag === 1) ? 1 : 0;

                if ($keywordFlag === 1) {
                    $flagReason = $conn->real_escape_string($keywordReason);
                } else {
                    $flagReason = $conn->real_escape_string($aiReason);
                }

                // ------------------------------------------------------------
                //  INSERT INTO feedback
                // ------------------------------------------------------------
                $sql = "
                INSERT INTO feedback 
                (user_id, zone, feedback_text, model_used, sentiment, score, domain, is_flagged, flag_reason, created_at)
                VALUES 
                ($user_id, '$zone', '$clean_text', '$model_used', '$sentiment', $score, '$domain', $isFlag, '$flagReason', NOW())
                ";

                if (!$conn->query($sql)) {
                    die("Error inserting feedback: " . $conn->error);
                }

                $feedback_id = $conn->insert_id;

                // ------------------------------------------------------------
                //  IF FLAGGED → INSERT INTO flagged_feedback
                // ------------------------------------------------------------
                if ($isFlag == 1) {
                    $sqlFlag = "
                    INSERT INTO flagged_feedback 
                    (feedback_id, user_id, zone, feedback_text, reason, status, created_at)
                    VALUES
                    ($feedback_id, $user_id, '$zone', '$clean_text', '$flagReason', 'pending', NOW())
                    ";

                    if (!$conn->query($sqlFlag)) {
                        die("Error inserting flagged feedback: " . $conn->error);
                    }
                }

                // ------------------------------------------------------------
                //  AUDIT LOG
                // ------------------------------------------------------------
                audit_log($conn, $user_id, "Submitted Feedback", "Feedback ID: $feedback_id");

                $message = "Feedback submitted successfully!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Submit Feedback | EmoNeighbour</title>

    <!-- ✅ new dedicated CSS (absolute path so it always loads) -->
    <link rel="stylesheet" href="/EmoNeighbour/assets/css/feedback.css">
</head>

<body>
  <div class="page-container">
    <div class="shell">

      <h2 class="page-title">Share Your Feedback</h2>
      <p class="zone-line">Your Block: <span class="zone-pill"><?= strtoupper($zone) ?></span></p>

      <form method="POST">
        <div class="form-group">
          <label class="label" for="feedback_text">Your Feedback:</label>
          <textarea id="feedback_text" name="feedback_text" required></textarea>
        </div>

        <div class="actions">
          <button class="btn-primary" type="submit">Analyze &amp; Submit</button>
          <a class="btn-back" href="<?= $backLink ?>">← Back</a>
        </div>
      </form>

      <?php if (!empty($message)): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>
