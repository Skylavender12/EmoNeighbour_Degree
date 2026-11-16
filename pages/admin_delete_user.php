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

// prevent deleting self
if ($user_id == $_SESSION['user_id']) {
    header("Location: admin_users.php");
    exit();
}

$conn->query("DELETE FROM users WHERE id=$user_id");

header("Location: admin_users.php");
exit();
