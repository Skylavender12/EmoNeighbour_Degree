<?php
include '../config/config.php';

$messageHtml = "";
$generatedToken = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $email = strtolower($email);
    $safeEmail = $conn->real_escape_string($email);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageHtml = "<div class='error-box'>Please enter a valid email address.</div>";
    } else {
        $result = $conn->query("SELECT id FROM users WHERE email='$safeEmail' LIMIT 1");

        if ($result && $result->num_rows > 0) {
            $generatedToken = bin2hex(random_bytes(16));
            $safeToken = $conn->real_escape_string($generatedToken);

            $update = "
                UPDATE users
                SET reset_token = '$safeToken',
                    reset_token_created_at = NOW()
                WHERE email = '$safeEmail'
                LIMIT 1
            ";

            if ($conn->query($update)) {
                $messageHtml = "
                    <div class='success-box'>
                        <div style='font-weight:900; margin-bottom:8px;'>Reset token generated</div>
                        <div style='opacity:.9; margin-bottom:10px;'>Copy the token below, then continue to Reset Password.</div>

                        <div class='token-wrap'>
                            <input id='tokenField' class='token-field' type='text' value='{$generatedToken}' readonly>
                            <button type='button' class='btn-copy' onclick='copyToken()'>Copy</button>
                        </div>

                        <div style='margin-top:12px;'>
                            <a class='btn-secondary-link' href='reset_password.php'>Go to Reset Password →</a>
                        </div>
                    </div>
                ";
            } else {
                $messageHtml = "<div class='error-box'>Database error: " . htmlspecialchars($conn->error) . "</div>";
            }
        } else {
            $messageHtml = "<div class='error-box'>Email not found.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .helper-text{ margin-top:6px; font-size:12px; opacity:.75; }

        /* token row */
        .token-wrap{
            display:flex;
            gap:10px;
            align-items:stretch; /* ✅ makes button + input same height */
            margin-top:10px;
        }

        .token-field{
            flex: 1;
            height: 44px;               /* ✅ consistent height */
            padding: 0 14px;            /* ✅ vertical alignment via height */
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.18);
            font-weight: 800;
            letter-spacing: 0.4px;
            background: #fff;
            box-sizing: border-box;
        }

        .btn-copy{
            height: 44px;               /* ✅ same height */
            min-width: 88px;            /* ✅ not too small */
            padding: 0 16px;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.16);
            background: #5e5e5eff;
            font-weight: 900;
            font-size: 14px;
            line-height: 44px;          /* ✅ centers text perfectly */
            cursor: pointer;
            white-space: nowrap;
            box-sizing: border-box;
        }
        .btn-copy:hover{ background: rgba(0,0,0,0.04); }

        .btn-secondary-link{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:10px 14px;
            border-radius:10px;
            text-decoration:none;
            font-weight:900;
            border:1px solid rgba(0,0,0,0.16);
            background:#fff;
            color:#111;
        }
        .btn-secondary-link:hover{ background: rgba(0,0,0,0.04); }

        /* Back to login as button */
        .back-btn{
            margin-top: 12px;
            display: inline-flex;
        }
        .back-btn a{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:10px 14px;
            border-radius:10px;
            text-decoration:none;
            font-weight:900;
            border:1px solid rgba(0,0,0,0.16);
            background:#fff;
            color:#111;
        }
        .back-btn a:hover{ background: rgba(0,0,0,0.04); }

        .toast{
            margin-top:10px;
            font-size:12px;
            font-weight:800;
            opacity:.85;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Forgot Password</h2>

    <form method="POST" autocomplete="off">
        <label>Enter your email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        <div class="helper-text">We’ll generate a reset token for your account.</div>

        <button type="submit">Generate Reset Token</button>
    </form>

    <?= $messageHtml ?>

    <div class="back-btn">
        <a href="login.php">← Back to Login</a>
    </div>
</div>

<script>
function copyToken(){
    const el = document.getElementById('tokenField');
    if (!el) return;

    el.select();
    el.setSelectionRange(0, 99999);

    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }

    if (!ok && navigator.clipboard) {
        navigator.clipboard.writeText(el.value).then(
            () => showToast("Token copied."),
            () => showToast("Copy failed.")
        );
        return;
    }

    showToast(ok ? "Token copied." : "Copy failed.");
}

function showToast(msg){
    const box = document.querySelector('.success-box');
    if (!box) return;

    let t = box.querySelector('.toast');
    if (!t){
        t = document.createElement('div');
        t.className = 'toast';
        box.appendChild(t);
    }
    t.textContent = msg;
}
</script>

</body>
</html>
