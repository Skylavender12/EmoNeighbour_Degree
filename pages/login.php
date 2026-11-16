<?php
session_start();
include '../config/config.php';
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['zone']     = $user['zone'];

            if ($user['role'] === 'admin') { header("Location: admin_dashboard.php"); exit(); }
            if ($user['role'] === 'leader') { header("Location: leader_dashboard.php"); exit(); }
            if ($user['role'] === 'resident') { header("Location: dashboard.php"); exit(); }

            $message = "❌ Unknown user role.";
        } else {
            $message = "❌ Incorrect password.";
        }
    } else {
        $message = "❌ No account found with that email.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login | EmoNeighbour</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="container">

    <div style="text-align:center; margin-bottom:12px;">
        <img src="../assets/img/logo.png"
             alt="EmoNeighbour Logo"
             style="width:104px; height:104px; object-fit:contain; display:inline-block;">
    </div>

    <h2>Welcome Back</h2>

    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <div class="forgot-row">
            <a href="forgot_password.php">Forgot Password?</a>
            <a href="forgot_username.php">Forgot Username?</a>
        </div>

        <button type="submit" class="btn-width">Login</button>
    </form>

    <?php if (!empty($message)): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <a href="register.php" class="btn-width">Create Account</a>

</div>

</body>
</html>
