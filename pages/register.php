<?php
session_start();
include '../config/config.php';

$message = "";
$msgType = ""; // 'success' | 'error'

function set_msg(&$message, &$msgType, $type, $text){
    $msgType = $type;
    $message = $text;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $zone     = trim($_POST['zone'] ?? '');
    $pw_raw   = $_POST['password'] ?? '';
    $role     = "resident";

    // Basic validation
    if ($username === '' || $email === '' || $pw_raw === '' || $zone === '') {
        set_msg($message, $msgType, "error", "All fields are required.");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_msg($message, $msgType, "error", "Please enter a valid email.");
    } elseif (!in_array($zone, ['A','B','C','D'], true)) {
        set_msg($message, $msgType, "error", "Please select a valid block.");
    } else {

        // Check duplicate email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            set_msg($message, $msgType, "error", "Database error.");
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();

            if ($res && $res->num_rows > 0) {
                set_msg($message, $msgType, "error", "That email is already registered. Please login.");
            } else {

                $hash = password_hash($pw_raw, PASSWORD_DEFAULT);

                // Insert user (prepared)
                $stmt2 = $conn->prepare("
                    INSERT INTO users (username, email, password, role, zone)
                    VALUES (?, ?, ?, ?, ?)
                ");
                if (!$stmt2) {
                    set_msg($message, $msgType, "error", "Database error.");
                } else {
                    $stmt2->bind_param("sssss", $username, $email, $hash, $role, $zone);
                    $ok = $stmt2->execute();
                    $err = $stmt2->error;
                    $stmt2->close();

                    if ($ok) {
                        set_msg($message, $msgType, "success", "Account created! You may now log in.");
                        // optional: clear fields after success
                        $username = $email = $zone = "";
                    } else {
                        set_msg($message, $msgType, "error", "Error: " . htmlspecialchars($err));
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Register | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        /* Small UI polish while still using auth.css */
        .msg { margin-top: 14px; }
        .success-box, .error-box {
            margin-top: 12px;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
        }
        .success-box { background:#d4ffd8; color:#1a6b2c; }
        .error-box { background:#ffd4d4; color:#7a1a1a; }

        /* Turn the login link into a button-style link */
        .actions-row{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            margin-top: 14px;
        }
        .btn-link{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.18);
            background:#fff;
            color:#111;
            text-decoration:none;
            font-weight: 800;
        }
        .btn-link:hover{ background: rgba(0,0,0,0.04); }

        /* Keep your "Your Block" label looking like the screenshot */
        .field-label{
            font-weight: 800;
            margin-top: 10px;
            display:block;
        }

        @media (max-width: 520px){
            .actions-row > *{ width:100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Create Resident Account</h2>

    <form method="POST" autocomplete="on">
        <input
            type="text"
            name="username"
            placeholder="Full Name"
            required
            value="<?= htmlspecialchars($username ?? '') ?>"
        >

        <input
            type="email"
            name="email"
            placeholder="Email"
            required
            value="<?= htmlspecialchars($email ?? '') ?>"
        >

        <label class="field-label">Your Block</label>
        <select name="zone" required>
            <option value="">-- Select Block --</option>
            <option value="A" <?= (($zone ?? '') === 'A') ? 'selected' : '' ?>>Block A</option>
            <option value="B" <?= (($zone ?? '') === 'B') ? 'selected' : '' ?>>Block B</option>
            <option value="C" <?= (($zone ?? '') === 'C') ? 'selected' : '' ?>>Block C</option>
            <option value="D" <?= (($zone ?? '') === 'D') ? 'selected' : '' ?>>Block D</option>
        </select>

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >

        <div class="actions-row">
            <button type="submit">Register</button>
            <a class="btn-link" href="login.php">Already have an account? Login</a>
        </div>
    </form>

    <?php if (!empty($message)): ?>
        <div class="msg">
            <?php if ($msgType === 'success'): ?>
                <div class="success-box"><?= $message ?></div>
            <?php else: ?>
                <div class="error-box"><?= $message ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
