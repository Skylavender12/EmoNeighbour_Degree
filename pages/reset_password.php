<?php
include '../config/config.php';

$message = "";

// helper: render message box
function box($type, $text) {
    $cls = ($type === 'success') ? 'success-box' : 'error-box';
    return "<div class='$cls'>$text</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email        = trim($_POST['email'] ?? '');
    $token        = trim($_POST['token'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ($email === '' || $token === '' || $password === '' || $confirm_pass === '') {
        $message = box('error', "All fields are required.");
    } elseif ($password !== $confirm_pass) {
        $message = box('error', "Passwords do not match.");
    } else {
        // Find user by email + token
        $sql = "SELECT id FROM users WHERE email = ? AND reset_token = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ss", $email, $token);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $row  = $res->fetch_assoc();
                $uid  = (int)$row['id'];
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Update password + clear token
                $up = "UPDATE users
                       SET password = ?, reset_token = NULL, reset_token_created_at = NULL
                       WHERE id = ?";
                $stmt2 = $conn->prepare($up);

                if ($stmt2) {
                    $stmt2->bind_param("si", $hash, $uid);

                    if ($stmt2->execute()) {
                        $message = box('success', "Password reset successful. You can now log in.");
                    } else {
                        $message = box('error', "Database error updating password.");
                    }
                    $stmt2->close();
                } else {
                    $message = box('error', "Database error preparing update.");
                }
            } else {
                $message = box('error', "Invalid email or token.");
            }

            $stmt->close();
        } else {
            $message = box('error', "Database error preparing request.");
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        /* Keep it consistent with auth.css, just make the Back button look like a button */
        .actions-row{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            margin-top:14px;
        }
        .btn-secondary{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:12px 16px;
            border-radius:10px;
            border:1px solid rgba(0,0,0,0.18);
            background:#fff;
            color:#111;
            text-decoration:none;
            font-weight:800;
        }
        .btn-secondary:hover{ background: rgba(0,0,0,0.04); }
        .btn-secondary:focus{
            outline: 3px solid rgba(9, 132, 227, 0.45);
            outline-offset: 2px;
        }
        /* if your auth.css makes buttons full width, match that on small screens */
        @media (max-width: 520px){
            .actions-row > *{ width:100%; }
        }
        .msg-wrap{ margin-top: 14px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Reset Password</h2>

    <form method="POST" autocomplete="on">
        <label>Email</label>
        <input type="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">

        <label>Reset Token</label>
        <input type="text" name="token" required value="<?= isset($_POST['token']) ? htmlspecialchars($_POST['token']) : '' ?>">

        <label>New Password</label>
        <input type="password" name="password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <div class="actions-row">
            <button type="submit">Reset Password</button>
            <a class="btn-secondary" href="login.php">← Back to Login</a>
        </div>
    </form>

    <?php if ($message): ?>
        <div class="msg-wrap"><?php echo $message; ?></div>
    <?php endif; ?>
</div>

</body>
</html>
