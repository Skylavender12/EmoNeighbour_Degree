<?php
include '../config/config.php';

$message = "";

// helper to keep styling consistent with auth.css boxes
function box($type, $text) {
    $cls = ($type === 'success') ? 'success-box' : 'error-box';
    return "<div class='$cls'>$text</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $message = box('error', "Email is required.");
    } else {

        // prepared statement
        $sql = "SELECT username FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $username = htmlspecialchars($row['username']);
                $message = box('success', "Your username is: <b>$username</b>");
            } else {
                $message = box('error', "Email not found.");
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
    <title>Forgot Username</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        /* Make Back to Login look like a button (same approach as reset_password.php) */
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
        .msg-wrap{ margin-top:14px; }
        @media (max-width: 520px){
            .actions-row > *{ width:100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Forgot Username</h2>

    <form method="POST" autocomplete="on">
        <label>Email</label>
        <input type="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">

        <div class="actions-row">
            <button type="submit">Find Username</button>
            <a class="btn-secondary" href="login.php">← Back to Login</a>
        </div>
    </form>

    <?php if ($message): ?>
        <div class="msg-wrap"><?php echo $message; ?></div>
    <?php endif; ?>
</div>

</body>
</html>
