<?php
session_start();
include '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_users.php");
    exit();
}

$user_id = (int) $_GET['id'];
$res = $conn->query("SELECT * FROM users WHERE id=$user_id");
if (!$res || $res->num_rows == 0) {
    header("Location: admin_users.php");
    exit();
}
$user = $res->fetch_assoc();

$message = "";
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'resident';
    $zone     = $_POST['zone'] ?? '-';

    if ($username === '' || $email === '' || $role === '') {
        $message = "All fields are required.";
        $msgType = "error";
    } else {
        // Only residents have zones
        if ($role !== 'resident') {
            $zone = '-';
        }

        $usernameEsc = $conn->real_escape_string($username);
        $emailEsc    = $conn->real_escape_string($email);
        $roleEsc     = $conn->real_escape_string($role);
        $zoneEsc     = $conn->real_escape_string($zone);

        $sql = "
            UPDATE users
            SET username='$usernameEsc',
                email='$emailEsc',
                role='$roleEsc',
                zone='$zoneEsc'
            WHERE id=$user_id
        ";

        if ($conn->query($sql)) {
            $message = "User updated successfully.";
            $msgType = "success";

            $res = $conn->query("SELECT * FROM users WHERE id=$user_id");
            if ($res && $res->num_rows > 0) {
                $user = $res->fetch_assoc();
            }
        } else {
            $message = "Database error: " . $conn->error;
            $msgType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit User | Admin</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        .single-card-wrap{ max-width: 820px; margin: 0 auto; padding: 25px; }
        .single-card{ background:#fff; border-radius: var(--radius); box-shadow: var(--shadow); border:1px solid rgba(0,0,0,0.06); overflow:hidden; }
        .single-card:hover{ box-shadow: var(--shadow2); }

        .single-head{ padding: 22px 22px 10px; }
        .single-title{ margin:0; font-size: 34px; font-weight: 900; color: var(--text); }
        .single-sub{ margin: 6px 0 0; opacity: .78; font-weight: 600; }

        .single-body{ padding: 18px 22px 22px; }
        .field{ margin-bottom: 14px; }
        label{ display:block; font-weight: 900; margin-bottom: 6px; }

        input, select{
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.18);
            outline: none;
            font-size: 14px;
            background: #fff;
        }
        input:focus, select:focus{
            border-color: rgba(25,118,210,0.65);
            box-shadow: 0 0 0 4px rgba(25,118,210,0.18);
        }

        .btn-primary{
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 12px;
            background: #1976d2;
            color: #fff;
            font-weight: 900;
            font-size: 15px;
            cursor: pointer;
            margin-top: 6px;
        }
        .btn-primary:hover{ background:#1565c0; }

        .msg-box{
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 800;
            border: 1px solid rgba(0,0,0,0.08);
        }
        .msg-success{ background:#d4ffd8; color:#1a6b2c; border-color: rgba(26,107,44,0.20); }
        .msg-error{ background:#ffd4d4; color:#7a1a1a; border-color: rgba(122,26,26,0.20); }

        .footer-link{
            text-align: center;
            padding: 16px 22px 22px;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        .footer-link a{
            text-decoration: none;
            font-weight: 900;
            color: var(--link);
        }
        .footer-link a:hover{ text-decoration: underline; }
    </style>
</head>

<body>
<div class="single-card-wrap">
    <div class="single-card">

        <div class="single-head">
            <h1 class="single-title">Edit User (ID: <?= (int)$user_id ?>)</h1>
            <p class="single-sub">Update account details and role.</p>
        </div>

        <div class="single-body">

            <?php if ($message !== ""): ?>
                <div class="msg-box <?= $msgType === 'success' ? 'msg-success' : 'msg-error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <div class="field">
                    <label>Role</label>
                    <select name="role" id="roleSelect" onchange="toggleZone()" required>
                        <option value="resident" <?= ($user['role']==='resident' ? 'selected' : '') ?>>Resident</option>
                        <option value="leader"   <?= ($user['role']==='leader'   ? 'selected' : '') ?>>Community Leader</option>
                        <option value="admin"    <?= ($user['role']==='admin'    ? 'selected' : '') ?>>Admin</option>
                    </select>
                </div>

                <div class="field" id="zoneField">
                    <label>Block (Resident Block)</label>
                    <select name="zone">
                        <option value="A" <?= ($user['zone']==='A' ? 'selected' : '') ?>>Block A</option>
                        <option value="B" <?= ($user['zone']==='B' ? 'selected' : '') ?>>Block B</option>
                        <option value="C" <?= ($user['zone']==='C' ? 'selected' : '') ?>>Block C</option>
                        <option value="D" <?= ($user['zone']==='D' ? 'selected' : '') ?>>Block D</option>
                    </select>
                </div>

                <button class="btn-primary" type="submit">Save Changes</button>
            </form>
        </div>

        <div class="footer-link">
            <a href="admin_users.php">← Back to User List</a>
        </div>
    </div>
</div>

<script>
function toggleZone() {
    const role = document.getElementById('roleSelect').value;
    const zoneDiv = document.getElementById('zoneField');
    zoneDiv.style.display = (role === 'resident') ? 'block' : 'none';
}
toggleZone();
</script>
</body>
</html>
